<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Se já está logado, vai para a home
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getSiteUrl('index.php'));
    exit;
}

$erros   = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validações
    if (empty($username)) {
        $erros[] = 'O nome de usuário é obrigatório.';
    } elseif (strlen($username) < 3) {
        $erros[] = 'O nome de usuário deve ter pelo menos 3 caracteres.';
    }

    if (empty($email)) {
        $erros[] = 'O email é obrigatório.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Email inválido.';
    }

    if (empty($password)) {
        $erros[] = 'A senha é obrigatória.';
    } elseif (strlen($password) < 6) {
        $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
    }

    if ($password !== $confirmPassword) {
        $erros[] = 'As senhas não coincidem.';
    }

    // Se não houver erros, verifica se usuário já existe
    if (empty($erros)) {
        try {
            // Verifica se o username já existe
            $userExiste = db()->fetchOne(
                "SELECT id FROM user WHERE username = ?", // Corrigido para 'user'
                [$username]
            );
            if ($userExiste) {
                $erros[] = 'Este nome de usuário já está em uso.';
            }

            // Verifica se o email já existe
            $emailExiste = db()->fetchOne(
                "SELECT id FROM user WHERE email = ?", // Corrigido para 'user'
                [$email]
            );
            if ($emailExiste) {
                $erros[] = 'Este email já está cadastrado.';
            }

            // Se ainda não houver erros, cria o usuário
            if (empty($erros)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                db()->insert(
                    "INSERT INTO user (username, email, password_hash, role, is_active)
                     VALUES (?, ?, ?, 'customer', 1)", // Corrigido para 'user'
                    [$username, $email, $passwordHash]
                );

                $success = true;
            }
        } catch (Exception $e) {
            error_log("Erro ao registrar usuário: " . $e->getMessage());
            $erros[] = 'Erro ao criar conta. Por favor, tente novamente.';
        }
    }
}
?>
<?php require_once ABSPATH . 'includes/header.php'; ?>

<main>
    <div class="register-container">
        <h1>Criar Conta</h1>

        <?php if (!empty($erros)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($erros as $erro): ?>
                        <li><?php echo htmlspecialchars($erro); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <p>✅ Conta criada com sucesso!</p>
                <p>
                    <a href="<?php echo getSiteUrl('pages/login'); ?>" class="btn-login">
                        Clique aqui para fazer o login
                    </a>
                </p>
            </div>
        <?php else: ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="username">Nome de Usuário</label>
                    <input type="text" 
                           name="username" 
                           id="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirmar Senha</label>
                    <input type="password" 
                           name="confirm_password" 
                           id="confirm_password" 
                           required>
                </div>

                <button type="submit" class="btn-register">Criar Conta</button>
            </form>

            <div class="login-link">
                Já tem uma conta? 
                <a href="<?php echo getSiteUrl('pages/login'); ?>">Faça login aqui</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
    /* Seus estilos CSS para o formulário de registro */
    .register-container {
        max-width: 500px;
        margin: 50px auto;
        padding: 30px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .register-container h1 {
        text-align: center;
        margin-bottom: 30px;
        color: #333;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #555;
    }
    .form-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 16px;
        box-sizing: border-box;
    }
    .form-group input:focus {
        outline: none;
        border-color: #007bff;
    }
    .btn-register {
        width: 100%;
        padding: 12px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }
    .btn-register:hover {
        background: #218838;
    }
    .btn-login {
        display: inline-block;
        padding: 10px 20px;
        background: #007bff;
        color: white;
        border-radius: 4px;
        text-decoration: none;
        font-weight: bold;
        margin-top: 10px;
    }
    .btn-login:hover {
        background: #0056b3;
    }
    .error-messages {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        border: 1px solid #f5c6cb;
    }
    .error-messages ul {
        margin: 0;
        padding-left: 20px;
    }
    .success-message {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
        border: 1px solid #c3e6cb;
        text-align: center;
    }
    .login-link {
        text-align: center;
        margin-top: 20px;
        color: #666;
    }
    .login-link a {
        color: #007bff;
        text-decoration: none;
    }
    .login-link a:hover {
        text-decoration: underline;
    }
</style>

<?php require_once ABSPATH . 'includes/footer.php'; ?>