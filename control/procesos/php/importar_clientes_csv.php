<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/../../../conexion/conexioni.php';

date_default_timezone_set('America/Argentina/Cordoba');

$archivo = __DIR__ . '/../../../uploads/clientes_newrita.csv';

if (!file_exists($archivo)) {
    die('No existe el CSV');
}

$f = fopen($archivo, 'r');

if (!$f) {
    die('No se pudo abrir el CSV');
}

$totalInsertados = 0;
$totalActualizados = 0;
$totalErrores = 0;

$linea = 0;

while (($row = fgetcsv($f, 10000, ';')) !== false) {

    $linea++;

    // saltar cabecera
    if ($linea == 1) {
        continue;
    }

    $Ncliente      = trim($row[0] ?? '');
    $Nombre        = trim($row[1] ?? '');
    $RazonSocial   = trim($row[2] ?? '');
    $Direccion     = trim($row[3] ?? '');
    $Telefono1     = trim($row[4] ?? '');
    $Cuit          = trim($row[5] ?? '');
    $Telefono2     = trim($row[8] ?? '');
    $Ciudad        = trim($row[9] ?? '');
    $CodigoPostal  = trim($row[10] ?? '');
    $Recorrido     = trim($row[11] ?? '');

    if ($Ncliente == '') {
        continue;
    }

    if ($RazonSocial == '') {
        $RazonSocial = $Nombre;
    }

    $Celular = '';

    if ($Telefono2 != '') {
        $Celular = $Telefono2;
    } elseif ($Telefono1 != '') {
        $Celular = $Telefono1;
    }

    $Celular = preg_replace('/[^0-9]/', '', $Celular);
    $Cuit = preg_replace('/[^0-9]/', '', $Cuit);

    $Distribuidora = 'Dinter';

    $stmtExiste = $mysqli->prepare("
        SELECT id 
        FROM Clientes 
        WHERE Ncliente = ?
        LIMIT 1
    ");

    $stmtExiste->bind_param('s', $Ncliente);
    $stmtExiste->execute();

    $resExiste = $stmtExiste->get_result();

    if ($resExiste->num_rows > 0) {

        $cliente = $resExiste->fetch_assoc();

        $id = $cliente['id'];

        $stmt = $mysqli->prepare("
            UPDATE Clientes
            SET
                RazonSocial = ?,
                Direccion = ?,
                Ciudad = ?,
                Celular = ?,
                Cuit = ?,
                Recorrido = ?,
                Distribuidora = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            'sssssssi',
            $RazonSocial,
            $Direccion,
            $Ciudad,
            $Celular,
            $Cuit,
            $Recorrido,
            $Distribuidora,
            $id
        );

        if ($stmt->execute()) {
            $totalActualizados++;
        } else {
            $totalErrores++;
        }
    } else {

        $stmt = $mysqli->prepare("
            INSERT INTO Clientes
            (
                Ncliente,
                RazonSocial,
                Direccion,
                Ciudad,
                Celular,
                Cuit,
                Recorrido,
                Distribuidora
            )
            VALUES
            (?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            'ssssssss',
            $Ncliente,
            $RazonSocial,
            $Direccion,
            $Ciudad,
            $Celular,
            $Cuit,
            $Recorrido,
            $Distribuidora
        );

        if ($stmt->execute()) {
            $totalInsertados++;
        } else {
            $totalErrores++;
        }
    }
}

fclose($f);

echo '<h2>Importación finalizada</h2>';

echo '<b>Insertados:</b> ' . $totalInsertados . '<br>';
echo '<b>Actualizados:</b> ' . $totalActualizados . '<br>';
echo '<b>Errores:</b> ' . $totalErrores . '<br>';
