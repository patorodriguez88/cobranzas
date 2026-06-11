<?
session_start();
include_once "../../../conexion/conexioni.php";
date_default_timezone_set("America/Argentina/Cordoba");

//ANULAR EXPORTACION
if (isset($_POST['Anular'])) {

    $id = intval($_POST['id']);
    $filled_int = sprintf("%08d", $id);

    // Debug: qué valores de Exportado existen para este id
    $chk = $mysqli->query("SELECT DISTINCT Exportado FROM Cobranza_conciliacion WHERE id_cobranza IN (SELECT id_cobranza FROM Cobranza_conciliacion WHERE Exportado='$filled_int' OR Exportado='$id') LIMIT 10");
    $vals = [];
    if ($chk) { while ($r = $chk->fetch_assoc()) $vals[] = $r['Exportado']; }

    $cnt = $mysqli->query("SELECT COUNT(*) as c FROM Cobranza_conciliacion WHERE Exportado='$filled_int'");
    $cntRow = $cnt ? $cnt->fetch_assoc() : ['c' => -1];

    $r1 = $mysqli->query("UPDATE Cobranza_conciliacion SET Exportado='', Estado='Aceptado' WHERE Exportado='$filled_int'");
    $r2 = $mysqli->query("UPDATE Cobranza_exportados SET Estado='Anulado' WHERE id='$id'");

    echo json_encode(array(
        'success' => ($r1 && $r2) ? 1 : 0,
        'error'   => $mysqli->error,
        'debug'   => [
            'id_recibido'  => $id,
            'filled_int'   => $filled_int,
            'match_count'  => $cntRow['c'],
            'valores_exportado_encontrados' => $vals,
            'r1_affected'  => $mysqli->affected_rows,
        ]
    ));
    exit;
}

//EXPORTADO
if (isset($_POST['Exportado'])) {

    $User = $_SESSION['user_control'];

    if ($mysqli->query("UPDATE Cobranza_exportados SET Descargas=Descargas+1,Estado='Descargado',Usuario='" . $User . "' WHERE id='" . $_POST['id'] . "'")) {

        echo json_encode(array('success' => 1));
    } else {

        echo json_encode(array('success' => 0));
    }
}

//TABLA EXPORTADOS
if (isset($_POST['Tabla_exportados'])) {

    $sql = $mysqli->query("SELECT Cobranza_exportados.*,usuarios.Usuario as User FROM Cobranza_exportados LEFT JOIN usuarios ON usuarios.id=Cobranza_exportados.Usuario");

    $rows = array();

    while ($row = $sql->fetch_array(MYSQLI_ASSOC)) {

        $rows[] = $row;
    }

    echo json_encode(array('data' => $rows));
}

if (isset($_POST['Exportar_ver'])) {

    $dato = join(',', array_unique($_POST['id_cobranza']));
    $sql = $mysqli->query("SELECT SUM(Importe)as total FROM Cobranza_conciliacion WHERE id_cobranza IN($dato)");
    $row = $sql->fetch_array(MYSQLI_ASSOC);

    echo json_encode(array('success' => 1, 'total' => $row['total']));
}


if (isset($_POST['Exportar'])) {

    $Fecha = date('Ymd');
    $Hora = date('H:i:s');
    $User = $_SESSION['user_control'];

    // $name=date('dmY H:i:s');    
    // $fichero = 'exportaciones/'.$name.'.txt';
    // Abre el fichero para obtener el contenido existente
    // $actual = file_get_contents($fichero);

    //si se crea el archivo correctamente genero un nuevo registro en exportacion

    // Deduplicar IDs para evitar registros repetidos en el CSV
    $ids_unicos = array_values(array_unique($_POST['id_cobranza']));
    $dato = join(',', $ids_unicos);

    $sql = $mysqli->query("SELECT SUM(Importe)as total,COUNT(id)as registros FROM Cobranza_conciliacion WHERE id_cobranza IN($dato)");
    $row = $sql->fetch_array(MYSQLI_ASSOC);
    $Total = $row['total'];
    $Registros = count($ids_unicos);

    if ($mysqli->query("INSERT INTO `Cobranza_exportados`(`Fecha`, `Hora`,`Total`,`Registros`,`Estado`,`Usuario`) VALUES ('{$Fecha}','{$Hora}','{$Total}','{$Registros}','Generado','{$User}')") != null) {

        $name = $mysqli->insert_id;

        $filled_int = sprintf("%08d", $name);

        $fichero = 'exportaciones/' . $filled_int . '.csv';
        $actual = "";

        for ($i = 0; $i < count($ids_unicos); $i++) {

            $dato = $ids_unicos[$i];

            $sql = $mysqli->query("SELECT Fecha,Hora,NumeroCliente,Importe,Banco,Operacion
                FROM Cobranza_conciliacion WHERE id_cobranza='$dato'
                ORDER BY id DESC LIMIT 1");
            $row = $sql->fetch_array(MYSQLI_ASSOC);

            if (!$row) continue;

            $Banco = ($row['Banco'] == 'Banco Macro') ? '03' : '04';

            $campos = [
                $dato,
                $row['Fecha'],
                $row['NumeroCliente'],
                $Banco,
                '"' . str_replace('"', '""', $row['Operacion']) . '"',
                $row['Importe'],
                $row['Fecha'],
                $row['Hora'],
            ];
            $actual .= implode(",", $campos) . "\n";
        }

        // Escribe el contenido al archivo
        if (file_put_contents($fichero, $actual)) {

            for ($i = 0; $i < count($ids_unicos); $i++) {

                $mysqli->query("UPDATE Cobranza_conciliacion SET Exportado='$filled_int',Estado='Exportado' WHERE id_cobranza='" . $ids_unicos[$i] . "'");
            }

            echo json_encode(array('success' => 1, 'name' => $filled_int));
        } else {

            echo json_encode(array('success' => 0));
        }
    }
}
