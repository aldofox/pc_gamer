<?php
require_once '../includes/functions.php';

// Obtém o slug do produto da URL
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : null;

if (!$slug) {
    redirect(getSiteUrl('index.php'));
    exit;
}

// Busca o produto pelo slug
$product = getProductBySlug($slug);

if (!$product) {
    // Produto não encontrado
    http_response_code(404);
    $pageTitle = "Produto não encontrado";
    include '../includes/header.php';
    ?>
    <main class="container">
        <div class="error-page">
            <h1>❌ Produto não encontrado</h1>
            <p>Desculpe, o produto que você está procurando não existe ou foi removido.</p>
            <a href="<?php echo getSiteUrl('index.php'); ?>" class="btn btn-primary">Voltar à Página Inicial</a>
        </div>
    </main>
    <?php
    include '../includes/footer.php';
    exit;
}

// Obtém informações de preço do produto
$priceInfo = getProductPrice($product);

// Obtém produtos relacionados (mesma categoria)
$relatedProducts = getRelatedProducts($product['category_id'], $product['id'], 6);

// Obtém avaliações do produto
$reviews = getProductReviews($product['id']);

// Calcula a média de avaliações
$avgRating = !empty($reviews) ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;

$pageTitle = $product['name'] . " - GamerStore";
?>

<?php include '../includes/header.php'; ?>

