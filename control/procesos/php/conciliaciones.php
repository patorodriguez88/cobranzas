<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once "../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

switch ($accion) {

    case 'ventas_aplicadas':

        $idCobranza = isset($_POST['idCobranza']) ? (int)$_POST['idCobranza'] : 0;

        $sql = "
            SELECT 
                CV.id AS id,
                CV.idCobranza,
                CV.idVenta,
                CV.ImporteAplicado,
                CV.Fecha AS FechaAplicacion,
                V.NumeroVenta,
                V.NumeroOrdenVenta,
                V.Fecha,
                V.Total,
                V.TotalPagado,
                V.Saldo,
                V.EstadoPago
            FROM CobranzasVentas CV
            INNER JOIN Ventas V ON V.id = CV.idVenta
            WHERE CV.idCobranza = '$idCobranza'
              AND IFNULL(CV.Eliminado,0) = 0
              AND V.Eliminado = 0
            ORDER BY CV.id DESC
        ";

        $res = $mysqli->query($sql);

        if (!$res) {
            echo json_encode([
                "success" => 0,
                "error" => $mysqli->error,
                "data" => []
            ]);
            exit;
        }

        $data = [];

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode([
            "success" => 1,
            "data" => $data
        ]);
        break;


    case 'desvincular_pago_venta':

        $idAplicacion = isset($_POST['idAplicacion']) ? (int)$_POST['idAplicacion'] : 0;

        if ($idAplicacion <= 0) {
            echo json_encode([
                "success" => 0,
                "error" => "Aplicación inválida."
            ]);
            exit;
        }

        $mysqli->begin_transaction();

        try {

            $sqlAplicacion = "
                SELECT 
                    id,
                    idCobranza,
                    idVenta,
                    ImporteAplicado
                FROM CobranzasVentas
                WHERE id = '$idAplicacion'
                  AND IFNULL(Eliminado,0) = 0
                LIMIT 1
                FOR UPDATE
            ";

            $resAplicacion = $mysqli->query($sqlAplicacion);

            if (!$resAplicacion) {
                throw new Exception($mysqli->error);
            }

            if ($resAplicacion->num_rows == 0) {
                throw new Exception("La aplicación no existe o ya fue desvinculada.");
            }

            $aplicacion = $resAplicacion->fetch_assoc();

            $idCobranza = (int)$aplicacion['idCobranza'];
            $idVenta = (int)$aplicacion['idVenta'];

            $sqlEliminar = "
                UPDATE CobranzasVentas
                SET Eliminado = 1
                WHERE id = '$idAplicacion'
                LIMIT 1
            ";

            if (!$mysqli->query($sqlEliminar)) {
                throw new Exception($mysqli->error);
            }

            $sqlTotalPagado = "
                SELECT IFNULL(SUM(ImporteAplicado),0) AS TotalPagado
                FROM CobranzasVentas
                WHERE idVenta = '$idVenta'
                  AND IFNULL(Eliminado,0) = 0
            ";

            $resTotalPagado = $mysqli->query($sqlTotalPagado);

            if (!$resTotalPagado) {
                throw new Exception($mysqli->error);
            }

            $rowPagado = $resTotalPagado->fetch_assoc();
            $totalPagado = (float)$rowPagado['TotalPagado'];

            $sqlVenta = "
                SELECT Total
                FROM Ventas
                WHERE id = '$idVenta'
                LIMIT 1
                FOR UPDATE
            ";

            $resVenta = $mysqli->query($sqlVenta);

            if (!$resVenta || $resVenta->num_rows == 0) {
                throw new Exception("Venta inexistente.");
            }

            $venta = $resVenta->fetch_assoc();

            $totalVenta = (float)$venta['Total'];
            $saldo = $totalVenta - $totalPagado;

            if ($saldo <= 0.01) {
                $saldo = 0;
                $estado = "PAGADA";
            } elseif ($totalPagado > 0) {
                $estado = "PARCIAL";
            } else {
                $estado = "PENDIENTE";
            }

            $sqlUpdateVenta = "
                UPDATE Ventas
                SET 
                    TotalPagado = '$totalPagado',
                    Saldo = '$saldo',
                    EstadoPago = '$estado'
                WHERE id = '$idVenta'
                LIMIT 1
            ";

            if (!$mysqli->query($sqlUpdateVenta)) {
                throw new Exception($mysqli->error);
            }

            $mysqli->commit();

            echo json_encode([
                "success" => 1,
                "idCobranza" => $idCobranza,
                "idVenta" => $idVenta
            ]);
        } catch (Exception $e) {

            $mysqli->rollback();

            echo json_encode([
                "success" => 0,
                "error" => $e->getMessage()
            ]);
        }

        break;


    default:
        echo json_encode([
            "success" => 0,
            "error" => "Acción inválida."
        ]);
        break;
}
