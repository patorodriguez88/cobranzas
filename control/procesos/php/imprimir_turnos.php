<?php
include_once __DIR__ . "/../../../conexion/conexioni.php";
date_default_timezone_set('America/Argentina/Cordoba');

$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

$fechaSql = $mysqli->real_escape_string($fecha);

$sql = "
    SELECT 
        TR.FechaTurno,
        TR.HoraTurno,
        TR.NumeroVenta,
        TR.NumeroOrdenVenta,
        TR.Cliente,
        TR.Telefono,
        V.Total,
        GROUP_CONCAT(
            CONCAT(VD.ProductoNombre, ' x', VD.Cantidad)
            SEPARATOR '<br>'
        ) AS Productos
    FROM TurnosRetiro TR
    LEFT JOIN Ventas V ON V.id = TR.idVenta
    LEFT JOIN VentasDetalle VD 
        ON VD.idVenta = V.id 
        AND VD.Eliminado = 0
    WHERE TR.FechaTurno = '$fechaSql'
      AND TR.Eliminado = 0
    GROUP BY TR.id
    ORDER BY TR.HoraTurno ASC, TR.Cliente ASC
";

$res = $mysqli->query($sql);

$fechaMostrar = date('d/m/Y', strtotime($fecha));
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Listado de Turnos</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #222;
        }

        h2,
        h4 {
            margin: 0;
        }

        .header {
            margin-bottom: 20px;
        }

        .fecha {
            margin-top: 6px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f1f3f5;
            border: 1px solid #999;
            padding: 6px;
            text-align: left;
        }

        td {
            border: 1px solid #999;
            padding: 6px;
            vertical-align: top;
        }

        .text-center {
            text-align: center;
        }

        .firma {
            height: 45px;
        }

        .no-print {
            margin-bottom: 15px;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <div class="no-print">
        <button onclick="window.print()">Imprimir</button>
    </div>

    <div class="header">
        <h2>DINTER S.A.</h2>
        <h4>Listado de turnos de retiro</h4>
        <div class="fecha"><b>Fecha:</b> <?php echo $fechaMostrar; ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:70px;">Hora</th>
                <th>Cliente</th>
                <th style="width:100px;">Celular</th>
                <th style="width:120px;">Venta / OV</th>
                <th>Productos</th>
                <th style="width:120px;">Entrega</th>
                <th style="width:130px;">Firma cliente</th>
                <th style="width:130px;">Aclaración</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($res && $res->num_rows > 0) { ?>
                <?php while ($row = $res->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo substr($row['HoraTurno'], 0, 5); ?></td>
                        <td><?php echo htmlspecialchars($row['Cliente']); ?></td>
                        <td><?php echo htmlspecialchars($row['Telefono']); ?></td>
                        <td>
                            Venta #<?php echo htmlspecialchars($row['NumeroVenta']); ?><br>
                            <?php if (!empty($row['NumeroOrdenVenta'])) { ?>
                                OV <?php echo htmlspecialchars($row['NumeroOrdenVenta']); ?>
                            <?php } else { ?>
                                <span>Sin OV</span>
                            <?php } ?>
                        </td>
                        <td><?php echo $row['Productos']; ?></td>
                        <td class="firma"></td>
                        <td class="firma"></td>
                        <td class="firma"></td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="8" class="text-center">No hay turnos para la fecha seleccionada.</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <script>
        window.onload = function() {
            // Descomentar si querés que abra directo el cuadro de impresión
            // window.print();
        };
    </script>

</body>

</html>