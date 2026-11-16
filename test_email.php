<?php
define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php';
require_once ABSPATH . 'includes/functions.php';

// ============================================================
// PASSO 1: Descubra um ID válido na tabela orders
// Descomente as 3 linhas abaixo, acesse a página,
// anote um ID da lista, depois comente de volta.
// ============================================================
// $db = db();
// $pdo = $db->getPdo();
// $stmt = $pdo->query("SELECT id, order_number, user_id FROM orders ORDER BY id DESC LIMIT 10");
// echo '<pre>'; print_r($stmt->fetchAll(PDO::FETCH_ASSOC)); echo '</pre>'; die;

// ============================================================
// PASSO 2: Coloque aqui o ID correto que você anotou acima
// ============================================================
$orderId = 15; // <<< MUDE AQUI para um ID real da sua tabela orders

// ============================================================
// PASSO 3: Busca os dados do pedido
// ============================================================
$db  = db();
$pdo = $db->getPdo();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se o pedido existe
if (!$order) {
    die("<pre style='color:red;'>ERRO: Nenhum pedido encontrado com ID = {$orderId}.\nVerifique o ID e tente novamente.</pre>");
}

// Mostra o pedido encontrado para conferência
echo "<pre style='background:#eeffee;border:1px solid green;padding:10px;'>";
echo "✅ Pedido encontrado:\n";
print_r($order);
echo "</pre>";

// ============================================================
// PASSO 4: Busca os itens do pedido
// ============================================================
$stmt = $pdo->prepare("
    SELECT 
        product_name, 
        quantity, 
        unit_price AS price
    FROM order_items
    WHERE order_id = ?
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) {
    die("<pre style='color:red;'>ERRO: Nenhum item encontrado para o pedido ID = {$orderId}.</pre>");
}

// Mostra os itens encontrados para conferência
echo "<pre style='background:#eeffee;border:1px solid green;padding:10px;'>";
echo "✅ Itens do pedido:\n";
print_r($items);
echo "</pre>";

// ============================================================
// PASSO 5: Monta os dados para o e-mail
// ============================================================

// Decodifica o endereço
$shippingAddressData = !empty($order['shipping_address_snapshot'])
    ? json_decode($order['shipping_address_snapshot'], true)
    : null;

$shippingAddress = $shippingAddressData
    ? implode(", ", array_filter([
        ($shippingAddressData['address'] ?? '') . (!empty($shippingAddressData['number']) ? ', ' . $shippingAddressData['number'] : ''),
        $shippingAddressData['neighborhood'] ?? '',
        $shippingAddressData['city'] ?? '',
        $shippingAddressData['state'] ?? '',
        $shippingAddressData['zip_code'] ?? '',
    ]))
    : 'Endereço não disponível';

$orderDataForEmail = [
    'id'                => $order['id'],
    'order_number'      => $order['order_number'],
    'customer_name'     => 'Teste Manual',
    'order_date'        => $order['created_at'],
    'status'            => $order['status'],
    'shipping_address'  => $shippingAddress,
    'payment_method'    => $order['payment_method'],
    'total'             => (float)($order['total'] ?? 0),
    'subtotal_products' => (float)($order['subtotal'] ?? 0),
    'shipping_cost'     => (float)($order['shipping_cost'] ?? 0),
    'discount_amount'   => (float)($order['discount'] ?? 0),
];

$orderItemsForEmail = [];
foreach ($items as $it) {
    $orderItemsForEmail[] = [
        'name'     => $it['product_name'],
        'quantity' => (int)($it['quantity'] ?? 0),
        'subtotal' => (float)($it['price'] ?? 0) * (int)($it['quantity'] ?? 0),
    ];
}

// ============================================================
// PASSO 6: Envia o e-mail de teste
// ============================================================
$emailDestino = 'aldosilveira@gmail.com'; // <<< MUDE AQUI

echo "<pre style='background:#fffbe6;border:1px solid orange;padding:10px;'>";
echo "📧 Tentando enviar e-mail para: {$emailDestino}\n";
echo "</pre>";

$result = sendOrderConfirmationEmail($orderDataForEmail, $orderItemsForEmail, $emailDestino);

if ($result) {
    echo "<pre style='background:#eeffee;border:1px solid green;padding:10px;'>✅ E-mail enviado com sucesso!</pre>";
} else {
    echo "<pre style='background:#fee;border:1px solid red;padding:10px;'>❌ Falha ao enviar o e-mail. Verifique o log de erros do PHP (error_log).</pre>";
}