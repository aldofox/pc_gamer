<?php
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__DIR__)) . '/'); 
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';
require_once ABSPATH . 'admin/includes/admin_functions.php';

ensureSessionStarted();
requireAdminLogin();

$pageTitle = "Gerenciar Pedidos | Painel Admin";

// ==========================
// PAGINAÇÃO
// ==========================
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $limit;

// ==========================
// FILTROS
// ==========================
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

try {
    $db = db();

    $params = [];
    $where = [];

    if (!empty($search)) {
        $where[] = "(o.order_number LIKE ? OR u.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($status)) {
        $where[] = "o.status = ?";
        $params[] = $status;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    // ==========================
    // TOTAL DE PEDIDOS
    // ==========================
    $totalOrders = $db->fetchOne("
        SELECT COUNT(*) as total
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        $whereSql
    ", $params)['total'];

    $totalPages = ceil($totalOrders / $limit);

    // ==========================
    // BUSCA PEDIDOS
    // ==========================
    $orders = $db->fetchAll("
        SELECT
            o.id,
            o.order_number,
            o.status,
            o.total,
            o.payment_method,
            o.created_at,
            o.user_id,
            o.shipping_address_snapshot,
            u.name AS user_name,
            u.email AS user_email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        $whereSql
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset
    ", $params);

} catch (Exception $e) {
    error_log("Erro ao buscar pedidos: " . $e->getMessage());
    $orders = [];
    $totalPages = 1;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="admin-container">
    <?php include ABSPATH . 'admin/includes/admin_siderbar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1><i class="fa-solid fa-shopping-cart"></i> Pedidos</h1>

            <form action="<?php echo getSiteUrl('admin/logout.php'); ?>" method="post" style="display:inline;">
                <button type="submit" class="logout-btn">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Sair
                </button>
            </form>
        </div>

        <!-- MENSAGEM -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message']['type']); ?>">
                <?php echo htmlspecialchars($_SESSION['message']['text']); ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- FILTRO -->
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Buscar pedido ou cliente"
                value="<?php echo htmlspecialchars($search); ?>" class="filtro">

            <select name="status" class="filtro">
                <option value="">Todos os status</option>
                <option value="pending" <?php if ($status=='pending') echo 'selected'; ?>>Pendente</option>
                <option value="paid" <?php if ($status=='paid') echo 'selected'; ?>>Pago</option>
                <option value="processing" <?php if ($status=='processing') echo 'selected'; ?>>Processando</option>
                <option value="shipped" <?php if ($status=='shipped') echo 'selected'; ?>>Enviado</option>
                <option value="delivered" <?php if ($status=='delivered') echo 'selected'; ?>>Entregue</option>
                <option value="canceled" <?php if ($status=='canceled') echo 'selected'; ?>>Cancelado</option>
                <option value="refunded" <?php if ($status=='refunded') echo 'selected'; ?>>Reembolsado</option>
            </select>

            <button type="submit" class="btn"><i class="fa-solid fa-filter"></i> Filtrar</button>
        </form>

        <!-- TABELA -->
        <?php if (empty($orders)): ?>
            <p>Nenhum pedido encontrado.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nº Pedido</th>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php
                        $snapshot = json_decode($order['shipping_address_snapshot'], true);
                        $customerName = $order['user_name'] ?? ($snapshot['name'] ?? 'Convidado');
                        $customerEmail = $order['user_email'] ?? ($snapshot['email'] ?? '-');
                    ?>
                    <tr>
                        <td><?php echo $order['id']; ?></td>
                        <td>#<?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td><?php echo htmlspecialchars($customerName); ?></td>
                        <td><?php echo htmlspecialchars($customerEmail); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                        <td>
                            <span class="status-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></td>
                        <td><?php echo ucfirst(str_replace('_',' ',$order['payment_method'])); ?></td>
                        <td>
                            <a href="<?php echo getSiteUrl('admin/pages/order_view.php?id=' . $order['id']); ?>" class="btn btn-sm">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- PAGINAÇÃO -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $queryString = http_build_query([
                        'search' => $search,
                        'status' => $status
                    ]);
                    ?>

                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&<?php echo $queryString; ?>" class="page-link"><i class="fa-solid fa-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&<?php echo $queryString; ?>"
                           class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                           <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>&<?php echo $queryString; ?>" class="page-link"><i class="fa-solid fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
</body>
</html>