<?php
$senha_admin = 'senhaForte'; // Escolha uma senha forte e fácil de lembrar
$hash_admin = password_hash($senha_admin, PASSWORD_DEFAULT);
echo "Senha original: " . $senha_admin . "<br>";
echo "Hash gerado: " . $hash_admin ."<br>";
?>