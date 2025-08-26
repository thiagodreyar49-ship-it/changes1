<?php
// api/upload_file_from_device.php
// Endpoint para o dispositivo enviar um arquivo para o servidor.

require_once 'connect.php'; // Inclui o script de conexão com o banco de dados

header('Content-Type: application/json');

// Garante que a requisição seja um POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    exit();
}

$imei = isset($_POST['imei']) ? $_POST['imei'] : null;
$command_id = isset($_POST['command_id']) ? (int)$_POST['command_id'] : null; // Para atualizar o status do comando
$file_path_on_device = isset($_POST['original_path']) ? $_POST['original_path'] : null; // Caminho original no dispositivo

// Verifica se os dados necessários estão presentes
if (!$imei || !$command_id || !$file_path_on_device || !isset($_FILES['file'])) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['status' => 'error', 'message' => 'Dados de upload incompletos.']);
    exit();
}

$file_name = basename($_FILES['file']['name']);
$temp_file_path = $_FILES['file']['tmp_name'];
$error_code = $_FILES['file']['error'];

if ($error_code !== UPLOAD_ERR_OK) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro no upload do arquivo: Código ' . $error_code]);
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

    // Define o diretório de destino no servidor
    // Ex: /raptor/uploads/TEST_IMEI_12345/
    $upload_dir = '../uploads/' . $imei . '/';

    // Cria os diretórios se não existirem
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true); // Permissões 0777 para teste, ajustar em produção
    }

    // Sanitize o nome do arquivo para evitar travessia de diretórios ou problemas de segurança
    $sanitized_file_name = preg_replace('/[^a-zA-Z0-9_\-.]/', '', $file_name);
    // Para evitar colisões de nome, pode-se adicionar um timestamp ou hash
    $final_file_name = time() . '_' . $sanitized_file_name;
    $destination_path = $upload_dir . $final_file_name;

    if (move_uploaded_file($temp_file_path, $destination_path)) {
        // Atualiza o status do comando para 'completed' no banco de dados
        $stmt_update_command = $pdo->prepare("UPDATE commands SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt_update_command->execute(['completed', $command_id]);

        // Opcional: Registra o arquivo enviado em uma nova tabela 'uploaded_files'
        // Crie esta tabela se desejar manter um registro detalhado dos arquivos enviados:
        // CREATE TABLE uploaded_files (
        //     id INT AUTO_INCREMENT PRIMARY KEY,
        //     device_id INT NOT NULL,
        //     command_id INT NOT NULL,
        //     original_path_device TEXT NOT NULL,
        //     server_path VARCHAR(255) NOT NULL,
        //     original_name VARCHAR(255) NOT NULL,
        //     uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        //     FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
        //     FOREIGN KEY (command_id) REFERENCES commands(id) ON DELETE CASCADE
        // );
        $server_file_url = str_replace('../', 'http://192.168.3.175/raptor/', $destination_path); // Gerar URL acessível

        $stmt_insert_uploaded_file = $pdo->prepare("INSERT INTO uploaded_files 
                                                    (device_id, command_id, original_path_device, server_path, original_name, download_url) 
                                                    VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert_uploaded_file->execute([
            $device_id,
            $command_id,
            $file_path_on_device,
            $destination_path,
            $file_name,
            $server_file_url
        ]);


        echo json_encode([
            'status' => 'success',
            'message' => 'Arquivo enviado com sucesso.',
            'server_path' => $destination_path,
            'download_url' => $server_file_url // Retorna a URL para o cliente
        ]);

    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Falha ao mover o arquivo enviado.']);
    }

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro no servidor: ' . $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro inesperado: ' . $e->getMessage()]);
}

?>
