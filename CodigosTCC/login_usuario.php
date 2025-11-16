<?php
// login_usuario.php
session_start();
include 'conexao.php';

$mensagem = "";

// Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf); // só números

    if (strlen($cpf) != 11) return false;
    if (preg_match('/(\d)\1{10}/', $cpf)) return false; // rejeita sequências iguais

    $soma = 0;
    for ($i = 0, $peso = 10; $i < 9; $i++, $peso--) $soma += $cpf[$i] * $peso;
    $digito1 = (($soma * 10) % 11) == 10 ? 0 : (($soma * 10) % 11);

    $soma = 0;
    for ($i = 0, $peso = 11; $i < 10; $i++, $peso--) $soma += $cpf[$i] * $peso;
    $digito2 = (($soma * 10) % 11) == 10 ? 0 : (($soma * 10) % 11);

    return ($cpf[9] == $digito1 && $cpf[10] == $digito2);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf']); // mantém só números

    if (!empty($cpf) && validarCPF($cpf)) {
        // Verifica se existe ao menos uma requisição com esse CPF
        // Normalizando também o CPF do banco (remove pontos e traço)
        $sql = "
            SELECT 1 
            FROM reclamacoes 
            WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = ? 
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            // Guarda só os números na sessão
            $_SESSION['cpf_usuario'] = $cpf;
            $_SESSION['logado_cidadao'] = true;

            header("Location: acompanhar_requisicao.php");
            exit;
        } else {
            $mensagem = "<div class='alert alert-danger'>CPF não encontrado no sistema.</div>";
        }
    } else {
        $mensagem = "<div class='alert alert-warning'>Digite um CPF válido.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <link rel="icon"  href="images/icone.ico">
    <title>Login Cidadão - Ouvidoria de Nepomuceno</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-building"></i> Ouvidoria de Nepomuceno
        </a>
    </div>
</nav>

<!-- Formulário de Login -->
<main class="d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="login-box">
        <h3 class="text-center mb-3">Login Cidadão</h3>
        <?php if ($mensagem) echo $mensagem; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">CPF</label>
                <input type="text" id="cpf" name="cpf" class="form-control" maxlength="14" placeholder="000.000.000-00" required>
            </div>
            <button type="submit" class="btn btn-submit w-100">Entrar</button>
        </form>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(document).ready(function(){
    $('#cpf').mask('000.000.000-00'); // aplica máscara sempre
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
