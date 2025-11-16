<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

include 'conexao.php';
include 'verifica_login.php';

/* ======================= Entrada ======================= */
$alterados = $_POST['alterados'] ?? [];
if (!is_array($alterados) || empty($alterados)) {
    echo json_encode(['success' => false, 'error' => 'Nenhum dado recebido'], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============== Util: remover acentos ================== */
function removerAcentos(string $str): string {
    $acentos    = ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç',
                   'Á','À','Ã','Â','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Õ','Ô','Ö','Ú','Ù','Û','Ü','Ç'];
    $semAcentos = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c',
                   'A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','C'];
    return str_replace($acentos, $semAcentos, $str);
}

/* ============== Validação de status ==================== */
$statusValidos = ['Pendente', 'Em Andamento', 'Concluido'];

/* ============== Fase 1: atualizar no banco ============= */
/* Atualiza apenas quando houver mudança de status          */
/* e detecta transição real para "Concluido" via affected_rows */
$upd = $conn->prepare("UPDATE reclamacoes SET status_reclamacao = ? WHERE id = ? AND status_reclamacao <> ?");
if (!$upd) {
    echo json_encode(['success' => false, 'error' => 'Falha ao preparar UPDATE: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Coletar IDs que viraram 'Concluido' AGORA (transição real) */
$idsConcluidos = [];

foreach ($alterados as $item) {
    $id_raw     = $item['id'] ?? null;
    $status_raw = $item['status'] ?? null;

    $id     = is_numeric($id_raw) ? (int)$id_raw : 0;
    $status = is_string($status_raw) ? trim($status_raw) : '';

    if ($id <= 0 || $status === '') continue;

    $statusSemAcento = removerAcentos($status);

    /* Normaliza capitalização para comparar exatamente com a whitelist */
    // Ex.: "concluído", "CONCLUIDO" -> "Concluido"
    $statusSemAcento = mb_convert_case($statusSemAcento, MB_CASE_TITLE, 'UTF-8');

    if (!in_array($statusSemAcento, $statusValidos, true)) continue;

    /* Atualiza só se for diferente do que já está no banco */
    $upd->bind_param("sis", $statusSemAcento, $id, $statusSemAcento);
    $upd->execute();

    /* Se realmente atualizou (houve mudança) e novo status é Concluido -> marca para envio */
    if ($upd->affected_rows > 0 && $statusSemAcento === 'Concluido') {
        $idsConcluidos[] = $id;
    }
}
$upd->close();

/* ============== Fase 2: buscar números e enviar ========= */
$sel = $conn->prepare("SELECT telefone FROM reclamacoes WHERE id = ?");
if (!$sel) {
    echo json_encode([
        'success' => true,
        'warning' => 'Status atualizados, mas falhou preparar SELECT de números: ' . $conn->error,
        'enviados' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* Função isolada para chamar enviar_whatsapp.php */
function postarParaEnviarWhatsapp(string $telefone, string $mensagem): array {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
    $url    = "{$scheme}://{$host}{$base}/enviar_whatsapp.php";

    $payload = json_encode([
        'numero'   => $telefone,
        'mensagem' => $mensagem
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $respBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'erro_curl' => $curlErr ?: null,
        'resposta'  => json_decode($respBody, true) ?: $respBody
    ];
}

$enviados = [];
if (!empty($idsConcluidos)) {
    foreach ($idsConcluidos as $id) {
        $sel->bind_param("i", $id);
        $sel->execute();
        $rs = $sel->get_result();

        if ($row = $rs->fetch_assoc()) {
            $telefone = (string)($row['telefone'] ?? '');
            $telefone = preg_replace('/\D+/', '', $telefone); // só dígitos

            if ($telefone !== '') {
                $mensagem = "Olá! Sua reclamação #{$id} foi *concluída*. Agradecemos o contato. Qualquer dúvida, fale conosco ✅";
                $ret = postarParaEnviarWhatsapp($telefone, $mensagem);

                $enviados[] = [
                    'id'        => $id,
                    'numero'    => $telefone,
                    'http_code' => $ret['http_code'],
                    'erro_curl' => $ret['erro_curl'],
                    'resposta'  => $ret['resposta'],
                ];
            } else {
                $enviados[] = ['id' => $id, 'erro' => 'Número vazio/ inválido na base'];
            }
        } else {
            $enviados[] = ['id' => $id, 'erro' => 'Registro não encontrado ao buscar número'];
        }
    }
}
$sel->close();

/* ============== Resposta final ========================= */
echo json_encode([
    'success'            => true,
    'atualizados'        => count($alterados),
    'concluidos'         => count($idsConcluidos),   // apenas transições reais para Concluido
    'envios_processados' => $enviados,               // log opcional
], JSON_UNESCAPED_UNICODE);
