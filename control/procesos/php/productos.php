<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

error_reporting(E_ALL);
session_start();
include_once __DIR__ . "/../../../conexion/conexioni.php";

header('Content-Type: application/json');

$accion = $_POST['accion'] ?? '';

switch ($accion) {

    case 'listar':

    $sql = "
SELECT 
    P.id,
    P.Codigo,
    P.Nombre,
    P.Categoria,
    P.PrecioCosto,
    P.PrecioVenta,
    P.StockMinimo,
    P.Descripcion,
    P.Activo,
    P.MostrarEnVentaRapida,

    (
        IFNULL((
            SELECT SUM(OCD.Cantidad)
            FROM OrdenesCompraDetalle OCD
            INNER JOIN OrdenesCompra OC 
                ON OC.id = OCD.idOrdenCompra
            WHERE OCD.idProducto = P.id
              AND IFNULL(OCD.Eliminado,0) = 0
              AND IFNULL(OC.Eliminado,0) = 0
        ),0)
        -
        IFNULL((
            SELECT SUM(VCS.Cantidad)
            FROM VentasConsumoStock VCS
            INNER JOIN Ventas V 
                ON V.id = VCS.idVenta
            WHERE VCS.idProducto = P.id
              AND IFNULL(VCS.Eliminado,0) = 0
              AND IFNULL(V.Eliminado,0) = 0
        ),0)
    ) AS StockReal

FROM Productos P
WHERE IFNULL(P.Eliminado,0) = 0
ORDER BY P.id DESC
";

    $res = $mysqli->query($sql);

    if (!$res) {
        echo json_encode([
            "success" => 0,
            "error" => $mysqli->error
        ]);
        exit;
    }

    $data = [];

    while ($row = $res->fetch_assoc()) {
        $data[] = [
    "id" => $row["id"],
    "Codigo" => $row["Codigo"],
    "Nombre" => $row["Nombre"],
    "Categoria" => $row["Categoria"],
    "PrecioCosto" => $row["PrecioCosto"],
    "PrecioVenta" => $row["PrecioVenta"],
    "StockReal" => $row["StockReal"],
    "StockMinimo" => $row["StockMinimo"],
    "Descripcion" => $row["Descripcion"],
    "Activo" => $row["Activo"],
    "MostrarEnVentaRapida" => $row["MostrarEnVentaRapida"]
];
    }

    echo json_encode([
        "success" => 1,
        "data" => $data
    ]);

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
        $MostrarEnVentaRapida = isset($_POST['MostrarEnVentaRapida']) ? (int)$_POST['MostrarEnVentaRapida'] : 0;
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
