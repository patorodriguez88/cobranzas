<?php
// Copiar como conexion/config_wepoint.php (NO se sube al repo, ver .gitignore) y completar.
// En producción se sube a mano por FTP una sola vez: el deploy no lo pisa.
//
// Cada distribuidora manda sus órdenes a la cuenta de Wepoint de su empresa. Una cuenta es:
// - email + password: Cobranzas inicia sesión, guarda el token en la tabla WepointTokens y
//   vuelve a iniciar sesión solo si Wepoint lo rechaza; o
// - token: un token fijo (cuentas viejas).
// "transportista_clientes": transportista habitual de los clientes que Cobranzas crea en esa cuenta.
return [
    'base_url' => 'https://sistema.wepoint.ar/api/v2', // testing: https://testing.wepoint.ar/api/v2

    'cuentas' => [
        // DINTER SA
        'dinter' => [
            'email' => 'cobranza@dintersa.com.ar',
            'password' => '',
            'transportista_clientes' => 1, // Caddy
        ],
        // Cuenta anterior (empresa "Patricio"): la usan Misas y las órdenes enviadas antes del cambio.
        'patricio' => [
            'token' => '',
        ],
        // El Rak SAS
        'rak' => [
            'token' => '',
        ],
    ],

    // Distribuidora de Cobranzas (en mayúsculas) => cuenta de Wepoint para las órdenes NUEVAS.
    'distribuidoras' => [
        'DINTER' => 'dinter',
        'MISAS' => 'patricio',
        'RAK' => 'rak',
    ],

    // Cuenta de las órdenes enviadas antes de guardar la cuenta en cada venta (columna Ventas.wepoint_cuenta).
    'cuenta_anterior' => 'patricio',
];
