<?php
// cadastro_usuario.php
include 'conexao.php';
include 'verifica_login.php';
$mensagem = "";

// Pegar ENUM do tipo_usuario
$enum_query = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'tipo_usuario'");
$enum_row = $enum_query->fetch_assoc();
$enum_str = $enum_row['Type']; // enum('admin','comum',...)
preg_match("/^enum\('(.*)'\)$/", $enum_str, $matches);
$tipos_usuario = explode("','", $matches[1]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome_usuario']);
    $telefone = trim($_POST['telefone_usuario']);
    $id_secretaria = intval($_POST['id_secretaria']);
    $id_setor = intval($_POST['id_setor']);
    $tipo_usuario = $_POST['tipo_usuario'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO usuarios (nome_usuario, telefone_usuario, id_secretaria, id_setor, tipo_usuario, senha) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisss", $nome, $telefone, $id_secretaria, $id_setor, $tipo_usuario, $senha);

    if ($stmt->execute()) {
        $mensagem = "<div class='alert alert-success mt-3'>Usuário cadastrado com sucesso!</div>";
    } else {
        $mensagem = "<div class='alert alert-danger mt-3'>Erro: " . $conn->error . "</div>";
    }
}

// Carregar secretarias e setores
$secretarias = $conn->query("SELECT id_secretaria, nome_secretaria FROM secretaria ORDER BY nome_secretaria");
$setores = $conn->query("SELECT id_setor, nome_setor FROM setor ORDER BY nome_setor");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon"  href="images/icone.ico">
    <title>Cadastro de Usuário</title>
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
                <li class="nav-item"><a class="nav-link active" href="cadastro_usuario.php"><i class="bi bi-person-plus"></i> User</a></li>
                <li class="nav-item"><a class="nav-link" href="cadastro_secretaria.php"><i class="bi bi-diagram-3"></i> Secretaria</a></li>
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
        <h2 class="mb-4">Cadastro de Usuário</h2>
        <?php if ($mensagem) echo $mensagem; ?>
    
        <form method="POST" class="form-section">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" name="nome_usuario" class="form-control" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="telefone_usuario" id="telefone_usuario" class="form-control" placeholder="(99) 99999-9999">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Secretaria</label>
                    <select name="id_secretaria" class="form-select" required>
                        <option value="">Selecione</option>
                        <?php while($s = $secretarias->fetch_assoc()): ?>
                            <option value="<?= $s['id_secretaria'] ?>"><?= $s['nome_secretaria'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Setor</label>
                    <select name="id_setor" class="form-select" required>
                        <option value="">Selecione</option>
                        <?php while($s = $setores->fetch_assoc()): ?>
                            <option value="<?= $s['id_setor'] ?>"><?= $s['nome_setor'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Tipo de Usuário</label>
                    <select name="tipo_usuario" class="form-select" required>
                        <option value="">Selecione</option>
                        <?php foreach($tipos_usuario as $tipo): ?>
                            <option value="<?= $tipo ?>"><?= ucfirst($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 position-relative">
                    <label class="form-label">Senha</label>
                    <input type="password" name="senha" id="senha" class="form-control" required>
                    <i class="bi bi-eye password-toggle" id="toggleSenha"></i>
                </div>
            </div>
    
            <!-- Botão Cadastrar em linha separada e centralizado -->
            <div class="row mt-4">
                <div class="col-12 col-sm-8 col-md-6 col-lg-4 mx-auto">
                    <button type="submit" class="btn btn-submit w-100">Cadastrar</button>
                </div>
            </div>
        </form>
    </main>


    <script>
        // Toggle mostrar/ocultar senha
        document.getElementById("toggleSenha").addEventListener("click", function() {
            const senhaInput = document.getElementById("senha");
            if (senhaInput.type === "password") {
                senhaInput.type = "text";
                this.classList.replace("bi-eye", "bi-eye-slash");
            } else {
                senhaInput.type = "password";
                this.classList.replace("bi-eye-slash", "bi-eye");
            }
        });

        // Máscara de telefone
        $(document).ready(function(){
            $('#telefone_usuario').mask('(00) 00000-0000');
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
