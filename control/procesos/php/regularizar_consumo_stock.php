<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$usuario = isset($_SESSION['user_name']) && $_SESSION['user_name'] != ''
    ? $mysqli->real_escape_string($_SESSION['user_name'])
    : 'Regularizacion';

$mysqli->begin_transaction();

try {

    // 1) Anular regularizaciones anteriores
    $sqlAnular = "
        UPDATE VentasConsumoStock
        SET Eliminado = 1
        WHERE Eliminado = 0
    ";

    if (!$mysqli->query($sqlAnular)) {
        throw new Exception($mysqli->error);
    }

    // 2) Traer todas las ventas existentes, de más vieja a más nueva
    $sqlVentas = "
        SELECT 
            V.id AS idVenta,
            V.NumeroVenta,
            V.Fecha,
            VD.id AS idVentaDetalle,
            VD.idProducto,
            VD.ProductoNombre,
            VD.Cantidad
        FROM Ventas V
        INNER JOIN VentasDetalle VD 
            ON VD.idVenta = V.id
            AND VD.Eliminado = 0
        WHERE V.Eliminado = 0
        ORDER BY V.Fecha ASC, V.id ASC, VD.id ASC
    ";

    $resVentas = $mysqli->query($sqlVentas);

    if (!$resVentas) {
        throw new Exception($mysqli->error);
    }

    $ventasProcesadas = 0;
    $registrosConsumo = 0;
    $erroresStock = array();

    while ($venta = $resVentas->fetch_assoc()) {

        $idVenta = (int)$venta['idVenta'];
        $idVentaDetalle = (int)$venta['idVentaDetalle'];
        $idProducto = (int)$venta['idProducto'];
        $cantidadPendiente = (float)$venta['Cantidad'];
        $productoNombre = $venta['ProductoNombre'];
        $numeroVenta = $venta['NumeroVenta'];

        if ($idVenta <= 0 || $idVentaDetalle <= 0 || $idProducto <= 0 || $cantidadPendiente <= 0) {
            continue;
        }

        // 3) Buscar stock disponible en órdenes de ingreso, FIFO
        $sqlStockOrdenes = "
            SELECT 
                OCD.id AS idOrdenCompraDetalle,
                OCD.idOrdenCompra,
                OCD.idProducto,
                OCD.Cantidad AS CantidadIngresada,

                (
                    OCD.Cantidad 
                    - IFNULL((
                        SELECT SUM(VCS.Cantidad)
                        FROM VentasConsumoStock VCS
                        WHERE VCS.idOrdenCompraDetalle = OCD.id
                          AND VCS.Eliminado = 0
                    ),0)
                ) AS Disponible

            FROM OrdenesCompraDetalle OCD
            INNER JOIN OrdenesCompra OC 
                ON OC.id = OCD.idOrdenCompra
            WHERE OCD.idProducto = '$idProducto'
              AND IFNULL(OCD.Eliminado,0) = 0
              AND IFNULL(OC.Eliminado,0) = 0
            HAVING Disponible > 0
            ORDER BY OC.Fecha ASC, OCD.id ASC
        ";

        $resStockOrdenes = $mysqli->query($sqlStockOrdenes);

        if (!$resStockOrdenes) {
            throw new Exception($mysqli->error);
        }

        while ($cantidadPendiente > 0 && $stockRow = $resStockOrdenes->fetch_assoc()) {

            $disponible = (float)$stockRow['Disponible'];

            if ($disponible <= 0) {
                continue;
            }

            $cantidadConsumir = min($cantidadPendiente, $disponible);

            $idOrdenCompra = (int)$stockRow['idOrdenCompra'];
            $idOrdenCompraDetalle = (int)$stockRow['idOrdenCompraDetalle'];

            $sqlConsumo = "
                INSERT INTO VentasConsumoStock
                (
                    idVenta,
                    idVentaDetalle,
                    idOrdenCompra,
                    idOrdenCompraDetalle,
                    idProducto,
                    Cantidad,
                    Eliminado,
                    FechaAlta,
                    UsuarioAlta
                )
                VALUES
                (
                    '$idVenta',
                    '$idVentaDetalle',
                    '$idOrdenCompra',
                    '$idOrdenCompraDetalle',
                    '$idProducto',
                    '$cantidadConsumir',
                    0,
                    NOW(),
                    '$usuario'
                )
            ";

            if (!$mysqli->query($sqlConsumo)) {
                throw new Exception($mysqli->error);
            }

            $cantidadPendiente -= $cantidadConsumir;
            $registrosConsumo++;
        }

        if ($cantidadPendiente > 0) {
            $erroresStock[] = array(
                "idVenta" => $idVenta,
                "NumeroVenta" => $numeroVenta,
                "idProducto" => $idProducto,
                "Producto" => $productoNombre,
                "CantidadFaltante" => $cantidadPendiente
            );
        }

        $ventasProcesadas++;
    }

    if (count($erroresStock) > 0) {
        throw new Exception("Hay ventas con stock insuficiente para regularizar.");
    }

    $mysqli->commit();

    echo json_encode(array(
        "success" => 1,
        "mensaje" => "Regularización finalizada correctamente.",
        "ventasProcesadas" => $ventasProcesadas,
        "registrosConsumo" => $registrosConsumo
    ));
} catch (Exception $e) {

    $mysqli->rollback();

    echo json_encode(array(
        "success" => 0,
        "error" => $e->getMessage(),
        "ventasConStockInsuficiente" => isset($erroresStock) ? $erroresStock : array()
    ));
}
