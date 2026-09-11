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

function fechaLimiteInformeExigible($valor)
{
    $valor = trim((string)$valor);
    if ($valor === '') {
        return [null, true];
    }

    $fecha = DateTime::createFromFormat('Y-m-d\TH:i', $valor) ?: DateTime::createFromFormat('Y-m-d H:i:s', $valor);
    if (!$fecha) {
        return [null, false];
    }

    return [$fecha->format('Y-m-d H:i:s'), true];
}

function prepararHistorialExigible($mysqli)
{
    return $mysqli->query("CREATE TABLE IF NOT EXISTS Cobranza_exigible_importaciones (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        Archivo VARCHAR(255) NOT NULL,
        Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Usuario VARCHAR(150) NOT NULL,
        CantidadFilas INT UNSIGNED NOT NULL DEFAULT 0,
        MensajesIniciados INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function prepararDetalleExigible($mysqli)
{
    return $mysqli->query("CREATE TABLE IF NOT EXISTS Cobranza_exigible_detalle (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ImportacionId INT UNSIGNED NOT NULL,
        Fecha DATE NULL,
        Ncliente VARCHAR(50) NOT NULL,
        RazonSocial VARCHAR(255) NOT NULL DEFAULT '',
        Recorrido VARCHAR(100) NOT NULL DEFAULT '',
        Celular VARCHAR(50) NOT NULL DEFAULT '',
        Dni VARCHAR(50) NOT NULL DEFAULT '',
        Distribuidora VARCHAR(100) NOT NULL DEFAULT 'DINTER',
        Exigible DECIMAL(12,2) NOT NULL DEFAULT 0,
        Encontrado TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_importacion (ImportacionId),
        KEY idx_importacion_cliente (ImportacionId, Ncliente)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function prepararMensajesExigible($mysqli)
{
    return $mysqli->query("CREATE TABLE IF NOT EXISTS Cobranza_exigible_mensajes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ImportacionId INT UNSIGNED NOT NULL,
        Ncliente VARCHAR(50) NOT NULL,
        Celular VARCHAR(50) NOT NULL,
        Mensaje TEXT NOT NULL,
        Fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        Usuario VARCHAR(150) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_importacion_cliente (ImportacionId, Ncliente)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function asegurarColumnaFechaLimiteExigible($mysqli)
{
    $existe = $mysqli->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Cobranza_exigible_importaciones' AND COLUMN_NAME = 'FechaLimiteInforme'");
    if ($existe && $existe->num_rows === 0) {
        $mysqli->query('ALTER TABLE Cobranza_exigible_importaciones ADD COLUMN FechaLimiteInforme DATETIME NULL AFTER MensajesIniciados');
    }
}

function obtenerDetalleExigible($mysqli, $importacionId)
{
    $filas = [];
    $stmtDetalle = $mysqli->prepare(
        'SELECT Ncliente, RazonSocial, Recorrido, Celular, Dni, Distribuidora, Exigible, Encontrado, Fecha
         FROM Cobranza_exigible_detalle WHERE ImportacionId = ? ORDER BY id ASC'
    );
    $stmtDetalle->bind_param('i', $importacionId);
    $stmtDetalle->execute();
    $resultadoDetalle = $stmtDetalle->get_result();
    while ($fila = $resultadoDetalle->fetch_assoc()) {
        $fila['Exigible'] = (float)$fila['Exigible'];
        $fila['Encontrado'] = (int)$fila['Encontrado'];
        $filas[] = $fila;
    }
    $stmtDetalle->close();

    $envios = [];
    $stmtMensajes = $mysqli->prepare(
        'SELECT Ncliente, Fecha, Usuario FROM Cobranza_exigible_mensajes WHERE ImportacionId = ? ORDER BY Fecha ASC'
    );
    $stmtMensajes->bind_param('i', $importacionId);
    $stmtMensajes->execute();
    $resultadoMensajes = $stmtMensajes->get_result();
    while ($mensaje = $resultadoMensajes->fetch_assoc()) {
        $ncliente = $mensaje['Ncliente'];
        if (!isset($envios[$ncliente])) {
            $envios[$ncliente] = ['CantidadEnvios' => 0];
        }
        $envios[$ncliente]['CantidadEnvios']++;
        $envios[$ncliente]['UltimoEnvio'] = $mensaje['Fecha'];
        $envios[$ncliente]['UltimoEnvioUsuario'] = $mensaje['Usuario'];
    }
    $stmtMensajes->close();

    foreach ($filas as &$fila) {
        $info = $envios[$fila['Ncliente']] ?? null;
        $fila['UltimoEnvio'] = $info['UltimoEnvio'] ?? null;
        $fila['UltimoEnvioUsuario'] = $info['UltimoEnvioUsuario'] ?? null;
        $fila['CantidadEnvios'] = $info['CantidadEnvios'] ?? 0;
    }
    unset($fila);

    return $filas;
}

function usuarioExigible()
{
    return trim((string)($_SESSION['name'] ?? $_SESSION['user_name'] ?? $_SESSION['user_control'] ?? 'Usuario desconocido'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderExigible(['success' => 0, 'error' => 'Acción inválida.'], 400);
}

$accion = $_POST['accion'] ?? '';

$accionesConHistorial = ['ultimo_archivo', 'registrar_mensaje', 'procesar_exigible', 'actualizar_fecha_limite'];
if (in_array($accion, $accionesConHistorial, true)) {
    if (!prepararHistorialExigible($mysqli) || !prepararDetalleExigible($mysqli) || !prepararMensajesExigible($mysqli)) {
        responderExigible(['success' => 0, 'error' => 'No se pudo preparar el historial de importaciones.'], 500);
    }
    asegurarColumnaFechaLimiteExigible($mysqli);
}

if ($accion === 'ultimo_archivo') {
    $resultadoUltimo = $mysqli->query('SELECT id, Archivo, Fecha, Usuario, CantidadFilas, MensajesIniciados, FechaLimiteInforme FROM Cobranza_exigible_importaciones ORDER BY id DESC LIMIT 1');
    $ultimo = $resultadoUltimo ? $resultadoUltimo->fetch_assoc() : null;
    $filas = $ultimo ? obtenerDetalleExigible($mysqli, (int)$ultimo['id']) : [];
    responderExigible(['success' => 1, 'data' => $ultimo, 'filas' => $filas]);
}

if ($accion === 'registrar_mensaje') {
    $importacionId = (int)($_POST['importacion_id'] ?? 0);
    $ncliente = trim((string)($_POST['ncliente'] ?? ''));
    $celular = preg_replace('/\D/', '', (string)($_POST['celular'] ?? ''));
    $mensaje = trim((string)($_POST['mensaje'] ?? ''));

    if ($importacionId <= 0 || $ncliente === '' || $celular === '' || $mensaje === '') {
        responderExigible(['success' => 0, 'error' => 'Datos incompletos para registrar el envío.'], 400);
    }

    $usuario = usuarioExigible();

    $stmtMensaje = $mysqli->prepare('INSERT INTO Cobranza_exigible_mensajes (ImportacionId, Ncliente, Celular, Mensaje, Usuario) VALUES (?, ?, ?, ?, ?)');
    $stmtMensaje->bind_param('issss', $importacionId, $ncliente, $celular, $mensaje, $usuario);
    $stmtMensaje->execute();
    $stmtMensaje->close();

    $stmtContador = $mysqli->prepare('UPDATE Cobranza_exigible_importaciones SET MensajesIniciados = MensajesIniciados + 1 WHERE id = ?');
    $stmtContador->bind_param('i', $importacionId);
    $stmtContador->execute();
    $stmtContador->close();

    responderExigible(['success' => 1, 'fecha' => date('Y-m-d H:i:s'), 'usuario' => $usuario]);
}

if ($accion === 'actualizar_fecha_limite') {
    $importacionId = (int)($_POST['importacion_id'] ?? 0);
    if ($importacionId <= 0) {
        responderExigible(['success' => 0, 'error' => 'Importación inválida.'], 400);
    }

    [$fechaLimiteSql, $fechaLimiteValida] = fechaLimiteInformeExigible($_POST['fecha_limite'] ?? '');
    if (!$fechaLimiteValida) {
        responderExigible(['success' => 0, 'error' => 'La fecha y hora ingresadas no son válidas.'], 400);
    }

    $stmtFecha = $mysqli->prepare('UPDATE Cobranza_exigible_importaciones SET FechaLimiteInforme = ? WHERE id = ?');
    $stmtFecha->bind_param('si', $fechaLimiteSql, $importacionId);
    $stmtFecha->execute();
    $stmtFecha->close();

    responderExigible(['success' => 1, 'fecha_limite' => $fechaLimiteSql]);
}

if ($accion === 'actualizar_telefono') {
    $ncliente = trim((string)($_POST['ncliente'] ?? ''));
    $celular = preg_replace('/\D/', '', (string)($_POST['celular'] ?? ''));

    if ($ncliente === '' || strlen($celular) < 8 || strlen($celular) > 15) {
        responderExigible(['success' => 0, 'error' => 'El número de teléfono no es válido.'], 400);
    }

    $stmtTelefono = $mysqli->prepare('UPDATE Clientes SET Celular = ? WHERE Ncliente = ? LIMIT 1');
    if (!$stmtTelefono) {
        responderExigible(['success' => 0, 'error' => 'No se pudo preparar la actualización.'], 500);
    }

    $stmtTelefono->bind_param('ss', $celular, $ncliente);
    $stmtTelefono->execute();
    $actualizado = $stmtTelefono->affected_rows >= 0;
    $stmtTelefono->close();

    responderExigible([
        'success' => $actualizado ? 1 : 0,
        'error' => $actualizado ? null : 'No se pudo actualizar el cliente.',
        'celular' => $celular,
    ], $actualizado ? 200 : 500);
}

if ($accion !== 'procesar_exigible') {
    responderExigible(['success' => 0, 'error' => 'Acción inválida.'], 400);
}

[$fechaLimiteSql, $fechaLimiteValida] = fechaLimiteInformeExigible($_POST['fecha_limite'] ?? '');
if (!$fechaLimiteValida) {
    responderExigible(['success' => 0, 'error' => 'La fecha y hora límite ingresadas no son válidas.'], 400);
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
    'SELECT Ncliente, RazonSocial, Recorrido, Celular, Dni, Distribuidora FROM Clientes WHERE Ncliente = ? LIMIT 1'
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
        'Distribuidora' => $cliente['Distribuidora'] ?? 'DINTER',
        'Exigible' => $importe,
        'Encontrado' => $cliente ? 1 : 0,
    ];
}

$stmt->close();
fclose($handle);

$archivo = basename((string)$_FILES['archivo']['name']);
$usuario = usuarioExigible();
$cantidadFilas = count($filas);
$stmtImportacion = $mysqli->prepare('INSERT INTO Cobranza_exigible_importaciones (Archivo, Usuario, CantidadFilas, FechaLimiteInforme) VALUES (?, ?, ?, ?)');
if (!$stmtImportacion) {
    responderExigible(['success' => 0, 'error' => 'No se pudo registrar la importación.'], 500);
}
$stmtImportacion->bind_param('ssis', $archivo, $usuario, $cantidadFilas, $fechaLimiteSql);
$stmtImportacion->execute();
$importacionId = $stmtImportacion->insert_id;
$stmtImportacion->close();

if ($filas) {
    $stmtDetalle = $mysqli->prepare(
        'INSERT INTO Cobranza_exigible_detalle (ImportacionId, Fecha, Ncliente, RazonSocial, Recorrido, Celular, Dni, Distribuidora, Exigible, Encontrado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($filas as $fila) {
        $stmtDetalle->bind_param(
            'isssssssdi',
            $importacionId,
            $fila['Fecha'],
            $fila['Ncliente'],
            $fila['RazonSocial'],
            $fila['Recorrido'],
            $fila['Celular'],
            $fila['Dni'],
            $fila['Distribuidora'],
            $fila['Exigible'],
            $fila['Encontrado']
        );
        $stmtDetalle->execute();
    }
    $stmtDetalle->close();
}

responderExigible([
    'success' => 1,
    'data' => $filas,
    'omitidas' => $omitidas,
    'importacion' => [
        'id' => $importacionId,
        'Archivo' => $archivo,
        'Fecha' => date('Y-m-d H:i:s'),
        'Usuario' => $usuario,
        'CantidadFilas' => $cantidadFilas,
        'MensajesIniciados' => 0,
        'FechaLimiteInforme' => $fechaLimiteSql,
    ],
]);
