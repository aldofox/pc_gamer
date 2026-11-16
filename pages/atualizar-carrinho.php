<?php

require_once '../includes/functions.php';
ensureSessionStarted();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(getSiteUrl('pages/carrinho.php'));
    exit;
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id']: 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if (updateCartQuantity($productId, $quantity)) {
    $_SESSION['success_message'] = 'Quantidade atualizada com Sucesso!';
} else {
    $_SESSION['error_message'] = 'Nao foi possivel atualizar a quantidade.';
}

redirect(getSiteUrl('pages/carrinho.php'));
exit;