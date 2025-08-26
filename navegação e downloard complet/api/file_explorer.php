<?php
// api/file_explorer.php
// Endpoint para receber a lista de arquivos de um dispositivo.

require_once 'connect.php';

header('Content-Type: application/json');

$request_data = json_decode(file_get_contents('php://input'), true);

if (!isset($request_data['imei']) || !isset($request_data['files']) || !is_array($request_data['files'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'IMEI ou dados de arquivos inválidos/ausentes.']);
    exit();
}

$imei = $request_data['imei'];
$files = $request_data['files'];

try {
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $device = $stmt->fetch();

    if (!$device) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Dispositivo não encontrado.']);
        exit();
    }
    $device_id = $device['id'];

    // Usamos INSERT ... ON DUPLICATE KEY UPDATE para evitar duplicatas.
    // Isso requer uma chave UNIQUE em (device_id, path, file_name) na tabela file_explorer_results.
    // Certifique-se de ter executado: ALTER TABLE file_explorer_results ADD UNIQUE (`device_id`, `path`, `file_name`);
    $stmt = $pdo->prepare("INSERT INTO file_explorer_results (device_id, path, file_name, is_directory, size, created_at, updated_at) 
                           VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                           ON DUPLICATE KEY UPDATE 
                               is_directory = VALUES(is_directory), 
                               size = VALUES(size),
                               updated_at = CURRENT_TIMESTAMP");

    foreach ($files as $file) {
        if (!isset($file['name'], $file['path'], $file['isDirectory'], $file['size'])) {
            error_log("Arquivo com dados incompletos recebido para IMEI $imei: " . json_encode($file));
            continue;
        }

        $stmt->execute([
            $device_id,
            $file['path'],
            $file['name'],
            (int)$file['isDirectory'], // Garante que seja um inteiro (0 ou 1)
            $file['size']
        ]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Resultados do explorador de arquivos recebidos/atualizados.']);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao processar resultados do explorador: ' . $e->getMessage()]);
}

?>
