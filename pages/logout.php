<?php
session_start();

// Destrói todas as variáveis de sessão
$_SESSION = [];

// Destrói a sessão
session_destroy();

// Redireciona para a home
header('Location: ../index.php');
exit;
