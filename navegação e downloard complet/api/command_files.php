<?php
// api/command_files.php
// Endpoint para um dispositivo consultar comandos pendentes para ele.

require_once 'connect.php';

header('Content-Type: application/json');

$imei = isset($_GET['imei']) ? $_GET['imei'] : null;

// Caminho para o arquivo de log no servidor
$log_file = __DIR__ . '/command_files_debug.log';

file_put_contents($log_file, date('[Y-m-d H:i:s]') . " Requisição para command_files.php. IMEI recebido: '$imei'\n", FILE_APPEND);

if (!$imei) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'IMEI do dispositivo não fornecido.']);
    file_put_contents($log_file, date('[Y-m-d H:i:s]') . " Erro: IMEI não fornecido.\n", FILE_APPEND);
    exit();
}

try {
    // Encontra o ID do dispositivo pelo IMEI
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Dispositivo não encontrado.']);
        file_put_contents($log_file, date('[Y-m-d H:i:s]') . " Erro: Dispositivo com IMEI '$imei' não encontrado na tabela 'devices'.\n", FILE_APPEND);
        exit();
    }
    $device_id = $device['id'];
    file_put_contents($log_file, date('[Y-m-d H:i:s]') . " Device ID encontrado para IMEI '$imei': '$device_id'.\n", FILE_APPEND);

    // Busca comandos pendentes para este dispositivo
    $sql_query_commands = "SELECT id, command_type as command, command_data, status FROM commands WHERE device_id = ? AND status = 'pending' ORDER BY created_at ASC LIMIT 1";
    $stmt = $pdo->prepare($sql_query_commands);
    $stmt->execute([$device_id]);
    $command_data_from_db = $stmt->fetch(PDO::FETCH_ASSOC);

    file_put_contents($log_file, date('[Y-m-d H:i:s]') . " SQL para comandos: '$sql_query_commands' com device_id '$device_id'. Resultado do fetch: " . json_encode($command_data_from_db) . "\n", FILE_APPEND);

    $response_to_client = [];
    if ($command_data_from_db) {
        $command_object = [
            'id' => (int)$command_data_from_db['id'],
            'deviceId' => (int)$device_id,
            'command' => (string)$command_data_from_db['command'],
            'status' => (string)$command_data_from_db['status'],
            'command_data' => isset($command_data_from_db['command_data']) ? (string)$command_data_from_db['command_data'] : null
        ];
        $response_to_client = ['status' => 'success', 'command' => $command_object];
    } else {
        $response_to_client = ['status' => 'no_command', 'message' => 'Nenhum comando pendente.'];
    }

    file_put_contents($log_file, date('[Y-m-d H:i:s]') . " Resposta FINAL enviada para IMEI '$imei': " . json_encode($response_to_client) . "\n", FILE_APPEND);

    echo json_encode($response_to_client);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar comandos: ' . $e->getMessage()]);
    file_put_contents($log_file, date('[Y-m-d H:i:s]') . " Exceção PDO: " . $e->getMessage() . "\n", FILE_APPEND);
}

?>
