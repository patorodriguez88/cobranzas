<?php

$host="localhost";
$user="dinter6_prodrig";
$pass="pato@4986";
$db="dinter6_dinter";
$mysqli = new mysqli($host,$user,$pass,$db);

mysqli_set_charset($mysqli,"utf8"); 

date_default_timezone_set('America/Argentina/Cordoba');


?>