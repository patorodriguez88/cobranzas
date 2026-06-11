<?php
include "conexion/conexioni.php";
header("Content-Type: text/plain");

$result = $mysqli->query("SHOW COLUMNS FROM Cobranza_conciliacion");
if (!$result) {
    echo "Error: " . $mysqli->error . "\n";
    exit;
}
echo "=== Columnas de Cobranza_conciliacion ===\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Extra'] . "\n";
}

// Verificar si TimeStamp existe
$result2 = $mysqli->query("SELECT TimeStamp FROM Cobranza_conciliacion LIMIT 1");
if (!$result2) {
    echo "\n⚠️  La columna TimeStamp NO existe: " . $mysqli->error . "\n";
} else {
    $row2 = $result2->fetch_assoc();
    echo "\n✅ La columna TimeStamp EXISTE. Valor ejemplo: " . ($row2['TimeStamp'] ?? '(null)') . "\n";
}
