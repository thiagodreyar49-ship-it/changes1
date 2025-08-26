<?php
// api/connect.php
// Script de conexão com o banco de dados MySQL.

// *** CRÍTICO: NÃO DEVE HAVER NENHUM CARACTERE (ESPAÇOS, LINHAS VAZIAS, BOM) ANTES DESTA TAG <?php. ***

$host = 'localhost';
$db   = 'raptor';      // Nome do banco de dados
$user = 'root';      // Usuário padrão do XAMPP
$pass = '';          // Senha padrão do XAMPP (geralmente vazia no XAMPP)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage()); // Loga no log de erros do Apache
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Check server logs for details.']);
    exit();
}
// *** CRÍTICO: NÃO DEVE HAVER NENHUM CARACTERE (ESPAÇOS, LINHAS VAZIAS) APÓS ESTE BLOCO PHP. ***
