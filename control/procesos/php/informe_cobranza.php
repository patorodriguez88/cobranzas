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
    die("Cobranza inválida.");
}

$sqlCobranza = "
    SELECT
        C.*,
        CC.Fecha AS FechaConciliacion,
        CC.Observaciones AS ObservacionesConciliacion,
        U.Usuario AS UsuarioConcilio
    FROM Cobranza C
    LEFT JOIN (
        SELECT CC1.*
        FROM Cobranza_conciliacion CC1
        INNER JOIN (
            SELECT id_cobranza, MAX(id) AS UltimoId
            FROM Cobranza_conciliacion
            GROUP BY id_cobranza
        ) ULT ON ULT.UltimoId = CC1.id
    ) CC ON CC.id_cobranza = C.id
    LEFT JOIN usuarios U ON U.id = CC.Usuario
    WHERE C.id = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sqlCobranza);
$stmt->bind_param("i", $id);
$stmt->execute();
$cobranza = $stmt->get_result()->fetch_assoc();

if (!$cobranza) {
    die("Cobranza no encontrada.");
}

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
    <title>Comprobante de Cobranza #<?= $id ?></title>
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

        .totales {
            width: 40%;
            margin-left: auto;
            margin-top: 5px;
        }

        .totales td {
            font-size: 9px;
            padding: 3px 4px;
        }

        .total-final {
            font-size: 11px;
            font-weight: bold;
        }

        .badge-directa {
            display: inline-block;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 8px;
            color: #555;
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
                <div class="title">Comprobante de Cobranza</div>
                <div class="subtitle">Cobranza Nº <?= htmlspecialchars($id) ?></div>
            </div>

            <div class="text-end">
                <strong>DINTER S.A.</strong><br>
                Fecha: <?= fecha($cobranza['Fecha'] ?? date('Y-m-d')) ?><br>
                Hora: <?= htmlspecialchars($cobranza['Hora'] ?? '') ?>
            </div>
        </div>

        <div class="grid">
            <div class="box">
                <div class="label">Cliente</div>
                <div class="value">
                    <?= htmlspecialchars($cobranza['NombreCliente'] ?? '') ?>
                </div>

                <div class="label">Nº Cliente</div>
                <div class="value"><?= htmlspecialchars($cobranza['NumeroCliente'] ?? '') ?></div>
            </div>

            <div class="box">
                <div class="label">Estado</div>
                <div class="value">
                    <span class="badge-directa">Cobranza directa — sin venta asociada</span>
                </div>

                <div class="label">Cargado por</div>
                <div class="value"><?= htmlspecialchars($cobranza['Usuario'] ?? '') ?></div>
            </div>
        </div>

        <div class="grid">
            <div class="box">
                <div class="label">Banco</div>
                <div class="value"><?= htmlspecialchars($cobranza['Banco'] ?? '') ?></div>

                <div class="label">Operación</div>
                <div class="value"><?= htmlspecialchars($cobranza['Operacion'] ?? '') ?></div>
            </div>

            <div class="box">
                <div class="label">Conciliado</div>
                <div class="value">
                    <?= intval($cobranza['Conciliado'] ?? 0) === 1 ? 'SI — ' . fecha($cobranza['FechaConciliacion'] ?? '') : 'NO' ?>
                </div>

                <?php if (!empty($cobranza['UsuarioConcilio'])) { ?>
                    <div class="label">Concilió</div>
                    <div class="value"><?= htmlspecialchars($cobranza['UsuarioConcilio']) ?></div>
                <?php } ?>
            </div>
        </div>

        <?php if (!empty($cobranza['Usuario_obs']) || !empty($cobranza['ObservacionesConciliacion'])) { ?>
            <div class="box">
                <div class="label">Observaciones</div>
                <div class="value">
                    <?= nl2br(htmlspecialchars($cobranza['Usuario_obs'] ?: $cobranza['ObservacionesConciliacion'])) ?>
                </div>
            </div>
        <?php } ?>

        <table class="totales">
            <tr>
                <td>Importe</td>
                <td class="text-end total-final"><?= money($cobranza['Importe'] ?? 0) ?></td>
            </tr>
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
