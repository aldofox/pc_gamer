<?php
// pages/order_confirmation.php

// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php'; 
require_once ABSPATH . 'includes/functions.php';

ensureSessionStarted();

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    $_SESSION['error_message'] = 'ID do pedido inválido.';
    redirect(getSiteUrl('pages/my_orders.php')); // Ou para a página inicial
    exit;
}

$db = db(); // Sua função que retorna a instância da classe Database

// Busca os detalhes do pedido
// Garante que o usuário só veja seus próprios pedidos
$order = $db->fetchOne("SELECT * FROM orders WHERE id = :id AND user_id = :user_id", [
    'id' => $orderId,
    'user_id' => getUserId()
]);

if (!$order) {
    $_SESSION['error_message'] = 'Pedido não encontrado ou você não tem permissão para visualizá-lo.';
    redirect(getSiteUrl('pages/my_orders.php'));
    exit;
}

// Busca os itens do pedido
$orderItems = $db->fetchAll("SELECT * FROM order_items WHERE order_id = :order_id", ['order_id' => $orderId]);

$pageTitle = 'Confirmação do Pedido #' . htmlspecialchars($order['order_number']);

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo getSiteUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Estilos específicos para a página de confirmação de pedido */
        .order-confirmation-page {
            padding: 40px 0;
            text-align: center;
        }

        .order-confirmation-page h1 {
            color: #28a745; /* Verde de sucesso */
            margin-bottom: 20px;
            font-size: 2.5rem;
        }

        .order-summary-details {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 25px;
            margin: 0 auto 30px auto;
            max-width: 600px;
            text-align: left;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .order-summary-details p {
            margin-bottom: 10px;
            font-size: 1.1rem;
            color: #333;
        }

        .order-summary-details p strong {
            color: #000;
        }

        .order-summary-details p:last-child {
            margin-bottom: 0;
            font-size: 1.3rem;
            font-weight: bold;
            border-top: 1px dashed #ccc;
            padding-top: 15px;
            margin-top: 15px;
        }

        .section-title {
            font-size: 1.8rem;
            color: #333;
            margin-top: 40px;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            text-align: center;
        }

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }

        .order-items-table th,
        .order-items-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .order-items-table th {
            background-color: #f8f9fa;
            color: #555;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .order-items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .order-items-table .product-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .order-items-table .product-info img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border: 1px solid #eee;
            border-radius: 5px;
        }

        .order-items-table .product-info span {
            font-weight: 600;
            color: #333;
        }

        .btn-primary, .btn-secondary {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 0 10px;
            transition: background-color 0.2s ease;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Responsividade para a tabela de itens */
        @media (max-width: 768px) {
            .order-items-table th,
            .order-items-table td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
            .order-items-table .product-info img {
                width: 40px;
                height: 40px;
            }
        }

        @media (max-width: 576px) {
            .order-items-table thead {
                display: none; /* Oculta o cabeçalho da tabela em mobile */
            }
            .order-items-table, .order-items-table tbody, .order-items-table tr, .order-items-table td {
                display: block; /* Faz com que as células se comportem como blocos */
                width: 100%;
            }
            .order-items-table tr {
                margin-bottom: 15px;
                border: 1px solid #eee;
                border-radius: 8px;
                padding: 10px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .order-items-table td {
                text-align: right;
                padding-left: 50%; /* Espaço para a "label" virtual */
                position: relative;
                border: none;
            }
            .order-items-table td::before {
                content: attr(data-label); /* Usa o atributo data-label para mostrar o nome da coluna */
                position: absolute;
                left: 10px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: 600;
                color: #555;
                text-align: left;
            }
            .order-items-table .product-info {
                justify-content: flex-end; /* Alinha o produto à direita */
                text-align: right;
            }
            .order-items-table .product-info::before {
                content: "Produto:";
            }
            .order-items-table td:nth-child(2)::before { /* Preço Unit. */
                content: "Preço Unit.:";
            }
            .order-items-table td:nth-child(3)::before { /* Qtd. */
                content: "Qtd.:";
            }
            .order-items-table td:nth-child(4)::before { /* Subtotal */
                content: "Subtotal:";
            }
            .order-items-table .product-info img {
                margin-right: 0; /* Remove margem extra */
            }
        }
    </style>
</head>
<body>
    <?php include ABSPATH . 'includes/header.php'; ?>

    <div class="container order-confirmation-page">
        <h1>Confirmação do Pedido</h1>

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

        <div class="order-summary-details">
            <p><strong>Número do Pedido:</strong> #<?php echo htmlspecialchars($order['order_number']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($order['status'])); ?></p>
            <p><strong>Data do Pedido:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
            <p><strong>Método de Pagamento:</strong>
                <?php
                    echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method'])));
                    if ($order['payment_method'] === 'credit_card' && $order['installments'] > 1) {
                        $installmentValue = $order['total'] / $order['installments'];
                        echo " em " . htmlspecialchars($order['installments']) . "x de R$ " . number_format($installmentValue, 2, ',', '.');
                    }
                ?>
            </p>
            <p><strong>Método de Frete:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['shipping_method'] ?? 'Não informado'))); ?></p>
            <p><strong>Custo do Frete:</strong> R$ <?php echo number_format($order['shipping_cost'], 2, ',', '.'); ?></p>
            <p><strong>Total do Pedido:</strong> R$ <?php echo number_format($order['total'], 2, ',', '.'); ?></p>
        </div>

        <h2 class="section-title">Itens do Pedido</h2>
        <?php if (!empty($orderItems)): ?>
            <table class="order-items-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Preço Unit.</th>
                        <th>Qtd.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td class="product-info" data-label="Produto">
                                <img src="<?php echo getSiteUrl($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                            </td>
                            <td data-label="Preço Unit.">R$ <?php echo number_format($item['unit_price'], 2, ',', '.'); ?></td>
                            <td data-label="Qtd."><?php echo htmlspecialchars($item['quantity']); ?></td>
                            <td data-label="Subtotal">R$ <?php echo number_format($item['total_line'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhum item encontrado para este pedido.</p>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="<?php echo getSiteUrl('pages/my_orders.php'); ?>" class="btn-primary">Ver Meus Pedidos</a>
            <a href="<?php echo getSiteUrl('index.php'); ?>" class="btn-secondary">Continuar Comprando</a>
        </div>
    </div>

    <?php include ABSPATH . 'includes/footer.php'; ?>
</body>
</html>