<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

function responder($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function calcularNclienteInterno($distribuidora, $nclienteExcel)
{
    $distribuidora = strtoupper(trim($distribuidora));
    $nclienteExcel = (int)$nclienteExcel;

    if ($distribuidora === 'RAK') {
        return 9000000 + $nclienteExcel;
    }

    if ($distribuidora === 'MISAS') {
        return 8000000 + $nclienteExcel;
    }

    return $nclienteExcel;
}

function limpiar($valor)
{
    $valor = trim((string)$valor);
    $valor = str_replace("\xEF\xBB\xBF", "", $valor);
    return $valor;
}

function detectarSeparador($linea)
{
    $cantidadPuntoComa = substr_count($linea, ';');
    $cantidadComa = substr_count($linea, ',');

    return ($cantidadPuntoComa >= $cantidadComa) ? ';' : ',';
}

if ($accion === 'preview_importacion_clientes') {

    if (!isset($_FILES['archivo'])) {
        responder([
            "success" => 0,
            "error" => "No se recibió archivo."
        ]);
    }

    $distribuidora = isset($_POST['distribuidora']) ? strtoupper(trim($_POST['distribuidora'])) : 'DINTER';

    if ($distribuidora == '') {
        $distribuidora = 'DINTER';
    }

    $tmp = $_FILES['archivo']['tmp_name'];

    if (!file_exists($tmp)) {
        responder([
            "success" => 0,
            "error" => "Archivo temporal no encontrado."
        ]);
    }

    $handle = fopen($tmp, 'r');

    if (!$handle) {
        responder([
            "success" => 0,
            "error" => "No se pudo abrir el archivo."
        ]);
    }

    $primeraLinea = fgets($handle);
    rewind($handle);

    $separador = detectarSeparador($primeraLinea);

    $data = [];
    $fila = 0;

    while (($row = fgetcsv($handle, 10000, $separador)) !== false) {

        $fila++;

        if ($fila == 1) {
            continue;
        }

        $nclienteExcel = isset($row[0]) ? limpiar($row[0]) : '';
        $razonSocial   = isset($row[1]) ? limpiar($row[1]) : '';
        $cuit          = isset($row[2]) ? limpiar($row[2]) : '';
        $direccion     = isset($row[3]) ? limpiar($row[3]) : '';
        $ciudad        = isset($row[4]) ? limpiar($row[4]) : '';
        $telefono      = isset($row[5]) ? limpiar($row[5]) : '';
        $celular       = isset($row[6]) ? limpiar($row[6]) : '';

        if ($nclienteExcel == '' && $razonSocial == '') {
            continue;
        }

        $nclienteInterno = calcularNclienteInterno($distribuidora, $nclienteExcel);

        $existe = 0;
        $mensaje = "Nuevo cliente";

        if ($nclienteInterno <= 0) {
            $existe = 1;
            $mensaje = "Ncliente inválido";
        } elseif ($razonSocial == '') {
            $existe = 1;
            $mensaje = "Razón social vacía";
        } else {
            $sqlExiste = "
                SELECT Ncliente, RazonSocial
                FROM Clientes
                WHERE Ncliente = '$nclienteInterno'
                LIMIT 1
            ";

            $resExiste = $mysqli->query($sqlExiste);

            if ($resExiste && $resExiste->num_rows > 0) {
                $clienteExistente = $resExiste->fetch_assoc();
                $existe = 1;
                $mensaje = "Ya existe: " . $clienteExistente['RazonSocial'];
            }
        }

        $data[] = [
            "existe"       => $existe,
            "mensaje"      => $mensaje,
            "Ncliente"     => $nclienteInterno,
            "NclienteExcel" => $nclienteExcel,
            "RazonSocial"  => $razonSocial,
            "Cuit"         => $cuit,
            "Direccion"    => $direccion,
            "Ciudad"       => $ciudad,
            "Telefono"     => $telefono,
            "Celular"      => $celular,
            "Distribuidora" => $distribuidora
        ];
    }

    fclose($handle);

    responder([
        "success" => 1,
        "data" => $data
    ]);
}

if ($accion === 'importar_clientes') {

    $clientesJson = isset($_POST['clientes']) ? $_POST['clientes'] : '[]';
    $clientes = json_decode($clientesJson, true);

    if (!is_array($clientes) || count($clientes) == 0) {
        responder([
            "success" => 0,
            "error" => "No hay clientes para importar."
        ]);
    }

    $insertados = 0;

    $mysqli->begin_transaction();

    try {

        foreach ($clientes as $c) {

            if (isset($c['existe']) && (int)$c['existe'] == 1) {
                continue;
            }

            $ncliente = isset($c['Ncliente']) ? (int)$c['Ncliente'] : 0;

            if ($ncliente <= 0) {
                continue;
            }

            $razonSocial  = $mysqli->real_escape_string(limpiar($c['RazonSocial'] ?? ''));
            $cuit         = $mysqli->real_escape_string(limpiar($c['Cuit'] ?? ''));
            $direccion    = $mysqli->real_escape_string(limpiar($c['Direccion'] ?? ''));
            $ciudad       = $mysqli->real_escape_string(limpiar($c['Ciudad'] ?? ''));
            $telefono     = $mysqli->real_escape_string(limpiar($c['Telefono'] ?? ''));
            $celular      = $mysqli->real_escape_string(limpiar($c['Celular'] ?? ''));
            $distribuidora = $mysqli->real_escape_string(strtoupper(limpiar($c['Distribuidora'] ?? 'DINTER')));

            if ($razonSocial == '') {
                continue;
            }

            $sqlCheck = "
                SELECT Ncliente
                FROM Clientes
                WHERE Ncliente = '$ncliente'
                LIMIT 1
            ";

            $resCheck = $mysqli->query($sqlCheck);

            if ($resCheck && $resCheck->num_rows > 0) {
                continue;
            }

            $sqlInsert = "
                INSERT INTO Clientes
                (
                    Ncliente,
                    RazonSocial,
                    Cuit,
                    Direccion,
                    Ciudad,
                    Telefono,
                    Celular,
                    Distribuidora
                )
                VALUES
                (
                    '$ncliente',
                    '$razonSocial',
                    '$cuit',
                    '$direccion',
                    '$ciudad',
                    '$telefono',
                    '$celular',
                    '$distribuidora'
                )
            ";

            if (!$mysqli->query($sqlInsert)) {
                throw new Exception($mysqli->error);
            }

            $insertados++;
        }

        $mysqli->commit();

        responder([
            "success" => 1,
            "insertados" => $insertados
        ]);
    } catch (Exception $e) {

        $mysqli->rollback();

        responder([
            "success" => 0,
            "error" => $e->getMessage()
        ]);
    }
}

responder([
    "success" => 0,
    "error" => "Acción inválida."
]);
