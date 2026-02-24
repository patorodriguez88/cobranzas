<?php
session_start();
include_once "../../conexion/conexioni.php";

if (isset($_POST['Mis_pagos'])) {

    $Ncliente = (int)($_SESSION['ncliente_cobranza'] ?? 0);

    if ($Ncliente > 0) {

        $sql = $mysqli->query("SELECT * FROM Cobranza WHERE NumeroCliente='$Ncliente' ORDER BY id DESC");

        $rows = [];

        while ($row = $sql->fetch_array(MYSQLI_ASSOC)) {
            $rows[] = $row;
        }

        echo json_encode([
            'success' => count($rows) ? 1 : 0,
            'data'    => $rows
        ]);
    } else {
        echo json_encode(['success' => 0, 'error' => 'Ncliente inválido']);
    }
}
