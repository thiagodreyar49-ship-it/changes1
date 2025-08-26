<?php
// api/clear_explorer_results.php
// Endpoint para limpar todos os resultados do explorador de arquivos para um dispositivo específico.

require_once 'connect.php';

header('Content-Type: application/json');

// Recebe o IMEI via POST
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
    $device = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch como array associativo

    if (!$device) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Dispositivo não encontrado.']);
        exit();
    }
    $device_id = $device['id'];

    // Agora, exclui todos os resultados do explorador para este device_id
    $stmt = $pdo->prepare("DELETE FROM file_explorer_results WHERE device_id = ?");
    $stmt->execute([$device_id]);

    echo json_encode(['status' => 'success', 'message' => "Todos os resultados do explorador para o dispositivo '$imei' foram limpos."]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao limpar resultados do explorador: ' . $e->getMessage()]);
}

?>
