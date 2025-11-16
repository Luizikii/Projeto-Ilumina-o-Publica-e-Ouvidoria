<?php
include 'conexao.php';
include 'verifica_login.php';
header('Content-Type: application/json');

// Função Haversine
function haversine($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

// --- Buscar todas não concluídas ---
$sql = "SELECT id, lat, lon, data_hora 
        FROM reclamacoes 
        WHERE status_reclamacao <> 'Concluído'";
$result = $conn->query($sql);

if(!$result) {
    echo json_encode(['success'=>false, 'error'=>$conn->error]);
    exit;
}

$hoje = new DateTime();
$vencidas = [];
$normais = [];

while($row = $result->fetch_assoc()) {
    if(!empty($row['lat']) && !empty($row['lon'])) {
        $dataReq = new DateTime($row['data_hora']);
        $dias = $dataReq->diff($hoje)->days;

        if ($dias >= 15) {
            $vencidas[] = $row; // prioridade máxima
        } else {
            $normais[] = $row;
        }
    }
}

if (count($vencidas) === 0 && count($normais) === 0) {
    echo json_encode(['success'=>false, 'msg'=>'Nenhuma reclamação aberta encontrada']);
    exit;
}

$ordem = 1;

// --- Passo 1: ordenar vencidas pela distância da primeira vencida ---
if (!empty($vencidas)) {
    // a mais antiga vencida primeiro
    usort($vencidas, fn($a,$b)=> strtotime($a['data_hora']) <=> strtotime($b['data_hora']));
    $primeira = array_shift($vencidas);

    $conn->query("UPDATE reclamacoes SET ordem_proximidade = $ordem WHERE id = ".(int)$primeira['id']);
    $ordem++;

    $baseLat = (float)$primeira['lat'];
    $baseLon = (float)$primeira['lon'];

    // ordenar as outras vencidas pela distância
    usort($vencidas, function($a,$b) use($baseLat,$baseLon){
        $distA = haversine($baseLat, $baseLon, (float)$a['lat'], (float)$a['lon']);
        $distB = haversine($baseLat, $baseLon, (float)$b['lat'], (float)$b['lon']);
        return $distA <=> $distB;
    });

    foreach($vencidas as $rec) {
        $conn->query("UPDATE reclamacoes SET ordem_proximidade = $ordem WHERE id = ".(int)$rec['id']);
        $ordem++;
    }
}

// --- Passo 2: ordenar as normais ---
if (!empty($normais)) {
    // a mais antiga normal primeiro
    usort($normais, fn($a,$b)=> strtotime($a['data_hora']) <=> strtotime($b['data_hora']));
    $primeiraNormal = array_shift($normais);

    $conn->query("UPDATE reclamacoes SET ordem_proximidade = $ordem WHERE id = ".(int)$primeiraNormal['id']);
    $ordem++;

    $baseLat = (float)$primeiraNormal['lat'];
    $baseLon = (float)$primeiraNormal['lon'];

    // ordenar as demais pela distância
    usort($normais, function($a,$b) use($baseLat,$baseLon){
        $distA = haversine($baseLat, $baseLon, (float)$a['lat'], (float)$a['lon']);
        $distB = haversine($baseLat, $baseLon, (float)$b['lat'], (float)$b['lon']);
        return $distA <=> $distB;
    });

    foreach($normais as $rec) {
        $conn->query("UPDATE reclamacoes SET ordem_proximidade = $ordem WHERE id = ".(int)$rec['id']);
        $ordem++;
    }
}

echo json_encode(['success'=>true, 'msg'=>"Ordem atualizada com prioridade para vencidas", 'total'=>$ordem-1]);
?>
