<?php
// Define ABSPATH se ainda não estiver definido
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(__DIR__)) . '/');
}

require_once ABSPATH . 'includes/config.php';
require_once ABSPATH . 'includes/conexao.php'; // Inclui a classe Database e a função db()
require_once ABSPATH . 'includes/functions.php'; // Inclui ensureSessionStarted(), sanitizeInput(), redirect(), getSiteUrl()

/**
 * Função de login do administrador
 */
function adminLogin($username, $password) {
    ensureSessionStarted();

    try {
        // Log para depuração:
        error_log("Tentativa de login admin para: " . $username);

        $user = db()->fetchOne("
            SELECT id, username, email, password_hash, role, is_active 
            FROM user 
            WHERE (username = ? OR email = ?) AND is_active = 1 AND role = 'admin'
        ", [$username, $username]);

        // Log para depuração:
        if ($user) {
            error_log("Usuário encontrado: " . $user['username'] . " (ID: " . $user['id'] . ", Role: " . $user['role'] . ")");
            error_log("Senha digitada (não hash): " . $password); // CUIDADO: NÃO FAÇA ISSO EM PRODUÇÃO!
            error_log("Hash no banco: " . $user['password_hash']);
        } else {
            error_log("Usuário admin NÃO encontrado para: " . $username);
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            error_log("password_verify() retornou TRUE. Login bem-sucedido.");
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $user['id'];
            $_SESSION['admin_username']  = $user['username'];
            $_SESSION['admin_email']     = $user['email'];
            $_SESSION['admin_role']      = $user['role'];
            return true;
        } else {
            error_log("password_verify() retornou FALSE ou usuário não encontrado. Login falhou.");
            return false;
        }
    } catch (Exception $e) {
        error_log("Erro no login admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se o usuário logado é um administrador.
 * @return bool True se for admin, false caso contrário.
 */
function isUserAdminLoggedIn() {
    ensureSessionStarted(); // Garante que a sessão está iniciada
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Requer que o usuário esteja logado como admin. Se não estiver, redireciona para o login do admin.
 */
function requireAdminLogin() {
    if (!isUserAdminLoggedIn()) {
        redirect(getSiteUrl('admin/login.php'));
        exit;
    }
}

/**
 * Logout do administrador.
 */
function adminLogout() {
    ensureSessionStarted(); // Garante que a sessão está iniciada
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_email']);
    unset($_SESSION['admin_role']);
    // Opcional: se você quer que o logout do admin não afete a sessão de usuário normal
    // unset($_SESSION['user_id']); 
    session_destroy(); // Destrói toda a sessão
    redirect(getSiteUrl('admin/login.php'));
    exit;
}

/**
 * Buscar usuário admin por ID
 */
function getAdminById($id) {
    ensureSessionStarted(); // Garante que a sessão está iniciada
    try {
        // Usa db()->fetchOne() e corrige o nome da tabela
        return db()->fetchOne("SELECT * FROM user WHERE id = ? AND role = 'admin'", [$id]);
    } catch (Exception $e) {
        error_log("Erro ao buscar admin: " . $e->getMessage());
        return null;
    }
}

/**
 * Atualiza o status de um pedido e, opcionalmente, registra no histórico.
 *
 * @param PDO    $pdo       Instância PDO para acesso ao banco de dados.
 * @param int    $orderId   ID do pedido a ser atualizado.
 * @param string $newStatus Novo status do pedido.
 * @param string $comment   Comentário opcional para a atualização.
 * @return array|false Retorna o array completo do pedido atualizado ou false em caso de falha.
 */
function updateOrderStatus(PDO $pdo, int $orderId, string $newStatus, string $comment = '')
{
    try {
        $pdo->beginTransaction();

        // 1. Atualiza o status na tabela orders
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $comment, $orderId]);

        // 2. Registra no histórico de status
        $stmtHistory = $pdo->prepare("INSERT INTO order_status_history (order_id, status, comment, changed_at) VALUES (?, ?, ?, NOW())");
        $stmtHistory->execute([$orderId, $newStatus, $comment]);

        $pdo->commit();

        // 3. Busca o pedido atualizado para retornar
        // CORREÇÃO AQUI: Usar LEFT JOIN customer c ON o.user_id = c.id
        $stmt = $pdo->prepare("SELECT o.*, c.name AS user_name, c.email AS user_email FROM orders o LEFT JOIN customers c ON o.user_id = c.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $updatedOrder = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($updatedOrder) {
            error_log("DEBUG: updateOrderStatus - Pedido #{$orderId} atualizado e retornado com sucesso.");
            return $updatedOrder;
        } else {
            error_log("ERROR: updateOrderStatus - Pedido #{$orderId} atualizado no banco, mas NÃO ENCONTRADO ao buscar para retorno (após LEFT JOIN customer). Isso é inesperado.");
            return false;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("ERROR: Erro crítico em updateOrderStatus para pedido #{$orderId}: " . $e->getMessage());
        return false;
    }
}
