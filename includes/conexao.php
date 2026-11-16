<?php
// ============================================
// CONEXÃO COM O BANCO DE DADOS (PDO)
// ============================================

require_once __DIR__ . '/config.php';  // Carrega as configs primeiro

class Database {
    private static $instance = null;
    private $pdo;
    private $inTransaction = false;

    /**
     * Construtor privado (padrão Singleton)
     */
    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci"
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Log de conexão bem-sucedida (apenas em debug)
            if (DEBUG_MODE) {
                error_log("Conexão com banco estabelecida: " . DB_NAME);
            }
        } catch (PDOException $e) {
            // Em produção, logue o erro sem expor detalhes
            if (DEBUG_MODE) {
                die("Erro de conexão: " . $e->getMessage());
            } else {
                error_log("Erro de conexão com banco: " . $e->getMessage());
                die("Erro interno do servidor. Tente novamente mais tarde.");
            }
        }
    }

    /**
     * Padrão Singleton: Retorna a instância única
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Executa uma query preparada
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params); // <-- Aqui estava "executar"
            return $stmt;
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                error_log("Erro na query: " . $sql . " | Params: " . json_encode($params) . " | Erro: " . $e->getMessage());
                throw $e;
            } else {
                error_log("Erro na query: " . $e->getMessage());
                throw new Exception("Erro ao executar consulta no banco.");
            }
        }
    }

    /**
     * Busca um registro único
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Busca múltiplos registros
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Conta registros
     */
    public function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }

    /**
     * Insere um registro e retorna o ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Atualiza ou deleta (retorna linhas afetadas)
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Inicia transação
     */
    public function beginTransaction() { // <-- Aqui estava "startTransaction"
        if (!$this->inTransaction) {
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
        }
        return $this->inTransaction;
    }

    /**
     * Confirma transação
     */
    public function commit() {
        if ($this->inTransaction) {
            $this->pdo->commit();
            $this->inTransaction = false;
        }
    }

    /**
     * Desfaz transação
     */
    public function rollback() {
        if ($this->inTransaction) {
            $this->pdo->rollBack();
            $this->inTransaction = false;
        }
    }

    /**
     * Obtém o PDO para queries customizadas (use com cuidado)
     */
    public function getPdo() {
        return $this->pdo;
    }

    // Impede clonagem
    private function __clone() {}

    // Impede unserialize
    public function __wakeup() {
        throw new Exception("Não é possível desserializar a instância da conexão."); // <-- Aqui estava "instanica"
    }
}

// Função global para obter a conexão (facilita o uso)
function db() {
    return Database::getInstance();
}
?>
