<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$idCobranza = isset($_POST['idCobranza']) ? (int)$_POST['idCobranza'] : 0;

if ($idCobranza <= 0) {
    echo json_encode([
        "success" => 0,
        "error" => "No se recibió idCobranza."
    ]);
    exit;
}

if (!isset($_FILES["file"])) {
    echo json_encode([
        "success" => 0,
        "error" => "No se recibió archivo."
    ]);
    exit;
}

$permitidos = [
    "image/pjpeg",
    "image/jpeg",
    "image/png",
    "image/gif"
];

$extension = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));
$extPermitidas = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array($_FILES["file"]["type"], $permitidos) && !in_array($extension, $extPermitidas)) {
    echo json_encode([
        "success" => 0,
        "error" => "Tipo de archivo no permitido."
    ]);
    exit;
}

$carpeta = __DIR__ . "/../../images/depositos/";

if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

$destino = $carpeta . $idCobranza . "." . $extension;

if (move_uploaded_file($_FILES["file"]["tmp_name"], $destino)) {
    echo json_encode([
        "success" => 1,
        "archivo" => $idCobranza . "." . $extension,
        "idCobranza" => $idCobranza
    ]);
} else {
    echo json_encode([
        "success" => 0,
        "error" => "Tipo de archivo no permitido.",
        "mime" => $_FILES["file"]["type"],
        "extension" => $extension,
        "nombre" => $_FILES["file"]["name"]
    ]);
    exit;
}
exit;
