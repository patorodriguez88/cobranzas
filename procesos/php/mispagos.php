<?php
session_start();
include_once "../../conexion/conexioni.php";

if (isset($_POST['Mis_pagos'])) {

    $Ncliente = intval($_SESSION['Ncliente']);


    if ($Ncliente <> 0 && $Ncliente <> NULL) {

        $sql = $mysqli->query("SELECT * FROM Cobranza WHERE NumeroCliente='$Ncliente' ORDER BY id DESC");

        $row = $sql->fetch_array(MYSQLI_ASSOC);

        if ($row['id'] <> 0 && $row['id'] <> NULL) {

            $rows = array();

            $rows[] = $row;

            $_SESSION['user'] = $row['id'];

            echo json_encode(array('success' => 1, 'data' => $rows));
        } else {

            echo json_encode(array('success' => 0, 'Ncliente' => $Ncliente));
        }
    } else {

        echo json_encode(array('success' => 0, 'Ncliente 2' => $Ncliente));
    }
}
