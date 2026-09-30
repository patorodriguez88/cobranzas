<?php

$host="localhost";
$user="dinter6_prodrig";
$pass="pato@4986";
$db="dinter6_dinter";
// Entorno local de pruebas: si existe conexion/config_local.php (no se sube al repo, ver
// .gitignore) redefine $host/$user/$pass/$db para usar la base local. En producción no existe.
if (is_file(__DIR__ . '/config_local.php')) {
    require __DIR__ . '/config_local.php';
}
$mysqli = new mysqli($host,$user,$pass,$db);

mysqli_set_charset($mysqli,"utf8"); 
if (!empty($sqlModeLocal)) {
    $mysqli->query("SET SESSION sql_mode = '" . $mysqli->real_escape_string($sqlModeLocal) . "'");
}

date_default_timezone_set('America/Argentina/Cordoba');


?>