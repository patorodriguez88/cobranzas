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


    case 'listar':

        $sql = "
        SELECT 
            OC.id,
            OC.NumeroOrden,
            OC.Fecha,
            OC.Usuario,
            OC.Observaciones,
            OC.TotalItems,
            GROUP_CONCAT(
                CONCAT(OCD.ProductoNombre, ' x', OCD.Cantidad)
                SEPARATOR '||'
            ) AS Productos
        FROM OrdenesCompra OC
        LEFT JOIN OrdenesCompraDetalle OCD 
            ON OCD.idOrdenCompra = OC.id
            AND OCD.Eliminado = 0
        WHERE OC.Eliminado = 0
        GROUP BY OC.id
        ORDER BY OC.NumeroOrden DESC
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
    case 'ver':

        $idOrden = isset($_POST['idOrden']) ? (int)$_POST['idOrden'] : 0;

        if ($idOrden <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Orden inválida."
            ));
            exit;
        }

        $sqlOrden = "
        SELECT *
        FROM OrdenesCompra
        WHERE id = '$idOrden'
          AND Eliminado = 0
        LIMIT 1
    ";

        $resOrden = $mysqli->query($sqlOrden);

        if (!$resOrden || $resOrden->num_rows == 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Orden no encontrada."
            ));
            exit;
        }

        $orden = $resOrden->fetch_assoc();

        $sqlDetalle = "
        SELECT 
            OCD.id,
            OCD.idProducto,
            OCD.ProductoNombre,
            OCD.Cantidad,
            P.Stock AS StockActual
        FROM OrdenesCompraDetalle OCD
        LEFT JOIN Productos P ON P.id = OCD.idProducto
        WHERE OCD.idOrdenCompra = '$idOrden'
          AND OCD.Eliminado = 0
        ORDER BY OCD.id ASC
    ";

        $resDetalle = $mysqli->query($sqlDetalle);

        if (!$resDetalle) {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
            exit;
        }

        $detalle = array();

        while ($row = $resDetalle->fetch_assoc()) {
            $detalle[] = $row;
        }

        echo json_encode(array(
            "success" => 1,
            "orden" => $orden,
            "detalle" => $detalle
        ));

        break;

    case 'guardar':

        $idOrdenEditar = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $usuario = '';

        if (isset($_SESSION['user_name']) && $_SESSION['user_name'] != '') {
            $usuario = $_SESSION['user_name'];
        } elseif (isset($_SESSION['Usuario']) && $_SESSION['Usuario'] != '') {
            $usuario = $_SESSION['Usuario'];
        } else {
            $usuario = 'Sistema';
        }

        $usuario = $mysqli->real_escape_string($usuario);

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

            /*
        SI ES EDICIÓN:
        1. Revertimos stock anterior
        2. Marcamos detalle anterior como eliminado
        */
            if ($idOrdenEditar > 0) {

                $sqlOrdenExiste = "
                SELECT id, NumeroOrden
                FROM OrdenesCompra
                WHERE id = '$idOrdenEditar'
                  AND Eliminado = 0
                LIMIT 1
                FOR UPDATE
            ";

                $resOrdenExiste = $mysqli->query($sqlOrdenExiste);

                if (!$resOrdenExiste) {
                    throw new Exception($mysqli->error);
                }

                if ($resOrdenExiste->num_rows == 0) {
                    throw new Exception("La orden que intentás editar no existe o está anulada.");
                }

                $sqlDetalleViejo = "
                SELECT 
                    idProducto,
                    ProductoNombre,
                    Cantidad
                FROM OrdenesCompraDetalle
                WHERE idOrdenCompra = '$idOrdenEditar'
                  AND Eliminado = 0
            ";

                $resDetalleViejo = $mysqli->query($sqlDetalleViejo);

                if (!$resDetalleViejo) {
                    throw new Exception($mysqli->error);
                }

                while ($itemViejo = $resDetalleViejo->fetch_assoc()) {

                    $idProductoViejo = (int)$itemViejo['idProducto'];
                    $cantidadVieja = (int)$itemViejo['Cantidad'];
                    $productoNombreViejo = $mysqli->real_escape_string($itemViejo['ProductoNombre']);

                    if ($idProductoViejo <= 0 || $cantidadVieja <= 0) {
                        continue;
                    }

                    $sqlProductoViejo = "
                    SELECT Stock
                    FROM Productos
                    WHERE id = '$idProductoViejo'
                    LIMIT 1
                    FOR UPDATE
                ";

                    $resProductoViejo = $mysqli->query($sqlProductoViejo);

                    if (!$resProductoViejo) {
                        throw new Exception($mysqli->error);
                    }

                    $productoViejo = $resProductoViejo->fetch_assoc();

                    if (!$productoViejo) {
                        throw new Exception("Producto inexistente al revertir stock: " . $idProductoViejo);
                    }

                    $stockAnterior = (int)$productoViejo['Stock'];
                    $stockNuevo = $stockAnterior - $cantidadVieja;

                    if ($stockNuevo < 0) {
                        throw new Exception("No se puede revertir la orden porque el stock de {$productoNombreViejo} quedaría negativo.");
                    }

                    $sqlRevertirStock = "
                    UPDATE Productos
                    SET Stock = '$stockNuevo'
                    WHERE id = '$idProductoViejo'
                    LIMIT 1
                ";

                    if (!$mysqli->query($sqlRevertirStock)) {
                        throw new Exception($mysqli->error);
                    }

                    $obsMov = $mysqli->real_escape_string(
                        "Reversión por edición de orden de ingreso #" . $idOrdenEditar . " - " . $productoNombreViejo
                    );

                    $sqlMovReversa = "
                    INSERT INTO MovimientosStock
                    (
                        idProducto,
                        Tipo,
                        idReferencia,
                        Cantidad,
                        StockAnterior,
                        StockNuevo,
                        Usuario,
                        Observaciones,
                        Fecha
                    )
                    VALUES
                    (
                        '$idProductoViejo',
                        'REVERSA_INGRESO_COMPRA',
                        '$idOrdenEditar',
                        '$cantidadVieja',
                        '$stockAnterior',
                        '$stockNuevo',
                        '$usuario',
                        '$obsMov',
                        NOW()
                    )
                ";

                    if (!$mysqli->query($sqlMovReversa)) {
                        throw new Exception($mysqli->error);
                    }
                }

                $sqlEliminarDetalleViejo = "
                UPDATE OrdenesCompraDetalle
                SET Eliminado = 1
                WHERE idOrdenCompra = '$idOrdenEditar'
            ";

                if (!$mysqli->query($sqlEliminarDetalleViejo)) {
                    throw new Exception($mysqli->error);
                }

                $idOrden = $idOrdenEditar;
            } else {

                $sqlOrden = "
                INSERT INTO OrdenesCompra
                (Fecha, Usuario, Observaciones, TotalItems, Eliminado)
                VALUES
                (NOW(), '$usuario', '$observaciones', 0, 0)
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
            }

            /*
        Aplicar nuevo detalle
        */
            $totalItems = 0;

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
                FOR UPDATE
            ");

                if (!$stmt) {
                    throw new Exception($mysqli->error);
                }

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
                (
                    idOrdenCompra,
                    idProducto,
                    ProductoNombre,
                    Cantidad,
                    StockAnterior,
                    StockNuevo,
                    Eliminado,
                    FechaAlta
                )
                VALUES
                (
                    '$idOrden',
                    '$idProducto',
                    '$productoNombre',
                    '$cantidad',
                    '$stockAnterior',
                    '$stockNuevo',
                    0,
                    NOW()
                )
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

                $obsMov = $mysqli->real_escape_string(
                    ($idOrdenEditar > 0 ? "Reaplicación por edición de orden de ingreso #" : "Ingreso por orden de compra #") . $idOrden
                );

                $sqlMov = "
                INSERT INTO MovimientosStock
                (
                    idProducto,
                    Tipo,
                    idReferencia,
                    Cantidad,
                    StockAnterior,
                    StockNuevo,
                    Usuario,
                    Observaciones,
                    Fecha
                )
                VALUES
                (
                    '$idProducto',
                    'INGRESO_COMPRA',
                    '$idOrden',
                    '$cantidad',
                    '$stockAnterior',
                    '$stockNuevo',
                    '$usuario',
                    '$obsMov',
                    NOW()
                )
            ";

                if (!$mysqli->query($sqlMov)) {
                    throw new Exception($mysqli->error);
                }

                $totalItems += $cantidad;
            }

            $sqlActualizarOrden = "
            UPDATE OrdenesCompra
            SET 
                Usuario = '$usuario',
                Observaciones = '$observaciones',
                TotalItems = '$totalItems'
            WHERE id = '$idOrden'
            LIMIT 1
        ";

            if (!$mysqli->query($sqlActualizarOrden)) {
                throw new Exception($mysqli->error);
            }

            $mysqli->commit();

            echo json_encode(array(
                "success" => 1,
                "idOrden" => $idOrden,
                "NumeroOrden" => $idOrden,
                "editada" => $idOrdenEditar > 0 ? 1 : 0
            ));
        } catch (Exception $e) {

            $mysqli->rollback();

            echo json_encode(array(
                "success" => 0,
                "error" => $e->getMessage()
            ));
        }

        break;

    case 'eliminar':

        $idOrden = isset($_POST['idOrden']) ? (int)$_POST['idOrden'] : 0;

        $usuario = '';

        if (isset($_SESSION['user_name']) && $_SESSION['user_name'] != '') {
            $usuario = $_SESSION['user_name'];
        } elseif (isset($_SESSION['Usuario']) && $_SESSION['Usuario'] != '') {
            $usuario = $_SESSION['Usuario'];
        } else {
            $usuario = 'Sistema';
        }

        $usuario = $mysqli->real_escape_string($usuario);

        if ($idOrden <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Orden inválida."
            ));
            exit;
        }

        $mysqli->begin_transaction();

        try {

            $sqlOrden = "
            SELECT id
            FROM OrdenesCompra
            WHERE id = '$idOrden'
              AND Eliminado = 0
            LIMIT 1
            FOR UPDATE
        ";

            $resOrden = $mysqli->query($sqlOrden);

            if (!$resOrden) {
                throw new Exception($mysqli->error);
            }

            if ($resOrden->num_rows == 0) {
                throw new Exception("La orden no existe o ya fue anulada.");
            }

            $sqlDetalle = "
            SELECT 
                idProducto,
                ProductoNombre,
                Cantidad
            FROM OrdenesCompraDetalle
            WHERE idOrdenCompra = '$idOrden'
              AND Eliminado = 0
        ";

            $resDetalle = $mysqli->query($sqlDetalle);

            if (!$resDetalle) {
                throw new Exception($mysqli->error);
            }

            while ($item = $resDetalle->fetch_assoc()) {

                $idProducto = (int)$item['idProducto'];
                $cantidad = (int)$item['Cantidad'];
                $productoNombre = $mysqli->real_escape_string($item['ProductoNombre']);

                if ($idProducto <= 0 || $cantidad <= 0) {
                    continue;
                }

                $sqlProducto = "
                SELECT Stock
                FROM Productos
                WHERE id = '$idProducto'
                LIMIT 1
                FOR UPDATE
            ";

                $resProducto = $mysqli->query($sqlProducto);

                if (!$resProducto) {
                    throw new Exception($mysqli->error);
                }

                $producto = $resProducto->fetch_assoc();

                if (!$producto) {
                    throw new Exception("Producto inexistente: " . $idProducto);
                }

                $stockAnterior = (int)$producto['Stock'];
                $stockNuevo = $stockAnterior - $cantidad;

                if ($stockNuevo < 0) {
                    throw new Exception("No se puede anular porque el stock de {$productoNombre} quedaría negativo.");
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

                $obsMov = $mysqli->real_escape_string(
                    "Anulación de orden de ingreso #" . $idOrden . " - " . $productoNombre
                );

                $sqlMov = "
                INSERT INTO MovimientosStock
                (
                    idProducto,
                    Tipo,
                    idReferencia,
                    Cantidad,
                    StockAnterior,
                    StockNuevo,
                    Usuario,
                    Observaciones,
                    Fecha
                )
                VALUES
                (
                    '$idProducto',
                    'ANULACION_INGRESO_COMPRA',
                    '$idOrden',
                    '$cantidad',
                    '$stockAnterior',
                    '$stockNuevo',
                    '$usuario',
                    '$obsMov',
                    NOW()
                )
            ";

                if (!$mysqli->query($sqlMov)) {
                    throw new Exception($mysqli->error);
                }
            }

            $sqlEliminarDetalle = "
            UPDATE OrdenesCompraDetalle
            SET Eliminado = 1
            WHERE idOrdenCompra = '$idOrden'
        ";

            if (!$mysqli->query($sqlEliminarDetalle)) {
                throw new Exception($mysqli->error);
            }

            $sqlEliminarOrden = "
            UPDATE OrdenesCompra
            SET Eliminado = 1
            WHERE id = '$idOrden'
            LIMIT 1
        ";

            if (!$mysqli->query($sqlEliminarOrden)) {
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
