<?php
// pages/editar_papel.php
require_once 'config/functions.php';
require_permission('gerenciar_papeis');
global $pdo;

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . base_url('index.php?page=gerenciar_papeis')); exit; }

$role = $pdo->prepare('SELECT * FROM roles WHERE id = ?'); $role->execute([$id]); $role = $role->fetch();
if (!$role) { header('Location: ' . base_url('index.php?page=gerenciar_papeis')); exit; }

// Protege papel super_admin contra edição pela UI
if (isset($role['slug']) && $role['slug'] === 'super_admin') {
    $_SESSION['error_message'] = 'O papel super_admin é protegido e não pode ser editado.';
    header('Location: ' . base_url('index.php?page=gerenciar_papeis'));
    exit;
}

$perms = $pdo->query('SELECT * FROM permissions ORDER BY name')->fetchAll();
$rolePermsStmt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?'); $rolePermsStmt->execute([$id]);
$rolePerms = $rolePermsStmt->fetchAll(PDO::FETCH_COLUMN);

?>
<?php render_page_title('Editar Papel: ' . htmlspecialchars($role['name']), 'Ajuste nome, slug e permissões do papel', 'bi-person-badge'); ?>

<form method="POST" action="process/rbac_handler.php">
    <input type="hidden" name="action" value="update_role">
    <input type="hidden" name="id" value="<?php echo $role['id']; ?>">
    <div class="mb-2"><label>Nome</label><input class="form-control" name="name" value="<?php echo htmlspecialchars($role['name']); ?>"></div>
    <div class="mb-2"><label>Slug</label><input class="form-control" name="slug" value="<?php echo htmlspecialchars($role['slug']); ?>"></div>
    <div class="mb-2"><label>Descrição</label><textarea class="form-control" name="description"><?php echo htmlspecialchars($role['description']); ?></textarea></div>
    <h6>Permissões</h6>
    <div class="mb-3">
        <?php foreach ($perms as $p): ?>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="perms[]" value="<?php echo $p['id']; ?>" id="perm_<?php echo $p['id']; ?>" <?php echo in_array($p['id'], $rolePerms) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="perm_<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($p['slug']); ?>)</small></label>
            </div>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-primary">Salvar</button>
</form>
