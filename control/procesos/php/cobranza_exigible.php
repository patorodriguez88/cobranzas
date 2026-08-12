<?php
ini_set('display_errors', 0);
session_start();
include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

function responderExigible($datos, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function limpiarCampoExigible($valor)
{
    return trim(str_replace("\xEF\xBB\xBF", '', (string)$valor));
}

function fechaExigible($valor)
{
    $valor = preg_replace('/\D/', '', $valor);
    if (strlen($valor) !== 8) {
        return null;
    }

    $fecha = DateTime::createFromFormat('!Ymd', $valor);
    return $fecha && $fecha->format('Ymd') === $valor ? $fecha->format('Y-m-d') : null;
}

function importeExigible($valor)
{
    $valor = preg_replace('/[^0-9,.-]/', '', (string)$valor);
    if ($valor === '') {
        return null;
    }

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return is_numeric($valor) ? (float)$valor : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['accion'] ?? '') !== 'procesar_exigible') {
    responderExigible(['success' => 0, 'error' => 'Acción inválida.'], 400);
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    responderExigible(['success' => 0, 'error' => 'No se recibió un archivo válido.'], 400);
}

$extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
if ($extension !== 'csv') {
    responderExigible(['success' => 0, 'error' => 'El archivo debe tener extensión CSV.'], 400);
}

$handle = fopen($_FILES['archivo']['tmp_name'], 'r');
if (!$handle) {
    responderExigible(['success' => 0, 'error' => 'No se pudo abrir el archivo.'], 400);
}

$stmt = $mysqli->prepare(
    'SELECT Ncliente, RazonSocial, Recorrido, Celular, Dni FROM Clientes WHERE Ncliente = ? LIMIT 1'
);
if (!$stmt) {
    fclose($handle);
    responderExigible(['success' => 0, 'error' => 'No se pudo consultar la base de clientes.'], 500);
}

$filas = [];
$omitidas = 0;
$numeroFila = 0;

while (($campos = fgetcsv($handle, 10000, ';')) !== false) {
    $numeroFila++;
    if (count($campos) < 3) {
        $omitidas++;
        continue;
    }

    $fecha = fechaExigible(limpiarCampoExigible($campos[0]));
    $numeroOriginal = limpiarCampoExigible($campos[1]);
    $importe = importeExigible(limpiarCampoExigible($campos[2]));

    // Permite que un archivo futuro incluya encabezados sin generar una fila inválida.
    if ($numeroFila === 1 && (!$fecha || $importe === null)) {
        continue;
    }
    if (!$fecha || $numeroOriginal === '' || $importe === null) {
        $omitidas++;
        continue;
    }

    $numeroConsulta = ltrim($numeroOriginal, '0');
    if ($numeroConsulta === '') {
        $numeroConsulta = '0';
    }

    $stmt->bind_param('s', $numeroConsulta);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $cliente = $resultado ? $resultado->fetch_assoc() : null;

    $filas[] = [
        'Fecha' => $fecha,
        'Ncliente' => $cliente['Ncliente'] ?? $numeroOriginal,
        'RazonSocial' => $cliente['RazonSocial'] ?? 'Cliente no encontrado',
        'Recorrido' => $cliente['Recorrido'] ?? '',
        'Celular' => $cliente['Celular'] ?? '',
        'Dni' => $cliente['Dni'] ?? '',
        'Exigible' => $importe,
        'Encontrado' => $cliente ? 1 : 0,
    ];
}

$stmt->close();
fclose($handle);

responderExigible([
    'success' => 1,
    'data' => $filas,
    'omitidas' => $omitidas,
]);
