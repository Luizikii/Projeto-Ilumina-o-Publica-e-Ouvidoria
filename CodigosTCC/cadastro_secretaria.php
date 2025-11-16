<?php
include 'conexao.php';
include 'verifica_login.php';
$mensagem = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome']);
    $responsavel = trim($_POST['nome_responsavel_secretaria']);
    $telefone = trim($_POST['telefone']);
    $endereco = trim($_POST['endereco']);

    $sql = "INSERT INTO secretaria (nome_secretaria, nome_responsavel_secretaria, telefone_secretaria, endereco_secretaria) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $nome, $responsavel, $telefone, $endereco);

    if ($stmt->execute()) {
        $mensagem = "<div class='alert alert-success mt-3'>Secretaria cadastrada com sucesso!</div>";
    } else {
        $mensagem = "<div class='alert alert-danger mt-3'>Erro: " . $conn->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon"  href="images/icone.ico">
    <title>Cadastro de Secretaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <!-- Logo -->
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-building"></i> Ouvidoria
        </a>

        <!-- Botão hamburguer -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="cadastro_usuario.php"><i class="bi bi-person-plus"></i> User</a></li>
                <li class="nav-item"><a class="nav-link active" href="cadastro_secretaria.php"><i class="bi bi-diagram-3"></i> Secretaria</a></li>
                <li class="nav-item"><a class="nav-link" href="cadastro_setor.php"><i class="bi bi-geo-alt"></i> Setor</a></li>
                <li class="nav-item"><a class="nav-link" href="cadastrar_tipo_requisicao.php"><i class="bi bi-card-list"></i> Tipo Requisição</a></li>
                <li class="nav-item"><a class="nav-link" href="visualizar_reclamacoes.php"><i class="bi bi-eye"></i> Ver Requisição</a></li>
                <li class="nav-item"><a class="nav-link" href="cadastrar_requisicao.php"><i class="bi bi-plus-circle"></i> Cad. Requisições</a></li>
                <li class="nav-item"><a class="nav-link" href="mapa_pontos.php"><i class="bi bi-map"></i> Mapa de Reclamações</a></li>
                <li class="nav-item"><a class="nav-link" href="visualizar_ordem_distancia.php"><i class="bi bi-map"></i> Ver por Distância</a></li>
            </ul>

            <!-- Usuário com dropdown -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <?php echo $logado ? htmlspecialchars($nome_usuario) : 'Usuário'; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if (!$logado): ?>
                            <li><a class="dropdown-item" href="login.php">Entrar</a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="logout.php">Sair</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>


<!-- Conteúdo -->
<main class="container-fluid">
    <h2 class="mb-4">Cadastro de Secretaria</h2>
    <?php if ($mensagem) echo $mensagem; ?>

    <form method="POST" class="form-section">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Nome da Secretaria</label>
                <input type="text" name="nome" class="form-control" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Responsável</label>
                <input type="text" name="nome_responsavel_secretaria" class="form-control" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" id="telefone" class="form-control">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Endereço</label>
                <input type="text" name="endereco" class="form-control">
            </div>
        </div>

        <!-- Botão Cadastrar em linha separada -->
        <div class="row mt-3">
            <div class="col-12 col-sm-8 col-md-6 col-lg-4 mx-auto">
                <button type="submit" class="btn btn-submit w-100">Cadastrar</button>
            </div>
        </div>
    </form>
</main>


<script>
$(document).ready(function(){
    $('#telefone').mask('(00) 00000-0000');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
