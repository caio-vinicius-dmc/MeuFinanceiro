<?php
// pages/lancamentos.php
global $pdo;

// --- 1. Capturar e Sanitizar Filtros ---
$filtro_empresa_id = $_GET['id_empresa'] ?? null;
$filtro_status = $_GET['status'] ?? null;
$filtro_venc_inicio = $_GET['venc_inicio'] ?? null;
$filtro_venc_fim = $_GET['venc_fim'] ?? null;
$filtro_valor_min = $_GET['valor_min'] ?? null;
$filtro_valor_max = $_GET['valor_max'] ?? null;

// --- 2. Lógica de Permissão e Construção WHERE/PARAMS ---
$where_conditions = [];
$params = [];

// 2a. Permissão de Dados (Clientes e Contadores)
$clientes_permitidos_ids = []; 
if (isContador()) {
    $stmt_clientes_assoc = $pdo->prepare("SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
    $stmt_clientes_assoc->execute([$_SESSION['user_id']]);
    $clientes_permitidos_ids = $stmt_clientes_assoc->fetchAll(PDO::FETCH_COLUMN);

    if (empty($clientes_permitidos_ids)) {
        $where_conditions[] = "e.id_cliente IN (0)"; // Não pode ver nada
    } else {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $where_conditions[] = "e.id_cliente IN ($placeholders)";
        $params = array_merge($params, $clientes_permitidos_ids);
    }
} 
elseif (isClient()) {
    $where_conditions[] = "e.id_cliente = ?";
    $params[] = $_SESSION['id_cliente_associado'];
}

// 2b. Filtros de Interface (Aplicados após a permissão)
if (!empty($filtro_empresa_id)) {
    $where_conditions[] = "l.id_empresa = ?";
    $params[] = $filtro_empresa_id;
}
if (!empty($filtro_status)) {
    $where_conditions[] = "l.status = ?";
    $params[] = $filtro_status;
}
if (!empty($filtro_venc_inicio)) {
    $where_conditions[] = "l.data_vencimento >= ?";
    $params[] = $filtro_venc_inicio;
}
if (!empty($filtro_venc_fim)) {
    $where_conditions[] = "l.data_vencimento <= ?";
    $params[] = $filtro_venc_fim;
}
if (is_numeric($filtro_valor_min)) {
    $where_conditions[] = "l.valor >= ?";
    $params[] = $filtro_valor_min;
}
if (is_numeric($filtro_valor_max)) {
    $where_conditions[] = "l.valor <= ?";
    $params[] = $filtro_valor_max;
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// --- 3. Consulta Principal de Lançamentos ---
$sql = "SELECT l.*, e.razao_social, e.id_cliente, c.nome_responsavel 
        FROM lancamentos l
        JOIN empresas e ON l.id_empresa = e.id
        JOIN clientes c ON e.id_cliente = c.id
        $where_sql
        ORDER BY l.data_vencimento DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll();

// --- 4. Consulta para População de Dropdowns (Empresas) ---
$sql_empresas_modal = "SELECT e.id, e.razao_social, c.nome_responsavel 
                        FROM empresas e
                        JOIN clientes c ON e.id_cliente = c.id";
$params_empresas_modal = [];

if(isContador()) {
    $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
    if (!empty($clientes_permitidos_ids)) {
        $sql_empresas_modal .= " WHERE e.id_cliente IN ($placeholders)";
        $params_empresas_modal = $clientes_permitidos_ids;
    } else {
        $sql_empresas_modal .= " WHERE 1=0";
    }
} elseif (isClient()) {
    $sql_empresas_modal .= " WHERE e.id_cliente = ?";
    $params_empresas_modal[] = $_SESSION['id_cliente_associado'];
}

$sql_empresas_modal .= " ORDER BY c.nome_responsavel, e.razao_social";
$stmt_empresas_modal = $pdo->prepare($sql_empresas_modal);
$stmt_empresas_modal->execute($params_empresas_modal);
$empresas_modal = $stmt_empresas_modal->fetchAll();

// 5. Opções de Status
$status_options = [
    'pendente' => 'Pendente', 
    'confirmado_cliente' => 'Confirmado pelo Cliente', 
    'contestado' => 'Contestado', 
    'pago' => 'Pago'
];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Lançamentos Financeiros</h3>
    <div class="d-flex gap-2">
        <button id="btn-exportar" class="btn btn-outline-success" title="Exportar dados filtrados para CSV">
            <i class="bi bi-file-earmark-spreadsheet"></i> Exportar
        </button>
        <?php if (isAdmin() || isContador()): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento">
                <i class="bi bi-plus-circle"></i> Novo Lançamento
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-funnel me-2"></i> Filtros
    </div>
    <div class="card-body">
        <form id="form-filtros" method="GET" class="row g-3">
            <input type="hidden" name="page" value="lancamentos">

            <div class="col-md-3">
                <label for="id_empresa" class="form-label">Empresa</label>
                <select id="id_empresa" name="id_empresa" class="form-select">
                    <option value="">Todas as Empresas</option>
                    <?php foreach ($empresas_modal as $empresa): ?>
                        <option value='<?php echo $empresa['id']; ?>' <?php echo ($filtro_empresa_id == $empresa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($empresa['razao_social']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Todos os Status</option>
                    <?php foreach ($status_options as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($filtro_status == $value) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Vencimento Entre</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="venc_inicio" value="<?php echo htmlspecialchars($filtro_venc_inicio ?? ''); ?>" title="Data Início">
                    <input type="date" class="form-control" name="venc_fim" value="<?php echo htmlspecialchars($filtro_venc_fim ?? ''); ?>" title="Data Fim">
                </div>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Valor Entre (R$)</label>
                <div class="input-group">
                    <input type="number" step="0.01" class="form-control" name="valor_min" value="<?php echo htmlspecialchars($filtro_valor_min ?? ''); ?>" placeholder="Mínimo" title="Valor Mínimo">
                    <input type="number" step="0.01" class="form-control" name="valor_max" value="<?php echo htmlspecialchars($filtro_valor_max ?? ''); ?>" placeholder="Máximo" title="Valor Máximo">
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary" id="btn-aplicar-filtros">
                    <i class="bi bi-search me-2"></i>Aplicar Filtros
                </button>
                <a href="index.php?page=lancamentos" class="btn btn-outline-secondary">Limpar Filtros</a>
            </div>
        </form>
    </div>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Vencimento</th>
                        <th>Cliente/Empresa</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lancamentos)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Nenhum lançamento encontrado.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($lancamentos as $lanc): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($lanc['data_vencimento'])); ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($lanc['razao_social']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($lanc['nome_responsavel']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($lanc['descricao']); ?></td>
                            <td class="fw-bold <?php echo ($lanc['tipo'] == 'receita') ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($lanc['tipo'] == 'receita') ? '+' : '-'; ?>
                                R$ <?php echo number_format($lanc['valor'], 2, ',', '.'); ?>
                            </td>
                            <td>
                                <?php
                                $status_badge = '';
                                $status_text = '';
                                switch ($lanc['status']) {
                                    case 'pago': 
                                        $status_badge = 'bg-success'; 
                                        $status_text = 'Pago';
                                        break;
                                    case 'contestado': 
                                        $status_badge = 'bg-danger'; 
                                        $status_text = 'Contestado';
                                        break;
                                    case 'confirmado_cliente': 
                                        $status_badge = 'bg-info'; 
                                        $status_text = 'Confirmado';
                                        break;
                                    case 'pendente': 
                                    default:
                                        $status_badge = 'bg-warning text-dark'; 
                                        $status_text = 'Pendente';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $status_badge; ?> fs-6" 
                                      <?php if ($lanc['status'] == 'contestado' && !empty($lanc['observacao_contestacao'])): ?>
                                          data-bs-toggle="tooltip" data-bs-placement="top" 
                                          title="Motivo: <?php echo htmlspecialchars($lanc['observacao_contestacao']); ?>"
                                      <?php endif; ?>>
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                
                                <?php // --- AÇÕES CONTADOR/ADMIN --- ?>

                                <?php if (isAdmin() || isContador()): ?>
                                
                                    <a href="process/crud_handler.php?action=disparar_email_lancamento&id_lancamento=<?php echo $lanc['id']; ?>"
                                        class="btn btn-sm btn-outline-info me-1"
                                        title="Enviar E-mail de Notificação para o Cliente"
                                        onclick="return confirm('Deseja enviar o e-mail de notificação para o cliente associado a este lançamento?');">
                                        <i class="bi bi-envelope"></i>
                                    </a>
                                    <?php 
                                    // Ação: Dar Baixa (se pendente, confirmado ou contestado)
                                    $pode_baixar = in_array($lanc['status'], ['pendente', 'confirmado_cliente', 'contestado']);
                                    ?>
                                    <?php if ($pode_baixar): ?>
                                        <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Confirmar o recebimento (dar baixa) deste lançamento?');">
                                            <input type="hidden" name="action" value="dar_baixa_lancamento">
                                            <input type="hidden" name="id_lancamento" value="<?php echo $lanc['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Confirmar Recebimento (Dar Baixa)">
                                                <i class="bi bi-check-circle"></i> Baixar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php // Ação: Reverter Confirmação (APENAS se confirmado_cliente) ?>
                                    <?php if ($lanc['status'] == 'confirmado_cliente'): ?>
                                        <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Reverter a confirmação do cliente para PENDENTE?');">
                                            <input type="hidden" name="action" value="reverter_confirmacao_cliente">
                                            <input type="hidden" name="id_lancamento" value="<?php echo $lanc['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Reverter Confirmação">
                                                <i class="bi bi-person-x-fill"></i> Reverter Conf.
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php // Ação: Reverter Contestação (APENAS se CONTESTADO) ?>
                                    <?php if ($lanc['status'] == 'contestado'): ?>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalReverterContestacao_<?php echo $lanc['id']; ?>" title="Reverter Contestação">
                                            <i class="bi bi-arrow-return-left"></i> Reverter Contest.
                                        </button>
                                    <?php endif; ?>


                                    <?php // Ação: Reverter Pagamento (APENAS se PAGO) ?>
                                    <?php if ($lanc['status'] == 'pago'): ?>
                                        <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja REVERTER este lançamento para PENDENTE? O status PAGO será desfeito.');">
                                            <input type="hidden" name="action" value="reverter_pagamento">
                                            <input type="hidden" name="id_lancamento" value="<?php echo $lanc['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Reverter Pagamento">
                                                <i class="bi bi-arrow-counterclockwise"></i> Reverter Baixa
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php // Só pode editar se NÃO estiver PAGO ?>
                                    <?php if ($lanc['status'] != 'pago'): ?>
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditarLancamento"
                                                data-id="<?php echo $lanc['id']; ?>"
                                                data-id_empresa="<?php echo $lanc['id_empresa']; ?>"
                                                data-descricao="<?php echo htmlspecialchars($lanc['descricao']); ?>"
                                                data-valor="<?php echo htmlspecialchars($lanc['valor']); ?>"
                                                data-tipo="<?php echo htmlspecialchars($lanc['tipo']); ?>"
                                                data-vencimento="<?php echo htmlspecialchars($lanc['data_vencimento']); ?>"
                                                title="Editar Lançamento">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>

                                <?php endif; ?>


                                <?php // 2. Cliente (Pode confirmar ou contestar, APENAS se pendente) ?>
                                <?php if ( isClient() && $lanc['status'] == 'pendente' ): ?>
                                    
                                     <button class="btn btn-sm btn-success" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalConfirmarPagamento_<?php echo $lanc['id']; ?>" 
                                            title="Sinalizar Pagamento e Informar Detalhes">
                                        <i class="bi bi-check-lg"></i> Confirmar
                                     </button>
                                     <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalContestar_<?php echo $lanc['id']; ?>" title="Contestar Lançamento">
                                        <i class="bi bi-exclamation-triangle"></i> Contestar
                                    </button>
                                <?php endif; ?>
                                
                                <?php // 3. Cliente (Vê status de aguardo) ?>
                                <?php if ( isClient() && $lanc['status'] == 'confirmado_cliente' ): ?>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-hourglass-split me-1"></i>Aguardando baixa
                                    </span>
                                <?php endif; ?>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php // --- Modal: Novo Lançamento (Apenas Admin/Contador) --- ?>
<?php if (isAdmin() || isContador()): ?>
<div class="modal fade" id="modalNovoLancamento" tabindex="-1" aria-labelledby="modalNovoLancamentoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovoLancamentoLabel">Novo Lançamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <input type="hidden" name="action" value="cadastrar_lancamento">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="id_empresa" class="form-label">Empresa</label>
                            <select id="id_empresa" name="id_empresa" class="form-select" required>
                                <option value="" selected disabled>Selecione a empresa...</option>
                                <?php foreach ($empresas_modal as $empresa): ?>
                                    <option value='<?php echo $empresa['id']; ?>'>
                                        <?php echo htmlspecialchars($empresa['nome_responsavel'] . ' / ' . $empresa['razao_social']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label for="descricao" class="form-label">Descrição</label>
                            <input type="text" class="form-control" id="descricao" name="descricao" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="valor" class="form-label">Valor</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" step="0.01" class="form-control" id="valor" name="valor" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                             <label for="tipo" class="form-label">Tipo</label>
                            <select id="tipo" name="tipo" class="form-select" required>
                                <option value="receita">Receita (Entrada)</option>
                                <option value="despesa">Despesa (Saída)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="data_vencimento" class="form-label">Data Vencimento</label>
                            <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Lançamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarLancamento" tabindex="-1" aria-labelledby="modalEditarLancamentoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarLancamentoLabel">Editar Lançamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <input type="hidden" name="action" value="editar_lancamento">
                <input type="hidden" name="id_lancamento" id="edit_id_lancamento">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="edit_id_empresa" class="form-label">Empresa</label>
                            <select id="edit_id_empresa" name="id_empresa" class="form-select" required>
                                <option value="" selected disabled>Selecione a empresa...</option>
                                <?php foreach ($empresas_modal as $empresa): ?>
                                    <option value='<?php echo $empresa['id']; ?>'>
                                        <?php echo htmlspecialchars($empresa['nome_responsavel'] . ' / ' . $empresa['razao_social']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-danger">Atenção: Mudar a empresa afetará a visibilidade para o cliente associado.</small>
                        </div>

                        <div class="col-md-12">
                            <label for="edit_descricao" class="form-label">Descrição</label>
                            <input type="text" class="form-control" id="edit_descricao" name="descricao" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="edit_valor" class="form-label">Valor</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" step="0.01" class="form-control" id="edit_valor" name="valor" required>
                            </div>
                        </div>

                        <div class="col-md-4">
                             <label for="edit_tipo" class="form-label">Tipo</label>
                            <select id="edit_tipo" name="tipo" class="form-select" required>
                                <option value="receita">Receita (Entrada)</option>
                                <option value="despesa">Despesa (Saída)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="edit_data_vencimento" class="form-label">Data Vencimento</label>
                            <input type="date" class="form-control" id="edit_data_vencimento" name="data_vencimento" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<?php // --- Modais: Reverter Contestação (APENAS ADMIN/CONTADOR) --- ?>
<?php if (isAdmin() || isContador()): ?>
    <?php foreach ($lancamentos as $lanc): ?>
    <?php if ($lanc['status'] == 'contestado'): ?>
    <div class="modal fade" id="modalReverterContestacao_<?php echo $lanc['id']; ?>" tabindex="-1" aria-labelledby="modalReverterContestacaoLabel_<?php echo $lanc['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalReverterContestacaoLabel_<?php echo $lanc['id']; ?>">Reverter Contestação</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="reverter_contestacao">
                    <input type="hidden" name="id_lancamento" value="<?php echo $lanc['id']; ?>">
                    <div class="modal-body">
                        <p class="mb-1"><strong>Lançamento:</strong> <?php echo htmlspecialchars($lanc['descricao']); ?></p>
                        <p class="mb-3"><strong>Status Atual:</strong> <span class="badge bg-danger">Contestado</span></p>
                        
                        <?php if (!empty($lanc['observacao_contestacao'])): ?>
                        <div class="alert alert-info py-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Motivo da Contestação: **<?php echo htmlspecialchars($lanc['observacao_contestacao']); ?>**
                        </div>
                        <?php endif; ?>

                        <hr>
                        <p>Ao reverter, o lançamento voltará ao status **Pendente**.</p>
                        
                        <div class="mb-3">
                            <label for="motivo_reversao_<?php echo $lanc['id']; ?>" class="form-label">Motivo da Reversão (Opcional para Log):</label>
                            <textarea class="form-control" id="motivo_reversao_<?php echo $lanc['id']; ?>" name="motivo_reversao" rows="3" placeholder="Ex: Cliente forneceu o comprovante posteriormente."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Reversão para Pendente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>


<?php // --- Modais: Confirmação Detalhada do Cliente (CLIENTE) --- ?>
<?php if (isClient()): ?>
    <?php foreach ($lancamentos as $lanc): ?>
    <?php if ($lanc['status'] == 'pendente'): // Somente para lançamentos pendentes ?>
    <div class="modal fade" id="modalConfirmarPagamento_<?php echo $lanc['id']; ?>" tabindex="-1" aria-labelledby="modalConfirmarPagamentoLabel_<?php echo $lanc['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalConfirmarPagamentoLabel_<?php echo $lanc['id']; ?>">Detalhes da Confirmação de Pagamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="confirmar_pagamento_cliente">
                    <input type="hidden" name="id_lancamento" value="<?php echo $lanc['id']; ?>">
                    <div class="modal-body">
                        <p>Lançamento: <strong><?php echo htmlspecialchars($lanc['descricao']); ?></strong></p>
                        <p>Valor: <span class="fw-bold text-success">R$ <?php echo number_format($lanc['valor'], 2, ',', '.'); ?></span></p>
                        <hr>
                        <p class="mb-3">Por favor, informe quando e como o pagamento foi realizado.</p>

                        <div class="mb-3">
                            <label for="data_pagamento_cliente_<?php echo $lanc['id']; ?>" class="form-label">Data do Pagamento:</label>
                            <input type="date" class="form-control" id="data_pagamento_cliente_<?php echo $lanc['id']; ?>" name="data_pagamento_cliente" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="metodo_pagamento_<?php echo $lanc['id']; ?>" class="form-label">Método de Pagamento:</label>
                            <select class="form-select" id="metodo_pagamento_<?php echo $lanc['id']; ?>" name="metodo_pagamento" required>
                                <option value="" disabled selected>Selecione o método...</option>
                                <option value="Pix">Pix</option>
                                <option value="Boleto">Boleto (Compensado)</option>
                                <option value="Transferência">Transferência Bancária (TED/DOC)</option>
                                <option value="Cartão">Cartão de Crédito/Débito</option>
                                <option value="Outro">Outro</option>
                            </select>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Confirmar e Enviar para Baixa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>


<?php // --- Modais: Contestar (CLIENTE) --- ?>
<?php if (isClient()): ?>
    <?php foreach ($lancamentos as $lanc): // Re-looping apenas para gerar os modais ?>
    <?php if ($lanc['status'] == 'pendente' || $lanc['status'] == 'confirmado_cliente'): // Pode contestar se estiver pendente ou confirmado ?>
    <div class="modal fade" id="modalContestar_<?php echo $lanc['id']; ?>" tabindex="-1" aria-labelledby="modalContestarLabel_<?php echo $lanc['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalContestarLabel_<?php echo $lanc['id']; ?>">Contestar Lançamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="contestar_lancamento">
                    <input type="hidden" name="id_lancamento" value="<?php echo $lanc['id']; ?>">
                    <div class="modal-body">
                        <p><strong>Descrição:</strong> <?php echo htmlspecialchars($lanc['descricao']); ?></p>
                        <p><strong>Valor:</strong> R$ <?php echo number_format($lanc['valor'], 2, ',', '.'); ?></p>
                        <hr>
                        <div class="mb-3">
                            <label for="motivo_contestacao_<?php echo $lanc['id']; ?>" class="form-label">Motivo da Contestação:</label>
                            <textarea class="form-control" id="motivo_contestacao_<?php echo $lanc['id']; ?>" name="motivo_contestacao" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Confirmar Contestação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>