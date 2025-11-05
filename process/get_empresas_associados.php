<?php
require_once __DIR__ . '/../config/functions.php';
requireLogin();

$empresa_id = intval($_GET['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}
try {
    // Ensure requester can access this company (AJAX-aware)
    ensure_user_can_access_company($empresa_id);
    global $pdo;
    $stmt = $pdo->prepare('SELECT u.id, u.nome, u.email, ue.role FROM usuarios_empresas ue JOIN usuarios u ON ue.usuario_id = u.id WHERE ue.empresa_id = ?');
    $stmt->execute([$empresa_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}

?>