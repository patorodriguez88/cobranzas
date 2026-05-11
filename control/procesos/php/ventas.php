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

            if (!empty($row["Ncliente"])) {
                $textoCliente = "[" . $row["Ncliente"] . "] " . $row["RazonSocial"];
            } else {
                $textoCliente = $row["RazonSocial"];
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

        $sql = "
        SELECT 
            id,
            Nombre,
            PrecioVenta,
            Stock,
            StockMinimo
        FROM Productos
        WHERE Eliminado = 0
          AND Activo = 1
        ORDER BY Nombre ASC
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

        $total = 0;

        foreach ($detalle as $item) {
            $cantidad = isset($item['Cantidad']) ? (float)$item['Cantidad'] : 0;
            $precio = isset($item['PrecioUnitario']) ? (float)$item['PrecioUnitario'] : 0;
            $total += ($cantidad * $precio);
        }

        $mysqli->begin_transaction();

        try {

            if ($id == 0) {
                $usuario = isset($_SESSION['Usuario']) ? $_SESSION['Usuario'] : '';
                $sqlVenta = "
                    INSERT INTO Ventas
(Fecha, idCliente, Observaciones, Total, TotalPagado, Saldo, EstadoPago, Usuario, Eliminado)
VALUES
(NOW(), '$idCliente', '$Observaciones', '$total', 0, '$total', 'PENDIENTE', '$usuario', 0)
                ";

                if (!$mysqli->query($sqlVenta)) {
                    throw new Exception($mysqli->error);
                }

                $idVenta = $mysqli->insert_id;

                $sqlNumero = "
                    UPDATE Ventas
                    SET NumeroVenta = '$idVenta'
                    WHERE id = '$idVenta'
                    LIMIT 1
                ";

                $mysqli->query($sqlNumero);
            } else {

                $sqlVenta = "
                    UPDATE Ventas SET
                        idCliente = '$idCliente',
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
                $sqlStock = "UPDATE Productos SET Stock = Stock - '$Cantidad'
                WHERE id = '$idProducto' LIMIT 1";

                if (!$mysqli->query($sqlStock)) {

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
        $usuario = isset($_SESSION['Usuario']) ? $_SESSION['Usuario'] : 'Sistema';

        if ($id <= 0) {
            echo json_encode(array(
                "success" => 0,
                "error" => "Venta inválida."
            ));
            exit;
        }
        $sqlPagos = "SELECT COUNT(*) AS Total
                    FROM AplicacionesPagosVentas
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
        $mysqli->begin_transaction();

        try {

            $sqlDetalle = "
            SELECT 
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
                $stockNuevo = $stockAnterior + $cantidad;

                $sqlUpdateStock = "
                UPDATE Productos
                SET Stock = '$stockNuevo'
                WHERE id = '$idProducto'
                LIMIT 1
            ";

                if (!$mysqli->query($sqlUpdateStock)) {
                    throw new Exception($mysqli->error);
                }

                $observacionMovimiento = $mysqli->real_escape_string(
                    "Devolución por eliminación de venta #" . $id . " - " . $productoNombre
                );

                $sqlMovimiento = "
                INSERT INTO MovimientosStock
                (
                    idProducto,
                    Tipo,
                    Cantidad,
                    StockAnterior,
                    StockNuevo,
                    Observaciones,
                    Usuario,
                    Fecha
                )
                VALUES
                (
                    '$idProducto',
                    'DEVOLUCION_VENTA_ELIMINADA',
                    '$cantidad',
                    '$stockAnterior',
                    '$stockNuevo',
                    '$observacionMovimiento',
                    '$usuario',
                    NOW()
                )
            ";

                if (!$mysqli->query($sqlMovimiento)) {
                    throw new Exception($mysqli->error);
                }
            }

            $sqlEliminarDetalle = "
            UPDATE VentasDetalle
            SET Eliminado = 1
            WHERE idVenta = '$id'
        ";

            if (!$mysqli->query($sqlEliminarDetalle)) {
                throw new Exception($mysqli->error);
            }

            $sqlEliminarVenta = "
            UPDATE Ventas 
            SET Eliminado = 1 
            WHERE id = '$id' 
            LIMIT 1
        ";

            if (!$mysqli->query($sqlEliminarVenta)) {
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

    case 'productos_venta_rapida':

        $sql = "
        SELECT 
            id,
            Nombre,
            Stock,
            PrecioVenta
        FROM Productos
        WHERE Eliminado = 0
          AND Activo = 1
          AND MostrarEnVentaRapida = 1
        ORDER BY Nombre ASC
    ";

        $res = $mysqli->query($sql);

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
            FROM AplicacionesPagosVentas APV
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
        V.Saldo,

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
                "Saldo"             => $row["Saldo"],
                "Usuario"           => $row["Usuario"],
                "TurnoRetiro"       => $row["TurnoRetiro"]
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
            V.Saldo,
            V.EstadoPago,
            V.Observaciones,
            C.RazonSocial,
            V.NumeroOrdenVenta
        FROM Ventas V
        LEFT JOIN Clientes C ON C.id = V.idCliente
        WHERE V.id = '$idVenta'
        LIMIT 1
    ";

        $resVenta = $mysqli->query($sqlVenta);
        $venta = $resVenta->fetch_assoc();

        $sqlPagos = "

    SELECT 

        APV.ImporteAplicado,

        APV.Fecha AS FechaAplicacion,

        CB.Fecha,

        CB.Hora,

        CB.Banco,

        CB.Operacion,

        CB.Importe

    FROM AplicacionesPagosVentas APV

    LEFT JOIN Cobranza CB ON CB.id = APV.idCobranza

    WHERE APV.idVenta = '$idVenta'

      AND APV.Eliminado = 0

    ORDER BY APV.id DESC

";

        $resPagos = $mysqli->query($sqlPagos);

        $pagos = array();

        while ($row = $resPagos->fetch_assoc()) {
            $pagos[] = $row;
        }

        echo json_encode(array(
            "success" => 1,
            "venta" => $venta,
            "pagos" => $pagos
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
        $usuario = isset($_SESSION['Usuario']) ? $mysqli->real_escape_string($_SESSION['Usuario']) : 'Sistema';

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
                Celular,
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
    default:

        echo json_encode(array(
            "success" => 0,
            "error" => "Acción inválida."
        ));

        break;
}
