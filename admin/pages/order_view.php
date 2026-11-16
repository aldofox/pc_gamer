<?php
// admin/pages/order_view.php

ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

// ====================================================================
// CORREÇÃO DEFINITIVA DO ABSPATH
// ====================================================================
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR);
}

// ====================================================================
// MAPA DE STATUS (em português)
// ====================================================================
$statusOptions = [
    'pending'    => 'Pendente',
    'paid'       => 'Pago',
    'processing' => 'Processando',
    'shipped'    => 'Enviado',
    'delivered'  => 'Entregue',
    'canceled'   => 'Cancelado',
    'refunded'   => 'Reembolsado',
];

// ====================================================================
// INCLUSÃO DE ARQUIVOS CRÍTICOS
// ====================================================================
try {
    $configPath = ABSPATH . 'includes' . DIRECTORY_SEPARATOR . 'config.php';
    $conexaoPath = ABSPATH . 'includes' . DIRECTORY_SEPARATOR . 'conexao.php';
    $functionsPath = ABSPATH . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
    $adminFunctionsPath = ABSPATH . 'admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'admin_functions.php';

    if (!file_exists($configPath)) throw new Exception("Erro fatal: Arquivo de configuração não encontrado em: " . $configPath);
    if (!file_exists($conexaoPath)) throw new Exception("Erro fatal: Arquivo de conexão não encontrado em: " . $conexaoPath);
    if (!file_exists($functionsPath)) throw new Exception("Erro fatal: Arquivo de funções não encontrado em: " . $functionsPath);
    if (!file_exists($adminFunctionsPath)) throw new Exception("Erro fatal: Arquivo de funções administrativas não encontrado em: " . $adminFunctionsPath);

    require_once $configPath;
    require_once $conexaoPath;
    require_once $functionsPath;
    require_once $adminFunctionsPath;

} catch (Exception $e) {
    ob_clean();
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro crítico ao carregar o sistema: ' . $e->getMessage()];
    error_log("Erro crítico na inclusão de arquivos em order_view.php: " . $e->getMessage());
    if (function_exists('getSiteUrl')) {
        redirect(getSiteUrl('admin/pages/orders.php'));
    } else {
        die('Erro crítico: ' . $e->getMessage());
    }
    exit;
}

// Descarta qualquer saída gerada pelos includes
$lixo = ob_get_clean();
if (!empty(trim($lixo))) {
    error_log("order_view.php - Saída indesejada capturada antes do HTML: " . strip_tags($lixo));
}

// --- DEBUG DO ORDER ID ---
$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'ID de pedido inválido.'];
    redirect(getSiteUrl('admin/pages/orders.php'));
    exit;
}

// --- CARREGA DADOS DO PEDIDO ---
try {
    $db = db();
    $pdo_instance = $db->getPdo();

    $order = $db->fetchOne("
        SELECT o.*, c.name AS user_name, c.email AS user_email
        FROM orders o
        LEFT JOIN customers c ON o.user_id = c.id
        WHERE o.id = ?
    ", [$orderId]);

    if (!$order) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Pedido não encontrado.'];
        redirect(getSiteUrl('admin/pages/orders.php'));
        exit;
    }

    $orderItems = $db->fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$orderId]);

    $shippingAddressData = null;
    if (!empty($order['shipping_address_snapshot'])) {
        $shippingAddressData = json_decode($order['shipping_address_snapshot'], true);
    }

    $statusHistory = $db->fetchAll("
        SELECT status, comment, changed_at
        FROM order_status_history
        WHERE order_id = ?
        ORDER BY changed_at ASC
    ", [$orderId]);

} catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao carregar o pedido.'];
    redirect(getSiteUrl('admin/pages/orders.php'));
    exit;
}

