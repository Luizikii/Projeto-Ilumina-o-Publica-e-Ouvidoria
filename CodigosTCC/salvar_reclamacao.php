<?php
include 'conexao.php';
include 'verifica_login.php';

// Inicializa variáveis de mensagem
$mensagem = '';
$mensagem_tipo = ''; // 'success' ou 'danger'

// Consulta os tipos de requisição do banco
$sql = "SELECT id, nome FROM tipos_requisicao ORDER BY nome";
$result = $conn->query($sql);

$tipos = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tipos[] = $row;
    }
}

// Processa o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $tipo_requisicao = $_POST['tipo_requisicao'] ?? '';
    $telefone = $_POST['telefone'] ?? '';
    $endereco = $_POST['endereco'] ?? '';
    $numero_endereco = $_POST['numero_endereco'] ?? '';
    $lat = $_POST['lat'] ?? null;
    $lon = $_POST['lon'] ?? null;
    $data_hora = $_POST['data_hora'] ?? '';
    $secretaria = $_POST['secretaria'] ?? '';

    // Verifica se latitude e longitude foram fornecidas
    if ($lat === null || $lon === null) {
        $mensagem = "Erro: Latitude e longitude não fornecidas.";
        $mensagem_tipo = "danger";
    } else {
        // Insere a reclamação
        $stmt = $conn->prepare(
            "INSERT INTO reclamacoes (nome, tipo_requisicao, telefone, endereco, numero_endereco, lat, lon, data_hora, secretaria) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if ($stmt) {
            $stmt->bind_param(
                "sisssddss",
                $nome,
                $tipo_requisicao,
                $telefone,
                $endereco,
                $numero_endereco,
                $lat,
                $lon,
                $data_hora,
                $secretaria
            );

            if ($stmt->execute()) {
                $mensagem = "Reclamação enviada com sucesso!";
                $mensagem_tipo = "success";
            } else {
                $mensagem = "Erro ao enviar a reclamação: " . $stmt->error;
                $mensagem_tipo = "danger";
            }

            $stmt->close();
        } else {
            $mensagem = "Erro ao preparar a requisição: " . $conn->error;
            $mensagem_tipo = "danger";
        }
    }
}
?>
