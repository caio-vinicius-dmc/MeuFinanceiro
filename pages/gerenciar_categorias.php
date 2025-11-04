<?php
// pages/gerenciar_categorias.php

// Apenas admins podem acessar esta página
if (!isAdmin()) {
    $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
    header('Location: index.php?page=dashboard');
    exit;
}

// Busca todas as categorias para listar na tabela
$stmt = $pdo->query("SELECT * FROM categorias_lancamento ORDER BY nome ASC");
$categorias = $stmt->fetchAll();

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Adicionar Categoria de Lançamento</h5>
                </div>
                <div class="card-body">
                    <form action="process/crud_handler.php" method="POST">
                        <input type="hidden" name="action" value="criar_categoria_lancamento">

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Ex: Conta de Luz" required>
                        </div>

                        <div class="mb-3">
                            <label for="descricao" class="form-label">Descrição (opcional)</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1" checked>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>

                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Categorias de Lançamento Cadastradas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Descrição</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categorias)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Nenhuma categoria cadastrada.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categorias as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($cat['descricao']); ?></td>
                                            <td>
                                                <?php if ($cat['ativo']): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarCategoria" 
                                                        data-id="<?php echo $cat['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($cat['nome']); ?>"
                                                        data-descricao="<?php echo htmlspecialchars($cat['descricao']); ?>"
                                                        data-ativo="<?php echo $cat['ativo']; ?>">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>
                                                <a href="process/crud_handler.php?action=excluir_categoria_lancamento&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta categoria?');">
                                                    <i class="bi bi-trash-fill"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEditar = document.getElementById('modalEditarCategoria');
    if (modalEditar) {
        modalEditar.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nome = button.getAttribute('data-nome') || '';
            const descricao = button.getAttribute('data-descricao') || '';
            const ativo = button.getAttribute('data-ativo');

            modalEditar.querySelector('#edit_id').value = id || '';
            modalEditar.querySelector('#edit_nome').value = nome;
            modalEditar.querySelector('#edit_descricao').value = descricao;
            modalEditar.querySelector('#edit_ativo').checked = (ativo == 1 || ativo === '1');
        });
    }
});
</script>

<!-- Modal de Edição -->
<div class="modal fade" id="modalEditarCategoria" tabindex="-1" aria-labelledby="modalEditarCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarCategoriaLabel">Editar Categoria</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar_categoria_lancamento">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="mb-3">
                        <label for="edit_nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="edit_nome" name="nome" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="edit_descricao" name="descricao" rows="3"></textarea>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="edit_ativo" name="ativo" value="1">
                        <label class="form-check-label" for="edit_ativo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
