<?php
// config/auth.php
// Helpers simples para multi-tenant e RBAC por empresa

if (session_status() === PHP_SESSION_NONE) session_start();

// Retorna lista de empresas associadas a um usuário
function get_user_companies($user_id) {
    global $pdo;
    $user_id = intval($user_id);
    try {
        $stmt = $pdo->prepare("SELECT e.id, e.nome FROM usuarios_empresas ue JOIN empresas e ON ue.empresa_id = e.id WHERE ue.usuario_id = ? ORDER BY e.nome");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Se a tabela pivot não existir, retorna vazio
        return [];
    }
}

// Define empresa atual na sessão (só se o usuário estiver associado)
function set_current_company($company_id) {
    if (!isLoggedIn()) return false;
    $user_id = $_SESSION['user_id'];
    try {
        global $pdo;
        $stmt = $pdo->prepare('SELECT 1 FROM usuarios_empresas WHERE usuario_id = ? AND empresa_id = ? LIMIT 1');
        $stmt->execute([$user_id, $company_id]);
        if ($stmt->fetchColumn()) {
            $_SESSION['current_company_id'] = intval($company_id);
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// Retorna a empresa atual (session). Se não houver, tenta escolher a primeira empresa do usuário.
function current_company_id() {
    if (isset($_SESSION['current_company_id'])) return intval($_SESSION['current_company_id']);
    if (!isLoggedIn()) return null;
    $user_id = $_SESSION['user_id'];
    $companies = get_user_companies($user_id);
    if (!empty($companies)) {
        $_SESSION['current_company_id'] = intval($companies[0]['id']);
        return $_SESSION['current_company_id'];
    }
    return null;
}

// Verifica se um usuário tem um papel específico em uma empresa
function user_has_role($user_id, $company_id, $role) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT role FROM usuarios_empresas WHERE usuario_id = ? AND empresa_id = ? LIMIT 1');
        $stmt->execute([$user_id, $company_id]);
        $r = $stmt->fetchColumn();
        return ($r !== false && $r === $role);
    } catch (Exception $e) {
        return false;
    }
}

// Verifica se usuário atual tem qualquer dos papéis fornecidos na empresa atual (ou passada)
function require_company_role($roles = ['admin'], $company_id = null) {
    if (!isLoggedIn()) {
        header('HTTP/1.1 401 Unauthorized');
        die('Acesso não autorizado. Faça login.');
    }
    $user_id = $_SESSION['user_id'];
    $company_id = $company_id ?: current_company_id();
    if ($company_id === null) {
        header('HTTP/1.1 403 Forbidden');
        die('Empresa não selecionada.');
    }
    // Global admin bypass
    if (function_exists('isAdmin') && isAdmin()) return true;

    foreach ((array)$roles as $role) {
        if (user_has_role($user_id, $company_id, $role)) return true;
    }
    header('HTTP/1.1 403 Forbidden');
    die('Permissão negada para esta ação.');
}

// Conveniências
function is_admin_for_current_company() {
    if (!isLoggedIn()) return false;
    $cid = current_company_id();
    if ($cid === null) return false;
    return user_has_role($_SESSION['user_id'], $cid, 'admin');
}

function is_contador_for_current_company() {
    if (!isLoggedIn()) return false;
    $cid = current_company_id();
    if ($cid === null) return false;
    return user_has_role($_SESSION['user_id'], $cid, 'contador');
}

function is_cliente_for_current_company() {
    if (!isLoggedIn()) return false;
    $cid = current_company_id();
    if ($cid === null) return false;
    return user_has_role($_SESSION['user_id'], $cid, 'cliente');
}

// Helper para anexar condição de empresa em queries simples (retorna SQL e parametros)
function company_where_clause($alias = '') {
    $cid = current_company_id();
    if ($cid === null) return ['sql' => '', 'params' => []];
    global $pdo;
    static $cachedCol = null;
    if ($cachedCol === null) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'empresa_id'");
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) $cachedCol = 'empresa_id';
            else {
                // fallback para colunas antigas como id_empresa
                $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'id_empresa'");
                $stmt2->execute();
                $cachedCol = ($stmt2->fetchColumn() > 0) ? 'id_empresa' : 'empresa_id';
            }
        } catch (Exception $e) {
            $cachedCol = 'empresa_id';
        }
    }
    $col = $cachedCol;
    if ($alias !== '') {
        $colSql = "`" . str_replace('`','', $alias) . "`." . $col;
    } else {
        $colSql = "`" . $col . "`";
    }
    return ['sql' => "{$colSql} = ?", 'params' => [$cid]];
}

// Retorna true se o usuário tem qualquer papel na empresa
function user_has_any_role($user_id, $company_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(1) FROM usuarios_empresas WHERE usuario_id = ? AND empresa_id = ?');
        $stmt->execute([$user_id, $company_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Retorna o nome da coluna usada para referenciar empresa nas tabelas ('empresa_id' ou 'id_empresa')
function get_company_column_name() {
    global $pdo;
    static $cachedColName = null;
    if ($cachedColName !== null) return $cachedColName;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'empresa_id'");
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) { $cachedColName = 'empresa_id'; return $cachedColName; }
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'id_empresa'");
        $stmt2->execute();
        if ($stmt2->fetchColumn() > 0) { $cachedColName = 'id_empresa'; return $cachedColName; }
    } catch (Exception $e) {
        // fallback
    }
    $cachedColName = 'id_empresa';
    return $cachedColName;
}

// Verifica e interrompe se o usuário não pertence à empresa (retorna JSON se for AJAX)
function ensure_user_can_access_company($company_id) {
    if (!isLoggedIn()) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Login requerido']);
            exit;
        }
        header('Location: ' . base_url('index.php?page=login'));
        exit;
    }
    $user_id = $_SESSION['user_id'];
    // admin global bypass
    if (function_exists('isAdmin') && isAdmin()) return true;
    // membership check
    if (user_has_any_role($user_id, $company_id)) return true;

    // Deny: return JSON if ajax, else set session + redirect back
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permissão negada para esta empresa']);
        exit;
    }
    $_SESSION['error_message'] = 'Você não tem permissão para acessar/alterar dados desta empresa.';
    header('Location: ' . base_url('index.php'));
    exit;
}

?>
