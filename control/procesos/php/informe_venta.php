<?php
session_start();
include_once "../../../conexion/conexioni.php";
date_default_timezone_set("America/Argentina/Cordoba");

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Venta inválida.");
}

$sqlVenta = "
    SELECT 
        V.*,
        C.RazonSocial,
        C.Ncliente,
        C.Cuit,
        C.Direccion,
        C.Ciudad,
        C.Celular
    FROM Ventas V
    LEFT JOIN Clientes C ON C.id = V.idCliente
    WHERE V.id = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sqlVenta);
$stmt->bind_param("i", $id);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();

if (!$venta) {
    die("Venta no encontrada.");
}

$sqlDetalle = "
    SELECT 
        VD.*,
        P.Nombre AS Producto
    FROM VentasDetalle VD
    LEFT JOIN Productos P ON P.id = VD.idProducto
    WHERE VD.idVenta = ?
";

$stmt = $mysqli->prepare($sqlDetalle);
$stmt->bind_param("i", $id);
$stmt->execute();
$detalle = $stmt->get_result();

$sqlPagos = "
    SELECT
        CV.id,
        CV.idCobranza,
        CV.ImporteAplicado,
        CV.Usuario,
        CV.Fecha
    FROM CobranzasVentas CV
    WHERE CV.idVenta = ?
      AND CV.Eliminado = 0
    ORDER BY CV.id ASC
";

$stmt = $mysqli->prepare($sqlPagos);
$stmt->bind_param("i", $id);
$stmt->execute();

$pagos = $stmt->get_result();


function money($n)
{
    return '$ ' . number_format((float)$n, 2, ',', '.');
}

