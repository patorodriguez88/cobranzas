<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include_once __DIR__ . "/../../../conexion/conexioni.php";

function recalcularEstadoVenta($mysqli, $idVenta)
{
    $idVenta = (int)$idVenta;

    $sql = "
        SELECT 
            V.Total,

            IFNULL((
                SELECT SUM(
                    CASE 
                        WHEN CC.id IS NOT NULL THEN CC.Importe
                        ELSE CV.ImporteAplicado
                    END
                )
                FROM CobranzasVentas CV
                LEFT JOIN Cobranza_conciliacion CC 
                    ON CC.id_cobranza = CV.idCobranza
                    
                WHERE CV.idVenta = '$idVenta'
                  AND IFNULL(CV.Eliminado,0) = 0
            ),0) AS TotalPagadoReal,

            IFNULL((
                SELECT SUM(AP.importe)
                FROM Ventas_Ajustes_Pago AP
                WHERE AP.idVenta = V.id
                  AND AP.eliminado = 0
            ),0) AS Ajustes

        FROM Ventas V
        WHERE V.id = '$idVenta'
        LIMIT 1
    ";

    $res = $mysqli->query($sql);

    if (!$res || $res->num_rows == 0) {
        throw new Exception("Venta inexistente para recalcular.");
    }

    $row = $res->fetch_assoc();

    $total = (float)$row['Total'];
    $totalPagadoReal = (float)$row['TotalPagadoReal'];
    $ajustes = (float)$row['Ajustes'];

    $saldo = $total - $totalPagadoReal - $ajustes;

    if ($saldo <= 0) {
        $estado = 'PAGADA';
    } elseif ($totalPagadoReal > 0) {
        $estado = 'PARCIAL';
    } else {
        $estado = 'PENDIENTE';
    }

    $sqlUpdate = "
        UPDATE Ventas
        SET 
            TotalPagado = '$totalPagadoReal',
            Saldo = '$saldo',
            EstadoPago = '$estado'
        WHERE id = '$idVenta'
        LIMIT 1
    ";

    if (!$mysqli->query($sqlUpdate)) {
        throw new Exception($mysqli->error);
    }
}

function eliminarOrdenVentaWepoint($mysqli, $idVenta)
{
    $token = '1383|1w3olMBz6851a6JdfbA1GH0jdF5QdUnwUtAfehSL0f00e3a5';

    $sql = "
        SELECT 
            wepoint_id_orden_venta,
            NumeroOrdenVenta
        FROM Ventas
        WHERE id = '$idVenta'
        LIMIT 1
    ";

    $res = $mysqli->query($sql);

    if (!$res) {
        throw new Exception($mysqli->error);
    }

    $venta = $res->fetch_assoc();

    if (!$venta) {
        throw new Exception("Venta inexistente.");
    }

    $idOrdenWepoint = isset($venta['wepoint_id_orden_venta'])
        ? (int)$venta['wepoint_id_orden_venta']
        : 0;

    if ($idOrdenWepoint <= 0) {
        return;
    }

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://sistema.wepoint.ar/api/v2/egresos/productos/' . $idOrdenWepoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);

    curl_close($curl);

    if ($error) {
        throw new Exception("Error eliminando OV en Wepoint: " . $error);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Wepoint rechazó la eliminación de la OV. HTTP: " . $httpCode . " - " . $response);
    }

    $responseSql = $mysqli->real_escape_string($response);

    $sqlUpdate = "
        UPDATE Ventas
        SET 
            wepoint_deleted_at = NOW(),
            wepoint_delete_response = '$responseSql'
        WHERE id = '$idVenta'
        LIMIT 1
    ";

    if (!$mysqli->query($sqlUpdate)) {
        throw new Exception("OV eliminada en Wepoint, pero no se pudo guardar auditoría local: " . $mysqli->error);
    }
}
if (isset($_GET['accion']) && $_GET['accion'] === 'exportar_ventas_excel') {

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Listado_Ventas_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "\xEF\xBB\xBF";

    $sql = "
        SELECT 
            V.id,
            V.NumeroVenta,
            DATE_FORMAT(V.Fecha, '%d/%m/%Y') AS Fecha,
            DATE_FORMAT(V.Fecha, '%H:%i') AS Hora,
            C.Ncliente,
            C.RazonSocial,
            V.Usuario,
            SUM(CASE WHEN UPPER(VD.ProductoNombre) LIKE '%FIGURITA%' THEN VD.Cantidad ELSE 0 END) AS Figuritas,
            SUM(CASE WHEN UPPER(VD.ProductoNombre) LIKE '%ALBUM%' THEN VD.Cantidad ELSE 0 END) AS Album,
            V.Total,
            V.TotalPagado,
            IFNULL((
            SELECT SUM(AP.importe)
            FROM Ventas_Ajustes_Pago AP
            WHERE AP.idVenta = V.id
            AND AP.eliminado = 0
            ), 0) AS Ajustes,
            V.Saldo,
            V.EstadoPago
        FROM Ventas V
        LEFT JOIN Clientes C ON C.id = V.idCliente
        LEFT JOIN VentasDetalle VD ON VD.idVenta = V.id AND VD.Eliminado = 0
        WHERE V.Eliminado = 0
        GROUP BY V.id
        ORDER BY V.NumeroVenta DESC
    ";

    $res = $mysqli->query($sql);

    echo "<table border='1'>";
    echo "
        <tr>
            <th>ID</th>
            <th>Venta</th>
            <th>Usuario</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>NCliente</th>
            <th>Razón Social</th>
            <th>Figuritas</th>
            <th>Álbum</th>
            <th>Total</th>
            <th>Pagado</th>
            <th>Ajustes</th>
            <th>Saldo</th>
            <th>Estado</th>
        </tr>
    ";

    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['NumeroVenta']}</td>";
        echo "<td>{$row['Usuario']}</td>";
        echo "<td>{$row['Fecha']}</td>";
        echo "<td>{$row['Hora']}</td>";
        echo "<td>{$row['Ncliente']}</td>";
        echo "<td>{$row['RazonSocial']}</td>";
        echo "<td>{$row['Figuritas']}</td>";
        echo "<td>{$row['Album']}</td>";
        echo "<td>{$row['Total']}</td>";
        echo "<td>{$row['TotalPagado']}</td>";
        echo "<td>{$row['Ajustes']}</td>";
        echo "<td>{$row['Saldo']}</td>";
        echo "<td>{$row['EstadoPago']}</td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}


header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Cordoba');

$accion = isset($_POST['accion']) ? $_POST['accion'] : '';

function normalizarNumeroCliente($valor)
{
    $valor = trim((string)$valor);
    $valor = preg_replace('/[^0-9]/', '', $valor);

    return $valor;
}


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
            Celular,
            Ncliente
        FROM Clientes
        WHERE
            RazonSocial LIKE ?
            OR Cuit LIKE ?
            OR Celular LIKE ?
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

            $textoCliente = "";

            $distribuidora = !empty($row["Distribuidora"]) ? $row["Distribuidora"] : "DINTER";

            if (!empty($row["Ncliente"])) {
                $textoCliente = "[" . $row["Ncliente"] . "] " . $row["RazonSocial"] . " (" . $distribuidora . ")";
            } else {
                $textoCliente = $row["RazonSocial"] . " (" . $distribuidora . ")";
            }

            $data[] = [
                "id" => $row['id'],
                "text" => $textoCliente,
                "cliente" => $row
            ];
        }

        echo json_encode($data);
        break;

    case 'productos':

        $sql = "SELECT 
    P.id,
    P.Nombre,
    P.PrecioVenta,
    P.StockMinimo,

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
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
            exit;
        }

        $data = array();

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data);
        break;


    case 'listar':

        $sql = "SELECT 
        V.id,
        V.Fecha,
        V.idCliente,
        C.RazonSocial,
        V.Total,

        V.Observaciones,

        COUNT(VD.id) AS Productos

    FROM Ventas V

    LEFT JOIN Clientes C ON C.id = V.idCliente

    LEFT JOIN VentasDetalle VD ON VD.idVenta = V.id

    WHERE V.Eliminado = 0

    GROUP BY V.id

    ORDER BY V.id DESC

