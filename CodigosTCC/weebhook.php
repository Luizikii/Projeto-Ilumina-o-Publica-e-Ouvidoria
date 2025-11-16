<?php
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexao.php'; // Conexão ao MySQL

function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

// Pegar a reclamação mais antiga
$sql_antiga = "SELECT * FROM reclamacoes ORDER BY data_hora ASC LIMIT 1";
$result_antiga = $conn->query($sql_antiga);
if (!$result_antiga) {
    die(json_encode([
        "components" => [
            ["type" => "body", "parameters" => [["type" => "text", "text" => "Erro na query da reclamação mais antiga: " . $conn->error]]]
        ]
    ], JSON_UNESCAPED_UNICODE));
}
if ($result_antiga->num_rows == 0) {
    echo json_encode([
        "components" => [
            ["type" => "body", "parameters" => [["type" => "text", "text" => "Não há reclamações cadastradas."]]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$mais_antiga = $result_antiga->fetch_assoc();
$lat_ref = $mais_antiga['lat'];
$lon_ref = $mais_antiga['lon'];

// Buscar todas as outras reclamações
$sql_todas = "SELECT * FROM reclamacoes WHERE id != {$mais_antiga['id']}";
$result_todas = $conn->query($sql_todas);
if (!$result_todas) {
    die(json_encode([
        "components" => [
            ["type" => "body", "parameters" => [["type" => "text", "text" => "Erro na query das demais reclamações: " . $conn->error]]]
        ]
    ], JSON_UNESCAPED_UNICODE));
}

$distancias = [];
while ($row = $result_todas->fetch_assoc()) {
    $dist = haversine($lat_ref, $lon_ref, $row['lat'], $row['lon']);
    $row['distancia'] = $dist;
    $distancias[] = $row;
}

// Ordenar pelo menor valor de distância
usort($distancias, function($a, $b) {
    return $a['distancia'] <=> $b['distancia'];
});

// Construir componentes de mensagem separados (cada linha como parâmetro)
$components = [
    ["type" => "body", "parameters" => [["type" => "text", "text" => "Localizações das reclamações:"]]],
    ["type" => "body", "parameters" => [["type" => "text", "text" => "1. {$mais_antiga['endereco']} (mais antiga)"]]]
];

$i = 2;
foreach ($distancias as $d) {
    $components[] = [
        "type" => "body",
        "parameters" => [["type" => "text", "text" => "{$i}. {$d['endereco']} (aprox. " . round($d['distancia'], 2) . " km)"]]
    ];
    $i++;
}

// Retornar JSON compatível com message templates
echo json_encode(["components" => $components], JSON_UNESCAPED_UNICODE);
