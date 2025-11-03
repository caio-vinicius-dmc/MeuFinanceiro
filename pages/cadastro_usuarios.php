<?php
// pages/cadastro_usuarios.php
global $pdo;

// Apenas Admin pode ver esta página
if (!isAdmin()) {
    header("Location: " . base_url('index.php?page=dashboard'));
    exit;
}

// Buscar usuários existentes
$stmt_users = $pdo->query("SELECT id, nome, email, telefone, tipo, ativo, id_cliente_associado, acesso_lancamentos FROM usuarios ORDER BY nome");
$usuarios = $stmt_users->fetchAll();

// Buscar clientes para os selects (usado no form de 'Novo' e no 'Modal de Edição')
$stmt_clientes = $pdo->query("SELECT id, nome_responsavel FROM clientes ORDER BY nome_responsavel");
$clientes = $stmt_clientes->fetchAll();

// Buscar TODAS as associações de contadores para popular os modais
$stmt_assoc = $pdo->query("SELECT id_usuario_contador, id_cliente FROM contador_clientes_assoc");
$all_associations_raw = $stmt_assoc->fetchAll(PDO::FETCH_ASSOC);

// Mapear associações para fácil acesso: [id_usuario_contador => [id_cliente1, id_cliente2]]
$all_associations = [];
foreach ($all_associations_raw as $assoc) {
    $all_associations[$assoc['id_usuario_contador']][] = $assoc['id_cliente'];
}

?>

