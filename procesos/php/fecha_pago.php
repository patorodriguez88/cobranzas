<?php
// Fecha de un pago informado (depósito/transferencia): desde hoy hasta 30 días atrás,
// en hora de Córdoba. Devuelve el mensaje de error o null si la fecha es válida.
// La usan la carga de pagos del cliente, la cobranza directa y el depósito desde Ventas.
const PAGO_DIAS_ATRAS = 30;

function errorFechaPago(string $fecha): ?string
{
    $zona = new DateTimeZone('America/Argentina/Cordoba');
    $f = DateTime::createFromFormat('!Y-m-d', $fecha, $zona);
    if (!$f || $f->format('Y-m-d') !== $fecha) {
        return 'La fecha del pago no es válida.';
    }
    $hoy = new DateTime('today', $zona);
    if ($f > $hoy) {
        return 'La fecha del pago no puede ser posterior a hoy.';
    }
    if ($f < (clone $hoy)->modify('-' . PAGO_DIAS_ATRAS . ' days')) {
        return 'La fecha del pago no puede tener más de ' . PAGO_DIAS_ATRAS . ' días.';
    }
    return null;
}
