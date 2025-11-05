<?php
require_once __DIR__ . '/../config/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Se for admin global, lista todas as empresas. Senão, lista as associadas.
try {
    global $pdo;
    if (isAdmin()) {
        $stmt = $pdo->query('SELECT id, nome, cnpj, created_at FROM empresas ORDER BY nome');
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $empresas = get_user_companies($user_id);
    }
    // lista de usuários para associar (apenas id e nome)
    $stmt2 = $pdo->query('SELECT id, nome, email FROM usuarios ORDER BY nome');
    $usuarios = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro ao consultar empresas: ' . $e->getMessage();
    $empresas = [];
    $usuarios = [];
}

$page_title = 'Gerenciar Empresas';
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Gerenciar Empresas</h3>
        <?php if (isAdmin()): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateEmpresa">Nova Empresa</button>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>CNPJ</th>
                            <th>Criada em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empresas as $e): ?>
                            <tr id="empresa-row-<?php echo $e['id']; ?>" data-empresa-id="<?php echo $e['id']; ?>">
                                <td><?php echo intval($e['id']); ?></td>
                                <td><?php echo htmlspecialchars($e['nome']); ?></td>
                                <td><?php echo htmlspecialchars($e['cnpj'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($e['created_at'] ?? ''); ?></td>
                                <td>
                                    <?php if (isAdmin()): ?>
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditEmpresa" 
                                            data-id="<?php echo $e['id']; ?>" data-nome="<?php echo htmlspecialchars($e['nome'], ENT_QUOTES); ?>" data-cnpj="<?php echo htmlspecialchars($e['cnpj'] ?? '', ENT_QUOTES); ?>">Editar</button>
                                        <form action="<?php echo base_url('process/empresas_handler.php'); ?>" method="post" class="d-inline empresa-delete-form" onsubmit="return confirm('Confirmar exclusão desta empresa?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="empresa_id" value="<?php echo $e['id']; ?>">
                                            <button class="btn btn-sm btn-danger">Excluir</button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalAssociar" 
                                        data-empresa-id="<?php echo $e['id']; ?>" data-empresa-nome="<?php echo htmlspecialchars($e['nome'], ENT_QUOTES); ?>">Associar Usuário</button>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalAssociados" 
                                        data-empresa-id="<?php echo $e['id']; ?>" data-empresa-nome="<?php echo htmlspecialchars($e['nome'], ENT_QUOTES); ?>">Ver Associados</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Create -->
    <div class="modal fade" id="modalCreateEmpresa" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form id="formCreateEmpresa" action="<?php echo base_url('process/empresas_handler.php'); ?>" method="post" class="modal-content">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title">Nova Empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">CNPJ</label>
                    <input name="cnpj" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Criar</button>
            </div>
        </form>
      </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="modalEditEmpresa" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form id="formEditEmpresa" action="<?php echo base_url('process/empresas_handler.php'); ?>" method="post" class="modal-content">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="empresa_id" id="edit_empresa_id">
            <div class="modal-header">
                <h5 class="modal-title">Editar Empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input id="edit_empresa_nome" name="nome" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">CNPJ</label>
                    <input id="edit_empresa_cnpj" name="cnpj" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Salvar</button>
            </div>
        </form>
      </div>
    </div>

    <!-- Modal Associar Usuário -->
    <div class="modal fade" id="modalAssociar" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <form action="<?php echo base_url('process/empresas_handler.php'); ?>" method="post" class="modal-content">
            <input type="hidden" name="action" value="associate">
            <input type="hidden" name="empresa_id" id="assoc_empresa_id">
            <div class="modal-header">
                <h5 class="modal-title">Associar Usuário à Empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Empresa</label>
                    <input id="assoc_empresa_nome" class="form-control" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Usuário</label>
                    <select name="usuario_id" class="form-select">
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nome'] . ' <' . $u['email'] . '>'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Papel</label>
                    <select name="role" class="form-select">
                        <option value="cliente">Cliente</option>
                        <option value="contador">Contador</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary">Associar</button>
            </div>
        </form>
      </div>
    </div>

    <!-- Modal Listar Associados -->
    <div class="modal fade" id="modalAssociados" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Usuários associados</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="associados-container">Carregando...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
      </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var editModal = document.getElementById('modalEditEmpresa');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(e){
            var btn = e.relatedTarget;
            var id = btn.getAttribute('data-id');
            var nome = btn.getAttribute('data-nome');
            var cnpj = btn.getAttribute('data-cnpj');
            document.getElementById('edit_empresa_id').value = id;
            document.getElementById('edit_empresa_nome').value = nome;
            document.getElementById('edit_empresa_cnpj').value = cnpj;
        });
    }

    var assocModal = document.getElementById('modalAssociar');
    if (assocModal) {
        assocModal.addEventListener('show.bs.modal', function(e){
            var btn = e.relatedTarget;
            var id = btn.getAttribute('data-empresa-id');
            var nome = btn.getAttribute('data-empresa-nome');
            document.getElementById('assoc_empresa_id').value = id;
            document.getElementById('assoc_empresa_nome').value = nome;
        });
    }

    var associadosModal = document.getElementById('modalAssociados');
    if (associadosModal) {
        function loadAssociados(empresaId, container) {
            container.innerHTML = 'Carregando...';
            fetch('<?php echo base_url('process/get_empresas_associados.php'); ?>?empresa_id=' + encodeURIComponent(empresaId))
                .then(r => r.json())
                .then(data => {
                    if (!Array.isArray(data)) { container.innerHTML = 'Erro ao carregar associados'; return; }
                    if (data.length === 0) { container.innerHTML = '<div class="alert alert-info">Nenhum usuário associado.</div>'; return; }
                    var html = '<div id="associados-msg"></div><table class="table"><thead><tr><th>Usuário</th><th>Email</th><th>Papel</th><th>Ações</th></tr></thead><tbody>';
                    data.forEach(function(u){
                        html += '<tr>' +
                            '<td>'+ escapeHtml(u.nome) +'</td>' +
                            '<td>'+ escapeHtml(u.email || '') +'</td>' +
                            '<td>'+ escapeHtml(u.role || '') +'</td>' +
                            '<td>' +
                                '<form method="post" action="<?php echo base_url('process/empresas_handler.php'); ?>" onsubmit="return confirm(\'Remover associação?\');" class="d-inline me-1 assoc-action-form">' +
                                    '<input type="hidden" name="action" value="remove_association">' +
                                    '<input type="hidden" name="empresa_id" value="'+empresaId+'">' +
                                    '<input type="hidden" name="usuario_id" value="'+u.id+'">' +
                                    '<button class="btn btn-sm btn-danger">Remover</button>' +
                                '</form>' +
                                // small inline form to change role
                                '<form method="post" action="<?php echo base_url('process/empresas_handler.php'); ?>" class="d-inline assoc-action-form">' +
                                    '<input type="hidden" name="action" value="associate">' +
                                    '<input type="hidden" name="empresa_id" value="'+empresaId+'">' +
                                    '<input type="hidden" name="usuario_id" value="'+u.id+'">' +
                                    '<select name="role" class="form-select form-select-sm d-inline-block" style="width:auto; display:inline-block; vertical-align:middle; margin-left:6px; margin-right:6px;">' +
                                        '<option value="cliente" '+(u.role==='cliente'?'selected':'')+'>Cliente</option>' +
                                        '<option value="contador" '+(u.role==='contador'?'selected':'')+'>Contador</option>' +
                                        '<option value="admin" '+(u.role==='admin'?'selected':'')+'>Admin</option>' +
                                    '</select>' +
                                    '<button class="btn btn-sm btn-outline-primary">Salvar</button>' +
                                '</form>' +
                            '</td>' +
                        '</tr>';
                    });
                    html += '</tbody></table>';
                    container.innerHTML = html;
                }).catch(function(){ container.innerHTML = 'Erro ao carregar associados'; });
        }

        associadosModal.addEventListener('show.bs.modal', function(e){
            var btn = e.relatedTarget;
            var id = btn.getAttribute('data-empresa-id');
            var nome = btn.getAttribute('data-empresa-nome');
            var container = document.getElementById('associados-container');
            loadAssociados(id, container);
        });

        // Delegation: intercept form submissions inside associados container and submit via fetch
        document.getElementById('associados-container').addEventListener('submit', function(ev){
            var form = ev.target;
            if (!form || form.tagName.toLowerCase() !== 'form') return;
            // only handle forms that post to empresas_handler
            if (!form.action || form.action.indexOf('empresas_handler.php') === -1) return;
            ev.preventDefault();
            var fd = new FormData(form);
            fd.append('ajax', '1');
            fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(r){
                return r.json();
            }).then(function(json){
                var empresaId = form.querySelector('[name="empresa_id"]').value;
                var container = document.getElementById('associados-container');
                if (json && json.success) {
                    // show small success message
                    var msgDiv = document.getElementById('associados-msg');
                    if (!msgDiv) {
                        // reload to show message area
                        loadAssociados(empresaId, container);
                        return;
                    }
                    msgDiv.innerHTML = '<div class="alert alert-success">'+escapeHtml(json.message || 'Sucesso')+'</div>';
                    setTimeout(function(){ loadAssociados(empresaId, container); }, 600);
                } else {
                    alert(json.message || 'Erro ao executar ação');
                }
            }).catch(function(){ alert('Erro ao executar ação'); });
        });
    }

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }
});

