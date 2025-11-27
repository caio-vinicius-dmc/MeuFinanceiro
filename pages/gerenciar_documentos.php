<?php
// pages/gerenciar_documentos.php
// Permissão: visualizar_documentos ou acesso administrativo
if (!isAdmin()) {
    if (function_exists('current_user_has_permission') && current_user_has_permission('visualizar_documentos')) {
        // permitido via RBAC
    } else {
        $_SESSION['error_message'] = 'Apenas administradores ou usuários com permissão podem acessar esta página.';
        header('Location: index.php?page=dashboard');
        exit;
    }
}

$doc_templates = getDocumentTemplates();

// Fetch users for association (inclui cliente associado quando houver)
$stmt = $pdo->query('SELECT u.id, u.nome, u.email, u.id_cliente_associado, c.nome_responsavel AS cliente_nome FROM usuarios u LEFT JOIN clientes c ON u.id_cliente_associado = c.id ORDER BY u.nome ASC');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch root folders with associated users (if pivot exists). Fallback to owner_user_id if pivot missing.
    try {
    // treat parent_id = 0 as root as well (some imports may have used 0 instead of NULL)
    $sql = "SELECT p.*, GROUP_CONCAT(DISTINCT uu.nome SEPARATOR ', ') as associados, p.created_at
            FROM documentos_pastas p
            LEFT JOIN documentos_pastas_usuarios dpu ON p.id = dpu.pasta_id
            LEFT JOIN usuarios uu ON dpu.user_id = uu.id
            WHERE (p.parent_id IS NULL OR p.parent_id = 0)
            GROUP BY p.id
            ORDER BY p.nome ASC";
    $stmt = $pdo->query($sql);
    $pastas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // fallback: also accept parent_id = 0
    $stmt = $pdo->query('SELECT p.*, u.nome as owner_nome FROM documentos_pastas p LEFT JOIN usuarios u ON p.owner_user_id = u.id WHERE (p.parent_id IS NULL OR p.parent_id = 0) ORDER BY p.nome ASC');
    $pastas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch pending files queue
$stmt = $pdo->query("SELECT a.*, p.nome as pasta_nome, u.nome as enviado_por_nome FROM documentos_arquivos a LEFT JOIN documentos_pastas p ON a.pasta_id = p.id LEFT JOIN usuarios u ON a.enviado_por_user_id = u.id WHERE a.status = 'pending' ORDER BY a.criado_em ASC");
$pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all subfolders (those with parent_id not null) with their parent name and owner
try {
    // consider parent_id = 0 as root; subpastas are those with parent_id not null and not 0
    $sqlSub = "SELECT sp.*, p.nome as parent_nome, u.nome as owner_nome FROM documentos_pastas sp LEFT JOIN documentos_pastas p ON sp.parent_id = p.id LEFT JOIN usuarios u ON sp.owner_user_id = u.id WHERE (sp.parent_id IS NOT NULL AND sp.parent_id != 0) ORDER BY p.nome ASC, sp.nome ASC";
    $stmt = $pdo->query($sqlSub);
    $subpastas_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $subpastas_all = [];
}

// Fetch all folders (roots + subfolders) to populate parent selects
try {
    $stmt = $pdo->query('SELECT id, nome, parent_id FROM documentos_pastas ORDER BY nome ASC');
    $all_pastas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_pastas = $pastas; // fallback to roots only
}
?>

<div class="container-fluid my-3 doc-page p-3">
    <?php render_page_title('Gerenciar Documentos', 'Crie pastas, associe usuários e aprove arquivos enviados.', 'bi-folder2-open'); ?>
    <style>
        /* Estilos locais para melhorar aparência da página de documentos (cards full-width e menor altura) */
        .doc-page { padding-left: 0.5rem; padding-right: 0.5rem; }
        .doc-page .card { border-radius: 6px; box-shadow: 0 1px 6px rgba(0,0,0,0.06); width: 100%; display: flex; flex-direction: column; }
        .doc-page .card .card-header h5 { font-weight: 600; margin: 0; }
        /* reduzir paddings para cards e limitar altura das áreas roláveis */
        .doc-page .card .card-body { padding: 0.75rem; flex: 1 1 auto; overflow: hidden; }
        .doc-page .table-responsive { max-height: 280px; overflow: auto; }
        .doc-page .user-item { padding: 3px 0; }
        /* botões um pouco menores e compactos */
        .doc-page .btn { min-width: 5.4rem; }
        /* reduzir espaço entre cards */
        .doc-page .card + .card { margin-top: 0.6rem; }
        /* ensure two-column cards have equal height */
        .doc-page .row.gx-3 > .col-md-6 { display: flex; }
        .doc-page .row.gx-3 > .col-md-6 > .card { flex: 1; }
        /* small screens: let cards stack naturally */
        @media (max-width: 767.98px) {
            .doc-page .btn { min-width: unset; }
            .doc-page .table-responsive { max-height: 220px; }
        }
    </style>
    <div class="row gx-3">
        <!-- Debug removido: apenas exibido anteriormente para administradores durante desenvolvimento -->
            <!-- Modal: Criar Pasta Raiz -->
            <div class="modal fade" id="createPastaModal" tabindex="-1" aria-labelledby="createPastaModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="process/documentos_handler.php">
                            <input type="hidden" name="action" value="criar_pasta_raiz">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createPastaModalLabel">Criar Pasta Raiz</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Nome da Pasta</label>
                                    <input type="text" name="nome" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Associar usuários (opcional)</label>
                                    <input type="text" class="form-control pasta-user-search" placeholder="Buscar usuário para adicionar">
                                    <div id="user_checklist" class="border rounded p-2 mt-2" style="max-height:220px; overflow:auto;">
                                        <?php foreach ($users as $u): ?>
                                            <div class="form-check user-item" data-name="<?php echo htmlspecialchars(strtolower($u['nome'] . ' ' . ($u['cliente_nome'] ?? ''))); ?>">
                                                <input class="form-check-input" type="checkbox" name="user_ids[]" value="<?php echo $u['id']; ?>" id="user_check_<?php echo $u['id']; ?>">
                                                <label class="form-check-label" for="user_check_<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nome'] . (!empty($u['cliente_nome']) ? ' — ' . $u['cliente_nome'] : '')); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                <button type="submit" class="btn btn-primary">Criar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal: Criar Subpasta -->
            <div class="modal fade" id="createSubpastaModal" tabindex="-1" aria-labelledby="createSubpastaModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="process/documentos_handler.php">
                            <input type="hidden" name="action" value="criar_subpasta">
                            <div class="modal-header">
                                <h5 class="modal-title" id="createSubpastaModalLabel">Criar Subpasta</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Pasta Pai</label>
                                    <select name="parent_id" id="create_sub_parent" class="form-select" required>
                                        <option value="">-- Escolher pasta raiz --</option>
                                        <?php foreach ($all_pastas as $p): ?>
                                            <?php if ($p['parent_id'] === null || $p['parent_id'] == 0 || $p['parent_id'] === ''): ?>
                                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nome da Subpasta</label>
                                    <input type="text" name="nome" class="form-control" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                <button type="submit" class="btn btn-primary">Criar Subpasta</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
                <?php $pastas_count = count($pastas); $subpastas_count = count($subpastas_all); ?>
                <div class="row">
                    <div class="col-md-6 col-12">
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Pastas Raiz <span class="badge bg-info ms-2"><?php echo $pastas_count; ?></span></h5>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createPastaModal">Nova pasta raiz</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="pastas_table" class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Associações</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pastas_table_body">
                                            <?php if (empty($pastas)): ?>
                                                <tr><td colspan="5" class="text-muted">Nenhuma pasta raiz encontrada.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($pastas as $p): ?>
                                                <?php $ass = getUsuariosAssociadosPasta($p['id']); $ass_ids = array_column($ass, 'id'); ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($p['nome']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo count($ass_ids); ?> associados</span></td>
                                            
                                                    <td>
                                                        <?php $canModify = isAdmin() || (function_exists('current_user_has_permission') && current_user_has_permission('gerenciar_documentos')); ?>
                                                        <?php if ($canModify): ?>
                                                            <button type="button" class="btn btn-sm btn-primary ms-1 pasta-actions-btn" 
                                                                data-pasta-id="<?php echo $p['id']; ?>"
                                                                data-pasta-nome="<?php echo htmlspecialchars($p['nome'], ENT_QUOTES); ?>"
                                                                data-pasta-owner="<?php echo intval($p['owner_user_id'] ?? 0); ?>"
                                                                data-pasta-ass="<?php echo implode(',', $ass_ids); ?>"
                                                            >Ações</button>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sem permissão</span>
                                                        <?php endif; ?>
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

                    <div class="col-md-6 col-12">
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Subpastas <span class="badge bg-info ms-2"><?php echo $subpastas_count; ?></span></h5>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createSubpastaModal">Nova subpasta</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="subpastas_table" class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nome</th>
                                                <th>Pai</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="subpastas_table_body">
                                            <?php if (empty($subpastas_all)): ?>
                                                <tr><td colspan="5" class="text-muted">Nenhuma subpasta encontrada.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($subpastas_all as $sp): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sp['nome']); ?></td>
                                                    <td><?php echo htmlspecialchars($sp['parent_nome'] ?? '-'); ?></td>
                                                    
                                                    <td>
                                                        <?php $canModify = isAdmin() || (function_exists('current_user_has_permission') && current_user_has_permission('gerenciar_documentos')); ?>
                                                        <?php if ($canModify): ?>
                                                            <div style="display:inline-block; vertical-align:middle; width:220px;">
                                                                <!-- Subpastas não mostram associados nesta listagem -->
                                                            </div>
                                                            <button type="button" class="btn btn-sm btn-primary ms-1 pasta-actions-btn"
                                                                data-pasta-id="<?php echo $sp['id']; ?>"
                                                                data-pasta-nome="<?php echo htmlspecialchars($sp['nome'], ENT_QUOTES); ?>"
                                                                data-pasta-owner="<?php echo intval($sp['owner_user_id'] ?? 0); ?>"
                                                            >Ações</button>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sem permissão</span>
                                                        <?php endif; ?>
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

                <div class="row">
                    <div class="col-12">
                        <!-- Fila de Aprovação (Arquivos Pendentes) -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Fila de Aprovação (Arquivos Pendentes)</h5>
                            </div>
                            <div class="card-body">
                    <?php if (empty($pendentes)): ?>
                        <p class="text-muted">Nenhum arquivo pendente.</p>
                    <?php else: ?>
                        <div id="pending_controls" class="mb-2 d-flex justify-content-end" style="gap:.5rem;">
                            <button id="clear_pending_filter" class="btn btn-sm btn-outline-secondary" style="display:none;">Mostrar todas</button>
                        </div>
                        <div class="table-responsive" id="pending_table_wrapper">
                            <table class="table table-hover" id="pending_table">
                                <thead>
                                    <tr>
                                        <th>Arquivo</th>
                                        <th>Pasta</th>
                                        <th>Enviado por</th>
                                        <th>Enviado em</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendentes as $a): ?>
                                        <tr data-pasta-id="<?php echo intval($a['pasta_id'] ?? 0); ?>" data-arquivo-id="<?php echo intval($a['id']); ?>">
                                            <td><?php echo htmlspecialchars($a['nome_original']); ?></td>
                                            <td><?php echo htmlspecialchars($a['pasta_nome'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($a['enviado_por_nome'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($a['criado_em']); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary" href="<?php echo base_url('process/serve_documento.php?id=' . $a['id']); ?>" target="_blank">Abrir</a>
                                                <form style="display:inline" method="POST" action="process/documentos_handler.php">
                                                    <input type="hidden" name="action" value="aprovar_arquivo">
                                                    <input type="hidden" name="arquivo_id" value="<?php echo $a['id']; ?>">
                                                    <input type="hidden" name="acao" value="aprovar">
                                                    <button class="btn btn-sm btn-success">Aprovar</button>
                                                </form>
                                                <form style="display:inline" method="POST" action="process/documentos_handler.php">
                                                    <input type="hidden" name="action" value="aprovar_arquivo">
                                                    <input type="hidden" name="arquivo_id" value="<?php echo $a['id']; ?>">
                                                    <input type="hidden" name="acao" value="reprovar">
                                                    <button class="btn btn-sm btn-danger">Reprovar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            <!-- Modal de Edição de Pasta -->
            <div class="modal fade" id="editPastaModal" tabindex="-1" aria-labelledby="editPastaModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="process/documentos_handler.php">
                                <input type="hidden" name="action" value="editar_pasta">
                                <input type="hidden" name="pasta_id" id="edit_pasta_id" value="">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editPastaModalLabel">Editar Pasta</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nome da Pasta</label>
                                        <input type="text" name="nome" id="edit_pasta_nome" class="form-control" required>
                                    </div>
                                    <div class="mb-3" id="edit_pasta_parent_wrapper">
                                        <label class="form-label">Pasta Pai (ou vazio)</label>
                                        <select name="parent_id" id="edit_pasta_parent" class="form-select">
                                            <option value="">-- Nenhuma (raiz) --</option>
                                            <?php foreach ($all_pastas as $p): ?>
                                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                </div>
                            </form>
                        </div> <!-- modal-content -->
                    </div> <!-- modal-dialog -->
                    </div> <!-- modal -->

                <!-- Modal de Ações da Pasta (dinâmico) -->
                <div class="modal fade" id="pastaActionsModal" tabindex="-1" aria-labelledby="pastaActionsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="pastaActionsModalLabel">Ações</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">Carregando...</div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            </div>
                        </div>
                    </div>
                </div>

    
        </div>
    </div>
</div>
                <!-- Toast container (global feedback) -->
                <div class="toast-container position-fixed bottom-0 end-0 p-3" id="docToastContainer"></div>
                <script>
                // pass pasta tree to JS for client-side descendant checks
                window.DOC_PASTAS = <?php echo json_encode(array_values($all_pastas)); ?>;
                // provide URLs required by external helpers
                window.DOC_SEARCH_USERS_URL = '<?php echo base_url("process/search_users.php"); ?>';
                window.DOC_DOCUMENTS_HANDLER = '<?php echo base_url("process/documentos_handler.php"); ?>';
                window.DOC_GET_PASTA_ASSOCIADOS = '<?php echo base_url("process/get_pasta_associados.php"); ?>';
                </script>
                <script src="<?php echo base_url('js/gerenciar_documentos_helpers.js'); ?>"></script>
                <script>
                // bridge shims so existing inline code can call the helpers by old names
                window.attachAjaxUserAutocomplete = function (el) { if (window.gd && gd.attachAjaxUserAutocomplete) return gd.attachAjaxUserAutocomplete(el); };
                window.showToast = function (msg, type) { if (window.gd && gd.showToast) return gd.showToast(msg, type); };
                window.insertPastaRow = function (p, isRoot) { if (window.gd && gd.insertPastaRow) return gd.insertPastaRow(p, isRoot); };
                window.updatePastaCounts = function () { if (window.gd && gd.updatePastaCounts) return gd.updatePastaCounts(); };
                // Busca em tempo real para checklist de usuários (página de Gerenciar Documentos)
                document.addEventListener('DOMContentLoaded', function () {
                    // Global user checklist local filter
                    var userSearch = document.getElementById('user_search');
                    if (userSearch) {
                        userSearch.addEventListener('input', function () {
                            var q = this.value.trim().toLowerCase();
                            var items = document.querySelectorAll('#user_checklist .user-item');
                            items.forEach(function (it) {
                                var name = it.getAttribute('data-name') || '';
                                it.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
                            });
                        });
                    }

                    // Per-pasta search boxes (local filter) and AJAX select helper
                    var pastaSearchBoxes = document.querySelectorAll('.pasta-user-search');
                    pastaSearchBoxes.forEach(function (box) {
                        box.addEventListener('input', function () {
                            var q = this.value.trim().toLowerCase();
                            var list = this.parentElement.querySelector('.pasta-user-list');
                            if (!list) return;
                            var items = list.querySelectorAll('.pasta-user-item');
                            items.forEach(function (it) {
                                var name = it.getAttribute('data-name') || '';
                                it.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
                            });
                        });
                        // Add AJAX dropdown for adding users to this pasta list
                        attachAjaxUserAutocomplete(box);
                    });

                    // Attach modal behavior for edit buttons
                    var editModal = document.getElementById('editPastaModal');
                    // Ensure modals are direct children of body to avoid stacking context issues
                    try {
                        var am = document.getElementById('pastaActionsModal'); if (am && am.parentElement !== document.body) document.body.appendChild(am);
                        var em = document.getElementById('editPastaModal'); if (em && em.parentElement !== document.body) document.body.appendChild(em);
                        var cm = document.getElementById('createPastaModal'); if (cm && cm.parentElement !== document.body) document.body.appendChild(cm);
                        var sm = document.getElementById('createSubpastaModal'); if (sm && sm.parentElement !== document.body) document.body.appendChild(sm);
                    } catch (e) { /* ignore */ }
                    if (editModal) {
                        editModal.addEventListener('show.bs.modal', function (event) {
                            var button = event.relatedTarget;
                            if (!button || !button.getAttribute) {
                                try { console.debug('editPastaModal shown without relatedTarget, skipping auto-fill'); } catch (e) {}
                                return;
                            }
                            var pastaId = button.getAttribute('data-pasta-id');
                            var pastaNome = button.getAttribute('data-pasta-nome');
                            document.getElementById('edit_pasta_id').value = pastaId;
                            document.getElementById('edit_pasta_nome').value = pastaNome;
                            // ensure parent select does not allow selecting self
                            var sel = document.getElementById('edit_pasta_parent');
                            var wrapper = document.getElementById('edit_pasta_parent_wrapper');
                            if (sel) {
                                // compute descendants and disable them + self
                                var descendants = (function getDescendants(id){
                                    var map = {};
                                    (window.DOC_PASTAS || []).forEach(function(p){ map[p.id] = p.parent_id; });
                                    var res = [];
                                    function visit(cur){
                                        (window.DOC_PASTAS || []).forEach(function(pp){ if (pp.parent_id == cur) { res.push(pp.id); visit(pp.id); } });
                                    }
                                    visit(id);
                                    return res;
                                })(pastaId);
                                for (var i = 0; i < sel.options.length; i++) {
                                    var val = sel.options[i].value;
                                    sel.options[i].disabled = (String(val) === String(pastaId) || descendants.indexOf(parseInt(val)) !== -1);
                                }
                                // determine if this pasta is a root (no parent) and hide wrapper if so
                                try {
                                    var found = (window.DOC_PASTAS || []).find(function(pp){ return String(pp.id) === String(pastaId); });
                                    var isRoot = (found && (found.parent_id === null || found.parent_id == 0 || found.parent_id === '')) || (!found && (pastaId === null || pastaId == 0 || pastaId === ''));
                                    if (wrapper) wrapper.style.display = isRoot ? 'none' : '';
                                    if (!isRoot) {
                                        var parentVal = (found && (found.parent_id !== null && found.parent_id != 0)) ? String(found.parent_id) : '';
                                        sel.value = parentVal;
                                    } else {
                                        sel.value = '';
                                    }
                                } catch (e) { if (wrapper) wrapper.style.display = ''; }
                            }
                        });

                        // intercept edit form submission to use AJAX
                        var editForm = editModal.querySelector('form');
                        if (editForm) {
                            editForm.addEventListener('submit', function (ev) {
                                ev.preventDefault();
                                var fd = new FormData(editForm);
                                fd.append('ajax', '1');
                                var fetchUrl = (typeof window.DOC_DOCUMENTS_HANDLER === 'string' && window.DOC_DOCUMENTS_HANDLER) ? window.DOC_DOCUMENTS_HANDLER : 'process/documentos_handler.php';
                                try { console.debug('Editing pasta via', fetchUrl, typeof fetchUrl, editForm); } catch(e) {}
                                fetch(fetchUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (json) {
                                    if (!json.ok) { showToast(json.error || 'Erro ao editar pasta', 'danger'); return; }
                                    // receive full pasta info
                                    var pasta = json.pasta || null;
                                    if (!pasta) { showToast('Resposta inválida do servidor', 'danger'); return; }

                                    // find any existing button for this pasta (may be in root or sub table)
                                    var existingBtn = document.querySelector('[data-pasta-id="' + pasta.id + '"]');
                                    var existingTr = existingBtn ? existingBtn.closest('tr') : null;

                                    // helper to remove placeholder row if exists
                                    function removeEmptyRow(tbody) {
                                        var empty = tbody.querySelector('tr td.text-muted');
                                        if (empty) { tbody.innerHTML = ''; }
                                    }

                                    // build a new tr for the target table using same structure as insertPastaRow
                                    function buildRowForPasta(p, isRoot) {
                                        var tr = document.createElement('tr');
                                        var tdNome = document.createElement('td'); tdNome.textContent = p.nome || '';
                                            if (isRoot) {
                                            var tdAssoc = document.createElement('td'); tdAssoc.innerHTML = '<span class="badge bg-secondary">0 associados</span>';
                                            var tdAcoes = document.createElement('td');
                                            var btn = document.createElement('button'); btn.className = 'btn btn-sm btn-primary ms-1 pasta-actions-btn'; btn.setAttribute('type','button');
                                            btn.setAttribute('data-pasta-id', p.id); btn.setAttribute('data-pasta-nome', p.nome); btn.setAttribute('data-pasta-owner', p.owner_user_id || 0);
                                            btn.textContent = 'Ações'; tdAcoes.appendChild(btn);
                                            tr.appendChild(tdNome); tr.appendChild(tdAssoc); tr.appendChild(tdAcoes);
                                        } else {
                                            var tdPai = document.createElement('td'); tdPai.textContent = p.parent_nome || '-';
                                            var tdAcoes = document.createElement('td');
                                            var btn = document.createElement('button'); btn.className = 'btn btn-sm btn-primary ms-1 pasta-actions-btn'; btn.setAttribute('type','button');
                                            btn.setAttribute('data-pasta-id', p.id); btn.setAttribute('data-pasta-nome', p.nome); btn.setAttribute('data-pasta-owner', p.owner_user_id || 0);
                                            btn.textContent = 'Ações'; tdAcoes.appendChild(btn);
                                            tr.appendChild(tdNome); tr.appendChild(tdPai); tr.appendChild(tdAcoes);
                                        }
                                        return tr;
                                    }

                                    // determine target table
                                    var targetIsRoot = (pasta.parent_id === null || pasta.parent_id === '' || pasta.parent_id == 0);

                                    if (existingTr) {
                                        // if location must change, remove and recreate in target
                                        var wasInRoot = (existingTr.parentElement && existingTr.parentElement.id === 'pastas_table_body');
                                        if ((wasInRoot && !targetIsRoot) || (!wasInRoot && targetIsRoot)) {
                                            // remove old
                                            existingTr.remove();
                                            // insert into new
                                            var newTbody = document.getElementById(targetIsRoot ? 'pastas_table_body' : 'subpastas_table_body');
                                            removeEmptyRow(newTbody);
                                            var newRow = buildRowForPasta(pasta, targetIsRoot);
                                            newTbody.insertBefore(newRow, newTbody.firstChild);
                                        } else {
                                            // same table: just update name and parent cell if sub
                                            var nameCell = existingTr.querySelector('td');
                                            if (nameCell) nameCell.textContent = pasta.nome;
                                            if (!targetIsRoot) {
                                                var parentCell = existingTr.querySelectorAll('td')[1];
                                                if (parentCell) parentCell.textContent = pasta.parent_nome || '-';
                                            }
                                        }
                                    } else {
                                        // not found (maybe placeholder was present), just insert
                                        var newTbody = document.getElementById(targetIsRoot ? 'pastas_table_body' : 'subpastas_table_body');
                                        removeEmptyRow(newTbody);
                                        var newRow = buildRowForPasta(pasta, targetIsRoot);
                                        newTbody.insertBefore(newRow, newTbody.firstChild);
                                    }

                                    // update client-side map
                                    window.DOC_PASTAS = window.DOC_PASTAS || [];
                                    var found = false;
                                    for (var i = 0; i < window.DOC_PASTAS.length; i++) {
                                        if (parseInt(window.DOC_PASTAS[i].id) === parseInt(pasta.id)) { window.DOC_PASTAS[i].nome = pasta.nome; window.DOC_PASTAS[i].parent_id = pasta.parent_id; found = true; break; }
                                    }
                                    if (!found) window.DOC_PASTAS.push({ id: parseInt(pasta.id), nome: pasta.nome, parent_id: pasta.parent_id });

                                    // update counts
                                    function updateCounts() {
                                        var roots = document.querySelectorAll('#pastas_table_body button[data-pasta-id]').length;
                                        var subs = document.querySelectorAll('#subpastas_table_body button[data-pasta-id]').length;
                                        var rootBadge = document.querySelector('div.card.mb-3 .card-header .badge');
                                        var subBadge = document.querySelector('div.card + div.card .card-header .badge');
                                        if (rootBadge) rootBadge.textContent = roots;
                                        if (subBadge) subBadge.textContent = subs;
                                    }
                                    updateCounts();

                                    showToast('Pasta atualizada', 'success');
                                    bootstrap.Modal.getInstance(editModal).hide();
                                }).catch(function (e) { console.error(e); alert('Erro ao editar pasta'); });
                            });
                        }
                    }

                    // Global AJAX autocomplete for the create form (and pasta user-search boxes are handled above)
                    // No global #user_search element is required; per-modal inputs have class 'pasta-user-search'

                    // small helper to update root/sub counts (used across handlers)
                    function updatePastaCounts() {
                        var roots = document.querySelectorAll('#pastas_table_body button[data-pasta-id]').length;
                        var subs = document.querySelectorAll('#subpastas_table_body button[data-pasta-id]').length;
                        var rootBadge = document.querySelector('#pastas_table').closest('.card').querySelector('.card-header .badge');
                        var subBadge = document.querySelector('#subpastas_table').closest('.card').querySelector('.card-header .badge');
                        if (rootBadge) rootBadge.textContent = roots;
                        if (subBadge) subBadge.textContent = subs;
                    }

                    // Helper to insert a new pasta row into the correct table (root or sub)
                    function insertPastaRow(pasta, isRoot) {
                        try {
                            var tbody = document.getElementById(isRoot ? 'pastas_table_body' : 'subpastas_table_body');
                            if (!tbody) return;
                            var tr = document.createElement('tr');
                            var tdNome = document.createElement('td'); tdNome.textContent = pasta.nome || '';
                            var tdPai = document.createElement('td'); tdPai.textContent = pasta.parent_nome || '-';
                            var tdAcoes = document.createElement('td');
                            // build actions cell similar to server-rendered rows
                            var assocCountDiv = document.createElement('div'); assocCountDiv.style.display = 'inline-block'; assocCountDiv.style.verticalAlign = 'middle'; assocCountDiv.style.width = '220px';
                            var small = document.createElement('div'); small.className = 'small text-muted'; small.textContent = 'Associados: ' + (pasta.ass_count || 0); assocCountDiv.appendChild(small);
                            tdAcoes.appendChild(assocCountDiv);
                            var btn = document.createElement('button'); btn.className = 'btn btn-sm btn-primary ms-1 pasta-actions-btn'; btn.setAttribute('type','button');
                            btn.setAttribute('data-pasta-id', pasta.id); btn.setAttribute('data-pasta-nome', pasta.nome); btn.setAttribute('data-pasta-owner', pasta.owner_user_id || 0); btn.setAttribute('data-pasta-ass', (pasta.ass_ids || []).join(',')); btn.textContent = 'Ações';
                            tdAcoes.appendChild(btn);

                            if (isRoot) {
                                // root table columns: Nome, Associações, Ações
                                tr.appendChild(tdNome);
                                var tdAssoc = document.createElement('td'); tdAssoc.appendChild(assocCountDiv.cloneNode(true)); tr.appendChild(tdAssoc);
                                tr.appendChild(tdAcoes);
                                tbody.insertBefore(tr, tbody.firstChild);
                            } else {
                                // sub table columns: Nome, Pai, Ações
                                tr.appendChild(tdNome); tr.appendChild(tdPai); tr.appendChild(tdAcoes);
                                tbody.insertBefore(tr, tbody.firstChild);
                            }

                            // ensure new option appears in parent selects
                            var selects = document.querySelectorAll('select[id^="parent_id"] , select#edit_pasta_parent , select#create_sub_parent');
                            selects.forEach(function(s){ var opt = document.createElement('option'); opt.value = pasta.id; opt.textContent = pasta.nome; s.appendChild(opt); });
                            // update client-side pasta map
                            window.DOC_PASTAS = window.DOC_PASTAS || [];
                            window.DOC_PASTAS.push({ id: parseInt(pasta.id), nome: pasta.nome, parent_id: pasta.parent_id });
                        } catch (e) { console.error('insertPastaRow error', e); }
                    }

                    // Note: create_root_form / create_sub_form handlers were removed when switching to modal-based creation.
                    // The create flows now use modal forms (#createPastaModal, #createSubpastaModal) which submit normally or via
                    // the modal form submit handlers already present. This block was removed to avoid redundant unreachable code.

                    // Actions modal: populate with folder-specific actions when shown
                    var actionsModalEl = document.getElementById('pastaActionsModal');
                    if (actionsModalEl) {
                        // builder used both for bootstrap show event and for manual click fallback
                        function buildActionsForButton(button) {
                            try {
                                console.debug('buildActionsForButton called', button);
                            } catch (e) { /* ignore if console missing */ }
                            var pastaId = button && button.getAttribute ? button.getAttribute('data-pasta-id') : null;
                            var pastaNome = button && button.getAttribute ? button.getAttribute('data-pasta-nome') : null;
                            var pastaOwner = button && button.getAttribute ? button.getAttribute('data-pasta-owner') : null;
                            // set title
                            var title = actionsModalEl.querySelector('.modal-title');
                            if (title) title.textContent = 'Ações — ' + pastaNome;
                            // populate body
                            var body = actionsModalEl.querySelector('.modal-body');
                            if (!body) return;
                            body.innerHTML = '';
                            // small visible marker so we can tell the JS ran and inserted content
                            // (info text removed) -- previously showed a JS debug line about subfolders

                            // Create a centered vertical stack to hold action buttons (same size, stacked)
                            var actionsWrapper = document.createElement('div');
                            actionsWrapper.className = 'd-flex flex-column gap-2 mx-auto';
                            actionsWrapper.style.width = '260px';
                            // Edit button (opens edit modal)
                            var editBtn = document.createElement('button');
                            editBtn.setAttribute('type','button');
                            editBtn.className = 'btn btn-warning';
                            editBtn.style.width = '100%';
                            editBtn.textContent = 'Editar';
                            editBtn.addEventListener('click', function () {
                                if (window.bootstrap && bootstrap.Modal) {
                                    var editModal = new bootstrap.Modal(document.getElementById('editPastaModal'));
                                    document.getElementById('edit_pasta_id').value = pastaId;
                                    document.getElementById('edit_pasta_nome').value = pastaNome;
                                    // disable selecting self or any descendant as parent and preselect current parent
                                    var sel = document.getElementById('edit_pasta_parent');
                                    var wrapper = document.getElementById('edit_pasta_parent_wrapper');
                                    if (sel) {
                                        // build parent map and compute descendants
                                        var map = {};
                                        (window.DOC_PASTAS || []).forEach(function(pp){ map[pp.id] = pp.parent_id; });
                                        var descendants = [];
                                        function collectDesc(id){ (window.DOC_PASTAS || []).forEach(function(pp){ if (String(pp.parent_id) === String(id) || pp.parent_id == id) { descendants.push(pp.id); collectDesc(pp.id); } }); }
                                        collectDesc(pastaId);
                                        for (var i = 0; i < sel.options.length; i++) {
                                            var val = sel.options[i].value;
                                            sel.options[i].disabled = (String(val) === String(pastaId) || descendants.indexOf(parseInt(val)) !== -1);
                                        }
                                        // hide parent select for root folders, otherwise preselect
                                        try {
                                            var found = (window.DOC_PASTAS || []).find(function(pp){ return String(pp.id) === String(pastaId); });
                                            var isRoot = (found && (found.parent_id === null || found.parent_id == 0 || found.parent_id === '')) || (!found && (pastaId === null || pastaId == 0 || pastaId === ''));
                                            if (wrapper) wrapper.style.display = isRoot ? 'none' : '';
                                            if (!isRoot) {
                                                var parentVal = (found && (found.parent_id !== null && found.parent_id != 0)) ? String(found.parent_id) : '';
                                                sel.value = parentVal;
                                            } else {
                                                sel.value = '';
                                            }
                                        } catch (e) { if (wrapper) wrapper.style.display = ''; }
                                    }
                                    editModal.show();
                                    var m = bootstrap.Modal.getInstance(actionsModalEl); if (m) m.hide();
                                }
                            });
                            actionsWrapper.appendChild(editBtn);

                            // Gerenciar associados: only for root folders (subpastas não gerenciam associados aqui)
                            try {
                                var isRoot = true;
                                var found = (window.DOC_PASTAS || []).find(function(pp){ return String(pp.id) === String(pastaId); });
                                if (found && !(found.parent_id === null || found.parent_id == 0 || found.parent_id === '')) isRoot = false;
                            } catch (e) { var isRoot = true; }
                            var assocContainer = null;
                            if (isRoot) {
                                var assocToggle = document.createElement('button');
                                assocToggle.className = 'btn btn-secondary';
                                assocToggle.setAttribute('type','button');
                                assocToggle.style.width = '100%';
                                assocToggle.textContent = 'Gerenciar associados';
                                actionsWrapper.appendChild(assocToggle);

                                assocContainer = document.createElement('div');
                                assocContainer.className = 'mt-3';
                                assocContainer.style.display = 'none';

                                assocToggle.addEventListener('click', function () {
                                    // validate pastaId before requesting
                                    if (!pastaId || pastaId === '0' || pastaId === 0) { try { showToast('Pasta inválida', 'danger'); } catch(e){}; return; }
                                    if (assocContainer.style.display === 'none') {
                                        assocContainer.style.display = '';
                                        try { console.debug('Loading associados for pasta', pastaId); } catch (e) {}
                                        loadPastaAssociadosIntoModal(pastaId, assocContainer);
                                    } else { assocContainer.style.display = 'none'; }
                                });
                            }

                            // Link para ver fila de pendências filtrada (ancora local)
                            // Só adicionar este botão para subpastas (não para pastas raiz)
                            if (!isRoot) {
                                var pendLink = document.createElement('button');
                                pendLink.className = 'btn btn-outline-primary';
                                pendLink.style.width = '100%';
                                pendLink.textContent = 'Ver pendências desta pasta';
                                pendLink.addEventListener('click', function (ev) { ev.preventDefault(); try { if (typeof window.filterPendenciasForPasta === 'function') { window.filterPendenciasForPasta(pastaId); } else { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); } } catch (e) { console.error(e); } });
                                actionsWrapper.appendChild(pendLink);
                            }

                            // Delete form (AJAX)
                            var delForm = document.createElement('form'); delForm.method = 'POST';
                            var _docHandler = (typeof window.DOC_DOCUMENTS_HANDLER === 'string' && window.DOC_DOCUMENTS_HANDLER) ? window.DOC_DOCUMENTS_HANDLER : 'process/documentos_handler.php';
                            var inAction = document.createElement('input'); inAction.type = 'hidden'; inAction.name = 'action'; inAction.value = 'deletar_pasta';
                            var inId = document.createElement('input'); inId.type = 'hidden'; inId.name = 'pasta_id'; inId.value = pastaId;
                            delForm.appendChild(inAction); delForm.appendChild(inId);
                            var delBtn = document.createElement('button'); delBtn.setAttribute('type','submit'); delBtn.className = 'btn btn-danger'; delBtn.style.width = '100%'; delBtn.textContent = 'Excluir'; delForm.appendChild(delBtn);
                            delForm.addEventListener('submit', function (ev) {
                                ev.preventDefault(); if (!confirm('Deseja realmente excluir esta pasta? As subpastas serão reatribuídas ao pai.')) return;
                                var fd = new FormData(delForm); fd.append('ajax', '1');
                                var fetchUrl = _docHandler; // use a local string, avoid relying on mutated form.action
                                try { console.debug('Deleting pasta via', fetchUrl, typeof fetchUrl, delForm); } catch(e) {}
                                fetch(fetchUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
                                    if (!r.ok) {
                                        return r.text().then(function(txt){ var msg = 'Erro servidor ao excluir: HTTP ' + r.status + ' - ' + (txt || r.statusText); showToast(msg, 'danger'); throw new Error(msg); });
                                    }
                                    return r.text().then(function(txt){ try { return JSON.parse(txt); } catch (e) { showToast('Resposta inválida do servidor ao excluir.', 'danger'); throw e; } });
                                }).then(function (json) {
                                    if (!json.ok) { showToast(json.error || 'Erro ao excluir pasta', 'danger'); return; }
                                    var deletedId = json.pasta_id;
                                    // remove deleted row if present
                                    var btn = document.querySelector('[data-pasta-id="' + deletedId + '"]'); if (btn) { var tr = btn.closest('tr'); if (tr) tr.remove(); }

                                    // process affected children returned by server (reparented subfolders)
                                    if (json.affected_children && Array.isArray(json.affected_children)) {
                                        json.affected_children.forEach(function (child) {
                                            // child should contain: id, parent_id, parent_nome, nome (optional)
                                            var childBtn = document.querySelector('[data-pasta-id="' + child.id + '"]');
                                            var targetIsRoot = (child.parent_id === null || child.parent_id == 0 || child.parent_id === '');
                                            if (childBtn) {
                                                var childTr = childBtn.closest('tr');
                                                if (childTr) {
                                                    var currentlyInRoot = childTr.parentElement && childTr.parentElement.id === 'pastas_table_body';
                                                    if ((currentlyInRoot && !targetIsRoot) || (!currentlyInRoot && targetIsRoot)) {
                                                        // move row to other table (rebuild minimally to match column structure)
                                                        var name = child.nome || child.name || childBtn.getAttribute('data-pasta-nome') || '';
                                                        // remove old row
                                                        childTr.remove();
                                                        // insert a new row in the target table using insertPastaRow
                                                        insertPastaRow({ id: child.id, nome: name, parent_id: child.parent_id, parent_nome: child.parent_nome || '', owner_nome: child.owner_nome || '-', created_at: child.created_at || '' }, targetIsRoot);
                                                    } else {
                                                        // same table: update parent cell if sub
                                                        if (!targetIsRoot) {
                                                            var parentCell = childTr.querySelectorAll('td')[1];
                                                            if (parentCell) parentCell.textContent = child.parent_nome || '-';
                                                        }
                                                    }
                                                }
                                            } else {
                                                // not present in DOM: insert
                                                insertPastaRow({ id: child.id, nome: child.nome || '', parent_id: child.parent_id, parent_nome: child.parent_nome || '', owner_nome: child.owner_nome || '-', created_at: child.created_at || '' }, targetIsRoot);
                                            }
                                            // update client-side map
                                            window.DOC_PASTAS = window.DOC_PASTAS || [];
                                            var idx = window.DOC_PASTAS.findIndex(function(p){ return parseInt(p.id) === parseInt(child.id); });
                                            if (idx !== -1) { window.DOC_PASTAS[idx].parent_id = child.parent_id; window.DOC_PASTAS[idx].nome = child.nome || window.DOC_PASTAS[idx].nome; }
                                            else { window.DOC_PASTAS.push({ id: parseInt(child.id), nome: child.nome || '', parent_id: child.parent_id }); }
                                        });
                                    }

                                    // refresh badges
                                    updatePastaCounts();

                                    if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getInstance(actionsModalEl).hide(); } else { actionsModalEl.classList.remove('show'); actionsModalEl.style.display = 'none'; }
                                }).catch(function (e) { console.error(e); showToast('Erro ao excluir pasta', 'danger'); });
                            });

                            actionsWrapper.appendChild(delForm);
                            // append the wrapper and the assocContainer below it
                            body.appendChild(actionsWrapper);
                            if (assocContainer) body.appendChild(assocContainer);
                        }

                        // store last clicked trigger as a fallback when show.bs.modal.relatedTarget
                        // is not provided by some bootstrap invocations
                        var lastActionsTrigger = null;

                        // bind to bootstrap show event (if using bootstrap). Use event.relatedTarget
                        // when available, otherwise fall back to lastActionsTrigger.
                        actionsModalEl.addEventListener('show.bs.modal', function (event) {
                            var trigger = event.relatedTarget || lastActionsTrigger;
                            try { console.debug('pastaActionsModal show.bs.modal, trigger=', trigger); } catch (e) {}
                            if (!trigger) return; // nothing to build
                            try { buildActionsForButton(trigger); } catch (e) { console.error('buildActionsForButton error', e); }
                        });

                        // clear fallback when modal hidden
                        actionsModalEl.addEventListener('hidden.bs.modal', function () { lastActionsTrigger = null; });

                        // Use event delegation so dynamically-inserted action buttons work without
                        // needing explicit rebinding. This handles both bootstrap-triggered and
                        // manual-show scenarios. We avoid duplicating the build when the button
                        // already uses data-bs-toggle="modal" (bootstrap will trigger show.bs.modal).
                        // Single delegated handler: handle clicks on pasta action triggers.
                        document.addEventListener('click', function (ev) {
                            var btn = ev.target.closest('button.pasta-actions-btn');
                            if (!btn) return;
                            ev.preventDefault();
                            lastActionsTrigger = btn;
                            try { buildActionsForButton(btn); } catch (e) { console.error('buildActionsForButton error', e); }
                            // show modal programmatically after building content
                            if (window.bootstrap && bootstrap.Modal) {
                                try { var m = new bootstrap.Modal(actionsModalEl); m.show(); } catch (e) { console.error('modal show error', e); }
                            } else {
                                actionsModalEl.style.display = 'block'; actionsModalEl.classList.add('show');
                            }
                        });
                    }

                    // The following helpers were moved to external file `js/gerenciar_documentos_helpers.js` and are
                    // exposed on window.gd (gd.attachAjaxUserAutocomplete, gd.showToast, gd.insertPastaRow, gd.updatePastaCounts).

                    // load associados for a pasta into a container inside modal (and attach AJAX submit)
                    function loadPastaAssociadosIntoModal(pastaId, container) {
                        container.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Carregando...</span></div> Carregando...';
                        var url = (window.DOC_GET_PASTA_ASSOCIADOS || 'process/get_pasta_associados.php') + '?pasta_id=' + encodeURIComponent(pastaId);
                        try { console.debug('loadPastaAssociadosIntoModal ->', url); } catch (e) {}
                        fetch(url, { credentials: 'same-origin' }).then(function(r){
                            if (!r.ok) {
                                return r.text().then(function(txt){ container.innerHTML = '<div class="text-danger">Erro ao carregar usuários: HTTP ' + r.status + ' - ' + (txt || r.statusText) + '</div>'; try { showToast('Erro ao carregar associados: HTTP ' + r.status, 'danger'); } catch(e){}; throw new Error('HTTP ' + r.status); });
                            }
                            return r.text().then(function(txt){
                                try { return JSON.parse(txt); } catch (e) {
                                    // show raw server response to help debugging (escaped)
                                    var pre = document.createElement('pre'); pre.style.whiteSpace = 'pre-wrap'; pre.style.maxHeight = '300px'; pre.style.overflow = 'auto';
                                    // escape HTML
                                    var escaped = txt.replace(/&/g, '&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                                    pre.innerHTML = escaped;
                                    container.innerHTML = '';
                                    var errDiv = document.createElement('div'); errDiv.className = 'text-danger'; errDiv.textContent = 'Resposta inválida do servidor (mostrada abaixo):';
                                    container.appendChild(errDiv);
                                    container.appendChild(pre);
                                    try { showToast('Erro: resposta inválida do servidor ao carregar associados (ver modal).', 'danger'); } catch (e) {}
                                    throw new Error('invalid-json');
                                }
                            });
                        }).then(function(json){
                            if (!json || !json.ok) { container.innerHTML = '<div class="text-danger">Erro ao carregar usuários: ' + (json && json.error ? json.error : 'Resposta inválida') + '</div>'; try { showToast('Erro ao carregar associados: ' + (json && json.error ? json.error : 'Resposta inválida'), 'danger'); } catch(e){}; return; }
                            // build form
                            var form = document.createElement('form');
                            form.method = 'POST';
                            var _docHandler = (typeof window.DOC_DOCUMENTS_HANDLER === 'string' && window.DOC_DOCUMENTS_HANDLER) ? window.DOC_DOCUMENTS_HANDLER : 'process/documentos_handler.php';
                            var inAction = document.createElement('input'); inAction.type = 'hidden'; inAction.name = 'action'; inAction.value = 'associar_pasta_usuario';
                            var inPasta = document.createElement('input'); inPasta.type = 'hidden'; inPasta.name = 'pasta_id'; inPasta.value = pastaId;
                            form.appendChild(inAction); form.appendChild(inPasta);
                            var listDiv = document.createElement('div'); listDiv.className = 'border rounded p-2';
                            json.users.forEach(function(u){
                                var d = document.createElement('div'); d.className = 'form-check';
                                var cb = document.createElement('input'); cb.className = 'form-check-input'; cb.type = 'checkbox'; cb.name = 'user_ids[]'; cb.value = u.id; cb.id = 'assoc_modal_user_' + u.id; if (u.associated) cb.checked = true;
                                var lb = document.createElement('label'); lb.className = 'form-check-label'; lb.htmlFor = cb.id; lb.innerHTML = u.nome + (u.cliente_nome ? ' <small class="text-muted">— ' + u.cliente_nome + '</small>' : '');
                                d.appendChild(cb); d.appendChild(lb); listDiv.appendChild(d);
                            });
                            form.appendChild(listDiv);
                            var saveBtn = document.createElement('button'); saveBtn.className = 'btn btn-primary btn-sm mt-2'; saveBtn.type = 'submit'; saveBtn.textContent = 'Salvar associados';
                            form.appendChild(saveBtn);
                            container.innerHTML = '';
                            container.appendChild(form);

                            // attach AJAX submit
                            form.addEventListener('submit', function (ev) {
                                ev.preventDefault();
                                var fd = new FormData(form);
                                fd.append('ajax', '1');
                                var fetchUrl = (typeof window.DOC_DOCUMENTS_HANDLER === 'string' && window.DOC_DOCUMENTS_HANDLER) ? window.DOC_DOCUMENTS_HANDLER : 'process/documentos_handler.php';
                                try { console.debug('Submitting associados to', fetchUrl, typeof fetchUrl, form); } catch(e) {}
                                fetch(fetchUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
                                    if (!r.ok) {
                                        return r.text().then(function(txt){ var msg = 'Erro servidor ao salvar associados: HTTP ' + r.status + ' - ' + (txt || r.statusText); showToast(msg, 'danger'); throw new Error(msg); });
                                    }
                                    return r.text().then(function(txt){ try { return JSON.parse(txt); } catch (e) { showToast('Resposta inválida do servidor ao salvar associados', 'danger'); throw e; } });
                                }).then(function (resp) {
                                    if (!resp.ok) { showToast(resp.error || 'Erro ao salvar associados', 'danger'); return; }
                                    // update associated count in table
                                    var btn = document.querySelector('[data-pasta-id="' + pastaId + '"]');
                                    if (btn) {
                                        var tr = btn.closest('tr');
                                        var count = resp.count || (fd.getAll('user_ids[]') ? fd.getAll('user_ids[]').length : 0);
                                        // prefer existing .badge (server-rendered) then .small.text-muted
                                        var badge = tr ? (tr.querySelector('.badge') || tr.querySelector('.small.text-muted')) : null;
                                        if (badge) {
                                            // if it's a bootstrap badge, keep succinct text; else use 'Associados: N'
                                            if (badge.classList.contains('badge')) badge.textContent = (count || 0) + ' associados';
                                            else badge.textContent = 'Associados: ' + (count || 0);
                                        } else {
                                            // fallback to previous DOM shape
                                            var containerCell = btn.parentElement.querySelector('.small.text-muted');
                                            if (containerCell) containerCell.textContent = 'Associados: ' + (count || 0);
                                        }
                                        // update data attribute
                                        btn.dataset.pastaAss = (fd.getAll('user_ids[]') || []).join(',');
                                    }
                                    // show small feedback
                                    showToast(resp.message || 'Associados atualizados', 'success');
                                }).catch(function (e) { console.error(e); showToast('Erro ao salvar associados', 'danger'); });
                            });
                        }).catch(function(e){ container.innerHTML = '<div class="text-danger">Erro ao carregar: ' + (e && e.message ? e.message : 'Ver console') + '</div>'; console.error(e); });
                    }

                    // filter pending queue by pasta id: shows only rows matching data-pasta-id and scrolls into view
                    window.filterPendenciasForPasta = function(pastaId) {
                        try {
                            var wrapper = document.getElementById('pending_table_wrapper');
                            var table = document.getElementById('pending_table');
                            if (!table || !wrapper) { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); return; }
                            var rows = table.querySelectorAll('tbody tr');
                            var found = 0;
                            // reset
                            rows.forEach(function(r){ r.classList.remove('table-success'); r.style.display = ''; });
                            if (!pastaId || pastaId === '0' || pastaId === 0) {
                                // show all
                                var clearBtn = document.getElementById('clear_pending_filter'); if (clearBtn) clearBtn.style.display = 'none';
                            } else {
                                rows.forEach(function(r){
                                    var pid = r.getAttribute('data-pasta-id') || '0';
                                    if (String(pid) !== String(pastaId)) {
                                        r.style.display = 'none';
                                    } else {
                                        r.classList.add('table-success'); found++;
                                    }
                                });
                                // show clear button
                                var clearBtn = document.getElementById('clear_pending_filter'); if (clearBtn) clearBtn.style.display = '';
                            }
                            // scroll to the pending wrapper
                            wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            // if nothing found, show a small notice near the wrapper
                            if (found === 0 && pastaId && pastaId !== '0' && pastaId !== 0) {
                                var note = document.createElement('div'); note.className = 'alert alert-info mt-2'; note.id = 'pending_filter_note'; note.textContent = 'Nenhum arquivo pendente nesta pasta.';
                                if (!document.getElementById('pending_filter_note')) wrapper.parentElement.insertBefore(note, wrapper.nextSibling);
                                setTimeout(function(){ var n = document.getElementById('pending_filter_note'); if (n) n.remove(); }, 5000);
                            }
                        } catch (e) { console.error(e); }
                    };

                    // clear filter button handler
                    var clearPendingBtn = document.getElementById('clear_pending_filter');
                    if (clearPendingBtn) {
                        clearPendingBtn.addEventListener('click', function () { window.filterPendenciasForPasta(0); });
                    }
                });
                </script>
                <!-- Personalização do email de recibo removida desta tela por solicitação -->
