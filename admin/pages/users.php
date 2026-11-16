<?php

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_functions.php';

requireAdminLogin();

$pageTitle = "Gerenciar Usuários | Painel Admin";

//Recupera Menssage da Sesao

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

$loggedInUserId = $_SESSION['user_id'] ?? 0;

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
error_log("//====================== Inicio DEBUG Exclusao Usuairo ===============================");

$userId = isset($_POST['user_id']) ? (int)trim($_POST['user_id']) : 0;

error_log("ID de usuario recebido do POST (Apos trim e cast): " . $userId);

if ($userId > 0 ) {
    //Prevençao: Nao permitir que um usuario delete  a sim mesmo.
    if ($userId == $loggedInUserId) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Voce nao pode deletar sua propria conta.'];
        error_log("Tentativa de auto-exclusao pelo usuario ID " . $userId);
    } else {
        if (deleteUser($userId)) {
            error_log("Usuario ID $userId deletado com sucesso!");
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Usuario Deletado com Sucesso.'];
        } else {
            error_log("Falha ao Deletar usuario ID $userID na funçao deleteUser().");
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao deletar o usuario. Tente Novamente.'];
        }
    }
} else{
    error_log("ID de usuario invalido (nao eh > 0): " . $userId);
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ID de usuario invalido'];
}
error_log("//====================== Fim DEBUG Exclusao Usuairo ===============================");

redirect(getSiteUrl('admin/pages/users.php'));
exit;
}

$users = getAllUsers();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle;?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css');?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <?php include dirname(__DIR__) . '/includes/admin_siderbar.php';?>
        <div class="main-content">
            <div class="header">
                <h1><?php echo $pageTitle;?></h1>
                <form method="POST" action="<?php echo getSiteUrl('admin/logout.php');?>">
                    <button type="submit" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i>  Sair</button>
                </form>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message['type']);?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif;?>
            
            <a href="<?php echo getSiteUrl('admin/pages/user_form.php');?>" class="add-product-btn"><i class="fas fa-user-plus"></i> Adicionar Usuario</a>

            <?php if (empty($users)): ?>
                <p>Nenhum Usuario Cadastrado.</p>
            <?php else: ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Nome</th>
                            <th>E-Mail</th>
                            <th>Funçao</th>
                            <th>Ativo</th>
                            <th>Açoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                                <td><?php echo $user['is_active'] ? 'Sim' : 'Nao'; ?></td>
                                <td class="actions">
                                    <a href="<?php echo getSiteUrl('admin/pages/user_form.php?id=' . $user['id']); ?>" class="action-btn edit-btn"><i class="fas fa-edit"></i> </a>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('⚠️ Tem certeza que deseja DELETAR este usuário? Esta ação não pode ser desfeita! Usuário: <?php echo htmlspecialchars(addslashes($user['username'])); ?>');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                        <button type="submit" class="action-btn delete-btn" <?php echo ($user['id'] == $loggedInUserId) ? 'disabled title="Você não pode deletar sua própria conta."' : ''; ?>><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>