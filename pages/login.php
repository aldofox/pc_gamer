<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// Carrega configs, conexão e funções
require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

// Garante que a sessão está iniciada
ensureSessionStarted(); // Usando a função do functions.php

// Se o usuário (cliente) já está logado, redireciona para a home ou Minha Conta
if (isUserLoggedIn()) { // Usando a função que verifica login de cliente
    redirect(getSiteUrl('pages/my_account.php')); // Redireciona para Minha Conta
    exit;
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validações básicas
    if (empty($login)) {
        $erros[] = 'Por favor, informe seu e-mail.'; // Mudado para e-mail, já que clientes usam e-mail
    }
    if (empty($password)) {
        $erros[] = 'Por favor, informe sua senha.';
    }

    if (empty($erros)) {
        try {
            $db = db();
            $pdo_instance = $db->getPdo();

            // Tenta buscar o cliente pelo e-mail na tabela 'customers'
            // A tabela 'customers' não tem 'username', então buscamos apenas por 'email'
            $stmt = $pdo_instance->prepare("
                SELECT id, name, email, password, is_active
                FROM customers
                WHERE email = ? AND is_active = 1
            ");
            $stmt->execute([$login]); // $login agora é o email
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifica se encontrou o cliente E se a senha está correta
            if ($customer && password_verify($password, $customer['password'])) {
                // Login bem-sucedido para o CLIENTE
                $_SESSION['user_id']  = $customer['id']; // ID da tabela 'customers'
                $_SESSION['username'] = $customer['name'];
                $_SESSION['email']    = $customer['email'];
                $_SESSION['role']     = 'customer'; // Definir explicitamente o role como 'customer'

                // LIMPAR QUAISQUER SESSÕES DE ADMIN QUE POSSAM TER FICADO
                unset($_SESSION['admin_logged_in']);
                unset($_SESSION['admin_id']);
                unset($_SESSION['admin_username']);
                unset($_SESSION['admin_email']);
                unset($_SESSION['admin_role']);

                $_SESSION['success_message'] = 'Login realizado com sucesso!';
                redirect(getSiteUrl('pages/my_account.php')); // Redireciona para a Minha Conta
                exit;
            } else {
                // Se não encontrou cliente ou senha incorreta
                $erros[] = 'E-mail ou senha incorretos.';
            }
        } catch (Exception $e) {
            error_log("Erro ao fazer login do cliente: " . $e->getMessage());
            $erros[] = 'Erro ao fazer login. Por favor, tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GamerStore</title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Seus estilos CSS para o formulário de login */
        .login-container {
            max-width: 450px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .login-container h1 {
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
            border-color: var(--primary-color); /* Usando sua variável CSS */
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--primary-color); /* Usando sua variável CSS */
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #ff8533; /* Cor de hover para primary-color */
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
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .register-link a {
            color: var(--accent-color); /* Usando sua variável CSS */
            text-decoration: none;
            font-weight: bold;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<?php include ABSPATH . 'includes/header.php'; ?>

<main>
    <div class="login-container">
        <h1>Login de Cliente</h1> <!-- Título mais específico -->

        <?php
        // Exibe mensagens de sucesso ou erro da sessão (se houver)
        if (isset($_SESSION['message'])) {
            $msgType = $_SESSION['message']['type'];
            $msgText = $_SESSION['message']['text'];
            echo '<div class="alert alert-' . htmlspecialchars($msgType) . '">' . htmlspecialchars($msgText) . '</div>';
            unset($_SESSION['message']);
        }
        ?>

        <?php if (!empty($erros)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($erros as $erro): ?>
                        <li><?php echo htmlspecialchars($erro); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="login">E-mail</label> <!-- Label mudado para E-mail -->
                <input type="email"
                       name="login"
                       id="login"
                       value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                       required>
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password"
                       name="password"
                       id="password"
                       required>
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="register-link">
            <p class="auth-link-bottom">
                Não tem uma conta? <a href="<?php echo getSiteUrl('pages/register.php'); ?>">Cadastre-se</a>
            </p>
        </div>
    </div>
</main>

<?php include ABSPATH . 'includes/footer.php'; ?>
</body>
</html>