// --- TRATA ALTERAÇÃO DE STATUS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $comment   = sanitizeInput($_POST['comment'] ?? '');

    $validStatuses = array_keys($statusOptions);
    if (!in_array($newStatus, $validStatuses, true)) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Status inválido.'];
        redirect(getSiteUrl('admin/pages/order_view.php?id=' . $orderId));
        exit;
    }

    if ($newStatus === $order['status']) {
        $_SESSION['message'] = ['type' => 'info', 'text' => 'O status selecionado já é o atual.'];
        redirect(getSiteUrl('admin/pages/order_view.php?id=' . $orderId));
        exit;
    }

    try {
        $updatedOrder = updateOrderStatus($pdo_instance, $orderId, $newStatus, $comment);

        if ($updatedOrder) {
            $label = $statusOptions[$newStatus] ?? ucfirst($newStatus);
            $_SESSION['message'] = [
                'type' => 'success',
                'text' => 'Status do pedido atualizado com sucesso para "' . $label . '".'
            ];

            $customerName  = $updatedOrder['user_name'] ?? 'Cliente';
            $customerEmail = $updatedOrder['user_email'] ?? null;

            if ($customerEmail) {
                $emailSent = sendOrderStatusUpdateEmail($pdo_instance, $updatedOrder, $customerName, $customerEmail);
                if (!$emailSent) {
                    $_SESSION['message']['text'] .= ' Mas houve um erro ao enviar o e-mail de notificação.';
                    $_SESSION['message']['type'] = 'warning';
                }
            } else {
                $_SESSION['message']['text'] .= ' (Observação: o e-mail do cliente não está cadastrado ou não foi encontrado para notificação.)';
            }

        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao atualizar status.'];
        }
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao atualizar status do pedido.'];
    }

    redirect(getSiteUrl('admin/pages/order_view.php?id=' . $orderId));
    exit;
}

