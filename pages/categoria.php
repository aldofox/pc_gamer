<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- DEBUG: Início de pages/produto-detalhes.php -->\n";
echo "<!-- DEBUG: GET params: " . htmlspecialchars(json_encode($_GET)) . " -->\n";

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/header.php';

$categoria = $_GET['cat'] ?? '';

// Mapeia o 'cat' da URL para o category_id do banco de dados
$categoriasMap = [
    'pcs'          => ['id' => 1, 'nome' => 'PCs Gamers'],
    'notebooks'    => ['id' => 2, 'nome' => 'Notebooks Gamers'],
    'placas_video' => ['id' => 3, 'nome' => 'Placas de Vídeo'],
    'perifericos'  => ['id' => 4, 'nome' => 'Periféricos'],
    'monitores'    => ['id' => 5, 'nome' => 'Monitores'],
    'headsets'     => ['id' => 6, 'nome' => 'Headsets Gamers'], // Adicionei headsets
    'cadeiras'     => ['id' => 7, 'nome' => 'Cadeiras Gamer'],   // Adicionei cadeiras
];

if (!array_key_exists($categoria, $categoriasMap)) {
    // Redireciona para a home se a categoria for inválida
    header('Location: ' . getSiteUrl('index.php'));
    exit;
}

$categoryId    = $categoriasMap[$categoria]['id'];
$nomeCategoria = $categoriasMap[$categoria]['nome'];

try {
    $sql = "SELECT id, name, unit_price, promotional_price, image_url, stock_qty 
            FROM products 
            WHERE category_id = ? AND is_active = 1
            ORDER BY name ASC";

    $produtos = db()->fetchAll($sql, [$categoryId]);

} catch (Exception $e) {
    error_log("Erro ao buscar produtos da categoria: " . $e->getMessage());
    $produtos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($nomeCategoria); ?> - GamerStore</title>
    <!-- O CSS principal já deve ser incluído pelo header.php -->
    <!-- Se você precisar de CSS específico para esta página, adicione aqui -->
    <style>
        /* Seus estilos CSS específicos para a página de categoria */
        .categoria-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .categoria-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .categoria-header h1 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 10px;
        }
        .categoria-header p {
            color: #666;
            font-size: 1.1em;
        }
        .produtos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .produto-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }
        .produto-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .produto-imagem {
            width: 90%;
            height: 250px;
            object-fit: contain;
            background: #f5f5f5;
        }
        .produto-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .produto-nome {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            min-height: 50px;
        }
        .produto-preco {
            margin-top: auto;
        }
        .preco-original {
            text-decoration: line-through;
            color: #999;
            font-size: 0.9em;
        }
        .preco-promocional {
            color: #e74c3c;
            font-size: 1.5em;
            font-weight: bold;
        }
        .preco-normal {
            color: #27ae60;
            font-size: 1.5em;
            font-weight: bold;
        }
        .produto-estoque {
            margin-top: 10px;
            font-size: 0.9em;
        }
        .em-estoque {
            color: #27ae60;
        }
        .estoque-baixo {
            color: #f39c12;
        }
        .sem-estoque {
            color: #e74c3c;
        }
        .btn-ver-detalhes {
            display: block;
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            text-align: center;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 15px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
        }
        .btn-ver-detalhes:hover {
            background: #0056b3;
        }
        .sem-produtos {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .sem-produtos i {
            font-size: 4em;
            color: #ddd;
            margin-bottom: 20px;
        }
        .sem-produtos h2 {
            font-size: 1.8em;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="categoria-container">
        <div class="categoria-header">
            <h1><?php echo htmlspecialchars($nomeCategoria); ?></h1>
            <p>Encontramos <?php echo count($produtos); ?> produto(s) nesta categoria</p>
        </div>

        <?php if (empty($produtos)): ?>
            <div class="sem-produtos">
                <i class="fas fa-box-open"></i>
                <h2>Nenhum produto encontrado</h2>
                <p>Não há produtos disponíveis nesta categoria no momento.</p>
                <a href="<?php echo getSiteUrl('index.php'); ?>" class="btn-ver-detalhes" style="max-width: 300px; margin: 20px auto;">
                    Voltar para a Home
                </a>
            </div>
        <?php else: ?>
            <div class="produtos-grid">
                <?php foreach ($produtos as $produto): ?>
                    <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?id=' . $produto['id']); ?>" class="produto-card">
                        <img src="<?php echo getSiteUrl($produto['image_url']); ?>"
                            alt="<?php echo htmlspecialchars($produto['name']); ?>"
                            class="produto-imagem"
                            onerror="this.src='<?php echo getSiteUrl('assets/images/placeholder.jpg'); ?>'">

                        <div class="produto-info">
                            <div class="produto-nome">
                                <?php echo htmlspecialchars($produto['name']); ?>
                            </div>

                            <div class="produto-preco">
                                <?php if ($produto['promotional_price'] > 0 && $produto['promotional_price'] < $produto['unit_price']): ?>
                                    <div class="preco-original">
                                        De: R$ <?php echo number_format($produto['unit_price'], 2, ',', '.'); ?>
                                    </div>
                                    <div class="preco-promocional">
                                        Por: R$ <?php echo number_format($produto['promotional_price'], 2, ',', '.'); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="preco-normal">
                                        R$ <?php echo number_format($produto['unit_price'], 2, ',', '.'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="produto-estoque">
                                <?php if ($produto['stock_qty'] > 10): ?>
                                    <span class="em-estoque">✓ Em estoque</span>
                                <?php elseif ($produto['stock_qty'] > 0): ?>
                                    <span class="estoque-baixo">⚠ Últimas unidades (<?php echo $produto['stock_qty']; ?>)</span>
                                <?php else: ?>
                                    <span class="sem-estoque">✗ Indisponível</span>
                                <?php endif; ?>
                            </div>

                            <span class="btn-ver-detalhes">Ver Detalhes</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php require_once ABSPATH . 'includes/footer.php'; ?>
</body>
</html>