<?php
// api/send_command.php
// Endpoint para o painel HTML enviar um novo comando para um dispositivo.

require_once 'connect.php';

header('Content-Type: application/json');

$imei = isset($_POST['imei']) ? $_POST['imei'] : null;
$command_type = isset($_POST['command']) ? $_POST['command'] : null;
$command_data = isset($_POST['path']) ? $_POST['path'] : null; // Ex: o path para explorar

if (!$imei || !$command_type) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'IMEI e tipo de comando são obrigatórios.']);
    exit();
}

try {
    // Obtém o ID do dispositivo COM BASE NO IMEI.
    // Isso garante que estamos usando o device_id correto e atual para este IMEI.
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Dispositivo não encontrado.']);
        exit();
    }
    $device_id = $device['id'];

    // Insere o comando na tabela
    $stmt = $pdo->prepare("INSERT INTO commands (device_id, command_type, command_data, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$device_id, $command_type, $command_data]);

    echo json_encode(['status' => 'success', 'message' => 'Comando enviado com sucesso.']);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao enviar comando: ' . $e->getMessage()]);
}

?>