";

        $res = $mysqli->query($sql);

        $data = array();

        while ($row = $res->fetch_assoc()) {
            $data[] = [

                "id" => $row["id"],

                "Fecha" => $row["Fecha"],

                "Cliente" => "[" . $row["idCliente"] . "] " . $row["RazonSocial"],

                "Productos" => $row["Productos"],

                "Total" => $row["Total"],

                "Observaciones" => $row["Observaciones"]

            ];
        }

        echo json_encode($data);
        break;


    case 'guardar':

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $Observaciones = isset($_POST['Observaciones']) ? $mysqli->real_escape_string($_POST['Observaciones']) : '';
        $detalleJson = isset($_POST['detalle']) ? $_POST['detalle'] : '[]';
        $idCliente = isset($_POST['idCliente']) ? (int)$_POST['idCliente'] : 0;

        if ($idCliente <= 0) {

            echo json_encode(array(

                "success" => 0,

                "error" => "Debe seleccionar un cliente."

            ));

            exit;
        }

        $detalle = json_decode($detalleJson, true);

        if (!is_array($detalle) || count($detalle) == 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "La venta no tiene productos."
            ));
            exit;
        }

        foreach ($detalle as $item) {

            $idProducto = isset($item['idProducto']) ? (int)$item['idProducto'] : 0;
            $cantidadSolicitada = isset($item['Cantidad']) ? (float)$item['Cantidad'] : 0;
            $productoNombre = isset($item['ProductoNombre']) ? trim($item['ProductoNombre']) : 'Producto';

            if ($idProducto <= 0 || $cantidadSolicitada <= 0) {
                continue;
            }

            $sqlStockDisponible = "SELECT 
            IFNULL(SUM(
                OCD.Cantidad 
                - IFNULL((
                    SELECT SUM(VCS.Cantidad)
                    FROM VentasConsumoStock VCS
                    INNER JOIN Ventas VCS_V ON VCS_V.id = VCS.idVenta
                    WHERE VCS.idOrdenCompraDetalle = OCD.id
                      AND IFNULL(VCS.Eliminado,0) = 0
                      AND IFNULL(VCS_V.Eliminado,0) = 0
                ),0)
            ),0) AS Disponible
            FROM OrdenesCompraDetalle OCD
            INNER JOIN OrdenesCompra OC ON OC.id = OCD.idOrdenCompra
            WHERE OCD.idProducto = '$idProducto'
            AND IFNULL(OCD.Eliminado,0) = 0
            AND IFNULL(OC.Eliminado,0) = 0";

            $resStockDisponible = $mysqli->query($sqlStockDisponible);

            if (!$resStockDisponible) {
                echo json_encode([
                    "success" => 0,
                    "error" => $mysqli->error
                ]);
                exit;
            }

            $rowStock = $resStockDisponible->fetch_assoc();
            $disponible = (float)$rowStock['Disponible'];

            if ($cantidadSolicitada > $disponible) {
                echo json_encode([
                    "success" => 0,
                    "error" => "Stock insuficiente para {$productoNombre}. Disponible: {$disponible}. Solicitado: {$cantidadSolicitada}."
                ]);
                exit;
            }
        }

        $total = 0;

        foreach ($detalle as $item) {
            $cantidad = isset($item['Cantidad']) ? (float)$item['Cantidad'] : 0;
            $precio = isset($item['PrecioUnitario']) ? (float)$item['PrecioUnitario'] : 0;
            $total += ($cantidad * $precio);
        }

        $mysqli->begin_transaction();

        try {
            $sqlCliente = "SELECT RazonSocial FROM Clientes WHERE id = '$idCliente' LIMIT 1";
            $resCliente = $mysqli->query($sqlCliente);

            $clienteNombre = '';

            if ($resCliente && $resCliente->num_rows > 0) {
                $rowCliente = $resCliente->fetch_assoc();
                $clienteNombre = $mysqli->real_escape_string($rowCliente['RazonSocial']);
            }

            if ($id == 0) {
                $usuario = isset($_SESSION['user_name']) && $_SESSION['user_name'] != ''
                    ? $mysqli->real_escape_string($_SESSION['user_name'])
                    : 'Sistema';



                $sqlVenta = "INSERT INTO Ventas (Fecha, idCliente,Cliente, Observaciones, Total, TotalPagado, Saldo, EstadoPago, Usuario, Eliminado)
                VALUES (NOW(), '$idCliente','$clienteNombre','$Observaciones', '$total', 0, '$total', 'PENDIENTE', '$usuario', 0)";


                if (!$mysqli->query($sqlVenta)) {
                    throw new Exception($mysqli->error);
                }

                $idVenta = $mysqli->insert_id;

                $sqlNumero = "UPDATE Ventas
                    SET NumeroVenta = '$idVenta'
                    WHERE id = '$idVenta'
                    LIMIT 1
                ";

                $mysqli->query($sqlNumero);
            } else {

                $sqlVenta = "UPDATE Ventas SET
                        idCliente = '$idCliente',
                        Cliente = '$clienteNombre',
                        Observaciones = '$Observaciones',
                        Total = '$total',
                        Saldo = ('$total' - IFNULL(TotalPagado,0))
                    WHERE id = '$id'
                    LIMIT 1
                ";

                if (!$mysqli->query($sqlVenta)) {
                    throw new Exception($mysqli->error);
                }

                $idVenta = $id;

                $sqlLiberarConsumo = "UPDATE VentasConsumoStock
                SET Eliminado = 1
                WHERE idVenta = '$idVenta'";

                if (!$mysqli->query($sqlLiberarConsumo)) {
                    throw new Exception($mysqli->error);
                }

                $sqlDeleteDetalle = "UPDATE VentasDetalle 
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

                $sqlDetalle = "INSERT INTO VentasDetalle
                    (idVenta, idProducto, ProductoNombre, Cantidad, PrecioUnitario, Subtotal, Eliminado)
                    VALUES
                    ('$idVenta', '$idProducto', '$ProductoNombre', '$Cantidad', '$PrecioUnitario', '$Subtotal', 0)
                ";

                if (!$mysqli->query($sqlDetalle)) {
                    throw new Exception($mysqli->error);
                }
                $idVentaDetalle = $mysqli->insert_id;
                $cantidadPendiente = $Cantidad;

                $sqlStockOrdenes = "SELECT 
                    OCD.id AS idOrdenCompraDetalle,
                    OCD.idOrdenCompra,
                    OCD.idProducto,
                    OCD.Cantidad AS CantidadIngresada,
                    (
                        OCD.Cantidad 
                        - IFNULL((
                            SELECT SUM(VCS.Cantidad)
                            FROM VentasConsumoStock VCS
                            INNER JOIN Ventas VCS_V 
                                ON VCS_V.id = VCS.idVenta
                            WHERE VCS.idOrdenCompraDetalle = OCD.id
                            AND VCS.Eliminado = 0
                            AND VCS_V.Eliminado = 0
                        ),0)
                    ) AS Disponible
                FROM OrdenesCompraDetalle OCD
                INNER JOIN OrdenesCompra OC 
                    ON OC.id = OCD.idOrdenCompra
                WHERE OCD.idProducto = '$idProducto'
                AND IFNULL(OCD.Eliminado,0) = 0
                AND IFNULL(OC.Eliminado,0) = 0
                HAVING Disponible > 0
                ORDER BY OC.Fecha ASC, OCD.id ASC";

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

                    $sqlConsumo = "INSERT INTO VentasConsumoStock
                        (
                            idVenta,
                            idVentaDetalle,
                            idOrdenCompra,
                            idOrdenCompraDetalle,
                            idProducto,
                            Cantidad,
                            Eliminado
                        )
                        VALUES
                        (
                            '$idVenta',
                            '$idVentaDetalle',
                            '$idOrdenCompra',
                            '$idOrdenCompraDetalle',
                            '$idProducto',
                            '$cantidadConsumir',
                            0
                        )
                    ";

                    if (!$mysqli->query($sqlConsumo)) {
                        throw new Exception($mysqli->error);
                    }

                    $cantidadPendiente -= $cantidadConsumir;
                }

                if ($cantidadPendiente > 0) {
                    throw new Exception("Stock insuficiente por orden de ingreso para el producto: " . $ProductoNombre);
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
        $usuario = isset($_SESSION['user_name']) && $_SESSION['user_name'] != ''
            ? $mysqli->real_escape_string($_SESSION['user_name'])
            : 'Sistema';

        if ($id <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Venta inválida."
            ));
            exit;
        }
        $sqlPagos = "SELECT COUNT(*) AS Total
                    FROM CobranzasVentas
                    WHERE idVenta = '$id'
                    AND Eliminado = 0
                ";

        $resPagos = $mysqli->query($sqlPagos);

        if (!$resPagos) {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
            exit;
        }

        $rowPagos = $resPagos->fetch_assoc();

        if ((int)$rowPagos['Total'] > 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "La venta tiene pagos aplicados. Primero debe desvincular el pago antes de eliminarla."
            ));
            exit;
        }

        $warningWepoint = "";

        try {

            eliminarOrdenVentaWepoint($mysqli, $id);
        } catch (Exception $e) {

            $mensajeWepoint = $e->getMessage();

            $ovYaNoExiste =

                stripos($mensajeWepoint, '404') !== false ||

                stripos($mensajeWepoint, 'not found') !== false ||

                stripos($mensajeWepoint, 'no encontrada') !== false ||

                stripos($mensajeWepoint, 'no existe') !== false ||

                stripos($mensajeWepoint, 'eliminada') !== false;

            if ($ovYaNoExiste) {

                $warningWepoint = "La OV ya no existía en Wepoint. Se eliminó únicamente la venta local.";
            } else {

                echo json_encode(array(

                    "success" => 0,

                    "error" => $mensajeWepoint

                ));

                exit;
            }
        }


        $mysqli->begin_transaction();

        try {

            $sqlDetalle = "SELECT 
                idProducto,
                ProductoNombre,
                Cantidad
            FROM VentasDetalle
            WHERE idVenta = '$id'
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
            }
            $sqlLiberarConsumo = "UPDATE VentasConsumoStock SET Eliminado = 1 WHERE idVenta = '$id'";

            if (!$mysqli->query($sqlLiberarConsumo)) {
                throw new Exception($mysqli->error);
            }

            $sqlEliminarDetalle = "UPDATE VentasDetalle SET Eliminado = 1 WHERE idVenta = '$id'";

            if (!$mysqli->query($sqlEliminarDetalle)) {
                throw new Exception($mysqli->error);
            }

            $sqlEliminarVenta = "UPDATE Ventas SET Eliminado = 1 WHERE id = '$id' LIMIT 1";

            if (!$mysqli->query($sqlEliminarVenta)) {
                throw new Exception($mysqli->error);
            }

            $mysqli->commit();

            echo json_encode(array(
                "success" => 1,
                "warning" => $warningWepoint
            ));
        } catch (Exception $e) {

            $mysqli->rollback();

            echo json_encode(array(
                "success" => 0,
                "error" => $e->getMessage()
            ));
        }

        break;

    case 'productos_venta_rapida':

        $sql = "
        SELECT 
            P.id,
            P.Nombre,
            P.PrecioVenta,

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
          AND P.MostrarEnVentaRapida = 1
        ORDER BY P.Nombre ASC
    ";

        $res = $mysqli->query($sql);

        if (!$res) {
            echo json_encode([
                "success" => 0,
                "error" => $mysqli->error
            ]);
            exit;
        }

        $data = array();

        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data);
        break;

    case 'ultimas_ventas':

        $sql = "SELECT 
        V.id,
        V.NumeroVenta,
        V.NumeroOrdenVenta,
        V.Fecha,
        V.idCliente,
        C.RazonSocial,
        C.Ncliente,
        V.Total,
        V.Observaciones,
        V.Usuario,

        GROUP_CONCAT(
            CONCAT(VD.ProductoNombre, ' x', VD.Cantidad)
            SEPARATOR '||'
        ) AS Productos,

        IFNULL((
            SELECT SUM(APV.ImporteAplicado)
            FROM CobranzasVentas APV
            WHERE APV.idVenta = V.id
              AND APV.Eliminado = 0
        ), 0) AS TotalPagado,

        (
            SELECT CONCAT(
                DATE_FORMAT(TR.FechaTurno, '%d/%m/%Y'),
                ' ',
                LEFT(TR.HoraTurno, 5)
            )
            FROM TurnosRetiro TR
            WHERE TR.idVenta = V.id
              AND TR.Eliminado = 0
            ORDER BY TR.FechaTurno DESC, TR.HoraTurno DESC
            LIMIT 1
        ) AS TurnoRetiro

    FROM Ventas V

    LEFT JOIN Clientes C 
        ON C.id = V.idCliente

    LEFT JOIN VentasDetalle VD 
        ON VD.idVenta = V.id 
        AND VD.Eliminado = 0

    WHERE V.Eliminado = 0

    GROUP BY V.id

    ORDER BY V.id DESC

    LIMIT 10";

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

            $cliente = "";

            if (!empty($row["RazonSocial"])) {

                if (!empty($row["Ncliente"])) {

                    $cliente = "[" . $row["Ncliente"] . "] " . $row["RazonSocial"];
                } else {

                    $cliente = $row["RazonSocial"];
                }
            }

            $total = (float)$row["Total"];
            $totalPagado = (float)$row["TotalPagado"];
            $saldo = $total - $totalPagado;

            $estado = "PENDIENTE";

            if ($saldo <= 0) {

                $estado = "PAGADA";
            } elseif ($totalPagado > 0) {

                $estado = "PARCIAL";
            }

            $data[] = array(
                "id"                => $row["id"],
                "NumeroVenta"       => $row["NumeroVenta"],
                "NumeroOrdenVenta"  => $row["NumeroOrdenVenta"],
                "Fecha"             => $row["Fecha"],
                "Cliente"           => $cliente,
                "Productos"         => $row["Productos"],
                "Total"             => $total,
                "TotalPagado"       => $totalPagado,
                "Saldo"             => $saldo,
                "EstadoPago"        => $estado,
                "Observaciones"     => $row["Observaciones"],
                "Usuario"           => $row["Usuario"],
                "TurnoRetiro"       => $row["TurnoRetiro"]
            );
        }

        echo json_encode(array(
            "data" => $data
        ));

        break;

    case 'listar_ventas':

        $sql = "SELECT 
        V.id,
        V.NumeroVenta,
        V.NumeroOrdenVenta,
        V.Fecha,
        V.idCliente,
        C.RazonSocial,
        C.Ncliente,
        V.Total,
        V.Observaciones,
        V.Usuario,

        GROUP_CONCAT(
            CONCAT(VD.ProductoNombre, ' x', VD.Cantidad)
            SEPARATOR '||'
        ) AS Productos,

       V.EstadoPago,
        V.TotalPagado,

        IFNULL((
            SELECT SUM(AP.importe)
            FROM Ventas_Ajustes_Pago AP
            WHERE AP.idVenta = V.id
            AND AP.eliminado = 0
        ), 0) AS Ajustes,

        (
            V.Total 
            - IFNULL(V.TotalPagado,0)
            - IFNULL((
                SELECT SUM(AP.importe)
                FROM Ventas_Ajustes_Pago AP
                WHERE AP.idVenta = V.id
                AND AP.eliminado = 0
            ), 0)
        ) AS Saldo,       
            (
            SELECT CONCAT(
                DATE_FORMAT(TR.FechaTurno, '%d/%m/%Y'),
                ' ',
                LEFT(TR.HoraTurno, 5)
            )
            FROM TurnosRetiro TR
            WHERE TR.idVenta = V.id
            AND TR.Eliminado = 0
            ORDER BY TR.FechaTurno DESC, TR.HoraTurno DESC
            LIMIT 1
        ) AS TurnoRetiro,

       (

    IFNULL((
        SELECT SUM(OCD.Cantidad)
        FROM OrdenesCompraDetalle OCD
        INNER JOIN OrdenesCompra OC 
            ON OC.id = OCD.idOrdenCompra
        WHERE OCD.idProducto = 1
          AND IFNULL(OC.Eliminado,0) = 0
          AND IFNULL(OCD.Eliminado,0) = 0
    ),0)
    -
    IFNULL((
        SELECT SUM(VD2.Cantidad)
        FROM VentasDetalle VD2
        INNER JOIN Ventas V2 
            ON V2.id = VD2.idVenta
        WHERE VD2.idProducto = 1
          AND VD2.Eliminado = 0
          AND V2.Eliminado = 0
    ),0)

) AS StockFiguritas,

     (
    IFNULL((
        SELECT SUM(OCD.Cantidad)
        FROM OrdenesCompraDetalle OCD
        INNER JOIN OrdenesCompra OC 
            ON OC.id = OCD.idOrdenCompra
        WHERE OCD.idProducto = 2
          AND IFNULL(OC.Eliminado,0) = 0
          AND IFNULL(OCD.Eliminado,0) = 0
    ),0)

    -

    IFNULL((
        SELECT SUM(VD2.Cantidad)
        FROM VentasDetalle VD2
        INNER JOIN Ventas V2 
            ON V2.id = VD2.idVenta
        WHERE VD2.idProducto = 2
          AND VD2.Eliminado = 0
          AND V2.Eliminado = 0
            ),0)
            ) AS StockAlbum
            FROM Ventas V
            LEFT JOIN Clientes C 
                ON C.id = V.idCliente
            LEFT JOIN VentasDetalle VD 
                ON VD.idVenta = V.id 
                AND VD.Eliminado = 0
            WHERE V.Eliminado = 0
            GROUP BY V.id
            ORDER BY V.NumeroVenta DESC
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

            $cliente = "";

            if (!empty($row["RazonSocial"])) {

                if (!empty($row["Ncliente"])) {

                    $cliente = "[" . $row["Ncliente"] . "] " . $row["RazonSocial"];
                } else {

                    $cliente = $row["RazonSocial"];
                }
            }

            $data[] = array(
                "id"                => $row["id"],
                "NumeroVenta"       => $row["NumeroVenta"],
                "NumeroOrdenVenta"  => $row["NumeroOrdenVenta"],
                "Fecha"             => $row["Fecha"],
                "Cliente"           => $cliente,
                "Productos"         => $row["Productos"],
                "Total"             => $row["Total"],
                "Observaciones"     => $row["Observaciones"],
                "EstadoPago"        => $row["EstadoPago"],
                "TotalPagado"       => $row["TotalPagado"],
                "Ajustes"           => $row["Ajustes"],
                "Saldo"             => $row["Saldo"],
                "Usuario"           => $row["Usuario"],
                "TurnoRetiro"       => $row["TurnoRetiro"],
                "StockFiguritas"    => $row["StockFiguritas"],
                "StockAlbum"        => $row["StockAlbum"]
            );
        }

        echo json_encode(array(
            "data" => $data
        ));

        break;
    case 'estado_venta':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;

        $sqlVenta = "SELECT 
        V.id,
        V.NumeroVenta,
        V.Fecha,
        V.Total,
        V.TotalPagado,
        C.Distribuidora,
        IFNULL((
            SELECT SUM(AP.importe)
            FROM Ventas_Ajustes_Pago AP
            WHERE AP.idVenta = V.id
              AND AP.eliminado = 0
        ), 0) AS Ajustes,

        (
            V.Total 
            - IFNULL(V.TotalPagado,0)
            - IFNULL((
                SELECT SUM(AP.importe)
                FROM Ventas_Ajustes_Pago AP
                WHERE AP.idVenta = V.id
                  AND AP.eliminado = 0
            ), 0)
        ) AS Saldo,

        V.EstadoPago,
        V.Observaciones,
        C.Ncliente,
        C.RazonSocial,
        V.NumeroOrdenVenta,
        V.caddy_id_venta,
        V.caddy_codigo_seguimiento,
        V.caddy_fecha_entrega,
        V.caddy_titulo_servicio,
        V.caddy_tarifa,
        V.caddy_created_at,

        (
            SELECT CONCAT(
                DATE_FORMAT(TR.FechaTurno, '%d/%m/%Y'),
                ' ',
                LEFT(TR.HoraTurno, 5),
                ' hs'
            )
            FROM TurnosRetiro TR
            WHERE TR.idVenta = V.id
              AND TR.Eliminado = 0
            ORDER BY TR.FechaTurno DESC, TR.HoraTurno DESC
            LIMIT 1
        ) AS TurnoRetiro

        FROM Ventas V
        LEFT JOIN Clientes C ON C.id = V.idCliente
        WHERE V.id = '$idVenta'
        AND V.Eliminado = 0
        LIMIT 1";

        $resVenta = $mysqli->query($sqlVenta);

        if (!$resVenta || $resVenta->num_rows == 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Venta inexistente."
            ));
            exit;
        }

        $venta = $resVenta->fetch_assoc();

        $sqlPagos = "SELECT 
            CV.idCobranza,
            CV.ImporteAplicado,

            CASE 
                WHEN CC.id IS NOT NULL THEN CC.Importe
                ELSE CV.ImporteAplicado
            END AS ImporteAplicadoReal,

            CV.Fecha AS FechaAplicacion,
            CB.Fecha,
            CB.Hora,
            CB.Banco,
            CB.Operacion,
            CB.Usuario_obs,
            CB.Conciliado,
            CB.Importe
        FROM CobranzasVentas CV
        LEFT JOIN Cobranza CB ON CB.id = CV.idCobranza
        LEFT JOIN Cobranza_conciliacion CC 
            ON CC.id_cobranza = CV.idCobranza
            
        WHERE CV.idVenta = '$idVenta'
        AND CV.Eliminado = 0
        ORDER BY CV.id DESC";

        $resPagos = $mysqli->query($sqlPagos);

        if (!$resPagos) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Error SQL pagos: " . $mysqli->error,
                "sql" => $sqlPagos
            ));
            exit;
        }

        $pagos = array();

        while ($row = $resPagos->fetch_assoc()) {

            $imagen = "";
            $idCobranza = isset($row['idCobranza']) ? (int)$row['idCobranza'] : 0;

            if ($idCobranza > 0) {

                $carpetaFisica = __DIR__ . "/../../../images/depositos/";
                $carpetaWeb = "images/depositos/";
                $extensiones = array("jpg", "jpeg", "png", "gif");

                foreach ($extensiones as $ext) {
                    $archivoFisico = $carpetaFisica . $idCobranza . "." . $ext;

                    if (file_exists($archivoFisico)) {
                        $imagen = $carpetaWeb . $idCobranza . "." . $ext;
                        break;
                    }
                }
            }

            $row['Imagen'] = $imagen;
            $pagos[] = $row;
        }

        if (count($pagos) == 0 && isset($venta['TotalPagado']) && (float)$venta['TotalPagado'] > 0) {
            $pagos[] = array(
                "idCobranza" => 0,
                "ImporteAplicado" => $venta['TotalPagado'],
                "FechaAplicacion" => $venta['Fecha'],
                "Fecha" => $venta['Fecha'],
                "Hora" => "",
                "Banco" => "Pago registrado",
                "Operacion" => "Sin detalle vinculado",
                "Importe" => $venta['TotalPagado'],
                "Imagen" => ""
            );
        }

        $sqlDetalle = "SELECT 
        VD.ProductoNombre,
        VD.Cantidad,
        VD.PrecioUnitario,
        VD.Subtotal
        FROM VentasDetalle VD
        WHERE VD.idVenta = '$idVenta'
        AND VD.Eliminado = 0
        ORDER BY VD.id ASC";

        $resDetalle = $mysqli->query($sqlDetalle);

        $detalle = array();

        while ($row = $resDetalle->fetch_assoc()) {
            $detalle[] = $row;
        }

        echo json_encode(array(
            "success" => 1,
            "venta" => $venta,
            "pagos" => $pagos,
            "detalle" => $detalle
        ));

        break;
    case 'guardar_orden_venta':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;
        $NumeroOrdenVenta = isset($_POST['NumeroOrdenVenta']) ? trim($_POST['NumeroOrdenVenta']) : '';

        if ($idVenta <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Venta inválida."
            ));
            exit;
        }

        if ($NumeroOrdenVenta == '') {
            echo json_encode(array(
                "success" => 0,
                "error" => "Debe ingresar el número de orden de venta."
            ));
            exit;
        }

        $NumeroOrdenVenta = $mysqli->real_escape_string($NumeroOrdenVenta);

        $sql = "
        UPDATE Ventas
        SET NumeroOrdenVenta = '$NumeroOrdenVenta'
        WHERE id = '$idVenta'
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

    case 'resumen_ventas':

        $data = array(
            'PENDIENTE' => 0,
            'PARCIAL'   => 0,
            'PAGADA'    => 0,
            'TOTAL'     => 0
        );

        $sql = "
        SELECT 
            IFNULL(EstadoPago, 'PENDIENTE') AS EstadoPago,
            COUNT(*) AS Total
        FROM Ventas
        WHERE Eliminado = 0
        GROUP BY IFNULL(EstadoPago, 'PENDIENTE')
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
            $estado = strtoupper(trim($row['EstadoPago']));

            if ($estado == '') {
                $estado = 'PENDIENTE';
            }

            $data[$estado] = (int)$row['Total'];
            $data['TOTAL'] += (int)$row['Total'];
        }

        echo json_encode($data);
        break;

    case 'turnos_por_fecha':

        $FechaTurno = isset($_POST['FechaTurno']) ? $mysqli->real_escape_string($_POST['FechaTurno']) : '';

        $sql = "
        SELECT 
            HoraTurno AS Hora,
            COUNT(*) AS Total
        FROM TurnosRetiro
        WHERE FechaTurno = '$FechaTurno'
          AND Eliminado = 0
        GROUP BY HoraTurno
        ORDER BY HoraTurno ASC
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
    case 'guardar_turno_retiro':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;
        $FechaTurno = isset($_POST['FechaTurno']) ? $mysqli->real_escape_string($_POST['FechaTurno']) : '';
        $HoraTurno = isset($_POST['HoraTurno']) ? $mysqli->real_escape_string($_POST['HoraTurno']) : '';
        $usuario = isset($_SESSION['user_name']) && $_SESSION['user_name'] != ''
            ? $mysqli->real_escape_string($_SESSION['user_name'])
            : 'Sistema';

        if ($idVenta <= 0 || $FechaTurno == '' || $HoraTurno == '') {
            echo json_encode(array(
                "success" => 0,
                "error" => "Datos incompletos para generar el turno."
            ));
            exit;
        }

        $sqlVenta = "
        SELECT 
            V.id,
            V.NumeroVenta,
            V.NumeroOrdenVenta,
            V.idCliente,
            C.RazonSocial,
            C.Celular
        FROM Ventas V
        LEFT JOIN Clientes C ON C.id = V.idCliente
        WHERE V.id = '$idVenta'
        LIMIT 1
    ";

        $resVenta = $mysqli->query($sqlVenta);

        if (!$resVenta) {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
            exit;
        }

        $venta = $resVenta->fetch_assoc();

        if (!$venta) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Venta inexistente."
            ));
            exit;
        }

        $cliente = $mysqli->real_escape_string($venta['RazonSocial']);
        $telefono = $mysqli->real_escape_string($venta['Celular']);
        $numeroVenta = (int)$venta['NumeroVenta'];
        $numeroOrdenVenta = trim($venta['NumeroOrdenVenta'] ?? '');
        $numeroOrdenVenta = $mysqli->real_escape_string($numeroOrdenVenta);

        $sqlExiste = "
        SELECT id
        FROM TurnosRetiro
        WHERE idVenta = '$idVenta'
          AND Eliminado = 0
        LIMIT 1
    ";

        $resExiste = $mysqli->query($sqlExiste);

        if ($resExiste && $resExiste->num_rows > 0) {
            $rowExiste = $resExiste->fetch_assoc();
            $idTurno = (int)$rowExiste['id'];

            $sqlTurno = "
            UPDATE TurnosRetiro
            SET 
                FechaTurno = '$FechaTurno',
                HoraTurno = '$HoraTurno',
                Usuario = '$usuario',
                FechaCarga = NOW()
            WHERE id = '$idTurno'
            LIMIT 1
        ";
        } else {
            $sqlTurno = "
            INSERT INTO TurnosRetiro
            (
                idVenta,
                NumeroVenta,
                NumeroOrdenVenta,
                idCliente,
                Cliente,
                Telefono,
                FechaTurno,
                HoraTurno,
                Usuario,
                Eliminado,
                FechaCarga
            )
            VALUES
            (
                '$idVenta',
                '$numeroVenta',
                '$numeroOrdenVenta',
                '{$venta['idCliente']}',
                '$cliente',
                '$telefono',
                '$FechaTurno',
                '$HoraTurno',
                '$usuario',
                0,
                NOW()
            )
        ";
        }

        if (!$mysqli->query($sqlTurno)) {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
            exit;
        }

        $telefonoWp = preg_replace('/\D/', '', $telefono);

        if (strlen($telefonoWp) > 0 && substr($telefonoWp, 0, 2) != '54') {
            $telefonoWp = '54' . $telefonoWp;
        }

        $telefonoWp = str_replace('549549', '549', $telefonoWp);
        $telefonoWp = str_replace('5415', '549', $telefonoWp);

        $fechaMostrar = date('d/m/Y', strtotime($FechaTurno));
        $horaMostrar = substr($HoraTurno, 0, 5);

        $fechaMostrar = date('d/m/Y', strtotime($FechaTurno));
        $horaMostrar = substr($HoraTurno, 0, 5);

        $codigoRetiro = !empty($numeroOrdenVenta) ? $numeroOrdenVenta : $numeroVenta;
        $tipoCodigo = !empty($numeroOrdenVenta) ? "Orden de Retiro" : "Venta";

        $mensajeTexto  = "Hola! Te informamos que tu pedido ya tiene turno de retiro asignado.\n\n";
        $mensajeTexto .= $tipoCodigo . ": #" . $codigoRetiro . "\n\n";
        $mensajeTexto .= "Fecha: " . $fechaMostrar . "\n";
        $mensajeTexto .= "Hora: " . $horaMostrar . " hs\n\n";
        $mensajeTexto .= "IMPORTANTE:\n";
        $mensajeTexto .= "Para retirar la mercadería deberás informar este número al momento del retiro.\n";
        $mensajeTexto .= "No compartas este código con nadie.\n\n";
        $mensajeTexto .= "¡Gracias por tu compra!\n";
        $mensajeTexto .= "Saludos.\n";
        $mensajeTexto .= "Dinter S.A.";

        $mensaje = rawurlencode($mensajeTexto);

        $whatsappUrl = "";

        if ($telefonoWp != '') {
            $whatsappUrl = "https://wa.me/" . $telefonoWp . "?text=" . $mensaje;
        }

        echo json_encode(array(
            "success" => 1,
            "whatsapp_url" => $whatsappUrl
        ));

        break;

    case 'actualizar_cantidad_producto_venta':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;
        $ProductoNombre = isset($_POST['ProductoNombre']) ? trim($_POST['ProductoNombre']) : '';
        $CantidadNueva = isset($_POST['Cantidad']) ? (float)$_POST['Cantidad'] : 0;

        if ($idVenta <= 0 || $ProductoNombre == '' || $CantidadNueva < 0) {
            echo json_encode([
                "success" => 0,
                "error" => "Datos incompletos."
            ]);
            exit;
        }

        $ProductoNombreSQL = $mysqli->real_escape_string($ProductoNombre);

        $mysqli->begin_transaction();

        try {

            $sqlVenta = "
            SELECT EstadoPago
            FROM Ventas
            WHERE id = '$idVenta'
              AND Eliminado = 0
            LIMIT 1
            FOR UPDATE
        ";

            $resVenta = $mysqli->query($sqlVenta);

            if (!$resVenta || $resVenta->num_rows == 0) {
                throw new Exception("Venta inexistente.");
            }

            $venta = $resVenta->fetch_assoc();

            if ($venta['EstadoPago'] !== 'PENDIENTE') {
                throw new Exception("Solo se pueden editar cantidades en ventas pendientes.");
            }

            $sqlDetalle = "
            SELECT id, idProducto, PrecioUnitario
            FROM VentasDetalle
            WHERE idVenta = '$idVenta'
              AND ProductoNombre = '$ProductoNombreSQL'
              AND Eliminado = 0
            LIMIT 1
            FOR UPDATE
        ";

            $resDetalle = $mysqli->query($sqlDetalle);

            if (!$resDetalle || $resDetalle->num_rows == 0) {
                throw new Exception("Producto no encontrado en la venta.");
            }

            $detalle = $resDetalle->fetch_assoc();

            $idDetalle = (int)$detalle['id'];
            $idProducto = (int)$detalle['idProducto'];
            $precioUnitario = (float)$detalle['PrecioUnitario'];
            $subtotal = $CantidadNueva * $precioUnitario;

            // Libero el consumo FIFO anterior de esta línea
            $sqlLiberarConsumo = "
            UPDATE VentasConsumoStock
            SET Eliminado = 1
            WHERE idVenta = '$idVenta'
              AND idVentaDetalle = '$idDetalle'
        ";

            if (!$mysqli->query($sqlLiberarConsumo)) {
                throw new Exception($mysqli->error);
            }

            // Actualizo la línea de detalle
            $sqlUpdateDetalle = "
            UPDATE VentasDetalle
            SET 
                Cantidad = '$CantidadNueva',
                Subtotal = '$subtotal'
            WHERE id = '$idDetalle'
            LIMIT 1
        ";

            if (!$mysqli->query($sqlUpdateDetalle)) {
                throw new Exception($mysqli->error);
            }

            // Si la cantidad nueva es mayor a 0, vuelvo a consumir FIFO
            if ($CantidadNueva > 0) {

                $cantidadPendiente = $CantidadNueva;

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
                            INNER JOIN Ventas VCS_V 
                                ON VCS_V.id = VCS.idVenta
                            WHERE VCS.idOrdenCompraDetalle = OCD.id
                              AND VCS.Eliminado = 0
                              AND VCS_V.Eliminado = 0
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
                        Eliminado
                    )
                    VALUES
                    (
                        '$idVenta',
                        '$idDetalle',
                        '$idOrdenCompra',
                        '$idOrdenCompraDetalle',
                        '$idProducto',
                        '$cantidadConsumir',
                        0
                    )
                ";

                    if (!$mysqli->query($sqlConsumo)) {
                        throw new Exception($mysqli->error);
                    }

                    $cantidadPendiente -= $cantidadConsumir;
                }

                if ($cantidadPendiente > 0) {
                    throw new Exception("Stock insuficiente por orden de ingreso para el producto: " . $ProductoNombre);
                }
            }

            // Recalculo total de la venta
            $sqlTotal = "
            SELECT IFNULL(SUM(Subtotal),0) AS Total
            FROM VentasDetalle
            WHERE idVenta = '$idVenta'
              AND Eliminado = 0
        ";

            $resTotal = $mysqli->query($sqlTotal);

            if (!$resTotal) {
                throw new Exception($mysqli->error);
            }

            $rowTotal = $resTotal->fetch_assoc();
            $totalVenta = (float)$rowTotal['Total'];

            $sqlUpdateVenta = "
            UPDATE Ventas
            SET 
                Total = '$totalVenta',
                Saldo = (
                    '$totalVenta'
                    - IFNULL(TotalPagado,0)
                    - IFNULL((
                        SELECT SUM(AP.importe)
                        FROM Ventas_Ajustes_Pago AP
                        WHERE AP.idVenta = Ventas.id
                          AND AP.eliminado = 0
                    ),0)
                ),
                EstadoPago = CASE
                    WHEN (
                        '$totalVenta'
                        - IFNULL(TotalPagado,0)
                        - IFNULL((
                            SELECT SUM(AP.importe)
                            FROM Ventas_Ajustes_Pago AP
                            WHERE AP.idVenta = Ventas.id
                              AND AP.eliminado = 0
                        ),0)
                    ) <= 0 THEN 'PAGADA'
                    WHEN IFNULL(TotalPagado,0) > 0 THEN 'PARCIAL'
                    ELSE 'PENDIENTE'
                END
            WHERE id = '$idVenta'
            LIMIT 1
        ";

            if (!$mysqli->query($sqlUpdateVenta)) {
                throw new Exception($mysqli->error);
            }

            $mysqli->commit();

            echo json_encode([
                "success" => 1
            ]);
            exit;
        } catch (Exception $e) {

            $mysqli->rollback();

            echo json_encode([
                "success" => 0,
                "error" => $e->getMessage()
            ]);
            exit;
        }

        break;

    case 'resumen_productos_ventas':

        $data = array(
            "FIGURITAS" => array(
                "stock" => 0,
                "total" => 0,
                "pendiente" => 0,
                "vendedores" => array()
            ),
            "ALBUM" => array(
                "stock" => 0,
                "total" => 0,
                "pendiente" => 0,
                "vendedores" => array()
            )
        );

        $sql = "SELECT
            CASE 
                WHEN UPPER(VD.ProductoNombre) LIKE '%ALBUM%' THEN 'ALBUM'
                WHEN UPPER(VD.ProductoNombre) LIKE '%FIGURITA%' THEN 'FIGURITAS'
                ELSE 'OTRO'
            END AS TipoProducto,
            SUM(VD.Cantidad) AS TotalAsignado,
            SUM(CASE WHEN V.EstadoPago = 'PENDIENTE' THEN VD.Cantidad ELSE 0 END) AS TotalPendiente
        FROM VentasDetalle VD
        INNER JOIN Ventas V ON V.id = VD.idVenta
        WHERE VD.Eliminado = 0
          AND V.Eliminado = 0
        GROUP BY TipoProducto
    ";

        $res = $mysqli->query($sql);

        while ($row = $res->fetch_assoc()) {
            $tipo = $row['TipoProducto'];

            if (isset($data[$tipo])) {
                $data[$tipo]["total"] = (int)$row["TotalAsignado"];
                $data[$tipo]["pendiente"] = (int)$row["TotalPendiente"];
            }
        }

        $sqlVendedores = "SELECT
            CASE 
                WHEN UPPER(VD.ProductoNombre) LIKE '%ALBUM%' THEN 'ALBUM'
                WHEN UPPER(VD.ProductoNombre) LIKE '%FIGURITA%' THEN 'FIGURITAS'
                ELSE 'OTRO'
            END AS TipoProducto,
            IFNULL(V.Usuario, 'Sin usuario') AS Usuario,
            SUM(VD.Cantidad) AS Total
        FROM VentasDetalle VD
        INNER JOIN Ventas V ON V.id = VD.idVenta
        WHERE VD.Eliminado = 0
          AND V.Eliminado = 0
        GROUP BY TipoProducto, V.Usuario
        ORDER BY TipoProducto, Total DESC
    ";

        $resVendedores = $mysqli->query($sqlVendedores);

        while ($row = $resVendedores->fetch_assoc()) {
            $tipo = $row['TipoProducto'];

            if (isset($data[$tipo])) {
                $data[$tipo]["vendedores"][] = array(
                    "Usuario" => $row["Usuario"],
                    "Total" => (int)$row["Total"]
                );
            }
        }
        $sqlStockReal = "SELECT
        P.id,
        IFNULL((
            SELECT SUM(OCD.Cantidad)
            FROM OrdenesCompraDetalle OCD
            INNER JOIN OrdenesCompra OC 
                ON OC.id = OCD.idOrdenCompra
            WHERE OCD.idProducto = P.id
              AND IFNULL(OC.Eliminado,0) = 0
              AND IFNULL(OCD.Eliminado,0) = 0
        ),0) AS TotalIngresado,

        IFNULL((
            SELECT SUM(VD.Cantidad)
            FROM VentasDetalle VD
            INNER JOIN Ventas V 
                ON V.id = VD.idVenta
            WHERE VD.idProducto = P.id
              AND VD.Eliminado = 0
              AND V.Eliminado = 0
        ),0) AS TotalVendido

            FROM Productos P
            WHERE P.Eliminado = 0
            AND P.Activo = 1
        ";

        $resStockReal = $mysqli->query($sqlStockReal);

        while ($row = $resStockReal->fetch_assoc()) {

            $stockReal = (float)$row['TotalIngresado'] - (float)$row['TotalVendido'];

            // FIGURITAS
            if ((int)$row['id'] === 1) {
                $data["FIGURITAS"]["stock"] += $stockReal;
            }

            // ALBUM
            if ((int)$row['id'] === 2) {
                $data["ALBUM"]["stock"] += $stockReal;
            }
        }

        echo json_encode($data);
        break;


    case 'validar_orden_para_turno':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;

        if ($idVenta <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "No se recibió un ID de venta válido."
            ));
            exit;
        }

        $sqlVenta = "
        SELECT 
            id,
            NumeroVenta,
            NumeroOrdenVenta,
            wepoint_id_orden_venta,
            wepoint_nro_orden_venta,
            wepoint_estado
        FROM Ventas
        WHERE id = '$idVenta'
        LIMIT 1
    ";

        $resVenta = $mysqli->query($sqlVenta);

        if (!$resVenta) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Error consultando la venta en la base local: " . $mysqli->error
            ));
            exit;
        }

        if ($resVenta->num_rows == 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "No existe una venta local con ID #" . $idVenta . "."
            ));
            exit;
        }

        $venta = $resVenta->fetch_assoc();

        if (empty($venta['NumeroOrdenVenta'])) {
            echo json_encode(array(
                "success" => 0,
                "error" => "La venta #" . $venta['NumeroVenta'] . " todavía no tiene número de Orden de Venta asignado."
            ));
            exit;
        }

        if (empty($venta['wepoint_id_orden_venta'])) {
            echo json_encode(array(
                "success" => 0,
                "error" => "La venta #" . $venta['NumeroVenta'] . " tiene OV local <b>" . $venta['NumeroOrdenVenta'] . "</b>, pero no tiene guardado el ID técnico de Wepoint."
            ));
            exit;
        }

        $token = '1383|1w3olMBz6851a6JdfbA1GH0jdF5QdUnwUtAfehSL0f00e3a5';

        $idOrdenVentaWepoint = (int)$venta['wepoint_id_orden_venta'];

        $url = "https://sistema.wepoint.ar/api/v2/egresos/productos/" . $idOrdenVentaWepoint;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);

        curl_close($curl);

        if ($curlError != '') {
            echo json_encode(array(
                "success" => 0,
                "error" => "No se pudo conectar con Wepoint: " . $curlError
            ));
            exit;
        }

        $data = json_decode($response, true);

        if ($httpCode == 401) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Wepoint rechazó la consulta por autorización. Revisar token/API Key."
            ));
            exit;
        }

        if ($httpCode == 404) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Wepoint no encontró la OV con ID técnico #" . $idOrdenVentaWepoint . "."
            ));
            exit;
        }

        if ($httpCode < 200 || $httpCode >= 300 || !$data) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Wepoint no devolvió una respuesta válida. HTTP: " . $httpCode,
                "response" => $response
            ));
            exit;
        }

        $orden = isset($data['data']) ? $data['data'] : $data;

        $estadoWepoint = '';

        if (isset($orden['estado'])) {
            $estadoWepoint = $orden['estado'];
        }

        if ($estadoWepoint == '' && isset($orden['orden_envio']['estado'])) {
            $estadoWepoint = $orden['orden_envio']['estado'];
        }

        if ($estadoWepoint == '' && isset($orden['ordenes_envio'][0]['estado'])) {
            $estadoWepoint = $orden['ordenes_envio'][0]['estado'];
        }

        if ($estadoWepoint == '') {
            echo json_encode(array(
                "success" => 0,
                "error" => "Wepoint respondió, pero no se pudo identificar el estado de la OV.",
                "response" => $data
            ));
            exit;
        }

        $estadoSql = $mysqli->real_escape_string($estadoWepoint);
        $responseSql = $mysqli->real_escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));

        $mysqli->query("
        UPDATE Ventas
        SET 
            wepoint_estado = '$estadoSql',
            wepoint_response = '$responseSql'
        WHERE id = '$idVenta'
        LIMIT 1
    ");

        if (strtolower(trim($estadoWepoint)) != 'listo para enviar') {
            echo json_encode(array(
                "success" => 0,
                "error" => "La OV <b>" . $venta['NumeroOrdenVenta'] . "</b> existe, pero todavía no está lista para generar turno.<br><br>Estado actual en Wepoint: <b>" . $estadoWepoint . "</b>",
                "estado" => $estadoWepoint
            ));
            exit;
        }

        echo json_encode(array(
            "success" => 1,
            "estado" => $estadoWepoint,
            "message" => "La OV está lista para enviar. Ya podés generar el turno."
        ));

        break;


    case 'guardar_observaciones_venta':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;
        $Observaciones = isset($_POST['Observaciones']) ? $mysqli->real_escape_string($_POST['Observaciones']) : '';

        if ($idVenta <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Venta inválida."
            ));
            exit;
        }

        $sql = "
        UPDATE Ventas
        SET Observaciones = '$Observaciones'
        WHERE id = '$idVenta'
        LIMIT 1
    ";

        if ($mysqli->query($sql)) {
            echo json_encode(array(
                "success" => 1
            ));
        } else {
            echo json_encode(array(
                "success" => 0,
                "error" => $mysqli->error
            ));
        }

        break;
    case 'guardar_ajuste_pago':

        $idVenta = isset($_POST['idVenta']) ? intval($_POST['idVenta']) : 0;
        $importe = isset($_POST['importe']) ? floatval($_POST['importe']) : 0;
        $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : 'AJUSTE_MANUAL';
        $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

        $usuario = isset($_SESSION['user_name']) && $_SESSION['user_name'] != ''
            ? $mysqli->real_escape_string($_SESSION['user_name'])
            : 'Sistema';
        $fechaHora = date('Y-m-d H:i:s');

        if ($idVenta <= 0) {
            echo json_encode(array("success" => false, "error" => "Venta inválida."));
            exit;
        }

        if ($importe <= 0) {
            echo json_encode(array("success" => false, "error" => "Importe inválido."));
            exit;
        }

        if ($observaciones == '') {
            echo json_encode(array("success" => false, "error" => "Debe ingresar observaciones."));
            exit;
        }

        $stmt = $mysqli->prepare("
        INSERT INTO Ventas_Ajustes_Pago
        (idVenta, importe, tipo, observaciones, usuario, fecha_hora)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

        $stmt->bind_param(
            "idssss",
            $idVenta,
            $importe,
            $tipo,
            $observaciones,
            $usuario,
            $fechaHora
        );

        if (!$stmt->execute()) {
            echo json_encode(array("success" => false, "error" => $stmt->error));
            exit;
        }
        $sqlUpdateVenta = "UPDATE Ventas
            SET 
                Saldo = Total 
                    - IFNULL(TotalPagado,0)
                    - IFNULL((
                            SELECT SUM(AP.importe)
                            FROM Ventas_Ajustes_Pago AP
                            WHERE AP.idVenta = Ventas.id
                            AND AP.eliminado = 0
                        ),0),
                EstadoPago = CASE
                    WHEN (
                        Total 
                        - IFNULL(TotalPagado,0)
                        - IFNULL((
                            SELECT SUM(AP.importe)
                            FROM Ventas_Ajustes_Pago AP
                            WHERE AP.idVenta = Ventas.id
                            AND AP.eliminado = 0
                        ),0)
                    ) <= 0 THEN 'PAGADA'
                    WHEN IFNULL(TotalPagado,0) > 0 THEN 'PARCIAL'
                    ELSE 'PENDIENTE'
                END
            WHERE id = '$idVenta'
            LIMIT 1
        ";

        if (!$mysqli->query($sqlUpdateVenta)) {
            echo json_encode(array("success" => false, "error" => $mysqli->error));
            exit;
        }
        echo json_encode(array("success" => true));
        exit;

    case 'datos_venta_deposito':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;

        if ($idVenta <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Venta inválida."
            ));
            exit;
        }

        $sql = "SELECT 
        V.id,
        V.NumeroVenta,
        V.idCliente,
        V.Total,

        IFNULL((
            SELECT SUM(CV.ImporteAplicado)
            FROM CobranzasVentas CV
            WHERE CV.idVenta = V.id
              AND CV.Eliminado = 0
        ),0) AS TotalPagado,

        IFNULL((
            SELECT SUM(AP.importe)
            FROM Ventas_Ajustes_Pago AP
            WHERE AP.idVenta = V.id
              AND AP.eliminado = 0
        ),0) AS Ajustes,

        (
            V.Total
            - IFNULL((
                SELECT SUM(CV.ImporteAplicado)
                FROM CobranzasVentas CV
                WHERE CV.idVenta = V.id
                  AND CV.Eliminado = 0
            ),0)
            - IFNULL((
                SELECT SUM(AP.importe)
                FROM Ventas_Ajustes_Pago AP
                WHERE AP.idVenta = V.id
                  AND AP.eliminado = 0
            ),0)
            ) AS Saldo,
            C.Ncliente,
            C.RazonSocial
            FROM Ventas V
            LEFT JOIN Clientes C ON C.id = V.idCliente
            WHERE V.id = '$idVenta'
            AND V.Eliminado = 0
            LIMIT 1
        ";

        $res = $mysqli->query($sql);

        if (!$res || $res->num_rows == 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Venta inexistente."
            ));
            exit;
        }

        echo json_encode(array(
            "success" => 1,
            "venta" => $res->fetch_assoc()
        ));

        break;

    case 'guardar_deposito_venta':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;
        $fecha = isset($_POST['fecha']) ? $mysqli->real_escape_string($_POST['fecha']) : '';
        $tipoOperacion = isset($_POST['tipoOperacion']) ? $mysqli->real_escape_string($_POST['tipoOperacion']) : '';
        $banco = isset($_POST['banco']) ? $mysqli->real_escape_string($_POST['banco']) : '';
        $operacion = isset($_POST['operacion']) ? $mysqli->real_escape_string($_POST['operacion']) : '';
        $importe = isset($_POST['importe']) ? (float)$_POST['importe'] : 0;
        $usuario_obs = isset($_POST['Usuario_obs']) ? $mysqli->real_escape_string($_POST['Usuario_obs']) : '';

        $usuario = isset($_SESSION['user_name']) && $_SESSION['user_name'] != ''
            ? $mysqli->real_escape_string($_SESSION['user_name'])
            : 'Sistema';

        $hora = date("H:i:s");

        $tipoOperacion = isset($_POST['tipoOperacion']) ? trim($_POST['tipoOperacion']) : '';
        $banco         = isset($_POST['banco']) ? trim($_POST['banco']) : '';
        $operacion     = isset($_POST['operacion']) ? trim($_POST['operacion']) : '';

        if (strtolower($tipoOperacion) === 'efectivo') {
            $banco = 'CAJA';
            $operacion = 'EFECTIVO';
        }

        if (
            !$idVenta ||
            !$fecha ||
            !$tipoOperacion ||
            $importe <= 0 ||
            (
                strtolower($tipoOperacion) !== 'efectivo' &&
                (!$banco || !$operacion)
            )
        ) {
            echo json_encode(["success" => false, "error" => "Datos incompletos."]);
            exit;
        }

        $sqlVenta = "
        SELECT 
            V.id,
            V.NumeroVenta,
            V.idCliente,
            V.Total,
            V.TotalPagado,
            V.Saldo,
            C.Ncliente,
            C.RazonSocial
        FROM Ventas V
        LEFT JOIN Clientes C ON C.id = V.idCliente
        WHERE V.id = '$idVenta'
          AND V.Eliminado = 0
        LIMIT 1
        FOR UPDATE
    ";

        $mysqli->begin_transaction();

        try {

            $resVenta = $mysqli->query($sqlVenta);

            if (!$resVenta || $resVenta->num_rows == 0) {
                throw new Exception("Venta inexistente.");
            }

            $venta = $resVenta->fetch_assoc();

            $ncliente = $mysqli->real_escape_string($venta['Ncliente']);
            $nombreCliente = $mysqli->real_escape_string($venta['RazonSocial']);

            $alertaDuplicidad = 0;

            if (strtolower($tipoOperacion) !== 'efectivo') {

                $sqlDuplicado = "SELECT 
                        C.id,
                        C.Fecha,
                        C.Banco,
                        C.Operacion,
                        C.Importe,
                        C.Usuario,
                        CV.idVenta
                    FROM Cobranza C
                    LEFT JOIN CobranzasVentas CV 
                        ON CV.idCobranza = C.id
                        AND IFNULL(CV.Eliminado,0) = 0
                    WHERE C.Banco = '$banco'
                    AND C.Operacion = '$operacion'
                    AND C.Importe = '$importe'
                    AND IFNULL(C.Eliminado,0) = 0
                    LIMIT 1
                ";

                $resDuplicado = $mysqli->query($sqlDuplicado);

                if ($resDuplicado && $resDuplicado->num_rows > 0) {

                    $dup = $resDuplicado->fetch_assoc();

                    throw new Exception(
                        "Pago posiblemente duplicado. " .
                            "Banco: " . $dup['Banco'] . " | " .
                            "Operación: " . $dup['Operacion'] . " | " .
                            "Importe: $ " . number_format((float)$dup['Importe'], 2, ',', '.') . " | " .
                            "Venta vinculada: #" . $dup['idVenta']
                    );
                }
            }

            $observacionFinal = $mysqli->real_escape_string(
                "Carga operador desde Ventas. Venta #" . $venta['NumeroVenta'] . ". "
            );

            $sqlCobranza = "
            INSERT INTO Cobranza
            (
                NombreCliente,
                NumeroCliente,
                Fecha,
                Hora,
                Banco,
                Operacion,
                Importe,
                AlertaDuplicidad,
                TipoOperacion,
                Observaciones,
                Usuario_obs,
                Usuario
            )
            VALUES
            (
                '$nombreCliente',
                '$ncliente',
                '$fecha',
                '$hora',
                '$banco',
                '$operacion',
                '$importe',
                '$alertaDuplicidad',
                '$tipoOperacion',
                '$observacionFinal',
                '$usuario_obs',
                '$usuario'
            )
        ";

            if (!$mysqli->query($sqlCobranza)) {
                throw new Exception($mysqli->error);
            }

            $idCobranza = $mysqli->insert_id;

            $_SESSION['NComprobante'] = $idCobranza;

            $sqlAplicacion = "
            INSERT INTO CobranzasVentas
            (
                idCobranza,
                idVenta,
                ImporteAplicado,
                Fecha,
                Usuario,
                Eliminado
            )
            VALUES
            (
                '$idCobranza',
                '$idVenta',
                '$importe',
                NOW(),
                '$usuario',
                0
            )
        ";

            if (!$mysqli->query($sqlAplicacion)) {
                throw new Exception($mysqli->error);
            }

            $sqlUpdateVenta = "
            UPDATE Ventas
            SET 
                TotalPagado = IFNULL(TotalPagado,0) + '$importe',
                Saldo = Total - (IFNULL(TotalPagado,0) + '$importe'),
                EstadoPago = CASE
                    WHEN Total - (IFNULL(TotalPagado,0) + '$importe') <= 0 THEN 'PAGADA'
                    WHEN (IFNULL(TotalPagado,0) + '$importe') > 0 THEN 'PARCIAL'
                    ELSE 'PENDIENTE'
                END
            WHERE id = '$idVenta'
            LIMIT 1
        ";

            if (!$mysqli->query($sqlUpdateVenta)) {
                throw new Exception($mysqli->error);
            }

            $mysqli->commit();

            echo json_encode(array(
                "success" => 1,
                "idCobranza" => $idCobranza,
                "duplicado" => $alertaDuplicidad
            ));
        } catch (Exception $e) {

            $mysqli->rollback();

            echo json_encode(array(
                "success" => 0,
                "error" => $e->getMessage()
            ));
        }

        break;


    case 'eliminar_ajuste_pago':

        $idVenta = isset($_POST['idVenta']) ? (int)$_POST['idVenta'] : 0;

        if ($idVenta <= 0) {
            echo json_encode([
                "success" => false,
                "error" => "Venta inválida."
            ]);
            exit;
        }

        $mysqli->begin_transaction();

        try {

            $sql = "
            UPDATE Ventas_Ajustes_Pago
            SET eliminado = 1
            WHERE idVenta = '$idVenta'
              AND eliminado = 0
        ";

            if (!$mysqli->query($sql)) {
                throw new Exception($mysqli->error);
            }

            $sqlUpdateVenta = "
            UPDATE Ventas
            SET 
                Saldo = Total 
                    - IFNULL(TotalPagado,0)
                    - IFNULL((
                        SELECT SUM(AP.importe)
                        FROM Ventas_Ajustes_Pago AP
                        WHERE AP.idVenta = Ventas.id
                          AND AP.eliminado = 0
                    ),0),

                EstadoPago = CASE
                    WHEN (
                        Total 
                        - IFNULL(TotalPagado,0)
                        - IFNULL((
                            SELECT SUM(AP.importe)
                            FROM Ventas_Ajustes_Pago AP
                            WHERE AP.idVenta = Ventas.id
                              AND AP.eliminado = 0
                        ),0)
                    ) <= 0 THEN 'PAGADA'

                    WHEN IFNULL(TotalPagado,0) > 0 THEN 'PARCIAL'

                    ELSE 'PENDIENTE'
                END
            WHERE id = '$idVenta'
            LIMIT 1
        ";

            if (!$mysqli->query($sqlUpdateVenta)) {
                throw new Exception($mysqli->error);
            }

            $mysqli->commit();

            echo json_encode(["success" => true]);
            exit;
        } catch (Exception $e) {

            $mysqli->rollback();

            echo json_encode([
                "success" => false,
                "error" => $e->getMessage()
            ]);
            exit;
        }

        break;

    case 'stock_por_orden_ingreso':

        $sql = "SELECT
            P.id AS idProducto,
            P.Nombre AS Producto,
            OC.id AS idOrdenCompra,
            OC.NumeroOrden,
            OCD.Cantidad AS StockIngresado,

            IFNULL((
                SELECT SUM(VCS.Cantidad)
                FROM VentasConsumoStock VCS
                INNER JOIN Ventas V 
                    ON V.id = VCS.idVenta
                WHERE VCS.idOrdenCompraDetalle = OCD.id
                  AND IFNULL(VCS.Eliminado,0) = 0
                  AND IFNULL(V.Eliminado,0) = 0
            ),0) AS StockAsignado,

            (
                OCD.Cantidad
                - IFNULL((
                    SELECT SUM(VCS.Cantidad)
                    FROM VentasConsumoStock VCS
                    INNER JOIN Ventas V 
                        ON V.id = VCS.idVenta
                    WHERE VCS.idOrdenCompraDetalle = OCD.id
                      AND IFNULL(VCS.Eliminado,0) = 0
                      AND IFNULL(V.Eliminado,0) = 0
                ),0)
            ) AS StockDisponible

        FROM OrdenesCompraDetalle OCD

        INNER JOIN OrdenesCompra OC 
            ON OC.id = OCD.idOrdenCompra

        INNER JOIN Productos P 
            ON P.id = OCD.idProducto

        WHERE IFNULL(OCD.Eliminado,0) = 0
          AND IFNULL(OC.Eliminado,0) = 0
          AND P.id IN (1,2)

        ORDER BY P.id ASC, OC.NumeroOrden ASC
    ";

        $res = $mysqli->query($sql);

        if (!$res) {

            echo json_encode([
                "success" => 0,
                "error" => $mysqli->error
            ]);

            exit;
        }

        $data = [
            "FIGURITAS" => [],
            "ALBUM" => [],
            "VENDEDORES" => [
                "FIGURITAS" => [],
                "ALBUM" => []
            ]
        ];

        while ($row = $res->fetch_assoc()) {

            $tipo = ((int)$row['idProducto'] === 1)
                ? "FIGURITAS"
                : "ALBUM";

            $data[$tipo][] = [
                "idOrdenCompra" => (int)$row["idOrdenCompra"],
                "NumeroOrden" => (int)$row["NumeroOrden"],
                "StockIngresado" => (float)$row["StockIngresado"],
                "StockAsignado" => (float)$row["StockAsignado"],
                "StockDisponible" => (float)$row["StockDisponible"]
            ];
        }

        /*
    =====================================================
    VENTAS POR USUARIO
    =====================================================
    */

        $sqlUsuarios = "
    SELECT
        VCS.idProducto,
        VCS.idOrdenCompra,
        OC.NumeroOrden,
        IFNULL(V.Usuario, 'Sin usuario') AS Usuario,
        SUM(VCS.Cantidad) AS Total
    FROM VentasConsumoStock VCS
    INNER JOIN Ventas V 
        ON V.id = VCS.idVenta
    INNER JOIN OrdenesCompra OC 
        ON OC.id = VCS.idOrdenCompra
    WHERE IFNULL(VCS.Eliminado,0) = 0
      AND IFNULL(V.Eliminado,0) = 0
      AND VCS.idProducto IN (1,2)
    GROUP BY 
        VCS.idProducto,
        VCS.idOrdenCompra,
        OC.NumeroOrden,
        V.Usuario
    ORDER BY 
        VCS.idProducto ASC,
        OC.NumeroOrden ASC,
        Total DESC
