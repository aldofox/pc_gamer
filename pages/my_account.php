<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

ensureSessionStarted();
requireUserLogin();

$userId     = $_SESSION['user_id'] ?? 0;
$errors     = [];
$successMsg = '';

try {
    $db           = db();
    $pdo_instance = $db->getPdo();

    // Busca dados do customer logado
    $stmt = $pdo_instance->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$userId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Cliente não encontrado.'];
        redirect(getSiteUrl('index.php'));
        exit;
    }

    // =============================================
    // POST: Atualizar dados pessoais / endereço / senha / AVATAR
    // =============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

        // Valida CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $errors[] = 'Token de segurança inválido. Tente novamente.';
        }

        // ---- Dados Pessoais ----
        if (empty($errors) && $_POST['action'] === 'update_profile') {
            $name          = trim($_POST['name'] ?? '');
            $email         = trim($_POST['email'] ?? '');
            $phone         = trim($_POST['phone'] ?? '');
            $cpf           = trim($_POST['cpf'] ?? '');
            $date_of_birth = trim($_POST['date_of_birth'] ?? '');
            $gender        = trim($_POST['gender'] ?? '');

            if (empty($name))  $errors[] = 'O nome é obrigatório.';
            if (empty($email)) $errors[] = 'O e-mail é obrigatório.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';

            // Verifica se email já está em uso por outro customer
            if (empty($errors)) {
                $stmtCheck = $pdo_instance->prepare(
                    "SELECT id FROM customers WHERE email = ? AND id != ?"
                );
                $stmtCheck->execute([$email, $userId]);
                if ($stmtCheck->fetch()) {
                    $errors[] = 'Este e-mail já está sendo usado por outra conta.';
                }
            }

            if (empty($errors)) {
                $stmt = $pdo_instance->prepare("
                    UPDATE customers
                    SET name = ?, email = ?, phone = ?, cpf = ?,
                        date_of_birth = ?, gender = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name,
                    $email,
                    $phone,
                    $cpf,
                    !empty($date_of_birth) ? $date_of_birth : null,
                    !empty($gender) ? $gender : null,
                    $userId
                ]);

                // Atualiza sessão
                $_SESSION['username'] = $name;
                $_SESSION['email']    = $email;

                $successMsg = 'Dados pessoais atualizados com sucesso!';

                // Recarrega dados atualizados
                $stmt = $pdo_instance->prepare("SELECT * FROM customers WHERE id = ?");
                $stmt->execute([$userId]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        // ---- Endereço ----
        if (empty($errors) && $_POST['action'] === 'update_address') {
            $address      = trim($_POST['address'] ?? '');
            $neighborhood = trim($_POST['neighborhood'] ?? '');
            $city         = trim($_POST['city'] ?? '');
            $state        = trim($_POST['state'] ?? '');
            $zip_code     = trim($_POST['zip_code'] ?? '');
            $country      = trim($_POST['country'] ?? 'Brasil');

            if (empty($address))      $errors[] = 'O endereço é obrigatório.';
            if (empty($city))         $errors[] = 'A cidade é obrigatória.';
            if (empty($state))        $errors[] = 'O estado é obrigatório.';
            if (empty($zip_code))     $errors[] = 'O CEP é obrigatório.';

            if (empty($errors)) {
                $stmt = $pdo_instance->prepare("
                    UPDATE customers
                    SET address = ?, neighborhood = ?, city = ?,
                        state = ?, zip_code = ?, country = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $address,
                    $neighborhood,
                    $city,
                    $state,
                    $zip_code,
                    $country,
                    $userId
                ]);

                $successMsg = 'Endereço atualizado com sucesso!';

                // Recarrega dados atualizados
                $stmt = $pdo_instance->prepare("SELECT * FROM customers WHERE id = ?");
                $stmt->execute([$userId]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }

        // ---- Alterar Senha ----
        if (empty($errors) && $_POST['action'] === 'update_password') {
            $current_password  = $_POST['current_password'] ?? '';
            $new_password      = $_POST['new_password'] ?? '';
            $confirm_password  = $_POST['confirm_password'] ?? '';

            if (empty($current_password)) $errors[] = 'Informe a senha atual.';
            if (empty($new_password))     $errors[] = 'Informe a nova senha.';
            if (strlen($new_password) < 6) $errors[] = 'A nova senha deve ter no mínimo 6 caracteres.';
            if ($new_password !== $confirm_password) $errors[] = 'As senhas não coincidem.';

            if (empty($errors)) {
                // Busca senha atual
                $stmtPwd = $pdo_instance->prepare("SELECT password FROM customers WHERE id = ?");
                $stmtPwd->execute([$userId]);
                $row = $stmtPwd->fetch(PDO::FETCH_ASSOC);

                if (!$row || !password_verify($current_password, $row['password'])) {
                    $errors[] = 'Senha atual incorreta.';
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmtUpd = $pdo_instance->prepare(
                        "UPDATE customers SET password = ?, updated_at = NOW() WHERE id = ?"
                    );
                    $stmtUpd->execute([$hashed, $userId]);
                    $successMsg = 'Senha alterada com sucesso!';
                }
            }
        }

        // ---- Upload de Avatar ----
        if (empty($errors) && $_POST['action'] === 'upload_avatar' && isset($_FILES['avatar_file'])) {
            $file = $_FILES['avatar_file'];

            // Validações do arquivo
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Erro no upload do arquivo: ' . $file['error'];
            } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB limite
                $errors[] = 'O arquivo é muito grande. Máximo 2MB.';
            } else {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($file['type'], $allowedTypes)) {
                    $errors[] = 'Tipo de arquivo não permitido. Apenas JPG, PNG ou GIF.';
                } else {
                    // Define o diretório de upload
                    $uploadDir = ABSPATH . 'assets/uploads/avatars/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true); // Cria o diretório se não existir
                    }

                    // Gera um nome único para o arquivo
                    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $newFileName   = uniqid('avatar_') . '.' . $fileExtension;
                    $targetFilePath = $uploadDir . $newFileName;

                    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                        // Se já existe um avatar antigo, tenta deletar
                        if (!empty($customer['avatar_url'])) {
                            $oldAvatarPath = ABSPATH . ltrim($customer['avatar_url'], '/');
                            if (file_exists($oldAvatarPath) && is_file($oldAvatarPath)) {
                                unlink($oldAvatarPath);
                            }
                        }

                        // Atualiza o caminho no banco de dados
                        $avatarUrl = 'assets/uploads/avatars/' . $newFileName;
                        $stmt = $pdo_instance->prepare("
                            UPDATE customers SET avatar_url = ?, updated_at = NOW() WHERE id = ?
                        ");
                        $stmt->execute([$avatarUrl, $userId]);

                        $successMsg = 'Avatar atualizado com sucesso!';

                        // Recarrega dados atualizados
                        $stmt = $pdo_instance->prepare("SELECT * FROM customers WHERE id = ?");
                        $stmt->execute([$userId]);
                        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $errors[] = 'Falha ao mover o arquivo para o diretório de uploads.';
                    }
                }
            }
        }

                // ---- Remover Avatar ----
        if (empty($errors) && $_POST['action'] === 'remove_avatar') {
            if (!empty($customer['avatar_url'])) {
                $oldAvatarPath = ABSPATH . ltrim($customer['avatar_url'], '/');
                if (file_exists($oldAvatarPath) && is_file($oldAvatarPath)) {
                    unlink($oldAvatarPath); // Deleta o arquivo físico
                }
                // Remove o caminho do banco de dados
                $stmt = $pdo_instance->prepare("
                    UPDATE customers SET avatar_url = NULL, updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$userId]);

                $successMsg = 'Avatar removido com sucesso!';

                // Recarrega dados atualizados
                $stmt = $pdo_instance->prepare("SELECT * FROM customers WHERE id = ?");
                $stmt->execute([$userId]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errors[] = 'Você não possui um avatar para remover.';
            }
        }
    }

} catch (Exception $e) {
    error_log("Erro em my_account.php: " . $e->getMessage());
    $errors[] = 'Ocorreu um erro inesperado. Tente novamente.';
}

