<?php

// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php'; 
require_once ABSPATH . 'includes/functions.php';

ensureSessionStarted();

// Redireciona se o Carrinho estiver vazio
if (empty($_SESSION['cart'])) {
    $_SESSION['error_message'] = 'Seu Carrinho está Vazio. Adicione produtos antes de finalizar a compra';
    redirect(getSiteUrl('pages/carrinho.php'));
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


    // Validação Básica
    if (empty($customerName) || empty($customerEmail) || empty($shippingAddress) ||
        empty($shippingCity) || empty($shippingState) || empty($shippingZipcode) ||
        empty($paymentMethod)) {
            $_SESSION['error_message'] = 'Por Favor, preencha todos os Campos obrigatórios.';
            redirect(getSiteUrl('pages/checkout.php'));
            exit;
        }

    // Calcula totais do Pedido
    $subTotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $priceToUse = ($item['promotional_price'] > 0 && $item['is_promotion']) ? $item['promotional_price'] : $item['unit_price']; // Adicionado is_promotion
        $subTotal += $priceToUse * $item['quantity'];
    }

    $shippingCost = 0; // Aqui vai ser implementado a Logica do Frete
    $discount = 0; // Logia do Cupom
    $total = $subTotal + $shippingCost - $discount; // Corrigido cálculo do total

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
        $pdo_instance = $db->getPdo(); // <--- AQUI! Obtém a instância PDO subjacente

        if (!$pdo_instance) {
            throw new Exception("Conexão PDO não disponível.");
        }

        $pdo_instance->beginTransaction(); // <--- AGORA CHAMA beginTransaction() NO OBJETO PDO

        // Inserir o Pedido na Tabela orders
        // Ajustado as colunas da Tabela
        $stmt = $pdo_instance->prepare("
            INSERT INTO orders (
                user_id, order_number, status, subtotal, shipping_cost, discount, total,
                payment_method, installments, shipping_address_id, shipping_address_snapshot
            ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            getUserId(),                                // 1 - user_id (usando getUserId() para usuários logados)
            $orderNumber,                               // 2 - order_number
            $subTotal,                                  // 3 - subtotal
            $shippingCost,                              // 4 - shipping_cost
            $discount,                                  // 5 - discount
            $total,                                     // 6 - total
            $paymentMethod,                             // 7 - payment_method
            1,                                          // 8 - installments (fixo em 1)
            null,                                       // 9 - shipping_address_id (se não estiver usando a tabela addresses)
            $shippingAddressSnapshot                    // 10 - shipping_address_snapshot
        ]);
        $orderId = $pdo_instance->lastInsertId(); // <--- USANDO $pdo_instance

        // Inserir os itens do carrinho na tabela order_items
                    // Inserir os itens do carrinho na tabela order_items

            foreach ($_SESSION['cart'] as $item) {

                $priceAtPurchase = ($item['promotional_price'] > 0 && ($item['is_promotion'] ?? false)) ? (float)$item['promotional_price'] : (float)$item['unit_price'];
                $totalLine = $priceAtPurchase * $item['quantity'];

                // Certifique-se de que 'image_url' existe no item do carrinho.
                // Se não existir, verifique a função addToCart() em functions.php.
                $productImage = $item['image_url'] ?? null; // Pega a URL da imagem do item do carrinho

                $stmt = $pdo_instance->prepare("
                        INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, unit_price, total_line)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $productImage, // <--- ADICIONADO: URL da imagem do produto
                    $item['quantity'],
                    $priceAtPurchase, // Preço que o cliente realmente pagou (promocional ou unitário)
                    $totalLine
                ]);

                // Atualizar estoque do produto
                $stmt = $pdo_instance->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }

        $pdo_instance->commit(); // <--- USANDO $pdo_instance
        unset($_SESSION['cart']); // Limpa o carrinho apos o pedido
        $_SESSION['success_message'] = '✅ Pedido realizado com sucesso! Número do Pedido: ' . $orderNumber;
        redirect(getSiteUrl('pages/order_confirmation.php?order_id=' . $orderId)); // Redireciona para a confirmação
        exit;
    } catch (Exception $e) { // Captura Exception genérica, incluindo PDOException
        if (isset($pdo_instance) && $pdo_instance->inTransaction()) {
            $pdo_instance->rollBack(); // <--- USANDO $pdo_instance
        }
        error_log("Erro ao finalizar pedido: " . $e->getMessage());
        $_SESSION['error_message'] = '❌ Erro ao finalizar o pedido. Por favor, tente novamente. Detalhes: ' . $e->getMessage();
        redirect(getSiteUrl('pages/checkout.php'));
        exit;
    }
}

