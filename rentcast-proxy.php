<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('RENTCAST_KEY', 'e0c4f92be6634e90a8f34b9b30e0c9eb');

$type  = isset($_GET['type'])  ? $_GET['type']  : 'sale';
$city  = isset($_GET['city'])  ? $_GET['city']  : 'Austin';
$state = isset($_GET['state']) ? $_GET['state'] : 'TX';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

$base = $type === 'rental'
    ? 'https://api.rentcast.io/v1/listings/rental/long-term'
    : 'https://api.rentcast.io/v1/listings/sale';

$params = http_build_query([
    'city'   => $city,
    'state'  => $state,
    'limit'  => $limit,
    'status' => 'Active',
]);

$url = $base . '?' . $params;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'X-Api-Key: ' . RENTCAST_KEY,
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL error: ' . $error]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo $response;
    exit;
}

echo $response;