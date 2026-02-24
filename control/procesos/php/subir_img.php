<?php
include_once "../../../conexion/conexioni.php";
date_default_timezone_set("America/Argentina/Cordoba");

if ($_POST['Exportar'] == 1) {

    $name = date('dmY H:i:s');
    $fichero = 'imagenes/' . $name . '.jpg';
    // Abre el fichero para obtener el contenido existente
    // $actual = file_get_contents($fichero);

    for ($i = 0; $i < count($_POST['id_cobranza']); $i++) {

        $dato = $_POST['id_cobranza'][$i];
    }

    // Escribe el contenido al fichero
    if (file_put_contents($fichero, $actual)) {

        for ($i = 0; $i < count($_POST['id_cobranza']); $i++) {

            // $mysqli->query("UPDATE Cobranza_conciliacion SET Exportado='$name' WHERE id_cobranza='".$_POST['id_cobranza'][$i]."'");

        }

        echo json_encode(array('success' => 1, 'name' => $name));
    }
}

$uploadedfileload = "true";
$uploadedfile_size = $_FILES['uploadedfile']['size'];
echo $_FILES['uploadedfile']['name'];

if ($_FILES['uploadedfile']['size'] > 200000) {
    $msg = $msg . "El archivo es mayor que 200KB, debes reduzcirlo antes de subirlo<BR>";
    $uploadedfileload = "false";
}

if (!($_FILES['uploadedfile']['type'] == "image/pjpeg" or $_FILES['uploadedfile']['type'] == "image/gif")) {
    $msg = $msg . " Tu archivo tiene que ser JPG o GIF. Otros archivos no son permitidos<BR>";
    $uploadedfileload = "false";
}

$file_name = $_FILES['uploadedfile']['name'];
$add = "uploads/$file_name";
if ($uploadedfileload == "true") {

    if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $add)) {
        echo " Ha sido subido satisfactoriamente";
    } else {
        echo "Error al subir el archivo";
    }
} else {
    echo $msg;
}
