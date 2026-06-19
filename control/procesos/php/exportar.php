<?
session_start();
include_once "../../../conexion/conexioni.php";
date_default_timezone_set("America/Argentina/Cordoba");

//ANULAR EXPORTACION
if (isset($_POST['Anular'])) {

    $id = intval($_POST['id']);
    $filled_int = sprintf("%08d", $id);

    // Libera los registros de Cobranza_conciliacion que fueron marcados con este export
    $mysqli->query("UPDATE Cobranza_conciliacion SET Exportado='', Estado='Aceptado' WHERE Exportado='$filled_int'");

    // Marca el export como Anulado
    $r2 = $mysqli->query("UPDATE Cobranza_exportados SET Estado='Anulado' WHERE id='$id'");

    if ($r2) {
        echo json_encode(array('success' => 1));
    } else {
        echo json_encode(array('success' => 0, 'error' => $mysqli->error));
    }
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

//LIMPIAR DUPLICADOS
if (isset($_POST['limpiar_duplicados'])) {

    $mysqli->begin_transaction();

    try {
        // IDs a eliminar: todo lo que no sea el MIN(id) de su grupo
        $resDups = $mysqli->query("
            SELECT cc.id, cc.id_cobranza
            FROM Cobranza_conciliacion cc
            INNER JOIN (
                SELECT MIN(id) AS min_id, NumeroCliente, Fecha, Hora, Importe, Operacion
                FROM Cobranza_conciliacion
                WHERE IFNULL(Estado,'') != 'Rechazado'
                GROUP BY NumeroCliente, Fecha, Hora, Importe, Operacion
                HAVING COUNT(*) > 1
            ) dup
              ON  dup.NumeroCliente = cc.NumeroCliente
              AND dup.Fecha         = cc.Fecha
              AND dup.Hora          = cc.Hora
              AND dup.Importe       = cc.Importe
              AND dup.Operacion     = cc.Operacion
            WHERE cc.id > dup.min_id
              AND IFNULL(cc.Estado,'') != 'Rechazado'
        ");

        if (!$resDups) throw new Exception($mysqli->error);

        $toDelete = [];
        $cobranzasAfectadas = [];

        while ($r = $resDups->fetch_assoc()) {
            $toDelete[]           = (int)$r['id'];
            $cobranzasAfectadas[] = (int)$r['id_cobranza'];
        }

        if (empty($toDelete)) {
            $mysqli->commit();
            echo json_encode(['success' => 1, 'eliminados' => 0]);
            exit;
        }

        $ids = implode(',', $toDelete);
        if (!$mysqli->query("DELETE FROM Cobranza_conciliacion WHERE id IN ($ids)")) {
            throw new Exception($mysqli->error);
        }

        // Si alguna Cobranza quedó sin registro en Cobranza_conciliacion, vuelve a no-conciliada
        foreach (array_unique($cobranzasAfectadas) as $idCob) {
            $check = $mysqli->query("SELECT COUNT(*) AS cnt FROM Cobranza_conciliacion WHERE id_cobranza = '$idCob'");
            $cnt   = (int)$check->fetch_assoc()['cnt'];
            if ($cnt === 0) {
                $mysqli->query("UPDATE Cobranza SET Conciliado=0 WHERE id='$idCob'");
            }
        }

        $mysqli->commit();
        echo json_encode(['success' => 1, 'eliminados' => count($toDelete)]);

    } catch (Exception $e) {
        $mysqli->rollback();
        echo json_encode(['success' => 0, 'error' => $e->getMessage()]);
    }
    exit;
}

//DUPLICADOS EN COBRANZA_CONCILIACION
if (isset($_POST['duplicados_exportados'])) {

    $sql = "
        SELECT
            cc.NumeroCliente,
            cc.NombreCliente,
            cc.Fecha,
            cc.Hora,
            cc.Importe,
            cc.Operacion,
            cc.Banco,
            GROUP_CONCAT(cc.id_cobranza ORDER BY cc.id SEPARATOR ', ') AS ids_cobranza,
            GROUP_CONCAT(NULLIF(cc.Exportado, '') ORDER BY cc.id SEPARATOR ', ')  AS exportaciones,
            COUNT(*) AS veces
        FROM Cobranza_conciliacion cc
        WHERE IFNULL(cc.Estado, '') != 'Rechazado'
        GROUP BY cc.NumeroCliente, cc.Fecha, cc.Hora, cc.Importe, cc.Operacion
        HAVING COUNT(*) > 1
        ORDER BY cc.Fecha DESC, cc.NumeroCliente
    ";

    $res = $mysqli->query($sql);

    if (!$res) {
        echo json_encode(['success' => 0, 'error' => $mysqli->error]);
        exit;
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode(['success' => 1, 'data' => $rows, 'total' => count($rows)]);
    exit;
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
                $row['Operacion'],
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
