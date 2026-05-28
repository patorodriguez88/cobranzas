<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
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
        CV.Usuario AS UsuarioAplicacion,
        CV.Fecha AS FechaAplicacion,

        C.Banco,
        C.Operacion,
        C.Conciliado,
        C.Usuario_obs

    FROM CobranzasVentas CV

    LEFT JOIN Cobranza C 
        ON C.id = CV.idCobranza

    WHERE CV.idVenta = ?
      AND CV.Eliminado = 0

    ORDER BY CV.id ASC
";

$stmt = $mysqli->prepare($sqlPagos);
$stmt->bind_param("i", $id);
$stmt->execute();

$pagos = $stmt->get_result();

$sqlDetalleAjustes = "

    SELECT tipo, observaciones, importe

    FROM Ventas_Ajustes_Pago

    WHERE idVenta = ?

      AND eliminado = 0

";

$stmt = $mysqli->prepare($sqlDetalleAjustes);

$stmt->bind_param("i", $id);

$stmt->execute();

$detalleAjustes = $stmt->get_result();

$ajustesTexto = [];
$totalAjustes = 0;

while ($aj = $detalleAjustes->fetch_assoc()) {

    $totalAjustes += floatval($aj['importe'] ?? 0);

    $texto = '';

    if (!empty($aj['tipo'])) {

        $texto = $aj['tipo'];
    }

    if (!empty($aj['observaciones'])) {

        if ($texto != '') {

            $texto .= ': ';
        }

        $texto .= $aj['observaciones'];
    }

    if ($texto != '') {

        $ajustesTexto[] = $texto;
    }
}

$totalVenta   = floatval($venta['Total'] ?? 0);
$totalPagado  = floatval($venta['TotalPagado'] ?? 0);

$saldoCalculado = $totalVenta - $totalPagado - $totalAjustes;

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
            margin: 6mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #222;
            font-size: 8.5px;
            background: #e9ecef;
            margin: 0;
            padding: 20px 0;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #FFF;
            box-shadow: 0 0 15px rgba(0, 0, 0, .15);
            padding: 6mm;
            box-sizing: border-box;
        }

        .header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #222;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
        }

        .subtitle {
            color: #666;
            margin-top: 1px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            align-items: stretch;
        }

        .grid .box {
            height: 100%;
        }

        .box {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            margin-bottom: 5px;
            box-sizing: border-box;
        }

        .label {
            font-size: 7.5px;
            color: #777;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .value {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 1px;
            color: #111;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            border: 1px solid #e5e5e5;
        }

        th {
            background: #f5f5f5;
            text-align: left;
            border-bottom: 1px solid #dcdcdc;
            padding: 4px 5px;
            font-size: 8px;
            font-weight: 700;
            color: #444;
        }

        td {
            border-bottom: 1px solid #ededed;
            padding: 4px 5px;
            font-size: 8px;
            vertical-align: top;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totales {
            width: 34%;
            margin-left: auto;
            margin-top: 5px;
        }

        .totales td {
            font-size: 8px;
            padding: 2px 3px;
        }

        .total-final {
            font-size: 9px;
            font-weight: bold;
        }

        h3 {
            margin-top: 7px !important;
            margin-bottom: 3px;
            font-size: 10px;
        }

        small {
            font-size: 6.8px;
        }

        .print-btn {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 8px 14px;
            border: none;
            background: #222;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        @media print {
            body {
                background: #FFF;
                padding: 0;
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            .print-btn {
                display: none;
            }
        }
    </style>
</head>

<body>

    <button class="print-btn" onclick="window.print()">Imprimir</button>
    <div class="page">
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

        <div class="grid">

            <!-- COLUMNA IZQUIERDA -->
            <div class="box">

                <div class="label">Estado de pago</div>
                <div class="value">
                    <?= htmlspecialchars($venta['EstadoPago'] ?? '') ?>
                </div>

                <div class="label">Usuario</div>
                <div class="value">
                    <?= htmlspecialchars($venta['Usuario'] ?? '') ?>
                </div>

                <?php if (!empty($venta['Observaciones'])) { ?>
                    <div class="label">Observaciones</div>
                    <div class="value">
                        <?= nl2br(htmlspecialchars($venta['Observaciones'])) ?>
                    </div>
                <?php } ?>

            </div>

            <!-- COLUMNA DERECHA -->
            <div class="box">

                <div class="label">OV Wepoint</div>
                <div class="value">
                    <?php if (!empty($venta['wepoint_nro_orden_venta'])) { ?>
                        #<?= htmlspecialchars($venta['wepoint_nro_orden_venta']) ?>

                        <?php if (!empty($venta['wepoint_estado'])) { ?>
                            - <?= htmlspecialchars($venta['wepoint_estado']) ?>
                        <?php } ?>

                    <?php } else { ?>
                        Sin OV Wepoint generada
                    <?php } ?>
                </div>

                <?php if (!empty($venta['wepoint_created_at'])) { ?>
                    <div class="label">Fecha OV Wepoint</div>
                    <div class="value">
                        <?= date('d/m/Y H:i', strtotime($venta['wepoint_created_at'])) ?>
                    </div>
                <?php } ?>

                <?php if (!empty($venta['caddy_codigo_seguimiento'])) { ?>
                    <div class="label">Seguimiento Caddy</div>
                    <div class="value">
                        <?= htmlspecialchars($venta['caddy_codigo_seguimiento']) ?>
                    </div>
                <?php } ?>

            </div>

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
                <td>

                    Ajustes incluidos

                    <?php if (!empty($ajustesTexto)) { ?>

                        <br>

                        <small>

                            (<?= htmlspecialchars(implode(' | ', $ajustesTexto)) ?>)

                        </small>

                    <?php } ?>

                </td>
                <td class="text-end"><?= money($totalAjustes) ?></td>
            </tr>

            <tr>
                <td>Saldo</td>
                <td class="text-end total-final"><?= money($saldoCalculado) ?></td>
            </tr>
        </table>
        <h3>
            Pagos / Depósitos
            <?php if (!empty($venta['wepoint_nro_orden_venta'])) { ?>
                - OV Wepoint #<?= htmlspecialchars($venta['wepoint_nro_orden_venta']) ?>
            <?php } ?>
        </h3>

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

                        $tieneComprobante = false;
                    ?>

                        <tr>

                            <td style="width:50px;">
                                <?= fecha($p['FechaAplicacion']) ?><br>

                                <small>
                                    <?= !empty($p['FechaAplicacion'])
                                        ? date('H:i', strtotime($p['FechaAplicacion']))
                                        : '' ?>
                                </small>
                            </td>

                            <td style="width:70px;">
                                <strong>
                                    <?= !empty($p['Banco']) ? htmlspecialchars($p['Banco']) : 'Sin banco informado' ?>
                                </strong>

                                <?php if ($tieneComprobante) { ?>
                                    <br>
                                    <small style="color:green;">
                                        📎 Comprobante adjunto
                                    </small>
                                <?php } ?>
                            </td>

                            <td style="width:180px;">
                                <?= !empty($p['Operacion']) ? htmlspecialchars($p['Operacion']) : 'Sin número informado' ?>

                                <br>

                                <small style="color:#666;">
                                    Cobranza #<?= intval($p['idCobranza']) ?>
                                </small>
                            </td>

                            <td>
                                <?= !empty($p['Usuario_obs']) ? nl2br(htmlspecialchars($p['Usuario_obs'])) : '-' ?>
                                <br>
                                <small>Usuario: <?= htmlspecialchars($p['UsuarioAplicacion'] ?? '-') ?></small>
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
    </div>

</body>

</html>