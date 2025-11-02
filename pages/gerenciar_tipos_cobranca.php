<?php
// pages/gerenciar_tipos_cobranca.php

// Apenas Admin pode acessar esta página
if (!isAdmin()) {
    echo '<div class="alert alert-danger">Acesso negado.</div>';
    return;
}

// Lógica para buscar os tipos de cobrança
$stmt = $pdo->query("SELECT * FROM tipos_cobranca ORDER BY nome ASC");
$tipos_cobranca = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="container-fluid">
    <h1 class="h2 mb-4">Gerenciar Tipos de Cobrança</h1>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Tipos de Cobrança Cadastrados</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoTipoCobranca">
                <i class="bi bi-plus-circle me-2"></i>Novo Tipo
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tipos_cobranca)): ?>
                            <tr>
                                <td colspan="3" class="text-center">Nenhum tipo de cobrança cadastrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tipos_cobranca as $tipo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tipo['nome']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $tipo['ativo'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $tipo['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-warning" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditarTipoCobranca"
                                                data-id="<?php echo $tipo['id']; ?>"
                                                data-nome="<?php echo htmlspecialchars($tipo['nome']); ?>"
                                                data-ativo="<?php echo $tipo['ativo']; ?>">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <a href="process/crud_handler.php?action=excluir_tipo_cobranca&id=<?php echo $tipo['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Tem certeza que deseja excluir este tipo de cobrança? Esta ação não pode ser desfeita.');">
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

<!-- Modal de Novo Tipo de Cobrança -->
<div class="modal fade" id="modalNovoTipoCobranca" tabindex="-1" aria-labelledby="modalNovoTipoCobrancaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoTipoCobrancaLabel">Novo Tipo de Cobrança</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar_tipo_cobranca">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" checked>
                        <label class="form-check-label" for="ativo">Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Edição de Tipo de Cobrança -->
<div class="modal fade" id="modalEditarTipoCobranca" tabindex="-1" aria-labelledby="modalEditarTipoCobrancaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarTipoCobrancaLabel">Editar Tipo de Cobrança</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar_tipo_cobranca">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="edit_nome" name="nome" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit_ativo" name="ativo" value="1">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEditar = document.getElementById('modalEditarTipoCobranca');
    modalEditar.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nome = button.getAttribute('data-nome');
        const ativo = button.getAttribute('data-ativo');

        const modalId = modalEditar.querySelector('#edit_id');
        const modalNome = modalEditar.querySelector('#edit_nome');
        const modalAtivo = modalEditar.querySelector('#edit_ativo');

        modalId.value = id;
        modalNome.value = nome;
        modalAtivo.checked = (ativo == 1);
    });
});
</script>
