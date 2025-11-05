<?php
// process/generate_download_token.php
require_once __DIR__ . '/../config/functions.php';
requireLogin();
global $pdo;

$arquivo_id = intval($_GET['id'] ?? 0);
$expires_minutes = intval($_GET['ttl'] ?? 60); // default 60 minutes
if ($arquivo_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'arquivo_id inválido']);
    exit;
}

// Check permission: user must be admin or associated to folder or uploader
$stmt = $pdo->prepare('SELECT a.*, p.id as pasta_id FROM documentos_arquivos a LEFT JOIN documentos_pastas p ON a.pasta_id = p.id WHERE a.id = ?');
$stmt->execute([$arquivo_id]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$a) {
    http_response_code(404);
    echo json_encode(['error' => 'arquivo não encontrado']);
    exit;
}

$user_id = $_SESSION['user_id'];
// Attempt to infer the empresa related to this file (best-effort):
// 1) prefer the owner_user_id -> usuarios_empresas pivot
// 2) fallback to current selected company
try {
    $file_company = null;
    $owner_uid = intval($a['owner_user_id'] ?? 0);
    if ($owner_uid > 0) {
        $stmtC = $pdo->prepare('SELECT empresa_id FROM usuarios_empresas WHERE usuario_id = ? LIMIT 1');
        $stmtC->execute([$owner_uid]);
        $file_company = $stmtC->fetchColumn();
    }
    if (empty($file_company)) {
        $cid = current_company_id();
        if ($cid !== null) $file_company = $cid;
    }
    if (!empty($file_company)) {
        // Ensure the requesting user can access this company (admin bypasses inside helper)
        ensure_user_can_access_company(intval($file_company));
    }
} catch (Exception $e) {
    // best-effort only: if anything fails here, continue to the regular permission checks below
}

if (!isAdmin() && intval($a['enviado_por_user_id']) !== intval($user_id) && !isUserAssociatedToPasta($user_id, $a['pasta_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'sem permissão']);
    exit;
}

$token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', time() + max(60, $expires_minutes) * 60);

try {
    $stmt = $pdo->prepare('INSERT INTO documentos_download_tokens (token, arquivo_id, criado_por, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$token, $arquivo_id, $user_id, $expires_at]);
    $url = base_url('process/serve_documento.php?token=' . $token);
    header('Content-Type: application/json');
    echo json_encode(['token' => $token, 'expires_at' => $expires_at, 'url' => $url]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'falha ao criar token', 'detail' => $e->getMessage()]);
    exit;
}
