<?php
session_start();

$idCobranza = isset($_POST['idCobranza']) ? (int)$_POST['idCobranza'] : 0;

if ($idCobranza <= 0 && isset($_SESSION['NComprobante'])) {
    $idCobranza = (int)$_SESSION['NComprobante'];
}

if ($idCobranza <= 0) {
    echo 'sin_id';
    exit;
}

if (!isset($_FILES["file"])) {
    echo 'sin_archivo';
    exit;
}

$permitidos = array(
    "image/pjpeg",
    "image/jpeg",
    "image/png",
    "image/gif"
);

if (in_array($_FILES["file"]["type"], $permitidos)) {
    $tipoArchivo = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));

    $destino = __DIR__ . "/../../images/depositos/" . $idCobranza . "." . $tipoArchivo;

    if (move_uploaded_file($_FILES["file"]["tmp_name"], $destino)) {
        echo json_encode(array("success" => 1));
    } else {
        echo 'error_move';
    }

    exit;
}

echo 'tipo_no_permitido';
