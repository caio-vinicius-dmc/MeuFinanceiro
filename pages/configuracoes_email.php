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

<div class="mb-4">
    <h3><i class="bi bi-envelope-fill me-2"></i> Configurações de E-mail (SMTP)</h3>
    <p class="text-muted">Configure o servidor de saída de e-mail para envio de notificações.</p>
</div>

<?php if ($mensagem): ?>
    <div class="alert <?php echo $class_alerta; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($mensagem); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="process/crud_handler.php" method="POST">
            <input type="hidden" name="action" value="salvar_config_smtp">
            
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
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-2"></i> Salvar Configurações
            </button>
        </form>
    </div>
</div>