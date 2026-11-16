<?php
require_once '../includes/functions.php';

$searchQuery = $_GET['q']        ?? '';
$categoryId  = $_GET['category'] ?? '';
$priceMin    = $_GET['price_min'] ?? '';
$priceMax    = $_GET['price_max'] ?? '';
$order       = $_GET['order']    ?? '';
$page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$perPage = 5; // produtos por página

$categories = getAllCategories();

// Inicializa os valores padrão
$searchResults = [];
$totalResults  = 0;
$totalPages    = 1;

// Só busca se tiver pelo menos um filtro preenchido
if (!empty($searchQuery) || !empty($categoryId) || !empty($priceMin) || !empty($priceMax)) {
    list($searchResults, $totalResults) = searchProducts(
        $searchQuery,
        $categoryId,
        $priceMin,
        $priceMax,
        $order,
        $page,
        $perPage
    );
    $totalPages = ($totalResults > 0) ? (int)ceil($totalResults / $perPage) : 1;
}

$pageTitle = "Resultados da Busca" . ($searchQuery ? " por: " . htmlspecialchars($searchQuery) : '');
?>

<?php include '../includes/header.php'; ?>

<main class="container my-5">

    <h1 class="mb-4 text-center">Buscar Produtos</h1>

    <!-- Formulário de Filtros -->
    <form method="GET" action="<?= getSiteUrl('pages/busca.php') ?>" class="search-filters mb-4">
        <div class="row g-3 align-items-end">

            <div class="col-md-4">
                <label for="q" class="form-label">O que você procura?</label>
                <input
                    type="text"
                    id="q"
                    name="q"
                    class="form-control"
                    placeholder="Ex: PC Gamer, Monitor, RTX 4060"
                    value="<?= htmlspecialchars($searchQuery) ?>"
                >
            </div>

            <div class="col-md-3">
                <label for="category" class="form-label">Categoria</label>
                <select id="category" name="category" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($categoryId == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="price_min" class="form-label">Preço mín. (R$)</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    id="price_min"
                    name="price_min"
                    class="form-control"
                    value="<?= htmlspecialchars($priceMin) ?>"
                >
            </div>

            <div class="col-md-2">
                <label for="price_max" class="form-label">Preço máx. (R$)</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    id="price_max"
                    name="price_max"
                    class="form-control"
                    value="<?= htmlspecialchars($priceMax) ?>"
                >
            </div>

            <div class="col-md-3">
                <label for="order" class="form-label">Ordenar por</label>
                <select id="order" name="order" class="form-select">
                    <option value="">Padrão</option>
                    <option value="price_asc"  <?= ($order === 'price_asc')  ? 'selected' : '' ?>>Menor preço</option>
                    <option value="price_desc" <?= ($order === 'price_desc') ? 'selected' : '' ?>>Maior preço</option>
                    <option value="name_asc"   <?= ($order === 'name_asc')   ? 'selected' : '' ?>>Nome (A–Z)</option>
                    <option value="name_desc"  <?= ($order === 'name_desc')  ? 'selected' : '' ?>>Nome (Z–A)</option>
                </select>
            </div>

            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filtrar
                </button>
            </div>

        </div>
    </form>

    <!-- Título dos resultados -->
    <h2 class="mb-4">
        Resultados
        <?php if ($searchQuery): ?>
            para "<?= htmlspecialchars($searchQuery) ?>"
        <?php endif; ?>
        <?php if ($totalResults > 0): ?>
            <small class="text-muted fs-6">
                (<?= $totalResults ?> produto<?= $totalResults > 1 ? 's' : '' ?> encontrado<?= $totalResults > 1 ? 's' : '' ?>)
            </small>
        <?php endif; ?>
    </h2>

    <!-- Cards de Produtos -->
    <?php if (!empty($searchResults)): ?>
        <div class="row">
            <?php foreach ($searchResults as $product): ?>
                <div class="col-md-4 mb-4">
                    <div class="card product-card">
                        <a href="<?= getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']) ?>">
                            <img
                                src="<?= getSiteUrl($product['image_url']) ?>"
                                class="card-img-top"
                                alt="<?= htmlspecialchars($product['name']) ?>"
                            >
                        </a>
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="<?= getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']) ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </a>
                            </h5>
                            <p class="card-text">
                                <?= htmlspecialchars($product['short_description'] ?? '') ?>
                            </p>
                            <div class="price-area">
                                <?php $priceInfo = getProductPrice($product); ?>
                                <?php if ($priceInfo['on_sale']): ?>
                                    <span class="original-price"><?= formatCurrency($priceInfo['original']) ?></span>
                                    <span class="promotional-price"><?= formatCurrency($priceInfo['final']) ?></span>
                                <?php else: ?>
                                    <span class="current-price"><?= formatCurrency($priceInfo['final']) ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="<?= getSiteUrl('pages/produto-detalhes.php?slug=' . $product['slug']) ?>"
                               class="btn btn-primary mt-3">
                                Ver Detalhes
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginação -->
        <?php if ($totalPages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $buildUrl = function(int $pageNum) use ($searchQuery, $categoryId, $priceMin, $priceMax, $order) {
                        $params = ['page' => $pageNum];
                        if ($searchQuery !== '') $params['q']         = $searchQuery;
                        if ($categoryId  !== '') $params['category']  = $categoryId;
                        if ($priceMin    !== '') $params['price_min'] = $priceMin;
                        if ($priceMax    !== '') $params['price_max'] = $priceMax;
                        if ($order       !== '') $params['order']     = $order;
                        return getSiteUrl('pages/busca.php') . '?' . http_build_query($params);
                    };
                    ?>
                    <!-- Anterior -->
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= ($page > 1) ? $buildUrl($page - 1) : '#' ?>" tabindex="-1">
                            &laquo; Anterior
                        </a>
                    </li>
                    <?php
                    $maxVisible = 5;
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $start + $maxVisible - 1);
                    if (($end - $start) < ($maxVisible - 1)) {
                        $start = max(1, $end - $maxVisible + 1);
                    }
                    ?>
                    <?php if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $buildUrl(1) ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $buildUrl($i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $buildUrl($totalPages) ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>

                    <!-- Próxima -->
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= ($page < $totalPages) ? $buildUrl($page + 1) : '#' ?>">
                            Próxima &raquo;
                        </a>
                    </li>

                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <?php if (!empty($searchQuery) || !empty($categoryId) || !empty($priceMin) || !empty($priceMax)): ?>
            <div class="alert alert-info" role="alert">
                Nenhum produto encontrado para os filtros selecionados. Tente outros termos ou categorias.
            </div>
        <?php else: ?>
            <div class="alert alert-secondary" role="alert">
                Use os filtros acima para buscar produtos.
            </div>
        <?php endif; ?>
    <?php endif; ?>

</main>

<?php include '../includes/footer.php'; ?>