<?php
// pages/meu_perfil.php
global $pdo;

// Busca os dados atuais do usuário logado
$user_id = $_SESSION['user_id'];
$stmt_user = $pdo->prepare("SELECT nome, email, telefone FROM usuarios WHERE id = ?");
$stmt_user->execute([$user_id]);
$usuario = $stmt_user->fetch();

// Se for um cliente, busca os dados da empresa (read-only)
$cliente_info = null;
if (isClient()) {
    $stmt_cliente = $pdo->prepare("SELECT nome_responsavel, email_contato, telefone FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$_SESSION['id_cliente_associado']]);
    $cliente_info = $stmt_cliente->fetch();
}

// REMOVIDO: O bloco de código que exibia $success_msg e $error_msg
// foi removido daqui, pois agora é gerenciado pelo header.php

?>

<?php render_page_title('Meu Perfil', 'Gerencie suas informações pessoais e de acesso.', 'bi-person-circle'); ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-fill me-2"></i> Dados Pessoais
            </div>
            <div class="card-body">
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="atualizar_perfil">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email (Login)</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>" placeholder="(XX) XXXXX-XXXX">
                    </div>

                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lock-fill me-2"></i> Alterar Senha
            </div>
            <div class="card-body">
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="alterar_senha">
                    
                    <div class="mb-3">
                        <label for="senha_atual" class="form-label">Senha Atual</label>
                        <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nova_senha" class="form-label">Nova Senha</label>
                        <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Alterar Senha</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php // Se for um cliente, exibe os dados da empresa (read-only) ?>
<?php if (isClient() && $cliente_info): ?>
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-building me-2"></i> Informações do Cliente
    </div>
    <div class="card-body">
        <p class="text-muted">
            As informações abaixo são gerenciadas pelo seu contador. 
            Para alterações, por favor, entre em contato com o administrador.
        </p>
        
        <div class="row">
            <div class="col-md-4">
                <strong>Responsável:</strong>
                <p><?php echo htmlspecialchars($cliente_info['nome_responsavel']); ?></p>
            </div>
            <div class="col-md-4">
                <strong>Email de Contato:</strong>
                <p><?php echo htmlspecialchars($cliente_info['email_contato']); ?></p>
            </div>
            <div class="col-md-4">
                <strong>Telefone:</strong>
                <p><?php echo htmlspecialchars($cliente_info['telefone']); ?></p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php // Preferências de Notificação por Email para clientes ?>
<?php if (isClient()): ?>
    <?php
        $id_cliente_logado = $_SESSION['id_cliente_associado'] ?? null;
        $pref_cobrancas = 0;
        $pref_recibos = 0;
        $cliente_email = '';
        if ($id_cliente_logado) {
            try {
                // lê email do cliente para decidir se as opções devem ser habilitadas
                $stmtCli = $pdo->prepare("SELECT email_contato FROM clientes WHERE id = ? LIMIT 1");
                $stmtCli->execute([$id_cliente_logado]);
                $cliente_row = $stmtCli->fetch(PDO::FETCH_ASSOC);
                $cliente_email = trim($cliente_row['email_contato'] ?? '');

                $chk = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                $chk->execute([$id_cliente_logado, 'receber_novas_cobrancas']);
                $pref_cobrancas = $chk->fetchColumn() ? 1 : 0;
                $chk->execute([$id_cliente_logado, 'receber_recibos']);
                $pref_recibos = $chk->fetchColumn() ? 1 : 0;
            } catch (Exception $e) {
                // se a tabela não existir ou erro, mantém defaults em 0
                error_log('Erro ao ler preferências de email do cliente: ' . $e->getMessage());
            }
        }
        $disabled_attr = empty($cliente_email) ? 'disabled' : '';
    ?>
    <div class="card mt-4">
        <div class="card-header">
            <i class="bi bi-envelope-fill me-2"></i> Preferências de Notificação por Email
        </div>
        <div class="card-body">
            <p class="text-muted">Marque as opções de e-mail que deseja receber automaticamente.</p>
            <form method="POST" action="process/crud_handler.php">
                <input type="hidden" name="action" value="salvar_preferencias_cliente">
                <?php if (empty($cliente_email)): ?>
                    <div class="alert alert-warning">Seu cliente não tem um <strong>email de contato</strong> cadastrado. Para receber notificações por email, peça para seu administrador/contador cadastrar um email de contato na ficha do cliente.</div>
                <?php endif; ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="pref_receber_cobrancas" name="receber_novas_cobrancas" <?php echo $pref_cobrancas ? 'checked' : ''; ?> <?php echo $disabled_attr; ?> />
                    <label class="form-check-label" for="pref_receber_cobrancas">Receber novas cobranças por email automaticamente</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="pref_receber_recibos" name="receber_recibos" <?php echo $pref_recibos ? 'checked' : ''; ?> <?php echo $disabled_attr; ?> />
                    <label class="form-check-label" for="pref_receber_recibos">Receber recibos de pagamento por email automaticamente</label>
                </div>
                <button type="submit" class="btn btn-primary">Salvar Preferências</button>
            </form>
        </div>
    </div>
<?php endif; ?>