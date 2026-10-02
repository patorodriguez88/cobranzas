<?php
session_start();
include_once "../../../conexion/conexioni.php";
include_once __DIR__ . "/../../../procesos/php/fecha_pago.php";
include_once __DIR__ . "/../../../procesos/php/duplicados.php";

function normalizarFecha($valor) {
    if (empty($valor)) return null;
    $formatos = ['d/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y', 'Y/m/d'];
    foreach ($formatos as $fmt) {
        $dt = DateTime::createFromFormat($fmt, trim($valor));
        if ($dt && $dt->format($fmt) === trim($valor)) {
            return $dt->format('Y-m-d');
        }
    }
    return null;
}

// Archivo del comprobante de una cobranza ('' si no tiene). Es el registrado en
// Cobranza.Comprobante; los pagos anteriores a esa columna solo tienen el archivo
// images/depositos/<id>.<ext>: si aparece en disco se registra en la columna.
function comprobanteCobranza(mysqli $mysqli, int $idCobranza, ?string $registrado): string
{
    $carpeta = __DIR__ . '/../../../images/depositos/';
    if ($registrado !== null && $registrado !== '' && is_file($carpeta . $registrado)) {
        return $registrado;
    }
    foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'] as $ext) {
        $archivo = $idCobranza . '.' . $ext;
        if (is_file($carpeta . $archivo)) {
            $st = $mysqli->prepare("UPDATE Cobranza SET Comprobante = ? WHERE id = ?");
            $st->bind_param('si', $archivo, $idCobranza);
            $st->execute();
            return $archivo;
        }
    }
    return '';
}

// Los pagos en efectivo no tienen comprobante bancario; el resto sí lo necesita.
function requiereComprobante(?string $tipoOperacion): bool
{
    return strtolower(trim((string)$tipoOperacion)) !== 'efectivo';
}

// Un pago sin comprobante no se puede tomar como válido (conciliar).
function exigirComprobante(mysqli $mysqli, int $idCobranza): void
{
    $st = $mysqli->prepare("SELECT TipoOperacion, Comprobante FROM Cobranza WHERE id = ?");
    $st->bind_param('i', $idCobranza);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row) {
        echo json_encode(['success' => 0, 'error' => 'Cobranza inexistente.']);
        exit;
    }
    if (requiereComprobante($row['TipoOperacion']) && comprobanteCobranza($mysqli, $idCobranza, $row['Comprobante']) === '') {
        echo json_encode(['success' => 0, 'error' => 'Este pago no tiene el comprobante cargado. Subí la foto del comprobante antes de conciliarlo.']);
        exit;
    }
}

$accionesQueRequierenSesion = [
    'Conciliar', 'Rechazar', 'Conciliar_quik', 'Conciliar_quik_cancel',
    'Vuelve', 'Eliminar', 'AsignarPagoVenta', 'Observaciones_Usuario', 'MarcarSinVenta', 'MarcarSinVentaLote',
    'IngresarCobranzaDirecta'
];

$accionActual = array_keys(array_filter($_POST, fn($v) => $v !== null, ARRAY_FILTER_USE_KEY));
$requiereSesion = !empty(array_intersect($accionActual, $accionesQueRequierenSesion));

if ($requiereSesion && empty($_SESSION['user_control'])) {
    echo json_encode(['session_expired' => 1]);
    exit;
}

//OBSERVACIONES
if (isset($_POST['Observaciones_search'])) {
    $sql = $mysqli->query("SELECT Usuario_obs FROM Cobranza WHERE id='$_POST[id]'");
    $row = $sql->fetch_array(MYSQLI_ASSOC);

    echo json_encode(array('success' => 1, 'Dato' => $row['Usuario_obs']));
}

if (isset($_POST['Observaciones_Usuario'])) {

    $mysqli->query("UPDATE Cobranza SET Usuario='$_SESSION[user_name]',Usuario_obs='$_POST[Observaciones_text]' WHERE id='$_POST[id]'");

    echo json_encode(array('success' => 1, 'bloque' => 'Observaciones'));
}

