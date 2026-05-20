<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$token = 'b4350e3bc34e1990381427a5bb10d717';

$payload = [
    "NombreCompleto" => "Elsa Fig",
    "Direccion" => "TURRADO JUAREZ 1377",
    "Ciudad" => "Córdoba",
    "CodigoPostal" => "5000",
    "Dni" => "223334434",
    "EnviarMail" => true,
    "Mail" => "prodriguez@caddy.com.ar",
    "Telefono" => "0351152462825",
    "Cantidad" => 1,
    "Servicio" => 3,
    "ValorDeclarado" => "10000",
    "Cobranza" => "107690",
    "idProveedor" => "TEST" . time(),
    "Observaciones" => "Prueba directa Caddy",
    "WebHook" => "",
    "Origen" => [["idProveedor" => "", "Nombre" => "", "Direccion" => ""]],
    "Box" => [["Length" => "10", "Width" => "10", "Height" => "10", "Weight" => "10"]]
];

$jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.caddy.com.ar/api/servicios',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_HTTPHEADER => [
        'X-Api-Token: Bearer ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
    ],
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);

curl_close($curl);

echo json_encode([
    "http" => $httpCode,
    "error" => $error,
    "payload" => $payload,
    "response_raw" => $response,
    "response_json" => json_decode($response, true)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);