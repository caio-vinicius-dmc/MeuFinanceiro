<?php
// pages/cadastro_clientes.php
global $pdo;

// Protegido: acessar_configuracoes ou Admin/Contador
if (!isAdmin() && !isContador()) {
    if (function_exists('current_user_has_permission') && current_user_has_permission('acessar_configuracoes')) {
        // permitido via RBAC
    } else {
        header("Location: " . base_url('index.php?page=dashboard'));
        exit;
    }
}

// Buscar clientes existentes (inclui preferências de email se tabela de configuração existir)
if (isContador()) {
    // Contador vê os nomes de todos os clientes (para solicitar associação),
    // mas não pode ver dados até estar associado — marcamos 'associado' por cliente
    $stmt = $pdo->prepare(
        "SELECT c.*, 
            (SELECT 1 FROM tb_confg_emailCliente ec WHERE ec.id_client = c.id AND ec.permissao = 'receber_novas_cobrancas' LIMIT 1) AS receber_novas_cobrancas_email,
            (SELECT 1 FROM tb_confg_emailCliente ec2 WHERE ec2.id_client = c.id AND ec2.permissao = 'receber_recibos' LIMIT 1) AS receber_recibos_email,
            (SELECT 1 FROM contador_clientes_assoc ca WHERE ca.id_usuario_contador = ? AND ca.id_cliente = c.id LIMIT 1) AS associado,
            (SELECT 1 FROM contador_assoc_requests r WHERE r.id_usuario_contador = ? AND r.id_cliente = c.id AND r.status = 'pending' LIMIT 1) AS solicitada
         FROM clientes c ORDER BY c.nome_responsavel"
    );
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $clientes = $stmt->fetchAll();
    // Determina se o contador tem alguma associação ativa
    $has_assoc = false;
    foreach ($clientes as $c) {
        if (!empty($c['associado'])) { $has_assoc = true; break; }
    }
    $contador_sem_clientes = !$has_assoc;
} else {
    $stmt = $pdo->query(
        "SELECT c.*, 
            (SELECT 1 FROM tb_confg_emailCliente ec WHERE ec.id_client = c.id AND ec.permissao = 'receber_novas_cobrancas' LIMIT 1) AS receber_novas_cobrancas_email,
            (SELECT 1 FROM tb_confg_emailCliente ec2 WHERE ec2.id_client = c.id AND ec2.permissao = 'receber_recibos' LIMIT 1) AS receber_recibos_email
         FROM clientes c ORDER BY c.nome_responsavel"
    );
    $clientes = $stmt->fetchAll();
    $contador_sem_clientes = false;
}

?>

<?php render_page_title('Cadastro de Clientes', 'Gerencie os clientes que utilizam o sistema.', 'bi-people'); ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Novo Cliente</h5>
            </div>
            <div class="card-body">
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="cadastrar_cliente">
                    
                        <div class="mb-3">
                            <label for="nome_responsavel" class="form-label">Nome do Responsável</label>
                            <input type="text" class="form-control" id="nome_responsavel" name="nome_responsavel" required>
                        </div>
                    
                    <div class="mb-3">
                        <label for="email_contato" class="form-label">Email de Contato</label>
                        <input type="email" class="form-control" id="email_contato" name="email_contato" required>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="receber_novas_cobrancas_email" name="receber_novas_cobrancas_email">
                        <label class="form-check-label" for="receber_novas_cobrancas_email">Receber novas cobranças por email automaticamente</label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="receber_recibos_email" name="receber_recibos_email">
                        <label class="form-check-label" for="receber_recibos_email">Receber recibos de pagamento por email automaticamente</label>
                    </div>

                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Salvar Cliente</button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Clientes Cadastrados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (isContador() && $contador_sem_clientes): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="alert alert-info mb-0">Você não possui clientes associados. Cadastre um novo cliente — ele será automaticamente associado a você.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php if (empty($clientes)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Nenhum cliente cadastrado.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></td>
                                    <?php if (isContador() && empty($cliente['associado'])): ?>
                                        <td colspan="2"><small class="text-muted">— (dados ocultos até associação)</small></td>
                                    <?php else: ?>
                                        <td><?php echo htmlspecialchars($cliente['email_contato']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['telefone']); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (isContador() && empty($cliente['associado'])): ?>
                                            <?php if (!empty($cliente['solicitada'])): ?>
                                                <span class="badge bg-warning text-dark">Solicitação Pendente</span>
                                            <?php else: ?>
                                                <form action="process/crud_handler.php" method="POST" class="d-inline me-1">
                                                    <input type="hidden" name="action" value="solicitar_assoc_cliente">
                                                    <input type="hidden" name="id_cliente" value="<?php echo $cliente['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Solicitar Associação">Solicitar Associação</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarCliente"
                                                    data-id="<?php echo $cliente['id']; ?>"
                                                    data-nome="<?php echo htmlspecialchars($cliente['nome_responsavel']); ?>"
                                                    data-email="<?php echo htmlspecialchars($cliente['email_contato']); ?>"
                                                    data-telefone="<?php echo htmlspecialchars($cliente['telefone']); ?>"
                                                            data-receber-cobrancas="<?php echo intval($cliente['receber_novas_cobrancas_email'] ?? 0); ?>"
                                                            data-receber-recibos="<?php echo intval($cliente['receber_recibos_email'] ?? 0); ?>"
                                                    title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php // Apenas Admin pode deletar ?>
                                        <?php if (isAdmin()): ?>
                                        <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este cliente? Isso excluirá TODAS as empresas e lançamentos associados.');">
                                            <input type="hidden" name="action" value="deletar_cliente">
                                            <input type="hidden" name="id_cliente" value="<?php echo $cliente['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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


<div class="modal fade" id="modalEditarCliente" tabindex="-1" aria-labelledby="modalEditarClienteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarClienteLabel">Editar Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <input type="hidden" name="action" value="editar_cliente">
                <input type="hidden" name="id_cliente" id="edit_id_cliente">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_nome_responsavel" class="form-label">Nome do Responsável</label>
                        <input type="text" class="form-control" id="edit_nome_responsavel" name="nome_responsavel" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email_contato" class="form-label">Email de Contato</label>
                        <input type="email" class="form-control" id="edit_email_contato" name="email_contato" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="edit_telefone" name="telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="edit_receber_novas_cobrancas_email" name="receber_novas_cobrancas_email">
                        <label class="form-check-label" for="edit_receber_novas_cobrancas_email">Receber novas cobranças por email automaticamente</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="edit_receber_recibos_email" name="receber_recibos_email">
                        <label class="form-check-label" for="edit_receber_recibos_email">Receber recibos de pagamento por email automaticamente</label>
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