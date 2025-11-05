<?php
// pages/gerenciar_formas_pagamento.php

// Apenas admins podem acessar esta página
if (!isAdmin()) {
    $_SESSION['error_message'] = "Você não tem permissão para acessar esta página.";
    header('Location: index.php?page=dashboard');
    exit;
}

// Busca todas as formas de pagamento para listar na tabela
$stmt = $pdo->query("SELECT * FROM formas_pagamento ORDER BY nome ASC");
$formas_pagamento = $stmt->fetchAll();

?>

<div class="container-fluid">
    <?php render_page_title('Formas de Pagamento', 'Gerencie os meios de pagamento disponíveis no sistema.', 'bi-credit-card'); ?>
    <div class="row">
        <!-- Coluna do Formulário de Cadastro -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Adicionar Forma de Pagamento</h5>
                </div>
                <div class="card-body">
                    <form action="process/crud_handler.php" method="POST">
                        <input type="hidden" name="action" value="criar_forma_pagamento">
                        
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Ex: PIX" required>
                        </div>

                        <div class="mb-3">
                            <label for="icone_bootstrap" class="form-label">Ícone do Bootstrap</label>
                            <input type="text" class="form-control" id="icone_bootstrap" name="icone_bootstrap" placeholder="Ex: bi-qr-code">
                            <div class="form-text">Você pode encontrar os nomes dos ícones na <a href="https://icons.getbootstrap.com/" target="_blank">biblioteca do Bootstrap Icons</a>.</div>
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

        <!-- Coluna da Tabela -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Formas de Pagamento Cadastradas</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Ícone</th>
                                    <th>Nome</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($formas_pagamento)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Nenhuma forma de pagamento cadastrada.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($formas_pagamento as $forma): ?>
                                        <tr>
                                            <td><i class="bi <?php echo htmlspecialchars($forma['icone_bootstrap']); ?> fs-5"></i></td>
                                            <td><?php echo htmlspecialchars($forma['nome']); ?></td>
                                            <td>
                                                <?php if ($forma['ativo']): ?>
                                                    <span class="badge bg-success">Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarFormaPagamento" 
                                                        data-id="<?php echo $forma['id']; ?>"
                                                        data-nome="<?php echo htmlspecialchars($forma['nome']); ?>"
                                                        data-icone="<?php echo htmlspecialchars($forma['icone_bootstrap']); ?>"
                                                        data-ativo="<?php echo $forma['ativo']; ?>">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>
                                                <a href="process/crud_handler.php?action=excluir_forma_pagamento&id=<?php echo $forma['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta forma de pagamento? Esta ação não pode ser desfeita.');">
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
                        const modalEditar = document.getElementById('modalEditarFormaPagamento');
                        if (modalEditar) {
                            modalEditar.addEventListener('show.bs.modal', function (event) {
                                const button = event.relatedTarget;
                                const id = button.getAttribute('data-id');
                                const nome = button.getAttribute('data-nome') || '';
                                const icone = button.getAttribute('data-icone') || '';
                                const ativo = button.getAttribute('data-ativo');

                                const modalId = modalEditar.querySelector('#edit_id');
                                const modalNome = modalEditar.querySelector('#edit_nome');
                                const modalIcone = modalEditar.querySelector('#edit_icone_bootstrap');
                                const modalAtivo = modalEditar.querySelector('#edit_ativo');

                                if (modalId) modalId.value = id || '';
                                if (modalNome) modalNome.value = nome;
                                if (modalIcone) modalIcone.value = icone;
                                if (modalAtivo) modalAtivo.checked = (ativo == 1 || ativo === '1');
                            });
                        }
                    });
                    </script>

<!-- Modal de Edição -->
<div class="modal fade" id="modalEditarFormaPagamento" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarLabel">Editar Forma de Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar_forma_pagamento">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="mb-3">
                        <label for="edit_nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="edit_nome" name="nome" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_icone_bootstrap" class="form-label">Ícone do Bootstrap</label>
                        <input type="text" class="form-control" id="edit_icone_bootstrap" name="icone_bootstrap">
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
