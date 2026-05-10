<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

switch ($accion) {
    case 'productos':

        $sql = "
        SELECT 
            id,
            Nombre,
            Stock
        FROM Productos
        WHERE Eliminado = 0
          AND Activo = 1
        ORDER BY Nombre ASC
    ";

        $res = $mysqli->query($sql);

        $data = array();

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data);
        break;
    case 'guardar':

        $usuario = isset($_SESSION['Usuario']) ? $_SESSION['Usuario'] : '';
        $observaciones = isset($_POST['Observaciones']) ? $mysqli->real_escape_string($_POST['Observaciones']) : '';
        $detalleJson = isset($_POST['detalle']) ? $_POST['detalle'] : '[]';

        $detalle = json_decode($detalleJson, true);

        if (!is_array($detalle) || count($detalle) == 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "La orden no tiene productos."
            ));
            exit;
        }

        $mysqli->begin_transaction();

        try {

            $totalItems = 0;

            foreach ($detalle as $item) {
                $totalItems += isset($item['Cantidad']) ? (int)$item['Cantidad'] : 0;
            }

            $sqlOrden = "
                INSERT INTO OrdenesCompra
                (Fecha, Usuario, Observaciones, TotalItems, Eliminado)
                VALUES
                (NOW(), '$usuario', '$observaciones', '$totalItems', 0)
            ";

            if (!$mysqli->query($sqlOrden)) {
                throw new Exception($mysqli->error);
            }

            $idOrden = $mysqli->insert_id;

            $sqlNumero = "
                UPDATE OrdenesCompra
                SET NumeroOrden = '$idOrden'
                WHERE id = '$idOrden'
                LIMIT 1
            ";

            if (!$mysqli->query($sqlNumero)) {
                throw new Exception($mysqli->error);
            }

            foreach ($detalle as $item) {

                $idProducto = isset($item['idProducto']) ? (int)$item['idProducto'] : 0;
                $cantidad = isset($item['Cantidad']) ? (int)$item['Cantidad'] : 0;

                if ($idProducto <= 0 || $cantidad <= 0) {
                    continue;
                }

                $stmt = $mysqli->prepare("
                    SELECT Nombre, Stock
                    FROM Productos
                    WHERE id = ?
                      AND Eliminado = 0
                    LIMIT 1
                ");

                $stmt->bind_param("i", $idProducto);
                $stmt->execute();

                $res = $stmt->get_result();
                $producto = $res->fetch_assoc();

                if (!$producto) {
                    throw new Exception("Producto inexistente: " . $idProducto);
                }

                $productoNombre = $mysqli->real_escape_string($producto['Nombre']);
                $stockAnterior = (int)$producto['Stock'];
                $stockNuevo = $stockAnterior + $cantidad;

                $sqlDetalle = "
                    INSERT INTO OrdenesCompraDetalle
                    (idOrdenCompra, idProducto, ProductoNombre, Cantidad, StockAnterior, StockNuevo, Eliminado)
                    VALUES
                    ('$idOrden', '$idProducto', '$productoNombre', '$cantidad', '$stockAnterior', '$stockNuevo', 0)
                ";

                if (!$mysqli->query($sqlDetalle)) {
                    throw new Exception($mysqli->error);
                }

                $sqlStock = "
                    UPDATE Productos
                    SET Stock = '$stockNuevo'
                    WHERE id = '$idProducto'
                    LIMIT 1
                ";

                if (!$mysqli->query($sqlStock)) {
                    throw new Exception($mysqli->error);
                }

                $sqlMov = "
                    INSERT INTO MovimientosStock
                    (idProducto, Tipo, idReferencia, Cantidad, StockAnterior, StockNuevo, Usuario, Observaciones, Fecha)
                    VALUES
                    ('$idProducto', 'INGRESO_COMPRA', '$idOrden', '$cantidad', '$stockAnterior', '$stockNuevo', '$usuario', '$observaciones', NOW())
                ";

                if (!$mysqli->query($sqlMov)) {
                    throw new Exception($mysqli->error);
                }
            }

            $mysqli->commit();

            echo json_encode(array(
                "success" => 1,
                "idOrden" => $idOrden,
                "NumeroOrden" => $idOrden
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
