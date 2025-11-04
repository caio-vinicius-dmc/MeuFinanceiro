<?php
// process/search_users.php
require_once __DIR__ . '/../config/functions.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
global $pdo;

$q = trim($_GET['q'] ?? '');
$limit = intval($_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 200) $limit = 50;

try {
    if ($q === '') {
        // return first users (limited)
        $stmt = $pdo->prepare('SELECT u.id, u.nome, u.id_cliente_associado, c.nome_responsavel AS cliente_nome FROM usuarios u LEFT JOIN clientes c ON u.id_cliente_associado = c.id ORDER BY u.nome ASC LIMIT ?');
        $stmt->execute([$limit]);
    } else {
        $like = '%' . str_replace('%','\\%',$q) . '%';
        $stmt = $pdo->prepare('SELECT u.id, u.nome, u.id_cliente_associado, c.nome_responsavel AS cliente_nome FROM usuarios u LEFT JOIN clientes c ON u.id_cliente_associado = c.id WHERE u.nome LIKE ? OR u.email LIKE ? ORDER BY u.nome ASC LIMIT ?');
        $stmt->execute([$like, $like, $limit]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'results' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

?>
