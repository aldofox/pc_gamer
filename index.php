<?php
require_once 'includes/functions.php'; // Inclui todas as funções e configs

$banners = getActiveBanners(); 
$featuredProducts = getFeaturedProducts(6); // Produtos em destaque
$promotionalProducts = getPromotionalProducts(6); // Produtos em promoção
$categories = getAllCategories(); // Todas as categorias para os carrosséis

?>

<?php include 'includes/header.php';?>

<main>
    <!-- Seção de Banners (Carrossel Principal) -->
    <section class="hero-banner swiper-container">
    <div class="swiper-wrapper">
        <?php if (!empty($banners)): ?>
            <?php foreach ($banners as $banner): ?>
                <div class="swiper-slide banner-item">
                    <div class="container">
                        <!-- IMAGEM DO PRODUTO À ESQUERDA -->
                        <div class="banner-product-image">
                            <img src="<?php echo getSiteUrl($banner['image_url']); ?>" 
                                alt="<?php echo sanitizeInput($banner['product_name']); ?>">
                        </div>

                        <!-- CONTEÚDO À DIREITA -->
                        <div class="banner-content">
                            <h1><?php echo sanitizeInput($banner['title']); ?></h1>
                            <p><?php echo sanitizeInput($banner['subtitle']); ?></p>

                            <div class="banner-price">
                                <?php 
                                $priceInfo = getProductPrice($banner);
                                if ($priceInfo['on_sale']): ?>
                                    <span class="original-price"><?php echo formatCurrency($priceInfo['original']); ?></span>
                                    <span class="promotional-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                                    <span class="discount-badge">-<?php echo $priceInfo['discount']; ?>%</span>
                                <?php else: ?>
                                    <span class="current-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                                <?php endif; ?>
                            </div>

                            <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $banner['product_slug']); ?>" 
                                class="btn-primary">
                                Ver Detalhes <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Banner padrão se não houver banners cadastrados -->
            <div class="swiper-slide banner-item no-banner">
                <div class="container">
                    <div class="banner-content" style="text-align: center; width: 100%;">
                        <h1>Bem-vindo à GameStore!</h1>
                        <p>Sua melhor escolha em equipamentos gamers.</p>
                        <a href="<?php echo getSiteUrl('pages/categoria.php?cat=pcs-gamers'); ?>" class="btn-primary">
                            Conheça nossos PCs <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Controles do Swiper -->
    <div class="swiper-pagination"></div>
    <div class="swiper-button-next"></div>
    <div class="swiper-button-prev"></div>
