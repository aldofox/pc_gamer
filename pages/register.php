<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

ensureSessionStarted();

// Se o usuário já estiver logado, redireciona para a página inicial ou Minha Conta
if (isUserLoggedIn()) {
    redirect(getSiteUrl('pages/my_account.php'));
    exit;
}

$errors     = [];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Token de segurança inválido. Tente novamente.';
    }

    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone         = trim($_POST['phone'] ?? '');
    $cpf           = trim($_POST['cpf'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender        = trim($_POST['gender'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $neighborhood  = trim($_POST['neighborhood'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $state         = trim($_POST['state'] ?? '');
    $zip_code      = trim($_POST['zip_code'] ?? '');
    $country       = trim($_POST['country'] ?? 'Brasil');

    // Validações básicas
    if (empty($name))  $errors[] = 'O nome é obrigatório.';
    if (empty($email)) $errors[] = 'O e-mail é obrigatório.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if (empty($password)) $errors[] = 'A senha é obrigatória.';
    if (strlen($password) < 6) $errors[] = 'A senha deve ter no mínimo 6 caracteres.';
    if ($password !== $confirm_password) $errors[] = 'As senhas não coincidem.';

    // Validação de CPF (opcional, mas bom ter)
    if (!empty($cpf) && !isValidCPF($cpf)) { // Supondo que você tenha uma função isValidCPF()isValidCPF()
        $errors[] = 'CPF inválido.';
    }

    if (empty($errors)) {
        try {
            $db = db();
            $pdo_instance = $db->getPdo();

            // Verifica se o e-mail já está cadastrado
            $stmtCheckEmail = $pdo_instance->prepare("SELECT id FROM customers WHERE email = ?");
            $stmtCheckEmail->execute([$email]);
            if ($stmtCheckEmail->fetch()) {
                $errors[] = 'Este e-mail já está cadastrado. Tente fazer login.';
            }

            // Verifica se o CPF já está cadastrado (se informado)
            if (empty($errors) && !empty($cpf)) {
                $stmtCheckCpf = $pdo_instance->prepare("SELECT id FROM customers WHERE cpf = ?");
                $stmtCheckCpf->execute([$cpf]);
                if ($stmtCheckCpf->fetch()) {
                    $errors[] = 'Este CPF já está cadastrado.';
                }
            }

            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo_instance->prepare("
                    INSERT INTO customers (
                        name, email, password, phone, cpf, date_of_birth, gender,
                        address, neighborhood, city, state, zip_code, country, is_active
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
                    )
                ");
                $stmt->execute([
                    $name, $email, $hashed_password,
                    !empty($phone) ? $phone : null,
                    !empty($cpf) ? $cpf : null,
                    !empty($date_of_birth) ? $date_of_birth : null,
                    !empty($gender) ? $gender : null,
                    !empty($address) ? $address : null,
                    !empty($neighborhood) ? $neighborhood : null,
                    !empty($city) ? $city : null,
                    !empty($state) ? $state : null,
                    !empty($zip_code) ? $zip_code : null,
                    !empty($country) ? $country : 'Brasil'
                ]);

                $successMsg = 'Cadastro realizado com sucesso! Faça login para continuar.';
                // Opcional: logar o usuário automaticamente
                // $_SESSION['user_id'] = $pdo_instance->lastInsertId();
                // $_SESSION['username'] = $name;
                // $_SESSION['email'] = $email;
                // $_SESSION['role'] = 'customer';
                // redirect(getSiteUrl('pages/my_account.php'));
                // exit;

                // Redireciona para a página de login
                $_SESSION['message'] = ['type' => 'success', 'text' => $successMsg];
                redirect(getSiteUrl('pages/login.php'));
                exit;
            }

        } catch (Exception $e) {
            error_log("Erro no cadastro de cliente: " . $e->getMessage());
            $errors[] = 'Ocorreu um erro inesperado ao cadastrar. Tente novamente.';
        }
    }
}

// Gera CSRF token se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = "Cadastre-se";
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
    <div class="auth-form-wrapper">
        <div class="auth-form-card">
            <h1 class="page-title">
                <i class="fas fa-user-plus"></i>
                Crie sua Conta
            </h1>
            <p class="auth-subtitle">
                Preencha seus dados para ter acesso exclusivo à sua área de cliente.
            </p>

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

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-grid-2">
                    <div class="form-group form-full">
                        <label for="name">Nome completo *</label>
                        <input type="text" id="name" name="name"
                               value="<?php echo htmlspecialchars($name ?? ''); ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail *</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo htmlspecialchars($email ?? ''); ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="password">Senha *</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="password" name="password"
                                   placeholder="Mínimo 6 caracteres" required>
                            <button type="button" class="toggle-password" data-target="password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Senha *</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="Repita a senha" required>
                            <button type="button" class="toggle-password" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone">Telefone</label>
                        <input type="tel" id="phone" name="phone"
                               value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                               placeholder="(00) 00000-0000">
                    </div>
                    <div class="form-group">
                        <label for="cpf">CPF</label>
                        <input type="text" id="cpf" name="cpf"
                               value="<?php echo htmlspecialchars($cpf ?? ''); ?>"
                               placeholder="000.000.000-00">
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Data de Nascimento</label>
                        <input type="date" id="date_of_birth" name="date_of_birth"
                               value="<?php echo htmlspecialchars($date_of_birth ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gênero</label>
                        <select id="gender" name="gender">
                            <option value="">Selecione</option>
                            <option value="M" <?php echo ($gender ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo ($gender ?? '') === 'F' ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo ($gender ?? '') === 'Outro' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>
                </div>

                <h2 class="form-section-title">Endereço</h2>
                <div class="form-grid-2">
                    <div class="form-group form-full">
                        <label for="address">Endereço</label>
                        <input type="text" id="address" name="address"
                               value="<?php echo htmlspecialchars($address ?? ''); ?>"
                               placeholder="Rua, número, complemento">
                    </div>
                    <div class="form-group">
                        <label for="neighborhood">Bairro</label>
                        <input type="text" id="neighborhood" name="neighborhood"
                               value="<?php echo htmlspecialchars($neighborhood ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="zip_code">CEP</label>
                        <input type="text" id="zip_code" name="zip_code"
                               value="<?php echo htmlspecialchars($zip_code ?? ''); ?>"
                               placeholder="00000-000">
                    </div>
                    <div class="form-group">
                        <label for="city">Cidade</label>
                        <input type="text" id="city" name="city"
                               value="<?php echo htmlspecialchars($city ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="state">Estado</label>
                        <select id="state" name="state">
                            <option value="">Selecione</option>
                            <?php
                            $states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
                                       'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
                                       'RS','RO','RR','SC','SP','SE','TO'];
                            foreach ($states as $uf):
                            ?>
                                <option value="<?php echo $uf; ?>"
                                    <?php echo ($state ?? '') === $uf ? 'selected' : ''; ?>>
                                    <?php echo $uf; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="country">País</label>
                        <input type="text" id="country" name="country"
                               value="<?php echo htmlspecialchars($country ?? 'Brasil'); ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        <i class="fas fa-user-plus"></i>
                        Cadastrar
                    </button>
                </div>
            </form>

            <p class="auth-link-bottom">
                Já tem uma conta? <a href="<?php echo getSiteUrl('pages/login.php'); ?>">Faça Login</a>
            </p>
        </div>
    </div>
</main>

<?php include ABSPATH . 'includes/footer.php'; ?>

<script>
// Toggle mostrar/ocultar senha (reaproveitado de my_account.php)
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
</script>

</body>
</html>