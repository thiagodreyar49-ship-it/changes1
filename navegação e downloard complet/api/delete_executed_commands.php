<?php
// api/delete_executed_commands.php
// Endpoint para limpar comandos executados ou falhos de um dispositivo.

require_once 'connect.php';

header('Content-Type: application/json');

$imei = isset($_POST['imei']) ? $_POST['imei'] : null;

if (!$imei) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'IMEI do dispositivo não fornecido.']);
    exit();
}

try {
    // Primeiro, encontra o ID do dispositivo
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Dispositivo não encontrado.']);
        exit();
    }
    $device_id = $device['id'];

    // Agora, exclui os comandos que foram executados ou falharam para este device_id
    // Mantemos apenas os comandos 'pending'
    $stmt = $pdo->prepare("DELETE FROM commands WHERE device_id = ? AND status IN ('executed', 'failed', 'completed')");
    $stmt->execute([$device_id]);

    $rows_affected = $stmt->rowCount(); // Conta quantas linhas foram afetadas

    echo json_encode(['status' => 'success', 'message' => "Foram apagados $rows_affected comandos executados/falhos para o dispositivo '$imei'."]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao limpar comandos executados: ' . $e->getMessage()]);
}

?>
