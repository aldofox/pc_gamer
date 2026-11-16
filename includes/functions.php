<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}


require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php'; 

// ============================================
// 🔐 FUNÇÕES DE AUTENTICAÇÃO E USUÁRIOS
// ============================================
/**
 * Registra um novo usuário
 */
function registerUser($name, $email, $password, $phone = null) {
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
    if (!$email) {
        return ['success' => false, 'message' => 'Email inválido'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Senha deve ter pelo menos 6 caracteres'];
    }
    try {
        $db = db();

        // Verifica se email já existe
        $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            return ['success' => false, 'message' => 'Email já cadastrado'];
        }

        // Hash da senha
        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);

        // Insere novo usuário
        $userId = $db->insert(
            "INSERT INTO users (name, email, password_hash, phone, is_active, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())",
            [$name, $email, $password_hash, $phone]
        );

        // Log administrativo
        addAdminLog(null, "Novo usuário registrado", "users", $userId);

        return ['success' => true, 'message' => 'Usuário registrado com sucesso', 'user_id' => $userId];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao registrar: ' . $e->getMessage()];
    }
}
/**
 * Faz login do usuário
 */
function loginUser($email, $password) {
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);

    if (!$email) {
        return ['success' => false, 'message' => 'Email inválido'];
    }

    try {
        $db = db();

        $user = $db->fetchOne(
            "SELECT id, name, email, password_hash, is_admin, is_active FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Email ou senha incorretos'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Usuário desativado'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Email ou senha incorretos'];
        }

        // Define sessão
        ensureSessionStarted(); // Garante que a sessão está iniciada
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['login_time'] = time();

        // Log administrativo
        addAdminLog($user['id'], "Login realizado", "users", $user['id']);

        return ['success' => true, 'message' => 'Login realizado com sucesso', 'is_admin' => $user['is_admin']];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao fazer login: ' . $e->getMessage()];
    }
}
/**
 * Faz logout do usuário
 */
function logoutUser() {
    ensureSessionStarted(); // Garante que a sessão está iniciada
    if (isset($_SESSION['user_id'])) {
        addAdminLog($_SESSION['user_id'], "Logout realizado", "users", $_SESSION['user_id']);
    }
    session_destroy();
    return true;
}
/**
 * Obtém dados do usuário logado
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $db = db();
        return $db->fetchOne(
            "SELECT id, name, email, cpf, phone, is_admin, is_active, created_at FROM users WHERE id = ?",
            [getUserId()]
        );
    } catch (Exception $e) {
        return null;
    }
}
/**
 * Atualiza dados do usuário
 */
function updateUserProfile($name, $phone = null, $cpf = null) {
    if (!isLoggedIn()) {
        return ['success' => false, 'message' => 'Usuário não autenticado'];
    }

    try {
        $db = db();

        $db->execute(
            "UPDATE users SET name = ?, phone = ?, cpf = ?, updated_at = NOW() WHERE id = ?",
            [$name, $phone, $cpf, getUserId()]
        );

        ensureSessionStarted(); // Garante que a sessão está iniciada
        $_SESSION['user_name'] = $name;

        addAdminLog(getUserId(), "Perfil atualizado", "users", getUserId());

        return ['success' => true, 'message' => 'Perfil atualizado com sucesso'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()];
    }
}
/**
 * Altera a senha do usuário
 */
function changePassword($oldPassword, $newPassword) {
    if (!isLoggedIn()) {
        return ['success' => false, 'message' => 'Usuário não autenticado'];
    }

    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Nova senha deve ter pelo menos 6 caracteres'];
    }

    try {
        $db = db();

        $user = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [getUserId()]);

        if (!password_verify($oldPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Senha atual incorreta'];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);

        $db->execute(
            "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
            [$newHash, getUserId()]
        );

        addAdminLog(getUserId(), "Senha alterada", "users", getUserId());

        return ['success' => true, 'message' => 'Senha alterada com sucesso'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao alterar: ' . $e->getMessage()];
    }
}
// ============================================
// 📦 FUNÇÕES DE PRODUTOS
// ============================================
/**
 * Obtém todos os produtos ativos
 */
function getAllProducts($limit = null, $offset = 0) {
    try {
        $db = db();
        $sql = "SELECT * FROM products WHERE is_active = 1 ORDER BY created_at DESC";
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            return $db->fetchAll($sql, [$limit, $offset]);
        }
        return $db->fetchAll($sql);
    } catch (Exception $e) {
        return [];
    }
}
/**
 * Obtém produtos em promoção
 */
function getPromotionalProducts($limit = 6) {
    try {
        $db = db();
        return $db->fetchAll(
            "SELECT * FROM products
            WHERE is_active = 1 AND is_promotion = 1
            ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    } catch (Exception $e) {
        return [];
    }
}
/**
 * Obtém produtos em destaque (featured)
 */
function getFeaturedProducts($limit = 6) {
    try {
        $db = db();

        return $db->fetchAll(
            "SELECT * FROM products
            WHERE is_active = 1 AND featured = 1
            ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );

    } catch (Exception $e) {
        return [];
    }
}
/**
 * Obtém produtos por categoria
 */
function getProductsByCategory($categoryId, $limit = 12, $offset = 0) {
    try {
        $db = db();

        return $db->fetchAll(
            "SELECT * FROM products
            WHERE is_active = 1 AND category_id = ?
            ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$categoryId, $limit, $offset]
        );

    } catch (Exception $e) {
        return [];
    }
}
/**
 * Obtém um produto por slug
 */
function getProductBySlug($slug) {
    try {
        $db = db();
        // Busca produto + nome e slug da categoria
        $product = $db->fetchOne(
            "SELECT
                p.*,
                COALESCE(c.name, 'Sem Categoria') AS category_name,
                COALESCE(c.slug, '') AS category_slug
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.slug = ? AND p.is_active = 1",
            [$slug]
        );
        if ($product) {
            // Incrementa visualizações
            $db->execute("UPDATE products SET views = views + 1 WHERE id = ?", [$product['id']]);
            // Busca imagens adicionais
            $product['images'] = $db->fetchAll(
                "SELECT image_url
                FROM product_images
                WHERE product_id = ?
                ORDER BY sort_order",
                [$product['id']]
            );
            // Garante que as chaves existam SE o produto não tiver categoria
            if (!isset($product['category_name'])) {
                $product['category_name'] = 'Sem Categoria';
            }
            if (!isset($product['category_slug'])) {
                $product['category_slug'] = '';
            }
        }
        return $product;
    } catch (Exception $e) {
        error_log("Erro em getProductBySlug: " . $e->getMessage());
        return null;
    }
}
/**
 * Busca produtos por termo
 */
// function searchProducts($term, $limit = 20) {
//     try {
//         $db = db();

//         $searchTerm = '%' . $term . '%';

//         return $db->fetchAll(
//             "SELECT * FROM products
//             WHERE is_active = 1 AND (name LIKE ? OR short_description LIKE ? OR description LIKE ?)
//             ORDER BY name ASC LIMIT ?",
//             [$searchTerm, $searchTerm, $searchTerm, $limit]
//         );

//     } catch (Exception $e) {
//         return [];
//     }
// }
/**
 * Obtém produtos similares (mesma categoria)
 */
function getSimilarProducts($productId, $limit = 4) {
    try {
        $db = db();

        $product = $db->fetchOne("SELECT category_id FROM products WHERE id = ?", [$productId]);

        if (!$product) {
            return [];
        }

        return $db->fetchAll(
            "SELECT * FROM products
            WHERE is_active = 1 AND category_id = ? AND id != ?
            ORDER BY RAND() LIMIT ?",
            [$product['category_id'], $productId, $limit]
        );

    } catch (Exception $e) {
        return [];
    }
}
/**
 * Obtém produtos com estoque baixo
 */
function getLowStockProducts($limit = 10) {
    try {
        $db = db();

        return $db->fetchAll(
            "SELECT * FROM products
            WHERE stock_qty <= min_stock AND is_active = 1
            ORDER BY stock_qty ASC LIMIT ?",
            [$limit]
        );

    } catch (Exception $e) {
        return [];
    }
}

/**
 * Obtém banners ativos com dados do produto associado
 */
