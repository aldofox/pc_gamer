<?php
require_once '../includes/functions.php';

ensureSessionStarted();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(getSiteUrl('pages/carrinho.php'));
    exit;
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if (removeFromCart($productId)) {
    $_SESSION['success_message'] = ' ✅ Produto removido do carrinho com Sucesso.';
} else {
    $_SESSION['error_message'] = '❌ Nao foi possivel remover o produto do carrinho.';
}

redirect(getSiteUrl('pages/carrinho.php'));
exit;