<?php
if (!defined('ABSPATH')) {
    // __DIR__ é C:\Apache24\htdocs\pc_gamer\admin
    // dirname(__DIR__) é C:\Apache24\htdocs\pc_gamer
    define('ABSPATH', dirname(__DIR__) . '/'); // Corrigido para apontar para a raiz do projeto (pc_gamer)
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';
require_once ABSPATH . 'admin/includes/admin_functions.php'; // Para funções auxiliares como getStatusBadgeClass

ensureSessionStarted();
requireAdminLogin();

$pageTitle = "Dashboard | Admin";

$db = db();
$pdo_instance = $db->getPdo();

// --- 1. BUSCA DE DADOS PARA MÉTRICAS CHAVE ---
$totalProducts = $db->fetchOne("SELECT COUNT(*) AS count FROM products;")['count'] ?? 0;
$totalOrders = $db->fetchOne("SELECT COUNT(*) AS count FROM orders;")['count'] ?? 0;
$totalCustomers = $db->fetchOne("SELECT COUNT(*) AS count FROM customers;")['count'] ?? 0;
$totalRevenue = $db->fetchOne("SELECT SUM(total) AS sum FROM orders WHERE status IN ('paid', 'shipped', 'delivered');")['sum'] ?? 0;
$pendingOrders = $db->fetchOne("SELECT COUNT(*) AS count FROM orders WHERE status = 'pending';")['count'] ?? 0;
$processingOrders = $db->fetchOne("SELECT COUNT(*) AS count FROM orders WHERE status = 'processing';")['count'] ?? 0;
$lowStockProductsCount = $db->fetchOne("SELECT COUNT(*) AS count FROM vw_low_stock_products;")['count'] ?? 0; // Usando sua view

// --- 2. BUSCA DE DADOS PARA GRÁFICOS/LISTAS ---
// Últimos 5 pedidos
$recentOrders = $db->fetchAll("
    SELECT o.id, o.order_number, o.status, o.created_at, c.name AS customer_name
    FROM orders o
    LEFT JOIN customers c ON o.user_id = c.id
    ORDER BY o.created_at DESC
    LIMIT 5;
");

// Produtos com baixo estoque (se a view existir e for útil)
$lowStockProducts = $db->fetchAll("SELECT * FROM vw_low_stock_products LIMIT 5;");

// Dados para gráfico de vendas diárias (últimos 7 dias)
$dailySales = $db->fetchAll("
    SELECT DATE(created_at) as order_date, SUM(total) as total_sales
    FROM orders
    WHERE status IN ('paid', 'shipped', 'delivered') AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY order_date
    ORDER BY order_date ASC;
");

// Dados para gráfico de status de pedidos
$orderStatusCounts = $db->fetchAll("
    SELECT status, COUNT(*) as count
    FROM orders
    GROUP BY status;
");

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php include ABSPATH . 'admin/includes/admin_header.php'; // Inclui CSS do Bootstrap e outros ?>
    <link rel="stylesheet" href="<?= getSiteUrl('admin/assets/css/admin.css'); ?>">
    <!-- Chart.js para gráficos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilos básicos para os cards do dashboard */
  .row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.col-12 {
    width: 100%;
    box-sizing: border-box;
}

@media (min-width: 768px) {
    .col-md-4 {
        width: calc(33.333% - 1rem); /* desconta o gap */
        flex: 0 0 calc(33.333% - 1rem);
    }
}

.dashboard-card {
    border-radius: 0.5rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    padding: 1.5rem;
    text-align: center;
    height: 160px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    /* removido o aspect-ratio e o margin-bottom (o gap do .row já cuida do espaçamento) */
}


        .dashboard-card h4 {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .dashboard-card p {
            font-size: 2rem;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 0;
        }
        .dashboard-card.bg-primary { background-color: #007bff !important; color: #fff !important; }
        .dashboard-card.bg-success { background-color: #28a745 !important; color: #fff !important; }
        .dashboard-card.bg-warning { background-color: #ffc107 !important; color: #212529 !important; }
        .dashboard-card.bg-info { background-color: #17a2b8 !important; color: #fff !important; }
        .dashboard-card.bg-danger { background-color: #dc3545 !important; color: #fff !important; }
        .dashboard-card.bg-secondary { background-color: #6c757d !important; color: #fff !important; }

        /* Estilos para os badges de status (se não estiverem no admin.css) */
        .status-pending { background-color: #6c757d; color: #fff!important; padding: .25em .4em; border-radius: .25rem; } /* secondary */
        .status-paid { background-color: #17a2b8; color: #fff; padding: .25em .4em; border-radius: .25rem; } /* info */
        .status-processing { background-color: #007bff; color: #fff; padding: .25em .4em; border-radius: .25rem; } /* primary */
        .status-shipped { background-color: #ffc107; color: #212529; padding: .25em .4em; border-radius: .25rem; } /* warning */
        .status-delivered { background-color: #28a745; color: #fff; padding: .25em .4em; border-radius: .25rem; } /* success */
        .status-canceled { background-color: #dc3545; color: #fff; padding: .25em .4em; border-radius: .25rem; } /* danger */
        .status-refunded { background-color: #343a40; color: #fff; padding: .25em .4em; border-radius: .25rem; } /* dark */
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include ABSPATH . 'admin/includes/admin_siderbar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><i class="fa-slab fa-regular fa-chart-bar"></i> Dashboard</h1>
            </div>

            <?php displayMessage(); // Exibe mensagens de sucesso/erro/info ?>

            <div class="row">
                <div class="col-12 col-md-4">
                    <div class="dashboard-card bg-info">
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <h4>Total de Produtos</h4>
                        <p><?= htmlspecialchars($totalProducts) ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="dashboard-card bg-primary">
                        <i class="fa-solid fa-gift"></i>
                        <h4>Total de Pedidos</h4>
                        <p><?= htmlspecialchars($totalOrders) ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="dashboard-card bg-success">
                        <i class="fa-solid fa-users"></i>
                        <h4>Total de Clientes</h4>
                        <p><?= htmlspecialchars($totalCustomers) ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="dashboard-card bg-dark">
                        <i class="fa-solid fa-brazilian-real-sign"></i>
                        <h4>Receita Total</h4>
                        <p>R$ <?= number_format($totalRevenue, 2, ',', '.') ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="dashboard-card bg-secondary">
                        <i class="fa-solid fa-hourglass-half"></i>
                        <h4>Pedidos Pendentes</h4>
                        <p><?= htmlspecialchars($pendingOrders) ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="dashboard-card bg-warning">
                        <i class="fa-solid fa-cogs"></i>
                        <h4>Pedidos em Processamento</h4>
                        <p><?= htmlspecialchars($processingOrders) ?></p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="dashboard-card bg-danger">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <h4>Produtos Baixo Estoque</h4>
                        <p><?= htmlspecialchars($lowStockProductsCount) ?></p>
                    </div>
                </div>
            </div>



            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Vendas Diárias (Últimos 7 Dias)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dailySalesChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Status dos Pedidos</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Últimos 5 Pedidos</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentOrders)): ?>
                                <ul class="list-group">
                                    <?php foreach ($recentOrders as $orderItem): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <a href="<?= getSiteUrl('admin/pages/order_view.php?id=' . $orderItem['id']) ?>">
                                                #<?= htmlspecialchars($orderItem['order_number']) ?> - <?= htmlspecialchars($orderItem['customer_name'] ?? 'Convidado') ?>
                                            </a>
                                            <span class="status-<?= htmlspecialchars($orderItem['status']) ?>">
                                                <?= htmlspecialchars(ucfirst($orderItem['status'])) ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Nenhum pedido recente.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Produtos com Baixo Estoque</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($lowStockProducts)): ?>
                                <ul class="list-group">
                                    <?php foreach ($lowStockProducts as $product): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($product['name']) ?>
                                            <span class="badge bg-danger rounded-pill">Estoque: <?= htmlspecialchars($product['stock_qty']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Nenhum produto com baixo estoque.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include ABSPATH . 'admin/includes/admin_footer.php'; // Inclui JS do Bootstrap e outros ?>

    <script>
        // --- Gráfico de Vendas Diárias ---
        const dailySalesData = {
            labels: [<?php foreach($dailySales as $sale) echo "'" . date('d/m', strtotime($sale['order_date'])) . "',"; ?>],
            datasets: [{
                label: 'Vendas Diárias (R$)',
                data: [<?php foreach($dailySales as $sale) echo $sale['total_sales'] . ","; ?>],
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1,
                fill: true
            }]
        };
        const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
        new Chart(dailySalesCtx, {
            type: 'line',
            data: dailySalesData,
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // --- Gráfico de Status dos Pedidos ---
        const orderStatusData = {
            labels: [<?php foreach($orderStatusCounts as $status) echo "'" . ucfirst($status['status']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach($orderStatusCounts as $status) echo $status['count'] . ","; ?>],
                backgroundColor: [
                    '#6c757d', // pending (secondary)
                    '#17a2b8', // paid (info)
                    '#007bff', // processing (primary)
                    '#ffc107', // shipped (warning)
                    '#28a745', // delivered (success)
                    '#dc3545', // canceled (danger)
                    '#343a40'  // refunded (dark)
                ],
                hoverOffset: 4
            }]
        };
        const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
        new Chart(orderStatusCtx, {
            type: 'doughnut', // Gráfico de rosca
            data: orderStatusData,
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    </script>
</body>
</html>