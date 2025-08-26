<?php
// api/update_command_status.php
// Endpoint para um dispositivo atualizar o status de um comando.

require_once 'connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['commandId'], $data['status'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Dados de atualização de status incompletos.']);
    exit();
}

$commandId = $data['commandId'];
$status = $data['status']; // Ex: 'executed', 'failed'

try {
    $stmt = $pdo->prepare("UPDATE commands SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$status, $commandId]);

    echo json_encode(['status' => 'success', 'message' => 'Status do comando atualizado.']);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar status do comando: ' . $e->getMessage()]);
}
