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

    case 'ventas_aplicadas':

        $idCobranza = (int)$_POST['idCobranza'];

        $sql = "
            SELECT 
                CV.id,
                CV.ImporteAplicado,

                V.id AS idVenta,
                V.NumeroVenta,
                V.NumeroOrdenVenta,
                V.Fecha,
                V.Total,
                V.TotalPagado,
                V.Saldo,
                V.EstadoPago

            FROM CobranzasVentas CV

            INNER JOIN Ventas V
                ON V.id = CV.idVenta

            WHERE CV.idCobranza = '$idCobranza'
              AND CV.Eliminado = 0

            ORDER BY CV.id DESC
        ";

        $res = $mysqli->query($sql);

        $data = array();

        while ($row = $res->fetch_assoc()) {

            $data[] = $row;
        }

        echo json_encode(array(
            "success" => 1,
            "data" => $data
        ));

        break;

    case 'desvincular_pago_venta':

        $idAplicacion = (int)$_POST['idAplicacion'];

        $mysqli->begin_transaction();

        try {

            $sql = "
                SELECT *
                FROM CobranzasVentas
                WHERE id = '$idAplicacion'
                  AND Eliminado = 0
                LIMIT 1
            ";

            $res = $mysqli->query($sql);

            if (!$res || $res->num_rows == 0) {

                throw new Exception("La aplicación no existe.");
            }

            $app = $res->fetch_assoc();

            $idVenta = (int)$app['idVenta'];
            $importe = (float)$app['ImporteAplicado'];

            $sqlEliminar = "
                UPDATE CobranzasVentas
                SET Eliminado = 1
                WHERE id = '$idAplicacion'
                LIMIT 1
            ";

            if (!$mysqli->query($sqlEliminar)) {

                throw new Exception($mysqli->error);
            }

            $sqlVenta = "
                SELECT Total, TotalPagado
                FROM Ventas
                WHERE id = '$idVenta'
                LIMIT 1
            ";

            $resVenta = $mysqli->query($sqlVenta);

            if (!$resVenta || $resVenta->num_rows == 0) {

                throw new Exception("La venta vinculada no existe.");
            }

            $venta = $resVenta->fetch_assoc();

            $nuevoPagado = (float)$venta['TotalPagado'] - $importe;

            if ($nuevoPagado < 0) {
                $nuevoPagado = 0;
            }

            $saldo = (float)$venta['Total'] - $nuevoPagado;

            $estado = 'PENDIENTE';

            if ($saldo <= 0) {

                $estado = 'PAGADA';
            } elseif ($nuevoPagado > 0) {

                $estado = 'PARCIAL';
            }

            $sqlUpdateVenta = "
                UPDATE Ventas
                SET 
                    TotalPagado = '$nuevoPagado',
                    Saldo = '$saldo',
                    EstadoPago = '$estado'
                WHERE id = '$idVenta'
                LIMIT 1
            ";

            if (!$mysqli->query($sqlUpdateVenta)) {

                throw new Exception($mysqli->error);
            }

            $mysqli->commit();

            echo json_encode(array(
                "success" => 1
            ));
        } catch (Exception $e) {

            $mysqli->rollback();

            echo json_encode(array(
                "success" => 0,
                "error" => $e->getMessage()
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
