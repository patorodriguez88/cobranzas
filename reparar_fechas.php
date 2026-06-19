<?php
session_start();
include_once "conexion/conexioni.php";

// Solo accesible para usuarios de control
if (empty($_SESSION['user_control'])) {
    http_response_code(403);
    die('<p style="font-family:monospace;color:red">Acceso denegado. Iniciá sesión primero.</p>');
}

$ejecutar = isset($_POST['ejecutar']);

// ─────────────────────────────────────────────
// DIAGNÓSTICO: contar registros con fecha rota
// ─────────────────────────────────────────────

$diagCC = $mysqli->query("
    SELECT COUNT(*) AS total
    FROM Cobranza_conciliacion
    WHERE Fecha = '0000-00-00' OR Fecha IS NULL OR Fecha = ''
");
$totalCC = (int)$diagCC->fetch_assoc()['total'];

$diagCE = $mysqli->query("
    SELECT COUNT(*) AS total
    FROM Cobranza_exportados
    WHERE Fecha = '0000-00-00' OR Fecha IS NULL OR Fecha = ''
");
$totalCE = (int)$diagCE->fetch_assoc()['total'];

// Cuántos de Cobranza_conciliacion NO tienen solución (Cobranza.Fecha también rota)
$diagSinSol = $mysqli->query("
    SELECT COUNT(*) AS total
    FROM Cobranza_conciliacion cc
    LEFT JOIN Cobranza c ON c.id = cc.id_cobranza
    WHERE (cc.Fecha = '0000-00-00' OR cc.Fecha IS NULL OR cc.Fecha = '')
      AND (c.Fecha IS NULL OR c.Fecha = '0000-00-00' OR c.Fecha = '' OR c.id IS NULL)
");
$totalSinSol = (int)$diagSinSol->fetch_assoc()['total'];

$resultados = [];

// ─────────────────────────────────────────────
// EJECUCIÓN
// ─────────────────────────────────────────────
if ($ejecutar) {

    $mysqli->begin_transaction();

    try {

        // 1. Cobranza_conciliacion: usar Cobranza.Fecha
        $r1 = $mysqli->query("
            UPDATE Cobranza_conciliacion cc
            INNER JOIN Cobranza c ON c.id = cc.id_cobranza
            SET cc.Fecha = c.Fecha
            WHERE (cc.Fecha = '0000-00-00' OR cc.Fecha IS NULL OR cc.Fecha = '')
              AND c.Fecha IS NOT NULL
              AND c.Fecha != '0000-00-00'
              AND c.Fecha != ''
        ");

        if (!$r1) throw new Exception("Error en Cobranza_conciliacion: " . $mysqli->error);
        $corregidosCC = $mysqli->affected_rows;

        // 2. Cobranza_exportados: derivar del TimeStamp auto-generado
        $r2 = $mysqli->query("
            UPDATE Cobranza_exportados
            SET Fecha = DATE_FORMAT(TimeStamp, '%Y-%m-%d')
            WHERE (Fecha = '0000-00-00' OR Fecha IS NULL OR Fecha = '')
              AND TimeStamp IS NOT NULL
              AND TimeStamp != '0000-00-00 00:00:00'
        ");

        if (!$r2) throw new Exception("Error en Cobranza_exportados: " . $mysqli->error);
        $corregidosCE = $mysqli->affected_rows;

        $mysqli->commit();

        $resultados = [
            'ok'           => true,
            'cc'           => $corregidosCC,
            'ce'           => $corregidosCE,
        ];

    } catch (Exception $e) {
        $mysqli->rollback();
        $resultados = ['ok' => false, 'error' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reparar fechas</title>
<style>
  body { font-family: monospace; max-width: 680px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
  h2   { color: #333; border-bottom: 2px solid #ccc; padding-bottom: 8px; }
  .card { background: #fff; border: 1px solid #ddd; border-radius: 6px; padding: 20px; margin-bottom: 18px; }
  .ok   { color: #28a745; font-weight: bold; }
  .err  { color: #dc3545; font-weight: bold; }
  .warn { color: #e67e22; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
  th { background: #f0f0f0; }
  .btn-fix {
    display: inline-block; padding: 10px 24px; background: #dc3545;
    color: #fff; border: none; border-radius: 4px; cursor: pointer;
    font-size: 15px; font-family: monospace;
  }
  .btn-fix:hover { background: #b02a37; }
</style>
</head>
<body>

<h2>Reparar fechas 0000-00-00</h2>

<!-- RESULTADO -->
<?php if (!empty($resultados)): ?>
  <?php if ($resultados['ok']): ?>
    <div class="card">
      <p class="ok">✔ Corrección aplicada correctamente.</p>
      <table>
        <tr><th>Tabla</th><th>Registros corregidos</th></tr>
        <tr><td>Cobranza_conciliacion</td><td><?= $resultados['cc'] ?></td></tr>
        <tr><td>Cobranza_exportados</td><td><?= $resultados['ce'] ?></td></tr>
      </table>
    </div>
  <?php else: ?>
    <div class="card">
      <p class="err">✖ Error: <?= htmlspecialchars($resultados['error']) ?></p>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- DIAGNÓSTICO -->
<div class="card">
  <strong>Diagnóstico actual</strong>
  <table style="margin-top:10px">
    <tr>
      <th>Tabla</th>
      <th>Fechas rotas</th>
      <th>Reparables</th>
      <th>Sin solución</th>
    </tr>
    <tr>
      <td>Cobranza_conciliacion</td>
      <td><?= $totalCC ?></td>
      <td class="ok"><?= $totalCC - $totalSinSol ?></td>
      <td class="<?= $totalSinSol > 0 ? 'warn' : 'ok' ?>"><?= $totalSinSol ?></td>
    </tr>
    <tr>
      <td>Cobranza_exportados</td>
      <td><?= $totalCE ?></td>
      <td class="ok"><?= $totalCE ?></td>
      <td class="ok">0</td>
    </tr>
  </table>

  <?php if ($totalSinSol > 0): ?>
    <p class="warn" style="margin-top:12px">
      ⚠ <?= $totalSinSol ?> registro(s) en Cobranza_conciliacion no tienen fecha recuperable
      (la Cobranza de origen tampoco tiene fecha válida). Esos quedarán sin corregir.
    </p>
  <?php endif; ?>
</div>

<!-- BOTON EJECUTAR -->
<?php if ($totalCC > 0 || $totalCE > 0): ?>
  <div class="card">
    <p>Se van a actualizar <strong><?= ($totalCC - $totalSinSol) + $totalCE ?></strong> registro(s).</p>
    <form method="post">
      <button class="btn-fix" type="submit" name="ejecutar" value="1"
        onclick="return confirm('¿Confirmar corrección de fechas?')">
        Ejecutar corrección
      </button>
    </form>
  </div>
<?php else: ?>
  <div class="card">
    <p class="ok">✔ No hay fechas rotas. No es necesario ejecutar nada.</p>
  </div>
<?php endif; ?>

</body>
</html>
