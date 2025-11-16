<?php
session_start();
unset($_SESSION['cpf_usuario']);
unset($_SESSION['logado_cidadao']);
session_destroy();
header("Location: login_usuario.php");
exit;
