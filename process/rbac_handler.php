<?php
// process/rbac_handler.php
require_once __DIR__ . '/../config/functions.php';
global $pdo;

// Apenas usuários com permissão para gerenciar papéis podem usar este handler
if (!function_exists('current_user_has_permission') || !current_user_has_permission('gerenciar_papeis')) {
    http_response_code(403);
    echo 'Acesso negado';
    exit;
}

$action = $_POST['action'] ?? null;
try {
    if ($action === 'create_role') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$name || !$slug) throw new Exception('Nome e slug são obrigatórios');
        $stmt = $pdo->prepare('INSERT INTO roles (name, slug, description) VALUES (?, ?, ?)');
        $stmt->execute([$name, $slug, $desc]);
        if (function_exists('rbac_bump_version')) rbac_bump_version();
        $_SESSION['success_message'] = 'Papel criado com sucesso.';
        header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
        exit;
    }

    if ($action === 'update_role') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$id || !$name || !$slug) throw new Exception('Dados inválidos');
        // Impedir alterações em papel protegido 'super_admin'
        $check = $pdo->prepare('SELECT slug FROM roles WHERE id = ? LIMIT 1'); $check->execute([$id]); $existing_slug = $check->fetchColumn();
        if ($existing_slug === 'super_admin') throw new Exception('O papel super_admin é protegido e não pode ser modificado.');
        $stmt = $pdo->prepare('UPDATE roles SET name = ?, slug = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $slug, $desc, $id]);
        // atualizar permissões vinculadas
        $perms = $_POST['perms'] ?? [];
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$id]);
        $stmtIns = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($perms as $pid) { $stmtIns->execute([$id, intval($pid)]); }
            // after making changes to role_permissions or roles, bump version to invalidate sessions
            // bump rbac version so logged-in sessions refresh permissions
            if (function_exists('rbac_bump_version')) {
                rbac_bump_version();
            }
        $_SESSION['success_message'] = 'Papel atualizado com sucesso.';
        header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
        exit;
    }

    if ($action === 'create_permission') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$name || !$slug) throw new Exception('Nome e slug são obrigatórios');
        $stmt = $pdo->prepare('INSERT INTO permissions (name, slug, description) VALUES (?, ?, ?)');
        $stmt->execute([$name, $slug, $desc]);
        if (function_exists('rbac_bump_version')) rbac_bump_version();
        $_SESSION['success_message'] = 'Permissão criada com sucesso.';
        header('Location: ' . base_url('index.php?page=gerenciar_permissoes'));
        exit;
    }

    if ($action === 'assign_role') {
        $user_id = intval($_POST['user_id']);
        $role_id = intval($_POST['role_id']);
        if (!$user_id || !$role_id) throw new Exception('Dados inválidos');
        // Não permitir atribuição do papel super_admin via este handler
        $check = $pdo->prepare('SELECT slug FROM roles WHERE id = ? LIMIT 1'); $check->execute([$role_id]); $role_slug = $check->fetchColumn();
        if ($role_slug === 'super_admin') throw new Exception('O papel super_admin não pode ser atribuído via interface.');
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)');
        $stmt->execute([$user_id, $role_id]);
        if (function_exists('rbac_bump_version')) rbac_bump_version();
        // If we changed the roles for the current user, refresh their permissions
        if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === $user_id && function_exists('rbac_maybe_refresh_permissions')) {
            rbac_maybe_refresh_permissions($user_id);
        }
        $_SESSION['success_message'] = 'Papel atribuído com sucesso.';
        header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
        exit;
    }

    if ($action === 'revoke_role') {
        $user_id = intval($_POST['user_id']);
        $role_id = intval($_POST['role_id']);
        if (!$user_id || !$role_id) throw new Exception('Dados inválidos');
        // Não permitir revogação do papel super_admin via este handler
        $check = $pdo->prepare('SELECT slug FROM roles WHERE id = ? LIMIT 1'); $check->execute([$role_id]); $role_slug = $check->fetchColumn();
        if ($role_slug === 'super_admin') throw new Exception('O papel super_admin não pode ser revogado via interface.');
        $stmt = $pdo->prepare('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?');
        $stmt->execute([$user_id, $role_id]);
        if (function_exists('rbac_bump_version')) rbac_bump_version();
        if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) === $user_id && function_exists('rbac_maybe_refresh_permissions')) {
            rbac_maybe_refresh_permissions($user_id);
        }
        $_SESSION['success_message'] = 'Papel revogado com sucesso.';
        header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
        exit;
    }

    throw new Exception('Ação desconhecida');

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
    exit;
}
