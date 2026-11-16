<?php
// pages/checkout.php
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}


require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php'; 
require_once ABSPATH . 'includes/functions.php';

ensureSessionStarted();

// Redireciona se o Carrinho estiver vazio
if (empty($_SESSION['cart'])) {
    $_SESSION['error_message'] = 'Seu Carrinho está Vazio. Adicione produtos antes de finalizar a compra.';
    redirect(getSiteUrl('pages/carrinho.php')); // Redireciona para a página do carrinho
    exit;
}

$pageTitle = 'Finalizar Compra'; // Definido aqui para ser usado antes do POST

// Lógica para processar o formulário de checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Coleta e sanitiza os dados do formulário
    $customerName = sanitizeInput($_POST['customer_name'] ?? '');
    $customerEmail = sanitizeInput($_POST['customer_email'] ?? '');
    $customerPhone = sanitizeInput($_POST['customer_phone'] ?? '');
    $shippingAddress = sanitizeInput($_POST['shipping_address'] ?? '');
    $shippingCity = sanitizeInput($_POST['shipping_city'] ?? '');
    $shippingState = sanitizeInput($_POST['shipping_state'] ?? '');
    $shippingZipcode = sanitizeInput($_POST['shipping_zipcode'] ?? '');
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');

    // NOVO: Captura o método de frete e o custo do frete
    $shippingMethod = sanitizeInput($_POST['shipping_method'] ?? '');
    $shippingCost = (float)($_POST['shipping_cost_hidden'] ?? 0);

    // NOVO: Captura o número de parcelas
    $installments = (int)($_POST['installments'] ?? 1);


    // Validação Básica
    if (empty($customerName) || empty($customerEmail) || empty($shippingAddress) ||
        empty($shippingCity) || empty($shippingState) || empty($shippingZipcode) ||
        empty($paymentMethod)) {
            $_SESSION['error_message'] = 'Por favor, preencha todos os campos obrigatórios.';
            redirect(getSiteUrl('pages/checkout.php'));
            exit;
        }

    // Validação do frete: se o frete for maior que zero, mas o método não foi selecionado
    if ($shippingCost > 0 && empty($shippingMethod)) {
        $_SESSION['error_message'] = 'Por favor, selecione uma opção de frete.';
        redirect(getSiteUrl('pages/checkout.php'));
        exit;
    }

    // Calcula totais do Pedido
    $subTotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $priceToUse = ($item['promotional_price'] > 0 && ($item['is_promotion'] ?? false)) ? (float)$item['promotional_price'] : (float)$item['unit_price'];
        $subTotal += $priceToUse * $item['quantity'];
    }

    $discount = 0; // Lógica do Cupom (ainda a ser implementada)
    $total = $subTotal + $shippingCost - $discount; // AGORA COM O FRETE REAL

    // Gera um numero de pedido unico
    $orderNumber = 'PED-' . uniqid();

    // Preparar o snapshot do endereço (incluindo email e nome do cliente)
    $shippingAddressSnapshot = json_encode([
        'name'      => $customerName,
        'email'     => $customerEmail,
        'phone'     => $customerPhone,
        'address'   => $shippingAddress,
        'city'      => $shippingCity,
        'state'     => $shippingState,
        'zipcode'   => $shippingZipcode
    ]);

    try {
        $db = db(); // Obtém a instância da sua classe Database
        $pdo_instance = $db->getPdo(); // Obtém a instância PDO subjacente

        if (!$pdo_instance) {
            throw new Exception("Conexão PDO não disponível.");
        }

        $pdo_instance->beginTransaction(); // Inicia a transação

        // Inserir o Pedido na Tabela orders
        // ATUALIZADO: Incluindo shipping_method e installments
        $stmt = $pdo_instance->prepare("
            INSERT INTO orders (
                user_id, order_number, status, subtotal, shipping_cost, discount, total,
                payment_method, installments, shipping_method, shipping_address_id, shipping_address_snapshot
            ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            getUserId(),                                // 1 - user_id (usando getUserId() para usuários logados)
            $orderNumber,                               // 2 - order_number
            $subTotal,                                  // 3 - subtotal
            $shippingCost,                              // 4 - shipping_cost (AGORA É O VALOR REAL)
            $discount,                                  // 5 - discount
            $total,                                     // 6 - total
            $paymentMethod,                             // 7 - payment_method
            $installments,                              // 8 - installments (AGORA É O VALOR REAL)
            $shippingMethod,                            // 9 - shipping_method (NOVO)
            null,                                       // 10 - shipping_address_id (se não estiver usando a tabela addresses)
            $shippingAddressSnapshot                    // 11 - shipping_address_snapshot
        ]);
        $orderId = $pdo_instance->lastInsertId(); // Pega o ID do pedido inserido

        // Inserir os itens do carrinho na tabela order_items
        foreach ($_SESSION['cart'] as $item) {
            $priceAtPurchase = ($item['promotional_price'] > 0 && ($item['is_promotion'] ?? false)) ? (float)$item['promotional_price'] : (float)$item['unit_price'];
            $totalLine = $priceAtPurchase * $item['quantity'];
            $productImage = $item['image_url'] ?? null; // Pega a URL da imagem do item do carrinho

            $stmt = $pdo_instance->prepare("
                    INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, unit_price, total_line)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                $item['product_id'],
                $item['name'],
                $productImage,
                $item['quantity'],
                $priceAtPurchase, // Preço que o cliente realmente pagou (promocional ou unitário)
                $totalLine
            ]);

            // Atualizar estoque do produto
            $stmt = $pdo_instance->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['product_id']]);
        }

        $pdo_instance->commit(); // Confirma a transação
        unset($_SESSION['cart']); // Limpa o carrinho apos o pedido
        $_SESSION['success_message'] = '✅ Pedido realizado com sucesso! Número do Pedido: ' . $orderNumber;
        redirect(getSiteUrl('pages/order_confirmation.php?order_id=' . $orderId)); // Redireciona para a confirmação
        exit;
    } catch (Exception $e) { // Captura Exception genérica, incluindo PDOException
        if (isset($pdo_instance) && $pdo_instance->inTransaction()) {
            $pdo_instance->rollBack(); // Desfaz a transação em caso de erro
        }
        error_log("Erro ao finalizar pedido: " . $e->getMessage());
        $_SESSION['error_message'] = '❌ Erro ao finalizar o pedido. Por favor, tente novamente. Detalhes: ' . $e->getMessage();
        redirect(getSiteUrl('pages/checkout.php'));
        exit;
    }
}

