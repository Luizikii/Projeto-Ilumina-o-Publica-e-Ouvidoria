<?php
session_start();
include 'conexao.php';

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario']);
    $senha   = trim($_POST['senha']);

    $sql  = "SELECT id_usuario, nome_usuario, senha, tipo_usuario FROM usuarios WHERE nome_usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($u = $result->fetch_assoc()) {
        if (password_verify($senha, $u['senha'])) {
            $_SESSION['id_usuario']   = $u['id_usuario'];
            $_SESSION['nome_usuario'] = $u['nome_usuario'];
            $_SESSION['tipo_usuario'] = $u['tipo_usuario'];
            $_SESSION['logado']       = true;
            header("Location: index.php"); exit;
        } else {
            $mensagem = "<div class='alert alert-danger text-center'>Senha incorreta.</div>";
        }
    } else {
        $mensagem = "<div class='alert alert-danger text-center'>Usuário não encontrado.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="images/icone.ico">
  <title>Login - Ouvidoria</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body
  class="d-flex flex-column min-vh-100"
  style="
    /* Camada 1: película translúcida (ajuste o .82 à vontade) */
    background:
      linear-gradient(rgba(255,255,255,.82), rgba(255,255,255,.82)),
      url('3f1e6a98-b5a0-4b38-a7ed-39ddcb39095e.png') center center / cover no-repeat fixed;
    background-color:#f8f9fa;
  "
>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="index.php">
      <i class="bi bi-building"></i> Ouvidoria
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="cadastro_usuario.php"><i class="bi bi-person-plus"></i> Usuário</a></li>
        <li class="nav-item"><a class="nav-link" href="cadastro_secretaria.php"><i class="bi bi-diagram-3"></i> Secretaria</a></li>
        <li class="nav-item"><a class="nav-link" href="cadastro_setor.php"><i class="bi bi-geo-alt"></i> Setor</a></li>
        <li class="nav-item"><a class="nav-link" href="cadastrar_tipo_requisicao.php"><i class="bi bi-card-list"></i> Tipo Requisição</a></li>
        <li class="nav-item"><a class="nav-link" href="visualizar_reclamacoes.php"><i class="bi bi-eye"></i> Ver Requisições</a></li>
        <li class="nav-item"><a class="nav-link" href="cadastrar_requisicao.php"><i class="bi bi-plus-circle"></i> Cadastrar Requisição</a></li>
        <li class="nav-item"><a class="nav-link" href="mapa_pontos.php"><i class="bi bi-map"></i> Mapa</a></li>
        <li class="nav-item"><a class="nav-link" href="visualizar_ordem_distancia.php"><i class="bi bi-pin-map"></i> Por Distância</a></li>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link active" href="login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Formulário centralizado -->
<main class="flex-grow-1 d-flex justify-content-center align-items-center">
  <div class="card shadow-lg border-0 p-4"
       style="max-width:380px;width:100%;background-color:rgba(33,37,41,.92);">
    <h3 class="text-center text-white mb-4">Login</h3>
    <?php if ($mensagem) echo $mensagem; ?>
    <form method="POST" class="text-white">
      <div class="mb-3">
        <label for="usuario" class="form-label">Usuário</label>
        <input type="text" name="usuario" id="usuario" class="form-control bg-light border-0" required>
      </div>
      <div class="mb-3">
        <label for="senha" class="form-label">Senha</label>
        <input type="password" name="senha" id="senha" class="form-control bg-light border-0" required>
      </div>
      <button type="submit" class="btn w-100 text-dark fw-semibold"
              style="background:linear-gradient(to right,#57c3a3,#53b8d3);">
        Entrar
      </button>
    </form>
  </div>
</main>

<!-- Rodapé -->
<footer class="bg-dark text-white text-center py-2 mt-auto small">
  © Prefeitura Municipal de Nepomuceno — Gestão 2025-2028
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
