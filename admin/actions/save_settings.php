<?php
// admin/actions/save_settings.php

session_start(); // ESSENCIAL: estava faltando aqui, por isso $_SESSION['message'] não funcionava

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__DIR__)) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';
require_once ABSPATH . 'admin/includes/admin_functions.php';

/**
 * Sanitiza entrada de texto simples.
 * IMPORTANTE: NÃO aplicar htmlspecialchars aqui.
 * Isso deve ser feito apenas na exibição (no HTML).
 */
function sanitize_input(string $data): string {
    return trim(stripslashes($data));
}

/**
 * Salva ou atualiza uma configuração na tabela settings.
 */
function saveSetting(string $key, string $value): void {
    $db = db();
    $existing = $db->fetchOne(
        "SELECT chave FROM settings WHERE chave = :chave",
        ['chave' => $key]
    );

    if ($existing) {
        $db->execute(
            "UPDATE settings SET valor = :valor WHERE chave = :chave",
            ['valor' => $value, 'chave' => $key]
        );
    } else {
        $db->execute(
            "INSERT INTO settings (chave, valor) VALUES (:chave, :valor)",
            ['chave' => $key, 'valor' => $value]
        );
    }
}

/**
 * Faz o upload de uma imagem e retorna o caminho relativo salvo.
 * Retorna null se não houver arquivo ou ocorrer erro.
 */
function handleImageUpload(string $fieldName, string $prefix, string $uploadDir): ?string {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file      = $_FILES[$fieldName];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Apenas extensões permitidas
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'webp'];
    if (!in_array($extension, $allowed)) {
        throw new Exception("Formato de arquivo não permitido para '$fieldName'. Use: " . implode(', ', $allowed));
    }

    // Garante que a pasta de upload existe
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = $prefix . '_' . uniqid() . '.' . $extension;
    $fullPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        throw new Exception("Falha ao mover o arquivo '$fieldName' para o destino.");
    }

    return 'uploads/' . $fileName; // Caminho relativo salvo no banco
}

// Só processa se for POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/settings.php');
    exit();
}

try {
    $db = db();
    $db->beginTransaction();

    // Campos de texto
    saveSetting('site_name',        sanitize_input($_POST['site_name']        ?? ''));
    saveSetting('site_slogan',      sanitize_input($_POST['site_slogan']      ?? ''));
    saveSetting('contact_email',    sanitize_input($_POST['contact_email']    ?? ''));
    saveSetting('contact_phone',    sanitize_input($_POST['contact_phone']    ?? ''));
    saveSetting('site_address',     sanitize_input($_POST['site_address']     ?? ''));
    saveSetting('social_instagram', sanitize_input($_POST['social_instagram'] ?? ''));
    saveSetting('social_facebook',  sanitize_input($_POST['social_facebook']  ?? ''));

    // Pasta de uploads (dois níveis acima de admin/actions/)
    $uploadDir = ABSPATH . 'uploads/';

    // Upload da logo
    $logoPath = handleImageUpload('site_logo', 'logo', $uploadDir);
    if ($logoPath !== null) {
        saveSetting('site_logo_path', $logoPath);
    }

    // Upload do favicon
    $faviconPath = handleImageUpload('site_favicon', 'favicon', $uploadDir);
    if ($faviconPath !== null) {
        saveSetting('site_favicon_path', $faviconPath);
    }

    $db->commit();

    $_SESSION['message'] = [
        'type' => 'success',
        'text' => '✅ Configurações salvas com sucesso!'
    ];

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    error_log("Erro ao salvar configurações: " . $e->getMessage());
    $_SESSION['message'] = [
        'type' => 'error',
        'text' => '❌ Erro ao salvar configurações: ' . $e->getMessage()
    ];
}

header('Location: ../pages/settings.php');
exit();