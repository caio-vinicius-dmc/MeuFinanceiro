<?php
// pages/cadastro_usuarios.php (reconstructed)
$pdo;

$stmt_users = $pdo->query("SELECT id, nome, email, telefone, tipo, ativo, id_cliente_associado, acesso_lancamentos, COALESCE(is_super_admin,0) AS is_super_admin FROM usuarios ORDER BY nome");
$usuarios = $stmt_users->fetchAll();

// Buscar clientes para os selects (usado no form de 'Novo' e no 'Modal de Edição')
$stmt_clientes = $pdo->query("SELECT id, nome_responsavel FROM clientes ORDER BY nome_responsavel");
$clientes = $stmt_clientes->fetchAll();

// Buscar papéis (roles) disponíveis para atribuição, exclui super_admin
$stmt_roles = $pdo->query("SELECT id, name, slug FROM roles WHERE slug != 'super_admin' ORDER BY name");
$available_roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);

// Buscar roles atribuídos por usuário para popular os botões de edição
$stmt_user_roles = $pdo->query('SELECT user_id, role_id FROM user_roles');
$user_roles_rows = $stmt_user_roles->fetchAll(PDO::FETCH_ASSOC);
$user_roles_map = [];
foreach ($user_roles_rows as $ur) {
    $user_roles_map[$ur['user_id']][] = intval($ur['role_id']);
}

// Map role ids by slug for client-side logic
$role_id_cliente = 0; $role_id_contador = 0; $role_id_admin = 0;
foreach ($available_roles as $r) {
    if ($r['slug'] === 'cliente') $role_id_cliente = intval($r['id']);
    if ($r['slug'] === 'contador') $role_id_contador = intval($r['id']);
    if ($r['slug'] === 'admin') $role_id_admin = intval($r['id']);
}

// Buscar TODAS as associações de contadores para popular os modais
$stmt_assoc = $pdo->query("SELECT id_usuario_contador, id_cliente FROM contador_clientes_assoc");
$all_associations_raw = $stmt_assoc->fetchAll(PDO::FETCH_ASSOC);

// Mapear associações para fácil acesso: [id_usuario_contador => [id_cliente1, id_cliente2]]
$all_associations = [];
foreach ($all_associations_raw as $assoc) {
    $all_associations[$assoc['id_usuario_contador']][] = $assoc['id_cliente'];
}

?>

<?php render_page_title('Cadastro de Usuários', 'Gerencie os acessos ao sistema (Admins, Contadores e Clientes).', 'bi-person-plus'); ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Novo Usuário</h5>
            </div>
            <div class="card-body">
                <form id="formNovoUsuario" action="process/crud_handler.php" method="POST">
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
                    
                    <!-- Tipo de usuário agora é derivado a partir dos papéis atribuídos (roles). -->
                    <input type="hidden" id="tipo_usuario" name="tipo_usuario" value="">

                    <div class="mb-3" id="assoc_cliente_div" style="display: none;">
                        <label for="id_cliente_associado" class="form-label">Associar ao Cliente:</label>
                        <select class="form-select" id="id_cliente_associado" name="id_cliente_associado">
                            <option value="">Nenhum</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-2 small text-muted">As permissões de acesso (ex.: Lançamentos) são gerenciadas por papéis e permissões. Para controlar o acesso, edite o papel do cliente em <a href="<?php echo base_url('index.php?page=gerenciar_papeis'); ?>">Gerenciar Papéis</a>.</div>
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

                    <?php if (!empty($available_roles)): ?>
                    <div class="mb-3">
                        <label class="form-label">Papéis (roles)</label>
                        <div class="border rounded p-2" style="max-height:200px; overflow:auto;">
                            <?php foreach ($available_roles as $role): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="role_ids[]" id="role_check_<?php echo $role['id']; ?>" value="<?php echo $role['id']; ?>">
                                    <label class="form-check-label" for="role_check_<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($role['slug']); ?>)</small></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-text text-muted">Atribua papéis ao usuário. Não é possível atribuir o papel Super Admin por esta interface.</small>
                    </div>
                    <?php endif; ?>

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
                                <td class="fw-bold"><?php echo htmlspecialchars($usuario['nome']); ?>
                                    <?php if (!empty($usuario['is_super_admin'])): ?>
                                        <span class="badge bg-dark ms-2">Super Admin</span>
                                    <?php endif; ?>
                                </td>
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
                                    <!-- Botões: Editar / Excluir -->
                                    <button class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditarUsuario"
                                        data-id="<?php echo $usuario['id']; ?>"
                                        data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"
                                        data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                        data-telefone="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>"
                                        data-id_cliente_associado="<?php echo $usuario['id_cliente_associado']; ?>"
                                        data-acesso_lancamentos="<?php echo $usuario['acesso_lancamentos']; ?>"
                                        data-ativo="<?php echo $usuario['ativo']; ?>"
                                        data-is-super="<?php echo intval($usuario['is_super_admin']); ?>"
                                        data-assoc_clientes='<?php echo json_encode($user_assoc_ids); ?>'
                                        data-user-roles='<?php echo json_encode($user_roles_map[$usuario['id']] ?? []); ?>'
                                        title="Editar" <?php echo !empty($usuario['is_super_admin']) ? 'disabled' : ''; ?>>
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                        <input type="hidden" name="action" value="deletar_usuario">
                                        <input type="hidden" name="id_usuario" value="<?php echo $usuario['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir" <?php echo ($usuario['id'] == $_SESSION['user_id'] || !empty($usuario['is_super_admin'])) ? 'disabled' : ''; ?>>
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

