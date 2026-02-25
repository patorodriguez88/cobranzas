<?php
session_start();
include_once "../../conexion/conexioni.php";

if (isset($_POST['Mis_pagos'])) {

    $Ncliente = (int)($_SESSION['ncliente_cobranza'] ?? 0);

    if ($Ncliente > 0) {

        // $sql = $mysqli->query("SELECT * FROM Cobranza_ WHERE NumeroCliente='$Ncliente' ORDER BY id DESC");
        $sql = $mysqli->query("SELECT c.*,
                    COALESCE(cc.Estado, 'Pendiente') AS Estado
                    FROM Cobranza c
                    LEFT JOIN Cobranza_conciliacion cc
                    ON cc.id = (
                    SELECT MAX(cc2.id)
                    FROM Cobranza_conciliacion cc2
                    WHERE cc2.id_cobranza = c.id
                    )
                    WHERE c.NumeroCliente = $Ncliente
                    ORDER BY c.Fecha DESC, c.id DESC
                    LIMIT 10;");

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
