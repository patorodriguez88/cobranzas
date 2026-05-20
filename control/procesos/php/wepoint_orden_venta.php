<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

function responder($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function obtenerCredencialesCaddy($distribuidora = 'Dinter')
{
    $distribuidora = strtoupper(trim($distribuidora));

    if ($distribuidora === 'MISAS') {
        return [
            "empresa" => "Misas",
            "base_url" => "https://api.caddy.com.ar/api",
            "usuario" => "bsosa@momentosinolvidables.com.ar",
            "password" => "momentos2023"
        ];
    }

    return [
        "empresa" => "Dinter",
        "base_url" => "https://api.caddy.com.ar/api",
        "usuario" => "cobranza@dintersa.com.ar",
        "password" => "dinter_123"
    ];
}
function obtenerTokenCaddy($credenciales)
{
    $payload = json_encode([
        "usuario" => $credenciales["usuario"],
        "password" => $credenciales["password"]
    ]);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $credenciales["base_url"] . "/auth",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/json",
            "Content-Length: " . strlen($payload)
        ],
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);

    curl_close($curl);

    file_put_contents(__DIR__ . "/debug_caddy.log",
        "\n\nAUTH CADDY\n" .
        "HTTP: " . $httpCode . "\n" .
        "ERROR: " . $error . "\n" .
        "PAYLOAD: " . $payload . "\n" .
        "RESPONSE: " . $response . "\n",
        FILE_APPEND
    );

    if ($error) {
        throw new Exception("Error auth Caddy: " . $error);
    }

    $data = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Caddy rechazó auth: " . $response);
    }

    $token = $data["result"]["token"] ?? "";

    if ($token == "") {
        throw new Exception("No se recibió token");
    }

    return $token;
}

function crearServicioCaddy($mysqli, $venta, $detalle, $idVenta, $nroOrdenVenta)
{
    $distribuidora = $venta["Distribuidora"] ?? "Dinter";
    $credenciales = obtenerCredencialesCaddy($distribuidora);

    $token = obtenerTokenCaddy($credenciales);

    $payload = [
    "NombreCompleto" => $venta["RazonSocial"] ?? "",
    "Direccion" => $venta["Direccion"] ?? "",
    "Ciudad" => "Córdoba",
    "CodigoPostal" => "5000",
    "Dni" => "223334434",
    "EnviarMail" => true,
    "Mail" => "prodriguez@caddy.com.ar",
    "Telefono" => $venta["Celular"] ?? "",
    "Cantidad" => 1,
    "Servicio" => 3,
    "ValorDeclarado" => (string)($venta["Total"] ?? "0"),
    "Cobranza" => "0",
    "idProveedor" => "VENTA" . $idVenta,
    "Observaciones" => "Orden generada desde Wepoint",
    "WebHook" => "https://mi-sistema.com/webhook/caddy",
    "Origen" => [
        [
            "idProveedor" => "",
            "Nombre" => "",
            "Direccion" => ""
        ]
    ],
    "Box" => [
        [
            "Length" => "10",
            "Width" => "10",
            "Height" => "10",
            "Weight" => "10"
        ]
    ]
];

    $data = enviarServicioCaddy($credenciales["base_url"], $token, $payload);

    if (isset($data["token_expired"]) && $data["token_expired"] == 1) {
        $token = obtenerTokenCaddy($credenciales);
        $payload["token"] = $token;
        $data = enviarServicioCaddy($credenciales["base_url"], $token, $payload);
    }

    $ok =
        !empty($data["success"])
        || (isset($data["status"]) && strtolower($data["status"]) === "ok");

    if (!$ok) {
        throw new Exception(
            "Caddy rechazó el servicio: " .
                json_encode($data, JSON_UNESCAPED_UNICODE)
        );
    }


    $codigoSeguimiento =
        $data["result"]["Codigo_Seguimiento"]
        ?? $data["Codigo_Seguimiento"]
        ?? "";

    $idVentaCaddy =
        $data["result"]["Id_de_Venta"]
        ?? $data["Id_de_Venta"]
        ?? "";

    $tarifa = (float)($data["result"]["Tarifa"] ?? 0);
    $fechaEntrega = $data["result"]["Fecha_Entrega"] ?? "";
    $tituloServicio = $data["result"]["Titulo"] ?? "";
    $responseJson = $mysqli->real_escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
    $codigoSeguimientoSql = $mysqli->real_escape_string($codigoSeguimiento);
    $idVentaCaddySql = $mysqli->real_escape_string($idVentaCaddy);
    $empresaSql = $mysqli->real_escape_string($credenciales["empresa"]);
    $fechaEntregaSql = $mysqli->real_escape_string($fechaEntrega);
    $tituloServicioSql = $mysqli->real_escape_string($tituloServicio);

    $sql = "UPDATE Ventas
        SET 
            caddy_codigo_seguimiento = '$codigoSeguimientoSql',
            caddy_id_venta = '$idVentaCaddySql',
            caddy_empresa = '$empresaSql',
            caddy_response = '$responseJson',
            caddy_created_at = NOW(),
            caddy_tarifa = '$tarifa',
            caddy_fecha_entrega = '$fechaEntregaSql',
            caddy_titulo_servicio = '$tituloServicioSql'    
        WHERE id = '$idVenta'
        LIMIT 1
    ";

    if (!$mysqli->query($sql)) {
        throw new Exception("Caddy creó el servicio, pero no se pudo guardar localmente: " . $mysqli->error);
    }

    return $data;
}

