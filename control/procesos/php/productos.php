<?php
ini_set('display_errors', 1);

ini_set('display_startup_errors', 1);

error_reporting(E_ALL);
include_once "../../conexion/conexioni.php";

header('Content-Type: application/json');

$accion = $_POST['accion'] ?? '';

switch ($accion) {

    case 'listar':
        $sql = "SELECT * FROM Productos WHERE Eliminado = 0 ORDER BY id DESC";
        $res = $mysqli->query($sql);

        $data = [];
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data);
        break;


    case 'guardar':

        $id = $_POST['id'];

        $Codigo = $mysqli->real_escape_string($_POST['Codigo']);
        $Nombre = $mysqli->real_escape_string($_POST['Nombre']);
        $Categoria = $mysqli->real_escape_string($_POST['Categoria']);
        $PrecioCosto = $_POST['PrecioCosto'];
        $PrecioVenta = $_POST['PrecioVenta'];
        $Stock = $_POST['Stock'];
        $Descripcion = $mysqli->real_escape_string($_POST['Descripcion']);

        if ($id == 0) {

            $sql = "INSERT INTO Productos 
            (Codigo, Nombre, Categoria, PrecioCosto, PrecioVenta, Stock, Descripcion, FechaAlta)
            VALUES 
            ('$Codigo','$Nombre','$Categoria','$PrecioCosto','$PrecioVenta','$Stock','$Descripcion',NOW())";
        } else {

            $sql = "UPDATE Productos SET
                Codigo='$Codigo',
                Nombre='$Nombre',
                Categoria='$Categoria',
                PrecioCosto='$PrecioCosto',
                PrecioVenta='$PrecioVenta',
                Stock='$Stock',
                Descripcion='$Descripcion',
                FechaModificacion=NOW()
                WHERE id='$id' LIMIT 1";
        }

        if ($mysqli->query($sql)) {
            echo json_encode(["success" => 1]);
        } else {
            echo json_encode(["success" => 0]);
        }

        break;


    case 'obtener':

        $id = $_POST['id'];
        $sql = "SELECT * FROM Productos WHERE id='$id' LIMIT 1";
        $res = $mysqli->query($sql);

        echo json_encode($res->fetch_assoc());
        break;


    case 'eliminar':

        $id = $_POST['id'];

        $sql = "UPDATE Productos SET Eliminado = 1 WHERE id='$id' LIMIT 1";

        if ($mysqli->query($sql)) {
            echo json_encode(["success" => 1]);
        } else {
            echo json_encode(["success" => 0]);
        }

        break;
}
