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