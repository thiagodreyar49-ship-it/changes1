<?php
// api/get_devices.php
// Endpoint para o painel HTML listar todos os dispositivos registrados.

// Certifique-se de que não há NENHUM ESPAÇO, NOVA LINHA ou CARACTERE antes desta tag.
require_once 'connect.php';

// O header deve ser enviado ANTES de qualquer output!
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT imei, model, android_version, ip_address, last_seen FROM devices ORDER BY last_seen DESC");
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($devices);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao buscar dispositivos: ' . $e->getMessage()]);
}