function enviarServicioCaddy($baseUrl, $token, $payload)
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $baseUrl . "/servicios",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            "X-Api-Token: Bearer " . $token,
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);

    curl_close($curl);

    file_put_contents(__DIR__ . "/debug_caddy.log",
        "\n\nSERVICIOS CADDY\n" .
        "FECHA: " . date("Y-m-d H:i:s") . "\n" .
        "URL: " . $baseUrl . "/servicios\n" .
        "HTTP: " . $httpCode . "\n" .
        "ERROR: " . $error . "\n" .
        "TOKEN: " . $token . "\n" .
        "PAYLOAD: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n" .
        "RESPONSE: " . $response . "\n",
        FILE_APPEND
    );

    if ($error) {
        throw new Exception("Error cURL Caddy: " . $error);
    }

    $data = json_decode($response, true);

if ($httpCode == 401 || $httpCode == 403) {
    return [
        "token_expired" => 1,
        "response" => $response
    ];
}

if ($httpCode < 200 || $httpCode >= 300) {
    return [
        "status" => "error",
        "http_code" => $httpCode,
        "response" => $response
    ];
}

return $data;
}

function crearClienteWepointSiNoExiste($mysqli, $cliente, $token)
{
    $idClienteLocal = isset($cliente['idCliente']) ? (int)$cliente['idCliente'] : 0;
    $wepointId = isset($cliente['wepoint_id_cliente']) ? (int)$cliente['wepoint_id_cliente'] : 0;

    if ($wepointId > 0) {
        return $wepointId;
    }

    if ($idClienteLocal <= 0) {
        throw new Exception("La venta no tiene cliente local válido.");
    }

    $payloadCliente = [
        "nombre" => $cliente['RazonSocial'] ?? '',
        "telefono" => $cliente['Celular'] ?? '',
        "email" => "",
        "direccion" => $cliente['Direccion'] ?? '',
        "provincia" => "Cordoba",
        "ciudad" => $cliente['Ciudad'] ?? 'Cordoba',
        "codigo_postal" => "5000",
        "barrio" => "",
        "entre_calles" => ""
    ];

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://sistema.wepoint.ar/api/v2/clientes',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payloadCliente, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);

    curl_close($curl);

    if ($error) {
        throw new Exception("Error cURL creando cliente Wepoint: " . $error);
    }

    $data = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300 || empty($data['success'])) {
        throw new Exception("Wepoint rechazó la creación del cliente: " . $response);
    }

    $nuevoId = isset($data['data']['id_cliente']) ? (int)$data['data']['id_cliente'] : 0;

    if ($nuevoId <= 0) {
        throw new Exception("Wepoint creó el cliente pero no devolvió id_cliente.");
    }

    $responseJson = $mysqli->real_escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));

    $sqlUpdateCliente = "UPDATE Clientes
        SET 
            wepoint_id_cliente = '$nuevoId',
            wepoint_response = '$responseJson',
            wepoint_created_at = NOW()
        WHERE id = '$idClienteLocal'
        LIMIT 1
    ";

    if (!$mysqli->query($sqlUpdateCliente)) {
        throw new Exception("Cliente creado en Wepoint, pero no se pudo guardar localmente: " . $mysqli->error);
    }

    return $nuevoId;
}

$idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;
$idTransportista = isset($_POST['idTransportista']) ? (int)$_POST['idTransportista'] : 0;

if ($idVenta <= 0) {
    responder([
        "success" => false,
        "message" => "ID de venta inválido"
    ]);
}

if ($idTransportista <= 0) {
    responder([
        "success" => false,
        "message" => "Debe seleccionar una forma de entrega."
    ]);
}

