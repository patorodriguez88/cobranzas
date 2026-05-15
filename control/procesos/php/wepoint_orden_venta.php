<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;
$idTransportista = isset($_POST['idTransportista']) ? (int)$_POST['idTransportista'] : 0;

if ($idVenta <= 0) {
    echo json_encode(["success" => false, "message" => "ID de venta inválido"]);
    exit;
}

if ($idTransportista <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Debe seleccionar una forma de entrega."
    ]);
    exit;
}

$sqlEstado = "SELECT EstadoPago FROM Ventas WHERE id = '$idVenta' LIMIT 1";
$resEstado = $mysqli->query($sqlEstado);

if (!$resEstado) {
    echo json_encode([
        "success" => false,
        "message" => "Error consultando estado de venta.",
        "mysql_error" => $mysqli->error
    ]);
    exit;
}

$rowEstado = $resEstado->fetch_assoc();

if (!$rowEstado) {
    echo json_encode([
        "success" => false,
        "message" => "Venta inexistente"
    ]);
    exit;
}

if ($rowEstado['EstadoPago'] != 'PAGADA') {
    echo json_encode([
        "success" => false,
        "message" => "La venta debe estar PAGADA para generar la OV."
    ]);
    exit;
}

$token = '1383|1w3olMBz6851a6JdfbA1GH0jdF5QdUnwUtAfehSL0f00e3a5';

$sqlVenta = "
    SELECT 
        V.*,
        C.RazonSocial,
        C.Celular,        
        C.Direccion,
        C.Ciudad,    
        C.Ncliente    
    FROM Ventas V
    LEFT JOIN Clientes C ON C.id = V.idCliente
    WHERE V.id = ?
      AND V.Eliminado = 0
    LIMIT 1
";

$stmt = $mysqli->prepare($sqlVenta);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Error preparando sqlVenta",
        "mysql_error" => $mysqli->error,
        "sql" => $sqlVenta
    ]);
    exit;
}

$stmt->bind_param("i", $idVenta);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Error ejecutando sqlVenta",
        "mysql_error" => $stmt->error
    ]);
    exit;
}

$resVenta = $stmt->get_result();

if ($resVenta->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Venta no encontrada o eliminada."
    ]);
    exit;
}

$venta = $resVenta->fetch_assoc();

if (!empty($venta['NumeroOrdenVenta'])) {
    echo json_encode([
        "success" => true,
        "message" => "La orden ya fue generada anteriormente",
        "id_orden_venta" => isset($venta['wepoint_id_orden_venta']) ? $venta['wepoint_id_orden_venta'] : null,
        "nro_orden_venta" => $venta['NumeroOrdenVenta'],
        "estado" => isset($venta['wepoint_estado']) ? $venta['wepoint_estado'] : null
    ]);
    exit;
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
    echo json_encode([
        "success" => false,
        "message" => "Error preparando detalle de venta.",
        "mysql_error" => $mysqli->error,
        "sql" => $sqlDetalle
    ]);
    exit;
}

$stmtDet->bind_param("i", $idVenta);

if (!$stmtDet->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Error ejecutando detalle de venta.",
        "mysql_error" => $stmtDet->error
    ]);
    exit;
}

$resDetalle = $stmtDet->get_result();

$detalle = [];

while ($row = $resDetalle->fetch_assoc()) {
    $idProductoWepoint = (int)($row['idProductoWepoint'] ?? 0);

    if ($idProductoWepoint <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "El producto " . $row['Nombre'] . " no tiene idProductoWepoint configurado."
        ]);
        exit;
    }

    $detalle[] = [
        "id_producto" => $idProductoWepoint,
        "cantidad" => (float)$row['Cantidad'],
        "precio" => (float)$row['PrecioUnitario']
    ];
}

if (empty($detalle)) {
    echo json_encode([
        "success" => false,
        "message" => "La venta no tiene productos cargados."
    ]);
    exit;
}

$numeroVenta = isset($venta['NumeroVenta'])
    ? trim($venta['NumeroVenta'])
    : $idVenta;

$ncliente = isset($venta['Ncliente'])
    ? trim($venta['Ncliente'])
    : '';

$usuarioActual = isset($_SESSION['user-name'])
    ? trim($_SESSION['user-name'])
    : 'Sistema';

$referencia = "Venta #" . $numeroVenta;

if ($ncliente != '') {
    $referencia .= " | Cliente " . $ncliente;
}

$referencia .= " | Usuario " . $usuarioActual;

$notas = isset($venta['Observaciones'])
    ? trim($venta['Observaciones'])
    : '';

$payload = [
    "no_referencia" => $referencia,
    "fecha" => date('Y-m-d'),
    "notas" => $notas,
    "id_transportista" => (string)$idTransportista,
    "id_cliente" => "219",
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
    echo json_encode([
        "success" => false,
        "message" => "Error cURL: " . $error
    ]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300 || empty($data['success'])) {
    echo json_encode([
        "success" => false,
        "message" => "Wepoint rechazó la orden",
        "http_code" => $httpCode,
        "response" => $data ?: $response,
        "payload" => $payload
    ]);
    exit;
}

$idOrdenWepoint = $data['data']['id_orden_venta'] ?? null;
$nroOrdenVenta = $data['data']['nro_orden_venta'] ?? null;
$estado = $data['data']['estado'] ?? null;
$total = isset($data['data']['total']) ? (float)$data['data']['total'] : 0;

if (empty($nroOrdenVenta)) {
    echo json_encode([
        "success" => false,
        "message" => "Wepoint creó la orden pero no devolvió nro_orden_venta.",
        "response" => $data
    ]);
    exit;
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
    echo json_encode([
        "success" => false,
        "message" => "Error preparando UPDATE local de la venta.",
        "mysql_error" => $mysqli->error,
        "sql" => $sqlUpdate
    ]);
    exit;
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
    echo json_encode([
        "success" => false,
        "message" => "La OV se generó en Wepoint, pero no se pudo guardar localmente.",
        "error" => $stmtUpd->error,
        "nro_orden_venta" => $nroOrdenVenta
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Orden de venta generada correctamente",
    "id_orden_venta" => $idOrdenWepoint,
    "nro_orden_venta" => $nroOrdenVenta,
    "estado" => $estado,
    "total" => $total
]);
