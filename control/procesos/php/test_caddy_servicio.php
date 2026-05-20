<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');


$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api.caddy.com.ar/api/servicios',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>'{
  "NombreCompleto": "Elsa Fig",
  "Direccion": "TURRADO JUAREZ 1377",
  "Ciudad": "Córdoba",
  "CodigoPostal": "5000",
  "Dni": "223334434",
  "EnviarMail": true,
  "Mail": "prodriguez@caddy.com.ar",
  "Telefono": "0351152462825",
  "Cantidad": 1,
  "Servicio": 3,
  "ValorDeclarado": "10000",
  "Cobranza": "107690",
  "idProveedor": "I2757268",
  "Observaciones": "ELSA VIVE EN UNA CASA COLOR GRIS CON DETALLES EN NEGRO, HAY UN PERRO EN LA PUERTA TOCAR EL TIMBRE QUE DICE 1...",
  "WebHook": "https://mi-sistema.com/webhook/caddy",
  "Origen": [
    {
      "idProveedor": "",
      "Nombre": "",
      "Direccion": ""
    }
  ],
  "Box": [
    {
      "Length": "10",
      "Width": "10",
      "Height": "10",
      "Weight": "10"
    }
  ]
}',
  CURLOPT_HTTPHEADER => array(
    'X-Api-Token: Bearer b4350e3bc34e1990381427a5bb10d717',
    'Content-Type: application/json'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
