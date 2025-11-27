<?php
// pages/gerenciar_papeis.php
require_once 'config/functions.php';
require_permission('gerenciar_papeis');
global $pdo;

$roles = $pdo->query('SELECT * FROM roles ORDER BY id DESC')->fetchAll();
$permissions = $pdo->query('SELECT * FROM permissions ORDER BY name')->fetchAll();

// Lista de usuários para atribuições simples
$users = $pdo->query('SELECT id, nome, email FROM usuarios ORDER BY nome')->fetchAll();

?>
<?php render_page_title('Gerenciar Papéis', 'Criar, editar papéis e atribuir permissões', 'bi-person-badge'); ?>

<div class="row">
    <div class="col-md-6">
        <h5>Papéis existentes</h5>
        <table class="table table-sm">
            <thead><tr><th>Nome</th><th>Slug</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                    <td><?php echo htmlspecialchars($r['slug']); ?></td>
                    <td>
                        <?php if (isset($r['slug']) && $r['slug'] === 'super_admin'): ?>
                            <span class="text-muted small">Protegido</span>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-primary" href="index.php?page=editar_papel&id=<?php echo $r['id']; ?>">Editar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <hr>
        <h6>Criar novo papel</h6>
        <form action="process/rbac_handler.php" method="POST">
            <input type="hidden" name="action" value="create_role">
            <div class="mb-2"><input class="form-control" name="name" placeholder="Nome do papel"></div>
            <div class="mb-2"><input class="form-control" name="slug" placeholder="slug_unico"></div>
            <div class="mb-2"><textarea class="form-control" name="description" placeholder="Descrição"></textarea></div>
            <button class="btn btn-primary">Criar</button>
        </form>
    </div>

    <div class="col-md-6">
        <h5>Gerenciar permissões do papel</h5>
        <p class="small text-muted">Clique em Editar num papel para ajustar permissões.</p>

        <h6>Permissões existentes</h6>
        <table class="table table-sm">
            <thead><tr><th>Nome</th><th>Slug</th></tr></thead>
            <tbody>
            <?php foreach ($permissions as $p): ?>
                <tr><td><?php echo htmlspecialchars($p['name']); ?></td><td><?php echo htmlspecialchars($p['slug']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <hr>
        <h6>Atribuir papel a usuário</h6>
        <form action="process/rbac_handler.php" method="POST">
            <input type="hidden" name="action" value="assign_role">
            <div class="mb-2">
                <select name="user_id" class="form-select">
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nome'] . ' <' . $u['email'] . '>'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <select name="role_id" class="form-select">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary">Atribuir</button>
        </form>
    </div>
</div>