//MARCAR/DESMARCAR COBRANZA COMO "SIN VENTA" (no requiere vincularse a una venta)
if (isset($_POST['MarcarSinVenta'])) {

    $idCobranza = isset($_POST['id_cobranza']) ? (int)$_POST['id_cobranza'] : 0;
    $valor = isset($_POST['valor']) && (int)$_POST['valor'] === 1 ? 1 : 0;

    if ($idCobranza <= 0) {
        echo json_encode(array('success' => 0, 'error' => 'Cobranza inválida.'));
        exit;
    }

    if ($valor === 1) {

        $sqlAplicado = $mysqli->query("
            SELECT IFNULL(SUM(ImporteAplicado),0) AS TotalAplicado
            FROM CobranzasVentas
            WHERE idCobranza = '$idCobranza'
              AND IFNULL(Eliminado,0) = 0
        ");

        $totalAplicado = (float)$sqlAplicado->fetch_assoc()['TotalAplicado'];

        if ($totalAplicado > 0) {
            echo json_encode(array(
                'success' => 0,
                'error' => 'Este pago ya está vinculado a una venta, desvincúlelo primero.'
            ));
            exit;
        }
    }

    if (!$mysqli->query("UPDATE Cobranza SET SinVenta = '$valor' WHERE id = '$idCobranza' LIMIT 1")) {
        echo json_encode(array('success' => 0, 'error' => $mysqli->error));
        exit;
    }

    echo json_encode(array('success' => 1, 'SinVenta' => $valor));
    exit;
}

//MARCAR VARIOS PAGOS COMO COBRANZA DIRECTA DE UNA VEZ (los ya vinculados a una venta se saltean)
if (isset($_POST['MarcarSinVentaLote'])) {

    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($id) => $id > 0)));
    if (!$ids) {
        echo json_encode(['success' => 0, 'error' => 'No se seleccionó ningún pago.']);
        exit;
    }

    $aplicado = $mysqli->prepare("SELECT IFNULL(SUM(ImporteAplicado), 0) FROM CobranzasVentas WHERE idCobranza = ? AND IFNULL(Eliminado, 0) = 0");
    $marcar = $mysqli->prepare("UPDATE Cobranza SET SinVenta = 1 WHERE id = ? AND Conciliado = 1 LIMIT 1");
    $marcados = 0;
    $omitidos = [];

    $mysqli->begin_transaction();
    foreach ($ids as $id) {
        $aplicado->bind_param('i', $id);
        $aplicado->execute();
        if ((float)$aplicado->get_result()->fetch_row()[0] > 0) {
            $omitidos[] = $id;
            continue;
        }
        $marcar->bind_param('i', $id);
        $marcar->execute();
        $marcados += $marcar->affected_rows;
    }
    $mysqli->commit();

    echo json_encode(['success' => 1, 'marcados' => $marcados, 'omitidos' => $omitidos]);
    exit;
}

