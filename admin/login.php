<?php
require_once '../includes/config.php';
require_once 'includes/admin_functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (adminLogin($username, $password)) {
        redirect(getSiteUrl('admin/dashboard.php'));
    } else {
        $error = 'Usuario ou senha invalidos.';
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel Admin</title>
    <style>
        * { margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body { 
            font-family: Verdana, Geneva, Tahoma, sans-serif, sans-serif; 
            background: #f5f5f5; 
        }

        .login-container { 
            max-width: 400px; 
            margin: 100px auto; 
            background: white; 
            padding: 40px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 { 
            text-align: center; 
            margin-bottom: 30px; 
            color: #333; 
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 5px; 
            color: #555; 
        }
        input { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        button { 
            width: 100%; 
            padding: 10px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; cursor: pointer; 
        }
        button:hover { 
            background: #0056b3; 
        }
        .error { 
            color: red; margin-bottom: 20px; 
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔐 Painel Admin</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="">Usuario:</label>
                <input type="text" name="username" required placeholder="Nome do seu Usuario.">
            </div>
            <div class="form-group">
                <label for="">Senha:</label>
                <input type="password" name="password" required placeholder="Sua Senha">
            </div>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>