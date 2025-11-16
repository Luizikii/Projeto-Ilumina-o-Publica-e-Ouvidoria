<?php
include 'verifica_login.php';
$logado = $_SESSION['logado'] ?? false; // true se usuário logado
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon"  href="images/icone.ico">
    <title>Ouvidoria - Página Inicial</title>
    
    
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- CSS personalizado -->
    <link rel="stylesheet" href="estilos.css">

    <style>
        .menu-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .menu-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
        }

        .menu-card:hover:not(.disabled) {
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        .menu-icon {
            font-size: 3rem;
            color: #0d6efd;
            margin-bottom: 1rem;
        }

        .menu-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .login-card {
            background: linear-gradient(135deg, #0d6efd, #20c997);
            color: white !important;
            font-weight: bold;
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            padding: 3rem;
        }

        .login-card .menu-icon {
            color: white;
            font-size: 4rem;
        }

        .menu-card.disabled {
            pointer-events: none;
            opacity: 0.6;
        }

        .menu-card.disabled .overlay-lock {
            position: absolute;
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%;
            background: rgba(0,0,0,0.25);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 5rem;
            color: rgba(220,53,69,0.7);
            pointer-events: none;
        }
    </style>
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
<div class="container text-center mt-4">
    <h2>Bem-vindo à Ouvidoria</h2>
</div>

<!-- Card Entrar -->
<?php if (!$logado): ?>
    <div class="d-flex justify-content-center mt-3 w-100">
        <a href="login.php" class="menu-card login-card text-decoration-none w-100" style="max-width: 400px;">
            <i class="bi bi-box-arrow-in-right menu-icon"></i>
            <div class="menu-title">Entrar</div>
        </a>
    </div>
<?php endif; ?>

<!-- Outros cards -->
<div class="menu-container">
    <?php
    $cards = [
        ['link'=>'cadastrar_requisicao.php','icon'=>'bi-plus-circle','title'=>'Cadastrar Requisições'],
        ['link'=>'visualizar_reclamacoes.php','icon'=>'bi-eye','title'=>'Visualizar Reclamações'],
        ['link'=>'visualizar_ordem_distancia.php','icon'=>'bi-signpost-split','title'=>'Visualizar Reclamações por Distância'],
        ['link'=>'mapa_pontos.php','icon'=>'bi-geo-alt-fill','title'=>'Ver Pontos no Mapa'],
        ['link'=>'cadastro_usuario.php','icon'=>'bi-person-plus','title'=>'Usuários'],
        ['link'=>'cadastro_secretaria.php','icon'=>'bi-diagram-3','title'=>'Secretarias'],
        ['link'=>'cadastro_setor.php','icon'=>'bi-geo-alt','title'=>'Setores'],
        ['link'=>'cadastrar_tipo_requisicao.php','icon'=>'bi-card-list','title'=>'Tipos de Requisição']
    ];

    foreach($cards as $c):
        $disabled = !$logado ? 'disabled' : '';
    ?>
        <a href="<?= $c['link'] ?>" class="menu-card text-decoration-none text-dark <?= $disabled ?>">
            <i class="bi <?= $c['icon'] ?> menu-icon"></i>
            <div class="menu-title"><?= $c['title'] ?></div>
            <?php if (!$logado): ?>
                <div class="overlay-lock"><i class="bi bi-lock-fill"></i></div>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
