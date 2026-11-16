<?php
// ============================================
// Configurações Gerais do Projeto
// ============================================

// Define o caminho absoluto para a raiz do projeto (se ainda não definido)
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// ============================================
// Configurações para o Acesso ao Banco de Dados
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'pc_gamer');
define('DB_USER', 'root');
define('DB_PASS', 'aldo');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// Configuração do Site
// ============================================
define('SITE_URL', 'http://loja.local/');
define('SITE_NAME', 'GamerStore - Loja Gamer');
define('SITE_EMAIL', 'aldosilveira@gmail.com');
define('ADMIN_EMAIL', 'aldosilveira@gmail.com');

// ============================================
// Segurança
// ============================================
define('SESSION_TIMEOUT', 1800);
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_COST', 10);

// ============================================
// Configuração do E-Commerce
// ============================================
define('SHIPPING_BASE_PRICE', 29.90); // Base para cálculo do Frete
define('SHIPPING_FREE_MIN', 299.90); // Valor Mínimo para Frete grátis
define('MAX_INSTALLMENTS', 12); // Número das parcelas do Cartão
define('TAX_RATE', 0.18); // Imposto

// ============================================
// Para Uploads de Arquivos
// ============================================
define('UPLOAD_PATH', ABSPATH . 'assets/images/produtos/'); // Usando ABSPATH
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // Tamanho da Imagem 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);

// ============================================
// Data e Hora
// ============================================
date_default_timezone_set('America/Sao_Paulo');

// ============================================
// Para o Debug dos erros
// ============================================
define('DEBUG_MODE', true); // Defina como false em produção!
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Inicia a sessão caso não esteja ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funções auxiliares para carregar configurações de ambiente .env
// Esta função e a chamada loadEnv() podem ficar aqui, pois são de configuração
function loadEnv($file = '.env') {
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name,$_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
// Efetua o carregamento do Arquivo .env se existir
loadEnv(ABSPATH . '.env'); // Usando ABSPATH
