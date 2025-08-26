<?php
// api/get_uploaded_files.php
// Endpoint para o painel HTML listar arquivos enviados por um dispositivo.

require_once 'connect.php';

header('Content-Type: application/json');

$imei = isset($_GET['imei']) ? $_GET['imei'] : null;

if (!$imei) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'IMEI do dispositivo não fornecido.']);
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
        exit();
    }
    $device_id = $device['id'];

    // Busca os arquivos enviados para este dispositivo
    $stmt = $pdo->prepare("SELECT original_name, download_url, uploaded_at FROM uploaded_files WHERE device_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$device_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'files' => $files]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar arquivos enviados: ' . $e->getMessage()]);
}

?>