// Cálculo de totais para exibição no formulário (fora do bloco POST)
// Estes valores serão atualizados via JS após mudanças no carrinho, frete, etc.
$subTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $priceToUse = ($item['promotional_price'] > 0 && ($item['is_promotion'] ?? false)) ? (float)$item['promotional_price'] : (float)$item['unit_price'];
    $subTotal += $priceToUse * $item['quantity'];
}
$shippingCost = 0; // Valor inicial, será atualizado via JS
$discount = 0; // Valor inicial, será atualizado via JS
$total = $subTotal + $shippingCost - $discount;

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <?php include ABSPATH . 'includes/header.php'; ?>

    <div class="container checkout-page">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                unset($_SESSION['error_message']);
            }
        ?>

        <div class="checkout-content">
            <div class="shipping-details">
                <h2 class="section-title">Detalhes de Entrega</h2>
                <form id="checkoutForm" action="<?php echo getSiteUrl('pages/checkout.php'); ?>" method="POST">

                    <div class="form-group">
                        <label for="customer_name">Nome Completo</label>
                        <input type="text" name="customer_name" id="customer_name" required value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="customer_email">Email</label>
                        <input type="email" name="customer_email" id="customer_email" required value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="customer_phone">Telefone</label>
                        <input type="tel" name="customer_phone" id="customer_phone" required value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="shipping_address">Endereço</label>
                        <input type="text" name="shipping_address" id="shipping_address" placeholder="Rua, Numero, Bairro" value="<?php echo htmlspecialchars($_POST['shipping_address'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="shipping_city">Cidade</label>
                        <input type="text" name="shipping_city" id="shipping_city" required value="<?php echo htmlspecialchars($_POST['shipping_city'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="shipping_state">Estado</label>
                        <input type="text" name="shipping_state" id="shipping_state" required value="<?php echo htmlspecialchars($_POST['shipping_state'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="shipping_zipcode">CEP</label>
                        <input type="text" name="shipping_zipcode" id="shipping_zipcode" required value="<?php echo htmlspecialchars($_POST['shipping_zipcode'] ?? ''); ?>">
                    </div>

                    <!-- Seção de Frete -->
                    <h2 class="section-title">Opções de Frete</h2>
                    <div class="form-group">
                        <label for="cep_input">Calcular Frete (CEP)</label>
                        <input type="text" id="cep_input" name="cep_input" placeholder="Ex: 00000-000" value="">
                        <button type="button" id="calculate_shipping_btn" class="btn-secondary mt-2">Calcular Frete</button>
                        <div id="shipping_options" class="mt-3">
                            <!-- Opções de frete serão carregadas aqui via JS -->
                            <p>Insira seu CEP para ver as opções de frete.</p>
                        </div>
                        <input type="hidden" name="shipping_method" id="shipping_method_hidden">
                        <input type="hidden" name="shipping_cost_hidden" id="shipping_cost_hidden" value="0">
                    </div>

                    <h2 class="section-title">Método de Pagamento</h2>
                    <div class="form-group">
                        <label for="payment_method">Escolha o método de Pagamento</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="" <?php echo (($_POST['payment_method'] ?? '') == '') ? 'selected' : '';?>>-- Selecione --</option>
                            <option value="pix" <?php echo (($_POST['payment_method'] ?? '') == 'pix') ? 'selected' : '';?>>PIX</option>
                            <option value="boleto" <?php echo (($_POST['payment_method'] ?? '') == 'boleto') ? 'selected' : '';?>>Boleto Bancário</option>
                            <option value="credit_card" <?php echo (($_POST['payment_method'] ?? '') == 'credit_card') ? 'selected' : '';?>>Cartão de Crédito</option>
                            <option value="debit_card" <?php echo (($_POST['payment_method'] ?? '') == 'debit_card') ? 'selected' : '';?>>Cartão de Débito</option>
                        </select>
                    </div>

                    <!-- Seção de Parcelamento (visível apenas para Cartão de Crédito) -->
                    <div id="installments_section" style="display: none;">
                        <div class="form-group">
                            <label for="installments">Número de Parcelas</label>
                            <select name="installments" id="installments" class="form-control">
                                <!-- Opções de parcelamento serão carregadas via JS -->
                                <option value="1">1x de R$ <?php echo number_format($total, 2, ',', '.'); ?> (à vista)</option>
                            </select>
                        </div>
                    </div>

                    <div class="order-summary">
                        <h2 class="section-title">Resumo do Pedido</h2>
                        <div class="summary-item">
                            <span>Subtotal</span>
                            <span id="summary_subtotal">R$ <?php echo number_format($subTotal, 2, ',' , '.'); ?></span>
                        </div>

                        <div class="summary-item">
                            <span>Frete</span>
                            <span id="summary_shipping">R$ <?php echo number_format($shippingCost, 2, ',' , '.'); ?></span>
                        </div>

                        <?php if ($discount > 0): // Apenas exibe se houver desconto ?>
                            <div class="summary-item discount">
                                <span>Desconto</span>
                                <span id="summary_discount">- R$ <?php echo number_format($discount,2, ',', '.'); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="summary-item total">
                            <span>Total:</span>
                            <span id="summary_total">R$ <?php echo number_format($total, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary btn-checkout">Finalizar Pedido</button>
                </form>
            </div>

            <div class="cart-items-review">
                <h2 class="section-title">Itens no Carrinho</h2>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <table class="cart-review-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Preço Unit.</th>
                                <th>Qtd.</th>
                                <th>Subtotal</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="cart_items_tbody">
                            <?php foreach ($_SESSION['cart'] as $item): ?>
                                <?php
                                    $priceToDisplay = ($item['promotional_price'] > 0 && ($item['is_promotion'] ?? false)) ? $item['promotional_price'] : $item['unit_price'];
                                    $itemSubtotal = $priceToDisplay * $item['quantity'];
                                ?>
                                <tr data-product-id="<?= $item['product_id'] ?>">
                                    <td class="product-info" data-label="Produto">
                                        <img src="<?= getSiteUrl($item['image_url']); ?>" alt="<?= htmlspecialchars($item['name']); ?>">
                                        <span><?= htmlspecialchars($item['name']); ?></span>
                                    </td>
                                    <td class="price-unit" data-label="Preço Unit.">R$ <?= number_format($priceToDisplay, 2, ',', '.'); ?></td>
                                    <td class="quantity-control" data-label="Qtd.">
                                        <button type="button" class="quantity-btn decrease-quantity" data-product-id="<?= $item['product_id'] ?>">-</button>
                                        <input type="number" class="quantity-input" value="<?= htmlspecialchars($item['quantity']); ?>" min="1" data-product-id="<?= $item['product_id'] ?>">
                                        <button type="button" class="quantity-btn increase-quantity" data-product-id="<?= $item['product_id'] ?>">+</button>
                                    </td>
                                    <td class="item-subtotal" data-label="Subtotal">R$ <?= number_format($itemSubtotal, 2, ',', '.'); ?></td>
                                    <td class="item-actions" data-label="Ações">
                                        <button type="button" class="remove-item-btn" data-product-id="<?= $item['product_id'] ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p id="empty_cart_message">Seu carrinho está vazio.</p>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include ABSPATH . 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cartItemsTbody = document.getElementById('cart_items_tbody');
            const summarySubtotalSpan = document.getElementById('summary_subtotal');
            const summaryShippingSpan = document.getElementById('summary_shipping');
            const summaryDiscountSpan = document.getElementById('summary_discount'); // NOVO
            const summaryTotalSpan = document.getElementById('summary_total');
            const cartCountSpan = document.getElementById('cartCount'); // Do header
            const emptyCartMessage = document.getElementById('empty_cart_message');
            const checkoutContent = document.querySelector('.checkout-content'); // Para ocultar tudo se o carrinho esvaziar

            const paymentMethodSelect = document.getElementById('payment_method');
            const installmentsSection = document.getElementById('installments_section');
            const installmentsSelect = document.getElementById('installments');
            const shippingCostHiddenInput = document.getElementById('shipping_cost_hidden');
            const shippingMethodHiddenInput = document.getElementById('shipping_method_hidden');
            const cepInput = document.getElementById('cep_input');

            // Variáveis para armazenar os valores atuais do resumo
            let currentSubtotal = parseFloat(summarySubtotalSpan.textContent.replace('R$', '').replace('.', '').replace(',', '.'));
            let currentShippingCost = parseFloat(shippingCostHiddenInput.value || '0');
            let currentDiscount = 0; // Por enquanto, sem lógica de cupom

            // Função auxiliar para formatar números (PHP number_format equivalente)
            function number_format(number, decimals, dec_point, thousands_sep) {
                number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
                var n = !isFinite(+number) ? 0 : +number,
                    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
                    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
                    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
                    s = '',
                    toFixedFix = function(n, prec) {
                        var k = Math.pow(10, prec);
                        return '' + Math.round(n * k) / k;
                    };

                s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
                if (s[0].length > 3) {
                    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
                }
                if ((s[1] || '').length < prec) {
                    s[1] = s[1] || '';
                    s[1] += new Array(prec - s[1].length + 1).join('0');
                }
                return s.join(dec);
            }

            // Função para atualizar o resumo do pedido
            function updateOrderSummaryDisplay() {
                const total = currentSubtotal + currentShippingCost - currentDiscount;
                summarySubtotalSpan.textContent = `R$ ${number_format(currentSubtotal, 2, ',', '.')}`;
                summaryShippingSpan.textContent = `R$ ${number_format(currentShippingCost, 2, ',', '.')}`;
                if (summaryDiscountSpan) {
                    summaryDiscountSpan.textContent = `- R$ ${number_format(currentDiscount, 2, ',', '.')}`;
                }
                summaryTotalSpan.textContent = `R$ ${number_format(total, 2, ',', '.')}`;

                // Atualiza as opções de parcelamento se for cartão de crédito
                if (paymentMethodSelect.value === 'credit_card') {
                    loadInstallmentOptions(total);
                }
            }

            // Função para enviar requisição AJAX para atualizar o carrinho
            async function updateCart(productId, action, quantity = 0) {
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('action', action);
                if (action === 'update_quantity') {
                    formData.append('quantity', quantity);
                }

                try {
                    const response = await fetch('<?php echo getSiteUrl('actions/update_cart.php'); ?>', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status} - ${response.statusText}`);
                    }

                    const data = await response.json();
                    console.log('Resposta update_cart:', data);

                    if (!data.success) {
                        alert('Não foi possível atualizar o carrinho: ' + (data.message || 'Erro desconhecido.'));
                        return;
                    }

                    const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                    const itemKey = String(productId);

                    if (data.cart_items && data.cart_items[itemKey]) {
                        // Item ainda existe no carrinho, atualiza a linha
                        const item = data.cart_items[itemKey];
                        const priceToDisplay = (item.promotional_price > 0 && item.is_promotion)
                            ? parseFloat(item.promotional_price)
                            : parseFloat(item.unit_price);
                        const itemSubtotal = priceToDisplay * item.quantity;

                        if (row) {
                            row.querySelector('.quantity-input').value = item.quantity;
                            row.querySelector('.item-subtotal').textContent = `R$ ${number_format(itemSubtotal, 2, ',', '.')}`;
                        }
                    } else if (row) {
                        // Item foi removido, remove a linha da tabela
                        row.remove();
                    }

                    // Atualiza o subtotal global e o contador de itens
                    currentSubtotal = data.cart_total_raw;
                    if (cartCountSpan) {
                        cartCountSpan.textContent = data.item_count;
                    }

                    // Se o carrinho ficou vazio, redireciona
                    if (data.item_count === 0) {
                        if (checkoutContent) checkoutContent.style.display = 'none';
                        if (emptyCartMessage) {
                            emptyCartMessage.style.display = 'block';
                            emptyCartMessage.textContent = 'Seu carrinho está vazio. Redirecionando para a página de produtos...';
                        }
                        setTimeout(() => {
                            window.location.href = '<?php echo getSiteUrl('index.php'); ?>'; // Ou para a página de produtos
                        }, 2500);
                    } else {
                        // Recalcula e atualiza o display do resumo
                        updateOrderSummaryDisplay();
                    }

                } catch (error) {
                    console.error('Erro na requisição AJAX:', error);
                    alert('Ocorreu um erro na comunicação com o servidor. Tente novamente.\n\nDetalhes: ' + error.message);
                }
            }

            // Event Listeners para os botões de quantidade e remover
            cartItemsTbody.addEventListener('click', function(event) {
                const target = event.target;
                const productId = target.dataset.productId || target.closest('button')?.dataset.productId;

                if (!productId) return;

                const quantityInput = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
                let currentQuantity = parseInt(quantityInput.value);

                if (target.classList.contains('increase-quantity')) {
                    currentQuantity++;
                    updateCart(productId, 'update_quantity', currentQuantity);
                } else if (target.classList.contains('decrease-quantity')) {
                    if (currentQuantity > 1) {
                        currentQuantity--;
                        updateCart(productId, 'update_quantity', currentQuantity);
                    } else {
                        // Se a quantidade for 1 e tentar diminuir, pergunta se quer remover
                        if (confirm('Deseja remover este item do carrinho?')) {
                            updateCart(productId, 'remove_item');
                        }
                    }
                } else if (target.classList.contains('remove-item-btn') || target.closest('.remove-item-btn')) {
                    if (confirm('Tem certeza que deseja remover este item do carrinho?')) {
                        updateCart(productId, 'remove_item');
                    }
                }
            });

            // Event Listener para o input de quantidade (se o usuário digitar)
            cartItemsTbody.addEventListener('change', function(event) {
                const target = event.target;
                if (target.classList.contains('quantity-input')) {
                    const productId = target.dataset.productId;
                    let newQuantity = parseInt(target.value);

                    if (isNaN(newQuantity) || newQuantity < 1) {
                        newQuantity = 1; // Garante que a quantidade mínima é 1
                        target.value = 1;
                    }
                    updateCart(productId, 'update_quantity', newQuantity);
                }
            });

            // Lógica para mostrar/ocultar a seção de parcelamento
            function toggleInstallmentsSection() {
                if (paymentMethodSelect.value === 'credit_card') {
                    installmentsSection.style.display = 'block';
                    updateOrderSummaryDisplay(); // Recalcula para atualizar parcelas
                } else {
                    installmentsSection.style.display = 'none';
                }
            }

            paymentMethodSelect.addEventListener('change', toggleInstallmentsSection);
            toggleInstallmentsSection(); // Chama ao carregar a página para definir o estado inicial

            // Função para carregar opções de parcelamento
            function loadInstallmentOptions(totalValue) {
                totalValue = totalValue || currentSubtotal + currentShippingCost - currentDiscount; // Usa o total atualizado
                installmentsSelect.innerHTML = ''; // Limpa opções existentes

                // Exemplo: até 3x sem juros, depois com juros simples de 1% ao mês
                for (let i = 1; i <= 12; i++) {
                    let installmentValue = totalValue / i;
                    let interestText = '';
                    if (i > 3) { // Exemplo: juros a partir da 4ª parcela
                        const interestRate = 0.01; // 1% ao mês
                        // Cálculo de juros simples para simplificar
                        const totalWithInterest = totalValue * (1 + (interestRate * (i - 3))); 
                        installmentValue = totalWithInterest / i;
                        interestText = ` (com juros)`;
                    }

                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = `${i}x de R$ ${number_format(installmentValue, 2, ',', '.')}${interestText}`;
                    installmentsSelect.appendChild(option);
                }
            }

            // Função para calcular frete (simulada)
            document.getElementById('calculate_shipping_btn').addEventListener('click', async function() {
                const cep = cepInput.value.replace(/\D/g, ''); // Limpa o CEP
                if (cep.length !== 8) {
                    alert('Por favor, insira um CEP válido (8 dígitos).');
                    return;
                }

                const shippingOptionsDiv = document.getElementById('shipping_options');
                shippingOptionsDiv.innerHTML = '<p>Calculando frete...</p>'; // Feedback ao usuário
                shippingMethodHiddenInput.value = ''; // Limpa seleção anterior
                shippingCostHiddenInput.value = '0'; // Limpa custo anterior
                currentShippingCost = 0; // Reseta o custo global
                updateOrderSummaryDisplay(); // Atualiza o resumo para refletir frete 0

                const formData = new FormData();
                formData.append('cep', cep);

                try {
                    const response = await fetch('<?php echo getSiteUrl('pages/actions/calculate_shipping.php'); ?>', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status} - ${response.statusText}`);
                    }

                    const data = await response.json();
                    console.log('Resposta Melhor Envio:', data);

                    if (!data.success) {
                        shippingOptionsDiv.innerHTML = `<p class="text-danger">Erro: ${data.message}</p>`;
                        alert('Erro ao calcular frete: ' + data.message);
                        return;
                    }

                    if (data.shipping_options && data.shipping_options.length > 0) {
                        let optionsHtml = '<h3>Opções de Frete:</h3>';
                        data.shipping_options.forEach((option, index) => {
                            const deliveryTime = option.custom_delivery_time ? option.custom_delivery_time.min + '-' + option.custom_delivery_time.max + ' dias úteis' : option.delivery_time + ' dias úteis';
                            optionsHtml += `
                                <div class="form-check">
                                    <input type="radio" id="shipping_${option.id}" name="shipping_option" value="${option.id}" data-cost="${option.price}" data-name="${option.name} - ${option.company}" ${index === 0 ? 'checked' : ''}>
                                    <label for="shipping_${option.id}">
                                        ${option.name} (${option.company}) - R$ ${number_format(option.price, 2, ',', '.')} (${deliveryTime})
                                    </label>
                                </div>
                            `;
                        });
                        shippingOptionsDiv.innerHTML = optionsHtml;

                        // Seleciona a primeira opção por padrão e atualiza o resumo
                        const firstOption = data.shipping_options[0];
                        shippingMethodHiddenInput.value = `${firstOption.name} - ${firstOption.company}`; // Nome e empresa
                        shippingCostHiddenInput.value = firstOption.price;
                        currentShippingCost = firstOption.price;
                        updateOrderSummaryDisplay();

                        // Adiciona listener para seleção de frete
                        shippingOptionsDiv.addEventListener('change', function(e) {
                            if (e.target.name === 'shipping_option') {
                                const selectedCost = parseFloat(e.target.dataset.cost);
                                const selectedName = e.target.dataset.name;
                                shippingCostHiddenInput.value = selectedCost;
                                shippingMethodHiddenInput.value = selectedName;
                                currentShippingCost = selectedCost;
                                updateOrderSummaryDisplay();
                            }
                        });

                    } else {
                        shippingOptionsDiv.innerHTML = '<p>Nenhuma opção de frete encontrada para este CEP.</p>';
                    }

                } catch (error) {
                    console.error('Erro ao calcular frete:', error);
                    shippingOptionsDiv.innerHTML = `<p class="text-danger">Ocorreu um erro ao calcular o frete. Tente novamente. Detalhes: ${error.message}</p>`;
                    alert('Ocorreu um erro ao calcular o frete. Tente novamente. Detalhes: ' + error.message);
                }
            });

            // Chama as funções iniciais para garantir que o resumo e parcelas estejam corretos ao carregar
            updateOrderSummaryDisplay();
        });
    </script>
</body>
</html>