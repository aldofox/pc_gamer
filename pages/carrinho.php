<?php
// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

// Garante a Inicializaçao da Sessao
ensureSessionStarted();

//Inicializa o carrinho se nao Existir
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

//Logica para processar açoes do carrinho
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);

    switch ($action) {
        case 'update_quantity':
            if (updateCartItemQuantity($productId, $quantity)) {
                $_SESSION['success_message'] = '✅ Quantidade atualizado com sucesso.';
            } else {
                //Se a atualizaçao falhou (Ex: Estoque insuficinte)
                $_SESSION['error_message'] = '❌ Nao foi possivel atualizar a quantidade.';
            }
            break;
        case 'clear_cart': //logica para o botao "Limpar Carrinho"
            unset($_SESSION['cart']);
            $_SESSION['success_message'] = '✅ Carrinho Limpado com Sucesso.';
            break;
    }
    redirect(getSiteUrl('pages/carrinho.php'));
    exit;

    //Calculo de totais (usando $_Session['cart'])
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $priceToUse = $item['promotional_price'] > 0 ? $item['promotional_price'] : $item['unit_price'];
        $subtotal += $priceToUse * $item['quantity'];
    }

    $frete = 0;
    $valorTotal = $subtotal + $frete;

    //Exibir menssagem
        if (isset($_SESSION['sucsess_message'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
    }
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['danger_message']) . '</div>';
        unset($_SESSION['error_message']);
    }

}

// Busca os itens do carrinho
$cartItems = getCartItems();

$cartTotals = getCartTotal(0.0, 0.0);

$subtotal = $cartTotals['subtotal'];
$shipping = $cartTotals['shipping'];
$discount = $cartTotals['discount'];
$total = $cartTotals['total'];

$pageTitle = "Carrinho de Compras - PC GamerStore";
?>

<?php include '../includes/header.php'; ?>

<main>
    <div class="container">
        <h1 class="page-title"><i class="fas fa-shopping"></i> Carrinho de Compras</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])):?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isCartEmpty()): ?>
            <!-- Carrinho Vaziou -->
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Seu Carrinho esta vaziou</h2>
                <p>Adicione produtos ao Carrinho  para Continuar comprando.</p>
                <a href="<?php echo getSiteUrl('index.php'); ?>" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Continuar Comprado</a>
            </div>
        <?php else: ?>
            <!-- Carrinho  com Produtos -->
            <div class="cart-content">
                <!-- Tabela de Produtos -->
                <div class="cart-table-wrapper">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Preço</th>
                                <th>Quantidade</th>
                                <th>Subtotal</th>
                                <th>Açoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                                <?php
                                $price = $item['promotional_price'] ?? $item['unit_price'];
                                $itemSubtotal = $price * $item['quantity'];
                                ?>
                                <tr data-product-id="<?php echo $item['product_id']; ?>">
                                    <td class="product-info">
                                        <img src="<?php echo getSiteUrl($item['image_url']); ?>" 
                                            alt="<?php echo sanitizeInput($item['name']); ?>">
                                        <div class="product-details">
                                            <a href="<?php echo getSiteUrl('pages/produto-detalhes.php?slug=' . $item['slug']); ?>">
                                                <?php echo sanitizeInput($item['name']); ?>
                                            </a>
                                            <span class="stock-info">
                                                <?php if ($item['stock_qty'] > 0): ?>
                                                    Em estoque (<?php echo $item['stock_qty']; ?> disponivel)
                                                <?php else: ?>
                                                    <span class="out-of-stock">Fora de Estoque</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="price">
                                        <?php echo formatCurrency($price); ?>
                                    </td>
                                    <td class="quantity">
                                        <form method="POST" action="<?php echo getSiteUrl('pages/atualizar-carrinho.php'); ?>" class="quantity-form">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            <div class="quantity-input">
                                                <button type="button" class="qty-btn" onclick="decreaseQty(this)">-</button>
                                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                                    min="1" max="<?php echo $item['stock_qty']; ?>" 
                                                    onchange="this.form.submit()">
                                                <button type="button" class="qty-btn" onclick="increaseQty(this, <?php echo $item['stock_qty']; ?>)">+</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="subtotal">
                                        <?php echo formatCurrency($itemSubtotal); ?>
                                    </td>
                                    <td class="actions">
                                        <form method="POST" action="<?php echo getSiteUrl('pages/remover-carrinho.php'); ?>"
                                            onsubmit="return confirm('Tem certeza que deseja remover este item?');">
                                            <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                            <button type="submit" class="btn-remove" title="Remover">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Resumo  do Carrinho -->
                <div class="cart-summary">
                        <h3>Resumo do Pedido</h3>

                    <div class="summary-row">    
                        <span>Subtotal:</span>
                        <span><?php echo formatCurrency($subtotal); ?></span>
                    </div>

                    <?php if ($shipping > 0): ?>
                    <div class="summary-row">
                        <span>Frete:</span>
                        <span><?php echo formatCurrency($shipping); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="summary-row">
                        <span>Frete:</span>
                        <span class="free-shipping">A calcular no Checkout</span>
                    </div>
                    <?php endif; ?>

                    <?php if ($discount > 0 ): ?>
                    <div class="summary-row discount">
                        <span>Desconto:</span>
                        <span>-<?php echo formatCurrency($discount); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span><?php echo formatCurrency($total); ?></span>
                    </div>

                    <!-- Cupom de desconto -->
                    <div class="coupon-section">
                        <input type="text" placeholder="Codigo do Cupom" id="couponCode">
                        <button class="btn btn-secondary" onclick="applyCoupon()">Aplicar</button>
                    </div>

                    <!-- Botoes de Açoes -->
                    <div class="cart-action">
                        <a href="<?php echo getSiteUrl('index.php'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Continuar Comprado</a>
                        <br>
                        <br>
                        <a href="<?php echo getSiteUrl('pages/checkout.php'); ?>" class="btn btn-primary"> Finalizar Compra <i class="fas fa-arrow-right"></i></a>
                    </div>

                    <!-- Limpar Carrinho -->
                    <form method="POST" action="<?php echo getSiteUrl('pages/limpar-carrinho.php'); ?>" onsubmit="return confirm('Tem Certeza que deseja Limpar o Carrinho?');">
                        <button type="submit" class="btn btn-clear-cart"> <i class="fas fa-trash-alt"></i> Limpar Carrinho</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Aumentar quantidade
function increaseQty(btn, max) {
    const input = btn.previousElementSibling;
    if (parseInt(input.value) < max) {
        input.value = parseInt(input.value) + 1;
        input.form.submit();
    }
}

// Diminuir quantidade
function decreaseQty(btn) {
    const input = btn.nextElementSibling;
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
        input.form.submit();
    }
}

// Aplicar cupom (a ser implementado)
function applyCoupon() {
    const code = document.getElementById('couponCode').value;
    if (code) {
        alert('Funcionalidade de cupom será implementada em breve!');
    }
}
</script>

<?php include '../includes/footer.php'; ?>