<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

ensureSessionStarted();
requireUserLogin(); // garante que só usuário logado acesse

$userId = $_SESSION['user_id'] ?? 0;
if ($userId <= 0) {
    redirect(getSiteUrl('pages/login.php'));
    exit;
}

try {
    $db = db();
    // Busca pedidos do usuário logado
    $orders = $db->fetchAll("
        SELECT id, order_number, status, total, created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC
    ", [$userId]);
} catch (Exception $e) {
    error_log("Erro ao carregar meus pedidos do usuário #$userId: " . $e->getMessage());
    $orders = [];
}

$pageTitle = "Meus Pedidos";
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
        <i class="fas fa-box"></i>
        Meus Pedidos
    </h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message']['type']); ?>">
            <?php 
                echo htmlspecialchars($_SESSION['message']['text']); 
                unset($_SESSION['message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="empty-cart">
            <i class="fas fa-box-open"></i>
            <h2>Você ainda não tem pedidos</h2>
            <p>Explore nossos produtos e faça seu primeiro pedido agora mesmo.</p>
            <a href="<?php echo getSiteUrl('index.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-shopping-bag"></i>
                Ver produtos
            </a>
        </div>
    <?php else: ?>
        <div class="orders-list-wrapper">
            <div class="orders-list-card">
                <table class="user-orders-table">
                    <thead>
                        <tr>
                            <th>Nº Pedido</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Total</th>
                            <th>Pagamento</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))); ?></td>
                                <td>
                                    <span class="badge-status badge-status-<?php echo htmlspecialchars($order['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                    </span>
                                </td>
                                <td>R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method'] ?? ''))); ?>
                                </td>
                                <td>
                                    <a href="<?php echo getSiteUrl('pages/order_details.php?id=' . $order['id']); ?>" class="btn-small">
                                        Ver detalhes
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include ABSPATH . 'includes/footer.php'; ?>
</body>
</html>