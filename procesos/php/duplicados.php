<?php
// Criterio único para detectar pagos duplicados (lo usan la carga del cliente, la cobranza
// directa, el depósito desde Ventas y la alerta de Pendientes al conciliar).
//
// Mismo depósito = mismo banco + mismo N° de operación + mismo importe, SIN exigir la misma
// fecha (el mismo depósito cargado con fechas distintas antes no se detectaba). Con un N° de
// operación corto (menos de 8 caracteres, ej. "4593") además tiene que ser el mismo cliente:
// esos números se repiten entre clientes distintos con importes fijos (visto en la base, 2/10/2026). El N° de
// operación se compara normalizado: solo dígitos y sin ceros adelante ("00123 456" = "123456");
// si no tiene dígitos, en mayúsculas y sin espacios. El banco, sin la palabra "Banco" ni
// mayúsculas ("Banco Macro" = "Macro"). Los pagos en efectivo no se controlan (no hay
// operación bancaria).

function normalizarOperacionPago(string $op): string
{
    $digitos = preg_replace('/\D/', '', $op);
    if ($digitos !== '') {
        $sinCeros = ltrim($digitos, '0');
        return $sinCeros === '' ? '0' : $sinCeros;
    }
    return strtoupper(preg_replace('/\s+/', '', $op));
}

function normalizarBancoPago(string $banco): string
{
    $b = mb_strtolower(trim($banco), 'UTF-8');
    $b = preg_replace('/^banco\s+(de\s+(la\s+)?)?/u', '', $b);
    return strtr($b, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
}

/**
 * Pagos ya cargados que coinciden con este (banco + operación + importe). Devuelve las filas
 * (id, Fecha, NombreCliente, NumeroCliente, Banco, Operacion, Importe, Usuario, Conciliado).
 * $excluirId: para no encontrarse a sí mismo al revisar un pago ya guardado.
 */
function buscarPagosDuplicados(mysqli $mysqli, string $banco, string $operacion, float $importe, string $numeroCliente = '', int $excluirId = 0): array
{
    $op = normalizarOperacionPago($operacion);
    // Un N° de operación de menos de 4 caracteres ("1", "12") no alcanza para decir que es el mismo
    // depósito: se evita bloquear pagos legítimos por coincidencias casuales.
    if (strlen($op) < 4 || $importe <= 0) {
        return [];
    }
    $bancoN = normalizarBancoPago($banco);
    // Prefiltro en SQL por importe (indexable y barato); el resto se compara normalizado en PHP
    // para que el criterio sea exactamente el mismo en todos lados.
    $st = $mysqli->prepare("SELECT id, Fecha, NombreCliente, NumeroCliente, Banco, Operacion, Importe, Usuario, Conciliado, TipoOperacion
                              FROM Cobranza
                             WHERE ROUND(Importe, 2) = ROUND(?, 2) AND id <> ?");
    $st->bind_param('di', $importe, $excluirId);
    $st->execute();
    $res = $st->get_result();
    $dups = [];
    while ($r = $res->fetch_assoc()) {
        if (strtolower(trim((string) $r['TipoOperacion'])) === 'efectivo') {
            continue;
        }
        if (normalizarOperacionPago((string) $r['Operacion']) !== $op || normalizarBancoPago((string) $r['Banco']) !== $bancoN) {
            continue;
        }
        if (strlen($op) < 8 && (int) $r['NumeroCliente'] !== (int) $numeroCliente) {
            continue;
        }
        $dups[] = $r;
    }
    return $dups;
}
