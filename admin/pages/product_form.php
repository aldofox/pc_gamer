<?php
error_reporting(E_ALL & ~E_DEPRECATED); // Mantendo seu nível de erro

// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__DIR__)) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php'; // <--- ADICIONE ESTA LINHA!
require_once ABSPATH . 'includes/functions.php';
require_once ABSPATH . 'admin/includes/admin_functions.php';

// Inicia a sessão se ainda não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

requireAdminLogin();

$pageTitle = "Manutenção dos Produtos | Painel Admin";
$message = $_SESSION['message'] ?? null; // Exibi mensagem de Sucesso ou de Erro
unset($_SESSION['message']);

$product = [
    'id' => null,
    'name' => '',
    'slug' => '',
    'category_id' => '',
    'short_description' => '',
    'description' => '',
    'technical_specs' => '',
    'image_url' => '',
    'unit_price' => '',
    'promotional_price' => '',
    'stock_qty' => '',
    'is_active' => 1,
    'featured' => 0,
    'is_promotion' => 0
];

// Se um ID for passado os dados para edição são carregados
if (isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    // getProductById() também precisa usar db()
    $existingProduct = getProductById($productId); // Pegamos a função que já existe
    if ($existingProduct) {
        $product = $existingProduct;
        $pageTitle = "Editar Produto | Painel Admin.";
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Produto Não Encontrado.'];
        redirect(getSiteUrl('admin/pages/products.php'));
        exit;
    }
}

