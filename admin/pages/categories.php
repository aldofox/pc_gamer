<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__). '/includes/admin_functions.php';

requireAdminLogin();

$pageTitle = "Gerenciar Categorias | Painel Admin";

//Recupera a sessao se houver
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// ============================== DEBUG Processo da Exclusao da Categoria ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    error_log("================ Inicio do DEBUG da Exclusao de Catgoria ===========================");

    $categoryId = isset($_POST['category_id']) ? (int)trim($_POST['category_id']) : 0;

    error_log("ID de categoria recebido do POST (apos TRIM e CAST): " .$categoryId);

    if ($categoryId > 0) {
         //Antes de deletar, verificar se existem produtos associados a esta categoria.
        // Se houver, você pode:
        // 1. Impedir a exclusão e mostrar uma mensagem de erro.
        // 2. Reassociar os produtos a uma categoria padrão (ex: "Sem Categoria").
        // Por enquanto, a exclusão é direta.

        if (deleteCategory($categoryId)) {
            error_log("Categoria ID $categoryId deletada com sucesso!");
            $_SESSION['message'] = ['type' => 'sucess', 'text' => 'Categoria deletada com sucesso!'];
        } else {
            error_log("Falha ao deletar categoria ID $categoryId na funçao deleteCategory().");
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao deltar a categoria. Tente novamente.'];
        }
    } else {
        error_log("ID de categoria  invalido (nao e > 0): " . $categoryId);
        $_SESSION['message'] = ['type' => 'error', 'text' => 'ID de categoria inválido.'];
    }

    error_log("================ Fim do DEBUG da Exclusao de Categoria =====================");

    redirect(getSiteUrl('admin/pages/categories.php'));
    exit;
}

$categories = getAllCategories();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle;?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css');?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>
<body>
    <div class="admin-container">
        <?php include dirname(__DIR__) . '/includes/admin_siderbar.php';?>
        <div class="main-content">
            <div class="header">
                <h1><?php echo $pageTitle;?></h1>
                <form action="<?php echo getSiteUrl('admin/logout.php');?>" style="display-inline;">
                    <button type="submit" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i>  Sair</button>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif;?>

            <a href="<?php echo getSiteUrl('admin/pages/category_form.php'); ?>" class="add-product-btn"><i class="fa-solid fa-tag"></i> Adicionar Nova Categoria</a>
            <?php if (empty($categories)):?>
                <p>Nenhuma Categoria Cadastrada Ainda.</p>
            <?php else: ?>
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Nome</th>
                            <th>Slug</th>
                            <th>Icone</th>
                            <th>Descriçao</th>
                            <th>Ativa</th>
                            <th>Açao</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['id']); ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                <td><?php echo htmlspecialchars($category['icon'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(substr($category['description'] ?? '', 0, 50)) . (strlen($category['description'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td><?php echo $category['is_active'] ? 'Sim' : 'Nao'; ?></td>
                                <td class="actions">
                                    <a href="<?php echo getSiteUrl('admin/pages/category_form.php?id=' . $category['id']); ?>" class="edit-btn"><i class="fa-solid fa-edit"></i></a>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('⚠️ Tem certeza que deseja DELETAR esta categoria? Esta ação não pode ser desfeita! Categoria: <?php echo htmlspecialchars(addslashes($category['name'])); ?>');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category['id']); ?>">
                                        <button type="submit" class="delete-btn"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach;?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    </body>
</html>