$pageTitle = "Pedido #" . $order['order_number'] . " | Admin";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('admin/assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos para a Timeline do Pedido*/
        .timeline-container {
            position: relative;
            padding: 20px 0 20px 30px; /* Ajuste o padding para o lado esquerdo */
            list-style: none;
        }

        .timeline-container:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 10px; /* Posição da linha vertical */
            width: 2px;
            background-color: #e9ecef; /* Cor da linha */
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }

        .timeline-badge {
            width: 20px;
            height: 20px;
            position: absolute;
            top: 0;
            left: 0;
            border-radius: 50%;
            background-color: #007bff; /* Cor padrão do badge */
            color: #fff;
            z-index: 1; /* Garante que o badge fique sobre a linha */
            transform: translateX(-50%); /* Centraliza o badge na linha */
        }

        .timeline-panel {
            position: relative;
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 0.25rem;
            padding: 15px;
            margin-left: 20px; /* Espaço para o badge */
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .timeline-panel:before {
            content: '';
            position: absolute;
            top: 10px;
            left: -10px; /* Posição da seta */
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
            border-right: 10px solid #e9ecef; /* Cor da seta */
        }

        .timeline-panel:after {
            content: '';
            position: absolute;
            top: 11px;
            left: -9px; /* Posição da seta interna */
            border-top: 9px solid transparent;
            border-bottom: 9px solid transparent;
            border-right: 9px solid #fff; /* Cor da seta interna */
        }

        .timeline-heading h6 {
            margin-bottom: 5px;
        }

        .timeline-body p {
            margin-bottom: 0;
        }
        /* Estilos para os badges de status
        .status-pending { background-color: #6c757d; color: #fff!important; padding: .25em .4em; border-radius: .25rem; } /* secondary */
        .status-paid { background-color: #17a2b8; color: #fff!important; padding: .25em .4em; border-radius: .25rem; } /* info */
        .status-processing { background-color: #007bff; color: #fff !important; padding: .25em .4em; border-radius: .25rem; } /* primary */
        .status-shipped { background-color: #ffc107; color: #000!important; padding: .25em .4em; border-radius: .25rem; } /* warning */
        .status-delivered { background-color: #28a745; color: #fff!important; padding: .25em .4em; border-radius: .25rem; } /* success */
        .status-canceled { background-color: #dc3545; color: #fff!important; padding: .25em .4em; border-radius: .25rem; } /* danger */
        .status-refunded { background-color: #343a40; color: #fff; padding: .25em .4em; border-radius: .25rem; } /* dark */
    </style>
</head>
<body>
<div class="admin-container">
    <?php include ABSPATH . 'admin/includes/admin_siderbar.php'; ?>
    <div class="main-content">
        <div class="header">
            <h1>Pedido #<?php echo htmlspecialchars($order['order_number']); ?></h1>
            <a href="<?php echo getSiteUrl('admin/pages/orders.php'); ?>" class="btn btn-sm">Voltar</a>
        </div>

        <?php displayMessage(); ?>

        <div class="order-admin-details">
            <h2>Dados Gerais</h2>
            <p><strong>ID:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
            <p><strong>Nº Pedido:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
            <p><strong>Data:</strong> <?php echo formatDateTime($order['created_at']); ?></p>
            <p><strong>Status:</strong> 
                <span class="status-<?php echo htmlspecialchars($order['status']); ?>">
                    <?php echo htmlspecialchars($statusOptions[$order['status']] ?? ucfirst($order['status'])); ?>
                </span>
            </p>
            <p><strong>Método de Pagamento:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method']))); ?></p>
            <p><strong>Total:</strong> R$ <?php echo number_format($order['total'], 2, ',', '.'); // Corrigido para total_amount ?></p>

            <!-- FORMULÁRIO PARA ALTERAR STATUS -->
            <h2>Alterar Status do Pedido</h2>
            <form action="<?php echo getSiteUrl('admin/pages/order_view.php?id=' . $orderId); ?>" method="post" class="order-status-form">
                <input type="hidden" name="action" value="update_status">

                <div class="form-group">
                    <label for="status">Novo Status</label>
                    <select name="status" id="status" required>
                        <?php
                        $statusOptions = [
                            'pending'    => 'Pendente',
                            'paid'       => 'Pago',
                            'processing' => 'Processando',
                            'shipped'    => 'Enviado',
                            'delivered'  => 'Entregue',
                            'canceled'   => 'Cancelado',
                            'refunded'   => 'Reembolsado',
                        ];
                        foreach ($statusOptions as $value => $label):
                        ?>
                            <option value="<?php echo $value; ?>" <?php echo ($order['status'] === $value) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="comment">Comentário (opcional)</label>
                    <textarea name="comment" id="comment" rows="3" placeholder="Ex: Pagamento confirmado; Enviado via Correios; etc."></textarea>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-sync"></i> Atualizar Status</button>
            </form>
            <!-- FIM DO FORMULÁRIO -->

            <h2>Cliente</h2>
            <p><strong>Nome:</strong> <?php echo htmlspecialchars($order['user_name'] ?? ($shippingAddressData['name'] ?? 'Convidado')); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['user_email'] ?? ($shippingAddressData['email'] ?? '-')); ?></p>

            <h2>Endereço de Entrega</h2>
            <?php if ($shippingAddressData): ?>
                <p>
                    <?php echo htmlspecialchars($shippingAddressData['address'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($shippingAddressData['number'] ?? 'N/A'); ?><br>
                    <?php echo htmlspecialchars($shippingAddressData['neighborhood'] ?? 'N/A'); ?><br>
                    <?php echo htmlspecialchars($shippingAddressData['city'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($shippingAddressData['state'] ?? 'N/A'); ?><br>
                    CEP: <?php echo htmlspecialchars($shippingAddressData['zip_code'] ?? 'N/A'); ?><br>
                    Tel: <?php echo htmlspecialchars($shippingAddressData['phone'] ?? 'N/A'); ?>
                </p>
            <?php else: ?>
                <p>Endereço não disponível.</p>
            <?php endif; ?>

            <h2>Itens do Pedido</h2>
            <?php if (empty($orderItems)): ?>
                <p>Nenhum item encontrado.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th>Preço Unitário</th>
                            <th>Total Linha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($item['product_image'])): ?>
                                        <img src="<?php echo getSiteUrl($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td>R$ <?php echo number_format($item['unit_price'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($item['quantity'] * $item['unit_price'], 2, ',', '.'); // Corrigido para total_line ou cálculo ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- HISTÓRICO DE STATUS (TIMELINE - NOVO TRECHO) -->
            <h2>Histórico de Status</h2>
            <?php if (!empty($statusHistory)): ?>
                <div class="timeline-container">
                    <?php foreach ($statusHistory as $historyItem): ?>
                        <div class="timeline-item">
                            <div class="timeline-badge status-<?php echo htmlspecialchars($historyItem['status']); ?>"></div> <!-- Usa classe de status para cor -->
                            <div class="timeline-panel">
                                <div class="timeline-heading">
                                    <h6 class="timeline-title">Status: <span class="status-<?php echo htmlspecialchars($historyItem['status']); ?>"><?= htmlspecialchars(ucfirst($historyItem['status'])) ?></span></h6>
                                    <p><small class="text-muted"><i class="far fa-clock"></i> <?= formatDateTime($historyItem['changed_at']) ?></small></p>
                                </div>
                                <?php if (!empty($historyItem['comment'])): ?>
                                    <div class="timeline-body">
                                        <p><?= nl2br(htmlspecialchars($historyItem['comment'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Nenhum histórico de status encontrado para este pedido.</p>
            <?php endif; ?>
            <!-- FIM HISTÓRICO DE STATUS -->

        </div>
    </div>
</div>
</body>
</html>