<?php
session_start();

// Pega o nome do arquivo atual
$pagina_atual = basename($_SERVER['PHP_SELF']);

// Define páginas que podem ser acessadas sem login
$paginas_sem_login = ['index.php', 'login.php'];

// Se a página exige login
if (!in_array($pagina_atual, $paginas_sem_login)) {
    if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        header("Location: login.php");
        exit;
    }

    // verifica tipo de usuário permitido
    $tipos_permitidos = ['admin', 'comum'];
    if (!in_array($_SESSION['tipo_usuario'], $tipos_permitidos)) {
        echo "Acesso negado. Você não tem permissão para ver esta página.";
        exit;
    }
}

// --- 🔹 Defina variáveis globais que a navbar vai usar ---
$logado = isset($_SESSION['logado']) && $_SESSION['logado'] === true;
$nome_usuario = $_SESSION['nome_usuario'] ?? '';
?>