</section>


    <!-- Seção de Produtos em Destaque (Carrossel) -->
    <section class="product-carousel-section">
        <div class="container">
            <h2 class="section-title"><i class="fas fa-star"></i> Produtos em Destaque</h2>
            <div class="swiper product-carousel">
                <div class="swiper-wrapper">
                    <?php if (!empty($featuredProducts)): ?>
                        <?php foreach ($featuredProducts as $product): ?>
                            <div class="swiper-slide product-card">
                                <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>">
                                    <img src="<?php echo getSiteUrl($product['image_url']); ?>" alt="<?php echo sanitizeInput($product['name']); ?>">
                                </a>
                                <div class="card-content">
                                    <h3 class="product-name"><a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>"><?php echo sanitizeInput($product['name']); ?></a></h3>
                                    <p class="short-description"><?php echo sanitizeInput($product['short_description']); ?></p>
                                    <div class="price-area">
                                        <?php 
                                        $priceInfo = getProductPrice($product);
                                        if ($priceInfo['on_sale']): ?>
                                            <span class="original-price"><?php echo formatCurrency($priceInfo['original']); ?></span>
                                            <span class="promotional-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                                        <?php else: ?>
                                            <span class="current-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>" class="btn btn-details">Ver Detalhes</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-products">Nenhum produto em destaque no momento.</p>
                    <?php endif; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>
    </section>

    <!-- Seção de Produtos em Promoção (Carrossel) -->
    <section class="product-carousel-section bg-light">
        <div class="container">
            <h2 class="section-title"><i class="fas fa-fire"></i> Ofertas Imperdíveis</h2>
            <div class="swiper product-carousel">
                <div class="swiper-wrapper">
                    <?php if (!empty($promotionalProducts)): ?>
                        <?php foreach ($promotionalProducts as $product): ?>
                            <div class="swiper-slide product-card">
                                <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>">
                                    <img src="<?php echo getSiteUrl($product['image_url']); ?>" alt="<?php echo sanitizeInput($product['name']); ?>">
                                </a>
                                <div class="card-content">
                                    <h3 class="product-name"><a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>"><?php echo sanitizeInput($product['name']); ?></a></h3>
                                    <p class="short-description"><?php echo sanitizeInput($product['short_description']); ?></p>
                                    <div class="price-area">
                                        <?php 
                                        $priceInfo = getProductPrice($product);
                                        if ($priceInfo['on_sale']): ?>
                                            <span class="original-price"><?php echo formatCurrency($priceInfo['original']); ?></span>
                                            <span class="promotional-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                                        <?php else: ?>
                                            <span class="current-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>" class="btn btn-details">Ver Detalhes</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-products">Nenhuma oferta imperdível no momento.</p>
                    <?php endif; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>
    </section>

    <!-- Seções de Carrosséis por Categoria (PCs Gamers, Notebooks, Periféricos, etc.) -->
    <?php 
    // Mapeamento de slugs para IDs de categoria (você pode buscar isso dinamicamente se preferir)
    $categorySlugs = [
        'pcs-gamers' => 'PCs Gamers',
        'notebooks' => 'Notebooks',
        'placas-de-video' => 'Placas de Vídeo',
        'perifericos' => 'Periféricos',
        'monitores' => 'Monitores',
        'headsets' => 'Headsets',
        'cadeiras-gamer' => 'Cadeiras Gamer'
    ];

    foreach ($categorySlugs as $slug => $categoryName):
        $category = getCategoryBySlug($slug); // Busca a categoria pelo slug
        if ($category):
            $categoryProducts = getProductsForCarouselByCategory($category['id'], 6);
            if (!empty($categoryProducts)):
    ?>
                <section class="product-carousel-section <?php echo ($category['id'] % 2 == 0) ? 'bg-light' : ''; ?>">
                    <div class="container">
                        <h2 class="section-title"><i class="fas <?php echo sanitizeInput($category['icon'] ?? 'fa-gamepad'); ?>"></i> <?php echo sanitizeInput($categoryName); ?></h2>
                        <div class="swiper product-carousel">
                            <div class="swiper-wrapper">
                                <?php foreach ($categoryProducts as $product): ?>
                                    <div class="swiper-slide product-card">
                                        <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>">
                                            <img src="<?php echo getSiteUrl($product['image_url']); ?>" alt="<?php echo sanitizeInput($product['name']); ?>">
                                        </a>
                                        <div class="card-content">
                                            <h3 class="product-name"><a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>"><?php echo sanitizeInput($product['name']); ?></a></h3>
                                            <p class="short-description"><?php echo sanitizeInput($product['short_description']); ?></p>
                                            <div class="price-area">
                                                <?php 
                                                $priceInfo = getProductPrice($product);
                                                if ($priceInfo['on_sale']): ?>
                                                    <span class="original-price"><?php echo formatCurrency($priceInfo['original']); ?></span>
                                                    <span class="promotional-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                                                <?php else: ?>
                                                    <span class="current-price"><?php echo formatCurrency($priceInfo['final']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']); ?>" class="btn btn-details">Ver Detalhes</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                    </div>
                </section>
    <?php 
            endif; // Fim if !empty($categoryProducts)
        endif; // Fim if $category
    endforeach; 
    ?>

</main>

<?php include 'includes/footer.php'; ?>

<!-- Scripts do Swiper e JS customizado -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