function obtenerCredencialesWepoint($distribuidora = 'Dinter')
{
    $distribuidora = trim(strtoupper($distribuidora));

    switch ($distribuidora) {

        case 'MISAS':

            return [
                "empresa" => "Misas",
                "token" => 'TOKEN_MISAS',
                "id_cliente_origen" => 999
            ];

        case 'DINTER':
        default:

            return [
                "empresa" => "Dinter",
                "token" => '1383|1w3olMBz6851a6JdfbA1GH0jdF5QdUnwUtAfehSL0f00e3a5',
                "id_cliente_origen" => 219
            ];
    }
}
// $token = '1383|1w3olMBz6851a6JdfbA1GH0jdF5QdUnwUtAfehSL0f00e3a5';



$sqlEstado = "
    SELECT EstadoPago
    FROM Ventas
    WHERE id = '$idVenta'
    LIMIT 1
";

$resEstado = $mysqli->query($sqlEstado);

if (!$resEstado) {
    responder([
        "success" => false,
        "message" => "Error consultando estado de venta.",
        "mysql_error" => $mysqli->error
    ]);
}

$rowEstado = $resEstado->fetch_assoc();

if (!$rowEstado) {
    responder([
        "success" => false,
        "message" => "Venta inexistente"
    ]);
}

if ($rowEstado['EstadoPago'] != 'PAGADA') {
    responder([
        "success" => false,
        "message" => "La venta debe estar PAGADA para generar la OV."
    ]);
}

$sqlVenta = "SELECT 
        V.*,
        C.id AS idCliente,
        C.RazonSocial,
        C.Celular,
        C.Direccion,
        C.Ciudad,
        C.Ncliente,
        C.Distribuidora,
        C.wepoint_id_cliente
    FROM Ventas V
    LEFT JOIN Clientes C ON C.id = V.idCliente
    WHERE V.id = ?
      AND V.Eliminado = 0
    LIMIT 1
";

$stmt = $mysqli->prepare($sqlVenta);

if (!$stmt) {
    responder([
        "success" => false,
        "message" => "Error preparando sqlVenta",
        "mysql_error" => $mysqli->error
    ]);
}

$stmt->bind_param("i", $idVenta);

if (!$stmt->execute()) {
    responder([
        "success" => false,
        "message" => "Error ejecutando sqlVenta",
        "mysql_error" => $stmt->error
    ]);
}

$resVenta = $stmt->get_result();

if ($resVenta->num_rows === 0) {
    responder([
        "success" => false,
        "message" => "Venta no encontrada o eliminada."
    ]);
}

$venta = $resVenta->fetch_assoc();
$distribuidora = $venta['Distribuidora'] ?? 'Dinter';
$wepoint = obtenerCredencialesWepoint($distribuidora);
$token = $wepoint['token'];

if (!empty($venta['NumeroOrdenVenta'])) {
    responder([
        "success" => true,
        "message" => "La orden ya fue generada anteriormente",
        "id_orden_venta" => isset($venta['wepoint_id_orden_venta']) ? $venta['wepoint_id_orden_venta'] : null,
        "nro_orden_venta" => $venta['NumeroOrdenVenta'],
        "estado" => isset($venta['wepoint_estado']) ? $venta['wepoint_estado'] : null
    ]);
}

