<?php
// Acceso a la API de Wepoint por cuenta: cada distribuidora manda sus órdenes a la cuenta de su
// empresa en Wepoint. Las credenciales están en conexion/config_wepoint.php (fuera del repo; ver
// config_wepoint.sample.php). Una orden ya enviada se consulta o borra con la cuenta que la creó
// (Ventas.wepoint_cuenta), porque en Wepoint cada empresa solo ve sus órdenes.

function wepointConfig()
{
    static $config = null;

    if ($config === null) {
        $archivo = __DIR__ . '/../../../conexion/config_wepoint.php';
        if (!is_file($archivo)) {
            throw new Exception("Falta conexion/config_wepoint.php (credenciales de Wepoint). Ver conexion/config_wepoint.sample.php.");
        }
        $config = require $archivo;
    }

    return $config;
}

// Cuenta de Wepoint para las órdenes NUEVAS de una distribuidora.
function wepointCuentaDeDistribuidora($distribuidora)
{
    $config = wepointConfig();
    $clave = strtoupper(trim($distribuidora ?: 'DINTER'));

    if (empty($config['distribuidoras'][$clave])) {
        throw new Exception("La distribuidora $clave no tiene cuenta de Wepoint configurada.");
    }

    return $config['distribuidoras'][$clave];
}

// Cuenta con la que se creó una orden ya enviada.
function wepointCuentaDeVenta($venta)
{
    if (!empty($venta['wepoint_cuenta'])) {
        return $venta['wepoint_cuenta'];
    }

    return wepointConfig()['cuenta_anterior'];
}

function wepointDatosCuenta($cuenta)
{
    $config = wepointConfig();

    if (empty($config['cuentas'][$cuenta])) {
        throw new Exception("La cuenta de Wepoint '$cuenta' no está configurada.");
    }

    return $config['cuentas'][$cuenta];
}

function wepointHttp($metodo, $ruta, $cuerpo, $token)
{
    $headers = ['Accept: application/json'];
    if ($cuerpo !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => rtrim(wepointConfig()['base_url'], '/') . $ruta,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($cuerpo !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    }

    $respuesta = curl_exec($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    return [
        'http' => $http,
        'body' => $respuesta,
        'data' => json_decode((string)$respuesta, true),
        'error' => $error,
    ];
}

// Token de la cuenta: el fijo de la configuración o, con email y password, el guardado en
// WepointTokens (se inicia sesión si no hay o si $renovar). Los tokens de Wepoint no vencen.
function wepointToken($mysqli, $cuenta, $renovar = false)
{
    $datos = wepointDatosCuenta($cuenta);

    if (!empty($datos['token'])) {
        return $datos['token'];
    }

    if (empty($datos['email']) || empty($datos['password'])) {
        throw new Exception("La cuenta de Wepoint '$cuenta' no tiene token ni email y contraseña configurados.");
    }

    $cuentaSql = $mysqli->real_escape_string($cuenta);

    if (!$renovar) {
        $res = $mysqli->query("SELECT token FROM WepointTokens WHERE cuenta = '$cuentaSql' LIMIT 1");
        if ($res && ($fila = $res->fetch_assoc()) && $fila['token'] !== '') {
            return $fila['token'];
        }
    }

    $login = wepointHttp('POST', '/auth/login', ['email' => $datos['email'], 'password' => $datos['password']], null);
    $token = $login['data']['data']['token'] ?? '';

    if ($login['error'] || $token === '') {
        $motivo = $login['error'] ?: ($login['data']['message'] ?? ('HTTP ' . $login['http']));
        throw new Exception("No se pudo iniciar sesión en Wepoint con {$datos['email']}: $motivo");
    }

    $tokenSql = $mysqli->real_escape_string($token);
    $mysqli->query("REPLACE INTO WepointTokens (cuenta, token, updated_at) VALUES ('$cuentaSql', '$tokenSql', NOW())");

    return $token;
}

// Pedido a la API con el token de la cuenta. Si Wepoint rechaza el token guardado (401), inicia
// sesión de nuevo y reintenta una vez.
function wepointPedido($mysqli, $cuenta, $metodo, $ruta, $cuerpo = null)
{
    $resultado = wepointHttp($metodo, $ruta, $cuerpo, wepointToken($mysqli, $cuenta));

    if ($resultado['http'] == 401 && empty(wepointDatosCuenta($cuenta)['token'])) {
        $resultado = wepointHttp($metodo, $ruta, $cuerpo, wepointToken($mysqli, $cuenta, true));
    }

    return $resultado;
}
