<?php
require_once '../includes/functions.php';

ensureSessionStarted();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(getSiteUrl('pages/carrinho.php'));
    exit;
}

clearCart();
$_SESSION['success_message'] = 'Carrinho limpo com sucesso!';

redirect(getSiteUrl('pages/carrinho.php'));
exit;