<h3>Cadastro de Usuários</h3>
<p class="text-muted">Gerencie os acessos ao sistema (Admins, Contadores e Clientes).</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Novo Usuário</h5>
            </div>
            <div class="card-body">
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="cadastrar_usuario">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email (Login)</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>

                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha Provisória</label>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo_usuario" class="form-label">Tipo de Usuário</label>
                        <select class="form-select" id="tipo_usuario" name="tipo_usuario" required>
                            <option value="" selected disabled>Selecione o tipo...</option>
                            <option value="admin">Admin</option>
                            <option value="contador">Contador</option>
                            <option value="cliente">Cliente (Acesso Portal)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="assoc_cliente_div" style="display: none;">
                        <label for="id_cliente_associado" class="form-label">Associar ao Cliente:</label>
                        <select class="form-select" id="id_cliente_associado" name="id_cliente_associado">
                            <option value="">Nenhum</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="acesso_lancamentos" name="acesso_lancamentos" value="1">
                            <label class="form-check-label" for="acesso_lancamentos">Permitir acesso à tela de Lançamentos</label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="assoc_contador_div" style="display: none;">
                        <label class="form-label">Associar Contador aos Clientes:</label>
                        <div class="border rounded p-2" style="max-height:200px; overflow:auto;">
                            <?php foreach ($clientes as $cliente): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="id_clientes_associados[]" id="cliente_check_<?php echo $cliente['id']; ?>" value="<?php echo $cliente['id']; ?>">
                                    <label class="form-check-label" for="cliente_check_<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-text text-muted">Marque os clientes que este contador deverá acessar.</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" checked>
                            <label class="form-check-label" for="ativo">Usuário ativo (permite login)</label>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Salvar Usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
             <div class="card-header">
                <h5 class="mb-0">Usuários Cadastrados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email/Telefone</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <?php
                                // Pega as associações (IDs de clientes) deste contador
                                $user_assoc_ids = $all_associations[$usuario['id']] ?? [];
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($usuario['email']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($usuario['telefone'] ?? 'N/A'); ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo ucfirst($usuario['tipo']); ?></span></td>
                                <td>
                                    <?php echo ($usuario['ativo']) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditarUsuario"
                                            data-id="<?php echo $usuario['id']; ?>"
                                            data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"
                                            data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                            data-telefone="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>"
                                            data-tipo="<?php echo $usuario['tipo']; ?>"
                                            data-ativo="<?php echo $usuario['ativo']; ?>"
                                            data-id_cliente_associado="<?php echo $usuario['id_cliente_associado']; ?>"
                                            data-acesso_lancamentos="<?php echo $usuario['acesso_lancamentos']; ?>"
                                            data-assoc_clientes='<?php echo json_encode($user_assoc_ids); ?>'
                                            title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                        <input type="hidden" name="action" value="deletar_usuario">
                                        <input type="hidden" name="id_usuario" value="<?php echo $usuario['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir" <?php echo ($usuario['id'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarUsuarioLabel">Editar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <input type="hidden" name="action" value="editar_usuario">
                <input type="hidden" name="id_usuario" id="edit_id_usuario">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_nome" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="edit_nome" name="nome" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email (Login)</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="edit_telefone" name="telefone" placeholder="(XX) XXXXX-XXXX">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_tipo_usuario" class="form-label">Tipo de Usuário</label>
                            <select class="form-select" id="edit_tipo_usuario" name="tipo_usuario" required>
                                <option value="admin">Admin</option>
                                <option value="contador">Contador</option>
                                <option value="cliente">Cliente (Acesso Portal)</option>
                            </select>
                        </div>

                        <hr>
                        
                        <div class="col-12" id="edit_assoc_cliente_div" style="display: none;">
                            <label for="edit_id_cliente_associado" class="form-label">Associar ao Cliente:</label>
                            <select class="form-select" id="edit_id_cliente_associado" name="id_cliente_associado">
                                <option value="">Nenhum</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="edit_acesso_lancamentos" name="acesso_lancamentos" value="1">
                                <label class="form-check-label" for="edit_acesso_lancamentos">Permitir acesso à tela de Lançamentos</label>
                            </div>
                        </div>
                        
                        <div class="col-12" id="edit_assoc_contador_div" style="display: none;">
                            <label class="form-label">Associar Contador aos Clientes:</label>
                            <div class="border rounded p-2" style="max-height:200px; overflow:auto;">
                                <?php foreach ($clientes as $cliente): ?>
                                    <div class="form-check">
                                        <input class="form-check-input edit-cliente-checkbox" type="checkbox" name="id_clientes_associados[]" id="edit_cliente_check_<?php echo $cliente['id']; ?>" value="<?php echo $cliente['id']; ?>">
                                        <label class="form-check-label" for="edit_cliente_check_<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="form-text text-muted">Marque os clientes que este contador deverá acessar.</small>
                        </div>

                        <hr>
                        
                        <div class="col-12">
                             <label for="nova_senha" class="form-label">Nova Senha</label>
                             <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                             <small class="form-text text-muted">Deixe em branco para não alterar a senha.</small>
                        </div>

                        <div class="col-12 mt-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_ativo" name="ativo" value="1">
                                <label class="form-check-label" for="edit_ativo">Usuário ativo (permite login)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Mudanças</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoUsuarioSelect = document.getElementById('tipo_usuario');
    const assocClienteDiv = document.getElementById('assoc_cliente_div');
    const assocContadorDiv = document.getElementById('assoc_contador_div');
    const acessoLancamentosCheckbox = document.getElementById('acesso_lancamentos');

    function toggleAssocDivs() {
        assocClienteDiv.style.display = 'none';
        assocContadorDiv.style.display = 'none';
        acessoLancamentosCheckbox.checked = false; // Reset checkbox when type changes

        if (tipoUsuarioSelect.value === 'cliente') {
            assocClienteDiv.style.display = 'block';
        } else if (tipoUsuarioSelect.value === 'contador') {
            assocContadorDiv.style.display = 'block';
        }
    }

    tipoUsuarioSelect.addEventListener('change', toggleAssocDivs);
    toggleAssocDivs(); // Call on load to set initial state

    // Modal de Edição
    const modalEditarUsuario = document.getElementById('modalEditarUsuario');
    modalEditarUsuario.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nome = button.getAttribute('data-nome');
        const email = button.getAttribute('data-email');
        const telefone = button.getAttribute('data-telefone');
        const tipo = button.getAttribute('data-tipo');
        const id_cliente_associado = button.getAttribute('data-id_cliente_associado');
        const acesso_lancamentos = button.getAttribute('data-acesso_lancamentos'); // Get the new attribute
    const ativo = button.getAttribute('data-ativo');
        const assoc_clientes_json = button.getAttribute('data-assoc_clientes');
        const assoc_clientes = JSON.parse(assoc_clientes_json);

        modalEditarUsuario.querySelector('#edit_id_usuario').value = id;
        modalEditarUsuario.querySelector('#edit_nome').value = nome;
        modalEditarUsuario.querySelector('#edit_email').value = email;
        modalEditarUsuario.querySelector('#edit_telefone').value = telefone;
        
        const editTipoUsuarioSelect = modalEditarUsuario.querySelector('#edit_tipo_usuario');
        editTipoUsuarioSelect.value = tipo;

        const editAssocClienteDiv = modalEditarUsuario.querySelector('#edit_assoc_cliente_div');
        const editAssocContadorDiv = modalEditarUsuario.querySelector('#edit_assoc_contador_div');
        const editIdClienteAssociadoSelect = modalEditarUsuario.querySelector('#edit_id_cliente_associado');
        const editAcessoLancamentosCheckbox = modalEditarUsuario.querySelector('#edit_acesso_lancamentos'); // Get the new checkbox
        const editIdClientesAssociadosSelect = modalEditarUsuario.querySelector('#edit_id_clientes_associados');
    const editAtivoCheckbox = modalEditarUsuario.querySelector('#edit_ativo');

        // Reset visibility
        editAssocClienteDiv.style.display = 'none';
        editAssocContadorDiv.style.display = 'none';
        editAcessoLancamentosCheckbox.checked = false; // Reset checkbox
    editAtivoCheckbox.checked = false; // Reset ativo checkbox

        if (tipo === 'cliente') {
            editAssocClienteDiv.style.display = 'block';
            editIdClienteAssociadoSelect.value = id_cliente_associado;
            editAcessoLancamentosCheckbox.checked = (acesso_lancamentos == 1); // Set checked state
        }

        // Set ativo state (aplica para qualquer tipo)
        editAtivoCheckbox.checked = (ativo == 1);
        // Evita desativar o próprio usuário logado para não travar o acesso
        if (id == '<?php echo $_SESSION['user_id']; ?>') {
            editAtivoCheckbox.checked = true;
            editAtivoCheckbox.disabled = true;
        } else {
            editAtivoCheckbox.disabled = false;
        }
        
        if (tipo === 'contador') {
            editAssocContadorDiv.style.display = 'block';
            // Clear previous checkbox selections
            const editCheckboxes = modalEditarUsuario.querySelectorAll('.edit-cliente-checkbox');
            editCheckboxes.forEach(cb => cb.checked = false);
            // Check associated clients
            assoc_clientes.forEach(clientId => {
                const cb = modalEditarUsuario.querySelector(`#edit_cliente_check_${clientId}`);
                if (cb) cb.checked = true;
            });
        }

        // Event listener for type change in edit modal
        editTipoUsuarioSelect.onchange = function() {
            editAssocClienteDiv.style.display = 'none';
            editAssocContadorDiv.style.display = 'none';
            editAcessoLancamentosCheckbox.checked = false; // Reset checkbox
            if (this.value === 'cliente') {
                editAssocClienteDiv.style.display = 'block';
            } else if (this.value === 'contador') {
                editAssocContadorDiv.style.display = 'block';
            }
        };
    });
});
</script>