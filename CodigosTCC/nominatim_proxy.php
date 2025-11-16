<?php
header('Content-Type: application/json');

$lat = $_GET['lat'] ?? null;
$lon = $_GET['lon'] ?? null;

if (!$lat || !$lon) {
    echo json_encode(['error' => 'Latitude ou longitude não fornecidas']);
    exit;
}

$url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat={$lat}&lon={$lon}&zoom=18&addressdetails=1&accept-language=pt-BR";

$options = [
    "http" => [
        "header" => "User-Agent: ReclamaçõesApp/1.0\r\n"
    ]
];
$context = stream_context_create($options);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo json_encode(['error' => 'Falha na conexão com Nominatim']);
} else {
    echo $response;
}
?>