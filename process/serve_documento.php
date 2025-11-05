<?php
// process/serve_documento.php
require_once __DIR__ . '/../config/functions.php';
requireLogin();
global $pdo;

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    echo 'Arquivo não encontrado.';
    exit;
}

// If token provided, resolve arquivo_id
$token = $_GET['token'] ?? null;
if ($token) {
    $stmt = $pdo->prepare('SELECT t.arquivo_id, t.expires_at, a.* , p.owner_user_id, p.id as pasta_id FROM documentos_download_tokens t JOIN documentos_arquivos a ON a.id = t.arquivo_id LEFT JOIN documentos_pastas p ON a.pasta_id = p.id WHERE t.token = ?');
    $stmt->execute([$token]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        http_response_code(404);
        echo 'Token inválido.';
        exit;
    }
    // check expiry
    if (strtotime($a['expires_at']) < time()) {
        http_response_code(410);
        echo 'Token expirado.';
        exit;
    }
    $usingToken = true;
} else {
    $stmt = $pdo->prepare('SELECT a.*, p.owner_user_id, p.id as pasta_id FROM documentos_arquivos a LEFT JOIN documentos_pastas p ON a.pasta_id = p.id WHERE a.id = ?');
    $stmt->execute([$id]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);
    $usingToken = false;
}
if (!$a) {
    http_response_code(404);
    echo 'Arquivo não encontrado.';
    exit;
}

// Check permission: file must be approved or requester is admin or uploader or associated to folder
$user_id = $_SESSION['user_id'];
// Best-effort: try to determine the file's company (via owner_user_id -> usuarios_empresas pivot) and enforce company access
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
        ensure_user_can_access_company(intval($file_company));
    }
} catch (Exception $e) {
    // ignore best-effort failures and continue with existing permission checks
}
if ($a['status'] !== 'approved') {
    $allowed = false;
    if (isAdmin()) $allowed = true;
    if (intval($a['enviado_por_user_id']) === intval($user_id)) $allowed = true;
    // check association via helper (fallbacks included)
    if (isUserAssociatedToPasta($user_id, $a['pasta_id'])) $allowed = true;
    if (!$allowed) {
        http_response_code(403);
        echo 'Você não tem permissão para acessar este arquivo.';
        exit;
    }
}

// If accessed via token, we can optionally delete the token after use (single-use).
if (!empty($usingToken) && $usingToken === true) {
    try {
        $stmt = $pdo->prepare('DELETE FROM documentos_download_tokens WHERE token = ?');
        $stmt->execute([$token]);
    } catch (Exception $e) {
        // ignore deletion errors
    }
}

$path = __DIR__ . '/../' . $a['caminho'];
if (!file_exists($path)) {
    http_response_code(404);
    echo 'Arquivo físico não encontrado.';
    exit;
}

// Serve file with proper headers
$mime = $a['tipo_mime'] ?: mime_content_type($path);
$basename = basename($a['nome_original']);
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $basename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
