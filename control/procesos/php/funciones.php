<?php
session_start();
include_once "../../../conexion/conexioni.php";

if ($_POST['Ingreso'] == 1) {

    $usuario = $_POST['usuario'];

    $pass = $_POST['password'];

    if ($pass <> "") {

        $sql = $mysqli->query("SELECT id FROM usuarios WHERE Usuario='$usuario' AND `PASSWORD`='$pass'");

        $row = $sql->fetch_array(MYSQLI_ASSOC);

        if ($row['id'] <> 0 && $row['id'] <> NULL) {

            $rows = array();

            $rows[] = $row;

            $_SESSION['user'] = $row['id'];
            $_SESSION['user_name'] = $row['Usuario'];
            $_SESSION['name'] = $row['Nombre'] . ' ' . $row['Apellido'];
            $_SESSION['perfil'] = $row['Distribuidora'];

            echo json_encode(array('success' => 1, 'data' => $rows));
        } else {

            echo json_encode(array('success' => 0));
        }
    } else {

        echo json_encode(array('success' => 0));
    }
}
//TOTALES

if (isset($_POST['Totales'])) {

    $sql = $mysqli->query("SELECT SUM(Conciliado)as Conciliado,COUNT(id)as Total FROM `Cobranza`");
    $row = $sql->fetch_array(MYSQLI_ASSOC);

    $sql = $mysqli->query("SELECT COUNT(id)as Total FROM `Cobranza_conciliacion` WHERE Exportado=''");
    $row_rechazados = $sql->fetch_array(MYSQLI_ASSOC);

    if ($row_rechazados['Total'] == NULL) {

        $Conciliados = 0;
    } else {

        $Conciliados = $row_rechazados['Total'];
    }

    $NoConciliados = $row['Total'] - $row['Conciliado'];
    $sql = $mysqli->query("SELECT COUNT(id)as Exportados FROM `Cobranza_exportados` WHERE Estado='Generado'");
    $row_exportados = $sql->fetch_array(MYSQLI_ASSOC);

    echo json_encode(array('conciliados' => $Conciliados, 'total' => $NoConciliados, 'total_exportados' => $row_exportados['Exportados']));
}

if (isset($_POST['Datos'])) {

    $id = $_SESSION['user'];

    $sql = $mysqli->query("SELECT * FROM usuarios WHERE id='$id'");

    if ($row = $sql->fetch_array(MYSQLI_ASSOC)) {

        $rows = array();

        $rows[] = $row;

        echo json_encode(array('success' => 1, 'data' => $rows));
    } else {

        echo json_encode(array('success' => 0));
    }
};
