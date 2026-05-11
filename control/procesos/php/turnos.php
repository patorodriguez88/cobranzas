<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

switch ($accion) {

    case 'listar':

        $fecha = isset($_POST['fecha']) ? $mysqli->real_escape_string($_POST['fecha']) : date('Y-m-d');
        $estado = isset($_POST['estado']) ? $mysqli->real_escape_string($_POST['estado']) : '';

        $whereEstado = "";

        if ($estado != '') {
            $whereEstado = " AND TR.EstadoTurno = '$estado' ";
        }

        $sql = "
            SELECT
                TR.id,
                TR.idVenta,
                TR.NumeroVenta,
                TR.NumeroOrdenVenta,
                TR.idCliente,
                TR.Cliente,
                TR.Telefono,
                TR.FechaTurno,
                TR.HoraTurno,
                TR.EstadoTurno,
                TR.Usuario,
                TR.Observaciones,
                TR.FechaCarga,
                V.Total,
                V.EstadoPago
            FROM TurnosRetiro TR
            LEFT JOIN Ventas V ON V.id = TR.idVenta
            WHERE TR.Eliminado = 0
              AND TR.FechaTurno = '$fecha'
              $whereEstado
            ORDER BY TR.HoraTurno ASC, TR.id ASC
        ";

        $res = $mysqli->query($sql);

        if (!$res) {
            echo json_encode(array(
                "data" => array(),
                "error" => $mysqli->error
            ));
            exit;
        }

        $data = array();

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode(array("data" => $data));
        break;


    case 'resumen':

        $fecha = isset($_POST['fecha']) ? $mysqli->real_escape_string($_POST['fecha']) : date('Y-m-d');

        $data = array(
            "TOTAL" => 0,
            "PENDIENTE" => 0,
            "CONFIRMADO" => 0,
            "RETIRADO" => 0,
            "CANCELADO" => 0
        );

        $sql = "
            SELECT EstadoTurno, COUNT(*) AS Total
            FROM TurnosRetiro
            WHERE Eliminado = 0
              AND FechaTurno = '$fecha'
            GROUP BY EstadoTurno
        ";

        $res = $mysqli->query($sql);

        if (!$res) {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
            exit;
        }

        while ($row = $res->fetch_assoc()) {
            $estado = strtoupper(trim($row['EstadoTurno']));

            if ($estado == '') {
                $estado = 'PENDIENTE';
            }

            $data[$estado] = (int)$row['Total'];
            $data['TOTAL'] += (int)$row['Total'];
        }

        echo json_encode($data);
        break;


    case 'cambiar_estado':

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $estado = isset($_POST['estado']) ? $mysqli->real_escape_string($_POST['estado']) : '';
        $usuario = isset($_SESSION['Usuario']) ? $mysqli->real_escape_string($_SESSION['Usuario']) : 'Sistema';

        $estadosPermitidos = array('PENDIENTE', 'CONFIRMADO', 'RETIRADO', 'CANCELADO');

        if ($id <= 0 || !in_array($estado, $estadosPermitidos)) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Datos inválidos."
            ));
            exit;
        }

        $campoFecha = "";

        if ($estado == 'CONFIRMADO' || $estado == 'RETIRADO') {
            $campoFecha = ", FechaConfirmacion = NOW()";
        }

        $sql = "
            UPDATE TurnosRetiro
            SET EstadoTurno = '$estado',
                Usuario = '$usuario'
                $campoFecha
            WHERE id = '$id'
            LIMIT 1
        ";

        if ($mysqli->query($sql)) {
            echo json_encode(array("success" => 1));
        } else {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
        }

        break;


    case 'eliminar':

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Turno inválido."
            ));
            exit;
        }

        $sql = "
            UPDATE TurnosRetiro
            SET Eliminado = 1
            WHERE id = '$id'
            LIMIT 1
        ";

        if ($mysqli->query($sql)) {
            echo json_encode(array("success" => 1));
        } else {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
        }

        break;


    default:
        echo json_encode(array(
            "success" => 0,
            "error" => "Acción inválida."
        ));
        break;
}
