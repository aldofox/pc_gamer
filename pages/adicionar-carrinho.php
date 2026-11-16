<?php
require_once '../includes/functions.php';
// Inicia a sessão
ensureSessionStarted();
// DEBUG: Verifica se chegou aqui
error_log("=== ADICIONAR AO CARRINHO - INÍCIO ===");
// Verifica se o formulário foi enviado via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Método não é POST. Redirecionando para index.");
    redirect(getSiteUrl('index.php'));
    exit;
}
// DEBUG: Mostra os dados recebidos
error_log("POST recebido: " . print_r($_POST, true));
// Obtém os dados do formulário
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
error_log("Product ID: $productId, Quantity: $quantity");
// Valida os dados
if ($productId <= 0 || $quantity <= 0) {
    error_log("Dados inválidos.");
    $_SESSION['error_message'] = 'Dados inválidos.';
    redirect(getSiteUrl('index.php'));
    exit;
}
// Tenta adicionar ao carrinho
error_log("Tentando adicionar ao carrinho...");
if (addToCart($productId, $quantity)) {
    error_log("Produto adicionado com sucesso!");
    $_SESSION['success_message'] = 'Produto adicionado ao carrinho com sucesso!';

    // Redireciona de volta para a página do produto ou para o carrinho
    if (isset($_POST['redirect']) && $_POST['redirect'] === 'cart') {
        error_log("Redirecionando para carrinho...");
        redirect(getSiteUrl('pages/carrinho.php'));
    } else {
        // Volta para a página anterior (produto)
        $referer = $_SERVER['HTTP_REFERER'] ?? getSiteUrl('index.php');
        error_log("Redirecionando para: $referer");
        redirect($referer);
    }
} else {
    error_log("Falha ao adicionar produto ao carrinho.");
    $_SESSION['error_message'] = 'Não foi possível adicionar o produto ao carrinho. Verifique o estoque.';

    // Volta para a página anterior
    $referer = $_SERVER['HTTP_REFERER'] ?? getSiteUrl('index.php');
    error_log("Redirecionando para: $referer");
    redirect($referer);
}
error_log("=== ADICIONAR AO CARRINHO - FIM ===");
exit;