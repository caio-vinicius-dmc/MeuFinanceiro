<?php
// lib/rbac_helpers.php
// Helpers mínimos para RBAC: carregar permissões do usuário e checar permissões
if (session_status() === PHP_SESSION_NONE) session_start();

function rbac_load_user_permissions_into_session($user_id = null) {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) return [];
        $user_id = $_SESSION['user_id'];
    }
    global $pdo;
    try {
        $sql = "SELECT DISTINCT p.slug FROM permissions p
                JOIN role_permissions rp ON rp.permission_id = p.id
                JOIN user_roles ur ON ur.role_id = rp.role_id
                WHERE ur.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $_SESSION['user_permissions'] = $rows ?: [];
        // store loaded rbac version to avoid unnecessary reloads
        try {
            $verStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'rbac_version' LIMIT 1");
            $verStmt->execute();
            $ver = $verStmt->fetchColumn();
            $_SESSION['rbac_version_loaded'] = $ver !== false ? intval($ver) : 0;
        } catch (Exception $e) {
            $_SESSION['rbac_version_loaded'] = 0;
        }
        return $_SESSION['user_permissions'];
    } catch (Exception $e) {
        // Em caso de erro, garante array vazio e não trava a aplicação
        $_SESSION['user_permissions'] = [];
        $_SESSION['rbac_version_loaded'] = 0;
        return [];
    }
}

function current_user_has_permission($perm_slug) {
    if (!isLoggedIn()) return false;
    // Super admin bypass se implementado
    if (function_exists('isSuperAdmin') && isSuperAdmin()) return true;

    // Ensure permissions are fresh (uses versioning)
    if (function_exists('rbac_maybe_refresh_permissions')) {
        rbac_maybe_refresh_permissions($_SESSION['user_id']);
    } else {
        if (!isset($_SESSION['user_permissions'])) {
            rbac_load_user_permissions_into_session($_SESSION['user_id']);
        }
    }

    return in_array($perm_slug, $_SESSION['user_permissions']);
}

/**
 * Return current rbac_version as int (reads from system_settings.setting_key = 'rbac_version')
 */
function rbac_get_version() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'rbac_version' LIMIT 1");
        $stmt->execute();
        $v = $stmt->fetchColumn();
        return $v !== false ? intval($v) : 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Bump RBAC version (increment int in system_settings)
 */
function rbac_bump_version() {
    global $pdo;
    try {
        // Ensure settings table exists (idempotent)
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(191) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'rbac_version' LIMIT 1");
        $stmt->execute();
        $v = $stmt->fetchColumn();
        if ($v === false) {
            $ins = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('rbac_version', ?)");
            $ins->execute([1]);
        } else {
            $nv = intval($v) + 1;
            $upd = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'rbac_version'");
            $upd->execute([$nv]);
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $e2) {}
        error_log('rbac_bump_version failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Refresh permissions into session only if the persisted rbac_version changed
 */
function rbac_maybe_refresh_permissions($user_id = null) {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) return [];
        $user_id = $_SESSION['user_id'];
    }
    $current = rbac_get_version();
    $loaded = $_SESSION['rbac_version_loaded'] ?? null;
    if ($loaded === null || intval($loaded) !== intval($current) || !isset($_SESSION['user_permissions'])) {
        return rbac_load_user_permissions_into_session($user_id);
    }
    return $_SESSION['user_permissions'];
}

/**
 * Retorna true se usuário atual tem a permissão OR atende um predicado legado.
 * $legacy_fn pode ser nome de função sem parênteses (ex: 'isContador') ou callback.
 */
function current_user_can_or_legacy($perm_slug, $legacy_fn = null) {
    if (current_user_has_permission($perm_slug)) return true;
    if ($legacy_fn === null) return false;
    if (is_string($legacy_fn) && function_exists($legacy_fn)) {
        return call_user_func($legacy_fn);
    }
    if (is_callable($legacy_fn)) return call_user_func($legacy_fn);
    return false;
}

/**
 * Força reload das permissões (útil após atribuir/revogar roles)
 */
function rbac_refresh_current_user_permissions() {
    if (!isLoggedIn()) return [];
    return rbac_load_user_permissions_into_session($_SESSION['user_id']);
}

?>
