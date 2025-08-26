<?php
// api/get_files.php
// Endpoint para o painel HTML obter os resultados do explorador de arquivos de um dispositivo.

require_once 'connect.php';

header('Content-Type: application/json');

$imei = isset($_GET['imei']) ? $_GET['imei'] : null;
$path = isset($_GET['path']) ? $_GET['path'] : '/';

if (!$imei) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'IMEI do dispositivo não fornecido.']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id FROM devices WHERE imei = ?");
    $stmt->execute([$imei]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Dispositivo não encontrado.']);
        exit();
    }
    $device_id = $device['id'];

    $normalized_path = rtrim($path, '/');
    if ($normalized_path === '') {
        $normalized_path = '/';
    }

    $files = [];
    
    if ($normalized_path === '/' || $normalized_path === '/storage/emulated/0') {
        $stmt_sql = "SELECT file_name as name, path, is_directory as isDirectory, size 
                     FROM file_explorer_results 
                     WHERE device_id = ? 
                     AND (
                         path LIKE '/storage/emulated/0/%' ESCAPE '\\\\' 
                         AND path NOT LIKE '/storage/emulated/0/%/%' ESCAPE '\\\\'
                     )
                     ORDER BY is_directory DESC, file_name ASC";
        $stmt = $pdo->prepare($stmt_sql);
        $stmt->execute([$device_id]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $stmt_sql = "SELECT file_name as name, path, is_directory as isDirectory, size 
                     FROM file_explorer_results 
                     WHERE device_id = ? 
                     AND path LIKE ? ESCAPE '\\\\' 
                     AND path NOT LIKE ? ESCAPE '\\\\' 
                     ORDER BY is_directory DESC, file_name ASC";
        
        $search_like = $normalized_path . '/%';
        $exclude_like = $normalized_path . '/%/%';

        $stmt = $pdo->prepare($stmt_sql);
        $stmt->execute([$device_id, $search_like, $exclude_like]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode($files);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar arquivos: ' . $e->getMessage()]);
}

?>
