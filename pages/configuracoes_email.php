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
    <div class="card-body">
        <form action="process/crud_handler.php" method="POST">
            
            <h5 class="mb-3">Detalhes do Servidor</h5>
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

            <hr class="mt-4">
            <h5 class="mb-3">Templates de Mensagem</h5>
            <div class="mb-3">
                <label for="email_subject_template" class="form-label">Assunto (use placeholders: {toName}, {descricao}, {valor}, {data_vencimento}, {tipo})</label>
                <input type="text" class="form-control" id="email_subject_template" name="email_subject_template" value="<?php echo htmlspecialchars($settings['email_subject_template'] ?? 'Novo Lançamento Financeiro Disponível: R$ {valor}'); ?>">
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="email_from_name" class="form-label">Nome do Remetente (From name)</label>
                    <input type="text" class="form-control" id="email_from_name" name="email_from_name" value="<?php echo htmlspecialchars($settings['email_from_name'] ?? 'Sistema Financeiro'); ?>">
                </div>
                <div class="col-md-6">
                    <label for="email_salutation" class="form-label">Saudação (ex: Prezado(a) {toName},)</label>
                    <input type="text" class="form-control" id="email_salutation" name="email_salutation" value="<?php echo htmlspecialchars($settings['email_salutation'] ?? 'Prezado(a) {toName},'); ?>">
                </div>
            </div>

            <div class="mb-3">
                <label for="email_intro" class="form-label">Texto introdutório</label>
                <textarea class="form-control" id="email_intro" name="email_intro" rows="3"><?php echo htmlspecialchars($settings['email_intro'] ?? 'Informamos que um novo lançamento financeiro foi disponibilizado para sua empresa. Por favor, acesse o portal para visualizar os detalhes e a situação de pagamento.'); ?></textarea>
            </div>

            <div class="mb-3">
                <label for="email_closing" class="form-label">Texto de fechamento (assinatura)</label>
                <textarea class="form-control" id="email_closing" name="email_closing" rows="2"><?php echo htmlspecialchars($settings['email_closing'] ?? "Atenciosamente,\nEquipe Financeira"); ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="action" value="salvar_config_smtp" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i> Salvar Configurações
                </button>

                <button type="submit" name="action" value="test_smtp" class="btn btn-outline-secondary" title="Enviar e-mail de teste para o remetente configurado">
                    <i class="bi bi-envelope-check me-2"></i> Testar Conexão SMTP
                </button>
            </div>
        </form>
    </div>
</div>