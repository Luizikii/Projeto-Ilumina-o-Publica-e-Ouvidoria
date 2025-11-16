<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=UTF-8');

// 🔑 Sua API Key do OpenRouteService
$ORS_API_KEY = "eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjI3ZTE2YmY1OGIwODRmY2NhMDAxMGQ5NTNlMzc4MTJkIiwiaCI6Im11cm11cjY0In0="; // substitua pela sua chave real

// --- Lê JSON recebido ---
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input || empty($input['endereco']) || empty($input['numero'])) {
    echo json_encode(["success" => false, "error" => "Endereço ou número não fornecido"], JSON_UNESCAPED_UNICODE);
    exit;
}

$endereco = trim($input['endereco']);
$numero   = trim($input['numero']);

// Endereço + cidade fixo
$enderecoCompleto = $endereco . ", Nepomuceno, MG, Brasil";

// --- Função para normalizar strings ---
function normalizarTexto($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto); // remove acentos
    $texto = str_replace(['ss'], 's', $texto); // trata "ss"
    $texto = preg_replace('/[^a-z0-9 ]/', '', $texto); // remove caracteres estranhos
    return trim($texto);
}

// --- Função para buscar lat/lon no ORS ---
function buscarLatLon($enderecoCompleto, $enderecoUsuario, $apiKey) {
    // Centro aproximado de Nepomuceno
    $latCentro = -21.235;
    $lonCentro = -45.235;
    $raioKm = 50; // limite da busca

    $url = "https://api.openrouteservice.org/geocode/search?"
         . "api_key=" . urlencode($apiKey)
         . "&text=" . urlencode($enderecoCompleto)
         . "&size=5" // retorna até 5 resultados para debug
         . "&boundary.country=BR"
         . "&boundary.circle.lat={$latCentro}"
         . "&boundary.circle.lon={$lonCentro}"
         . "&boundary.circle.radius={$raioKm}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return ["success" => false, "error" => "Erro ao conectar na API"];
    }

    $data = json_decode($response, true);

    if (!isset($data['features'][0])) {
        return ["success" => false, "error" => "Nenhuma coordenada encontrada"];
    }

    // Primeiro resultado (principal)
    $coords = $data['features'][0]['geometry']['coordinates'];
    $lon = $coords[0];
    $lat = $coords[1];

    $nomeApi = $data['features'][0]['properties']['name'] ?? '';
    $labelApi = $data['features'][0]['properties']['label'] ?? '';
    $nomeApiNorm = normalizarTexto($nomeApi);
    $nomeUsuarioNorm = normalizarTexto($enderecoUsuario);

    // Alternativa para debug (sempre retorna a primeira opção da API)
    $alternativa = [
        "lat" => $lat,
        "lon" => $lon,
        "rua_api" => $nomeApi,
        "label_api" => $labelApi
    ];

    // 🔹 Validação extra: bounding box aproximada
    if ($lat < -21.50 || $lat > -21.00 || $lon < -45.50 || $lon > -45.00) {
        return [
            "success" => false,
            "error" => "Coordenada fora da área de Nepomuceno",
            "alternativa" => $alternativa
        ];
    }

    // 🔹 Verificação de nome da rua
    if (!empty($nomeUsuarioNorm) && !empty($nomeApiNorm)) {
        if (strpos($nomeApiNorm, $nomeUsuarioNorm) === false &&
            strpos($nomeUsuarioNorm, $nomeApiNorm) === false) {
            return [
                "success" => false,
                "error" => "Endereço encontrado não corresponde ao nome informado",
                "alternativa" => $alternativa
            ];
        }
    }

    return [
        "success" => true,
        "lat" => $lat,
        "lon" => $lon,
        "rua_api" => $nomeApi,
        "label_api" => $labelApi,
        "alternativa" => $alternativa
    ];
}

// Consulta API
$result = buscarLatLon($enderecoCompleto, $endereco, $ORS_API_KEY);

// Retorno final
echo json_encode($result, JSON_UNESCAPED_UNICODE);
