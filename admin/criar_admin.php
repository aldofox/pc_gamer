<?php
require_once '../includes/config.php';

$username = 'aldosilveira';
$email = 'adminsilveira@gmail.com';
$password = 'admin123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO user (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute([$username, $email, $password_hash]);
    echo "Usuário admin criado com sucesso!<br>";
    echo "Usuário: admin<br>";
    echo "Senha: admin123<br>";
    echo "<a href='login.php'>Ir para o login</a>";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>
