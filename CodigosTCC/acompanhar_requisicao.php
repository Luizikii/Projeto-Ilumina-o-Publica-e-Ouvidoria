<?php
session_start();
include 'conexao.php';

// Verifica se o CPF está salvo na sessão (definido no login)
if (!isset($_SESSION['cpf_usuario'])) {
    header("Location: login_usuario.php");
    exit;
}

// Normaliza CPF da sessão (remove tudo que não é dígito)
$cpf = preg_replace('/\D/', '', $_SESSION['cpf_usuario']);

// Busca todas as requisições do CPF logado (sem secretaria)
// Normaliza o campo CPF do banco removendo '.' e '-' para comparação
$sql = "SELECT r.*, t.nome AS tipo_nome
        FROM reclamacoes r
        LEFT JOIN tipos_requisicao t ON r.tipo_requisicao = t.id
        WHERE REPLACE(REPLACE(r.cpf, '.', ''), '-', '') = ?
        ORDER BY r.data_hora DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $cpf);
$stmt->execute();
$result = $stmt->get_result();

// Função para calcular tempo decorrido
function tempoDecorrido($dataHora) {
    $tz = new DateTimeZone('America/Sao_Paulo');

    $dataReq = new DateTime($dataHora, $tz);
    $agora   = new DateTime('now', $tz);

    $diff = $agora->diff($dataReq);

    if ($diff->y > 0) {
        return $diff->y . " ano(s)";
    } elseif ($diff->m > 0) {
        return $diff->m . " mês(es)";
    } elseif ($diff->d > 0) {
        return $diff->d . " dia(s)";
    } elseif ($diff->h > 0) {
        return $diff->h . " hora(s)";
    } elseif ($diff->i > 0) {
        return $diff->i . " minuto(s)";
    } else {
        return "agora mesmo";
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<link rel="icon"  href="images/icone.ico">
<title>Minhas Requisições</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="estilos.css">
</head>
<body class="bg-light">
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-building"></i> Ouvidoria de Nepomuceno
        </a>

        <!-- Usuário com dropdown -->
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i>
                    <?php 
                    if (isset($_SESSION['logado_cidadao']) && $_SESSION['logado_cidadao'] === true) {
                        echo "CPF: " . htmlspecialchars($_SESSION['cpf_usuario']);
                    } else {
                        echo "Cidadão";
                    }
                    ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (!isset($_SESSION['logado_cidadao']) || $_SESSION['logado_cidadao'] !== true): ?>
                        <li><a class="dropdown-item" href="login_usuario.php">Entrar</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="logout_usuario.php">Sair</a></li>
                    <?php endif; ?>
                </ul>
            </li>
        </ul>
    </div>
</nav>

<div class="container mt-5">
    <h2 class="text-center mb-4">Minhas Requisições</h2>

    <table class="table table-bordered table-striped bg-white">
        <thead>
            <tr>
                <th>Data Req.</th>
                <th>Tempo Aberta</th>
                <th>Tipo</th>
                <th>Endereço</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    // Remove números do endereço
                    $enderecoSemNumero = preg_replace('/\d+/', '', $row['endereco']);
                    
                    // Remove vírgulas e espaços extras no final
                    $enderecoLimpo = rtrim(trim($enderecoSemNumero), ',');
                    
                    // Monta o endereço final
                    $enderecoFinal = $enderecoLimpo;
                    if (!empty($row['numero_endereco'])) {
                        $enderecoFinal .= ', ' . $row['numero_endereco'];
                    }
                    ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($row['data_hora'])) ?></td>
                        <td><?= tempoDecorrido($row['data_hora']) ?></td>
                        <td><?= htmlspecialchars($row['tipo_nome'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($enderecoFinal) ?></td>
                        <td><?= htmlspecialchars($row['status_reclamacao'] ?? 'Pendente') ?></td>
                    </tr>

            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5" class="text-center">Nenhuma requisição encontrada</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="text-center mt-3">
        <a href="logout_usuario.php" class="btn btn-danger">Sair</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
