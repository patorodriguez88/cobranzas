<?php
session_start();
include_once "../../conexion/conexioni.php";
include_once __DIR__ . "/fecha_pago.php";

if (isset($_POST['NComprobante'])) {

    $_SESSION['NComprobante'] = $_POST['n'];
    echo json_encode(['success' => 1]);
    exit;
}

if (isset($_POST['Ingreso'])) {

    $doc = $_POST['doc'];

    if ($doc <> "") {

        $sql = $mysqli->query("SELECT * FROM Clientes WHERE Dni='$doc'");

        if ($row = $sql->fetch_array(MYSQLI_ASSOC)) {

            // 🔴 Caso 1: Cliente suspendido
            if ((int)$row['Suspendido'] === 1) {
                echo json_encode([
                    'success' => 0,
                    'error'   => 'Cliente no habilitado para carga de comprobantes. Comuníquese con administración.'
                ]);
                exit;
            }

            // 🟡 Caso 2: Cliente sin número
            if (empty($row['Ncliente'])) {
                echo json_encode([
                    'success' => 0,
                    'error'   => 'No se encuentra el número de cliente.'
                ]);
                exit;
            }

            // 🟢 Caso 3: Cliente activo OK
            $_SESSION['ncliente_cobranza'] = $row['Ncliente'];
            $_SESSION['user_cobranza'] = $row['id'];

            echo json_encode([
                'success' => 1,
                'data'    => [$row]
            ]);
        } else {

            // ❌ No existe
            echo json_encode([
                'success' => 0,
                'error'   => 'Cliente inexistente.'
            ]);
        }
    }
}

// Alta de un pago del cliente. El comprobante es obligatorio y viaja en el mismo
// pedido: el pago y la foto se guardan juntos o no se guarda nada.
if (isset($_POST['IngresarPago'])) {
    header('Content-Type: application/json; charset=utf-8');
    date_default_timezone_set('America/Argentina/Cordoba');

    $error = function (string $mensaje, int $status = 400) {
        http_response_code($status);
        echo json_encode(['success' => 0, 'error' => $mensaje]);
        exit;
    };

    $idCliente = (int)($_SESSION['user_cobranza'] ?? 0);
    if ($idCliente <= 0) {
        $error('Tu sesión venció. Volvé a ingresar con tu D.N.I.', 401);
    }
    $st = $mysqli->prepare("SELECT RazonSocial, Ncliente, Suspendido FROM Clientes WHERE id = ?");
    $st->bind_param('i', $idCliente);
    $st->execute();
    $cliente = $st->get_result()->fetch_assoc();
    if (!$cliente || empty($cliente['Ncliente']) || (int)$cliente['Suspendido'] === 1) {
        $error('Cliente no habilitado para carga de comprobantes. Comuníquese con administración.', 403);
    }

    $fecha         = trim((string)($_POST['fecha'] ?? ''));
    $banco         = trim((string)($_POST['banco'] ?? ''));
    $operacion     = trim((string)($_POST['noperacion'] ?? ''));
    $importe       = (float)str_replace(',', '', (string)($_POST['importe'] ?? '0'));
    $tipoOperacion = trim((string)($_POST['tipooperacion'] ?? ''));

    // Fecha del depósito: entre hoy y 30 días atrás (hora de Córdoba)
    if ($errorFecha = errorFechaPago($fecha)) {
        $error($errorFecha);
    }
    if ($banco === '' || $operacion === '' || $tipoOperacion === '') {
        $error('Faltan datos del depósito.');
    }
    if ($importe <= 0) {
        $error('El importe tiene que ser mayor a cero.');
    }

    // Comprobante obligatorio
    $archivo = $_FILES['comprobante'] ?? null;
    if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error('Tenés que adjuntar la foto del comprobante.');
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $error('No se pudo recibir la foto del comprobante. Probá de nuevo.');
    }
    $extensiones = ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = strtolower((string)(new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']));
    if (!isset($extensiones[$mime])) {
        $error('El comprobante tiene que ser una foto o imagen (JPG, PNG o WebP).');
    }
    $carpeta = __DIR__ . '/../../images/depositos/';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }

    // Duplicidad: mismo depósito ya informado (queda marcado para que lo revise administración)
    $st = $mysqli->prepare("SELECT 1 FROM Cobranza WHERE Fecha = ? AND Operacion = ? AND Banco = ? AND Importe = ? LIMIT 1");
    $st->bind_param('sssd', $fecha, $operacion, $banco, $importe);
    $st->execute();
    $alerta = $st->get_result()->num_rows ? 1 : 0;

    $destino = null;
    try {
        $mysqli->begin_transaction();
        $hora = date('H:i:s');
        $usuario = 'Cliente App';
        $st = $mysqli->prepare("INSERT INTO Cobranza (NombreCliente, NumeroCliente, Fecha, Hora, Banco, Operacion, Importe, AlertaDuplicidad, TipoOperacion, Usuario,
                                                      Observaciones, Conciliado, Usuario_obs)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', 0, '')");
        $st->bind_param('sissssdiss', $cliente['RazonSocial'], $cliente['Ncliente'], $fecha, $hora, $banco, $operacion, $importe, $alerta, $tipoOperacion, $usuario);
        $st->execute();
        $id = (int)$mysqli->insert_id;

        $nombreArchivo = $id . '.' . $extensiones[$mime];
        $destino = $carpeta . $nombreArchivo;
        if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
            $destino = null;
            throw new RuntimeException('No se pudo guardar la imagen del comprobante.');
        }
        $st = $mysqli->prepare("UPDATE Cobranza SET Comprobante = ? WHERE id = ?");
        $st->bind_param('si', $nombreArchivo, $id);
        $st->execute();
        $mysqli->commit();
    } catch (Throwable $e) {
        try { $mysqli->rollback(); } catch (Throwable $e2) {}
        if ($destino && is_file($destino)) {
            unlink($destino);
        }
        error_log('cobranzas IngresarPago: ' . $e->getMessage());
        $error('No pudimos registrar tu pago, por favor volvé a intentarlo.', 500);
    }

    echo json_encode(['success' => 1, 'idIngreso' => $id]);
    exit;
}


if (isset($_POST['Datos'])) {

    $id = (int)$_SESSION['user_cobranza'];

    $sql = $mysqli->query("SELECT * FROM Clientes WHERE id='$id'");

    if ($row = $sql->fetch_array(MYSQLI_ASSOC)) {

        // 🔴 Caso 1: Cliente suspendido
        if ((int)$row['Suspendido'] === 1) {
            echo json_encode([
                'success' => 0,
                'error'   => 'Cliente no habilitado para carga de comprobantes. Comuníquese con administración.'
            ]);
            exit;
        }

        // 🟡 Caso 2: Cliente sin número
        if (empty($row['Ncliente'])) {
            echo json_encode([
                'success' => 0,
                'error'   => 'No se encuentra el número de cliente.'
            ]);
            exit;
        }

        // 🟢 Caso 3: Cliente activo OK
        $_SESSION['ncliente_cobranza'] = $row['Ncliente'];

        echo json_encode([
            'success' => 1,
            'data'    => [$row]
        ]);
    } else {

        // ❌ No existe
        echo json_encode([
            'success' => 0,
            'error'   => 'Cliente inexistente.'
        ]);
    }
}
