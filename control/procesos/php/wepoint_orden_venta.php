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
        "provincia" => "Córdoba",
        "ciudad" => $cliente['Ciudad'] ?? 'Córdoba',
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

    $sqlUpdateCliente = "
        UPDATE Clientes
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

/*
    Por ahora dejamos Dinter.
    Luego agregamos token/id_empresa para Misas según Clientes.Distribuidora.
*/
$token = '1383|1w3olMBz6851a6JdfbA1GH0jdF5QdUnwUtAfehSL0f00e3a5';

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

$sqlVenta = "
    SELECT 
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

$sqlDetalle = "
    SELECT 
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

$sqlUpdate = "
    UPDATE Ventas
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

responder([
    "success" => true,
    "message" => "Orden de venta generada correctamente",
    "id_cliente_wepoint" => $idClienteWepoint,
    "id_orden_venta" => $idOrdenWepoint,
    "nro_orden_venta" => $nroOrdenVenta,
    "estado" => $estado,
    "total" => $total
]);