//BUSCAR CLIENTES HABILITADOS PARA INGRESAR COBRANZA DIRECTA (todos los clientes activos)
if (isset($_POST['BuscarClientesCobranzaDirecta'])) {

    $term = isset($_POST['term']) ? trim($_POST['term']) : '';
    $buscar = '%' . $mysqli->real_escape_string($term) . '%';

    $resBusqueda = $mysqli->query("
        SELECT id, RazonSocial, Cuit, Direccion, Ciudad, Celular, Ncliente, Recorrido
        FROM Clientes
        WHERE
            IFNULL(Suspendido,0) = 0
            AND (RazonSocial LIKE '$buscar' OR Cuit LIKE '$buscar' OR Celular LIKE '$buscar' OR Ncliente LIKE '$buscar')
        ORDER BY RazonSocial ASC
        LIMIT 20
    ");

    $data = [];

    while ($row = $resBusqueda->fetch_assoc()) {
        $textoCliente = !empty($row['Ncliente'])
            ? '[' . $row['Ncliente'] . '] ' . $row['RazonSocial']
            : $row['RazonSocial'];

        $data[] = [
            'id' => $row['id'],
            'text' => $textoCliente,
            'cliente' => $row
        ];
    }

    echo json_encode($data);
    exit;
}

//INGRESAR COBRANZA DIRECTA (operador carga un pago de cliente sin vincularlo a una venta)
if (isset($_POST['IngresarCobranzaDirecta'])) {

    $idCliente = isset($_POST['idCliente']) ? (int)$_POST['idCliente'] : 0;
    $fecha = isset($_POST['fecha']) ? $mysqli->real_escape_string($_POST['fecha']) : '';
    $tipoOperacion = isset($_POST['tipoOperacion']) ? trim($_POST['tipoOperacion']) : '';
    $banco = isset($_POST['banco']) ? trim($_POST['banco']) : '';
    $operacion = isset($_POST['operacion']) ? trim($_POST['operacion']) : '';
    $importe = isset($_POST['importe']) ? (float)$_POST['importe'] : 0;
    $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

    if (strtolower($tipoOperacion) === 'efectivo') {
        $banco = 'CAJA';
        $operacion = 'EFECTIVO';
    }

    if (
        $idCliente <= 0 ||
        !$fecha ||
        !$tipoOperacion ||
        $importe <= 0 ||
        (strtolower($tipoOperacion) !== 'efectivo' && (!$banco || !$operacion))
    ) {
        echo json_encode(array('success' => 0, 'error' => 'Completá cliente, fecha, tipo, banco, operación e importe.'));
        exit;
    }

    if ($errorFecha = errorFechaPago($fecha)) {
        echo json_encode(array('success' => 0, 'error' => $errorFecha));
        exit;
    }

    $banco = $mysqli->real_escape_string($banco);
    $operacion = $mysqli->real_escape_string($operacion);
    $tipoOperacion = $mysqli->real_escape_string($tipoOperacion);

    $usuario = !empty($_SESSION['user_name']) ? $mysqli->real_escape_string($_SESSION['user_name']) : 'Sistema';
    $hora = date('H:i:s');

    $resCliente = $mysqli->query("SELECT Ncliente, RazonSocial, Suspendido FROM Clientes WHERE id = '$idCliente' LIMIT 1");

    if (!$resCliente || $resCliente->num_rows == 0) {
        echo json_encode(array('success' => 0, 'error' => 'Cliente inexistente.'));
        exit;
    }

    $cliente = $resCliente->fetch_assoc();

    if (!empty($cliente['Suspendido'])) {
        echo json_encode(array('success' => 0, 'error' => 'Este cliente está suspendido y no puede cargarse una cobranza directa.'));
        exit;
    }

    $ncliente = $mysqli->real_escape_string($cliente['Ncliente']);
    $nombreCliente = $mysqli->real_escape_string($cliente['RazonSocial']);

    if (strtolower($tipoOperacion) !== 'efectivo') {
        $dups = buscarPagosDuplicados($mysqli, (string) $_POST['banco'], (string) $_POST['operacion'], $importe, (string) $cliente['Ncliente']);
        if ($dups) {
            $d = $dups[0];
            echo json_encode(array('success' => 0, 'error' => "Pago posiblemente duplicado: ya está cargado el pago #{$d['id']} del {$d['Fecha']} ({$d['NombreCliente']}) con el mismo banco, operación e importe."));
            exit;
        }
    }

    $observacionFinal = $mysqli->real_escape_string(
        'Carga operador (sin venta) desde Cobranza.' . ($observaciones !== '' ? ' ' . $observaciones : '')
    );
    $observacionesEscapadas = $mysqli->real_escape_string($observaciones);

    $sqlCobranza = "
        INSERT INTO Cobranza
        (NombreCliente, NumeroCliente, Fecha, Hora, Banco, Operacion, Importe, AlertaDuplicidad, TipoOperacion, Observaciones, Usuario_obs, Usuario, SinVenta)
        VALUES
        ('$nombreCliente', '$ncliente', '$fecha', '$hora', '$banco', '$operacion', '$importe', 0, '$tipoOperacion', '$observacionFinal', '$observacionesEscapadas', '$usuario', 1)
    ";

    if (!$mysqli->query($sqlCobranza)) {
        echo json_encode(array('success' => 0, 'error' => $mysqli->error));
        exit;
    }

    $idCobranza = $mysqli->insert_id;
    $_SESSION['NComprobante'] = $idCobranza;

    echo json_encode(array('success' => 1, 'idCobranza' => $idCobranza));
    exit;
}

//TABLA CONCILIADOS
if (isset($_POST['Tabla_conciliados'])) {

    $whereFiltro = "";

    if (isset($_POST['Filtro'])) {
        $whereFiltro = "WHERE Cobranza_conciliacion.Exportado = ''";
    }

    $sql = $mysqli->query("SELECT
            usuarios.Usuario AS User,
            Cobranza_conciliacion.*,
            Cobranza.Importe AS Importe_original,
            Cobranza.SinVenta,

            IFNULL((
                SELECT SUM(CV.ImporteAplicado)
                FROM CobranzasVentas CV
                WHERE CV.idCobranza = Cobranza_conciliacion.id_cobranza
                  AND IFNULL(CV.Eliminado,0) = 0
            ),0) AS TotalAplicado

        FROM Cobranza_conciliacion

        INNER JOIN Cobranza 
            ON Cobranza.id = Cobranza_conciliacion.id_cobranza

        INNER JOIN usuarios 
            ON Cobranza_conciliacion.Usuario = usuarios.id

        $whereFiltro
    ");

    $rows = array();

    while ($row = $sql->fetch_array(MYSQLI_ASSOC)) {
        $rows[] = $row;
    }

    echo json_encode(array('data' => $rows));
}

//TABLA NO CONCILIADOS 

if (isset($_POST['Tabla_no_conciliados'])) {

    $sql = $mysqli->query("SELECT * FROM Cobranza WHERE Conciliado=0");

    $rows = array();

    while ($row = $sql->fetch_array(MYSQLI_ASSOC)) {

        $row['Comprobante'] = comprobanteCobranza($mysqli, (int)$row['id'], $row['Comprobante']);
        $row['SinComprobante'] = requiereComprobante($row['TipoOperacion']) && $row['Comprobante'] === '' ? 1 : 0;
        $rows[] = $row;
    }

    echo json_encode(array('data' => $rows));
}

if (isset($_POST['Conciliar'])) {

    $idCobConciliar = (int)($_POST['id_cobranza'] ?? 0);
    $yaExiste = $mysqli->query("SELECT id FROM Cobranza_conciliacion WHERE id_cobranza = '$idCobConciliar' LIMIT 1");
    if ($yaExiste && $yaExiste->num_rows > 0) {
        echo json_encode(['success' => 0, 'error' => 'Este pago ya fue conciliado.']);
        exit;
    }
    exigirComprobante($mysqli, $idCobConciliar);

    $fechaConciliar = normalizarFecha($_POST['Fecha'] ?? '');
    if (!$fechaConciliar) {
        echo json_encode(['success' => 0, 'error' => 'Fecha inválida.']);
        exit;
    }

    $sql = "INSERT INTO `Cobranza_conciliacion`(`id_cobranza`, `NombreCliente`, `NumeroCliente`, `Fecha`, `Hora`, `Banco`, `Operacion`, `Importe`, `Usuario`, `Observaciones`,`Estado`) VALUES
    ('{$_POST['id_cobranza']}','{$_POST['Nombre']}','{$_POST['Numero']}','$fechaConciliar','{$_POST['Hora']}','{$_POST['Banco']}','{$_POST['Operacion']}','{$_POST['Importe']}','{$_SESSION['user_control']}','{$_POST['Observaciones']}','Aceptado')";

    if ($mysqli->query($sql)) {

        $mysqli->query("UPDATE Cobranza SET Conciliado=1 WHERE id='$_POST[id_cobranza]'");

        recalcularVentasCobranza($mysqli, $_POST['id_cobranza']);

        echo json_encode(array('success' => 1, 'bloque' => 'Conciliar'));
    } else {

        echo json_encode(array('success' => 0));
    }
}

if (isset($_POST['Vuelve'])) {

    $idCobranza = (int)$_POST['id_cobranza'];

    $mysqli->begin_transaction();

    try {

        $ventasAfectadas = [];

        $resVentas = $mysqli->query("
            SELECT DISTINCT idVenta
            FROM CobranzasVentas
            WHERE idCobranza = '$idCobranza'
              AND IFNULL(Eliminado,0) = 0
        ");

        if (!$resVentas) {
            throw new Exception($mysqli->error);
        }

        while ($v = $resVentas->fetch_assoc()) {
            $ventasAfectadas[] = (int)$v['idVenta'];
        }

        if (!$mysqli->query("UPDATE Cobranza SET Conciliado = 0 WHERE id = '$idCobranza'")) {
            throw new Exception($mysqli->error);
        }

        if (!$mysqli->query("DELETE FROM Cobranza_conciliacion WHERE id_cobranza = '$idCobranza'")) {
            throw new Exception($mysqli->error);
        }

        if (!$mysqli->query("UPDATE CobranzasVentas SET Eliminado = 1 WHERE idCobranza = '$idCobranza'")) {
            throw new Exception($mysqli->error);
        }

        foreach ($ventasAfectadas as $idVenta) {
            recalcularVentaIndividual($mysqli, $idVenta);
        }

        $mysqli->commit();

        echo json_encode([
            'success' => 1,
            'bloque' => 'Vuelve'
        ]);
    } catch (Exception $e) {

        $mysqli->rollback();

        echo json_encode([
            'success' => 0,
            'error' => $e->getMessage()
        ]);
    }

    exit;
}

if (isset($_POST['Rechazar'])) {

    $idCobranza = (int)$_POST['id_cobranza'];

    $fechaRechazar = normalizarFecha($_POST['Fecha'] ?? '');
    if (!$fechaRechazar) {
        echo json_encode(['success' => 0, 'error' => 'Fecha inválida.']);
        exit;
    }

    $mysqli->begin_transaction();

    try {

        // =========================
        // GUARDO VENTAS AFECTADAS
        // =========================

        $ventasAfectadas = [];

        $resVentas = $mysqli->query("
            SELECT DISTINCT idVenta
            FROM CobranzasVentas
            WHERE idCobranza = '$idCobranza'
              AND IFNULL(Eliminado,0) = 0
        ");

        if (!$resVentas) {
            throw new Exception($mysqli->error);
        }

        while ($v = $resVentas->fetch_assoc()) {
            $ventasAfectadas[] = (int)$v['idVenta'];
        }

        // =========================
        // INSERTO RECHAZO
        // =========================

        $sql = "
            INSERT INTO Cobranza_conciliacion
            (
                id_cobranza,
                NombreCliente,
                NumeroCliente,
                Fecha,
                Hora,
                Banco,
                Operacion,
                Importe,
                Usuario,
                Observaciones,
                Estado
            ) VALUES (
                '{$_POST['id_cobranza']}',
                '{$_POST['Nombre']}',
                '{$_POST['Numero']}',
                '$fechaRechazar',
                '{$_POST['Hora']}',
                '{$_POST['Banco']}',
                '{$_POST['Operacion']}',
                '{$_POST['Importe']}',
                '{$_SESSION['user_control']}',
                '{$_POST['Observaciones']}',
                'Rechazado'
            )
        ";

        if (!$mysqli->query($sql)) {
            throw new Exception($mysqli->error);
        }

        // =========================
        // MARCO COBRANZA CONCILIADA
        // =========================

        if (
            !$mysqli->query("
                UPDATE Cobranza
                SET Conciliado = 1
                WHERE id = '$idCobranza'
            ")
        ) {
            throw new Exception($mysqli->error);
        }

        // =========================
        // DESVINCULO VENTAS
        // =========================

        if (
            !$mysqli->query("
                UPDATE CobranzasVentas
                SET Eliminado = 1
                WHERE idCobranza = '$idCobranza'
            ")
        ) {
            throw new Exception($mysqli->error);
        }

        // =========================
        // RECALCULO VENTAS
        // =========================

        foreach ($ventasAfectadas as $idVenta) {

            recalcularVentaIndividual($mysqli, $idVenta);
        }

        $mysqli->commit();

        echo json_encode(array(
            'success' => 1,
            'bloque' => 'Rechazar'
        ));
    } catch (Exception $e) {

        $mysqli->rollback();

        echo json_encode(array(
            'success' => 0,
            'error' => $e->getMessage()
        ));
    }

    exit;
}


if (isset($_POST['Conciliar_quik'])) {

    $idCobQuik = (int)($_POST['id_cobranza'] ?? 0);
    $yaExisteQuik = $mysqli->query("SELECT id FROM Cobranza_conciliacion WHERE id_cobranza = '$idCobQuik' LIMIT 1");
    if ($yaExisteQuik && $yaExisteQuik->num_rows > 0) {
        echo json_encode(['success' => 0, 'error' => 'Este pago ya fue conciliado.']);
        exit;
    }
    exigirComprobante($mysqli, $idCobQuik);

    $sql = $mysqli->query("SELECT * FROM Cobranza WHERE id='$idCobQuik'");
    $row = $sql->fetch_array(MYSQLI_ASSOC);

    $sql = "INSERT INTO `Cobranza_conciliacion`(`id_cobranza`, `NombreCliente`, `NumeroCliente`, `Fecha`, `Hora`, `Banco`, `Operacion`, `Importe`, `Usuario`, `Observaciones`) VALUES 
    ('{$_POST['id_cobranza']}','{$row['NombreCliente']}','{$row['NumeroCliente']}','{$row['Fecha']}','{$row['Hora']}','{$row['Banco']}','{$row['Operacion']}','{$row['Importe']}','{$_SESSION['user_control']}','{$row['Observaciones']}')";

    if ($mysqli->query($sql)) {

        $mysqli->query("UPDATE Cobranza SET Conciliado=1 WHERE id='$_POST[id_cobranza]'");

        recalcularVentasCobranza($mysqli, $_POST['id_cobranza']);

        echo json_encode(array('success' => 1, 'bloque' => 'Conciliar_quik'));
    } else {

        echo json_encode(array('success' => 0));
    }
}

if (isset($_POST['Conciliar_quik_cancel'])) {

    $mysqli->query("UPDATE Cobranza SET Conciliado=0 WHERE id='$_POST[id_cobranza]'");
    $mysqli->query("DELETE FROM Cobranza_conciliacion WHERE id_cobranza='$_POST[id_cobranza]'");
    $mysqli->query("UPDATE CobranzasVentas SET Eliminado = 1 WHERE idCobranza = '$_POST[id_cobranza]'");

    recalcularVentasCobranza($mysqli, $_POST['id_cobranza']);

    echo json_encode(array('success' => 1, 'bloque' => 'Conciliar_quik_cancel'));
}

//BUSCO DATOS
if (isset($_POST['Datos'])) {

    $id = (int)$_POST['id'];

    $sql = $mysqli->query("SELECT 
            C.*,
            CASE
                WHEN CC.Importe IS NOT NULL THEN CC.Importe
                ELSE C.Importe
            END AS ImporteReal,

            CASE
                WHEN CC.Importe IS NOT NULL THEN 1
                ELSE 0
            END AS TieneConciliacion
            FROM Cobranza C
            LEFT JOIN (
            SELECT 
            CC1.id_cobranza,
            CC1.Importe,
            CC1.Estado
            FROM Cobranza_conciliacion CC1
            INNER JOIN (
            SELECT id_cobranza, MAX(id) AS UltimoId
            FROM Cobranza_conciliacion
            GROUP BY id_cobranza
            ) ULT ON ULT.UltimoId = CC1.id
            ) CC ON CC.id_cobranza = C.id

            WHERE C.id = '$id'
            LIMIT 1
    ");

    $rows = array();

    while ($row = $sql->fetch_array(MYSQLI_ASSOC)) {
        $row['Comprobante'] = comprobanteCobranza($mysqli, (int)$row['id'], $row['Comprobante']);
        $row['SinComprobante'] = requiereComprobante($row['TipoOperacion']) && $row['Comprobante'] === '' ? 1 : 0;
        $rows[] = $row;
    }

    echo json_encode(array('data' => $rows));
}
//VERIFICAR DUPLICADOS

//BUSCO DUPLICIDAD
if (isset($_POST['Duplicados'])) {

    // Antes comparaba con la fecha del formulario en formato dd/mm/aaaa contra la base (aaaa-mm-dd):
    // nunca encontraba nada. Ahora toma los datos del propio pago y usa el criterio de duplicados.php.
    $idCob = (int) ($_POST['id_cobranza'] ?? 0);
    $st = $mysqli->prepare("SELECT Banco, Operacion, Importe, NumeroCliente, TipoOperacion FROM Cobranza WHERE id = ?");
    $st->bind_param('i', $idCob);
    $st->execute();
    $pago = $st->get_result()->fetch_assoc();
    $rows = ($pago && strtolower(trim((string) $pago['TipoOperacion'])) !== 'efectivo')
        ? buscarPagosDuplicados($mysqli, (string) $pago['Banco'], (string) $pago['Operacion'], (float) $pago['Importe'], (string) $pago['NumeroCliente'], $idCob)
        : array();
    if ($rows) {
        $ids = array_merge([$idCob], array_map('intval', array_column($rows, 'id')));
        $mysqli->query("UPDATE Cobranza SET AlertaDuplicidad = 1 WHERE AlertaDuplicidad = 0 AND id IN (" . implode(',', $ids) . ")");
    }

    if ($rows) {

        echo json_encode(array('success' => 1, 'data' => $rows));
    } else {

        echo json_encode(array('success' => 0));
    }
}

//TABLA DUPLICADOS
if (isset($_POST['Duplicados_tabla'])) {

    $idCob = (int) ($_POST['id_cobranza'] ?? 0);
    $st = $mysqli->prepare("SELECT Banco, Operacion, Importe, NumeroCliente FROM Cobranza WHERE id = ?");
    $st->bind_param('i', $idCob);
    $st->execute();
    $pago = $st->get_result()->fetch_assoc();
    $rows = array();
    if ($pago) {
        $ids = array_map('intval', array_column(buscarPagosDuplicados($mysqli, (string) $pago['Banco'], (string) $pago['Operacion'], (float) $pago['Importe'], (string) $pago['NumeroCliente'], $idCob), 'id'));
        if ($ids) {
            $res = $mysqli->query("SELECT * FROM Cobranza WHERE id IN (" . implode(',', $ids) . ")");
            $rows = $res->fetch_all(MYSQLI_ASSOC);
        }
    }
    echo json_encode(array('data' => $rows));
}
//RABLA VENTAS PENDIENTES DE CLIENTE
if (isset($_POST['VentasPendientesCliente'])) {

    $numeroCliente = isset($_POST['NumeroCliente']) ? (int)$_POST['NumeroCliente'] : 0;

    $sql = "SELECT 
        V.id,
        V.NumeroVenta,
        V.Fecha,
        V.idCliente,
        V.Total,
        V.TotalPagado,
        V.Saldo,
        V.EstadoPago
    FROM Ventas V
    INNER JOIN Clientes C ON C.id = V.idCliente
    WHERE C.Ncliente = '$numeroCliente'
      AND V.Eliminado = 0
      AND V.EstadoPago <> 'PAGADA'
      AND V.Saldo > 0
      AND V.EstadoPago != 'PAGADA'
    ORDER BY V.NumeroVenta DESC
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
    exit;
}
//ASIGNAR PAGO A VENTA
if (isset($_POST['AsignarPagoVenta'])) {

    $idCobranza = isset($_POST['idCobranza']) ? (int)$_POST['idCobranza'] : 0;
    $aplicacionesJson = isset($_POST['AplicacionesVentas']) ? $_POST['AplicacionesVentas'] : '[]';
    $aplicaciones = json_decode($aplicacionesJson, true);

    $usuario = isset($_SESSION['Usuario']) ? $_SESSION['Usuario'] : '';

    if ($idCobranza <= 0) {
        echo json_encode(array(
            "success" => 0,
            "error" => "Cobranza inválida."
        ));
        exit;
    }

    if (!is_array($aplicaciones) || count($aplicaciones) == 0) {
        echo json_encode(array(
            "success" => 0,
            "error" => "No hay ventas para aplicar."
        ));
        exit;
    }

    $sqlCobranza = $mysqli->query("SELECT SinVenta FROM Cobranza WHERE id = '$idCobranza' LIMIT 1");
    $cobranzaActual = $sqlCobranza ? $sqlCobranza->fetch_assoc() : null;

    if (!$cobranzaActual) {
        echo json_encode(array(
            "success" => 0,
            "error" => "Cobranza inexistente."
        ));
        exit;
    }

    if ((int)$cobranzaActual['SinVenta'] === 1) {
        echo json_encode(array(
            "success" => 0,
            "error" => "Este pago está marcado como cobranza sin venta, desmárquelo antes de vincularlo a una venta."
        ));
        exit;
    }

    $mysqli->begin_transaction();

    try {

        foreach ($aplicaciones as $a) {

            $idVenta = isset($a['idVenta']) ? (int)$a['idVenta'] : 0;
            $importeAplicado = isset($a['ImporteAplicado']) ? (float)$a['ImporteAplicado'] : 0;

            if ($idVenta <= 0 || $importeAplicado <= 0) {
                continue;
            }

            $sqlVenta = "
                SELECT 
                    Total,
                    TotalPagado,
                    Saldo
                FROM Ventas
                WHERE id = '$idVenta'
                  AND Eliminado = 0
                LIMIT 1
                FOR UPDATE
            ";

            $resVenta = $mysqli->query($sqlVenta);

            if (!$resVenta) {
                throw new Exception($mysqli->error);
            }

            $venta = $resVenta->fetch_assoc();

            if (!$venta) {
                throw new Exception("Venta inexistente: " . $idVenta);
            }

            $saldoActual = (float)$venta['Saldo'];

            if ($importeAplicado > $saldoActual) {
                throw new Exception("El importe aplicado supera el saldo de la venta #" . $idVenta);
            }

            $nuevoPagado = (float)$venta['TotalPagado'] + $importeAplicado;
            $nuevoSaldo = $saldoActual - $importeAplicado;

            if ($nuevoSaldo <= 0.01) {
                $nuevoSaldo = 0;
                $estadoPago = "PAGADA";
            } else {
                $estadoPago = "PARCIAL";
            }

            $sqlInsert = "
                INSERT INTO CobranzasVentas
                (idCobranza, idVenta, ImporteAplicado, Usuario, Fecha)
                VALUES
                ('$idCobranza', '$idVenta', '$importeAplicado', '$usuario', NOW())
            ";

            if (!$mysqli->query($sqlInsert)) {
                throw new Exception($mysqli->error);
            }

            $sqlUpdateVenta = "
                UPDATE Ventas
                SET 
                    TotalPagado = '$nuevoPagado',
                    Saldo = '$nuevoSaldo',
                    EstadoPago = '$estadoPago'
                WHERE id = '$idVenta'
                LIMIT 1
            ";

            if (!$mysqli->query($sqlUpdateVenta)) {
                throw new Exception($mysqli->error);
            }
        }

        $mysqli->commit();

        echo json_encode(array(
            "success" => 1
        ));
        exit;
    } catch (Exception $e) {

        $mysqli->rollback();

        echo json_encode(array(
            "success" => 0,
            "error" => $e->getMessage()
        ));
        exit;
    }
}
if (isset($_POST['Eliminar'])) {

    $idCobranza = isset($_POST['id_cobranza']) ? (int)$_POST['id_cobranza'] : 0;

    if ($idCobranza <= 0) {
        echo json_encode([
            "success" => 0,
            "error" => "Cobranza inválida."
        ]);
        exit;
    }

    $mysqli->begin_transaction();

    try {

        $sqlVinculos = "
            SELECT COUNT(*) AS total
            FROM CobranzasVentas
            WHERE idCobranza = '$idCobranza'
              AND IFNULL(Eliminado,0) = 0
        ";

        $resVinculos = $mysqli->query($sqlVinculos);

        if (!$resVinculos) {
            throw new Exception($mysqli->error);
        }

        $rowVinculos = $resVinculos->fetch_assoc();

        if ((int)$rowVinculos['total'] > 0) {
            throw new Exception("Esta cobranza tiene ventas vinculadas. Primero desvinculá el pago.");
        }

        $sqlDeleteConciliacion = "
            DELETE FROM Cobranza_conciliacion
            WHERE id_cobranza = '$idCobranza'
        ";

        if (!$mysqli->query($sqlDeleteConciliacion)) {
            throw new Exception($mysqli->error);
        }

        $sqlDeleteCobranza = "
            DELETE FROM Cobranza
            WHERE id = '$idCobranza'
            LIMIT 1
        ";

        if (!$mysqli->query($sqlDeleteCobranza)) {
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
}


function recalcularVentasCobranza($mysqli, $idCobranza)
{
    $idCobranza = (int)$idCobranza;

    $resVentas = $mysqli->query("
        SELECT DISTINCT idVenta
        FROM CobranzasVentas
        WHERE idCobranza = '$idCobranza'
          AND IFNULL(Eliminado,0) = 0
    ");

    if (!$resVentas) {
        return;
    }

    while ($v = $resVentas->fetch_assoc()) {
        recalcularVentaIndividual($mysqli, (int)$v['idVenta']);
    }
}
function recalcularVentaIndividual($mysqli, $idVenta)
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
            ), 0) AS TotalPagadoReal,
            IFNULL((
                SELECT SUM(AP.importe)
                FROM Ventas_Ajustes_Pago AP
                WHERE AP.idVenta = V.id
                  AND AP.eliminado = 0
            ), 0) AS Ajustes
        FROM Ventas V
        WHERE V.id = '$idVenta'
        LIMIT 1
    ";

    $res = $mysqli->query($sql);

    if (!$res || $res->num_rows == 0) return;

    $row = $res->fetch_assoc();

    $total           = (float)$row['Total'];
    $totalPagadoReal = (float)$row['TotalPagadoReal'];
    $ajustes         = (float)$row['Ajustes'];
    $saldo           = $total - $totalPagadoReal - $ajustes;

    if ($saldo <= 0) {
        $saldo  = 0;
        $estado = 'PAGADA';
    } elseif ($totalPagadoReal > 0) {
        $estado = 'PARCIAL';
    } else {
        $estado = 'PENDIENTE';
    }

    $mysqli->query("
        UPDATE Ventas
        SET
            TotalPagado = '$totalPagadoReal',
            Saldo = '$saldo',
            EstadoPago = '$estado'
        WHERE id = '$idVenta'
        LIMIT 1
    ");
}
