<?php
session_start();
include_once "../../../conexion/conexioni.php";
date_default_timezone_set("America/Argentina/Cordoba");

//DNI
if (isset($_POST['Dni_search'])) {
    $sql = $mysqli->query("SELECT Dni FROM Clientes WHERE id='$_POST[id]'");
    $row = $sql->fetch_array(MYSQLI_ASSOC);

    echo json_encode(array('success' => 1, 'Dato' => $row['Dni']));
}

if (isset($_POST['Dni'])) {

    $mysqli->query("UPDATE Clientes SET Dni='$_POST[Dni_text]' WHERE id='$_POST[id]'");

    echo json_encode(array('success' => 1));
}


//OBSERVACIONES
if (isset($_POST['Observaciones_search'])) {
    $sql = $mysqli->query("SELECT Observaciones FROM Clientes WHERE id='$_POST[id]'");
    $row = $sql->fetch_array(MYSQLI_ASSOC);

    echo json_encode(array('success' => 1, 'Dato' => $row['Observaciones']));
}

if (isset($_POST['Observaciones'])) {

    $mysqli->query("UPDATE Clientes SET Observaciones='$_POST[Observaciones_text]' WHERE id='$_POST[id]'");

    echo json_encode(array('success' => 1));
}

//TABLA 

if (isset($_POST['Tabla_clientes'])) {

    $sql = $mysqli->query("SELECT id,Ncliente,RazonSocial,Dni,Observaciones,Suspendido,Direccion,Ciudad,Recorrido FROM Clientes");

    $rows = array();

    while ($row = $sql->fetch_array(MYSQLI_ASSOC)) {

        $rows[] = $row;
    }

    echo json_encode(array('data' => $rows));
}

if (isset($_POST['Status'])) {

    if ($mysqli->query("UPDATE Clientes SET Suspendido='$_POST[status]' WHERE id='$_POST[id_cliente]'") <> NULL) {

        echo json_encode(array('success' => 1));
    } else {

        echo json_encode(array('success' => 0));
    }
}
