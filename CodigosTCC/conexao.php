<?php
/**
 * conexao.php — conexão otimizada com MySQL
 * - Usa host 'p:localhost' (persistente) para reduzir conexões/hora
 * - Evita abrir conexões duplicadas no mesmo request
 * - Fecha automaticamente no fim do script
 */

// Evita abrir conexão se já existir
if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
    return;
}

// Credenciais
$host    = 'localhost'; // 'p:' ativa conexão persistente
$usuario = 'u740442472_administrador2';
$senha   = '!Srh153245';
$banco   = 'u740442472_ouvidoria';

// Conecta
$conn = new mysqli($host, $usuario, $senha, $banco);

// Erro de conexão
if ($conn->connect_error) {
    die("Erro na conexão com o banco de dados.");
}

// Charset para suportar acentos e emojis
if (!$conn->set_charset("utf8mb4")) {
    die("Erro ao definir charset.");
}

// Guarda conexão globalmente para reuso
$GLOBALS['conn'] = $conn;

// Fecha a conexão automaticamente no fim do script
register_shutdown_function(function () {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        @$GLOBALS['conn']->close();
    }
});
