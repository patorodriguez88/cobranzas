<?php
session_start();
include_once "../../conexion/conexioni.php";
header('Content-Type: application/json; charset=utf-8');

$sql = $mysqli->query("SELECT version, build, force_update, min_version, message FROM AppConfig ORDER BY id DESC LIMIT 1");
$row = $sql ? $sql->fetch_array(MYSQLI_ASSOC) : null;

if (!$row) {
    echo json_encode(['success' => 0, 'msg' => 'Sin configuración']);
    exit;
}

echo json_encode(['success' => 1, 'data' => $row]);
exit;
