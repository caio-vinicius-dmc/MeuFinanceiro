<?php
// lib/rbac.php
// Biblioteca simples de RBAC para consultas e helpers
// Depende de $pdo (fornecido por config/db.php via config/functions.php)

if (!defined('RBAC_LOADED')) {
    define('RBAC_LOADED', true);

    /**
     * Carrega roles e permissions do usuário na sessão para evitar múltiplas queries.
     * @param int $userId
     */
    function rbac_load_user_into_session($userId) {
        if (!isset($_SESSION)) session_start();
        global $pdo;
        try {
            // Carrega roles do usuário
            $stmt = $pdo->prepare('SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?');
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $_SESSION['user_roles'] = $roles;

            // Carrega permissões via roles
            if (!empty($roles)) {
                $in  = str_repeat('?,', count($roles) - 1) . '?';
                $sql = "SELECT DISTINCT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN roles r ON r.id = rp.role_id WHERE r.slug IN ($in)";
                $stmt2 = $pdo->prepare($sql);
                $stmt2->execute($roles);
                $perms = $stmt2->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } else {
                $perms = [];
            }
            $_SESSION['user_permissions'] = $perms;
            return true;
        } catch (Exception $e) {
            error_log('RBAC load error: ' . $e->getMessage());
            $_SESSION['user_roles'] = $_SESSION['user_roles'] ?? [];
            $_SESSION['user_permissions'] = $_SESSION['user_permissions'] ?? [];
            return false;
        }
    }

    function rbac_get_user_roles($userId) {
        global $pdo;
        try {
            $stmt = $pdo->prepare('SELECT r.* FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?');
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    function rbac_get_user_permissions($userId) {
        global $pdo;
        try {
            $stmt = $pdo->prepare('SELECT DISTINCT p.* FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id JOIN user_roles ur ON ur.role_id = rp.role_id WHERE ur.user_id = ?');
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }

    function current_user_has_permission($permSlug) {
        if (!isset($_SESSION['user_permissions'])) return false;
        return in_array($permSlug, $_SESSION['user_permissions']);
    }

    function require_permission($permSlug) {
        if (!isset($_SESSION) || !isset($_SESSION['user_id'])) {
            header('Location: ' . base_url('index.php?page=login'));
            exit;
        }
        if (current_user_has_permission($permSlug) || (function_exists('isSuperAdmin') && isSuperAdmin())) {
            return true;
        }
        // not allowed
        http_response_code(403);
        echo '<h3>403 - Acesso negado</h3><p>Você não tem permissão para acessar esta área.</p>';
        exit;
    }

    // Atribui um papel a um usuário
    function rbac_assign_role($userId, $roleId) {
        global $pdo;
        try {
            $stmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)');
            return $stmt->execute([$userId, $roleId]);
        } catch (Exception $e) { return false; }
    }

    function rbac_revoke_role($userId, $roleId) {
        global $pdo;
        try {
            $stmt = $pdo->prepare('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?');
            return $stmt->execute([$userId, $roleId]);
        } catch (Exception $e) { return false; }
    }

}
