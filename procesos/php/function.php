<?php
session_start();
include_once "../../conexion/conexioni.php";

if (isset($_POST['NComprobante'])) {

    $_SESSION['NComprobante'] = $_POST['n'];
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

if (isset($_POST['IngresarPago'])) {

    $hora = date("H:i:s");
    //BUSCO DUPLICIDAD
    $sql = $mysqli->query("SELECT * FROM Cobranza WHERE Fecha='$_POST[fecha]' AND Operacion='$_POST[noperacion]' AND
Banco='$_POST[banco]' AND Importe='$_POST[importe]'");

    $row = $sql->fetch_array(MYSQLI_ASSOC);

    if ($sql->num_rows) {
        $Alerta = 1;
    } else {
        $Alerta = 0;
    }

    if ($mysqli->query("INSERT INTO `Cobranza`(`NombreCliente`, `NumeroCliente`, `Fecha`, `Hora`, `Banco`, `Operacion`, `Importe`,`AlertaDuplicidad`,`TipoOperacion`) 
 VALUES ('" . $_POST['name'] . "','" . $_POST['ncliente'] . "','" . $_POST['fecha'] . "','" . $hora . "','" . $_POST['banco'] . "','" . $_POST['noperacion'] . "',
 '" . $_POST['importe'] . "','" . $Alerta . "','" . $_POST['tipooperacion'] . "')")) {

        $id = $mysqli->insert_id;

        echo json_encode(array('success' => 1, 'idIngreso' => $id));
    } else {

        echo json_encode(array('success' => 0));
    }
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
