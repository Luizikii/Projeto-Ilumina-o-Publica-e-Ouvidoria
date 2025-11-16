<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Log para depuração
file_put_contents('log_envio.txt', print_r($data, true));

if (!isset($data['numero']) || !isset($data['mensagem'])) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos']);
    exit;
}

$appkey = '32042597-751f-4e64-9817-ac57dba94c4e';
$authkey = '2LKjTClKGy69uTC9SPi0c7x1zjiUJ4nNRxhJl5EPFW4COLZoW8';

$payload = [
    'appkey'  => $appkey,
    'authkey' => $authkey,
    'to'      => $data['numero'],
    'message' => $data['mensagem']
];

$ch = curl_init('https://chatbot.menuia.com/api/create-message');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Grava log de resposta da API
file_put_contents('resposta_api.txt', "Código: $http_code\nResposta: $response");

echo json_encode([
    'status' => 'ok',
    'codigo_http' => $http_code,
    'resposta' => json_decode($response, true)
]);
