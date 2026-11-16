<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/admin_functions.php';

requireAdminLogin();

$pageTitle = "Manutençao de Categoria | Painel Admin.";
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

$category = [
    'id'          => null,
    'name'        => null,
    'slug'        => null,
    'icon'        => null,
    'description' => null,
    'is_active'   => null
];

//Se um ID de categoria  for passado, tentamos carregar os dados para edicao
if (isset($_GET['id'])) {
    $categoryId = (int)$_GET['id'];
    $existingCategory = getCategoryById($categoryId);

    // DEBUG: Categoria carregada do banco (mantenha se quiser, ou remova depois de testar)
    error_log("========== DEBUG: Categoria carregada do banco ==========");
    error_log("ID: " . ($existingCategory['id'] ?? 'NULL'));
    error_log("Name: " . ($existingCategory['name'] ?? 'NULL'));
    error_log("Slug: " . ($existingCategory['slug'] ?? 'NULL'));
    error_log("Icon: " . ($existingCategory['icon'] ?? 'NULL'));
    error_log("Description: " . ($existingCategory['description'] ?? 'NULL'));
    error_log("Is Active: " . ($existingCategory['is_active'] ?? 'NULL'));
    error_log("=========================================================");

    if ($existingCategory) {
        $category = $existingCategory;
        $pageTitle = "Editar Categoria | Painel Admin.";
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Categoria nao Encontrada.'];
        redirect(getSiteUrl('admin/pages/categories.php'));
        exit;
    }
}

//Logica para Salvar e Atualizar  a Categoria
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("============= DEBUG Inicio  do Processo do Formulario de Categoria ==================");

    $categoryid = isset($_POST['category_id']) && is_numeric($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

    $categoryData = [
        'id'          =>$categoryId > 0 ? $categoryId : null,
        'name'        => sanitizeInput($_POST['name'] ?? ''),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'icon'        => sanitizeInput($_POST['icon'] ?? ''),
        'is_active'   => isset($_POST['is_active']) ? 1 : 0
    ];

    error_log("Dados recebidos do formulario de categoria.");
    error_log("Categoria ID: " . ($categoryData['id'] ? : 'NOVO'));
    error_log("Nome: " . $categoryData['name']);
    error_log("Descriçao: " . $categoryData['description']);
    error_log("Ativo: " . $categoryData['is_active']);

    $savedCategoryId = saveCategory($categoryData);

    if ($savedCategoryId) {
        error_log("Categoria Salva com Sucesso! ID: " . $savedCategoryId);
        $_SESSION['messge'] = ['type' => 'sucess', 'text' => 'Categora Salva com Sucesso.'];
        redirect(getSiteUrl('admin/pages/categories.php'));
        exit;
    } else {
        error_log("Falha ao Salvar Categoria.");
        redirect(getSiteUrl('admin/pages/category_form.php' . ($categoryId > 0 ? '?id=' . $categoryId: '' )));
        exit;
    }
    error_log("============= DEBUG Fim do Processo do Formulario de Categoria ==================");
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=], initial-scale=1.0">
    <title><?php echo $pageTitle;?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="admin container">
        <?php include dirname(__DIR__) . '/includes/admin_siderbar.php';?>
        <div class="main-content">
            <div class="header">
                <h1><?php echo $pageTitle;?></h1>
                <form method="POST" action="<?php echo getSiteUrl('admin/logout.php'); ?>">
                    <button type="submit" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i>  Sair</button>
                </form>
            </div>
            <?php if ($message):?>
                <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif;?>

            <div class="product-form-container">
    <form method="POST">
        <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category['id'] ?? '');?>">
        <div class="form-group">
            <label for="name">Nome da Categoria</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($category['name'] ?? '');?>" required> <!-- ADICIONADO 'echo' AQUI -->
        </div>
        <div class="form-group">
            <label for="icon">Ícone (ex: fas fa-desktop):</label>
            <input type="text" id="icon" name="icon" value="<?php echo htmlspecialchars($category['icon'] ?? ''); ?>" oninput="updateIconPreview()">
            <small>
                Ex: `fas fa-desktop` para Font Awesome. 
                <a href="https://fontawesome.com/icons" target="_blank">Buscar ícones aqui</a>
            </small>
            <div id="icon-preview" style="font-size: 2em; margin-top: 10px;">
                <?php if (!empty($category['icon'])): ?>
                    <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                <?php endif; ?>
            </div>
        </div>
        <script>
        function updateIconPreview() {
            const iconInput = document.getElementById('icon');
            const iconPreview = document.getElementById('icon-preview');
            const iconClass = iconInput.value.trim();
            if (iconClass) {
                iconPreview.innerHTML = '<i class="' + iconClass + '"></i>';
            } else {
                iconPreview.innerHTML = '';
            }
        }
        </script>
        <div class="form-group">
            <label for="description">Descriçao</label>
            <textarea name="description" id="description" rows="4"><?php echo htmlspecialchars($category['description'] ?? '');?></textarea> <!-- ADICIONADO 'echo' AQUI -->
        </div>
        <div class="form-group">
            <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo ($category['is_active'] ?? 1) ? 'checked' : '';?>> <!-- ADICIONADO 'echo' AQUI -->
            <label for="is_active">Ativa</label>
        </div>
        <div class="form-actions">
            <button type="submit"><i class="fa-solid fa-save"></i>  Salvar Usuairo</button>
            <a href="<?php echo getSiteUrl('admin/pages/categories.php');?>" class="cancel-btn"><i class="fa-solid fa-times"></i> Cancelar</a>
        </div>
    </form>
</div>

        </div>
    </div>
</body>
</html>