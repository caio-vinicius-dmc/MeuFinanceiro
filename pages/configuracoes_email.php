<?php
// pages/configuracoes_email.php
require_once 'config/functions.php';
global $pdo;

// 1. Apenas Admin pode ver esta página
if (!isAdmin()) {
    header("Location: " . base_url('index.php?page=dashboard'));
    exit;
}

$settings = getSmtpSettings();

// Tratamento de Mensagens
$mensagem = $_SESSION['success_message'] ?? $_SESSION['error_message'] ?? null;
$class_alerta = isset($_SESSION['success_message']) ? 'alert-success' : 'alert-danger';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// A senha não é recuperada do DB por segurança, é redefinida no envio.
$smtp_password = ''; 
?>

<?php render_page_title('Configurações de E-mail (SMTP)', 'Configure o servidor de saída de e-mail para envio de notificações.', 'bi-envelope-fill'); ?>

<?php if ($mensagem): ?>
    <div class="alert <?php echo $class_alerta; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($mensagem); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Detalhes do Servidor</h5>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_smtp" aria-expanded="true" aria-controls="collapse_smtp">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div id="collapse_smtp" class="collapse show">
    <div class="card-body">
        <form action="process/crud_handler.php" method="POST">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="smtp_host" class="form-label">Host SMTP</label>
                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="smtp_port" class="form-label">Porta</label>
                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="smtp_secure" class="form-label">Criptografia</label>
                    <select class="form-select" id="smtp_secure" name="smtp_secure" required>
                        <option value="tls" <?php echo ($settings['smtp_secure'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                        <option value="starttls" <?php echo ($settings['smtp_secure'] === 'starttls') ? 'selected' : ''; ?>>STARTTLS (recomendado para Microsoft/Office365)</option>
                        <option value="ssl" <?php echo ($settings['smtp_secure'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                        <option value="" <?php echo ($settings['smtp_secure'] === '') ? 'selected' : ''; ?>>Nenhuma</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="email_from" class="form-label">E-mail de Remetente (FROM)</label>
                    <input type="email" class="form-control" id="email_from" name="email_from" value="<?php echo htmlspecialchars($settings['email_from']); ?>" required>
                </div>
            </div>

            <h5 class="mb-3">Autenticação</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="smtp_username" class="form-label">Usuário SMTP (E-mail)</label>
                    <input type="email" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="smtp_password" class="form-label">Senha SMTP</label>
                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="Preencha apenas para alterar" value="<?php echo htmlspecialchars($smtp_password); ?>">
                    <small class="text-muted">Deixe em branco para manter a senha atual do sistema.</small>
                </div>
            </div>

            <div class="mt-4 d-grid gap-2 d-md-flex">
                <button type="submit" name="action" value="salvar_config_smtp" class="btn btn-primary btn-full-mobile">
                    <i class="bi bi-save me-2"></i> Salvar Detalhes do Servidor
                </button>
                <button type="submit" name="action" value="test_smtp" class="btn btn-outline-secondary">
                    <i class="bi bi-envelope-check me-2"></i> Testar SMTP
                </button>
            </div>

        </form>
            <hr class="mt-4">
            <!-- Fim do formulário de Detalhes do Servidor -->
    </div>
    </div>
</div>

<!-- Card separado: Modelos de Template (assunto/intro/closing) -->
<div class="card shadow-sm mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cobrança gerada - Template de Email</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_cobranca" aria-expanded="true" aria-controls="collapse_cobranca">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div id="collapse_cobranca" class="collapse show">
    <div class="card-body">
        <p class="small text-muted">Esse template é para enviar email da cobrança nova gerada que será enviada ao cliente.</p>

        <form action="process/crud_handler.php" method="POST">
            <input type="hidden" name="action" value="salvar_templates_email">
            <div class="mb-3">
                <label for="email_subject_template" class="form-label">Assunto <br><code>(use placeholders: {toName}, {descricao}, {valor}, {data_vencimento}, {tipo})</code></label>
                <input type="text" class="form-control" id="email_subject_template" name="email_subject_template" value="<?php echo htmlspecialchars($settings['email_subject_template'] ?? 'Novo Lançamento Financeiro Disponível: R$ {valor}'); ?>">
            </div>

            <!-- Nome do Remetente removido; usar placeholders listados abaixo -->

            <!-- Texto introdutório removido (usará template personalizado em 'Corpo do Email') -->

            <div class="mb-3">
                <label for="lancamento_email_title" class="form-label">Título (opcional)</label>
                <input type="text" class="form-control" id="lancamento_email_title" name="lancamento_email_title" value="<?php echo htmlspecialchars($settings['lancamento_email_title'] ?? ''); ?>" placeholder="Ex: Novo Lançamento Disponível (opcional)">
            </div>

            <!-- Assunto removido: o assunto será definido pelo sistema ou por outro fluxo -->

            <div class="mb-3">
                <label for="lancamento_email_body" class="form-label">Mensagem do Email (HTML)</label>
                <textarea class="form-control" id="lancamento_email_body" name="lancamento_email_body" rows="8"><?php echo htmlspecialchars($settings['lancamento_email_body'] ?? "<p>{logo}</p><p>{salutation}</p><p>{email_intro}</p>{lancamento_table}<p>{email_closing}</p>"); ?></textarea>
                <div class="form-text small">Aceita HTML. Use placeholders: <code>{toName}</code>, <code>{descricao}</code>, <code>{valor}</code>, <code>{data_vencimento}</code>, <code>{tipo}</code>, <code>{forma}</code>, <code>{contexto}</code>, <code>{logo}</code>, <code>{logo_url}</code>, <code>{lancamento_table}</code>.
                <br>Observação: o campo "Nome do Remetente" foi removido da interface; se precisar inserir o nome do destinatário utilize <code>{toName}</code> no template.</div>
            </div>

            <div class="d-grid gap-2 d-md-flex">
                <button type="submit" name="action" value="salvar_templates_email" class="btn btn-primary btn-full-mobile">
                    <i class="bi bi-save me-2"></i> Salvar Modelos
                </button>
            </div>
        </form>
    </div>
</div>
    </div>
</div>

<!-- Recibo de Pagamento: personalização de email (exibir placeholders e dicas) -->
<div class="card shadow-sm mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recibo de Pagamento — Template de Email</h5>
        <div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_recibo" aria-expanded="true" aria-controls="collapse_recibo">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div id="collapse_recibo" class="collapse show">
    <div class="card-body">
        <p class="small text-muted">Personalize o assunto e a mensagem enviada ao cliente quando o recibo for enviado. Use os placeholders listados para inserir dados automaticamente.</p>

        <form action="process/crud_handler.php" method="POST">
            <input type="hidden" name="action" value="salvar_templates_email">
            <div class="mb-3">
                <label class="form-label">Assunto do Email</label>
                <input type="text" name="recibo_email_subject" class="form-control" value="<?php echo htmlspecialchars($settings['recibo_email_subject'] ?? 'Recibo de Pagamento - Cobrança #{id}'); ?>" placeholder="Ex: Recibo de Pagamento - Cobrança #{id}">
                <div class="form-text small">Placeholders válidos: <code>{id}</code>, <code>{empresa}</code>, <code>{cliente}</code>, <code>{valor}</code>, <code>{data_pagamento}</code>, <code>{date}</code>.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Título (opcional)</label>
                <input type="text" name="recibo_email_title" class="form-control" value="<?php echo htmlspecialchars($settings['recibo_email_title'] ?? 'Recibo de Pagamento'); ?>" placeholder="Título exibido no corpo do email (opcional)">
            </div>

            <div class="mb-3">
                <label class="form-label">Mensagem do Email (HTML)</label>
                <textarea name="recibo_email_body" rows="6" class="form-control"><?php echo htmlspecialchars($settings['recibo_email_body'] ?? '<p>Prezados,</p><p>Em anexo segue o recibo de pagamento referente à cobrança #{id}.</p><p>Atenciosamente,</p>'); ?></textarea>
                <div class="form-text small">Aceita HTML. Exemplos de uso: <code>&lt;p&gt;Prezados, &lt;/p&gt;&lt;p&gt;Em anexo... Cobrança #{id} - R$ {valor}&lt;/p&gt;</code></div>
            </div>

            <div class="mb-3">
                <label class="form-label">Placeholders úteis e contexto</label>
                <ul class="small mb-0">
                    <li><strong>{id}</strong> — ID da cobrança (ex.: 123)</li>
                    <li><strong>{empresa}</strong>, <strong>{cnpj}</strong> — Dados da empresa emissora</li>
                    <li><strong>{cliente}</strong>, <strong>{cliente_email}</strong> — Nome e e-mail do cliente</li>
                    <li><strong>{descricao}</strong>, <strong>{valor}</strong> — Informação do lançamento</li>
                    <li><strong>{data_pagamento}</strong>, <strong>{data_vencimento}</strong> — Datas relevantes</li>
                    <li><strong>{contexto}</strong> — Contexto do pagamento (ex.: chave PIX, código)</li>
                    <li><strong>{date}</strong> — Data e hora atual (formato <code>d/m/Y H:i</code>)</li>
                </ul>
            </div>

            <div class="d-grid gap-2 d-md-flex">
                <button type="submit" name="action" value="salvar_templates_email" class="btn btn-primary btn-full-mobile"><i class="bi bi-save me-2"></i> Salvar Template do Recibo</button>
            </div>
        </form>
    </div>
</div>