<?php
include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;

if ($idVenta <= 0) {
    echo json_encode(["success" => false, "message" => "ID de venta inválido"]);
    exit;
}

/*
IMPORTANTE:
Idealmente guardar este token fuera del código:
- tabla Configuracion
- archivo .env
- constante en config privado
*/
$token = '1383|1w3olMBz6851a6JdfbA1GH0jdF5QdUnwUtAfehSL0f00e3a5';

$sqlVenta = "
    SELECT *
    FROM Ventas
    WHERE id = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sqlVenta);
$stmt->bind_param("i", $idVenta);
$stmt->execute();
$resVenta = $stmt->get_result();

if ($resVenta->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Venta no encontrada"]);
    exit;
}

$venta = $resVenta->fetch_assoc();

if (!empty($venta['wepoint_nro_orden_venta'])) {
    echo json_encode([
        "success" => true,
        "message" => "La orden ya fue generada anteriormente",
        "nro_orden_venta" => $venta['wepoint_nro_orden_venta'],
        "id_orden_venta" => $venta['wepoint_id_orden_venta']
    ]);
    exit;
}

/*
Ajustar nombre de tabla/campos según cómo hayas armado el detalle.
Ejemplo: VentasDetalle
*/
$sqlDetalle = "
    SELECT 
        vd.id_producto_wepoint,
        vd.cantidad,
        vd.precio
    FROM VentasDetalle vd
    WHERE vd.idVenta = ?
";

$stmtDet = $mysqli->prepare($sqlDetalle);
$stmtDet->bind_param("i", $idVenta);
$stmtDet->execute();
$resDetalle = $stmtDet->get_result();

$detalle = [];

while ($row = $resDetalle->fetch_assoc()) {
    $detalle[] = [
        "id_producto" => (int)$row['id_producto_wepoint'],
        "cantidad" => (float)$row['cantidad'],
        "precio" => (float)$row['precio']
    ];
}

if (empty($detalle)) {
    echo json_encode(["success" => false, "message" => "La venta no tiene productos cargados"]);
    exit;
}

$payload = [
    "no_referencia" => (string)$idVenta,
    "fecha" => date('Y-m-d'),
    "notas" => isset($venta['Observaciones']) ? $venta['Observaciones'] : '',
    "id_transportista" => "2",
    "id_cliente" => "219",
    "destinatario" => [
        "nombre" => $venta['Cliente'] ?? $venta['RazonSocial'] ?? '',
        "telefono" => $venta['Telefono'] ?? '',
        "email" => $venta['Email'] ?? '',
        "direccion" => $venta['Direccion'] ?? '',
        "provincia" => $venta['Provincia'] ?? 'Cordoba',
        "ciudad" => $venta['Ciudad'] ?? 'Cordoba',
        "codigo_postal" => $venta['CodigoPostal'] ?? '5000',
        "barrio" => $venta['Barrio'] ?? '',
        "entre_calles" => $venta['EntreCalles'] ?? ''
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
        "message" => "Error cURL",
        "error" => $error
    ]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300 || empty($data['success'])) {
    echo json_encode([
        "success" => false,
        "message" => "Wepoint rechazó la orden",
        "http_code" => $httpCode,
        "response" => $data ?: $response
    ]);
    exit;
}

$idOrdenWepoint = $data['data']['id_orden_venta'] ?? null;
$nroOrdenVenta = $data['data']['nro_orden_venta'] ?? null;
$estado = $data['data']['estado'] ?? null;
$total = $data['data']['total'] ?? 0;

$sqlUpdate = "
    UPDATE Ventas
    SET 
        wepoint_id_orden_venta = ?,
        wepoint_nro_orden_venta = ?,
        wepoint_estado = ?,
        wepoint_total = ?,
        wepoint_response = ?,
        wepoint_created_at = ?
    WHERE id = ?
";

$responseJson = json_encode($data, JSON_UNESCAPED_UNICODE);
$createdAt = date('Y-m-d H:i:s');

$stmtUpd = $mysqli->prepare($sqlUpdate);
$stmtUpd->bind_param(
    "issdssi",
    $idOrdenWepoint,
    $nroOrdenVenta,
    $estado,
    $total,
    $responseJson,
    $createdAt,
    $idVenta
);

$stmtUpd->execute();

echo json_encode([
    "success" => true,
    "message" => "Orden de venta generada correctamente",
    "id_orden_venta" => $idOrdenWepoint,
    "nro_orden_venta" => $nroOrdenVenta,
    "estado" => $estado,
    "total" => $total
]);
