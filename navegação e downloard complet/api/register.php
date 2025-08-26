<?php
// api/register.php
// Endpoint para registrar um novo dispositivo.

require_once 'connect.php'; // Inclui o script de conexão com o banco de dados

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Verifica se os dados necessários estão presentes
if (!isset($data['imei'], $data['model'], $data['androidVersion'])) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['status' => 'error', 'message' => 'Dados de registro incompletos.']);
    exit();
}

$imei = $data['imei'];
$model = $data['model'];
$androidVersion = $data['androidVersion'];
$ip_address = $_SERVER['REMOTE_ADDR']; // Pega o IP do dispositivo que fez a requisição

try {
    // Tenta inserir um novo dispositivo ou atualizar se já existir
    $stmt = $pdo->prepare("INSERT INTO devices (imei, model, android_version, ip_address) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE model=?, android_version=?, ip_address=?, last_seen=CURRENT_TIMESTAMP");
    $stmt->execute([$imei, $model, $androidVersion, $ip_address, $model, $androidVersion, $ip_address]);

    echo json_encode(['status' => 'success', 'message' => 'Dispositivo registrado/atualizado com sucesso.']);
} catch (\PDOException $e) {
    http_response_code(500); // Erro interno do servidor
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registrar dispositivo: ' . $e->getMessage()]);
}
