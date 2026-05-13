<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

function normalizarTexto($txt)
{
    $txt = strtoupper(trim($txt));
    $txt = str_replace(
        array('Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'),
        array('A', 'E', 'I', 'O', 'U', 'N'),
        $txt
    );
    $txt = preg_replace('/[^A-Z0-9 ]/', ' ', $txt);
    $txt = preg_replace('/\s+/', ' ', $txt);
    return trim($txt);
}

function buscarProductoPorCodigo($mysqli, $codigo, $nombreLike)
{
    $codigo = $mysqli->real_escape_string($codigo);
    $nombreLike = $mysqli->real_escape_string($nombreLike);

    $sql = "
        SELECT id, Nombre, PrecioVenta, Stock
        FROM Productos
        WHERE Eliminado = 0
          AND Activo = 1
          AND (
                Codigo = '$codigo'
                OR UPPER(Nombre) LIKE '%$nombreLike%'
          )
        ORDER BY id ASC
        LIMIT 1
    ";

    $res = $mysqli->query($sql);

    if (!$res || $res->num_rows == 0) {
        return null;
    }

    return $res->fetch_assoc();
}

switch ($accion) {

    case 'validar':

        $datosJson = isset($_POST['datos']) ? $_POST['datos'] : '[]';
        $datos = json_decode($datosJson, true);

        if (!is_array($datos)) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Datos inválidos."
            ));
            exit;
        }

        $salida = array();

        foreach ($datos as $row) {

            $clienteExcel = isset($row['Cliente']) ? trim($row['Cliente']) : '';
            $figuritas = isset($row['Figuritas']) ? (int)$row['Figuritas'] : 0;
            $album = isset($row['Album']) ? (int)$row['Album'] : 0;

            if ($clienteExcel == '') {
                continue;
            }

            $clienteNorm = normalizarTexto($clienteExcel);

            $palabras = explode(' ', $clienteNorm);

            $where = array();

            foreach ($palabras as $p) {

                $p = trim($p);

                if (strlen($p) >= 3) {

                    $p = $mysqli->real_escape_string($p);

                    $where[] = "
                    UPPER(
                        REPLACE(
                            REPLACE(
                                REPLACE(RazonSocial,'.',''),
                            ',',''),
                        '-','')
                    ) LIKE '%$p%'
                ";
                }
            }

            if (count($where) == 0) {

                $like = $mysqli->real_escape_string($clienteNorm);

                $where[] = "RazonSocial LIKE '%$like%'";
            }

            $sql = "
            SELECT
                id,
                Ncliente,
                RazonSocial,
                Dni,
                Celular,
                Suspendido
            FROM Clientes
            WHERE Suspendido = 0
              AND (
                    " . implode(" OR ", $where) . "
              )
            ORDER BY RazonSocial ASC
            LIMIT 20
        ";

            $res = $mysqli->query($sql);

            $coincidencias = array();
            $idCliente = 0;
            $estado = "SIN_COINCIDENCIA";

            while ($c = $res->fetch_assoc()) {

                $coincidencias[] = $c;

                $razonNorm = normalizarTexto($c['RazonSocial']);

                if ($razonNorm == $clienteNorm) {

                    $idCliente = (int)$c['id'];
                    $estado = "OK";
                }
            }

            if ($idCliente == 0 && count($coincidencias) == 1) {

                $idCliente = (int)$coincidencias[0]['id'];
                $estado = "OK";
            } elseif ($idCliente == 0 && count($coincidencias) > 1) {

                $estado = "DUDOSO";
            }

            $salida[] = array(
                "Cliente" => $clienteExcel,
                "Figuritas" => $figuritas,
                "Album" => $album,
                "idCliente" => $idCliente,
                "estado" => $estado,
                "coincidencias" => $coincidencias
            );
        }

        echo json_encode(array(
            "success" => 1,
            "data" => $salida
        ));

        break;


    case 'importar':

        $datosJson = isset($_POST['datos']) ? $_POST['datos'] : '[]';
        $datos = json_decode($datosJson, true);

        if (!is_array($datos) || count($datos) == 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "No hay ventas para importar."
            ));
            exit;
        }

        $usuario = isset($_SESSION['user_name']) && $_SESSION['user_name'] != ''
            ? $mysqli->real_escape_string($_SESSION['user_name'])
            : 'Sistema';

        $productoFiguritas = buscarProductoPorCodigo($mysqli, '1', 'FIGURITA');
        $productoAlbum = buscarProductoPorCodigo($mysqli, '2', 'ALBUM');

        if (!$productoFiguritas) {
            echo json_encode(array(
                "success" => 0,
                "error" => "No se encontró el producto Figuritas."
            ));
            exit;
        }

        if (!$productoAlbum) {
            echo json_encode(array(
                "success" => 0,
                "error" => "No se encontró el producto Álbum."
            ));
            exit;
        }

        $mysqli->begin_transaction();

        try {

            $totalVentas = 0;

            foreach ($datos as $row) {

                $idCliente = isset($row['idCliente']) ? (int)$row['idCliente'] : 0;
                $cantFiguritas = isset($row['Figuritas']) ? (int)$row['Figuritas'] : 0;
                $cantAlbum = isset($row['Album']) ? (int)$row['Album'] : 0;

                if ($idCliente <= 0) {
                    throw new Exception("Hay una fila sin cliente seleccionado.");
                }

                if ($cantFiguritas <= 0 && $cantAlbum <= 0) {
                    continue;
                }

                $detalle = array();

                if ($cantFiguritas > 0) {
                    $detalle[] = array(
                        "idProducto" => (int)$productoFiguritas['id'],
                        "Nombre" => $productoFiguritas['Nombre'],
                        "Cantidad" => $cantFiguritas,
                        "PrecioUnitario" => (float)$productoFiguritas['PrecioVenta']
                    );
                }

                if ($cantAlbum > 0) {
                    $detalle[] = array(
                        "idProducto" => (int)$productoAlbum['id'],
                        "Nombre" => $productoAlbum['Nombre'],
                        "Cantidad" => $cantAlbum,
                        "PrecioUnitario" => (float)$productoAlbum['PrecioVenta']
                    );
                }

                $total = 0;

                foreach ($detalle as $d) {
                    $total += $d['Cantidad'] * $d['PrecioUnitario'];
                }

                $observaciones = $mysqli->real_escape_string("Venta importada desde Excel");

                $sqlVenta = "
                    INSERT INTO Ventas
                    (
                        Fecha,
                        idCliente,
                        Observaciones,
                        Total,
                        TotalPagado,
                        Saldo,
                        EstadoPago,
                        Usuario,
                        Eliminado
                    )
                    VALUES
                    (
                        NOW(),
                        '$idCliente',
                        '$observaciones',
                        '$total',
                        0,
                        '$total',
                        'PENDIENTE',
                        '$usuario',
                        0
                    )
                ";

                if (!$mysqli->query($sqlVenta)) {
                    throw new Exception($mysqli->error);
                }

                $idVenta = $mysqli->insert_id;

                if (!$mysqli->query("UPDATE Ventas SET NumeroVenta = '$idVenta' WHERE id = '$idVenta' LIMIT 1")) {
                    throw new Exception($mysqli->error);
                }

                foreach ($detalle as $d) {

                    $idProducto = (int)$d['idProducto'];
                    $nombre = $mysqli->real_escape_string($d['Nombre']);
                    $cantidad = (int)$d['Cantidad'];
                    $precio = (float)$d['PrecioUnitario'];
                    $subtotal = $cantidad * $precio;

                    $sqlProducto = "
                        SELECT Stock
                        FROM Productos
                        WHERE id = '$idProducto'
                        LIMIT 1
                        FOR UPDATE
                    ";

                    $resProducto = $mysqli->query($sqlProducto);

                    if (!$resProducto || $resProducto->num_rows == 0) {
                        throw new Exception("Producto inexistente: " . $idProducto);
                    }

                    $productoActual = $resProducto->fetch_assoc();

                    $stockAnterior = (int)$productoActual['Stock'];

                    if ($cantidad > $stockAnterior) {
                        throw new Exception("Stock insuficiente para " . $nombre . ". Stock: " . $stockAnterior . " / requerido: " . $cantidad);
                    }

                    $stockNuevo = $stockAnterior - $cantidad;

                    $sqlDetalle = "
                        INSERT INTO VentasDetalle
                        (
                            idVenta,
                            idProducto,
                            ProductoNombre,
                            Cantidad,
                            PrecioUnitario,
                            Subtotal,
                            Eliminado
                        )
                        VALUES
                        (
                            '$idVenta',
                            '$idProducto',
                            '$nombre',
                            '$cantidad',
                            '$precio',
                            '$subtotal',
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

                    $obsMov = $mysqli->real_escape_string("Venta importada desde Excel #" . $idVenta . " - " . $nombre);

                    $sqlMov = "
                        INSERT INTO MovimientosStock
                        (
                            idProducto,
                            Tipo,
                            TipoMovimiento,
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
                            'VENTA',
                            'VENTA_IMPORTADA_EXCEL',
                            '$idVenta',
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

                $totalVentas++;
            }

            $mysqli->commit();

            echo json_encode(array(
                "success" => 1,
                "total" => $totalVentas
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