";

        $resUsuarios = $mysqli->query($sqlUsuarios);

        if (!$resUsuarios) {

            echo json_encode([
                "success" => 0,
                "error" => $mysqli->error
            ]);

            exit;
        }

        while ($u = $resUsuarios->fetch_assoc()) {

            $tipo = ((int)$u['idProducto'] === 1)
                ? "FIGURITAS"
                : "ALBUM";

            $data["VENDEDORES"][$tipo][] = [
                "idOrdenCompra" => (int)$u["idOrdenCompra"],
                "NumeroOrden"  => (int)$u["NumeroOrden"],
                "Usuario"      => $u["Usuario"],
                "Total"        => (float)$u["Total"]
            ];
        }

        echo json_encode([
            "success" => 1,
            "data" => $data
        ]);

        break;

    case 'importar_excel_preview':

        $json = isset($_POST['data']) ? $_POST['data'] : '[]';

        $rows = json_decode($json, true);

        if (!is_array($rows) || count($rows) == 0) {

            echo json_encode([
                "success" => 0,
                "error" => "No se recibieron datos."
            ]);

            exit;
        }

        $resultado = [];

        foreach ($rows as $index => $row) {

            $numeroCliente = isset($row['cliente'])
                ? normalizarNumeroCliente($row['cliente'])
                : '';

            $figuritas = isset($row['figuritas'])
                ? (int)$row['figuritas']
                : 0;

            $album = isset($row['album'])
                ? (int)$row['album']
                : 0;

            if ($numeroCliente == '') {

                $resultado[] = [
                    "fila" => $index + 1,
                    "success" => 0,
                    "error" => "Número de cliente vacío."
                ];

                continue;
            }

            $sqlCliente = "
            SELECT 
                id,
                Ncliente,
                RazonSocial
            FROM Clientes
            WHERE Ncliente = '$numeroCliente'
            LIMIT 1
        ";

            $resCliente = $mysqli->query($sqlCliente);

            if (!$resCliente) {

                $resultado[] = [
                    "fila" => $index + 1,
                    "success" => 0,
                    "error" => $mysqli->error
                ];

                continue;
            }

            if ($resCliente->num_rows == 0) {

                $resultado[] = [
                    "fila" => $index + 1,
                    "success" => 0,
                    "cliente" => $numeroCliente,
                    "error" => "Cliente inexistente."
                ];

                continue;
            }

            $cliente = $resCliente->fetch_assoc();

            $resultado[] = [
                "fila" => $index + 1,
                "success" => 1,
                "idCliente" => $cliente['id'],
                "Ncliente" => $cliente['Ncliente'],
                "RazonSocial" => $cliente['RazonSocial'],
                "figuritas" => $figuritas,
                "album" => $album
            ];
        }

        echo json_encode([
            "success" => 1,
            "data" => $resultado
        ]);

        break;

    case 'confirmar_importacion_excel':

        $json = isset($_POST['data']) ? $_POST['data'] : '[]';

        $rows = json_decode($json, true);

        if (!is_array($rows) || count($rows) == 0) {

            echo json_encode([
                "success" => 0,
                "error" => "No hay datos para importar."
            ]);

            exit;
        }

        $mysqli->begin_transaction();

        try {

            foreach ($rows as $row) {

                $idCliente = isset($row['idCliente'])
                    ? (int)$row['idCliente']
                    : 0;

                $figuritas = isset($row['figuritas'])
                    ? (int)$row['figuritas']
                    : 0;

                $album = isset($row['album'])
                    ? (int)$row['album']
                    : 0;

                if ($idCliente <= 0) {
                    continue;
                }

                $usuario = isset($_SESSION['user_name']) && $_SESSION['user_name'] != ''
                    ? $mysqli->real_escape_string($_SESSION['user_name'])
                    : 'Sistema';

                $detalle = [];

                if ($figuritas > 0) {

                    $sqlProducto = "
                    SELECT id, Nombre, PrecioVenta
                    FROM Productos
                    WHERE id = 1
                    LIMIT 1
                ";

                    $resProducto = $mysqli->query($sqlProducto);

                    $producto = $resProducto->fetch_assoc();

                    $detalle[] = [
                        "idProducto" => $producto['id'],
                        "ProductoNombre" => $producto['Nombre'],
                        "Cantidad" => $figuritas,
                        "PrecioUnitario" => $producto['PrecioVenta']
                    ];
                }

                if ($album > 0) {

                    $sqlProducto = "
                    SELECT id, Nombre, PrecioVenta
                    FROM Productos
                    WHERE id = 2
                    LIMIT 1
                ";

                    $resProducto = $mysqli->query($sqlProducto);

                    $producto = $resProducto->fetch_assoc();

                    $detalle[] = [
                        "idProducto" => $producto['id'],
                        "ProductoNombre" => $producto['Nombre'],
                        "Cantidad" => $album,
                        "PrecioUnitario" => $producto['PrecioVenta']
                    ];
                }

                $total = 0;

                foreach ($detalle as $item) {

                    $total += (
                        (float)$item['Cantidad']
                        *
                        (float)$item['PrecioUnitario']
                    );
                }

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
                    'IMPORTACION EXCEL',
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

                $mysqli->query("
                UPDATE Ventas
                SET NumeroVenta = '$idVenta'
                WHERE id = '$idVenta'
                LIMIT 1
            ");

                foreach ($detalle as $item) {

                    $idProducto = (int)$item['idProducto'];
                    $ProductoNombre = $mysqli->real_escape_string($item['ProductoNombre']);
                    $Cantidad = (float)$item['Cantidad'];
                    $PrecioUnitario = (float)$item['PrecioUnitario'];
                    $Subtotal = $Cantidad * $PrecioUnitario;

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
                        '$ProductoNombre',
                        '$Cantidad',
                        '$PrecioUnitario',
                        '$Subtotal',
                        0
                    )
                ";

                    if (!$mysqli->query($sqlDetalle)) {
                        throw new Exception($mysqli->error);
                    }
                }
            }

            $mysqli->commit();

            echo json_encode([
                "success" => 1
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

        echo json_encode(array(
            "success" => 0,
            "error" => "Acción inválida."
        ));

        break;
}