// Lógica para salvar o produto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("========== INÍCIO DO PROCESSAMENTO DO FORMULÁRIO ==========");

    $productId = sanitizeInput($_POST['product_id'] ?? null);
    $name = sanitizeInput($_POST['name'] ?? '');
    $slug = sanitizeInput($_POST['slug'] ?? '');

    // Se o slug estiver vazio, gera automaticamente a partir do nome
    if (empty($slug) && !empty($name)) {
        // Remove acentos e caracteres especiais, converte para minúsculas e substitui espaços por hífens
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-',
            iconv('UTF-8', 'ASCII//TRANSLIT', $name)
        ), '-'));
        error_log("Slug gerado automaticamente: " . $slug);
    }

    $category_id = (int)sanitizeInput($_POST['category_id'] ?? 0);
    $short_description = sanitizeInput($_POST['short_description'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $technical_specs = sanitizeInput($_POST['technical_specs'] ?? '');
    $unit_price = (float)sanitizeInput($_POST['unit_price'] ?? 0);
    $promotional_price = (float)sanitizeInput($_POST['promotional_price'] ?? 0);
    $stock_qty = (int)sanitizeInput($_POST['stock_qty'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $is_promotion = isset($_POST['is_promotion']) ? 1 : 0;
    $current_image_url = sanitizeInput($_POST['current_image_url'] ?? '');

    error_log("Dados recebidos do formulário:");
    error_log("Product ID: " . ($productId ?: 'NOVO'));
    error_log("Name: " . $name);
    error_log("Slug: " . $slug);
    error_log("Category ID: " . $category_id);
    error_log("Unit Price: " . $unit_price);
    error_log("Stock: " . $stock_qty);

    $image_url = $current_image_url; // Mantém a imagem atual por padrão

    // Processa o upload da nova imagem, se houver
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        error_log("Tentando fazer upload de imagem...");
        error_log("Arquivo enviado: " . $_FILES['image_file']['name']);
        error_log("Erro do upload: " . $_FILES['image_file']['error']);
        // Certifique-se que UPLOAD_PATH, ALLOWED_IMAGE_TYPES, MAX_FILE_SIZE estão definidos em config.php
        // error_log("UPLOAD_PATH: " . UPLOAD_PATH);
        // error_log("ALLOWED_IMAGE_TYPES: " . print_r(ALLOWED_IMAGE_TYPES, true));
        // error_log("MAX_FILE_SIZE: " . MAX_FILE_SIZE);

        if ($_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            // uploadImage() também precisa usar db() se interagir com o banco ou ter acesso à conexão
            $uploadedImage = uploadImage($_FILES['image_file'], UPLOAD_PATH, ALLOWED_IMAGE_TYPES, MAX_FILE_SIZE);
            error_log("Resultado do upload: " . ($uploadedImage ?: 'FALHOU'));

            if ($uploadedImage) {
                $image_url = $uploadedImage;
                error_log("Nova imagem URL: " . $image_url);

                // Opcional: Remover a imagem antiga do servidor se houver
                if (!empty($current_image_url) && file_exists(ABSPATH . $current_image_url)) { // Use ABSPATH
                    unlink(ABSPATH . $current_image_url);
                    error_log("Imagem antiga removida: " . $current_image_url);
                }
            }
        } else {
            error_log("Erro no upload. Código: " . $_FILES['image_file']['error']);
        }
    } else {
        error_log("Nenhuma imagem enviada ou erro UPLOAD_ERR_NO_FILE");
    }

    // Validação básica
    error_log("Iniciando validação...");
    if (empty($name)) {
        error_log("ERRO DE VALIDAÇÃO: Nome vazio");
    }
    if (empty($slug)) {
        error_log("ERRO DE VALIDAÇÃO: Slug vazio");
    }
    if ($category_id === 0) {
        error_log("ERRO DE VALIDAÇÃO: Categoria não selecionada");
    }
    if ($unit_price <= 0) {
        error_log("ERRO DE VALIDAÇÃO: Preço inválido");
    }
    if ($stock_qty < 0) {
        error_log("ERRO DE VALIDAÇÃO: Estoque negativo");
    }

    if (empty($name) || empty($slug) || $category_id === 0 || $unit_price <= 0 || $stock_qty < 0) {
        error_log("Validação FALHOU. Redirecionando com mensagem de erro.");
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Por favor, preencha todos os campos obrigatórios e verifique os valores.'];
        redirect(getSiteUrl('admin/pages/product_form.php' . ($productId ? '?id=' . $productId : '')));
        exit;
    }

    error_log("Validação OK. Tentando salvar no banco de dados...");

    try {
        // Removido 'global $pdo;' e usando db()->getPdo()->prepare()
        $pdo_instance = db()->getPdo(); // Obtém a instância PDO subjacente

        if (!$pdo_instance) {
            error_log("ERRO CRÍTICO: Conexão PDO não está definida!");
            throw new Exception("Conexão com banco de dados não disponível");
        }

        error_log("Conexão PDO OK");
        $productId = isset($_POST['product_id']) && is_numeric($_POST['product_id'])
            ? (int)$_POST['product_id'] : 0;
        if ($productId > 0) {
            // Atualizar produto existente
            error_log("Atualizando produto ID: " . $productId);
            $sql = "UPDATE products SET
                    name = ?, slug = ?, category_id = ?, short_description = ?,
                    description = ?, technical_specs = ?, image_url = ?,
                    unit_price = ?, promotional_price = ?, stock_qty = ?,
                    is_active = ?, featured = ?, is_promotion = ?
                    WHERE id = ?";
            error_log("SQL: " . $sql);

            $stmt = $pdo_instance->prepare($sql); // Usando $pdo_instance
            $result = $stmt->execute([
                $name, $slug, $category_id, $short_description, $description,
                $technical_specs, $image_url, $unit_price, $promotional_price,
                $stock_qty, $is_active, $featured, $is_promotion, $productId
            ]);

            error_log("Resultado da execução UPDATE: " . ($result ? 'SUCESSO' : 'FALHOU'));
            error_log("Linhas afetadas: " . $stmt->rowCount());

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Produto atualizado com sucesso!'];
        } else {
            // Inserir novo produto
            error_log("Inserindo NOVO produto");
            $sql = "INSERT INTO products (
                    name, slug, category_id, short_description, description,
                    technical_specs, image_url, unit_price, promotional_price,
                    stock_qty, is_active, featured, is_promotion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            error_log("SQL: " . $sql);

            $stmt = $pdo_instance->prepare($sql); // Usando $pdo_instance
            $result = $stmt->execute([
                $name, $slug, $category_id, $short_description, $description,
                $technical_specs, $image_url, $unit_price, $promotional_price,
                $stock_qty, $is_active, $featured, $is_promotion
            ]);

            error_log("Resultado da execução INSERT: " . ($result ? 'SUCESSO' : 'FALHOU'));
            error_log("ID do novo produto: " . $pdo_instance->lastInsertId()); // Usando $pdo_instance

            $_SESSION['message'] = ['type' => 'success', 'text' => 'Produto adicionado com sucesso!'];
        }

        error_log("Redirecionando para a listagem de produtos...");
        redirect(getSiteUrl('admin/pages/products.php'));
        exit;

    } catch (PDOException $e) {
        error_log("ERRO PDO: " . $e->getMessage());
        error_log("Código do erro: " . $e->getCode());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao salvar o produto: ' . $e->getMessage()];
        redirect(getSiteUrl('admin/pages/product_form.php' . ($productId ? '?id=' . $productId : '')));
        exit;
    } catch (Exception $e) {
        error_log("ERRO GERAL: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro inesperado: ' . $e->getMessage()];
        redirect(getSiteUrl('admin/pages/product_form.php' . ($productId ? '?id=' . $productId : '')));
        exit;
    }

    error_log("========== FIM DO PROCESSAMENTO DO FORMULÁRIO ==========");
}

// Esta chamada agora deve funcionar corretamente
$categories = getAllActiveCategories();
?>
<!DOCTYPE html>
<html lang="pt-br">
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
            <div class="header">
                <h1><?php echo $pageTitle; ?></h1>
                <form method="POST" action="<?php echo getSiteUrl('admin/logout.php'); ?>" style="display:inline;">
                    <button type="submit" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i>  Sair</button>
                </form>
            </div>
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>

            <div class="product-form-container">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id'] ?? ''); ?>">

                    <div class="form-group">
                        <label for="name">Nome do Produto</label>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category_id">Categoria: *</label>
                        <select id="category_id" name="category_id" required>
                            <option value="">-- Selecione uma Categoria --</option>
                            <?php
                            foreach ($categories as $cat):
                            ?>
                                <option value="<?php echo $cat['id']; ?>"
                                    <?php echo ($product['category_id'] ?? null) == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php
                                    if (!empty($cat['icon'])) {
                                        echo htmlspecialchars($cat['icon']) . ' - ';
                                    }
                                    echo htmlspecialchars($cat['name']);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="slug">SLUG: (URL amigável ex: placa-video-rtx5090):</label>
                        <input type="text" name="slug" id="slug" value="<?php echo htmlspecialchars($product['slug']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="short_description">Descrição Curta:</label>
                        <textarea name="short_description" id="short_description" rows="3"><?php echo htmlspecialchars($product['short_description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="description">Descrição Completa:</label>
                        <textarea name="description" id="description" rows="6"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="technical_specs">Especificações Técnicas:</label>
                        <textarea name="technical_specs" id="technical_specs" rows="4"><?php echo htmlspecialchars($product['technical_specs']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="image_file">Imagem do Produto:</label>
                        <input type="file" id="image_file" name="image_file" accept="image/*">
                        <?php if (!empty($product['image_url'])): ?>
                            <div class="image-preview">
                                <p>Imagem Atual:</p>
                                <img src="<?php echo getSiteUrl($product['image_url']); ?>" alt="Imagem do Produto">
                                <input type="hidden" name="current_image_url" value="<?php echo htmlspecialchars($product['image_url']); ?>">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="unit_price">Preço Unitário</label>
                        <input type="number" name="unit_price" id="unit_price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['unit_price']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="promotional_price">Preço Promocional:</label>
                        <input type="number" name="promotional_price" id="promotional_price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['promotional_price']); ?>" >
                    </div>

                    <div class="form-group">
                        <label for="stock_qty">Em Estoque</label>
                        <input type="number" name="stock_qty" id="stock_qty" min="0" value="<?php echo htmlspecialchars($product['stock_qty']); ?>" required>
                    </div>

                    <div class="form-group">
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?php echo htmlspecialchars($product['is_active']) ? 'checked' : ''; ?>>
                        <label for="is_active">Produto Ativo?</label>
                    </div>

                    <div class="form-group">
                        <input type="checkbox" name="featured" id="featured" value="1" <?php echo htmlspecialchars($product['featured']) ? 'checked' : ''; ?>>
                        <label for="featured">Produto em Destaque</label>
                    </div>

                    <div class="form-group">
                        <input type="checkbox" name="is_promotion" id="is_promotion" value="1" <?php echo htmlspecialchars($product['is_promotion']) ? 'checked' : ''; ?>>
                        <label for="is_promotion">Em Promoção</label>
                    </div>

                    <div class="form-actions">
                        <button type="submit"> <i class="fa-solid fa-save"></i> Salvar Produto</button>
                        <a href="<?php echo getSiteUrl('admin/pages/products.php'); ?>" class="cancel-btn"><i class="fa-solid fa-times"></i> Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>