// Cálculo de totais para exibição no formulário (fora do bloco POST)
$subTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $priceToUse = ($item['promotional_price'] > 0 && $item['is_promotion']) ? $item['promotional_price'] : $item['unit_price'];
    $subTotal += $priceToUse * $item['quantity'];
}
$shippingCost = 0;
$discount = 0;
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
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                unset($_SESSION['error_message']);
            }
        ?>

        <div class="checkout-content">
            <div class="shipping-details">
                <h2>Detalhes de Entrega</h2>
                <form action="<?php echo getSiteUrl('pages/checkout.php'); ?>" method="POST">

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
                        <label for="shipping_state">Estado</label> <!-- Corrigido label -->
                        <input type="text" name="shipping_state" id="shipping_state" required value="<?php echo htmlspecialchars($_POST['shipping_state'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="shipping_zipcode">CEP</label> <!-- Corrigido label -->
                        <input type="text" name="shipping_zipcode" id="shipping_zipcode" required value="<?php echo htmlspecialchars($_POST['shipping_zipcode'] ?? ''); ?>">
                    </div>

                    <h2>Método de Pagamento</h2>
                    <div class="form-group">
                        <label for="payment_method">Escolha o método de Pagamento</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="">-- Selecione --</option>
                            <option value="pix" <?php echo (($_POST['payment_method'] ?? '') == 'pix') ? 'selected' : '';?>>PIX</option>
                            <option value="boleto" <?php echo (($_POST['payment_method'] ?? '') == 'boleto') ? 'selected' : '';?>>Boleto Bancário</option>
                            <option value="credit_card" <?php echo (($_POST['payment_method'] ?? '') == 'credit_card') ? 'selected' : '';?>>Cartão de Crédito</option>
                            <option value="debit_card" <?php echo (($_POST['payment_method'] ?? '') == 'debit_card') ? 'selected' : '';?>>Cartão de Débito</option>
                        </select>
                    </div>

                    <div class="order-summary">
                        <h2>Resumo do Pedido</h2>
                        <div class="summary-item">
                            <span>Subtotal</span>
                            <span>R$ <?php echo number_format($subTotal, 2, ',' , '.'); ?></span>
                        </div>

                        <div class="summary-item">
                            <span>Frete</span>
                            <span>R$ <?php echo number_format($shippingCost, 2, ',' , '.'); ?></span>
                        </div>

                        <?php if ($discount > 0): ?>
                            <div class="summary-item discount">
                                <span>Desconto</span>
                                <span>- R$ <?php echo number_format($discount,2, ',', '.'); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="summary-item total">
                            <span>Total:</span>
                            <span>R$ <?php echo number_format($total, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary btn-checkout">Finalizar Pedido</button>
                </form>
            </div>

            <div class="cart-items-review">
                <h2>Itens no Carrinho</h2>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <div class="cart-items-list">
                        <?php foreach ($_SESSION['cart'] as $item): ?>
                            <div class="cart-item-checkout">
                                <img src="<?php echo getSiteUrl($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">

                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p>Quantidade: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                    <?php
                                        $priceToDisplay = ($item['promotional_price'] > 0 && $item['is_promotion']) ? $item['promotional_price'] : $item['unit_price'];
                                    ?>
                                    <p>Preço Unitário: R$ <?php echo number_format($priceToDisplay, 2, ',', '.'); ?></p>
                                    <p>Subtotal: R$ <?php echo number_format($priceToDisplay * $item['quantity'], 2, ',', '.'); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <p>Seu carrinho está Vazio</p>
                    <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include ABSPATH . 'includes/footer.php'; ?>
</body>
</html>