<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

ensureSessionStarted();

// Aqui assumo que você passa o ID do pedido via GET ou guarda na sessão
// Exemplo com GET: order_success.php?id=123
$orderId = (int)($_GET['id'] ?? 0);

// OPCIONAL: se você preferir guardar o último pedido na sessão:
// $orderId = (int)($_SESSION['last_order_id'] ?? 0);

if ($orderId <= 0) {
    // Se não tiver ID, manda pra home
    redirect(getSiteUrl('index.php'));
    exit;
}

try {
    $db = db();

    // Pega o pedido
    $order = $db->fetchOne("
        SELECT *
        FROM orders
        WHERE id = ?
    ", [$orderId]);

    if (!$order) {
        redirect(getSiteUrl('index.php'));
        exit;
    }

    // Itens do pedido (para um resumo)
    $orderItems = $db->fetchAll("
        SELECT *
        FROM order_items
        WHERE order_id = ?
    ", [$orderId]);

} catch (Exception $e) {
    error_log("Erro ao carregar página de sucesso do pedido #$orderId: " . $e->getMessage());
    redirect(getSiteUrl('index.php'));
    exit;
}

$pageTitle = "Pedido realizado com sucesso";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('assets/css/style.css'); ?>">
    <!-- Ícones (fontawesome), se ainda não estiver no header -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php include ABSPATH . 'includes/header.php'; ?>

<main class="container">
    <div class="order-success-wrapper">
        <div class="order-success-card">
            <div class="order-success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="order-success-title">Pedido realizado com sucesso!</h1>
            <p class="order-success-subtitle">
                Obrigado por comprar na nossa loja. Enviamos os detalhes do pedido para o seu e-mail.
            </p>

            <div class="order-success-info">
                <div>
                    <span>Nº do Pedido</span>
                    <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                </div>
                <div>
                    <span>Data</span>
                    <strong><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))); ?></strong>
                </div>
                <div>
                    <span>Status</span>
                    <strong>
                        <span class="badge-status badge-status-<?php echo htmlspecialchars($order['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                        </span>
                    </strong>
                </div>
                <div>
                    <span>Total</span>
                    <strong>R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></strong>
                </div>
            </div>

            <?php if (!empty($orderItems)): ?>
                <div class="order-success-items">
                    <h2>Resumo dos itens</h2>
                    <ul>
                        <?php foreach ($orderItems as $item): ?>
                            <li>
                                <div class="osi-left">
                                    <?php if (!empty($item['product_image'])): ?>
                                        <img src="<?php echo getSiteUrl($item['product_image']); ?>"
                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                        <span>Qtd: <?php echo (int)$item['quantity']; ?></span>
                                    </div>
                                </div>
                                <div class="osi-right">
                                    <span>R$ <?php echo number_format($item['total_line'], 2, ',', '.'); ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="order-success-actions">
                <a href="<?php echo getSiteUrl('pages/order_details.php?id=' . $order['id']); ?>" class="btn btn-secondary">
                    <i class="fas fa-receipt"></i>
                    Ver detalhes do pedido
                </a>
                <a href="<?php echo getSiteUrl('pages/my_orders.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-box"></i>
                    Ir para Meus Pedidos
                </a>
                <a href="<?php echo getSiteUrl('index.php'); ?>" class="btn btn-secondary btn-outline">
                    <i class="fas fa-store"></i>
                    Continuar comprando
                </a>
            </div>
        </div>
    </div>
</main>

<?php include ABSPATH . 'includes/footer.php'; ?>
</body>
</html>