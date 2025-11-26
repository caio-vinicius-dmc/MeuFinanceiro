<?php
// pages/configuracoes_documentos.php
require_once 'config/functions.php';
global $pdo;

// 1. Apenas Admin pode ver esta página
if (!isAdmin()) {
    header("Location: " . base_url('index.php?page=dashboard'));
    exit;
}

$doc_templates = getDocumentTemplates();

// Tratamento de Mensagens
$mensagem = $_SESSION['success_message'] ?? $_SESSION['error_message'] ?? null;
$class_alerta = isset($_SESSION['success_message']) ? 'alert-success' : 'alert-danger';
unset($_SESSION['success_message'], $_SESSION['error_message']);

?>

<?php render_page_title('Configurar Termo e Recibo', 'Edite os templates do Termo de Quitação e do Recibo de Pagamento. Use os placeholders indicados.', 'bi-journal-check'); ?>

<?php if ($mensagem): ?>
    <div class="alert <?php echo $class_alerta; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($mensagem); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Placeholders (collapsible) -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Placeholders disponíveis</h6>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_placeholders" aria-expanded="true" aria-controls="collapse_placeholders">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div id="collapse_placeholders" class="collapse show">
    <div class="card-body">
        <p class="text-muted small">Use os códigos abaixo diretamente nos campos de Cabeçalho, Corpo ou Rodapé. Insira exatamente entre chaves, por exemplo <code>{date}</code> ou <code>{empresa}</code>.</p>
        <div class="row">
            <div class="col-md-6">
                <strong>Termo de Quitação</strong>
                <ul class="mb-0">
                    <li><code>{logo}</code> — Insere o logotipo da empresa (imagem embutida).</li>
                    <li><code>{date}</code> — Data atual do documento (formato <code>d/m/Y</code> ou use <code>{date_time}</code> se desejar hora).</li>
                    <li><code>{payments_table}</code> — Tabela HTML com os pagamentos quitados (gerada automaticamente).</li>
                    <li><code>{total}</code> — Soma total dos valores listados na tabela.</li>
                </ul>
            </div>
            <div class="col-md-6">
                <strong>Recibo de Pagamento</strong>
                <ul class="mb-0">
                    <li><code>{logo}</code> — Insere o logotipo da empresa (imagem embutida).</li>
                    <li><code>{empresa}</code> — Razão social da empresa.</li>
                    <li><code>{cnpj}</code> — CNPJ da empresa.</li>
                    <li><code>{cliente}</code> — Nome do cliente ou responsável.</li>
                    <li><code>{cliente_email}</code> — E‑mail do cliente.</li>
                    <li><code>{descricao}</code> — Descrição do lançamento/cobrança.</li>
                    <li><code>{valor}</code> — Valor do pagamento (formatado).</li>
                    <li><code>{data_pagamento}</code> — Data em que o pagamento foi registrado.</li>
                    <li><code>{tipo}</code>, <code>{forma}</code>, <code>{contexto}</code> — Informações adicionais sobre tipo/forma/contexto do pagamento.</li>
                </ul>
            </div>
        </div>
        <p class="mt-2 small text-muted">Observação: os campos aceitam HTML; evite scripts. Para incluir hora no rodapé do Termo, use o placeholder <code>{date_time}</code> (implementado como <code>d/m/Y H:i</code> pelo gerador).</p>
    </div>
    </div>
</div>
    <!-- Inicializar editor WYSIWYG (CKEditor5) e adicionar toggle Visual/HTML para cada textarea.tinymce -->
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof ClassicEditor !== 'undefined') {
            var editors = {};
            document.querySelectorAll('textarea.tinymce').forEach(function(el) {
                try {
                    if (!el.id) el.id = 'editor_' + Math.random().toString(36).substr(2,6);
                    ClassicEditor.create(el, {
                        toolbar: ['undo','redo','bold','italic','link','bulletedList','numberedList'],
                        removePlugins: ['Title'],
                    }).then(function(editor) {
                        editors[el.id] = editor;

                        var wrap = document.createElement('div');
                        wrap.className = 'mb-2';
                        var btnGroup = document.createElement('div');
                        btnGroup.className = 'btn-group btn-group-sm';
                        var btnVisual = document.createElement('button');
                        btnVisual.type = 'button'; btnVisual.className = 'btn btn-outline-secondary active'; btnVisual.textContent = 'Visual';
                        var btnHtml = document.createElement('button');
                        btnHtml.type = 'button'; btnHtml.className = 'btn btn-outline-secondary'; btnHtml.textContent = 'HTML';
                        btnGroup.appendChild(btnVisual); btnGroup.appendChild(btnHtml);
                        wrap.appendChild(btnGroup);

                        var parent = el.parentNode;
                        parent.insertBefore(wrap, el);

                        var source = document.createElement('textarea');
                        source.className = 'form-control d-none source-html';
                        source.style.minHeight = '200px';
                        source.id = el.id + '_source';
                        parent.insertBefore(source, el.nextSibling);

                        var editorWrapper = null;
                        try { editorWrapper = editor.ui.view.editable.element.closest('.ck-editor'); } catch(e) { editorWrapper = null; }

                        btnHtml.addEventListener('click', function(){
                            try { source.value = editor.getData(); } catch(e) { source.value = el.value; }
                            if (editorWrapper) editorWrapper.style.display = 'none';
                            source.classList.remove('d-none');
                            btnHtml.classList.add('active'); btnVisual.classList.remove('active');
                        });

                        btnVisual.addEventListener('click', function(){
                            try { editor.setData(source.value); } catch(e) {}
                            if (editorWrapper) editorWrapper.style.display = '';
                            source.classList.add('d-none');
                            btnVisual.classList.add('active'); btnHtml.classList.remove('active');
                        });

                    }).catch(function(err){ console.error(err); });
                } catch (e) {
                    console.error('Erro ao iniciar CKEditor5:', e);
                }
            });

            document.querySelectorAll('form').forEach(function(form){
                form.addEventListener('submit', function(){
                    document.querySelectorAll('textarea.tinymce').forEach(function(el){
                        var id = el.id;
                        var editor = editors[id];
                        var source = document.getElementById(id + '_source');
                        if (!el) return;
                        if (source && !source.classList.contains('d-none')) {
                            el.value = source.value;
                        } else {
                            try { el.value = editor.getData(); } catch(e) {}
                        }
                    });
                });
            });
        }
    });
    </script>

