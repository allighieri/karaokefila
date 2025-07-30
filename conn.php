<?php
$host = "localhost";
$db = "karaokefila";
$user = "root";     // Substitua pelo seu usuário do MySQL
$pass = "";         // Substitua pela sua senha do MySQL
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Em ambiente de produção, registre o erro em um log, não exiba diretamente
    error_log("Erro de Conexão PDO: " . $e->getMessage());
    die("Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.");
}
