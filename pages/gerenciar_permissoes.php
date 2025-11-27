<?php
// pages/gerenciar_permissoes.php
require_once 'config/functions.php';
require_permission('gerenciar_papeis');
global $pdo;

$perms = $pdo->query('SELECT * FROM permissions ORDER BY name')->fetchAll();

?>
<?php render_page_title('Gerenciar Permissões', 'Criar e listar permissões disponíveis', 'bi-key'); ?>

<div class="card">
    <div class="card-body">
        <h5>Permissões existentes</h5>
        <table class="table table-sm">
            <thead><tr><th>Nome</th><th>Slug</th></tr></thead>
            <tbody>
            <?php foreach ($perms as $p): ?>
                <tr><td><?php echo htmlspecialchars($p['name']); ?></td><td><?php echo htmlspecialchars($p['slug']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <hr>
        <h6>Criar nova permissão</h6>
        <form method="POST" action="process/rbac_handler.php">
            <input type="hidden" name="action" value="create_permission">
            <div class="mb-2"><input class="form-control" id="perm_name" name="name" placeholder="Nome da permissão"></div>
            <div class="mb-2"><input class="form-control" id="perm_slug" name="slug" placeholder="slug_unico"></div>
            <div class="mb-2"><textarea class="form-control" name="description" placeholder="Descrição (opcional)"></textarea></div>
            <button class="btn btn-primary" id="btnCreatePerm">Criar permissão</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btnCreatePerm');
    if (btn) {
        btn.addEventListener('click', function (e) {
            var name = document.getElementById('perm_name').value.trim();
            var slug = document.getElementById('perm_slug').value.trim();
            if (!name || !slug) {
                e.preventDefault();
                alert('Nome e slug são obrigatórios para criar uma permissão.');
                return false;
            }
        });
    }
});
</script>