// AJAX handling for create/update/delete of empresas
document.addEventListener('DOMContentLoaded', function(){
    function showAlert(message, type) {
        var container = document.getElementById('gerenciar-empresas-alerts');
        if (!container) {
            container = document.createElement('div');
            container.id = 'gerenciar-empresas-alerts';
            container.style.position = 'fixed';
            container.style.top = '1rem';
            container.style.right = '1rem';
            container.style.zIndex = 1060;
            document.body.appendChild(container);
        }
        var div = document.createElement('div');
        div.className = 'alert alert-' + (type || 'success');
        div.style.minWidth = '220px';
        div.style.marginBottom = '8px';
        div.textContent = message;
        container.appendChild(div);
        setTimeout(function(){ div.remove(); }, 3500);
    }

    var MF_isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;

    // Create
    var formCreate = document.getElementById('formCreateEmpresa');
    if (formCreate) {
        formCreate.addEventListener('submit', function(ev){
            ev.preventDefault();
            var fd = new FormData(formCreate);
            fd.append('ajax','1');
            fetch(formCreate.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                .then(r => r.json()).then(function(json){
                    if (json && json.success && json.empresa) {
                        // append new row
                        var tbody = document.querySelector('#gerenciar-empresas table tbody');
                        if (tbody) {
                            var e = json.empresa;
                            var tr = document.createElement('tr');
                            tr.id = 'empresa-row-' + e.id;
                            tr.setAttribute('data-empresa-id', e.id);
                            var editBtnHtml = '';
                            if (MF_isAdmin) {
                                editBtnHtml = '<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditEmpresa" data-id="'+ e.id +'" data-nome="'+ (e.nome? e.nome.replace(/"/g, '&quot;') : '') +'" data-cnpj="'+ (e.cnpj? e.cnpj.replace(/"/g,'&quot;') : '') +'">Editar</button>';
                            }
                            tr.innerHTML = '<td>'+ parseInt(e.id) +'</td>' +
                                           '<td>'+ (e.nome ? escapeHtml(e.nome) : '') +'</td>' +
                                           '<td>'+ (e.cnpj ? escapeHtml(e.cnpj) : '') +'</td>' +
                                           '<td>'+ (e.created_at ? escapeHtml(e.created_at) : '') +'</td>' +
                                           '<td>' + editBtnHtml +
                                           '<form action="<?php echo base_url('process/empresas_handler.php'); ?>" method="post" class="d-inline empresa-delete-form" onsubmit="return confirm(\'Confirmar exclusão desta empresa?\');">' +
                                           '<input type="hidden" name="action" value="delete">' +
                                           '<input type="hidden" name="empresa_id" value="'+ e.id +'">' +
                                           '<button class="btn btn-sm btn-danger">Excluir</button>' +
                                           '</form>' +
                                           '<button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalAssociar" data-empresa-id="'+ e.id +'" data-empresa-nome="'+ (e.nome? escapeHtml(e.nome):'') +'">Associar Usuário</button>' +
                                           '<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalAssociados" data-empresa-id="'+ e.id +'" data-empresa-nome="'+ (e.nome? escapeHtml(e.nome):'') +'">Ver Associados</button>' +
                                           '</td>';
                            tbody.appendChild(tr);
                        }
                        showAlert(json.message || 'Empresa criada', 'success');
                        // close modal
                        var modal = bootstrap.Modal.getInstance(document.getElementById('modalCreateEmpresa'));
                        if (modal) modal.hide();
                        formCreate.reset();
                    } else {
                        showAlert((json && json.message) || 'Erro ao criar empresa', 'danger');
                    }
                }).catch(function(){ showAlert('Erro ao criar empresa', 'danger'); });
        });
    }

    // Edit
    var formEdit = document.getElementById('formEditEmpresa');
    if (formEdit) {
        formEdit.addEventListener('submit', function(ev){
            ev.preventDefault();
            var fd = new FormData(formEdit);
            fd.append('ajax','1');
            fetch(formEdit.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
                .then(r => r.json()).then(function(json){
                    if (json && json.success && json.empresa) {
                        var e = json.empresa;
                        var tr = document.getElementById('empresa-row-' + e.id);
                        if (tr) {
                            tr.children[1].textContent = e.nome || '';
                            tr.children[2].textContent = e.cnpj || '';
                        }
                        showAlert(json.message || 'Empresa atualizada', 'success');
                        var modal = bootstrap.Modal.getInstance(document.getElementById('modalEditEmpresa'));
                        if (modal) modal.hide();
                    } else {
                        showAlert((json && json.message) || 'Erro ao atualizar empresa', 'danger');
                    }
                }).catch(function(){ showAlert('Erro ao atualizar empresa', 'danger'); });
        });
    }

    // Delete (delegated)
    document.body.addEventListener('submit', function(ev){
        var form = ev.target;
        if (!form.classList || !form.classList.contains('empresa-delete-form')) return;
        ev.preventDefault();
        if (!confirm('Confirmar exclusão desta empresa?')) return;
        var fd = new FormData(form);
        fd.append('ajax','1');
        fetch(form.action, { method: 'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'} })
            .then(r => r.json()).then(function(json){
                if (json && json.success) {
                    var id = json.empresa_id || (form.querySelector('[name="empresa_id"]') ? form.querySelector('[name="empresa_id"]').value : null);
                    if (id) {
                        var tr = document.getElementById('empresa-row-' + id);
                        if (tr) tr.remove();
                    }
                    showAlert(json.message || 'Empresa excluída', 'success');
                } else {
                    showAlert((json && json.message) || 'Erro ao excluir empresa', 'danger');
                }
            }).catch(function(){ showAlert('Erro ao excluir empresa', 'danger'); });
    });

    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }
});
</script>
