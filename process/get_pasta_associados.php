<?php
// process/get_pasta_associados.php
// Load application functions and DB (config/functions.php is used across the project)
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdmin()) {
    echo json_encode(['ok' => false, 'error' => 'Acesso negado']);
    exit;
}

$pasta_id = isset($_GET['pasta_id']) ? intval($_GET['pasta_id']) : 0;
if ($pasta_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'pasta_id inválido']);
    exit;
}

try {
    // fetch all users with client name
    // use the global $pdo provided by config/db.php (included via config/functions.php)
    global $pdo;
    if (!isset($pdo) || !$pdo) {
        echo json_encode(['ok' => false, 'error' => 'Erro interno: conexão com o banco não disponível']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT u.id, u.nome, u.email, u.id_cliente_associado, c.nome_responsavel as cliente_nome FROM usuarios u LEFT JOIN clientes c ON u.id_cliente_associado = c.id ORDER BY u.nome ASC');
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // fetch associated user ids
    $assoc = [];
    try {
        $stmt2 = $pdo->prepare('SELECT user_id FROM documentos_pastas_usuarios WHERE pasta_id = ?');
        $stmt2->execute([$pasta_id]);
        $rows = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        if ($rows) $assoc = array_map('intval', $rows);
    } catch (Exception $e) {
        // fallback to owner_user_id
        $stmt3 = $pdo->prepare('SELECT owner_user_id FROM documentos_pastas WHERE id = ?');
        $stmt3->execute([$pasta_id]);
        $owner = $stmt3->fetchColumn();
        if ($owner) $assoc = [intval($owner)];
    }

    $out = [];
    foreach ($users as $u) {
        $out[] = [
            'id' => intval($u['id']),
            'nome' => $u['nome'],
            'cliente_nome' => $u['cliente_nome'] ?? null,
            'associated' => in_array(intval($u['id']), $assoc, true)
        ];
    }

    echo json_encode(['ok' => true, 'users' => $out]);
    exit;
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Erro servidor: ' . $e->getMessage()]);
    exit;
}

