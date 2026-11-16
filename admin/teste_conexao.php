<?php
echo "<h2>Teste de Conexão com Banco de Dados</h2>";

// Caminho para o config.php
$config_path = dirname(__DIR__) . '/includes/config.php';
echo "Caminho do config.php: " . $config_path . "<br>";
echo "Arquivo existe? " . (file_exists($config_path) ? "SIM" : "NÃO") . "<br><br>";

// Tenta incluir o config.php
require_once $config_path;

// Verifica se $pdo foi criado
echo "Variável \$pdo existe? " . (isset($pdo) ? "SIM" : "NÃO") . "<br>";

if (isset($pdo)) {
    echo "Tipo da variável \$pdo: " . get_class($pdo) . "<br>";
    echo "Conexão com banco: <strong style='color:green;'>OK!</strong><br>";

    // Testa uma query simples
    try {
        $stmt = $pdo->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Banco de dados conectado: <strong>" . $result['db_name'] . "</strong><br>";
    } catch (PDOException $e) {
        echo "Erro ao testar query: " . $e->getMessage() . "<br>";
    }
} else {
    echo "<strong style='color:red;'>ERRO: \$pdo não foi criado!</strong><br>";
    echo "Verifique o arquivo config.php<br>";
}

// Lista todas as variáveis globais definidas
echo "<br><h3>Variáveis globais disponíveis:</h3>";
echo "<pre>";
print_r(array_keys($GLOBALS));
echo "</pre>";
?>
