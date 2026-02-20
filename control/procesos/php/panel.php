<?php
session_start();
include_once "../../../conexion/conexioni.php";

//OBSERVACIONES
if (isset($_POST['Observaciones_search'])) {
    $sql = $mysqli->query("SELECT Usuario_obs FROM Cobranza WHERE id='$_POST[id]'");
    $row = $sql->fetch_array(MYSQLI_ASSOC);

    echo json_encode(array('success' => 1, 'Dato' => $row['Usuario_obs']));
}

if (isset($_POST['Observaciones'])) {

    $mysqli->query("UPDATE Cobranza SET Usuario='$_SESSION[user_name]',Usuario_obs='$_POST[Observaciones_text]' WHERE id='$_POST[id]'");

    echo json_encode(array('success' => 1));
}
//TABLA CONCILIADOS
if (isset($_POST['Tabla_conciliados'])) {

    if (isset($_POST['Filtro'])) {

        $sql = $mysqli->query("SELECT usuarios.Usuario as User,Cobranza_conciliacion.*,Cobranza.Importe as Importe_original 
    FROM Cobranza_conciliacion INNER JOIN Cobranza ON Cobranza.id=Cobranza_conciliacion.id_cobranza 
    INNER JOIN usuarios ON Cobranza_conciliacion.Usuario=usuarios.id WHERE Exportado='' ");
    } else {

        $sql = $mysqli->query("SELECT usuarios.Usuario as User,Cobranza_conciliacion.*,Cobranza.Importe as Importe_original 
    FROM Cobranza_conciliacion INNER JOIN Cobranza ON Cobranza.id=Cobranza_conciliacion.id_cobranza
    INNER JOIN usuarios ON Cobranza_conciliacion.Usuario=usuarios.id ");
    }


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

        $rows[] = $row;
    }

    echo json_encode(array('data' => $rows));
}

if (isset($_POST['Conciliar'])) {

    $sql = "INSERT INTO `Cobranza_conciliacion`(`id_cobranza`, `NombreCliente`, `NumeroCliente`, `Fecha`, `Hora`, `Banco`, `Operacion`, `Importe`, `Usuario`, `Observaciones`,`Estado`) VALUES 
    ('{$_POST['id_cobranza']}','{$_POST['Nombre']}','{$_POST['Numero']}','{$_POST['Fecha']}','{$_POST['Hora']}','{$_POST['Banco']}','{$_POST['Operacion']}','{$_POST['Importe']}','{$_SESSION['user']}','{$_POST['Observaciones']}','Aceptado')";

    if ($mysqli->query($sql)) {

        $mysqli->query("UPDATE Cobranza SET Conciliado=1 WHERE id='$_POST[id_cobranza]'");

        echo json_encode(array('success' => 1));
    } else {

        echo json_encode(array('success' => 0));
    }
}

if (isset($_POST['Vuelve'])) {

    $sql = "UPDATE `Cobranza` SET Conciliado=0 WHERE id='$_POST[id_cobranza]'";

    if ($mysqli->query($sql)) {

        $mysqli->query("DELETE FROM `Cobranza_conciliacion` WHERE id_cobranza='$_POST[id_cobranza]'");

        echo json_encode(array('success' => 1));
    } else {

        echo json_encode(array('success' => 0));
    }
}

if (isset($_POST['Rechazar'])) {

    $sql = "INSERT INTO `Cobranza_conciliacion`(`id_cobranza`, `NombreCliente`, `NumeroCliente`, `Fecha`, `Hora`, `Banco`, `Operacion`, `Importe`, `Usuario`, `Observaciones`,`Estado`) VALUES 
    ('{$_POST['id_cobranza']}','{$_POST['Nombre']}','{$_POST['Numero']}','{$_POST['Fecha']}','{$_POST['Hora']}','{$_POST['Banco']}','{$_POST['Operacion']}','{$_POST['Importe']}','{$_SESSION['user']}','{$_POST['Observaciones']}','Rechazado')";

    if ($mysqli->query($sql)) {

        $mysqli->query("UPDATE Cobranza SET Conciliado=1 WHERE id='$_POST[id_cobranza]'");

        echo json_encode(array('success' => 1));
    } else {

        echo json_encode(array('success' => 0));
    }
}



if (isset($_POST['Conciliar_quik'])) {

    $sql = $mysqli->query("SELECT * FROM Cobranza WHERE id='$_POST[id_cobranza]'");
    $row = $sql->fetch_array(MYSQLI_ASSOC);

    $sql = "INSERT INTO `Cobranza_conciliacion`(`id_cobranza`, `NombreCliente`, `NumeroCliente`, `Fecha`, `Hora`, `Banco`, `Operacion`, `Importe`, `Usuario`, `Observaciones`) VALUES 
    ('{$_POST['id_cobranza']}','{$row['NombreCliente']}','{$row['NumeroCliente']}','{$row['Fecha']}','{$row['Hora']}','{$row['Banco']}','{$row['Operacion']}','{$row['Importe']}','{$_SESSION['user']}','{$row['Observaciones']}')";

    if ($mysqli->query($sql)) {

        $mysqli->query("UPDATE Cobranza SET Conciliado=1 WHERE id='$_POST[id_cobranza]'");

        echo json_encode(array('success' => 1));
    } else {

        echo json_encode(array('success' => 0));
    }
}

if (isset($_POST['Conciliar_quik_cancel'])) {

    $mysqli->query("UPDATE Cobranza SET Conciliado=0 WHERE id='$_POST[id_cobranza]'");
    $mysqli->query("DELETE FROM Cobranza_conciliacion WHERE id_cobranza='$_POST[id_cobranza]'");
    echo json_encode(array('success' => 1));
}

//BUSCO DATOS
if (isset($_POST['Datos'])) {

    $sql = $mysqli->query("SELECT * FROM Cobranza WHERE id=" . $_POST['id'] . " ");

    $rows = array();

    while ($row = $sql->fetch_array(MYSQLI_ASSOC)) {

        $rows[] = $row;
    }

    echo json_encode(array('data' => $rows));
}
//VERIFICAR DUPLICADOS

//BUSCO DUPLICIDAD
if (isset($_POST['Duplicados'])) {

    $sql = $mysqli->query("SELECT id FROM Cobranza WHERE Fecha='$_POST[fecha]' AND Operacion='$_POST[noperacion]' AND
Banco='$_POST[banco]' AND Importe='$_POST[importe]' AND id<>'$_POST[id_cobranza]'");

    $rows = array();

    while ($row = $sql->fetch_array(MYSQLI_ASSOC)) {

        $mysqli->query("UPDATE Cobranza SET AlertaDuplicidad=1 WHERE id='$_POST[id_cobranza]' AND AlertaDuplicidad=0");

        $mysqli->query("UPDATE Cobranza SET AlertaDuplicidad=1 WHERE id='$row[id]' AND AlertaDuplicidad=0");

        $rows[] = $row;
    }

    if ($rows) {

        echo json_encode(array('success' => 1, 'data' => $rows));
    } else {

        echo json_encode(array('success' => 0));
    }
}

//TABLA DUPLICADOS
if (isset($_POST['Duplicados_tabla'])) {

    $sql = $mysqli->query("SELECT Fecha,Operacion,Banco,Importe FROM Cobranza WHERE id='$_POST[id_cobranza]'");

    $row = $sql->fetch_array(MYSQLI_ASSOC);

    $rows = array();

    $sql_1 = $mysqli->query("SELECT * FROM Cobranza WHERE Fecha='$row[Fecha]' AND Operacion='$row[Operacion]' AND
    Banco='$row[Banco]' AND Importe='$row[Importe]' AND id<>'$_POST[id_cobranza]'");

    while ($row_1 = $sql_1->fetch_array(MYSQLI_ASSOC)) {

        $rows[] = $row_1;
    }



    // $rows=array();

    // while($row=$sql->fetch_array(MYSQLI_ASSOC)){

    // $rows[]=$row;

    // }
    echo json_encode(array('data' => $rows));
}
