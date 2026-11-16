<?php
// admin/pages/settings.php

session_start(); // DEVE ser a primeira coisa, antes de qualquer output ou require

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__DIR__)) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';
require_once ABSPATH . 'admin/includes/admin_functions.php';

// Carrega todas as configurações do banco de dados
$db = db();
$allSettings = $db->fetchAll("SELECT chave, valor FROM settings");
$settings = [];
foreach ($allSettings as $row) {
    $settings[$row['chave']] = $row['valor'];
}

// Variáveis para os campos do formulário
$site_name        = $settings['site_name']        ?? 'Minha Loja';
$site_slogan      = $settings['site_slogan']      ?? '';
$contact_email    = $settings['contact_email']    ?? '';
$contact_phone    = $settings['contact_phone']    ?? '';
$site_address     = $settings['site_address']     ?? '';
$social_instagram = $settings['social_instagram'] ?? '';
$social_facebook  = $settings['social_facebook']  ?? '';
$site_logo_path   = $settings['site_logo_path']   ?? '';
$site_favicon_path= $settings['site_favicon_path']?? '';

$pageTitle = "Configurações do Site";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            color: #333;
        }

        .settings-wrapper {
            max-width: 860px;
            margin: 40px auto;
            padding: 0 20px 60px;
        }

        .settings-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .settings-header h1 {
            font-size: 1.8rem;
            color: #222;
        }

        .settings-header p {
            color: #777;
            font-size: 0.95rem;
            margin-top: 6px;
        }

        /* Alertas */
        .alert {
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        /* Card de seção */
        .settings-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            padding: 28px 32px;
            margin-bottom: 24px;
        }

        .settings-card h2 {
            font-size: 1rem;
            font-weight: 700;
            color: #444;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f2f5;
        }

        /* Grupos de campos */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="url"],
        .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            color: #333;
            background-color: #fafafa;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="email"]:focus,
        .form-group input[type="url"]:focus,
        .form-group textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.12);
            outline: none;
            background-color: #fff;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #999;
            font-size: 0.8rem;
        }

        /* Upload de imagem */
        .upload-area {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .upload-area input[type="file"] {
            font-size: 0.88rem;
            color: #555;
        }

        .upload-preview {
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 6px;
            background-color: #fafafa;
            max-height: 60px;
            width: auto;
            object-fit: contain;
        }

        .upload-preview.favicon-preview {
            max-width: 40px;
            max-height: 40px;
        }

        .no-image {
            font-size: 0.82rem;
            color: #aaa;
            font-style: italic;
        }

        /* Duas colunas lado a lado */
        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Botão salvar */
        .settings-footer {
            text-align: center;
            margin-top: 8px;
        }

        .btn-save {
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 12px 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-save:hover {
            background-color: #0056b3;
        }

        /* Responsivo */
        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .settings-card {
                padding: 20px 16px;
            }
        }
    </style>
</head>
<body>

<?php include ABSPATH . 'admin/includes/admin_siderbar.php'; ?>

<div class="settings-wrapper">
    <?php include ABSPATH . 'admin/includes/admin_header.php'; ?>
    <div class="settings-header">
        <h1><i class="fa-solid fa-gear"></i> Configurações do Site</h1>
        <p>Gerencie as informações gerais, contato e redes sociais da sua loja.</p>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message']['type'] ?>">
            <?= htmlspecialchars($_SESSION['message']['text']) ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <form action="<?= getSiteUrl('admin/actions/save_settings.php') ?>" method="POST" enctype="multipart/form-data">

        <!-- Identidade -->
        <div class="settings-card">
            <h2><i class="fa-solid fa-id-card"></i> Identidade do Site</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="site_name">Nome do Site</label>
                    <input type="text" id="site_name" name="site_name"
                           value="<?= htmlspecialchars($site_name) ?>" required>
                </div>
                <div class="form-group">
                    <label for="site_slogan">Slogan</label>
                    <input type="text" id="site_slogan" name="site_slogan"
                           value="<?= htmlspecialchars($site_slogan) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="site_logo">Logo do Site</label>
                <div class="upload-area">
                    <input type="file" id="site_logo" name="site_logo" accept="image/*">
                    <?php if (!empty($site_logo_path)): ?>
                        <img src="<?= getSiteUrl(htmlspecialchars($site_logo_path)) ?>"
                             alt="Logo Atual" class="upload-preview">
                    <?php else: ?>
                        <span class="no-image">Nenhuma logo definida</span>
                    <?php endif; ?>
                </div>
                <small>Tamanho recomendado: 200x50px. Formatos: PNG, JPG, SVG.</small>
            </div>

            <div class="form-group">
                <label for="site_favicon">Favicon</label>
                <div class="upload-area">
                    <input type="file" id="site_favicon" name="site_favicon" accept="image/*">
                    <?php if (!empty($site_favicon_path)): ?>
                        <img src="<?= getSiteUrl(htmlspecialchars($site_favicon_path)) ?>"
                             alt="Favicon Atual" class="upload-preview favicon-preview">
                    <?php else: ?>
                        <span class="no-image">Nenhum favicon definido</span>
                    <?php endif; ?>
                </div>
                <small>Tamanho recomendado: 32x32px. Formato: ICO ou PNG.</small>
            </div>
        </div>

        <!-- Contato -->
        <div class="settings-card">
            <h2><i class="fa-solid fa-phone"></i> Informações de Contato</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="contact_email">E-mail de Contato</label>
                    <input type="email" id="contact_email" name="contact_email"
                           value="<?= htmlspecialchars($contact_email) ?>">
                </div>
                <div class="form-group">
                    <label for="contact_phone">Telefone / WhatsApp</label>
                    <input type="text" id="contact_phone" name="contact_phone"
                           value="<?= htmlspecialchars($contact_phone) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="site_address">Endereço Completo</label>
                <textarea id="site_address" name="site_address"><?= htmlspecialchars($site_address) ?></textarea>
            </div>
        </div>

        <!-- Redes Sociais -->
        <div class="settings-card">
            <h2><i class="fa-solid fa-share-alt"></i> Redes Sociais</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="social_instagram">Instagram</label>
                    <input type="url" id="social_instagram" name="social_instagram"
                           value="<?= htmlspecialchars($social_instagram) ?>"
                           placeholder="https://instagram.com/sualoja">
                </div>
                <div class="form-group">
                    <label for="social_facebook">Facebook</label>
                    <input type="url" id="social_facebook" name="social_facebook"
                           value="<?= htmlspecialchars($social_facebook) ?>"
                           placeholder="https://facebook.com/sualoja">
                </div>
            </div>
        </div>

        <!-- Botão -->
        <div class="settings-footer">
            <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Salvar Configurações</button>
        </div>

    </form>
</div>

</body>
</html>