function fecha($f)
{
    if (!$f || $f == '0000-00-00') return '';
    return date('d/m/Y', strtotime($f));
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Informe Venta #<?= $id ?></title>

    <style>
        @page {
            size: A4;
            margin: 18mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #222;
            font-size: 13px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #222;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .title {
            font-size: 22px;
            font-weight: bold;
        }

        .subtitle {
            color: #666;
            margin-top: 4px;
        }

        .box {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 15px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .label {
            font-size: 11px;
            color: #777;
            text-transform: uppercase;
        }

        .value {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th {
            background: #f2f2f2;
            text-align: left;
            border: 1px solid #ccc;
            padding: 8px;
        }

        td {
            border: 1px solid #ccc;
            padding: 8px;
        }

        .text-end {
            text-align: right;
        }

        .totales {
            width: 45%;
            margin-left: auto;
            margin-top: 20px;
        }

        .totales td {
            font-size: 14px;
        }

        .total-final {
            font-size: 18px;
            font-weight: bold;
        }

        .print-btn {
            position: fixed;
            top: 15px;
            right: 15px;
            padding: 10px 18px;
            border: none;
            background: #222;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        @media print {
            .print-btn {
                display: none;
            }
        }
    </style>
</head>

<body>

    <button class="print-btn" onclick="window.print()">Imprimir</button>

    <div class="header">
        <div>
            <div class="title">Informe de Venta</div>
            <div class="subtitle">Venta Nº <?= htmlspecialchars($venta['NumeroVenta'] ?? $id) ?></div>
        </div>

        <div class="text-end">
            <strong>DINTER S.A.</strong><br>
            Fecha: <?= fecha($venta['Fecha'] ?? date('Y-m-d')) ?><br>
            Hora: <?= htmlspecialchars($venta['Hora'] ?? '') ?>
        </div>
    </div>

    <div class="grid">
        <div class="box">
            <div class="label">Cliente</div>
            <div class="value">
                <?= htmlspecialchars($venta['RazonSocial'] ?? 'Sin cliente') ?>
            </div>

            <div class="label">Nº Cliente</div>
            <div class="value"><?= htmlspecialchars($venta['Ncliente'] ?? '') ?></div>

            <div class="label">CUIT / DNI</div>
            <div class="value"><?= htmlspecialchars($venta['Cuit'] ?? '') ?></div>
        </div>

        <div class="box">
            <div class="label">Dirección</div>
            <div class="value"><?= htmlspecialchars($venta['Direccion'] ?? '') ?></div>

            <div class="label">Ciudad</div>
            <div class="value"><?= htmlspecialchars($venta['Ciudad'] ?? '') ?></div>

            <div class="label">Teléfono</div>
            <div class="value"><?= htmlspecialchars($venta['Celular'] ?? '') ?></div>
        </div>
    </div>

    <div class="box">
        <div class="label">Estado de pago</div>
        <div class="value"><?= htmlspecialchars($venta['EstadoPago'] ?? '') ?></div>

        <div class="label">Usuario</div>
        <div class="value"><?= htmlspecialchars($venta['Usuario'] ?? '') ?></div>

        <?php if (!empty($venta['Observaciones'])) { ?>
            <div class="label">Observaciones</div>
            <div class="value"><?= nl2br(htmlspecialchars($venta['Observaciones'])) ?></div>
        <?php } ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="text-end">Cantidad</th>
                <th class="text-end">Precio Unit.</th>
                <th class="text-end">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $detalle->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['Producto'] ?? '') ?></td>
                    <td class="text-end"><?= number_format((float)$row['Cantidad'], 0, ',', '.') ?></td>
                    <td class="text-end"><?= money($row['PrecioUnitario'] ?? 0) ?></td>
                    <td class="text-end"><?= money($row['Subtotal'] ?? 0) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <table class="totales">
        <tr>
            <td>Total</td>
            <td class="text-end"><?= money($venta['Total'] ?? 0) ?></td>
        </tr>
        <tr>
            <td>Pagado</td>
            <td class="text-end"><?= money($venta['TotalPagado'] ?? 0) ?></td>
        </tr>
        <tr>
            <td>Saldo</td>
            <td class="text-end total-final"><?= money($venta['Saldo'] ?? 0) ?></td>
        </tr>
    </table>
    <h3 style="margin-top:30px;">Pagos / Depósitos</h3>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo / Banco</th>
                <th>Operación</th>
                <th>Observaciones</th>
                <th class="text-end">Importe</th>
                <th>Conciliado</th>
            </tr>
        </thead>

        <tbody>

            <?php
            if ($pagos->num_rows == 0) {
            ?>

                <tr>
                    <td colspan="6" style="text-align:center;">
                        Sin pagos registrados
                    </td>
                </tr>

                <?php
            } else {

                while ($p = $pagos->fetch_assoc()) {

                    $conciliado = intval($p['Conciliado']) === 1
                        ? 'SI'
                        : 'NO';

                    $tieneComprobante = !empty($p['Imagen']);
                ?>

                    <tr>

                        <td style="width:110px;">
                            <?= fecha($p['FechaAplicacion']) ?><br>

                            <small>
                                <?= !empty($p['FechaAplicacion'])
                                    ? date('H:i', strtotime($p['FechaAplicacion']))
                                    : '' ?>
                            </small>
                        </td>

                        <td style="width:170px;">
                            <strong>
                                <?= htmlspecialchars($p['Banco'] ?? '-') ?>
                            </strong>

                            <?php if ($tieneComprobante) { ?>
                                <br>
                                <small style="color:green;">
                                    📎 Comprobante adjunto
                                </small>
                            <?php } ?>
                        </td>

                        <td style="width:180px;">
                            <?= htmlspecialchars($p['Operacion'] ?? '-') ?>

                            <br>

                            <small style="color:#666;">
                                Cobranza #<?= intval($p['idCobranza']) ?>
                            </small>
                        </td>

                        <td>
                            <?= nl2br(htmlspecialchars($p['Usuario_obs'] ?? '-')) ?>

                            <br>

                            <small style="color:#666;">
                                Usuario: <?= htmlspecialchars($p['Usuario'] ?? '-') ?>
                            </small>
                        </td>

                        <td class="text-end" style="width:120px;">
                            <strong>
                                <?= money($p['ImporteAplicado']) ?>
                            </strong>
                        </td>

                        <td class="text-center" style="width:90px;">

                            <?php if ($conciliado === 'SI') { ?>

                                <span style="
                color:green;
                font-weight:bold;
            ">
                                    SI
                                </span>

                            <?php } else { ?>

                                <span style="
                color:#d39e00;
                font-weight:bold;
            ">
                                    NO
                                </span>

                            <?php } ?>

                        </td>

                    </tr>

            <?php
                }
            }
            ?>

        </tbody>
    </table>
    <script>
        window.onload = function() {
            // Si querés que abra e imprima directo, descomentá:
            // window.print();
        };
    </script>

</body>

</html>