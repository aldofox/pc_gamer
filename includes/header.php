<?php
// header.php

// Configurações de erro (bom para desenvolvimento, remova ou ajuste em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define ABSPATH e carrega os arquivos necessários apenas uma vez
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

// Garante que a sessão está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ====================================================================
// NOVO: Carrega as configurações do site do banco de dados
// ====================================================================
$db = db();
$allSettings = $db->fetchAll("SELECT chave, valor FROM settings");
$siteSettings = []; // Usaremos $siteSettings para evitar conflito com $settings da página admin
foreach ($allSettings as $row) {
    $siteSettings[$row['chave']] = $row['valor'];
}

// Define variáveis globais ou as torna acessíveis para uso em outras partes
// Você pode usar $GLOBALS['siteSettings'] = $siteSettings;
// Ou, como estamos incluindo o header, $siteSettings estará disponível
// nas páginas que o incluem e no footer.

// Valores padrão se não estiverem no banco
$site_name_from_db        = htmlspecialchars($siteSettings['site_name']        ?? 'GamerStore');
$site_slogan_from_db      = htmlspecialchars($siteSettings['site_slogan']      ?? 'Sua jornada gamer começa aqui!');
$contact_email_from_db    = htmlspecialchars($siteSettings['contact_email']    ?? 'contato@gamerstore.com');
$contact_phone_from_db    = htmlspecialchars($siteSettings['contact_phone']    ?? '(11) 98765-4321');
$site_address_from_db     = htmlspecialchars($siteSettings['site_address']     ?? 'Rua Gamer, 123 - Cidade Gamer, SP');
$social_instagram_from_db = htmlspecialchars($siteSettings['social_instagram'] ?? '#');
$social_facebook_from_db  = htmlspecialchars($siteSettings['social_facebook']  ?? '#');
$site_logo_path_from_db   = htmlspecialchars($siteSettings['site_logo_path']   ?? 'assets/images/logo/logo.png');
$site_favicon_path_from_db= htmlspecialchars($siteSettings['site_favicon_path']?? 'assets/images/favicon.ico'); // Assumindo um favicon padrão

// O $pageTitle é geralmente definido na página específica que inclui o header
// Se não for definido, usa um padrão.
$current_page_title = isset($pageTitle) ? htmlspecialchars($pageTitle) : $site_name_from_db . ' - ' . $site_slogan_from_db;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $current_page_title ?></title>
    <link rel="icon" href="<?= getSiteUrl($site_favicon_path_from_db) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= getSiteUrl('assets/css/style.css') . '?v=' . filemtime(ABSPATH . 'assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
</head>
<body>

    <?php
    // Exibe mensagens de sucesso ou erro da sessão
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success container">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
        unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger container">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <header class="header">
        <div class="container">
            <!-- TOPO DO HEADER: LOGO + MENU + USER ACTIONS -->
            <div class="header-top">

                <!-- Logo -->
                <div class="logo">
                    <a href="<?= getSiteUrl('index.php'); ?>">
                        <img src="<?= getSiteUrl($site_logo_path_from_db); ?>" alt="<?= $site_name_from_db ?>">
                    </a>
                </div>

                <!-- Menu -->
                <nav class="main-nav">
                    <ul class="menu">
                        <li><a href="<?= getSiteUrl('index.php'); ?>"><i class="fas fa-home"></i> Inicio</a></li>
                        <li><a href="<?= getSiteUrl('pages/categoria?cat=pcs'); ?>"><i class="fas fa-desktop"></i> PCs</a></li>
                        <li><a href="<?= getSiteUrl('pages/categoria?cat=notebooks'); ?>"><i class="fas fa-laptop"></i> Notebooks</a></li>
                        <li><a href="<?= getSiteUrl('pages/categoria?cat=perifericos'); ?>"><i class="fas fa-keyboard"></i> Periféricos</a></li>
                        <li><a href="<?= getSiteUrl('pages/categoria?cat=placas_video'); ?>"><i class="fas fa-microchip"></i> Placas de Vídeo</a></li>
                        <li><a href="<?= getSiteUrl('pages/categoria?cat=monitores'); ?>"><i class="fas fa-tv"></i> Monitores</a></li>
                    </ul>
                </nav>


            <!-- User Actions -->
<div class="user-actions">
    <?php if (isset($_SESSION['user_id'])): ?>

        <!-- Dropdown do usuário logado -->
        <div class="user-dropdown">
            <button class="user-dropdown-toggle user-link">
                <i class="fas fa-user-circle"></i>
                <span>Olá, <?= htmlspecialchars($_SESSION['username']); ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </button>
            <div class="user-dropdown-menu">
                <a href="<?= getSiteUrl('pages/my_account.php'); ?>">
                    <i class="fas fa-id-card"></i> Minha Conta
                </a>
                <a href="<?= getSiteUrl('pages/my_orders.php'); ?>">
                    <i class="fas fa-box"></i> Meus Pedidos
                </a>
                <div class="dropdown-divider"></div>
                <a href="<?= getSiteUrl('pages/logout.php'); ?>" class="dropdown-logout">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>

    <?php else: ?>
        <a href="<?= getSiteUrl('pages/login'); ?>" class="user-link">
            <i class="fas fa-sign-in-alt"></i>
            <span>Entrar</span>
        </a>
    <?php endif; ?>

    <a href="<?= getSiteUrl('pages/carrinho.php'); ?>" class="cart-link">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-count" id="cartCount">
            <?= getCartItemCount(); ?>
        </span>
    </a>
</div>
            </div>

            <!-- BARRA DE BUSCA -->
            <div class="search-bar">
                <form action="<?= getSiteUrl('pages/busca.php'); ?>" method="GET" class="search-form">
                    <input type="text" name="q" placeholder="Buscar Produtos..." class="search-input" required>
                    <button class="search-btn" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

        </div>
    </header>
    <script>
// Dropdown do usuário
(function() {
    var dropdown = document.querySelector('.user-dropdown');
    if (!dropdown) return;

    var toggle = dropdown.querySelector('.user-dropdown-toggle');

    // Abre/fecha ao clicar no botão
    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });

    // Fecha ao clicar fora
    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    // Fecha ao pressionar ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdown.classList.remove('open');
        }
    });
})();
</script>