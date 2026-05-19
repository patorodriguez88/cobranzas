<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

function responder($data)
{
    echo json_encode($data);
    exit;
}

function usuarioActual($mysqli)
{
    if (isset($_SESSION['user_name']) && $_SESSION['user_name'] != '') {
        return $mysqli->real_escape_string($_SESSION['user_name']);
    }

    if (isset($_SESSION['Usuario']) && $_SESSION['Usuario'] != '') {
        return $mysqli->real_escape_string($_SESSION['Usuario']);
    }

    return 'Sistema';
}

switch ($accion) {

    case 'productos':

        $sql = "SELECT 
            P.id,
            P.Nombre,
            (
                IFNULL((
                    SELECT SUM(OCD.Cantidad)
                    FROM OrdenesCompraDetalle OCD
                    INNER JOIN OrdenesCompra OC 
                        ON OC.id = OCD.idOrdenCompra
                    WHERE OCD.idProducto = P.id
                      AND IFNULL(OCD.Eliminado,0) = 0
                      AND IFNULL(OC.Eliminado,0) = 0
                ),0)
                -
                IFNULL((
                    SELECT SUM(VCS.Cantidad)
                    FROM VentasConsumoStock VCS
                    INNER JOIN Ventas V 
                        ON V.id = VCS.idVenta
                    WHERE VCS.idProducto = P.id
                      AND IFNULL(VCS.Eliminado,0) = 0
                      AND IFNULL(V.Eliminado,0) = 0
                ),0)
            ) AS Stock
        FROM Productos P
        WHERE P.Eliminado = 0
          AND P.Activo = 1
        ORDER BY P.Nombre ASC
    ";

        $res = $mysqli->query($sql);

        if (!$res) {
            responder([
                "success" => 0,
                "error" => $mysqli->error
            ]);
        }

        $data = [];

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        responder($data);

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
                AND IFNULL(OCD.Eliminado, 0) = 0
            WHERE IFNULL(OC.Eliminado, 0) = 0
            GROUP BY OC.id
            ORDER BY OC.NumeroOrden DESC
        ";

        $res = $mysqli->query($sql);

        if (!$res) {
            responder([
                "data" => [],
                "error" => $mysqli->error
            ]);
        }

        $data = [];

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        responder([
            "data" => $data
        ]);

        break;

    case 'ver':

        $idOrden = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($idOrden <= 0) {
            responder([
                "success" => 0,
                "error" => "Orden inválida."
            ]);
        }

        $sqlOrden = "
            SELECT 
                id,
                NumeroOrden,
                Fecha,
                Usuario,
                Observaciones,
                TotalItems
            FROM OrdenesCompra
            WHERE id = '$idOrden'
              AND IFNULL(Eliminado, 0) = 0
            LIMIT 1
        ";

        $resOrden = $mysqli->query($sqlOrden);

        if (!$resOrden) {
            responder([
                "success" => 0,
                "error" => $mysqli->error
            ]);
        }

        if ($resOrden->num_rows == 0) {
            responder([
                "success" => 0,
                "error" => "Orden no encontrada."
            ]);
        }

        $orden = $resOrden->fetch_assoc();

        $sqlDetalle = "
            SELECT 
                OCD.id,
                OCD.idOrdenCompra,
                OCD.idProducto,
                OCD.ProductoNombre,
                OCD.Cantidad,
                OCD.StockAnterior,
                OCD.StockNuevo,
                P.Stock AS StockActual
            FROM OrdenesCompraDetalle OCD
            LEFT JOIN Productos P 
                ON P.id = OCD.idProducto
            WHERE OCD.idOrdenCompra = '$idOrden'
              AND IFNULL(OCD.Eliminado, 0) = 0
            ORDER BY OCD.id ASC
        ";

        $resDetalle = $mysqli->query($sqlDetalle);

        if (!$resDetalle) {
            responder([
                "success" => 0,
                "error" => $mysqli->error
            ]);
        }

        $detalle = [];

        while ($row = $resDetalle->fetch_assoc()) {
            $detalle[] = $row;
        }

        responder([
            "success" => 1,
            "orden" => $orden,
            "detalle" => $detalle
        ]);

        break;

    case 'guardar':

        $idOrdenEditar = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $usuario = usuarioActual($mysqli);

        $observaciones = isset($_POST['Observaciones'])
            ? $mysqli->real_escape_string($_POST['Observaciones'])
            : '';

        $detalleJson = isset($_POST['detalle'])
            ? $_POST['detalle']
            : '[]';

        $detalle = json_decode($detalleJson, true);

        if (!is_array($detalle) || count($detalle) == 0) {

            responder([
                "success" => 0,
                "error" => "La orden no tiene productos."
            ]);
        }

        $mysqli->begin_transaction();

        try {

            /*
        =====================================================
        EDICIÓN
        =====================================================
        */

            if ($idOrdenEditar > 0) {

                $sqlOrdenExiste = "
                SELECT id
                FROM OrdenesCompra
                WHERE id = '$idOrdenEditar'
                AND IFNULL(Eliminado,0)=0
                LIMIT 1
                FOR UPDATE
            ";

                $resOrdenExiste = $mysqli->query($sqlOrdenExiste);

                if (!$resOrdenExiste) {
                    throw new Exception($mysqli->error);
                }

                if ($resOrdenExiste->num_rows == 0) {
                    throw new Exception("La orden no existe.");
                }

                $totalItems = 0;

                $productosEditados = [];

                foreach ($detalle as $item) {

                    $idProducto = (int)$item['idProducto'];
                    $cantidadNueva = (float)$item['Cantidad'];

                    if ($idProducto <= 0 || $cantidadNueva < 0) {

                        continue;
                    }

                    $productosEditados[] = $idProducto;

                    /*
                ============================================
                BUSCAR DETALLE ACTUAL
                ============================================
                */

                    $sqlDetalleActual = "
                    SELECT 
                        OCD.id,
                        OCD.Cantidad,
                        OCD.ProductoNombre,
                        P.Stock
                    FROM OrdenesCompraDetalle OCD
                    INNER JOIN Productos P 
                        ON P.id = OCD.idProducto
                    WHERE OCD.idOrdenCompra = '$idOrdenEditar'
                    AND OCD.idProducto = '$idProducto'
                    AND IFNULL(OCD.Eliminado,0)=0
                    LIMIT 1
                    FOR UPDATE
                ";

                    $resDetalleActual = $mysqli->query($sqlDetalleActual);

                    if (!$resDetalleActual) {
                        throw new Exception($mysqli->error);
                    }

                    /*
                ============================================
                PRODUCTO NUEVO EN LA OI
                ============================================
                */

                    if ($resDetalleActual->num_rows == 0) {

                        $stmtNuevo = $mysqli->prepare("
                        SELECT Nombre, Stock
                        FROM Productos
                        WHERE id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                        $stmtNuevo->bind_param("i", $idProducto);
                        $stmtNuevo->execute();

                        $resNuevo = $stmtNuevo->get_result();

                        if ($resNuevo->num_rows == 0) {
                            throw new Exception("Producto inexistente.");
                        }

                        $productoNuevo = $resNuevo->fetch_assoc();

                        $productoNombre = $mysqli->real_escape_string($productoNuevo['Nombre']);

                        $stockAnterior = (float)$productoNuevo['Stock'];

                        $stockNuevo = $stockAnterior + $cantidadNueva;

                        $sqlInsertDetalle = "
                        INSERT INTO OrdenesCompraDetalle
                        (
                            idOrdenCompra,
                            idProducto,
                            ProductoNombre,
                            Cantidad,
                            StockAnterior,
                            StockNuevo,
                            Eliminado
                        )
                        VALUES
                        (
                            '$idOrdenEditar',
                            '$idProducto',
                            '$productoNombre',
                            '$cantidadNueva',
                            '$stockAnterior',
                            '$stockNuevo',
                            0
                        )
                    ";

                        if (!$mysqli->query($sqlInsertDetalle)) {
                            throw new Exception($mysqli->error);
                        }
                        $obsMov = $mysqli->real_escape_string(
                            "Nuevo producto agregado en edición OI #{$idOrdenEditar}"
                        );

                        $sqlMov = "INSERT INTO MovimientosStock
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
                                'NUEVO_PRODUCTO_OI',
                                '$idOrdenEditar',
                                '$cantidadNueva',
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
                        $sqlUpdateStock = "
                        UPDATE Productos
                        SET Stock = '$stockNuevo'
                        WHERE id = '$idProducto'
                        LIMIT 1
                    ";

                        if (!$mysqli->query($sqlUpdateStock)) {
                            throw new Exception($mysqli->error);
                        }

                        $totalItems += $cantidadNueva;

                        continue;
                    }

                    /*
                ============================================
                PRODUCTO EXISTENTE
                ============================================
                */

                    $detalleActual = $resDetalleActual->fetch_assoc();

                    $idDetalle = (int)$detalleActual['id'];

                    $cantidadVieja = (float)$detalleActual['Cantidad'];

                    $productoNombre = $mysqli->real_escape_string($detalleActual['ProductoNombre']);

                    $stockActualProducto = (float)$detalleActual['Stock'];

                    /*
                CONSUMIDO
                */

                    $sqlConsumido = "
                    SELECT IFNULL(SUM(Cantidad),0) AS Consumido
                    FROM VentasConsumoStock
                    WHERE idOrdenCompraDetalle = '$idDetalle'
                    AND IFNULL(Eliminado,0)=0
                ";

                    $resConsumido = $mysqli->query($sqlConsumido);

                    if (!$resConsumido) {
                        throw new Exception($mysqli->error);
                    }

                    $consumido = (float)$resConsumido->fetch_assoc()['Consumido'];

                    if ($cantidadNueva < $consumido) {

                        throw new Exception(
                            "No podés dejar {$productoNombre} en {$cantidadNueva}. Ya hay {$consumido} consumidos."
                        );
                    }

                    /*
                DIFERENCIA
                */

                    $diferencia = $cantidadNueva - $cantidadVieja;

                    $nuevoStockProducto = $stockActualProducto + $diferencia;

                    if ($nuevoStockProducto < 0) {

                        throw new Exception(
                            "El stock quedaría negativo para {$productoNombre}"
                        );
                    }

                    /*
                UPDATE DETALLE
                */

                    $sqlUpdateDetalle = "
                    UPDATE OrdenesCompraDetalle
                    SET Cantidad = '$cantidadNueva'
                    WHERE id = '$idDetalle'
                    LIMIT 1
                ";

                    if (!$mysqli->query($sqlUpdateDetalle)) {
                        throw new Exception($mysqli->error);
                    }

                    /*
                UPDATE STOCK
                */

                    $sqlUpdateProducto = "
                    UPDATE Productos
                    SET Stock = '$nuevoStockProducto'
                    WHERE id = '$idProducto'
                    LIMIT 1
                ";

                    if (!$mysqli->query($sqlUpdateProducto)) {
                        throw new Exception($mysqli->error);
                    }
                    if ($diferencia != 0) {

                        $obsMov = $mysqli->real_escape_string(
                            "Edición OI #{$idOrdenEditar} - {$productoNombre}"
                        );

                        $tipoMov = $diferencia > 0
                            ? 'AJUSTE_POSITIVO_OI'
                            : 'AJUSTE_NEGATIVO_OI';

                        $sqlMov = "INSERT INTO MovimientosStock
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
                                '$tipoMov',
                                '$idOrdenEditar',
                                '" . abs($diferencia) . "',
                                '$stockActualProducto',
                                '$nuevoStockProducto',
                                '$usuario',
                                '$obsMov',
                                NOW()
                            )
                        ";

                        if (!$mysqli->query($sqlMov)) {
                            throw new Exception($mysqli->error);
                        }
                    }
                    $totalItems += $cantidadNueva;
                }

                /*
            ============================================
            PRODUCTOS ELIMINADOS DE LA OI
            ============================================
            */

                $sqlViejos = "SELECT 
                    OCD.id,
                    OCD.idProducto,
                    OCD.ProductoNombre,
                    OCD.Cantidad,
                    P.Stock
                FROM OrdenesCompraDetalle OCD
                INNER JOIN Productos P 
                    ON P.id = OCD.idProducto
                WHERE OCD.idOrdenCompra = '$idOrdenEditar'
                AND IFNULL(OCD.Eliminado,0)=0
            ";

                $resViejos = $mysqli->query($sqlViejos);

                while ($viejo = $resViejos->fetch_assoc()) {

                    $idProductoViejo = (int)$viejo['idProducto'];

                    if (in_array($idProductoViejo, $productosEditados)) {
                        continue;
                    }

                    $idDetalleViejo = (int)$viejo['id'];

                    $cantidadVieja = (float)$viejo['Cantidad'];

                    $stockActual = (float)$viejo['Stock'];

                    /*
                VALIDAR CONSUMIDO
                */

                    $sqlConsumido = "SELECT IFNULL(SUM(Cantidad),0) AS Consumido
                    FROM VentasConsumoStock
                    WHERE idOrdenCompraDetalle = '$idDetalleViejo'
                    AND IFNULL(Eliminado,0)=0
                ";

                    $resConsumido = $mysqli->query($sqlConsumido);

                    $consumido = (float)$resConsumido->fetch_assoc()['Consumido'];

                    if ($consumido > 0) {

                        throw new Exception(
                            "No podés eliminar productos ya consumidos."
                        );
                    }

                    /*
                ELIMINAR DETALLE
                */

                    $sqlEliminarDetalle = " UPDATE OrdenesCompraDetalle
                    SET Eliminado = 1
                    WHERE id = '$idDetalleViejo'
                    LIMIT 1
                ";

                    if (!$mysqli->query($sqlEliminarDetalle)) {
                        throw new Exception($mysqli->error);
                    }

                    /*
                REVERTIR STOCK
                */

                    $nuevoStock = $stockActual - $cantidadVieja;

                    $sqlStock = "UPDATE Productos
                    SET Stock = '$nuevoStock'
                    WHERE id = '$idProductoViejo'
                    LIMIT 1
                ";

                    if (!$mysqli->query($sqlStock)) {
                        throw new Exception($mysqli->error);
                    }
                    $obsMov = $mysqli->real_escape_string(
                        "Producto eliminado en edición OI #{$idOrdenEditar}"
                    );

                    $sqlMov = "INSERT INTO MovimientosStock
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
                            'ELIMINACION_PRODUCTO_OI',
                            '$idOrdenEditar',
                            '$cantidadVieja',
                            '$stockActual',
                            '$nuevoStock',
                            '$usuario',
                            '$obsMov',
                            NOW()
                        )
                    ";

                    if (!$mysqli->query($sqlMov)) {
                        throw new Exception($mysqli->error);
                    }
                }


                /*
            UPDATE OI
            */

                $sqlActualizarOrden = "
                UPDATE OrdenesCompra
                SET 
                    Observaciones = '$observaciones',
                    TotalItems = '$totalItems'
                WHERE id = '$idOrdenEditar'
                LIMIT 1
            ";

                if (!$mysqli->query($sqlActualizarOrden)) {
                    throw new Exception($mysqli->error);
                }

                $mysqli->commit();

                responder([
                    "success" => 1,
                    "editada" => 1,
                    "NumeroOrden" => $idOrdenEditar
                ]);
            }

            /*
        =====================================================
        NUEVA OI
        =====================================================
        */

            $sqlOrden = "
            INSERT INTO OrdenesCompra
            (
                Fecha,
                Usuario,
                Observaciones,
                TotalItems,
                Eliminado
            )
            VALUES
            (
                NOW(),
                '$usuario',
                '$observaciones',
                0,
                0
            )
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

            $totalItems = 0;

            foreach ($detalle as $item) {

                $idProducto = (int)$item['idProducto'];
                $cantidad = (float)$item['Cantidad'];

                if ($idProducto <= 0 || $cantidad <= 0) {
                    continue;
                }

                $stmt = $mysqli->prepare("
                SELECT Nombre, Stock
                FROM Productos
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

                $stmt->bind_param("i", $idProducto);
                $stmt->execute();

                $resProducto = $stmt->get_result();

                $producto = $resProducto->fetch_assoc();

                if (!$producto) {
                    throw new Exception("Producto inexistente.");
                }

                $productoNombre = $mysqli->real_escape_string($producto['Nombre']);

                $stockAnterior = (float)$producto['Stock'];

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
                    Eliminado
                )
                VALUES
                (
                    '$idOrden',
                    '$idProducto',
                    '$productoNombre',
                    '$cantidad',
                    '$stockAnterior',
                    '$stockNuevo',
                    0
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
                    "Ingreso por orden de ingreso #{$idOrden}"
                );

                $sqlMov = "INSERT INTO MovimientosStock
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
            SET TotalItems = '$totalItems'
            WHERE id = '$idOrden'
            LIMIT 1
        ";

            if (!$mysqli->query($sqlActualizarOrden)) {
                throw new Exception($mysqli->error);
            }

            $mysqli->commit();

            responder([
                "success" => 1,
                "idOrden" => $idOrden,
                "NumeroOrden" => $idOrden
            ]);
        } catch (Exception $e) {

            $mysqli->rollback();

            responder([
                "success" => 0,
                "error" => $e->getMessage()
            ]);
        }

        break;

    case 'eliminar':

        $idOrden = isset($_POST['idOrden']) ? (int)$_POST['idOrden'] : 0;
        $usuario = usuarioActual($mysqli);

        if ($idOrden <= 0) {
            responder([
                "success" => 0,
                "error" => "Orden inválida."
            ]);
        }

        $mysqli->begin_transaction();

        try {

            $sqlOrden = "
                SELECT id
                FROM OrdenesCompra
                WHERE id = '$idOrden'
                  AND IFNULL(Eliminado, 0) = 0
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

            /*
                Validación importante:
                No dejamos anular una orden si ya fue consumida por ventas.
            */
            $sqlConsumo = "
                SELECT COUNT(*) AS Total
                FROM VentasConsumoStock VCS
                INNER JOIN OrdenesCompraDetalle OCD 
                    ON OCD.id = VCS.idOrdenCompraDetalle
                WHERE OCD.idOrdenCompra = '$idOrden'
                  AND IFNULL(VCS.Eliminado, 0) = 0
                  AND IFNULL(OCD.Eliminado, 0) = 0
            ";

            $resConsumo = $mysqli->query($sqlConsumo);

            if (!$resConsumo) {
                throw new Exception($mysqli->error);
            }

            $rowConsumo = $resConsumo->fetch_assoc();

            if ((int)$rowConsumo['Total'] > 0) {
                throw new Exception("No se puede anular esta orden porque ya tiene stock consumido por ventas.");
            }

            $sqlDetalle = "
                SELECT 
                    idProducto,
                    ProductoNombre,
                    Cantidad
                FROM OrdenesCompraDetalle
                WHERE idOrdenCompra = '$idOrden'
                  AND IFNULL(Eliminado, 0) = 0
                FOR UPDATE
            ";

            $resDetalle = $mysqli->query($sqlDetalle);

            if (!$resDetalle) {
                throw new Exception($mysqli->error);
            }

            while ($item = $resDetalle->fetch_assoc()) {

                $idProducto = (int)$item['idProducto'];
                $cantidad = (float)$item['Cantidad'];
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

                $stockAnterior = (float)$producto['Stock'];
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

            responder([
                "success" => 1
            ]);
        } catch (Exception $e) {

            $mysqli->rollback();

            responder([
                "success" => 0,
                "error" => $e->getMessage()
            ]);
        }

        break;

    default:

        responder([
            "success" => 0,
            "error" => "Acción inválida."
        ]);

        break;
}
