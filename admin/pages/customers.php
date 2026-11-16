<?php
// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__DIR__)) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php'; // <--- ESTA LINHA É CRÍTICA!
require_once ABSPATH . 'includes/functions.php';
require_once ABSPATH . 'admin/includes/admin_functions.php';

// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

requireAdminLogin(); // Garante que o admin está logado

$pageTitle = "Gerenciar Clientes | Painel Admin";

// Recupera mensagem da Sessão se houver
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

//**************************** Processa a Exclusão do Cliente ****************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    error_log("============================ INÍCIO DA EXCLUSÃO DE CLIENTE ========================");

    $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;

    error_log("Tentando deletar o cliente ID: " . $customerId);

    if ($customerId > 0){
        // deleteCustomer() também precisará ser ajustada para usar db() e a tabela 'customers'
        if (deleteCustomer($customerId)){ // Esta função precisa existir e estar correta
            error_log("Cliente ID $customerId deletado com sucesso!");
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Cliente deletado com sucesso!.'];
        } else {
            error_log("Falha ao deletar cliente ID $customerId");
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao deletar o cliente. Tente novamente.'];
        }
    } else {
        error_log("ID de cliente inválido: " . $customerId);
        $_SESSION['message'] = ['type' => 'error', 'text' => 'ID de cliente inválido.'];
    }

    error_log("========== FIM DA EXCLUSÃO DE CLIENTE ==========");

    // Redireciona para atualizar a página
    redirect(getSiteUrl('admin/pages/customers.php'));
    exit;
}

// Lógica para buscar clientes
$customers = getAllCustomers(); // Chamada da função corrigida

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Adicione outros estilos ou scripts específicos para clientes aqui -->
</head>
<body>
    <div class="admin-container">
        <?php include ABSPATH . 'admin/includes/admin_siderbar.php'; ?>
        <div class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>

            <div class="header">
                <h1><?php echo $pageTitle; ?></h1>
                <form action="<?php echo getSiteUrl('admin/logout.php'); ?>" style="display:inline;">
                    <button type="submit" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i>  Sair</button>
                </form>
            </div>

            <a href="<?php echo getSiteUrl('admin/pages/customer_form.php'); ?>" class="add-product-btn"><i class="fa-solid fa-users"></i>Adicionar Cliente</a>

            <?php if (empty($customers)): ?>
                <p>Nenhum cliente cadastrado ainda.</p>
            <?php else: ?>
                <table class="product-table"> <!-- Mantendo sua classe de tabela -->
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>CPF</th>
                            <th>Ativo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['id']); ?></td>
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                <td><?php echo htmlspecialchars($customer['cpf']); ?></td>
                                <td><?php echo $customer['is_active'] ? 'Sim' : 'Não'; ?></td>
                                <td class="actions">
                                    <a href="<?php echo getSiteUrl('admin/pages/customer_form.php?id=' . $customer['id']); ?>" class="edit-btn"><i class="fa-solid fa-edit"></i> </a>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('⚠️ Tem certeza que deseja DELETAR este cliente? Esta ação não pode ser desfeita!\n\nCliente: <?php echo htmlspecialchars(addslashes($customer['name'])); ?>');">
                                        <input type="hidden" name="action" value="delete_customer">
                                        <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customer['id']); ?>">
                                        <button type="submit" class="delete-btn"><i class="fa-solid fa-trash"></i></button>
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