// Gera CSRF token se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Minha Conta";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<?php include ABSPATH . 'includes/header.php'; ?>

<main class="container">
    <h1 class="page-title">
        <i class="fas fa-user-circle"></i>
        Minha Conta
    </h1>

    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <ul style="margin: 5px 0 0 20px; padding: 0;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="my-account-layout">

        <!-- Sidebar com avatar e menu -->
        <aside class="account-sidebar">
            <div class="account-avatar">
                <div class="avatar-circle">
                    <?php if (!empty($customer['avatar_url'])): ?>
                        <img src="<?php echo getSiteUrl($customer['avatar_url']); ?>" alt="Avatar" class="user-avatar-img">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <h3><?php echo htmlspecialchars($customer['name']); ?></h3>
                <p><?php echo htmlspecialchars($customer['email']); ?></p>

                <!-- Formulário de upload de avatar -->
                <form method="POST" action="" enctype="multipart/form-data" class="avatar-upload-form">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="avatar_file" class="btn btn-secondary btn-small">
                        <i class="fas fa-camera"></i> Trocar Avatar
                    </label>
                    <input type="file" id="avatar_file" name="avatar_file" accept="image/jpeg,image/png,image/gif" style="display: none;">
                    <button type="submit" class="btn btn-primary btn-small" style="display: none;" id="upload_avatar_btn">
                        <i class="fas fa-upload"></i> Enviar
                    </button>
                    <?php if (!empty($customer['avatar_url'])): ?>
                        <button type="button" class="btn btn-danger btn-small" id="remove_avatar_btn">
                            <i class="fas fa-trash"></i> Remover
                        </button>
                    <?php endif; ?>
                </form>
            </div>
            <nav class="account-nav">
                <a href="#dados-pessoais" class="account-nav-item active" data-tab="dados-pessoais">
                    <i class="fas fa-id-card"></i> Dados Pessoais
                </a>
                <a href="#endereco" class="account-nav-item" data-tab="endereco">
                    <i class="fas fa-map-marker-alt"></i> Endereço
                </a>
                <a href="#senha" class="account-nav-item" data-tab="senha">
                    <i class="fas fa-lock"></i> Alterar Senha
                </a>
                <a href="<?php echo getSiteUrl('pages/my_orders.php'); ?>" class="account-nav-item">
                    <i class="fas fa-box"></i> Meus Pedidos
                </a>
            </nav>
        </aside>

        <!-- Conteúdo principal -->
        <div class="account-content">

            <!-- ABA: Dados Pessoais -->
            <div class="account-tab active" id="tab-dados-pessoais">
                <div class="order-card">
                    <h2>Dados Pessoais</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label for="name">Nome completo *</label>
                                <input type="text" id="name" name="name"
                                       value="<?php echo htmlspecialchars($customer['name']); ?>"
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="email">E-mail *</label>
                                <input type="email" id="email" name="email"
                                       value="<?php echo htmlspecialchars($customer['email']); ?>"
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Telefone</label>
                                <input type="tel" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"
                                       placeholder="(00) 00000-0000">
                            </div>
                            <div class="form-group">
                                <label for="cpf">CPF</label>
                                <input type="text" id="cpf" name="cpf"
                                       value="<?php echo htmlspecialchars($customer['cpf'] ?? ''); ?>"
                                       placeholder="000.000.000-00">
                            </div>
                            <div class="form-group">
                                <label for="date_of_birth">Data de Nascimento</label>
                                <input type="date" id="date_of_birth" name="date_of_birth"
                                       value="<?php echo htmlspecialchars($customer['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gênero</label>
                                <select id="gender" name="gender">
                                    <option value="">Selecione</option>
                                    <option value="M"     <?php echo ($customer['gender'] ?? '') === 'M'     ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="F"     <?php echo ($customer['gender'] ?? '') === 'F'     ? 'selected' : ''; ?>>Feminino</option>
                                    <option value="Outro" <?php echo ($customer['gender'] ?? '') === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Salvar Dados Pessoais
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ABA: Endereço -->
            <div class="account-tab" id="tab-endereco">
                <div class="order-card">
                    <h2>Endereço</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_address">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-grid-2">
                            <div class="form-group form-full">
                                <label for="address">Endereço *</label>
                                <input type="text" id="address" name="address"
                                       value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>"
                                       placeholder="Rua, número, complemento">
                            </div>
                            <div class="form-group">
                                <label for="neighborhood">Bairro</label>
                                <input type="text" id="neighborhood" name="neighborhood"
                                       value="<?php echo htmlspecialchars($customer['neighborhood'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="zip_code">CEP *</label>
                                <input type="text" id="zip_code" name="zip_code"
                                       value="<?php echo htmlspecialchars($customer['zip_code'] ?? ''); ?>"
                                       placeholder="00000-000">
                            </div>
                            <div class="form-group">
                                <label for="city">Cidade *</label>
                                <input type="text" id="city" name="city"
                                       value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="state">Estado *</label>
                                <select id="state" name="state">
                                    <option value="">Selecione</option>
                                    <?php
                                    $states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
                                               'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
                                               'RS','RO','RR','SC','SP','SE','TO'];
                                    foreach ($states as $uf):
                                    ?>
                                        <option value="<?php echo $uf; ?>"
                                            <?php echo ($customer['state'] ?? '') === $uf ? 'selected' : ''; ?>>
                                            <?php echo $uf; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="country">País</label>
                                <input type="text" id="country" name="country"
                                       value="<?php echo htmlspecialchars($customer['country'] ?? 'Brasil'); ?>">
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Salvar Endereço
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ABA: Alterar Senha -->
            <div class="account-tab" id="tab-senha">
                <div class="order-card">
                    <h2>Alterar Senha</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_password">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-grid-1">
                            <div class="form-group">
                                <label for="current_password">Senha Atual *</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="current_password" name="current_password"
                                           placeholder="Digite sua senha atual">
                                    <button type="button" class="toggle-password" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="new_password">Nova Senha *</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="new_password" name="new_password"
                                           placeholder="Mínimo 6 caracteres">
                                    <button type="button" class="toggle-password" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirmar Nova Senha *</label>
                                <div class="password-input-wrapper">
                                    <input type="password" id="confirm_password" name="confirm_password"
                                           placeholder="Repita a nova senha">
                                    <button type="button" class="toggle-password" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-lock"></i>
                                Alterar Senha
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div><!-- fim account-content -->
    </div><!-- fim my-account-layout -->
