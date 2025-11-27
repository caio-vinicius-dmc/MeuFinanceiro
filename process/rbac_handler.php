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
        $stmt = $pdo->prepare('UPDATE roles SET name = ?, slug = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $slug, $desc, $id]);
        // atualizar permissões vinculadas
        $perms = $_POST['perms'] ?? [];
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$id]);
        $stmtIns = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($perms as $pid) { $stmtIns->execute([$id, intval($pid)]); }
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
        $_SESSION['success_message'] = 'Permissão criada com sucesso.';
        header('Location: ' . base_url('index.php?page=gerenciar_permissoes'));
        exit;
    }

    if ($action === 'assign_role') {
        $user_id = intval($_POST['user_id']);
        $role_id = intval($_POST['role_id']);
        if (!$user_id || !$role_id) throw new Exception('Dados inválidos');
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)');
        $stmt->execute([$user_id, $role_id]);
        $_SESSION['success_message'] = 'Papel atribuído ao usuário.';
        header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
        exit;
    }

    if ($action === 'revoke_role') {
        $user_id = intval($_POST['user_id']);
        $role_id = intval($_POST['role_id']);
        if (!$user_id || !$role_id) throw new Exception('Dados inválidos');
        $stmt = $pdo->prepare('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?');
        $stmt->execute([$user_id, $role_id]);
        $_SESSION['success_message'] = 'Papel removido do usuário.';
        header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
        exit;
    }

    throw new Exception('Ação desconhecida');

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
    exit;
}
