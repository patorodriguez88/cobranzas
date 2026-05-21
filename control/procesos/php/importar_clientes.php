<?php
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

function calcularNclienteInterno($distribuidora, $nclienteExcel)
{
    $distribuidora = strtoupper(trim($distribuidora));
    $nclienteExcel = (int)$nclienteExcel;

    if ($distribuidora === 'RAK') {
        return 9000000 + $nclienteExcel;
    }

    if ($distribuidora === 'MISAS') {
        return 8000000 + $nclienteExcel;
    }

    return $nclienteExcel;
}
if ($_POST['accion'] == 'preview_importacion_clientes') {

    if (!isset($_FILES['archivo'])) {

        echo json_encode([
            "success" => 0,
            "error" => "Archivo inexistente."
        ]);

        exit;
    }

    $distribuidora = trim($_POST['distribuidora']);

    $tmp = $_FILES['archivo']['tmp_name'];

    $spreadsheet = IOFactory::load($tmp);

    $sheet = $spreadsheet->getActiveSheet();

    $rows = $sheet->toArray();

    $data = [];

    foreach ($rows as $i => $row) {

        if ($i == 0) continue;

        $nclienteExcel = trim($row[0]);
        $razonSocial = trim($row[1]);
        $cuit = trim($row[2]);
        $direccion = trim($row[3]);
        $ciudad = trim($row[4]);
        $telefono = trim($row[5]);
        $celular = trim($row[6]);

        if ($nclienteExcel == '') {
            continue;
        }

        $ncliente = calcularNclienteInterno(
            $distribuidora,
            $nclienteExcel
        );

        $sqlExiste = "
            SELECT id
            FROM Clientes
            WHERE Ncliente = '$ncliente'
            LIMIT 1
        ";

        $resExiste = $mysqli->query($sqlExiste);

        $existe = 0;

        if ($resExiste && $resExiste->num_rows > 0) {
            $existe = 1;
        }

        $data[] = [

            "Ncliente" => $ncliente,
            "RazonSocial" => $razonSocial,
            "Cuit" => $cuit,
            "Direccion" => $direccion,
            "Ciudad" => $ciudad,
            "Telefono" => $telefono,
            "Celular" => $celular,
            "Distribuidora" => $distribuidora,
            "existe" => $existe
        ];
    }

    echo json_encode([
        "success" => 1,
        "data" => $data
    ]);

    exit;
}
if ($_POST['accion'] == 'importar_clientes') {

    $clientes = json_decode($_POST['clientes'], true);

    $insertados = 0;

    foreach ($clientes as $c) {

        if ($c['existe'] == 1) {
            continue;
        }

        $ncliente = (int)$c['Ncliente'];

        $razonSocial = $mysqli->real_escape_string($c['RazonSocial']);
        $cuit = $mysqli->real_escape_string($c['Cuit']);
        $direccion = $mysqli->real_escape_string($c['Direccion']);
        $ciudad = $mysqli->real_escape_string($c['Ciudad']);
        $telefono = $mysqli->real_escape_string($c['Telefono']);
        $celular = $mysqli->real_escape_string($c['Celular']);
        $distribuidora = $mysqli->real_escape_string($c['Distribuidora']);

        $sql = "
            INSERT INTO Clientes
            (
                Ncliente,
                RazonSocial,
                Cuit,
                Direccion,
                Ciudad,
                Telefono,
                Celular,
                Distribuidora
            )
            VALUES
            (
                '$ncliente',
                '$razonSocial',
                '$cuit',
                '$direccion',
                '$ciudad',
                '$telefono',
                '$celular',
                '$distribuidora'
            )
        ";

        if ($mysqli->query($sql)) {
            $insertados++;
        }
    }

    echo json_encode([
        "success" => 1,
        "insertados" => $insertados
    ]);

    exit;
}
