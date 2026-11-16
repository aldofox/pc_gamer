<?php
// actions/update_cart.php

session_start();

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Erro desconhecido.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de requisição inválido.');
    }

    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $action    = $_POST['action'] ?? '';
    $quantity  = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    // DEBUG
    error_log("=== update_cart.php ===");
    error_log("POST: " . print_r($_POST, true));
    error_log("SESSION cart antes: " . print_r($_SESSION['cart'] ?? [], true));

    if ($productId <= 0) {
        throw new Exception('ID do produto inválido.');
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || !array_key_exists($productId, $_SESSION['cart'])) {
        throw new Exception('Produto não encontrado no carrinho.');
    }

    switch ($action) {
        case 'update_quantity':
            // Se quantity <= 0, remove; se > 0, atualiza respeitando estoque
            if (!updateCartQuantity($productId, $quantity)) {
                throw new Exception('Não foi possível atualizar a quantidade. Verifique o estoque disponível.');
            }
            $response['success'] = true;
            $response['message'] = $quantity <= 0
                ? 'Item removido do carrinho.'
                : 'Quantidade atualizada.';
            break;

        case 'remove_item':
            if (!removeFromCart($productId)) {
                throw new Exception('Não foi possível remover o item do carrinho.');
            }
            $response['success'] = true;
            $response['message'] = 'Item removido do carrinho.';
            break;

        default:
            throw new Exception('Ação inválida.');
    }

    // Recalcula totais após a operação
    $cartTotal = 0;
    $itemCount = 0;

    foreach ($_SESSION['cart'] ?? [] as $item) {
        $priceToUse = ($item['promotional_price'] > 0 && ($item['is_promotion'] ?? false))
            ? (float)$item['promotional_price']
            : (float)$item['unit_price'];
        $cartTotal += $priceToUse * $item['quantity'];
        $itemCount += $item['quantity'];
    }

    $response['cart_total']     = number_format($cartTotal, 2, ',', '.');
    $response['cart_total_raw'] = $cartTotal;
    $response['item_count']     = $itemCount;

    // Prepara itens para JS com chaves string
    $cartItemsForJs = [];
    foreach ($_SESSION['cart'] ?? [] as $pid => $item) {
        $cartItemsForJs[(string)$pid] = $item;
    }
    $response['cart_items'] = $cartItemsForJs;

    // DEBUG
    error_log("SESSION cart depois: " . print_r($_SESSION['cart'] ?? [], true));
    error_log("Resposta: " . print_r($response, true));

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Erro em update_cart.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;