<!-- Modal de Edição -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarUsuarioLabel">Editar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formEditarUsuario" action="process/crud_handler.php" method="POST">
                <input type="hidden" name="action" value="editar_usuario">
                <input type="hidden" name="id_usuario" id="edit_id_usuario">
                <input type="hidden" id="edit_tipo_usuario" name="tipo_usuario" value="">
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

                        <hr>
                        <div class="col-12" id="edit_assoc_cliente_div" style="display: none;">
                            <label for="edit_id_cliente_associado" class="form-label">Associar ao Cliente:</label>
                            <select class="form-select" id="edit_id_cliente_associado" name="id_cliente_associado">
                                <option value="">Nenhum</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="mt-2 small text-muted">As permissões de acesso (ex.: Lançamentos) são gerenciadas por papéis e permissões. Para controlar o acesso, edite o papel do cliente em <a href="<?php echo base_url('index.php?page=gerenciar_papeis'); ?>">Gerenciar Papéis</a>.</div>
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
                        <?php if (!empty($available_roles)): ?>
                        <div class="col-12">
                            <label class="form-label">Papéis (roles)</label>
                            <div class="border rounded p-2" style="max-height:200px; overflow:auto;">
                                <?php foreach ($available_roles as $role): ?>
                                    <div class="form-check">
                                        <input class="form-check-input edit-role-checkbox" type="checkbox" name="role_ids[]" id="edit_role_check_<?php echo $role['id']; ?>" value="<?php echo $role['id']; ?>">
                                        <label class="form-check-label" for="edit_role_check_<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?> <small class="text-muted"><?php echo '(' . htmlspecialchars($role['slug']) . ')'; ?></small></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="form-text text-muted">Edite os papéis deste usuário. O papel Super Admin não aparece aqui.</small>
                        </div>
                        <?php endif; ?>

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
    const assocClienteDiv = document.getElementById('assoc_cliente_div');
    const assocContadorDiv = document.getElementById('assoc_contador_div');
    const acessoLancamentosCheckbox = document.getElementById('acesso_lancamentos');

    // Attach listeners to role checkboxes to show assoc blocks when role 'cliente' or 'contador' is selected
    (function attachRoleListeners() {
        try {
            // role checkboxes in create form have ids like 'role_check_{id}'
            const roleBoxes = Array.from(document.querySelectorAll('[id^="role_check_"]'));
            roleBoxes.forEach(cb => cb.addEventListener('change', function() { toggleUsuarioCampos(''); }));
            // run once on load
            toggleUsuarioCampos('');
        } catch (e) {
            // ignore
        }
    })();

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
        
        // Tipo agora derivado por papéis; o modal irá marcar papéis e ajustar blocos abaixo
        const isSuper = button.getAttribute('data-is-super') === '1';

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

        // After roles are pre-checked, display assoc blocks based on presence of 'cliente' or 'contador' role
        const userRolesJson = button.getAttribute('data-user-roles') || '[]';
        let userRoles = [];
        try { userRoles = JSON.parse(userRolesJson); } catch(e) { userRoles = []; }
        // If cliente role selected, show client assoc and set selected client and acesso_lancamentos
        const clienteRoleId = <?php echo json_encode($role_id_cliente); ?>;
        const contadorRoleId = <?php echo json_encode($role_id_contador); ?>;
        if (userRoles.includes(clienteRoleId)) {
            editAssocClienteDiv.style.display = 'block';
            editIdClienteAssociadoSelect.value = id_cliente_associado;
            editAcessoLancamentosCheckbox.checked = (acesso_lancamentos == 1);
        }
        if (userRoles.includes(contadorRoleId)) {
            editAssocContadorDiv.style.display = 'block';
            // check associated clients later when we pre-check checkboxes
        }

        // Set ativo state (aplica para qualquer tipo)
        editAtivoCheckbox.checked = (ativo == 1);
        // Se for Super Admin, desabilita todo o formulário de edição para evitar qualquer alteração
        if (isSuper) {
            // Disable inputs and show a small notice
            const formElements = modalEditarUsuario.querySelectorAll('input, select, button');
            formElements.forEach(el => {
                // keep the modal close button enabled
                if (el.getAttribute('data-bs-dismiss') === 'modal') return;
                el.disabled = true;
            });
            // Show notice at top of modal
            let notice = modalEditarUsuario.querySelector('#super_admin_notice');
            if (!notice) {
                notice = document.createElement('div');
                notice.id = 'super_admin_notice';
                notice.className = 'alert alert-dark mb-3';
                notice.textContent = 'Conta Super Admin — Edição desabilitada por segurança.';
                modalEditarUsuario.querySelector('.modal-body').insertBefore(notice, modalEditarUsuario.querySelector('.modal-body').firstChild);
            }
        } else {
            // Ensure fields are enabled for non-super users
            const formElements = modalEditarUsuario.querySelectorAll('input, select, button');
            formElements.forEach(el => el.disabled = false);
            // Re-apply certain protections (cannot disable own user)
            if (id == '<?php echo $_SESSION['user_id']; ?>') {
                editAtivoCheckbox.checked = true;
                editAtivoCheckbox.disabled = true;
            }
            const existingNotice = modalEditarUsuario.querySelector('#super_admin_notice');
            if (existingNotice) existingNotice.remove();
        }
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