<main>
    <div class="container"> 
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="<?php echo getSiteUrl('index.php'); ?>">Início</a>
            <span>/</span>
            <a href="<?php echo getSiteUrl('pages/categoria.php?cat=' . $product['category_slug']); ?>">
                <?php echo sanitizeInput($product['category_name']); ?>
            </a>
            <span>/</span>
            <span><?php echo sanitizeInput($product['name']); ?></span>
        </div>

        <!-- Produto Principal -->
        <div class="product-detail">
            <!-- Galeria de Imagens -->
            <div class="product-gallery">
                <div class="main-image">
                    <img id="mainImage" src="<?php echo getSiteUrl($product['image_url']); ?>" 
                        alt="<?php echo sanitizeInput($product['name']); ?>">

                    <?php if ($priceInfo['on_sale']): ?>
                        <span class="sale-badge">-<?php echo $priceInfo['discount']; ?>%</span>
                    <?php endif; ?>
                </div>

                <!-- Thumbnails (se houver múltiplas imagens) -->
                <div class="thumbnails">
                    <img src="<?php echo getSiteUrl($product['image_url']); ?>" 
                        alt="<?php echo sanitizeInput($product['name']); ?>" 
                        class="thumbnail active" 
                        onclick="changeMainImage(this.src)">
                </div>
            </div>

            <!-- Informações do Produto -->
            <div class="product-info">
                <!-- Categoria e Avaliação -->
                <div class="product-meta">
                    <span class="category-tag"><?php echo sanitizeInput($product['category_name']); ?></span>
                    <div class="rating">
                        <div class="stars">
                            <?php 
                            $fullStars = floor($avgRating);
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= $fullStars):
                                    echo '<i class="fas fa-star"></i>';
                                else:
                                    echo '<i class="far fa-star"></i>';
                                endif;
                            endfor;
                            ?>
                        </div>
                        <span class="rating-text"><?php echo number_format($avgRating, 1); ?> (<?php echo count($reviews); ?> avaliações)</span>
                    </div>
                </div>

                <!-- Nome do Produto -->
                <h1><?php echo sanitizeInput($product['name']); ?></h1>

                <!-- Descrição Curta -->
                <p class="short-description"><?php echo sanitizeInput($product['short_description']); ?></p>

                <!-- Preço -->
                <div class="price-section">
                    <?php if ($priceInfo['on_sale']): ?>
                        <span class="original-price">De: <?php echo formatCurrency($priceInfo['original']); ?></span>
                        <span class="promotional-price">Por: <?php echo formatCurrency($priceInfo['final']); ?></span>
                        <span class="discount-info">Economia de <?php echo formatCurrency($priceInfo['original'] - $priceInfo['final']); ?></span>
                    <?php else: ?>
                        <span class="current-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Estoque -->
                <div class="stock-info">
                    <?php if ($product['stock_qty'] > 0): ?>
                        <span class="in-stock">✅ Em Estoque (<?php echo $product['stock_qty']; ?> disponível)</span>
                    <?php else: ?>
                        <span class="out-of-stock">❌ Fora de Estoque</span>
                    <?php endif; ?>
                </div>

                <!-- Adicionar ao Carrinho -->
                <div class="add-to-cart-section">
                    <form id="addToCartForm" method="POST" action="<?php echo getSiteUrl('pages/adicionar-carrinho.php'); ?>">
                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                        <div class="quantity-selector">
                            <label for="quantity">Quantidade:</label>
                            <div class="quantity-input">
                                <button type="button" class="qty-btn" onclick="decreaseQuantity()">−</button>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" 
                                    max="<?php echo $product['stock_qty']; ?>" readonly>
                                <button type="button" class="qty-btn" onclick="increaseQuantity(<?php echo $product['stock_qty']; ?>)">+</button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-large" 
                                <?php echo $product['stock_qty'] <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart"></i> Adicionar ao Carrinho
                        </button>
                    </form>

                    <button class="btn btn-secondary btn-wishlist" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                        <i class="far fa-heart"></i> Adicionar à Lista de Desejos
                    </button>
                </div>

                <!-- Informações Adicionais -->
                <div class="additional-info">
                    <div class="info-item">
                        <i class="fas fa-truck"></i>
                        <div>
                            <strong>Frete Grátis</strong>
                            <p>Para compras acima de R$ 299,00</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Garantia</strong>
                            <p>Produtos com garantia de fábrica</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-undo"></i>
                        <div>
                            <strong>Devolução</strong>
                            <p>Até 30 dias após a compra</p>
                        </div>
                    </div>
                </div>

                <!-- Compartilhar -->
                <div class="share-section">
                    <span>Compartilhar:</span>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug'])); ?>" 
                    target="_blank" class="share-btn facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug'])); ?>&text=<?php echo urlencode($product['name']); ?>" 
                    target="_blank" class="share-btn twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://wa.me/?text=<?php echo urlencode($product['name'] . ' - ' . getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug'])); ?>" 
                    target="_blank" class="share-btn whatsapp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Abas de Informações -->
        <div class="product-tabs">
            <div class="tabs-header">
                <button class="tab-btn active" onclick="showTab('description')">Descrição</button>
                <button class="tab-btn" onclick="showTab('specifications')">Especificações</button>
                <button class="tab-btn" onclick="showTab('reviews')">Avaliações (<?php echo count($reviews); ?>)</button>
            </div>

            <!-- Aba: Descrição -->
            <div id="description" class="tab-content active">
                <h3>Descrição do Produto</h3>
                <div class="description-text">
                    <?php echo nl2br(sanitizeInput($product['description'])); ?>
                </div>
            </div>

            <!-- Aba: Especificações -->
            <div id="specifications" class="tab-content">
                <h3>Especificações Técnicas</h3>
                <table class="specs-table">
                    <tr>
                        <td>SKU</td>
                        <td><?= htmlspecialchars($product['sku'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td>Categoria</td>
                        <td><?php echo sanitizeInput($product['category_name']); ?></td>
                    </tr>
                    <tr>
                        <td>Peso</td>
                        <td><?php echo $product['weight'] ? $product['weight'] . ' kg' : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Dimensões</td>
                        <td><?php echo $product['dimensions'] ? sanitizeInput($product['dimension']) : 'N/A'; ?></td>
                    </tr>
                    <tr>
                        <td>Cor</td>
                        <td><?= htmlspecialchars($product['color'] ?? 'N/A') ?></td>
                    </tr>
                </table>
            </div>

            <!-- Aba: Avaliações -->
            <div id="reviews" class="tab-content">
                <h3>Avaliações dos Clientes</h3>

                <?php if (!empty($reviews)): ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item">
                                <div class="review-header">
                                    <strong><?php echo sanitizeInput($review['customer_name']); ?></strong>
                                    <div class="review-stars">
                                        <?php 
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($i <= $review['rating']):
                                                echo '<i class="fas fa-star"></i>';
                                            else:
                                                echo '<i class="far fa-star"></i>';
                                            endif;
                                        endfor;
                                        ?>
                                    </div>
                                    <span class="review-date"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></span>
                                </div>
                                <p class="review-title"><strong><?php echo sanitizeInput($review['title']); ?></strong></p>
                                <p class="review-text"><?php echo sanitizeInput($review['comment']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Nenhuma avaliação ainda. Seja o primeiro a avaliar este produto!</p>
                <?php endif; ?>

                <!-- Formulário para adicionar avaliação (se logado) -->
                <?php if (isLoggedIn()): ?>
                    <div class="add-review-form">
                        <h4>Deixe sua Avaliação</h4>
                        <form method="POST" action="<?php echo getSiteUrl('pages/salvar-avaliacao.php'); ?>">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>"> <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    

                            <div class="form-group">
                                <label for="rating">Classificação:</label>
                                <div class="rating-input">
                                    <input type="radio" name="rating" value="5" id="star5"> <label for="star5">⭐⭐⭐⭐⭐</label>
                                    <input type="radio" name="rating" value="4" id="star4"> <label for="star4">⭐⭐⭐⭐</label>
                                    <input type="radio" name="rating" value="3" id="star3"> <label for="star3">⭐⭐⭐</label>
                                    <input type="radio" name="rating" value="2" id="star2"> <label for="star2">⭐⭐</label>
                                    <input type="radio" name="rating" value="1" id="star1"> <label for="star1">⭐</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="title">Título:</label>
                                <input type="text" id="title" name="title" required placeholder="Resumo da sua avaliação">
                            </div>

                            <div class="form-group">
                                <label for="comment">Comentário:</label>
                                <textarea id="comment" name="comment" rows="5" required placeholder="Compartilhe sua experiência com este produto..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Enviar Avaliação</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p><a href="<?php echo getSiteUrl('auth/login.php'); ?>">Faça login</a> para deixar uma avaliação.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Produtos Relacionados -->
        <?php if (!empty($relatedProducts)): ?>
            <section class="related-products">
                <h2>Produtos Relacionados</h2>
                <div class="swiper-container product-carousel">
                    <div class="swiper-wrapper">
                        <?php foreach ($relatedProducts as $relProduct): ?>
                            <?php $relPriceInfo = getProductPrice($relProduct); ?>
                            <div class="swiper-slide product-card">
                                <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $relProduct['slug']); ?>">
                                    <img src="<?php echo getSiteUrl($relProduct['image_url']); ?>" 
                                        alt="<?php echo sanitizeInput($relProduct['name']); ?>">
                                </a>
                                <div class="card-content">
                                    <h3 class="product-name">
                                        <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $relProduct['slug']); ?>">
                                            <?php echo sanitizeInput($relProduct['name']); ?>
                                        </a>
                                    </h3>
                                    <div class="price-area">
                                        <?php if ($relPriceInfo['on_sale']): ?>
                                            <span class="original-price"><?php echo formatCurrency($relPriceInfo['original']); ?></span>
                                            <span class="promotional-price"><?php echo formatCurrency($relPriceInfo['final']); ?></span>
                                        <?php else: ?>
                                            <span class="current-price"><?php echo formatCurrency($relPriceInfo['final']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $relProduct['slug']); ?>" 
                                    class="btn btn-details">Ver Detalhes</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>
<script>
    //Para Aumentar a Quantidade
    function increaseQuantity(max){
        const input = document.getElementById('quantity');
        if (parseInt(input.value) < max) {
            input.value = parseInt(input.value) + 1;
        }
    }

    //Aqui serve para Diminuir a Quantidade
    function decreaseQuantity() {
        const input = document.getElementById('quantity');
        if(parseInt(input.value) > 1) {
            input.value = parseInt(input.value) - 1;
        }
    }

    //Muda a Imagem Principal
    function showTab(tabName){
        //Oculta todas as Abas
        document.querySelectorAll('.tab-content').forEach(tab=> {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.tab-btn').forEach(btn=> {
            btn.classList.remove('active');
        });
        //Mostra a aba  selecionado
        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');
    }

    //Adicona a Lista de Desejos
    function addToWishlist(productId) {
        //Implementar a logica de lista de Desejos
        alert('Adicionado a Lista de Desejo.');
    }
</script>

<?php include '../includes/footer.php'; ?>