<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

switch ($accion) {

    case 'buscar_clientes':

        $term = isset($_POST['term']) ? trim($_POST['term']) : '';

        $sql = "
        SELECT
            id,
            RazonSocial,
            Cuit,
            Direccion,
            Ciudad,
            Telefono
        FROM Clientes
        WHERE
            RazonSocial LIKE ?
            OR Cuit LIKE ?
            OR Telefono LIKE ?
        ORDER BY RazonSocial ASC
        LIMIT 20
    ";

        $buscar = "%{$term}%";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sss", $buscar, $buscar, $buscar);
        $stmt->execute();

        $res = $stmt->get_result();

        $data = [];

        while ($row = $res->fetch_assoc()) {

            $data[] = [
                "id" => $row['id'],
                "text" => $row['RazonSocial'] . " - " . $row['Cuit'],
                "cliente" => $row
            ];
        }

        echo json_encode($data);
        break;

    case 'productos':

        $sql = "
            SELECT 
                id,
                Nombre,
                PrecioVenta
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


    case 'listar':

        $sql = "
            SELECT 
                V.id,
                V.Fecha,
                V.Cliente,
                V.Observaciones,
                V.Total,
                COUNT(VD.id) AS CantidadProductos
            FROM Ventas V
            LEFT JOIN VentasDetalle VD 
                ON VD.idVenta = V.id 
                AND VD.Eliminado = 0
            WHERE V.Eliminado = 0
            GROUP BY V.id
            ORDER BY V.id DESC
        ";

        $res = $mysqli->query($sql);

        $data = array();

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data);
        break;


    case 'guardar':

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $Cliente = isset($_POST['Cliente']) ? $mysqli->real_escape_string($_POST['Cliente']) : '';
        $Observaciones = isset($_POST['Observaciones']) ? $mysqli->real_escape_string($_POST['Observaciones']) : '';
        $detalleJson = isset($_POST['detalle']) ? $_POST['detalle'] : '[]';

        $detalle = json_decode($detalleJson, true);

        if (!is_array($detalle) || count($detalle) == 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "La venta no tiene productos."
            ));
            exit;
        }

        $total = 0;

        foreach ($detalle as $item) {
            $cantidad = isset($item['Cantidad']) ? (float)$item['Cantidad'] : 0;
            $precio = isset($item['PrecioUnitario']) ? (float)$item['PrecioUnitario'] : 0;
            $total += ($cantidad * $precio);
        }

        $mysqli->begin_transaction();

        try {

            if ($id == 0) {

                $sqlVenta = "
                    INSERT INTO Ventas
                    (Fecha, Cliente, Observaciones, Total, Eliminado)
                    VALUES
                    (NOW(), '$Cliente', '$Observaciones', '$total', 0)
                ";

                if (!$mysqli->query($sqlVenta)) {
                    throw new Exception($mysqli->error);
                }

                $idVenta = $mysqli->insert_id;
            } else {

                $sqlVenta = "
                    UPDATE Ventas SET
                        Cliente = '$Cliente',
                        Observaciones = '$Observaciones',
                        Total = '$total'
                    WHERE id = '$id'
                    LIMIT 1
                ";

                if (!$mysqli->query($sqlVenta)) {
                    throw new Exception($mysqli->error);
                }

                $idVenta = $id;

                $sqlDeleteDetalle = "
                    UPDATE VentasDetalle 
                    SET Eliminado = 1 
                    WHERE idVenta = '$idVenta'
                ";

                if (!$mysqli->query($sqlDeleteDetalle)) {
                    throw new Exception($mysqli->error);
                }
            }

            foreach ($detalle as $item) {

                $idProducto = isset($item['idProducto']) ? (int)$item['idProducto'] : 0;
                $ProductoNombre = isset($item['ProductoNombre']) ? $mysqli->real_escape_string($item['ProductoNombre']) : '';
                $Cantidad = isset($item['Cantidad']) ? (float)$item['Cantidad'] : 0;
                $PrecioUnitario = isset($item['PrecioUnitario']) ? (float)$item['PrecioUnitario'] : 0;
                $Subtotal = $Cantidad * $PrecioUnitario;

                if ($idProducto <= 0 || $Cantidad <= 0) {
                    continue;
                }

                $sqlDetalle = "
                    INSERT INTO VentasDetalle
                    (idVenta, idProducto, ProductoNombre, Cantidad, PrecioUnitario, Subtotal, Eliminado)
                    VALUES
                    ('$idVenta', '$idProducto', '$ProductoNombre', '$Cantidad', '$PrecioUnitario', '$Subtotal', 0)
                ";

                if (!$mysqli->query($sqlDetalle)) {
                    throw new Exception($mysqli->error);
                }
            }

            $mysqli->commit();

            echo json_encode(array(
                "success" => 1,
                "idVenta" => $idVenta
            ));
        } catch (Exception $e) {

            $mysqli->rollback();

            echo json_encode(array(
                "success" => 0,
                "error" => $e->getMessage()
            ));
        }

        break;


    case 'ver':

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $sqlVenta = "
            SELECT *
            FROM Ventas
            WHERE id = '$id'
            LIMIT 1
        ";

        $resVenta = $mysqli->query($sqlVenta);
        $venta = $resVenta->fetch_assoc();

        $sqlDetalle = "
            SELECT *
            FROM VentasDetalle
            WHERE idVenta = '$id'
              AND Eliminado = 0
            ORDER BY id ASC
        ";

        $resDetalle = $mysqli->query($sqlDetalle);

        $detalle = array();

        while ($row = $resDetalle->fetch_assoc()) {
            $detalle[] = $row;
        }

        echo json_encode(array(
            "venta" => $venta,
            "detalle" => $detalle
        ));

        break;


    case 'eliminar':

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $sql = "
            UPDATE Ventas 
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
