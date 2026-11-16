<?php
// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/functions.php'; // Para getSiteUrl()
require_once ABSPATH . 'admin/includes/admin_functions.php'; // Para isUserAdminLoggedIn()

// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Se o admin já estiver logado, redireciona para o dashboard
if (isUserAdminLoggedIn()) { // Usamos a função que vamos criar/ajustar
    redirect(getSiteUrl('admin/dashboard.php'));
} else {
    // Caso contrário, redireciona para a página de login do admin
    redirect(getSiteUrl('admin/login.php'));
}
exit; // Garante que o script para após o redirecionamento
?>