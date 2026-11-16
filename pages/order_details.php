<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

ensureSessionStarted();
requireUserLogin();

$userId = $_SESSION['user_id'] ?? 0;
$orderId = (int)($_GET['id'] ?? 0);

if ($userId <= 0 || $orderId <= 0) {
    redirect(getSiteUrl('pages/my_orders.php'));
    exit;
}

try {
    $db = db();

    // Pedido só se for do usuário logado
    $order = $db->fetchOne("
        SELECT *
        FROM orders
        WHERE id = ? AND user_id = ?
    ", [$orderId, $userId]);

    if (!$order) {
        // Pedido não pertence a esse usuário ou não existe
        redirect(getSiteUrl('pages/my_orders.php'));
        exit;
    }

    // Itens do pedido
    $orderItems = $db->fetchAll("
        SELECT *
        FROM order_items
        WHERE order_id = ?
    ", [$orderId]);

    // Endereço de entrega (snapshot)
    $shippingAddressData = null;
    if (!empty($order['shipping_address_snapshot'])) {
        $shippingAddressData = json_decode($order['shipping_address_snapshot'], true);
    }

    // Histórico de status do pedido (para timeline)
    $statusHistory = $db->fetchAll("
        SELECT status, comment, changed_at
        FROM order_status_history
        WHERE order_id = ?
        ORDER BY changed_at ASC
    ", [$orderId]);

} catch (Exception $e) {
    error_log("Erro ao carregar detalhes do pedido #$orderId para usuário #$userId: " . $e->getMessage());
    redirect(getSiteUrl('pages/my_orders.php'));
    exit;
}


$pageTitle = "Pedido #" . $order['order_number'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('assets/css/style.css'); ?>">
</head>
<body>
<?php include ABSPATH . 'includes/header.php'; ?>

<main class="container">
    <h1 class="page-title">
        <i class="fas fa-receipt"></i>
        Detalhes do Pedido #<?php echo htmlspecialchars($order['order_number']); ?>
    </h1>

    <p>
        <a href="<?php echo getSiteUrl('pages/my_orders.php'); ?>" class="btn-small" style="margin-bottom: 15px;">
            &laquo; Voltar para Meus Pedidos
        </a>
        <?php if ($order['status'] === 'delivered'): ?>
            <a href="<?php echo getSiteUrl('pages/review_order.php?order_id=' . $order['id']); ?>" class="btn-small btn-primary" style="margin-right: 10px;">
                Avaliar Produtos
            </a>
        <?php endif; ?>
        <a href="<?php echo getSiteUrl('index.php'); ?>" class="btn-small">
            Continuar Comprando
        </a>
    </p>

    <div class="order-details-layout">
        <!-- Coluna esquerda: resumo + endereço -->
        <div class="order-main-info">
            <section class="order-card">
                <h2>Resumo do Pedido</h2>
                <div class="order-summary-grid">
                    <div>
                        <strong>Nº Pedido:</strong>
                        <p>#<?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>
                    <div>
                        <strong>Data:</strong>
                        <p><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))); ?></p>
                    </div>
                    <div>
                        <strong>Status:</strong>
                        <p>
                            <span class="badge-status badge-status-<?php echo htmlspecialchars($order['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                            </span>
                        </p>
                    </div>
                    <div>
                        <strong>Pagamento:</strong>
                        <p><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method']))); ?></p>
                    </div>
                    <div>
                        <strong>Total:</strong>
                        <p class="order-total-highlight">
                            R$ <?php echo number_format($order['total'], 2, ',', '.'); ?>
                        </p>
                    </div>
                </div>
            </section>
            <?php if (!empty($order['tracking_code']) && $order['status'] === 'shipped'): ?>
            <section class="order-card">
                <h2>Rastreamento do Pedido</h2>
                <p>
                    Seu pedido foi enviado! Acompanhe a entrega com o código de rastreamento:
                    <br>
                    <strong><?php echo htmlspecialchars($order['tracking_code']); ?></strong>
                    <?php
                    // Exemplo de link para Correios. Adapte para sua transportadora.
                    $trackingUrl = 'https://rastreamento.correios.com.br/app/index.php?id=' . htmlspecialchars($order['tracking_code']);
                    ?>
                    <a href="<?php echo $trackingUrl; ?>" target="_blank" class="btn-small">
                        Rastrear nos Correios <i class="fas fa-external-link-alt"></i>
                    </a>
                </p>
            </section>
            <?php endif; ?>

            <section class="order-card">
                <h2>Endereço de Entrega</h2>
                <?php if ($shippingAddressData): ?>
                    <p class="shipping-address">
                        <strong><?php echo htmlspecialchars($shippingAddressData['name'] ?? ''); ?></strong><br>
                        <?php echo htmlspecialchars($shippingAddressData['address'] ?? ''); ?><br>
                        <?php echo htmlspecialchars($shippingAddressData['city'] ?? ''); ?> - <?php echo htmlspecialchars($shippingAddressData['state'] ?? ''); ?><br>
                        CEP: <?php echo htmlspecialchars($shippingAddressData['zipcode'] ?? ''); ?><br>
                        Tel: <?php echo htmlspecialchars($shippingAddressData['phone'] ?? ''); ?>
                    </p>
                <?php else: ?>
                    <p>Endereço de entrega não disponível.</p>
                <?php endif; ?>
            </section>

            <?php if (!empty($statusHistory)): ?>
                <section class="order-card">
                    <h2>Histórico de Status</h2>
                    <div class="order-timeline">
                        <?php foreach ($statusHistory as $entry): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot timeline-dot-<?php echo htmlspecialchars($entry['status']); ?>"></div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <span class="timeline-status">
                                            <?php echo htmlspecialchars(ucfirst($entry['status'])); ?>
                                        </span>
                                        <span class="timeline-date">
                                            <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($entry['changed_at']))); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($entry['comment'])): ?>
                                        <p class="timeline-comment">
                                            <?php echo nl2br(htmlspecialchars($entry['comment'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <!-- Coluna direita: itens do pedido -->
        <div class="order-items-wrapper">
            <section class="order-card">
                <h2>Itens do Pedido</h2>
                <?php if (empty($orderItems)): ?>
                    <p>Nenhum item encontrado para este pedido.</p>
                <?php else: ?>
                    <table class="user-orders-table order-items-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Qtd</th>
                                <th>Preço Unitário</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="order-item-product">
                                            <?php if (!empty($item['product_image'])): ?>
                                                <img src="<?php echo getSiteUrl($item['product_image']); ?>"
                                                    alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td>R$ <?php echo number_format($item['unit_price'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($item['total_line'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php include ABSPATH . 'includes/footer.php'; ?>
</body>
</html>