try {
    $idClienteWepoint = crearClienteWepointSiNoExiste($mysqli, $venta, $token);
} catch (Exception $e) {
    responder([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$sqlDetalle = "SELECT 
        VD.idProducto,
        VD.Cantidad,
        VD.PrecioUnitario,
        P.idProductoWepoint,
        P.Nombre
    FROM VentasDetalle VD
    INNER JOIN Productos P ON P.id = VD.idProducto
    WHERE VD.idVenta = ?
      AND VD.Eliminado = 0
";

$stmtDet = $mysqli->prepare($sqlDetalle);

if (!$stmtDet) {
    responder([
        "success" => false,
        "message" => "Error preparando detalle de venta.",
        "mysql_error" => $mysqli->error
    ]);
}

$stmtDet->bind_param("i", $idVenta);

if (!$stmtDet->execute()) {
    responder([
        "success" => false,
        "message" => "Error ejecutando detalle de venta.",
        "mysql_error" => $stmtDet->error
    ]);
}

$resDetalle = $stmtDet->get_result();

$detalle = [];

while ($row = $resDetalle->fetch_assoc()) {
    $idProductoWepoint = (int)($row['idProductoWepoint'] ?? 0);

    if ($idProductoWepoint <= 0) {
        responder([
            "success" => false,
            "message" => "El producto " . $row['Nombre'] . " no tiene idProductoWepoint configurado."
        ]);
    }

    $detalle[] = [
        "id_producto" => $idProductoWepoint,
        "cantidad" => (float)$row['Cantidad'],
        "precio" => (float)$row['PrecioUnitario']
    ];
}

if (empty($detalle)) {
    responder([
        "success" => false,
        "message" => "La venta no tiene productos cargados."
    ]);
}

$numeroVenta = isset($venta['NumeroVenta']) ? trim($venta['NumeroVenta']) : $idVenta;
$ncliente = isset($venta['Ncliente']) ? trim($venta['Ncliente']) : '';

$usuarioActual = 'Sistema';

if (isset($_SESSION['user_name']) && $_SESSION['user_name'] != '') {
    $usuarioActual = trim($_SESSION['user_name']);
} elseif (isset($_SESSION['Usuario']) && $_SESSION['Usuario'] != '') {
    $usuarioActual = trim($_SESSION['Usuario']);
}

$referencia = "Venta #" . $numeroVenta;

if ($ncliente != '') {
    $referencia .= " | Cliente " . $ncliente;
}

$referencia .= " | Usuario " . $usuarioActual;

$notas = isset($venta['Observaciones']) ? trim($venta['Observaciones']) : '';

$payload = [
    "no_referencia" => $referencia,
    "fecha" => date('Y-m-d'),
    "notas" => $notas,
    "id_transportista" => (string)$idTransportista,
    "id_cliente" => (string)$idClienteWepoint,
    "destinatario" => [
        "nombre" => $venta['RazonSocial'] ?? '',
        "telefono" => $venta['Celular'] ?? '',
        "email" => '',
        "direccion" => $venta['Direccion'] ?? '',
        "provincia" => "Cordoba",
        "ciudad" => $venta['Ciudad'] ?? 'Cordoba',
        "codigo_postal" => '5000',
        "barrio" => "",
        "entre_calles" => ""
    ],
    "detalle_orden_venta" => $detalle
];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://sistema.wepoint.ar/api/v2/egresos/productos',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ],
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);

curl_close($curl);

if ($error) {
    responder([
        "success" => false,
        "message" => "Error cURL: " . $error
    ]);
}

$data = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300 || empty($data['success'])) {
    responder([
        "success" => false,
        "message" => "Wepoint rechazó la orden",
        "http_code" => $httpCode,
        "response" => $data ?: $response,
        "payload" => $payload
    ]);
}

$idOrdenWepoint = $data['data']['id_orden_venta'] ?? null;
$nroOrdenVenta = $data['data']['nro_orden_venta'] ?? null;
$estado = $data['data']['estado'] ?? null;
$total = isset($data['data']['total']) ? (float)$data['data']['total'] : 0;

if (empty($nroOrdenVenta)) {
    responder([
        "success" => false,
        "message" => "Wepoint creó la orden pero no devolvió nro_orden_venta.",
        "response" => $data
    ]);
}

$responseJson = json_encode($data, JSON_UNESCAPED_UNICODE);
$createdAt = date('Y-m-d H:i:s');

$sqlUpdate = "UPDATE Ventas
    SET 
        NumeroOrdenVenta = ?,
        wepoint_id_orden_venta = ?,
        wepoint_nro_orden_venta = ?,
        wepoint_estado = ?,
        wepoint_total = ?,
        wepoint_response = ?,
        wepoint_created_at = ?
    WHERE id = ?
    LIMIT 1
";

$stmtUpd = $mysqli->prepare($sqlUpdate);

if (!$stmtUpd) {
    responder([
        "success" => false,
        "message" => "Error preparando UPDATE local de la venta.",
        "mysql_error" => $mysqli->error
    ]);
}

$stmtUpd->bind_param(
    "sissdssi",
    $nroOrdenVenta,
    $idOrdenWepoint,
    $nroOrdenVenta,
    $estado,
    $total,
    $responseJson,
    $createdAt,
    $idVenta
);

if (!$stmtUpd->execute()) {
    responder([
        "success" => false,
        "message" => "La OV se generó en Wepoint, pero no se pudo guardar localmente.",
        "error" => $stmtUpd->error,
        "nro_orden_venta" => $nroOrdenVenta
    ]);
}

$caddyData = null;
$caddyError = null;

if ((int)$idTransportista === 1) {
    if (!empty($venta["caddy_codigo_seguimiento"])) {

        $caddyData = [
            "status" => "ok",
            "message" => "Servicio ya creado previamente",
            "codigo" => $venta["caddy_codigo_seguimiento"]
        ];
    } else {
        try {

            $caddyData = crearServicioCaddy(
                $mysqli,
                $venta,
                $detalle,
                $idVenta,
                $nroOrdenVenta
            );
        } catch (Exception $e) {

            $caddyError = $e->getMessage();
        }
    }
}
responder([
    "success" => true,
    "message" => "Orden de venta generada correctamente",
    "id_cliente_wepoint" => $idClienteWepoint,
    "id_orden_venta" => $idOrdenWepoint,
    "nro_orden_venta" => $nroOrdenVenta,
    "estado" => $estado,
    "total" => $total,
    "caddy" => $caddyData,
    "caddy_error" => $caddyError
]);
