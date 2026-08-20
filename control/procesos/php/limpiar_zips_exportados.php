<?php
// Pensado para ejecutarse por cron (una vez por día). Borra los ZIP de
// comprobantes de exportaciones con más de 30 días de antigüedad. El CSV
// de exportación no se toca.

include_once __DIR__ . "/../../../conexion/conexioni.php";

$carpetaZip = __DIR__ . '/exportaciones/comprobantes/';
$limiteDias = 30;
$limiteSegundos = time() - ($limiteDias * 24 * 60 * 60);

$borrados = 0;

foreach (glob($carpetaZip . '*.zip') as $archivo) {

    if (filemtime($archivo) >= $limiteSegundos) {
        continue;
    }

    $nombre = basename($archivo);

    if (unlink($archivo)) {
        $borrados++;
        $mysqli->query(
            "UPDATE Cobranza_exportados SET ZipArchivo = NULL WHERE ZipArchivo = '" .
                $mysqli->real_escape_string($nombre) . "'"
        );
    }
}

echo "ZIPs borrados: {$borrados}\n";
