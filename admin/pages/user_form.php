<?php
    require_once dirname(__DIR__,2) . '/includes/config.php';
    require_once dirname(__DIR__,2) . '/includes/functions.php';
    require_once dirname(__DIR__) . '/includes/admin_functions.php';

    requireAdminLogin();

    $pageTitle = "Manunteçao de Usuarios | Painel Admin";
    $message = $_SESSION['message'] ?? null;
    unset($_SESSION['message']);

    $user = [
        'id'        => null,
        'username'  => '',
        'email'      => '',
        'role'      => 'editor', //Default
        'is_active' =>1
    ];

    //Se ID do Usuario for passado, carrega os Dados para Ediçao
    if (isset($_GET['id'])) {
        $userId = (int)$_GET['id'];
        $existingUser = getUserById($userId);
        if ($existingUser) {
            $user = $existingUser;
            $pageTitle = "Editar Usuario | Painel Admin";
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Usuario nao Encontrado.'];
            redirect(getSiteUrl('admin/pages/users.php'));
            exit;
        }
    }

    //Salva e Atualiza o Usuario
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("======================== Inicio do DEBUG  do Processamento do Formulario do Usuario ================");

        $userId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        $userData = [
            'id'        => $userId > 0 ? $userId : null,
            'username'  =>sanitizeInput($_POST['username'] ?? ''),
            'email'     =>sanitizeInput($_POST['email'] ?? ''),
            'password'  => $_POST['password'] ?? '',
            'role'      =>sanitizeInput($_POST['role'] ??     'editor'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        error_log("Dados Recebidos do Formulario de Usuariao");
        error_log("User ID: " . ($userData['id'] ? : 'NOVO'));
        error_log("Username:" . $userData['username']);
        error_log("E-Mail:" . $userData['email']);
        error_log("Funçao:" . $userData['role']);
        error_log("Ativo?:" . $userData['is_active']);
        error_log("Senha Fornecida:" . (!empty($userData['password']) ? 'SIM' : 'Nao'));

        $savedUserId = saveUser($userData);

        if ($savedUserId) {
            error_log("Usuario salvo com Suvesso!" . $savedUserId);
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Usuario Salvo com Sucesso. ✅'];
            redirect(getSiteUrl('admin/pages/users.php'));
            exit;
        } else {
            error_log("Falha ao Salvar o Usuario.");
            redirect(getSiteUrl('admin/pages/user_form.php' . ($userId > 0 ? '?id=' . $userId: '')));
            exit;
        }
        error_log("======================== Fim do DEBUG  do Processamento do Formulario do Usuario ================");
    }
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include dirname(__DIR__) . '/includes/admin_siderbar.php';?>
        <div class="main-content">
            <div class="header">
                <h1><?php echo $pageTitle;?></h1>
                <form method="POST" action="<?php echo getSiteUrl('admin/logout.php');?>" style="display-inline;">
                    <button type="submit" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i>  Sair</button>
                </form>
            </div>  
            <?php if ($message):?>
                <div class="alert alert-<?php echo htmlspecialchars($message['type']);?>">
                    <?php echo htmlspecialchars($message['text']);?>
                </div>
            <?php endif; ?>

            <div class="product-form-container">
                <form method="post">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'] ?? '');?>">

                    <div class="form-group">
                        <label for="username">Nome de Usuario</label>
                        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username'] ?? '');?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-Mail:</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? '');?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Senha? <?php echo($user['id'] ? '(Deixe em Branco para manter a senha Atual)' : '*');?></label>
                        <input type="password" name="password" id="password" value="<?php echo($user['id'] ? '' : 'required');?>>
                        <?php if ($user['id']): ?>
                            <small>Apenas Preencha se deseja Alterar a Senha.</small>
                        <?php endif;?>
                    </div>

                    <div class="form-group">
                        <label for="role">Funçao:</label>
                        <select name="role" id="role">
                            <option value="admin" <?php echo (($user['role'] ?? '') === 'admin') ? 'selected' : '';?>>Admin</option>
                            <option value="editor" <?php echo (($user['role'] ?? '') === 'editor') ? 'selected' : '';?>>Editor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo ($user['is_active'] ?? 1) ? 'checked' : '';?>>
                        <label for="is_active">Usuario Ativo</label>
                    </div>

                    <div class="form-actions">
                            <button type="submit"><i class="fa-solid fa-save"></i>  Salvar Usuairo</button>
                            <a href="<?php echo getSiteUrl('admin/pages/users.php');?>" class="cancel-btn"><i class="fa-solid fa-times"></i> Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>