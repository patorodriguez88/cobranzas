<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

function uploadErrorTexto($codigo)
{
    switch ($codigo) {
        case UPLOAD_ERR_INI_SIZE:
            return "El archivo supera upload_max_filesize del php.ini.";
        case UPLOAD_ERR_FORM_SIZE:
            return "El archivo supera MAX_FILE_SIZE del formulario.";
        case UPLOAD_ERR_PARTIAL:
            return "El archivo se subió parcialmente.";
        case UPLOAD_ERR_NO_FILE:
            return "No se subió ningún archivo.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Falta la carpeta temporal.";
        case UPLOAD_ERR_CANT_WRITE:
            return "No se pudo escribir el archivo en disco.";
        case UPLOAD_ERR_EXTENSION:
            return "Una extensión de PHP bloqueó la subida.";
        default:
            return "Error desconocido.";
    }
}

$idCobranza = isset($_POST['idCobranza']) ? (int)$_POST['idCobranza'] : 0;

if ($idCobranza <= 0) {
    echo json_encode([
        "success" => 0,
        "error" => "No se recibió idCobranza.",
        "post" => $_POST
    ]);
    exit;
}

if (!isset($_FILES["file"])) {
    echo json_encode([
        "success" => 0,
        "error" => "No se recibió archivo.",
        "post" => $_POST,
        "files" => $_FILES
    ]);
    exit;
}

if ($_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
    echo json_encode([
        "success" => 0,
        "error" => "Error PHP al subir archivo.",
        "upload_error_code" => $_FILES["file"]["error"],
        "upload_error_text" => uploadErrorTexto($_FILES["file"]["error"]),
        "file" => $_FILES["file"]
    ]);
    exit;
}

$permitidosMime = [
    "image/pjpeg",
    "image/jpeg",
    "image/png",
    "image/gif"
];

$extPermitidas = ['jpg', 'jpeg', 'png', 'gif'];

$mime = isset($_FILES["file"]["type"]) ? strtolower(trim($_FILES["file"]["type"])) : '';
$extension = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));

if (!in_array($mime, $permitidosMime) && !in_array($extension, $extPermitidas)) {
    echo json_encode([
        "success" => 0,
        "error" => "Tipo de archivo no permitido.",
        "mime" => $mime,
        "extension" => $extension,
        "nombre" => $_FILES["file"]["name"],
        "size" => $_FILES["file"]["size"]
    ]);
    exit;
}

$carpeta = __DIR__ . "/../../images/depositos/";

if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

$destino = $carpeta . $idCobranza . "." . $extension;

if (!move_uploaded_file($_FILES["file"]["tmp_name"], $destino)) {
    echo json_encode([
        "success" => 0,
        "error" => "No se pudo mover el archivo.",
        "tmp_name" => $_FILES["file"]["tmp_name"],
        "destino" => $destino,
        "is_uploaded_file" => is_uploaded_file($_FILES["file"]["tmp_name"]) ? 1 : 0,
        "carpeta_existe" => is_dir($carpeta) ? 1 : 0,
        "carpeta_writable" => is_writable($carpeta) ? 1 : 0
    ]);
    exit;
}

echo json_encode([
    "success" => 1,
    "archivo" => $idCobranza . "." . $extension,
    "idCobranza" => $idCobranza
]);
exit;