<script>
// Marca papéis atribuídos ao abrir o modal (já populado via data-user-roles)
document.addEventListener('DOMContentLoaded', function () {
    const modalEditarUsuario = document.getElementById('modalEditarUsuario');
    modalEditarUsuario.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const userRolesJson = button.getAttribute('data-user-roles') || '[]';
        let userRoles = [];
        try { userRoles = JSON.parse(userRolesJson); } catch(e) { userRoles = []; }

        // Uncheck all edit role checkboxes then check only those belonging to user
        const roleCheckboxes = modalEditarUsuario.querySelectorAll('.edit-role-checkbox');
        roleCheckboxes.forEach(cb => cb.checked = false);
        userRoles.forEach(rid => {
            const cb = modalEditarUsuario.querySelector('#edit_role_check_' + rid);
            if (cb) cb.checked = true;
        });
    });
});
</script>

<!-- Expor role ids e garantir preenchimento de tipo_usuario antes do envio -->
<script>
window.__ROLE_ID_CLIENTE__ = <?php echo json_encode($role_id_cliente); ?>;
window.__ROLE_ID_CONTADOR__ = <?php echo json_encode($role_id_contador); ?>;
window.__ROLE_ID_ADMIN__ = <?php echo json_encode($role_id_admin); ?>;

document.addEventListener('DOMContentLoaded', function () {
    const formNovo = document.getElementById('formNovoUsuario');
    if (formNovo) {
        formNovo.addEventListener('submit', function () {
            try {
                const clienteId = window.__ROLE_ID_CLIENTE__ || 0;
                const contadorId = window.__ROLE_ID_CONTADOR__ || 0;
                const adminId = window.__ROLE_ID_ADMIN__ || 0;
                let tipo = '';
                if (adminId) {
                    const a = document.getElementById('role_check_' + adminId);
                    if (a && a.checked) tipo = 'admin';
                }
                if (!tipo && contadorId) {
                    const c = document.getElementById('role_check_' + contadorId);
                    if (c && c.checked) tipo = 'contador';
                }
                if (!tipo && clienteId) {
                    const cl = document.getElementById('role_check_' + clienteId);
                    if (cl && cl.checked) tipo = 'cliente';
                }
                const tipoEl = document.getElementById('tipo_usuario'); if (tipoEl) tipoEl.value = tipo;
            } catch (e) {}
        });
    }

    const formEdit = document.getElementById('formEditarUsuario');
    if (formEdit) {
        formEdit.addEventListener('submit', function () {
            try {
                const clienteId = window.__ROLE_ID_CLIENTE__ || 0;
                const contadorId = window.__ROLE_ID_CONTADOR__ || 0;
                const adminId = window.__ROLE_ID_ADMIN__ || 0;
                let tipo = '';
                if (adminId) {
                    const a = document.getElementById('edit_role_check_' + adminId);
                    if (a && a.checked) tipo = 'admin';
                }
                if (!tipo && contadorId) {
                    const c = document.getElementById('edit_role_check_' + contadorId);
                    if (c && c.checked) tipo = 'contador';
                }
                if (!tipo && clienteId) {
                    const cl = document.getElementById('edit_role_check_' + clienteId);
                    if (cl && cl.checked) tipo = 'cliente';
                }
                const editTipoEl = document.getElementById('edit_tipo_usuario'); if (editTipoEl) editTipoEl.value = tipo;
            } catch (e) {}
        });
    }
});
</script>