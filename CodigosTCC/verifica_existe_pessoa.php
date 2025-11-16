<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexao.php';

// Força timezone para Brasília
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: application/json; charset=UTF-8');

if (!isset($conn) || !$conn) {
    echo json_encode(["code" => "erro_conexao", "msg" => $conn->connect_error], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Funções auxiliares --- //
function validarCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false; // rejeita repetições

    // Calcula 1º dígito verificador
    $soma = 0;
    for ($i = 0, $peso = 10; $i < 9; $i++, $peso--) $soma += $cpf[$i] * $peso;
    $digito1 = ($soma * 10) % 11;
    $digito1 = ($digito1 == 10 || $digito1 == 11) ? 0 : $digito1;

    // Calcula 2º dígito verificador
    $soma = 0;
    for ($i = 0, $peso = 11; $i < 10; $i++, $peso--) $soma += $cpf[$i] * $peso;
    $digito2 = ($soma * 10) % 11;
    $digito2 = ($digito2 == 10 || $digito2 == 11) ? 0 : $digito2;

    return ($cpf[9] == $digito1 && $cpf[10] == $digito2);
}

function formatarTelefone($numero) {
    $numero = preg_replace('/\D/', '', $numero);
    if (strlen($numero) === 10) {
        return sprintf("(%s) %s-%s", substr($numero,0,2), substr($numero,2,4), substr($numero,6));
    } elseif (strlen($numero) === 11) {
        return sprintf("(%s) %s-%s", substr($numero,0,2), substr($numero,2,5), substr($numero,7));
    }
    return $numero;
}

// --- Normaliza telefone: remove somente prefixo 55 --- //
function normalizarTelefone($numero) {
    $numero = preg_replace('/\D/', '', $numero); // só números
    if (substr($numero, 0, 2) === '55') {
        $numero = substr($numero, 2); // remove só o 55 do início
    }
    return $numero;
}

// --- Normaliza CPF: deixa só números --- //
function normalizarCPF($cpf) {
    return preg_replace('/\D/', '', $cpf);
}

function aplicarNonoDigitoSeFaltar($tel) {
    // Se tiver 10 dígitos (fixo/antigo), injeta nono dígito após o DDD
    if (strlen($tel) === 10) {
        return substr($tel, 0, 2) . '9' . substr($tel, 2);
    }
    return $tel;
}

// --- Função auxiliar: consulta verifica_lat_lon.php --- //
function obterLatLon($endereco, $numero) {
    $url = "https://ti-ouvidoria.nepomuceno.mg.gov.br/verifica_lat_lon.php";

    $payload = json_encode([
        "endereco" => $endereco,
        "numero"   => $numero
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// --- Lê JSON recebido --- //
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
if (!$input) {
    echo json_encode(["code" => "json_invalido", "msg" => "JSON inválido"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------
// ENTRADAS DO PAYLOAD
// ------------------------------
$respostaBruta     = trim($input['#resposta_sim'] ?? '');           // confirmação do telefone
$telefoneInput     = $input['numero_telefone'] ?? $input['#numero_telefone'] ?? '';
$remetenteRaw      = $input['menuia-numero-remetente'] ?? '';       // fallback do bot (ex.: 5535...)
$cpf               = normalizarCPF($input['#CPF'] ?? '');           // CPF sempre normalizado
$nome              = $input['#nome_completo'] ?? '';
$endereco          = $input['#endereco'] ?? '';
$numero_endereco   = $input['#numero_endereco'] ?? '';
$acao              = $input['#resposta_escolha'] ?? '';

// ------------------------------
// REGRAS DE CLASSIFICAÇÃO (pedido do usuário)
// Só é POSITIVO se for 1, sim ou Sim; qualquer outro valor é NEGATIVO
// ------------------------------
$ehPositiva = ($respostaBruta === '1' || $respostaBruta === 'sim' || $respostaBruta === 'Sim');

// ------------------------------
// Use #numero_telefone; se não vier, use menuia-numero-remetente.
// ------------------------------
$telefoneLimpo = '';
$telefoneFormatado = '';

if (!empty($telefoneInput)) {
    $telefoneLimpo = normalizarTelefone($telefoneInput);
} elseif (!empty($remetenteRaw)) {
    // fallback quando o bot não reenviou numero_telefone
    $telefoneLimpo = normalizarTelefone($remetenteRaw);
}

if (!empty($telefoneLimpo)) {
    $telefoneLimpo = aplicarNonoDigitoSeFaltar($telefoneLimpo);
    $telefoneFormatado = formatarTelefone($telefoneLimpo);
}

// ------------------------------
// FLUXOS
// ------------------------------

// 1) Caso: recebeu telefone, mas ainda não tem CPF/nome/endereço/número_endereco
//    -> verificar se já existe no banco (retorna 1=disponível/novo, 0=já existe), mantendo seu padrão atual
if (!empty($telefoneLimpo) && empty($cpf) && empty($nome) && empty($endereco) && empty($numero_endereco)) {
    $sql = "SELECT telefone FROM reclamacoes";
    $res = $conn->query($sql);

    $existe = false;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $telBanco = normalizarTelefone($row['telefone']);
            if ($telBanco === $telefoneLimpo) {
                $existe = true;
                break;
            }
        }
    }

    echo json_encode($existe ? 0 : 1, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) Caso: recebeu CPF para validar (independente do telefone)
//    Seu bot espera 0=VÁLIDO e 1=INVÁLIDO
if (!empty($cpf) && empty($nome) && empty($endereco) && empty($numero_endereco)) {
    $ehValido = validarCPF($cpf);
    echo $ehValido ? "0" : "1";
    exit;
}

// 3) Caso: recebeu nome (confirma etapa)
if (!empty($telefoneLimpo) && !empty($cpf) && !empty($nome) && empty($endereco) && empty($numero_endereco)) {
    echo json_encode("Nome recebido com sucesso", JSON_UNESCAPED_UNICODE);
    exit;
}

// 4) Caso: fazer nova requisição (ação == "2") com endereço e número
if ($acao === "2" && !empty($endereco) && !empty($numero_endereco)) {

    // Se o usuário NÃO confirmou telefone positivamente e não vieram nome/cpf, tenta buscar do último registro do mesmo telefone
    if (!$ehPositiva && (empty($nome) || empty($cpf)) && !empty($telefoneLimpo)) {
        $stmt = $conn->prepare("
            SELECT nome, cpf 
            FROM reclamacoes 
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ','') = ?
            ORDER BY id DESC LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param("s", $telefoneLimpo);
            $stmt->execute();
            $stmt->bind_result($dbNome, $dbCpf);
            if ($stmt->fetch()) {
                $nome = $dbNome ?: $nome;
                $cpf  = normalizarCPF($dbCpf) ?: $cpf;
            }
            $stmt->close();
        }
    }

    // Garante que teremos telefone formatado antes de salvar
    if (empty($telefoneFormatado) && !empty($telefoneLimpo)) {
        $telefoneFormatado = formatarTelefone($telefoneLimpo);
    }

    if (empty($telefoneFormatado)) {
        echo json_encode(["code" => "telefone_ausente", "msg" => "Telefone não identificado para cadastro"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Antes de salvar → verifica lat/lon
    $coords = obterLatLon($endereco, $numero_endereco);
    if (!$coords || empty($coords['success'])) {
        echo json_encode(["code" => "1", "msg" => "Erro ao buscar coordenadas"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lat = (float)$coords['lat'];
    $lon = (float)$coords['lon'];

    // Data/hora de Brasília
    $dataHora = date('Y-m-d H:i:s');

    // Faz o insert com lat/lon
    $stmt = $conn->prepare("
        INSERT INTO reclamacoes 
        (nome, telefone, cpf, endereco, numero_endereco, lat, lon, tipo_requisicao, id_secretaria, status_reclamacao, data_hora)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 'Pendente', ?)
    ");
    if ($stmt) {
        $stmt->bind_param("ssssddds", $nome, $telefoneFormatado, $cpf, $endereco, $numero_endereco, $lat, $lon, $dataHora);
        $ok = $stmt->execute();
        if ($ok) {
            echo json_encode("0", JSON_UNESCAPED_UNICODE); // sucesso
        } else {
            echo json_encode(["code" => "2", "msg" => $stmt->error], JSON_UNESCAPED_UNICODE); // erro no execute
        }
        $stmt->close();
    } else {
        echo json_encode(["code" => "2", "msg" => $conn->error], JSON_UNESCAPED_UNICODE); // erro ao preparar
    }
    exit;
}

// Fluxo não coberto
echo json_encode(["code" => "fluxo_invalido", "msg" => "Fluxo inválido ou dados insuficientes"], JSON_UNESCAPED_UNICODE);
?>