function getActiveBanners($limit = 5) {
    try {
        $db = db();
        return $db->fetchAll(
            "SELECT b.*, p.name AS product_name, p.short_description, p.unit_price, p.promotional_price, p.slug AS product_slug
            FROM banners b
            JOIN products p ON b.product_id = p.id
            WHERE b.is_active = 1
            ORDER BY b.sort_order ASC LIMIT ?",
            [$limit]
        );
    } catch (Exception $e) {
        error_log("Erro ao obter banners: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtém produtos de uma categoria específica para carrosséis
 */
function getProductsForCarouselByCategory($categoryId, $limit = 6) {
    try {
        $db = db();
        return $db->fetchAll(
            "SELECT id, name, slug, short_description, image_url, unit_price, promotional_price, stock_qty
            FROM products
            WHERE category_id = ? AND is_active = 1
            ORDER BY created_at DESC LIMIT ?",
            [$categoryId, $limit]
        );
    } catch (Exception $e) {
        error_log("Erro ao obter produtos para carrossel: " . $e->getMessage());
        return [];
    }
}
/**
 * Obtém produtos relacionados (mesma categoria)
 */
function getRelatedProducts($categoryId, $excludeProductId, $limit = 6) {
    try {
        $db = db();
        return $db->fetchAll(
            "SELECT id, name, slug, short_description, image_url, unit_price, promotional_price, stock_qty
            FROM products
            WHERE category_id = ? AND id != ? AND is_active = 1
            ORDER BY RAND() LIMIT ?",
            [$categoryId, $excludeProductId, $limit]
        );
    } catch (Exception $e) {
        error_log("Erro ao obter produtos relacionados: " . $e->getMessage());
        return [];
    }
}
/**
 * Obtém avaliações de um produto
 */
/**
 * Retorna as avaliações (reviews) de um produto específico.
 *
 * @param int $productId O ID do produto.
 * @param bool $onlyApproved Se true, retorna apenas reviews aprovadas.
 * @return array Um array de reviews.
 */
function getProductReviews($productId, $onlyApproved = true) {
    $db = db();
    $pdo_instance = $db->getPdo();

    if (!$pdo_instance) {
        error_log("Erro: Conexão PDO não disponível em getProductReviews.");
        return [];
    }

    $sql = "
        SELECT 
            r.*, 
            c.name AS customer_name, 
            c.avatar_url 
        FROM 
            reviews r
        JOIN 
            customers c ON r.user_id = c.id  -- <<<<<< CORRIGIDO AQUI: r.user_id
        WHERE 
            r.product_id = ?
    ";

    $params = [$productId];

    if ($onlyApproved) {
        $sql .= " AND r.is_approved = 1";
    }

    $sql .= " ORDER BY r.created_at DESC";

    try {
        $stmt = $pdo_instance->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar reviews do produto (ID: {$productId}): " . $e->getMessage());
        return [];
    }
}
/**
 * Obtém informações de preço do produto
 */
function getProductPrice($product) {
    $originalPrice = $product['unit_price'];
    $promotionalPrice = $product['promotional_price'] ?? $originalPrice;
    $onSale = $promotionalPrice < $originalPrice;
    $discount = $onSale ? round((($originalPrice - $promotionalPrice) / $originalPrice) * 100) : 0;
    return [
        'original' => $originalPrice,
        'final' => $onSale ? $promotionalPrice : $originalPrice,
        'on_sale' => $onSale,
        'discount' => $discount
    ];
}
// ============================================
// 🛒 FUNÇÕES DE CARRINHO
// ============================================
/**
 * Obtém ou cria o carrinho do usuário
 */
function getCart() {
    try {
        $db = db();

        if (isLoggedIn()) {
            $cart = $db->fetchOne(
                "SELECT id FROM carts WHERE user_id = ?",
                [getUserId()]
            );

            if (!$cart) {
                $cartId = $db->insert(
                    "INSERT INTO carts (user_id, created_at) VALUES (?, NOW())",
                    [getUserId()]
                );
                return ['id' => $cartId, 'user_id' => getUserId()];
            }

            return $cart;
        } else {
            // Para visitantes, usa session_id
            $sessionId = session_id();
            $cart = $db->fetchOne(
                "SELECT id FROM carts WHERE session_id = ?",
                [$sessionId]
            );

            if (!$cart) {
                $cartId = $db->insert(
                    "INSERT INTO carts (session_id, created_at) VALUES (?, NOW())",
                    [$sessionId]
                );
                return ['id' => $cartId, 'session_id' => $sessionId];
            }

            return $cart;
        }

    } catch (Exception $e) {
        return null;
    }
}
/*****
 * Inicia a sessao se ainda nao foi iniciado
 */
function ensureSessionStarted(){
    if (session_status() === PHP_SESSION_NONE){
        session_start();
    }
}
/**
 * Adiciona um produto ao carrinho
 * @param int $productId ID do produto
 * @param int $quantity Quantidade
 * @return bool True se adicionado com sucesso
 */
// ... (código anterior)

/**
 * Adiciona um produto ao carrinho
 * @param int $productId ID do produto
 * @param int $quantity Quantidade
 * @return bool True se adicionado com sucesso
 */
// Em C:\Apache24\htdocs\pc_gamer\includes\functions.php

/**
 * Adiciona um produto ao carrinho
 * @param int $productId ID do produto
 * @param int $quantity Quantidade
 * @return bool True se adicionado com sucesso
 */
function addToCart($productId, $quantity = 1) {
    ensureSessionStarted();

    // Busca informações do produto
    $product = getProductById($productId); // <--- ESTA FUNÇÃO DEVE RETORNAR 'image_url'

    if (!$product || $product['stock_qty'] < $quantity) {
        return false;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $product = getProductById($productId); 
    
    // Se o produto já está no carrinho, aumenta a quantidade
    if (array_key_exists($productId, $_SESSION['cart'])) {
        $newQuantity = $_SESSION['cart'][$productId]['quantity'] + $quantity;

        // Verifica se não excede o estoque
        if ($newQuantity > $product['stock_qty']) {
            return false;
        }

        $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
    } else {
        // Adiciona novo produto ao carrinho
        $_SESSION['cart'][$productId] = [
            'product_id' => $productId,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'image_url' => $product['image_url'], // <--- ESTA LINHA É CRÍTICA!
            'unit_price' => $product['unit_price'],
            'promotional_price' => $product['promotional_price'],
            'quantity' => $quantity,
            'stock_qty' => $product['stock_qty'],
            'is_promotion' => $product['is_promotion'] ?? false,
            'weight' => $product['weight'] ?? 0.1,
            'length' => $product['length'] ?? 10,
            'height' => $product['height'] ?? 10,
            'width' => $product['width'] ?? 10
        ];
    }

    return true;
}



/****
 * Atualiza a quantidade de um produto no carrinho
 * @param int $productId ID do produto
 * @param int $quantity Nova Quantidade
 * @return bool True se atualizado com sucesso
 */
function updateCartQuantity($productId, $quantity) {
    ensureSessionStarted();
    if (!isset($_SESSION['cart'][$productId])) {
        return false;
    }
    //Se quantidade for 0 ou negativo, remove o item
    if($quantity <= 0) {
        return removeFromCart($productId);
    }
    //Busca informações do produto para verificar estoque
    $product = getProductById($productId);
    if(!$product || $quantity > $product['stock_qty']){
        return false;
    }
    $_SESSION['cart'][$productId]['quantity'] = $quantity;
    return true;
}
/****
 * Remove item do carrinho
 * @param int $productId ID do produto
 * @return bool True se removido com sucesso
 */
function removeFromCart($productId) {
    ensureSessionStarted();

    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
        return true;
    }
}
/**
 * Atualiza quantidade de item no carrinho
 */
function updateCartItemQuantity($cartItemId, $quantity) {
    if ($quantity < 1) {
        return removeFromCart($cartItemId);
    }

    try {
        $db = db();

        // Verifica estoque
        $item = $db->fetchOne(
            "SELECT ci.product_id, p.stock_qty FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.id = ?",
            [$cartItemId]
        );

        if (!$item) {
            return ['success' => false, 'message' => 'Item não encontrado'];
        }

        if ($item['stock_qty'] < $quantity) {
            return ['success' => false, 'message' => 'Estoque insuficiente'];
        }

        $db->execute(
            "UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?",
            [$quantity, $cartItemId]
        );

        return ['success' => true, 'message' => 'Quantidade atualizada'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao atualizar: ' . $e->getMessage()];
    }
}
/**
 * Obtém itens do carrinho
 * @return array Itens fdo Carrinho
 */
/**
 * Retorna todos os itens do carrinho com detalhes completos dos produtos
 * @return array
 */
function getCartItems() {
    ensureSessionStarted();

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return [];
    }

    $cartItems = [];

    foreach ($_SESSION['cart'] as $productId => $cartItem) {
        // Busca os detalhes completos do produto no banco de dados
        $product = getProductById($productId);

        if ($product) {
            // Mescla os dados do produto com a quantidade do carrinho
            $cartItems[] = array_merge($product, [
                'product_id' => $productId,
                'quantity' => $cartItem['quantity']
            ]);
        }
    }

    return $cartItems;
}

/*****
 * @return int Total de itens
 */
function getCarItemCount(){
    $items = getCartItems();
    $count = 0;

    foreach ($items as $item) {
        $count += $item['quantity'];
    }
    return $count;
}
/**
 * Calcula o subtotal do carrinho
 */
function getCartSubtotal(): float
{
    $items = getCartItems();
    $subtotal = 0.0;

    foreach ($items as $item) {
        $price = $item['promotional_price'] ?? $item['unit_price'];

        $subtotal += (float) $price * (int) $item['quantity'];
    }

    return $subtotal;
}
/**
 * Calculo total do carrinho (com frete, descontos, etc)
 * @param float $shipping Valor do frete
 * @param float $discount Valor do desconto
 * @return float Total
 */
function getCartTotal(float $shipping = 0.0, float $discount = 0.0): array
{
    $subtotal = getCartSubtotal();

    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'discount' => $discount,
        'total' => $subtotal + $shipping - $discount
    ];
}
/*****
 * Verifica se o Carrinho esta vazio
 * @return bool True se vazio
 */
function isCartEmpty() {
    ensureSessionStarted();
    $items = getCartItems();
    return empty($items);
}
/**
 * Limpa o carrinho
 */
function clearCart() {
    ensureSessionStarted();
    $_SESSION['cart'] = []; // Corrigido espaço em branco
}
// ============================================
// 📋 FUNÇÕES DE CATEGORIAS
// ============================================
/**
 * Obtém uma categoria por slug
 */
function getCategoryBySlug($slug) {
    try {
        $db = db();

        return $db->fetchOne(
            "SELECT * FROM categories WHERE slug = ? AND is_active = 1",
            [$slug]
        );

    } catch (Exception $e) {
        return null;
    }
}
// ============================================
// 💳 FUNÇÕES DE PEDIDOS
// ============================================
/**
 * Cria um novo pedido
 */
function createOrder($paymentMethod, $shippingAddressId, $notes = null) {
    if (!isLoggedIn()) {
        return ['success' => false, 'message' => 'Usuário não autenticado'];
    }

    try {
        $db = db();
        // Obtém itens do carrinho
        $cartItems = getCartItems();
        if (empty($cartItems)) {
            return ['success' => false, 'message' => 'Carrinho vazio'];
        }
        // Calcula totais
        $totals = getCartTotal();
        // Valida endereço
        $address = $db->fetchOne(
            "SELECT * FROM addresses WHERE id = ? AND user_id = ?",
            [$shippingAddressId, getUserId()]
        );
        if (!$address) {
            return ['success' => false, 'message' => 'Endereço inválido'];
        }
        // Inicia transação
        $db->beginTransaction();
        try {
            // Gera número do pedido
            $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            // Cria pedido
            $orderId = $db->insert(
                "INSERT INTO orders (user_id, order_number, status, subtotal, shipping_cost, total, payment_method, shipping_address_id, notes, created_at)
                VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())",
                [getUserId(), $orderNumber, $totals['subtotal'], $totals['shipping'], $totals['total'], $paymentMethod, $shippingAddressId, $notes]
            );
            // Adiciona itens do pedido
            foreach ($cartItems as $item) {
                $price = $item['promotional_price'] ?? $item['unit_price'];
                $totalLine = $price * $item['quantity'];
                $db->insert(
                    "INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total_line, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$orderId, $item['product_id'], $item['name'], $item['quantity'], $price, $totalLine]
                );
                // Atualiza estoque
                $db->execute(
                    "UPDATE products SET stock_qty = stock_qty - ?, sales_count = sales_count + 1 WHERE id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }
            // Limpa carrinho
            // A função getCart() retorna um array, mas o delete FROM cart_items WHERE cart_id = ? espera um ID.
            // Se você está usando $_SESSION['cart'] para o carrinho, esta linha pode ser removida ou ajustada.
            // Se você tem uma tabela 'cart_items' no banco, o cart_id precisa ser o ID do carrinho do banco.
            // Por enquanto, vou comentar para evitar erro, assumindo que o carrinho é baseado em sessão.
            // $cart = getCart();
            // $db->execute("DELETE FROM cart_items WHERE cart_id = ?", [$cart['id']]);

            // Registra histórico
            $db->insert(
                "INSERT INTO order_status_history (order_id, old_status, new_status, notes, changed_by, created_at)
                VALUES (?, NULL, 'pending', 'Pedido criado', ?, NOW())",
                [$orderId, getUserId()]
            );
            // Log administrativo
            addAdminLog(getUserId(), "Pedido criado", "orders", $orderId);
            $db->commit();
            return [
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'order_id' => $orderId,
                'order_number' => $orderNumber
            ];
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao criar pedido: ' . $e->getMessage()];
    }
}
/**
 * Obtém um pedido por ID
 */
function getOrderById($orderId) {
    try {
        $db = db();

        $order = $db->fetchOne(
            "SELECT * FROM orders WHERE id = ?",
            [$orderId]
        );

        if ($order) {
            // Busca itens do pedido
            $order['items'] = $db->fetchAll(
                "SELECT * FROM order_items WHERE order_id = ?",
                [$orderId]
            );

            // Busca histórico de status
            $order['history'] = $db->fetchAll(
                "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC",
                [$orderId]
            );
        }

        return $order;

    } catch (Exception $e) {
        return null;
    }
}
/**
 * Obtém pedidos do usuário logado
 */
function getUserOrders($limit = 10, $offset = 0) {
    if (!isLoggedIn()) {
        return [];
    }

    try {
        $db = db();

        return $db->fetchAll(
            "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [getUserId(), $limit, $offset]
        );

    } catch (Exception $e) {
        return [];
    }
}
/**
 * Atualiza status do pedido
 */

// ============================================
// 📍 FUNÇÕES DE ENDEREÇOS
// ============================================
/**
 * Adiciona um novo endereço para o usuário logado
 */
function addAddress($label, $recipientName, $street, $number, $complement, $neighborhood, $city, $state, $zipCode, $isDefault = 0) {
    if (!isLoggedIn()) {
        return ['success' => false, 'message' => 'Usuário não autenticado'];
    }

    try {
        $db = db();
        $userId = getUserId();

        // Se for para ser o padrão, desativa outros padrões
        if ($isDefault) {
            $db->execute("UPDATE addresses SET is_default = 0 WHERE user_id = ?", [$userId]);
        }

        $addressId = $db->insert(
            "INSERT INTO addresses (user_id, label, recipient_name, street, number, complement, neighborhood, city, state, zip_code, country, is_default, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Brasil', ?, NOW())",
            [$userId, $label, $recipientName, $street, $number, $complement, $neighborhood, $city, $state, $zipCode, $isDefault]
        );

        addAdminLog($userId, "Endereço adicionado", "addresses", $addressId);

        return ['success' => true, 'message' => 'Endereço adicionado com sucesso', 'address_id' => $addressId];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao adicionar endereço: ' . $e->getMessage()];
    }
}
/**
 * Obtém todos os endereços do usuário logado
 */
function getUserAddresses() {
    if (!isLoggedIn()) {
        return [];
    }

    try {
        $db = db();
        return $db->fetchAll(
            "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC",
            [getUserId()]
        );
    } catch (Exception $e) {
        return [];
    }
}
/**
 * Obtém um endereço específico por ID
 */
function getAddressById($addressId) {
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $db = db();
        return $db->fetchOne(
            "SELECT * FROM addresses WHERE id = ? AND user_id = ?",
            [$addressId, getUserId()]
        );
    } catch (Exception $e) {
        return null;
    }
}
/**
 * Atualiza um endereço existente
 */
function updateAddress($addressId, $label, $recipientName, $street, $number, $complement, $neighborhood, $city, $state, $zipCode, $isDefault = 0) {
    if (!isLoggedIn()) {
        return ['success' => false, 'message' => 'Usuário não autenticado'];
    }

    try {
        $db = db();
        $userId = getUserId();

        // Verifica se o endereço pertence ao usuário
        $existingAddress = $db->fetchOne("SELECT id FROM addresses WHERE id = ? AND user_id = ?", [$addressId, $userId]);
        if (!$existingAddress) {
            return ['success' => false, 'message' => 'Endereço não encontrado ou não pertence ao usuário'];
        }

        // Se for para ser o padrão, desativa outros padrões
        if ($isDefault) {
            $db->execute("UPDATE addresses SET is_default = 0 WHERE user_id = ?", [$userId]);
        }

        $db->execute(
            "UPDATE addresses SET label = ?, recipient_name = ?, street = ?, number = ?, complement = ?, neighborhood = ?, city = ?, state = ?, zip_code = ?, is_default = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?",
            [$label, $recipientName, $street, $number, $complement, $neighborhood, $city, $state, $zipCode, $isDefault, $addressId, $userId]
        );

        addAdminLog($userId, "Endereço atualizado", "addresses", $addressId);

        return ['success' => true, 'message' => 'Endereço atualizado com sucesso'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao atualizar endereço: ' . $e->getMessage()];
    }
}
/**
 * Remove um endereço
 */
function deleteAddress($addressId) {
    if (!isLoggedIn()) {
        return ['success' => false, 'message' => 'Usuário não autenticado'];
    }

    try {
        $db = db();
        $userId = getUserId();

        // Verifica se o endereço pertence ao usuário
        $existingAddress = $db->fetchOne("SELECT id FROM addresses WHERE id = ? AND user_id = ?", [$addressId, $userId]);
        if (!$existingAddress) {
            return ['success' => false, 'message' => 'Endereço não encontrado ou não pertence ao usuário'];
        }

        $db->execute("DELETE FROM addresses WHERE id = ? AND user_id = ?", [$addressId, $userId]);

        addAdminLog($userId, "Endereço removido", "addresses", $addressId);

        return ['success' => true, 'message' => 'Endereço removido com sucesso'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao remover endereço: ' . $e->getMessage()];
    }
}
// ============================================
// 💰 FUNÇÕES DE PAGAMENTOS
// ============================================
/**
 * Registra um pagamento para um pedido
 */
function registerPayment($orderId, $method, $amount, $installments = 1, $transactionId = null, $pixCode = null, $boletoUrl = null, $cardLast4 = null) {
    try {
        $db = db();
        $userId = getUserId(); // Pode ser null se o admin estiver registrando
        // Verifica se o pedido existe e pertence ao usuário (se logado)
        $order = $db->fetchOne("SELECT id, user_id, total FROM orders WHERE id = ?", [$orderId]);
        if (!$order) {
            return ['success' => false, 'message' => 'Pedido não encontrado'];
        }
        if ($userId && $order['user_id'] != $userId && !isAdmin()) {
            return ['success' => false, 'message' => 'Acesso negado ao pedido'];
        }
        if ($order['total'] != $amount) {
            // Isso pode ser ajustado para permitir pagamentos parciais ou com desconto de cupom
            // Por enquanto, exige que o valor seja exato
            return ['success' => false, 'message' => 'Valor do pagamento não corresponde ao total do pedido'];
        }
        $db->beginTransaction();
        try {
            $paymentId = $db->insert(
                "INSERT INTO payments (order_id, method, amount, installments, transaction_id, pix_code, boleto_url, card_last4, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [$orderId, $method, $amount, $installments, $transactionId, $pixCode, $boletoUrl, $cardLast4]
            );
            // Atualiza o status do pedido para 'paid' se o pagamento for aprovado imediatamente (ex: PIX)
            // Ou 'processing' se for boleto/cartão aguardando confirmação
            $newOrderStatus = 'pending'; // Padrão
            if ($method == 'pix' || $method == 'credit_card' || $method == 'debit_card') {
                // Em um sistema real, isso seria confirmado por um webhook do gateway de pagamento
                // Por simplicidade, vamos considerar aprovado para PIX e cartão
                $db->execute("UPDATE payments SET status = 'approved', approved_at = NOW() WHERE id = ?", [$paymentId]);
                $newOrderStatus = 'processing'; // Pedido pago e pronto para processamento
            } else if ($method == 'boleto') {
                $newOrderStatus = 'pending'; // Boleto aguarda compensação
            }
            updateOrderStatus($orderId, $newOrderStatus, "Pagamento registrado via " . $method);
            addAdminLog($userId, "Pagamento registrado", "payments", $paymentId);
            $db->commit();
            return ['success' => true, 'message' => 'Pagamento registrado com sucesso', 'payment_id' => $paymentId, 'order_status' => $newOrderStatus];
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao registrar pagamento: ' . $e->getMessage()];
    }
}
/**
 * Obtém pagamentos de um pedido
 */
function getOrderPayments($orderId) {
    try {
        $db = db();
        return $db->fetchAll(
            "SELECT * FROM payments WHERE order_id = ? ORDER BY created_at DESC",
            [$orderId]
        );
    } catch (Exception $e) {
        return [];
    }
}
/**
 * Atualiza o status de um pagamento (geralmente por webhook ou admin)
 */
function updatePaymentStatus($paymentId, $newStatus, $transactionId = null) {
    if (!isAdmin()) { // Apenas admins podem alterar status de pagamento diretamente
        return ['success' => false, 'message' => 'Acesso negado'];
    }

    try {
        $db = db();
        $payment = $db->fetchOne("SELECT order_id, status FROM payments WHERE id = ?", [$paymentId]);
        if (!$payment) {
            return ['success' => false, 'message' => 'Pagamento não encontrado'];
        }

        $db->beginTransaction();
        try {
            $updateSql = "UPDATE payments SET status = ?, updated_at = NOW()";
            $params = [$newStatus];

            if ($transactionId) {
                $updateSql .= ", transaction_id = ?";
                $params[] = $transactionId;
            }
            if ($newStatus == 'approved') {
                $updateSql .= ", approved_at = NOW()";
            }
            $updateSql .= " WHERE id = ?";
            $params[] = $paymentId;

            $db->execute($updateSql, $params);

            // Atualiza status do pedido se o pagamento principal for aprovado/rejeitado
            if ($newStatus == 'approved') {
                updateOrderStatus($payment['order_id'], 'processing', 'Pagamento aprovado');
            } elseif ($newStatus == 'rejected') {
                updateOrderStatus($payment['order_id'], 'canceled', 'Pagamento rejeitado');
            } elseif ($newStatus == 'refunded') {
                updateOrderStatus($payment['order_id'], 'refunded', 'Pagamento estornado');
            }

            addAdminLog(getUserId(), "Status do pagamento alterado para " . $newStatus, "payments", $paymentId);

            $db->commit();
            return ['success' => true, 'message' => 'Status do pagamento atualizado'];

        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro ao atualizar status do pagamento: ' . $e->getMessage()];
    }
}
// ============================================
// 🎁 FUNÇÕES DE CUPONS
// ============================================
/**
 * Obtém um cupom por código
 */
function getCouponByCode($code) {
    try {
        $db = db();
        return $db->fetchOne(
            "SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND valid_from <= NOW() AND valid_until >= NOW()",
            [$code]
        );
    } catch (Exception $e) {
        return null;
    }
}
/**
 * Aplica um cupom ao carrinho (apenas simula o desconto)
 */
function applyCouponToCart($couponCode) {
    $coupon = getCouponByCode($couponCode);
    if (!$coupon) {
        return ['success' => false, 'message' => 'Cupom inválido ou expirado'];
    }
    $totals = getCartTotal();
    if ($totals['subtotal'] < $coupon['min_purchase']) {
        return ['success' => false, 'message' => 'Valor mínimo de compra não atingido para este cupom'];
    }
    $discountAmount = 0;
    if ($coupon['discount_type'] == 'percentage') {
        $discountAmount = $totals['subtotal'] * ($coupon['discount_value'] / 100);
    } else { // fixed
        $discountAmount = $coupon['discount_value'];
    }
    // Garante que o desconto não seja maior que o subtotal
    $discountAmount = min($discountAmount, $totals['subtotal']);

    return [
        'success' => true,
        'message' => 'Cupom aplicado com sucesso!',
        'coupon' => $coupon,
        'discount_amount' => $discountAmount,
        'new_subtotal' => $totals['subtotal'] - $discountAmount,
        'new_total' => $totals['total'] - $discountAmount // Assume que o desconto afeta o total final
    ];
}
// ============================================
// 📝 FUNÇÕES DE ADMINISTRAÇÃO E LOGS
// ============================================
/**
 * Adiciona um log de ação administrativa
 */
function addAdminLog($adminId, $action, $entity = null, $entityId = null, $details = null) {
    try {
        $db = db();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        // Se adminId for null, tenta pegar da sessão (para ações de cliente que geram log)
        if ($adminId === null && isLoggedIn()) {
            $adminId = getUserId();
        }
        $db->insert(
            "INSERT INTO admin_logs (admin_id, action, entity, entity_id, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$adminId, $action, $entity, $entityId, json_encode($details), $ipAddress]
        );
        return true;
    } catch (Exception $e) {
        error_log("Erro ao adicionar log administrativo: " . $e->getMessage());
        return false;
    }
}
/**
 * Obtém logs administrativos
 */
function getAdminLogs($limit = 20, $offset = 0) {
    if (!isAdmin()) {
        return [];
    }
    try {
        $db = db();
        return $db->fetchAll(
            "SELECT al.*, u.name AS admin_name FROM admin_logs al
             LEFT JOIN users u ON al.admin_id = u.id
             ORDER BY al.created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    } catch (Exception $e) {
        return [];
    }
}
// ============================================
// ⚙️ FUNÇÕES GERAIS / UTILS
// ============================================
/**
 * Formata um valor monetário para BRL
 */
function formatCurrency($value) {
    return 'R$' . number_format($value,2, ',', '.');
}

/**
 * Deleta uma imagem
 */
function deleteImage($filePath) {
    $fullPath = UPLOAD_PATH . $filePath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}
// ============================================
// 🔐 FUNÇÕES DE SEGURANÇA - CSRF TOKEN
// ============================================
/**
 * Gera um token CSRF e armazena na sessão
 * @return string Token CSRF
 */
function generateCsrfToken() {
    // Inicia a sessão se ainda não foi iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Se já existe um token na sessão, retorna ele
    if (isset($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    }
    // Gera um novo token aleatório
    $token = bin2hex(random_bytes(32)); // 64 caracteres hexadecimais
    // Armazena na sessão
    $_SESSION['csrf_token'] = $token;
    return $token;
}
/**
 * Valida o token CSRF enviado pelo formulário
 * @param string $token Token enviado pelo formulário
 * @return bool True se válido, False se inválido
 */
function validateCsrfToken($token) {
    // Inicia a sessão se ainda não foi iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Verifica se existe um token na sessão
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    // Compara o token enviado com o token da sessão
    // Usa hash_equals() para evitar timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}
/**
 * Regenera o token CSRF (usar após validação bem-sucedida)
 */
function regenerateCsrfToken() {
    // Inicia a sessão se ainda não foi iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Gera um novo token
    $token = bin2hex(random_bytes(32));
    // Substitui o token antigo
    $_SESSION['csrf_token'] = $token;
    return $token;
}
/**
 * Obtém um produto pelo ID
 * @param int $productId ID do produto
 * @return array|null Dados do produto
 */
function getProductById($productId) {
    try {
        $db = db(); // Garante que a conexão com o banco está ativa
        return $db->fetchOne("SELECT * FROM products WHERE id = ? AND is_active = 1",[$productId]);
    } catch (Exception $e) {
        error_log("Erro ao obter produto por ID: " . $e->getMessage());
        return null;
    }
}

// ============================================
// 🔼 FUNÇÕES DO UPLOAD DE IMAGENS
// ============================================
/**
 * Faz o upload de uma imagem para o diretório especificado.
 * @param array $file Array $_FILES de um campo de upload.
 * @param string $uploadPath Caminho absoluto para o diretório de upload.
 * @param array $allowedTypes Tipos de arquivo permitidos (ex: ['jpg', 'png']).
 * @param int $maxSize Tamanho máximo do arquivo em bytes.
 * @return string|false O nome do arquivo salvo (com caminho relativo ao UPLOAD_PATH) em caso de sucesso, ou false em caso de erro.
 */

function uploadImage($file, $uploadPath, $allowedTypes, $maxSize){
    if (!isset($file['name']) || $file['error'] !== UPLOAD_ERR_OK){
        return false;
    }
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileType = $file['type'];

    $fileExt =  strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    //Verifica o Tipo de Arquivo
    if (!in_array($fileExt, $allowedTypes)) {
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Tipo de arquivo nao permitido. Apenas ' . implode(',', $allowedTypes) . 'sao aceitos.'];
        return false;
    }

    //Verifica o Tamanho do Arquivo
    if ($fileError !== 0) {
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Ocorreu um erro no upload do arquivo. Codigo: ' . $fileError];
        return false;
    }

    //Gera um nome unico para arquivo para evitar sobrescrever
    $newFileName = uniqid('', true) . '.' . $fileExt;
    $fileDestination = $uploadPath . $newFileName;

    //Tenta mover o arquivo para o destino final
    if (move_uploaded_file($fileTmpName, $fileDestination)){
         // Retorna o caminho relativo para salvar no banco de dados
        // Assumindo que UPLOAD_PATH é algo como 'assets/imagens/'
        return 'assets/images/produtos/' . $newFileName;
    } else{
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Falha ao mover o arquivo enviadd0.'];
        return false;
    }
}

/**
 * Deleta um produto do banco de dados e remove sua imagem do servidor
 * @param int $productId ID do produto a ser deletado
 * @return bool True se deletado com sucesso, false caso contrário
 */
function deleteProduct($productId){
    // Removido global $pdo;
    try{
        $db = db(); // Obtém a instância do banco

        //Primeiro busca o produto para obter a URL da imagem
        $product = $db->fetchOne("SELECT image_url FROM products WHERE id = ?", [$productId]);

        if (!$product) {
            error_log("Produto ID $productId nao encontrado para exclusao. 🤦‍♂️");
            return false;
        }
        //Deleta o produto do banco de dados
        $result = $db->execute("DELETE FROM products WHERE id = ?", [$productId]);

        if ($result) { // db()->execute retorna true em sucesso
            error_log("Produto ID $productId deletado com sucesso! ❌");

            //Tenta remover a imagem do servidor, se existir
            if (!empty($product['image_url'])){
                // Ajuste o caminho para ser relativo à raiz do projeto
                $imagePath = ABSPATH . $product['image_url'];

                if (file_exists($imagePath)) {
                    if (unlink($imagePath)) {
                        error_log("Imagem removido: " . $imagePath);
                    } else{
                        error_log("Falha ao Remover a Imagem: " . $imagePath);
                    }
                } else{
                    error_log("Arquivo de image no encontrado: " . $imagePath);
                }
            }
            return true;
        } else {
            error_log("Nenhuma linha foi afetada ao tentar deletar o Produto Codigo: $productId");
            return false;
        }
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao deletar o produto: " . $e->getMessage());
        return false;
    }
}

/**
 * Busca todos os clientes do banco de dados.
 * @return array Lista de clientes.
 */
function getAllCustomers() {
    try {
        // Usa db()->fetchAll() para buscar todos os clientes da tabela 'customers'
        // Seleciona as colunas que você quer exibir na tabela
        return db()->fetchAll("SELECT id, name, email, phone, cpf, is_active FROM customers ORDER BY created_at DESC");
    } catch (Exception $e) {
        error_log("Erro ao buscar clientes: " . $e->getMessage());
        return [];
    }
}


/**
 * Busca um cliente pelo ID.
 * @param int $customerId ID do cliente.
 * @return array|false Dados do cliente ou false se não encontrado.
 */
function getCustumerById($customerId) {
    // Removido global $pdo;
    try{
        $db = db(); // Obtém a instância do banco
        return $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$customerId]);
    }catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao buscar cliente ID $customerId: " . $e->getMessage());
        return false;
    }
}
/**
 * Salva (insere ou atualiza) um cliente no banco de dados.
 * @param array $data Dados do cliente.
 * @return int|false ID do cliente inserido/atualizado ou false em caso de erro.
 */
function saveCustomer($data) {
    // Removido global $pdo;

    error_log("========== DENTRO DA FUNÇÃO saveCustomer() ==========");

    // Extrai e sanitiza os dados
    $id = $data['id'] ?? null;
    $name = sanitizeInput($data['name'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $phone = sanitizeInput($data['phone'] ?? '');
    $cpf = sanitizeInput($data['cpf'] ?? '');


    // Converte string vazia em NULL e converte formato de data para MySQL
    $date_of_birth = convertDateToMySQL($data['date_of_birth'] ?? '');

    error_log("Date of birth após conversão: " . ($date_of_birth ?? 'NULL'));

    $gender = !empty($data['gender']) ? sanitizeInput($data['gender']) : null;
    $address = sanitizeInput($data['address'] ?? '');
    $neighborhood = sanitizeInput($data['neighborhood'] ?? '');
    $city = sanitizeInput($data['city'] ?? '');
    $state = !empty($data['state']) ? sanitizeInput($data['state']) : null;
    $zip_code = sanitizeInput($data['zip_code'] ?? '');
    $country = sanitizeInput($data['country'] ?? 'Brasil');
    $is_active = isset($data['is_active']) ? 1 : 0;

    error_log("Dados processados:");
    error_log("ID: " . ($id ?? 'NULL'));
    error_log("Name: " . $name);
    error_log("Email: " . $email);

    // Validação básica
    if (empty($name) || empty($email)) {
        error_log("VALIDAÇÃO FALHOU: Nome ou e-mail vazio.");
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Nome e E-mail são campos obrigatórios.'];
        return false;
    }

    error_log("Validação OK. Verificando e-mail único...");

    // Validação de e-mail único (apenas para novos clientes ou se o e-mail for alterado)
    try {
        $db = db(); // Obtém a instância do banco
        $existingEmail = $db->fetchOne("SELECT id FROM customers WHERE email = ? AND id != ?", [$email, $id ?? 0]);
        if ($existingEmail) {
            error_log("E-MAIL JÁ EXISTE: " . $email);
            ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Este e-mail já está cadastrado para outro cliente.'];
            return false;
        }
        error_log("E-mail único OK.");
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao verificar e-mail único: " . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao verificar e-mail.'];
        return false;
    }

    error_log("Tentando salvar no banco de dados...");

    try {
        $db = db(); // Obtém a instância do banco
        if ($id) {
            // Atualizar cliente existente
            error_log("Atualizando cliente ID: " . $id);
            $result = $db->execute("UPDATE customers SET
                                    name = ?, email = ?, phone = ?, cpf = ?, date_of_birth = ?,
                                    gender = ?, address = ?, neighborhood = ?, city = ?, state = ?,
                                    zip_code = ?, country = ?, is_active = ?
                                    WHERE id = ?",
                [
                    $name, $email, $phone, $cpf, $date_of_birth, $gender, $address,
                    $neighborhood, $city, $state, $zip_code, $country, $is_active, $id
                ]
            );

            error_log("Resultado do UPDATE: " . ($result ? 'SUCESSO' : 'FALHOU'));
            // db()->execute não retorna rowCount diretamente, mas retorna true/false
            // error_log("Linhas afetadas: " . $stmt->rowCount()); // Removido

            if ($result) {
                error_log("Cliente ID $id atualizado com sucesso.");
                return $id;
            } else {
                error_log("UPDATE retornou false.");
                return false;
            }
        } else {
            // Inserir novo cliente
            error_log("Inserindo NOVO cliente...");
            $sql = "INSERT INTO customers (
                        name, email, phone, cpf, date_of_birth, gender,
                        address, neighborhood, city, state, zip_code, country, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            error_log("SQL: " . $sql);

            $newId = $db->insert(
                $sql,
                [
                    $name, $email, $phone, $cpf, $date_of_birth, $gender, $address,
                    $neighborhood, $city, $state, $zip_code, $country, $is_active
                ]
            );

            error_log("Resultado do INSERT: " . ($newId ? 'SUCESSO' : "FALHOU"));

            if ($newId) {
                error_log("Novo cliente inserido com ID: $newId");
                return $newId;
            } else {
                error_log("INSERT retornou false.");
                return false;
            }
        }
    } catch (Exception $e) { // Captura Exception genérica
        error_log("ERRO ao salvar cliente: " . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao salvar o cliente: ' . $e->getMessage()];
        return false;
    }
}

/**
 * Deleta um cliente do banco de dados.
 * @param int $customerId ID do cliente a ser deletado.
 * @return bool True se deletado com sucesso, false caso contrário.
 */
function deleteCustomer($customerId) {
    // Removido global $pdo;
    try{
        $db = db(); // Obtém a instância do banco
        $result = $db->execute("DELETE FROM customers WHERE id = ?", [$customerId]);

        if($result) { // db()->execute retorna true em sucesso
            error_log("Cliente ID $customerId deletado do banco de dados.");
            return true; // Corrigido para retornar true em sucesso
        } else {
            error_log("Nenhuma linha foi afetada ao tentar deletar cliente com ID $customerId");
            return false;
        }
    }catch  (Exception $e) { // Captura Exception genérica
        error_log("Erro ao Deletar cliente: " . $e->getMessage());
        return false;
    }
}
/**
 * Converte data do formato brasileiro (DD/MM/YYYY) para formato MySQL (YYYY-MM-DD)
 * @param string $date Data no formato DD/MM/YYYY ou YYYY-MM-DD
 * @return string|null Data no formato YYYY-MM-DD ou null se inválida
 */
function convertDateToMySQL($date) {
    if (empty($date)) {
        return null;
    }

    // Remove espaços em branco
    $date = trim($date);

    // Se já estiver no formato YYYY-MM-DD (do input type="date"), retorna como está
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }

    // Se estiver no formato DD/MM/YYYY, converte para YYYY-MM-DD
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        $day = $matches[1];
        $month = $matches[2];
        $year = $matches[3];

        // Valida a data
        if (checkdate($month, $day, $year)) {
            return "$year-$month-$day";
        }
    }

    error_log("Formato de data inválido: " . $date);
    return null;
}

/**
 * Busca todos os usuários do banco de dados.
 * @return array Lista de usuários.
 */

function getAllUsers() {
    // Removido global $pdo;
    try{
        $db = db(); // Obtém a instância do banco
        return $db->fetchAll("SELECT id, username, email, role, is_active, created_at FROM user ORDER BY username ASC");
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao Buscar usuarios: " . $e->getMessage());
        return[];
    }
}

/**
 * Busca um usuário pelo ID.
 * @param int $userId ID do usuário.
 * @return array|false Dados do usuário ou false se não encontrado.
 */

function getUserById($userId) {
    // Removido global $pdo;
    try{
        $db = db(); // Obtém a instância do banco
        return $db->fetchOne("SELECT id, username, email, is_active, created_at FROM user WHERE id = ?", [$userId]);
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao Buscar Usuario ID $userId: " . $e->getMessage());
        return false;
    }
}

/**
 * Salva (insere ou atualiza) um usuário no banco de dados.
 * @param array $data Dados do usuário.
 * @return int|false ID do usuário inserido/atualizado ou false em caso de erro.
 */

function saveUser($data) {
    // Removido global $pdo;

    error_log("======================== Inicio o DEBUG da Função saveUser() =========================");

    // Extrai e sanitiza os dados
    $id = $data['id'] ?? null;
    $username = sanitizeInput($data['username'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $password = trim($data['password'] ?? ''); // ← TRIM AQUI
    $role = sanitizeInput($data['role'] ?? 'editor');
    $is_active = isset($data['is_active']) ? 1 : 0;

    error_log("Dados processados para saveUser");
    error_log("Codigo: " . ($id ?? 'NOVO'));
    error_log("Username: " . $username);
    error_log("Email: " . $email);
    error_log("Role: " . $role);
    error_log("Is Active: " . $is_active);
    error_log("Password (após trim): '" . $password . "'"); // ← LOG DA SENHA (com aspas para ver espaços)
    error_log("Password length: " . strlen($password)); // ← LOG DO TAMANHO

    // Validação básica
    if (empty($username) || empty($email)) {
        error_log("VALIDAÇÃO FALHOU: Username ou e-mail vazio.");
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Nome de usuário e E-mail são campos obrigatórios.'];
        return false;
    }

    // Validação de e-mail único
    try {
        $db = db(); // Obtém a instância do banco
        $existingEmail = $db->fetchOne("SELECT id FROM user WHERE email = ? AND id != ?", [$email, $id ?? 0]);
        if ($existingEmail) {
            error_log("E-MAIL JÁ EXISTE: " . $email);
            ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Este e-mail já está cadastrado para outro usuário.'];
            return false;
        }
        error_log("E-mail Unico OK.");
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao verificar e-mail único: " . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao verificar e-mail.'];
        return false;
    }

    // Validação de username único
    try {
        $db = db(); // Obtém a instância do banco
        $existingUsername = $db->fetchOne("SELECT id FROM user WHERE username = ? AND id != ?", [$username, $id ?? 0]);
        if ($existingUsername) {
            error_log("USERNAME JÁ EXISTE: " . $username);
            ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Este nome de usuário já está em uso.'];
            return false;
        }
        error_log("Username Unico OK.");
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao verificar username único: " . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao verificar nome de usuário.'];
        return false;
    }

    // ========== VALIDAÇÃO E PROCESSAMENTO DA SENHA ==========
    error_log("========== INÍCIO DO PROCESSAMENTO DA SENHA ==========");

    $password_hash = null;

    try {
        $db = db(); // Obtém a instância do banco

        if ($id) {
            // ===== EDIÇÃO DE USUÁRIO =====
            error_log("Modo: EDIÇÃO de usuário ID " . $id);

            if (strlen($password) > 0) {
                error_log("Nova senha fornecida. Tamanho: " . strlen($password));

                if (strlen($password) < 6) {
                    error_log("ERRO: Senha muito curta (menos de 6 caracteres)");
                    ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'A senha deve ter no mínimo 6 caracteres.'];
                    return false;
                }

                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                error_log("Nova senha hashed para usuário ID: " . $id);
            } else {
                error_log("Nenhuma nova senha fornecida. Mantendo senha existente.");

                // Buscar hash da senha atual
                $password_hash = $db->fetchOne("SELECT password_hash FROM user WHERE id = ?", [$id]);

                if (!$password_hash) {
                    error_log("ERRO: Não foi possível recuperar a senha atual do usuário ID " . $id);
                    ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao buscar senha atual do usuário.'];
                    return false;
                }

                error_log("Senha existente recuperada com sucesso.");
            }
        } else {
            // ===== NOVO USUÁRIO =====
            error_log("Modo: NOVO usuário");
            error_log("Validando senha obrigatória...");
            error_log("strlen(password) = " . strlen($password));

            if (strlen($password) < 6) {
                error_log("ERRO: Senha vazia ou muito curta para novo usuário");
                ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
                $_SESSION['message'] = ['type' => 'error', 'text' => 'A senha é obrigatória e deve ter pelo menos 6 caracteres.'];
                return false;
            }

            error_log("Senha válida. Gerando hash...");
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            error_log("Hash gerado com sucesso.");
        }
    } catch (Exception $e) { // Captura Exception genérica
        error_log("ERRO ao processar senha: " . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao processar a senha: ' . $e->getMessage()];
        return false;
    }

    error_log("========== FIM DO PROCESSAMENTO DA SENHA ==========");

    if (!$password_hash) {
        error_log("ERRO CRÍTICO: password_hash está vazio após processamento!");
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao processar a senha.'];
        return false;
    }

    // ========== SALVAR NO BANCO ==========
    try {
        $db = db(); // Obtém a instância do banco
        if ($id) {
            // Atualizar usuário existente
            error_log("Executando UPDATE para usuário ID: " . $id);
            $result = $db->execute("UPDATE user SET
                                    username = ?, email = ?, password_hash = ?, role = ?, is_active = ?
                                    WHERE id = ?",
                [
                    $username, $email, $password_hash, $role, $is_active, $id
                ]
            );

            error_log("Resultado do UPDATE: " . ($result ? 'SUCESSO' : 'FALHOU'));
            // db()->execute não retorna rowCount diretamente, mas retorna true/false
            // error_log("Linhas afetadas: " . $stmt->rowCount()); // Removido

            if ($result) {
                error_log("Usuário ID $id atualizado com sucesso.");
                return $id;
            } else {
                error_log("UPDATE retornou false.");
                return false;
            }
        } else {
            // Inserir novo usuário
            error_log("Executando INSERT para novo usuário...");
            $sql = "INSERT INTO user (username, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, ?)";
            error_log("SQL: " . $sql);

            $newId = $db->insert(
                $sql,
                [
                    $username, $email, $password_hash, $role, $is_active
                ]
            );

            error_log("Resultado do INSERT: " . ($newId ? 'SUCESSO' : 'FALHOU'));

            if ($newId) {
                error_log("Novo usuário inserido com ID: $newId");
                return $newId;
            } else {
                error_log("INSERT retornou false.");
                return false;
            }
        }
    } catch (Exception $e) { // Captura Exception genérica
        error_log("ERRO ao salvar usuário: " . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao salvar o usuário: ' . $e->getMessage()];
        return false;
    }
}

/**
 * Deleta um usuário do banco de dados.
 * @param int $userId ID do usuário a ser deletado.
 * @return bool True se deletado com sucesso, false caso contrário.
 */

function deleteUser($userId) {
    // Removido global $pdo;
    try {
        $db = db(); // Obtém a instância do banco
        $result = $db->execute("DELETE FROM user WHERE id = ?", [$userId]);

        // Se deletou, retornamos TRUE
        if ($result) { // db()->execute retorna true em sucesso
            error_log("Usuario ID $userId deletado com Sucesso.");
            return true;
        } else {
            // Se não encontrou o ID, retornamos FALSE
            error_log("Nenhuma linha afetada para o ID $userId");
            return false;
        }
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao Deletar: " . $e->getMessage());
        return false;
    }
}

//============================================= Funções para Categorias ===================================//
/**
 * Gera um slug amigável a partir de uma string.
 * @param string $text A string de entrada (ex: "Placas de Vídeo").
 * @return string O slug gerado (ex: "placas-de-video").
 */
function generateSlug($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return $text;
}

/**
 * Busca todas as categorias do banco de dados.
 * @return array Lista de categorias.
 */

function getAllCategories() {
    try {
        $categories = db()->fetchAll("SELECT id, name, slug, icon, description, is_active, created_at FROM categories ORDER BY name ASC");
        return $categories;
    } catch (Exception $e) { // Captura Exception, pois db()->fetchAll() pode lançar Exception
        error_log("Erro ao buscar categorias: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca uma categoria pelo ID.
 * @param int $categoryId ID da categoria.
 * @return array|false Dados da categoria ou false se não encontrada.
 */

function getCategoryById($categoryId) {
    // Removido global $pdo;
    error_log("DEBUG: getCategoryById() chamado para ID: " . $categoryId); // Novo log
    try {
        $db = db(); // Obtém a instância do banco
        $categoryData = $db->fetchOne("SELECT id, name, slug, icon, description, is_active, created_at FROM categories WHERE id = ?", [$categoryId]);

        if ($categoryData) {
            error_log("DEBUG: Categoria ID " . $categoryId . " encontrada. Dados: " . print_r($categoryData, true)); // Novo log
        } else {
            error_log("DEBUG: Categoria ID " . $categoryId . " NÃO encontrada."); // Novo log
        }

        return $categoryData; // Retorna o resultado
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao Buscar categoria ID $categoryId: " . $e->getMessage());
        return false;
    }
}


/**
 * Salva (insere ou atualiza) uma categoria no banco de dados.
 * @param array $data Dados da categoria.
 * @return int|false ID da categoria inserida/atualizada ou false em caso de erro.
 */
function saveCategory($data) {
    // Removido global $pdo;

    error_log("==================== DEBUG - Dentro da Funçao saveCategory() ======================");

    //Extrai e sanitiza os dados
    $id = $data['id'] ?? null;
    $name = sanitizeInput($data['name'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    $icon = sanitizeInput($data['icon'] ?? '');
    $is_active = isset($data['is_active']) ? 1 : 0;

    //Gera o slug a partir do nome

    $slug = generateSlug($name);

    error_log("Dados processados para saveCaregory");
    error_log("Codigo: " . ($id ?? 'NOVO'));
    error_log("Nome: " . $name);
    error_log("Slug: " . $slug);
    error_log("icon: " . $icon);
    error_log("Descriçao: " . $description);
    error_log("Ativo: " . $is_active);

    //Validação Basico
    if (empty($name)) {
        error_log("Validação Falhou: Nome da Categoria Vaziou.");
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'O nome da categoria eh obrigatorio.'];
        return false;
    }

    //Validacao de nome unico
    try{
        $db = db(); // Obtém a instância do banco
        $existingName = $db->fetchOne("SELECT id FROM categories WHERE name = ? AND id != ?", [$name, $id ?? 0]);
        if($existingName){
            error_log("Nome da Categoria ja Existe." . $name);
            ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Ja existe uma categoria com esse nome.'];
            return false;
        }
        error_log("Nome da Categoria Unico OK.");
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao Verificar o nome da Categoria Unico." . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao Verificar o nome da Categoria.'];
        return false;
    }

    //Validacao do Slug Unico
    try {
        $db = db(); // Obtém a instância do banco
        $existingSlug = $db->fetchOne("SELECT id FROM categories WHERE slug = ? AND id != ?", [$slug, $id ?? 0]);
        if ($existingSlug) {
            error_log("SLUG JA EXISTE: " . $slug);
            ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
            $_SESSION['message'] = ['type' => 'error', 'text' => 'O Slug gerado para esta categoria ja esta em uso. Por favor, altere o nome.'];
            return false;
        }
        error_log("Slug unico OK");
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao verificar slug unico. " . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao verificar slug.'];
        return false;
    }

    try {
        $db = db(); // Obtém a instância do banco
        if ($id) {
            //Atualizar categoria existente
            error_log("Atualizando Categoria Codigo: " . $id);
            $result = $db->execute("UPDATE categories SET
                                    name = ?, slug = ?, icon = ?, description = ?, is_active = ?
                                    WHERE id = ?",
                [$name, $slug, $icon, $description, $is_active, $id]
            );

            error_log("Resultado do UPDATE: " . ($result ? 'SUCESSO' : 'FALHOU'));
            // db()->execute não retorna rowCount diretamente
            // error_log("Linhas Afetadas: " . $stmt->rowCount()); // Removido

            if ($result) {
                error_log("Categoria Codigo $id atualizado com Sucesso.");
                return $id;
            } else {
                error_log("UPDATE retornou false");
                return false;
            }
        } else {
            //Insere nova Categoria
            error_log("Inserindo Nova Categoria...");
            $sql = "INSERT INTO categories (name, slug, icon, description, is_active) VALUES(?,?,?,?,?)";
            error_log("SQL: " .$sql);

            $newId = $db->insert(
                $sql,
                [$name, $slug, $icon, $description, $is_active]
            );

            error_log("Resultado do INSERT: " . ($newId ? 'SUCESSO' : "FALHOU"));

            if ($newId) {
                error_log("Nova categoria inserida com ID:  $newId");
                return $newId;
            } else {
                error_log("INSERT retornou false.");
                return false;
            }
        }
    } catch (Exception $e) { // Captura Exception genérica
        error_log("ERRO ao salvar categoria:  " . $e->getMessage());
        ensureSessionStarted(); // Garante que a sessão está iniciada para a mensagem
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erro ao salvar a categoria: ' .$e->getMessage()];
        return false;
    }
}

/**
 * Deleta uma categoria do banco de dados.
 * @param int $categoryId ID da categoria a ser deletada.
 * @return bool True se deletada com sucesso, false caso contrário.
 */

function deleteCategory($categoryId): bool {
    // Removido global $pdo;
    try{
        $db = db(); // Obtém a instância do banco
        //Adicionar verificação se existem produtos associados a esta categoria
        // Se houver produtos, impedir a exclusão ou reassociá-los a uma categoria padrão.

        $result = $db->execute("DELETE FROM categories WHERE id = ?", [$categoryId]);

        if ($result){ // db()->execute retorna true em sucesso
            error_log("Categoria Codigo $categoryId deletada do banco de dados.");
            return true;
        } else {
            error_log("Nenhuma linha foi afetada ao tentar deletar categoria ID $categoryId");
            return false;
        }
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao deletar categoria: " . $e->getMessage());
        return false;
    }
}

// Função para buscar todas as categorias ativas
function getAllActiveCategories() {
    try {
        // Usa db()->fetchAll() para buscar todas as categorias ativas
        return db()->fetchAll("SELECT id, name, icon FROM categories WHERE is_active = 1 ORDER BY name ASC");
    } catch (Exception $e) {
        error_log("Erro ao buscar categorias ativas: " . $e->getMessage());
        return [];
    }
}

// Função para buscar categoria por ID
function getCategoryNameById($categoryId) {
    // Removido global $pdo;
    if (!$categoryId) return 'Sem categoria';
    try {
        $db = db(); // Obtém a instância do banco
        $result = $db->fetchOne("SELECT name FROM categories WHERE id = ?", [$categoryId]);
        return $result ? $result['name'] : 'Categoria não encontrada';
    } catch (Exception $e) { // Captura Exception genérica
        error_log("Erro ao buscar categoria: " . $e->getMessage());
        return 'Erro ao carregar categoria';
    }
}

// ===========================================
// Funções Auxiliares Globais
// ===========================================

// Pegar a URL Completa
function getSiteUrl($path = ''){
    // Garante que SITE_URL esteja definida (deve vir de config.php)
    if (!defined('SITE_URL')) {
        error_log("SITE_URL não definida em getSiteUrl(). Verifique includes/config.php.");
        return '/' . ltrim($path, '/'); // Retorna caminho relativo como fallback
    }
    $url = rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    // Remove quebras de linha para segurança
    return str_replace(["\r", "\n", "\r\n"], '', $url);
}

// Para Redirecionamento
function redirect($url) {
    $url = str_replace(["\r", "\n", "\r\n"], '', trim($url));
    if (!headers_sent()) {
        header("Location: " . $url);
        exit;
    } else {
        echo "<script type='text/javascript'>";
        echo "window.location.href = '" . addslashes($url) . "';";
        echo "</script>";
        echo "<noscript>";
        echo "<meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' />";
        echo "</noscript>";
        exit;
    }
}

// Faz a verificação se tem Admin logado
function isAdmin(){
    ensureSessionStarted(); // Garante que a sessão está iniciada
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true; // Usando a flag de admin
}

// Verifica se tem usuário logado (cliente)
function isLoggedIn(){
    ensureSessionStarted(); // Garante que a sessão está iniciada
   return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Obter a ID do Usuário Logado
function getUserId(){
    ensureSessionStarted(); // Garante que a sessão está iniciada
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

// Verifica, checa, valida dados do input
function sanitizeInput($data) {
    if ($data === null || $data === '' || $data === false) {
        return '';
    }
    $data = (string) $data;
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Gera o Token CSRF
function generateCsrToken() {
    ensureSessionStarted(); // Garante que a sessão está iniciada
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

// Verifica o token CSRF
function verifyCsrfToken($token) {
    ensureSessionStarted(); // Garante que a sessão está iniciada
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Limpa o token após o uso
function clearCsrfToken() {
    ensureSessionStarted(); // Garante que a sessão está iniciada
    unset($_SESSION['csrf_token']);
}

// Função para obter a contagem de itens no carrinho (exemplo, pode precisar de ajustes)
function getCartItemCount() {
    ensureSessionStarted(); // Garante que a sessão está iniciada
    return count($_SESSION['cart'] ?? []);
}

/**
 * Verifica se o usuário está logado.
 * @return bool True se logado, false caso contrário.
 */
function isUserLoggedIn() {
    ensureSessionStarted();
     return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) &&
           ($_SESSION['role'] ?? '') === 'customer' &&
           !isset($_SESSION['admin_logged_in']);
}

/**
 * Garante que apenas usuários logados acessem a página.
 * Redireciona para o login se não estiver logado.
 */
function requireUserLogin() {
    ensureSessionStarted();
    if (!isUserLoggedIn()) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Você precisa estar logado para acessar essa página.'];
        redirect(getSiteUrl('pages/login.php'));
        exit;
    }
}

/**
 * Valida CPF (formato brasileiro)
 * Aceita com ou sem máscara (000.000.000-00)
 */
function isValidCPF($cpf)
{
    // Remove tudo que não for número
    $cpf = preg_replace('/\D/', '', $cpf);

    // Tem que ter 11 dígitos
    if (strlen($cpf) !== 11) {
        return false;
    }

    // Rejeita CPFs com todos os dígitos iguais (ex: 11111111111)
    if (preg_match('/^(.)\1*$/', $cpf)) {
        return false;
    }

    // Cálculo do primeiro dígito verificador
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int)$cpf[$i] * (10 - $i);
    }
    $rest = $sum % 11;
    $digit1 = ($rest < 2) ? 0 : 11 - $rest;

    // Cálculo do segundo dígito verificador
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += (int)$cpf[$i] * (11 - $i);
    }
    $rest = $sum % 11;
    $digit2 = ($rest < 2) ? 0 : 11 - $rest;

    // Compara dígitos calculados com os informados
    return ($cpf[9] == $digit1 && $cpf[10] == $digit2);
}


/**
 * Envia um e-mail de confirmação de pedido para o cliente usando PHPMailer.
 *
 * @param array $orderData Dados do pedido (id, customer_name, order_date, status, shipping_address, payment_method, total, subtotal_products, shipping_cost, discount_amount)
 * @param array $orderItems Array de itens do pedido (cada item com name, quantity, subtotal)
 * @param string $customerEmail E-mail do cliente
 * @return bool True se o e-mail foi enviado com sucesso, false caso contrário.
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once ABSPATH . 'includes/phpmailer/Exception.php';
require_once ABSPATH . 'includes/phpmailer/PHPMailer.php';
require_once ABSPATH . 'includes/phpmailer/SMTP.php';

/**
 * Envia um e-mail de confirmação de pedido para o cliente usando PHPMailer.
 *
 * @param array  $orderData  Dados do pedido (id, customer_name, order_date, status, shipping_address, payment_method, total, subtotal_products, shipping_cost, discount_amount)
 * @param array  $orderItems Array de itens do pedido (cada item com name, quantity, subtotal)
 * @param string $customerEmail E-mail do cliente
 * @return bool
 */
function sendOrderConfirmationEmail($orderData, $orderItems, $customerEmail) {
    $templatePath = ABSPATH . 'includes/email_templates/order_confirmation.html';

    if (!file_exists($templatePath)) {
        error_log("Erro: Template de e-mail não encontrado em " . $templatePath);
        return false;
    }

    $template = file_get_contents($templatePath);

    // Mapeia status e forma de pagamento igual à página
    $statusLabels = [
        'pending'    => 'Aguardando Pagamento',
        'paid'       => 'Pago',
        'processing' => 'Em Processamento',
        'shipped'    => 'Enviado',
        'delivered'  => 'Entregue',
        'canceled'   => 'Cancelado',
        'refunded'   => 'Reembolsado',
    ];
    $paymentLabels = [
        'pix'         => 'PIX',
        'boleto'      => 'Boleto Bancário',
        'credit_card' => 'Cartão de Crédito',
        'debit_card'  => 'Cartão de Débito',
    ];

    $statusLabel  = $statusLabels[$orderData['status']] ?? ucfirst($orderData['status']);
    $paymentLabel = $paymentLabels[$orderData['payment_method']] ?? ucfirst($orderData['payment_method']);

    // Preencher placeholders gerais
    $replacements = [
        '[SITE_URL]'          => getSiteUrl(),
        '[ORDER_ID]'          => htmlspecialchars($orderData['id']),
        '[ORDER_NUMBER]'      => htmlspecialchars($orderData['order_number'] ?? ('PED-' . $orderData['id'])),
        '[CUSTOMER_NAME]'     => htmlspecialchars($orderData['customer_name']),
        '[ORDER_DATE]'        => htmlspecialchars(date('d/m/Y H:i', strtotime($orderData['order_date']))),
        '[ORDER_STATUS]'      => htmlspecialchars($statusLabel),
        '[SHIPPING_ADDRESS]'  => nl2br(htmlspecialchars($orderData['shipping_address'])),
        '[PAYMENT_METHOD]'    => htmlspecialchars($paymentLabel),
        '[PRODUCTS_SUBTOTAL]' => formatPrice($orderData['subtotal_products']),
        '[SHIPPING_COST]'     => formatPrice($orderData['shipping_cost']),
        '[DISCOUNT_AMOUNT]'   => formatPrice($orderData['discount_amount']),
        '[ORDER_TOTAL]'       => formatPrice($orderData['total']),
        '[CURRENT_YEAR]'      => date('Y'),
    ];

    foreach ($replacements as $placeholder => $value) {
        $template = str_replace($placeholder, $value, $template);
    }

    // Preencher itens do pedido
    if (preg_match('/<!-- ITENS_DO_PEDIDO -->(.*?)<!-- \/ITENS_DO_PEDIDO -->/s', $template, $matches)) {
        $itemTemplate = $matches[1];
        $allItemsHtml = '';

        foreach ($orderItems as $item) {
            $currentItemHtml = $itemTemplate;
            $currentItemHtml = str_replace('[ITEM_NAME]',     htmlspecialchars($item['name']),     $currentItemHtml);
            $currentItemHtml = str_replace('[ITEM_QUANTITY]', htmlspecialchars($item['quantity']), $currentItemHtml);
            $currentItemHtml = str_replace('[ITEM_SUBTOTAL]', formatPrice($item['subtotal']),      $currentItemHtml);
            $allItemsHtml   .= $currentItemHtml;
        }

        // Substitui o bloco inteiro pelos itens renderizados
        $template = str_replace($matches[0], $allItemsHtml, $template);
    }

    // Configuração do PHPMailer
    $mail = new PHPMailer(true);

    try {
        // AJUSTE AQUI COM OS DADOS DO SEU SMTP (Gmail, Mailtrap, etc.)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aldosilveira@gmail.com';        // ajuste
        $mail->Password   = 'fhrh hamr darw vxbp';           // ajuste (senha de app do Gmail)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // opcional p/ debug

        $mail->setFrom('noreply@' . parse_url(getSiteUrl(), PHP_URL_HOST), 'GamerStore');
        $mail->addAddress($customerEmail, $orderData['customer_name']);

        $mail->isHTML(true);
        $mail->Subject = "Confirmação de Pedido #" . ($orderData['order_number'] ?? $orderData['id']) . " - GamerStore";
        $mail->Body    = $template;
        $mail->AltBody = strip_tags($template);

        $mail->send();
        return true;
    } catch (Exception $e) {
    error_log("Erro ao enviar e-mail de confirmação (PHPMailer): " . $mail->ErrorInfo . " (Pedido #" . $orderData['id'] . ")");
    return false;
}
}

/**
 * Envia um e-mail de atualização de status de pedido.
 *
 * @param array  $order        Dados completos do pedido (linha da tabela orders)
 * @param string $customerName Nome do cliente
 * @param string $customerEmail Email do cliente
 * @return bool
 */
/**
 * Envia um e-mail de atualização de status de pedido.
 *
 * @param PDO    $pdo          Instância PDO para acesso ao banco de dados.
 * @param array  $order        Dados completos do pedido (linha da tabela orders).
 * @param string $customerName Nome do cliente.
 * @param string $customerEmail Email do cliente.
 * @return bool
 */
function sendOrderStatusUpdateEmail(PDO $pdo, array $order, string $customerName, string $customerEmail): bool
{
    // Verifica se o e-mail já foi enviado para este status específico
    if ($order['status_email_sent_for'] === $order['status']) {
        error_log("E-mail de atualização de status para '{$order['status']}' já enviado para o Pedido #{$order['id']}. Não enviando novamente.");
        return true; // Considera como sucesso, pois já foi enviado
    }

    $templatePath = ABSPATH . 'includes/email_templates/order_status_update.html';

    if (!file_exists($templatePath)) {
        error_log("Erro: Template de e-mail de status não encontrado em " . $templatePath);
        return false;
    }

    $template = file_get_contents($templatePath);

    // Labels iguais aos da tela
    $statusLabels = [
        'pending'    => 'Aguardando Pagamento',
        'paid'       => 'Pago',
        'processing' => 'Em Processamento',
        'shipped'    => 'Enviado',
        'delivered'  => 'Entregue',
        'canceled'   => 'Cancelado',
        'refunded'   => 'Reembolsado',
    ];

    $statusLabel = $statusLabels[$order['status']] ?? ucfirst($order['status']);

    // Mensagem específica por status (pode ajustar depois)
    $statusMessages = [
        'paid'       => 'Seu pagamento foi confirmado e seu pedido está sendo preparado.',
        'processing' => 'Seu pedido está em processamento em nosso centro de distribuição.',
        'shipped'    => 'Seu pedido foi enviado e está a caminho.',
        'delivered'  => 'Seu pedido foi entregue. Esperamos que você aproveite sua compra!',
        'canceled'   => 'Seu pedido foi cancelado. Se tiver dúvidas, entre em contato com nosso suporte.',
        'refunded'   => 'O reembolso do seu pedido foi processado. Em breve aparecerá em sua fatura.',
        'pending'    => 'Seu pedido foi registrado e está aguardando confirmação de pagamento.',
    ];

    $statusMessage = $statusMessages[$order['status']] ?? '';

    // Monta endereço a partir do snapshot
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

    // Subtotal, frete, etc.
    $productsSubtotal = (float)($order['subtotal'] ?? 0);
    $shippingCost     = (float)($order['shipping_cost'] ?? 0);
    $discountAmount   = (float)($order['discount'] ?? 0);
    $orderTotal       = (float)($order['total'] ?? 0);

    // Substituição dos placeholders
    $replacements = [
        '[SITE_URL]'          => getSiteUrl(),
        '[ORDER_ID]'          => htmlspecialchars($order['id']),
        '[ORDER_NUMBER]'      => htmlspecialchars($order['order_number']),
        '[CUSTOMER_NAME]'     => htmlspecialchars($customerName),
        '[ORDER_DATE]'        => htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))),
        '[ORDER_STATUS]'      => htmlspecialchars($statusLabel),
        '[STATUS_MESSAGE]'    => htmlspecialchars($statusMessage),
        '[SHIPPING_ADDRESS]'  => nl2br(htmlspecialchars($shippingAddress)),
        '[PRODUCTS_SUBTOTAL]' => formatPrice($productsSubtotal),
        '[SHIPPING_COST]'     => formatPrice($shippingCost),
        '[DISCOUNT_AMOUNT]'   => formatPrice($discountAmount),
        '[ORDER_TOTAL]'       => formatPrice($orderTotal),
        '[CURRENT_YEAR]'      => date('Y'),
    ];

    foreach ($replacements as $placeholder => $value) {
        $template = str_replace($placeholder, $value, $template);
    }

    // Configuração do PHPMailer
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'aldosilveira@gmail.com';      // MESMO que já está funcionando
        $mail->Password   = 'fhrh hamr darw vxbp';         // idem
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('noreply@' . parse_url(getSiteUrl(), PHP_URL_HOST), 'GamerStore');
        $mail->addAddress($customerEmail, $customerName);

        $mail->isHTML(true);
        $mail->Subject = "Atualização do Pedido #" . $order['order_number'] . " - " . $statusLabel;
        $mail->Body    = $template;
        $mail->AltBody = strip_tags($template);

        $mail->send();

        // Se o e-mail foi enviado com sucesso, atualiza o campo no banco
        $stmtUpdate = $pdo->prepare("
            UPDATE orders
            SET status_email_sent_for = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([$order['status'], $order['id']]);

        error_log("E-mail de atualização de status para '{$order['status']}' enviado com sucesso para {$customerEmail} (Pedido #{$order['id']})");
        return true;
    } catch (Exception $e) {
        error_log("Erro ao enviar e-mail de atualização de status (PHPMailer): " . $mail->ErrorInfo . " (Pedido #{$order['id']})");
        return false;
    }
}

// Função auxiliar para formatar preço (se você já não tiver uma)
if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        // Garante que o preço é um número antes de formatar
        $price = is_numeric($price) ? (float)$price : 0.00;
        return 'R$ ' . number_format($price, 2, ',', '.');
    }
}


function displayMessage() {
    if (!empty($_SESSION['message'])) {
        $message = $_SESSION['message'];

        // Mapeia os tipos para classes do Bootstrap
        $typeMap = [
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
        ];

        $alertClass = $typeMap[$message['type']] ?? 'alert-info';

        echo '<div class="alert ' . $alertClass . '" role="alert">';
        echo htmlspecialchars($message['text']);
        echo '</div>';

        unset($_SESSION['message']);
    }
}

/**
 * Formata uma string de data/hora para um formato legível.
 *
 * @param string $dateTimeString A string de data/hora (ex: 'YYYY-MM-DD HH:MM:SS').
 * @return string A data/hora formatada.
 */
function formatDateTime(string $dateTimeString): string
{
    try {
        $dateTime = new DateTime($dateTimeString);
        // Formato brasileiro com hora
        return $dateTime->format('d/m/Y H:i:s');
    } catch (Exception $e) {
        error_log("Erro ao formatar data/hora: " . $e->getMessage());
        return $dateTimeString; // Retorna a string original em caso de erro
    }
}

/**
 * Busca produtos no banco de dados com base em um termo de busca.
 * @param string $query Termo de busca.
 * @return array Lista de produtos encontrados.
 */
/**
 * Busca produtos com filtros de termo, categoria, faixa de preço e ordenação.
 */
/**
 * Busca produtos com filtros de termo, categoria, faixa de preço, ordenação e paginação.
 *
 * @return array [array $products, int $totalResults]
 */
function searchProducts(
    string $query = '',
    string $categoryId = '',
    string $priceMin = '',
    string $priceMax = '',
    string $order = '',
    int $page = 1,
    int $perPage = 12
): array {
    $db = db();

    $baseSql = "
        FROM products p
        WHERE p.is_active = 1
    ";

    $params = [];

    // Termo de busca
    if ($query !== '') {
        $baseSql .= " AND (p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)";
        $searchTerm = '%' . $query . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Categoria
    if ($categoryId !== '') {
        $baseSql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }

    // Preço mínimo
    if ($priceMin !== '' && is_numeric($priceMin)) {
        $baseSql .= " AND (COALESCE(p.promotional_price, p.price) >= ?)";
        $params[] = (float)$priceMin;
    }

    // Preço máximo
    if ($priceMax !== '' && is_numeric($priceMax)) {
        $baseSql .= " AND (COALESCE(p.promotional_price, p.price) <= ?)";
        $params[] = (float)$priceMax;
    }

    // 1) Obter total de resultados (sem LIMIT)
    $countSql = "SELECT COUNT(*) AS total " . $baseSql;
    $countRow = $db->fetchOne($countSql, $params);
    $totalResults = (int)($countRow['total'] ?? 0);

    if ($totalResults === 0) {
        return [[], 0];
    }

    // 2) Montar ORDER BY
    switch ($order) {
        case 'price_asc':
            $orderSql = " ORDER BY COALESCE(p.promotional_price, p.price) ASC";
            break;
        case 'price_desc':
            $orderSql = " ORDER BY COALESCE(p.promotional_price, p.price) DESC";
            break;
        case 'name_asc':
            $orderSql = " ORDER BY p.name ASC";
            break;
        case 'name_desc':
            $orderSql = " ORDER BY p.name DESC";
            break;
        default:
            $orderSql = " ORDER BY p.name ASC";
            break;
    }

    // 3) Paginação (LIMIT / OFFSET)
    $offset = ($page - 1) * $perPage;
    $limitSql = " LIMIT ? OFFSET ?";

    $sql = "
        SELECT
            p.id,
            p.name,
            p.slug,
            p.short_description,
            p.unit_price,
            p.promotional_price,
            p.image_url,
            p.stock_qty,
            p.featured,
            p.is_active,
            p.category_id
        " . $baseSql . $orderSql . $limitSql;

    // IMPORTANTE: params do LIMIT / OFFSET entram depois dos filtros
    $paramsWithLimit = $params;
    $paramsWithLimit[] = $perPage;
    $paramsWithLimit[] = $offset;

    $products = $db->fetchAll($sql, $paramsWithLimit);

    return [$products, $totalResults];
}
