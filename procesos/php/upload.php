<?php
session_start();
$user=$_SESSION['user'];
$N=$_SESSION['NComprobante'];

if   (($_FILES["file"]["type"] == "image/pjpeg")
    || ($_FILES["file"]["type"] == "image/jpeg")
    || ($_FILES["file"]["type"] == "image/png")
    || ($_FILES["file"]["type"] == "image/gif")) {
    
    $tipoArchivo = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));
    move_uploaded_file($_FILES["file"]["tmp_name"], "../../images/depositos/".$N.'.'.$tipoArchivo);
    // rename ("../../images/depositos/".$_FILES['file']['name'], $user);    
    }else{
        echo 'no';
    }
