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

    case 'resumen':

        $sql = "
            SELECT 
                COUNT(*) AS Ventas,
                IFNULL(SUM(Total),0) AS TotalFacturado,
                IFNULL(SUM(TotalPagado),0) AS TotalCobrado,
                IFNULL(SUM(Saldo),0) AS SaldoPendiente,
                SUM(CASE WHEN EstadoPago = 'PAGADA' THEN 1 ELSE 0 END) AS Pagadas,
                SUM(CASE WHEN EstadoPago = 'PARCIAL' THEN 1 ELSE 0 END) AS Parciales,
                SUM(CASE WHEN EstadoPago = 'PENDIENTE' THEN 1 ELSE 0 END) AS Pendientes
            FROM Ventas
            WHERE Eliminado = 0
        ";

        $res = $mysqli->query($sql);

        if (!$res) {
            echo json_encode([
                "success" => 0,
                "error" => $mysqli->error
            ]);
            exit;
        }

        echo json_encode([
            "success" => 1,
            "data" => $res->fetch_assoc()
        ]);

        break;

    case 'productos':

        $sql = "
            SELECT 
                VD.ProductoNombre,
                SUM(VD.Cantidad) AS CantidadVendida,
                SUM(VD.Subtotal) AS Recaudacion,
                COUNT(DISTINCT V.id) AS CantidadVentas
            FROM VentasDetalle VD
            INNER JOIN Ventas V ON V.id = VD.idVenta
            WHERE VD.Eliminado = 0
              AND V.Eliminado = 0
            GROUP BY VD.ProductoNombre
            ORDER BY Recaudacion DESC
        ";

        $res = $mysqli->query($sql);

        if (!$res) {
            echo json_encode([
                "data" => [],
                "error" => $mysqli->error
            ]);
            exit;
        }

        $data = [];

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode([
            "data" => $data
        ]);

        break;
    case 'ranking_vendedores':

        $sql = "
        SELECT 
            IFNULL(NULLIF(TRIM(Usuario), ''), 'Sin usuario') AS Usuario,
            COUNT(*) AS CantidadVentas,
            IFNULL(SUM(Total),0) AS TotalFacturado,
            IFNULL(SUM(TotalPagado),0) AS TotalCobrado,
            IFNULL(SUM(Saldo),0) AS SaldoPendiente
        FROM Ventas
        WHERE Eliminado = 0
        GROUP BY IFNULL(NULLIF(TRIM(Usuario), ''), 'Sin usuario')
        ORDER BY TotalFacturado DESC
        LIMIT 10
    ";

        $res = $mysqli->query($sql);

        if (!$res) {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error,
                "data" => array()
            ));
            exit;
        }

        $data = array();

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode(array(
            "success" => 1,
            "data" => $data
        ));

        break;
    case 'ventas_por_hora':

        $sql = "
        SELECT 
            HOUR(Fecha) AS Hora,
            COUNT(*) AS CantidadVentas,
            IFNULL(SUM(Total),0) AS TotalFacturado
        FROM Ventas
        WHERE Eliminado = 0
        GROUP BY HOUR(Fecha)
        ORDER BY Hora ASC
    ";

        $res = $mysqli->query($sql);

        if (!$res) {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error,
                "data" => array()
            ));
            exit;
        }

        $data = array();

        while ($row = $res->fetch_assoc()) {
            $data[] = array(
                "Hora" => str_pad($row["Hora"], 2, "0", STR_PAD_LEFT),
                "CantidadVentas" => $row["CantidadVentas"],
                "TotalFacturado" => $row["TotalFacturado"]
            );
        }

        echo json_encode(array(
            "success" => 1,
            "data" => $data
        ));

        break;
    case 'ultimos_movimientos':

        $sql = "
        SELECT 
            MS.id,
            MS.Fecha,
            MS.idProducto,
            P.Nombre AS Producto,
            MS.Tipo,
            MS.TipoMovimiento,
            MS.Cantidad,
            MS.StockAnterior,
            MS.StockNuevo,
            MS.Usuario,
            MS.Observaciones
        FROM MovimientosStock MS
        LEFT JOIN Productos P ON P.id = MS.idProducto
        ORDER BY MS.Fecha DESC, MS.id DESC
        LIMIT 10
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

        echo json_encode(array(
            "data" => $data
        ));

        break;
    default:

        echo json_encode([
            "success" => 0,
            "error" => "Acción inválida."
        ]);

        break;
}