</main>

<?php include ABSPATH . 'includes/footer.php'; ?>

<script>
// Troca de abas
document.querySelectorAll('.account-nav-item[data-tab]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        var tab = this.getAttribute('data-tab');

        // Remove active de todos
        document.querySelectorAll('.account-nav-item').forEach(function(el) {
            el.classList.remove('active');
        });
        document.querySelectorAll('.account-tab').forEach(function(el) {
            el.classList.remove('active');
        });

        // Ativa o clicado
        this.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
    });
});

// Toggle mostrar/ocultar senha
document.querySelectorAll('.toggle-password').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var targetId = this.getAttribute('data-target');
        var input    = document.getElementById(targetId);
        var icon     = this.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Lógica para upload de avatar
document.getElementById('avatar_file').addEventListener('change', function() {
    var uploadBtn = document.getElementById('upload_avatar_btn');
    if (this.files.length > 0) {
        uploadBtn.style.display = 'inline-block';
    } else {
        uploadBtn.style.display = 'none';
    }
});

// Botão de remover avatar
document.getElementById('remove_avatar_btn')?.addEventListener('click', function() {
    if (confirm('Tem certeza que deseja remover seu avatar?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = ''; // Envia para a própria página

        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'remove_avatar';
        form.appendChild(actionInput);

        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    }
});


// Abre aba correta se voltou com sucesso/erro em um POST
<?php if (!empty($successMsg) || !empty($errors)): ?>
    <?php if (isset($_POST['action'])): ?>
        (function() {
            var action = '<?php echo htmlspecialchars($_POST['action']); ?>';
            var tabMap = {
                'update_profile':  'dados-pessoais',
                'update_address':  'endereco',
                'update_password': 'senha',
                'upload_avatar':   'dados-pessoais', // Mantém na aba de dados pessoais após upload
                'remove_avatar':   'dados-pessoais'  // Mantém na aba de dados pessoais após remover
            };
            var tab = tabMap[action];
            if (tab) {
                document.querySelector('[data-tab="' + tab + '"]').click();
            }
        })();
    <?php endif; ?>
<?php endif; ?>
</script>

</body>
</html>