<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=UTF-8');

include 'conexao.php';

// -------- Helper de resposta JSON --------
function reply($arr, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($conn) || !$conn) {
    reply(["success" => false, "error" => "Falha na conexão com o banco."], 500);
}

// 🔑 API Key do OpenRouteService (ideal ler de variável de ambiente)
$ORS_API_KEY = "eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6IjI3ZTE2YmY1OGIwODRmY2NhMDAxMGQ5NTNlMzc4MTJkIiwiaCI6Im11cm11cjY0In0=";

/** Distância Haversine em KM */
function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2)
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
       * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

/**
 * Geocoding no ORS restrito a Nepomuceno:
 * - Usa boundary.circle com centro aproximado da cidade e raio (km)
 * - Aceita o ponto apenas se properties indicarem Nepomuceno/MG ou se estiver dentro do raio
 * - Retorna ['lat'=>..., 'lon'=>...] ou null
 */
function buscarLatLonNepomuceno($enderecoBase, $apiKey, &$httpStatus = null, &$curlErr = null) {
    // Centro aproximado de Nepomuceno/MG (ajuste se desejar)
    $centerLat = -21.2328;
    $centerLon = -45.2352;
    $raioKm    = 20; // raio aceito (km) – pode reduzir p/ ficar mais estrito

    // Monta texto com cidade explícita (melhora a precisão)
    $text = trim($enderecoBase) !== '' 
        ? "{$enderecoBase}, Nepomuceno, MG, Brasil" 
        : "Nepomuceno, MG, Brasil";

    $params = [
        'api_key'                => $apiKey,
        'text'                   => $text,
        'size'                   => 1,
        'boundary.country'       => 'BR',
        'boundary.circle.lat'    => $centerLat,
        'boundary.circle.lon'    => $centerLon,
        'boundary.circle.radius' => $raioKm, // km
        // Opcional: "puxar" resultados para o centro
        // 'focus.point.lat'     => $centerLat,
        // 'focus.point.lon'     => $centerLon,
    ];

    $url = "https://api.openrouteservice.org/geocode/search?" . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response   = curl_exec($ch);
    $curlErr    = curl_error($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['features'][0]['geometry']['coordinates'][0], $data['features'][0]['geometry']['coordinates'][1])) {
        return null;
    }

    $feat = $data['features'][0];
    $lon  = (float)$feat['geometry']['coordinates'][0];
    $lat  = (float)$feat['geometry']['coordinates'][1];

    // ---- Validação: aceitar apenas Nepomuceno/MG ----
    $props = $feat['properties'] ?? [];
    $txt   = strtoupper(trim(
        ($props['locality'] ?? '') . ' ' .
        ($props['county']   ?? '') . ' ' .
        ($props['region']   ?? '') . ' ' .
        ($props['label']    ?? '') . ' ' .
        ($props['name']     ?? '')
    ));

    $isNepomucenoByText = (strpos($txt, 'NEPOMUCENO') !== false) && (strpos($txt, 'MG') !== false);

    // Também valida por distância ao centro (fallback)
    $distKm = haversine($lat, $lon, $centerLat, $centerLon);
    $isInsideCircle = ($distKm <= $raioKm);

    if ($isNepomucenoByText || $isInsideCircle) {
        return ['lat' => $lat, 'lon' => $lon];
    }

    // Caso não passe nas validações, rejeita
    return null;
}

// -------- Seleciona registros sem lat/lon --------
$sql = "SELECT id, endereco, numero_endereco
        FROM reclamacoes
        WHERE (lat IS NULL OR lon IS NULL OR lat = 0 OR lon = 0)";
$res = $conn->query($sql);

if (!$res) {
    reply(["success" => false, "error" => "Erro na consulta: " . $conn->error], 500);
}

if ($res->num_rows === 0) {
    reply([
        "success"   => true,
        "updated"   => 0,
        "processed" => 0,
        "skipped"   => 0,
        "message"   => "Nenhum registro pendente."
    ], 200);
}

// -------- Processa em lote --------
$processed = 0;
$updated   = 0;
$skipped   = 0;
$errors    = [];

// $limite = 50; // opcional: limitar por execução

while ($row = $res->fetch_assoc()) {
    $processed++;

    $id  = (int)$row['id'];
    $rua = trim((string)$row['endereco']);
    $num = trim((string)$row['numero_endereco']);

    // Monta o endereço base: rua + número (cidade é adicionada dentro do geocoder)
    $enderecoBase = $rua . (strlen($num) ? " {$num}" : "");

    $httpStatus = null;
    $curlErr    = null;
    $coords     = buscarLatLonNepomuceno($enderecoBase, $ORS_API_KEY, $httpStatus, $curlErr);

    if ($coords && isset($coords['lat'], $coords['lon'])) {
        $lat = (float)$coords['lat'];
        $lon = (float)$coords['lon'];

        // Atualiza no banco
        $stmt = $conn->prepare("UPDATE reclamacoes SET lat = ?, lon = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ddi", $lat, $lon, $id);
            if ($stmt->execute()) {
                $updated++;
            } else {
                $errors[] = "Falha ao atualizar ID {$id}: {$stmt->error}";
            }
            $stmt->close();
        } else {
            $errors[] = "Falha ao preparar UPDATE para ID {$id}: {$conn->error}";
        }
    } else {
        $skipped++;
        $msg = "Sem coordenadas válidas (Nepomuceno) para ID {$id}.";
        if ($curlErr)              $msg .= " cURL: {$curlErr}.";
        if ($httpStatus !== null)  $msg .= " HTTP: {$httpStatus}.";
        $errors[] = $msg;
    }

    // Respeita rate-limit do ORS (plano gratuito ~1 req/seg)
    sleep(1);

    // if ($processed >= $limite) break;
}

// -------- Resposta final --------
reply([
    "success"       => true,
    "processed"     => $processed,
    "updated"       => $updated,
    "skipped"       => $skipped,
    "errors_count"  => count($errors),
    "errors_sample" => array_slice($errors, 0, 10)
], 200);