<!-- Termo de Quitação (separado, com collapse) -->
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><code>Termo de Quitação</code></h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_termo" aria-expanded="true" aria-controls="collapse_termo">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div id="collapse_termo" class="collapse show">
    <div class="card-body">
        <form action="process/crud_handler.php" method="POST">
            <input type="hidden" name="action" value="salvar_config_documentos">

            <div class="mb-3">
                <label for="termo_header" class="form-label"><strong>Termo - Cabeçalho</strong></label>
                <textarea class="form-control tinymce" id="termo_header" name="termo_header" rows="3"><?php echo htmlspecialchars($doc_templates['termo_header'] ?? '<div>{logo}<h2>Termo de Quitação</h2></div>'); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="termo_body" class="form-label"><strong>Termo - Corpo</strong></label>
                <textarea class="form-control tinymce" id="termo_body" name="termo_body" rows="6"><?php echo htmlspecialchars($doc_templates['termo_body'] ?? '<p>Lista de pagamentos confirmados até {date}:</p>{payments_table}'); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="termo_footer" class="form-label"><strong>Termo - Rodapé</strong></label>
                <textarea class="form-control tinymce" id="termo_footer" name="termo_footer" rows="2"><?php echo htmlspecialchars($doc_templates['termo_footer'] ?? '<p>Documento gerado em {date}</p>'); ?></textarea>
            </div>

            <div class="d-grid gap-2 d-md-flex">
                <button type="submit" class="btn btn-primary btn-full-mobile"><i class="bi bi-save me-2"></i> Salvar Termo</button>
            </div>
        </form>
    </div>
    </div>
</div>

<!-- Recibo de Pagamento (separado, com collapse) -->
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><code>Recibo de Pagamento</code></h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_recibo" aria-expanded="true" aria-controls="collapse_recibo">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div id="collapse_recibo" class="collapse show">
    <div class="card-body">
        <form action="process/crud_handler.php" method="POST">
            <input type="hidden" name="action" value="salvar_config_documentos">

            <div class="mb-3">
                <label for="recibo_header" class="form-label"><strong>Recibo - Cabeçalho</strong></label>
                <textarea class="form-control tinymce" id="recibo_header" name="recibo_header" rows="3"><?php echo htmlspecialchars($doc_templates['recibo_header'] ?? '<div>{logo}<h2>Recibo de Pagamento</h2></div>'); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="recibo_body" class="form-label"><strong>Recibo - Corpo</strong></label>
                <textarea class="form-control tinymce" id="recibo_body" name="recibo_body" rows="6"><?php echo htmlspecialchars($doc_templates['recibo_body'] ?? '<p><strong>Empresa:</strong> {empresa} ({cnpj})</p>'); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="recibo_footer" class="form-label"><strong>Recibo - Rodapé</strong></label>
                <textarea class="form-control tinymce" id="recibo_footer" name="recibo_footer" rows="2"><?php echo htmlspecialchars($doc_templates['recibo_footer'] ?? '<p>Documento gerado em {date}</p>'); ?></textarea>
            </div>

            <div class="d-grid gap-2 d-md-flex">
                <button type="submit" class="btn btn-primary btn-full-mobile"><i class="bi bi-save me-2"></i> Salvar Recibo</button>
                <a href="index.php?page=configuracoes_email" class="btn btn-outline-secondary btn-full-mobile">Voltar Configurações de E-mail</a>
            </div>
        </form>
    </div>
    </div>
</div>
