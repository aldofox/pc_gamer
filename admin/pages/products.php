<?php
// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__DIR__)) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';    // <- CONEXÃO (db())
require_once ABSPATH . 'includes/functions.php';
require_once ABSPATH . 'admin/includes/admin_functions.php';

requireAdminLogin(); //Somente Administradores vão acessar essa página

$pageTitle = "Gerenciar Produtos | Painel Admin.";

// Recupera mensagem da Sessão se houver
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

//**************************** Processa a Exclusão do Produto ****************************/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    error_log("============================ INÍCIO DA EXCLUSÃO DE PRODUTO ========================");

    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    error_log("Tentando deletar o produto ID: " . $productId);

    if ($productId > 0){
        if (deleteProduct($productId)){
            error_log("Produto ID $productId deletado com sucesso!");
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Produto deletado com sucesso!.'];
        } else {
            error_log("Falha ao deletar produto ID $productId");
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao deletar o produto. Tente novamente.'];
        }
    } else {
        error_log("ID de produto inválido: " . $productId);
        $_SESSION['message'] = ['type' => 'error', 'text' => 'ID de produto inválido.'];
    }

    error_log("========== FIM DA EXCLUSÃO DE PRODUTO ==========");

    // Redireciona para atualizar a página
    redirect(getSiteUrl('admin/pages/products.php'));
    exit;
}

//**************************** Buscar produtos ****************************/

function getAllProductAdmin() {
    try {
        // Usa a função db() da sua classe Database
        $sql = "SELECT p.*, c.name AS category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                ORDER BY p.created_at DESC";

        return db()->fetchAll($sql);
    } catch (Exception $e) {
        error_log("Erro ao buscar produtos no admin: " . $e->getMessage());
        return [];
    }
}

$products = getAllProductAdmin();

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

            <a href="<?php echo getSiteUrl('admin/pages/product_form.php'); ?>" class="add-product-btn"><i class="fa-solid fa-gifts"></i> Adicionar Produto</a>

            <?php if (empty($products)): ?>
                <p>Nenhum produto cadastrado ainda.</p>
            <?php else: ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Promoção</th>
                            <th>Estoque</th>
                            <th>Ativo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['id']); ?></td>
                                <td>
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?php echo getSiteUrl($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        Sem Imagem
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>
                                    <?php 
                                        // Você já traz category_name no SELECT, poderia usar direto.
                                        // Mas se quiser manter a função:
                                        $categoryName = $product['category_name'] ?? getCategoryNameById($product['category_id']);
                                        echo htmlspecialchars($categoryName);
                                    ?>
                                </td>
                                <td><?php echo formatCurrency($product['unit_price']); ?></td>
                                <td>
                                    <?php 
                                        echo ($product['is_promotion'] && !empty($product['promotional_price']))
                                            ? formatCurrency($product['promotional_price'])
                                            : '-';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['stock_qty']); ?></td>
                                <td><?php echo $product['is_active'] ? 'Sim' : 'Não'; ?></td>
                                <td class="actions">
                                    <a href="<?php echo getSiteUrl('admin/pages/product_form.php?id=' . $product['id']); ?>" class="edit-btn"><i class="fa-solid fa-edit"></i></a>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('⚠️ Tem certeza que deseja DELETAR este produto? Esta ação não pode ser desfeita!\n\nProduto: <?php echo htmlspecialchars(addslashes($product['name'])); ?>');"> 
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                        <button type="submit" class="delete-btn"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            <?php endif;?>
        </div>
    </div>
</body>
</html>