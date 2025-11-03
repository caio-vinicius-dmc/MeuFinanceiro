<?php
// pages/lancamentos.php
global $pdo;

// Redireciona se o usuário não tiver acesso a lançamentos
if (!hasLancamentosAccess()) {
    header("Location: " . base_url('index.php?page=dashboard'));
    exit;
}

// Helpers locais para nomes usados nos filtros (reduz chamadas JS/ajax)
function getEmpresaNome($id) {
    global $pdo;
    if (empty($id)) return null;
    $stmt = $pdo->prepare("SELECT razao_social FROM empresas WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

function getFormaPagamentoNome($id) {
    global $pdo;
    if (empty($id)) return null;
    $stmt = $pdo->prepare("SELECT nome FROM formas_pagamento WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}

// --- 1. Capturar e Sanitizar Filtros ---
$filtro_empresa_id = $_GET['id_empresa'] ?? null;
$filtro_status = $_GET['status'] ?? null;
$filtro_venc_inicio = $_GET['venc_inicio'] ?? null;
$filtro_venc_fim = $_GET['venc_fim'] ?? null;
$filtro_pag_inicio = $_GET['pag_inicio'] ?? null; // novo filtro: data de pagamento inicio
$filtro_pag_fim = $_GET['pag_fim'] ?? null;     // novo filtro: data de pagamento fim
$filtro_comp_inicio = $_GET['comp_inicio'] ?? null; // novo filtro: data de competência inicio
$filtro_comp_fim = $_GET['comp_fim'] ?? null;       // novo filtro: data de competência fim
$filtro_forma_pag = $_GET['forma_pagamento'] ?? null; // novo filtro: forma de pagamento
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
    // Se o filtro for 'Vencido', ajustamos a lógica para cobrir registros que estão em aberto
    // Observação: o DB pode registrar status como 'pendente', 'Pendente', 'Em aberto' ou até NULL em registros antigos.
    // Aqui usamos LOWER(TRIM(...)) para comparar de forma case-insensitive e também aceitamos NULL como 'em aberto'.
    if ($filtro_status == 'Vencido') {
        $where_conditions[] = "(l.status IS NULL OR LOWER(TRIM(l.status)) IN ('pendente','em aberto')) AND l.data_vencimento < CURDATE()";
    } else {
        $where_conditions[] = "LOWER(TRIM(l.status)) = ?";
        $params[] = strtolower($filtro_status);
    }
}
if (!empty($filtro_venc_inicio)) {
    $where_conditions[] = "l.data_vencimento >= ?";
    $params[] = $filtro_venc_inicio;
}
if (!empty($filtro_venc_fim)) {
    $where_conditions[] = "l.data_vencimento <= ?";
    $params[] = $filtro_venc_fim;
}
// filtro por data de pagamento
if (!empty($filtro_pag_inicio) && !empty($filtro_pag_fim)) {
    $where_conditions[] = "l.data_pagamento BETWEEN ? AND ?";
    $params[] = $filtro_pag_inicio;
    $params[] = $filtro_pag_fim;
} elseif (!empty($filtro_pag_inicio)) {
    $where_conditions[] = "l.data_pagamento >= ?";
    $params[] = $filtro_pag_inicio;
} elseif (!empty($filtro_pag_fim)) {
    $where_conditions[] = "l.data_pagamento <= ?";
    $params[] = $filtro_pag_fim;
}

// filtro por data de competencia
if (!empty($filtro_comp_inicio) && !empty($filtro_comp_fim)) {
    $where_conditions[] = "l.data_competencia BETWEEN ? AND ?";
    $params[] = $filtro_comp_inicio;
    $params[] = $filtro_comp_fim;
} elseif (!empty($filtro_comp_inicio)) {
    $where_conditions[] = "l.data_competencia >= ?";
    $params[] = $filtro_comp_inicio;
} elseif (!empty($filtro_comp_fim)) {
    $where_conditions[] = "l.data_competencia <= ?";
    $params[] = $filtro_comp_fim;
}

// filtro por forma de pagamento
if (!empty($filtro_forma_pag)) {
    $where_conditions[] = "l.id_forma_pagamento = ?";
    $params[] = $filtro_forma_pag;
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
$count_sql = "SELECT COUNT(1) FROM lancamentos l JOIN empresas e ON l.id_empresa = e.id JOIN clientes c ON e.id_cliente = c.id " . $where_sql;
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_items = (int)$stmt_count->fetchColumn();

$per_page = 25;
$page_num = max(1, intval($_GET['page_num'] ?? 1));
$offset = ($page_num - 1) * $per_page;

$sql = "SELECT l.*, e.razao_social, e.id_cliente, c.nome_responsavel 
    FROM lancamentos l
    JOIN empresas e ON l.id_empresa = e.id
    JOIN clientes c ON e.id_cliente = c.id
    $where_sql
    ORDER BY l.data_vencimento DESC
    LIMIT ? OFFSET ?";

$params_for_query = $params;
$params_for_query[] = $per_page;
$params_for_query[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params_for_query);
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
    'pendente' => 'Em aberto',
    'Vencido' => 'Vencido', // Este será tratado dinamicamente no display
    'pago' => 'Pago'
];

// --- 6. Consulta para População de Dropdowns (Formas de Pagamento) ---
$sql_formas_pagamento = "SELECT id, nome FROM formas_pagamento ORDER BY nome";
$stmt_formas_pagamento = $pdo->prepare($sql_formas_pagamento);
$stmt_formas_pagamento->execute();
$formas_pagamento = $stmt_formas_pagamento->fetchAll(PDO::FETCH_ASSOC);
// Função para determinar o status real e a cor do badge
function getLancamentoStatusInfo($lancamento) {
    $today = new DateTime();
    $vencimento = new DateTime($lancamento['data_vencimento']);
    $today->setTime(0, 0, 0);
    $vencimento->setTime(0, 0, 0);
    // Normaliza status e trata NULL/empty como 'pendente' (compatibilidade com registros antigos)
    $rawStatus = $lancamento['status'] ?? '';
    $status = strtolower(trim($rawStatus));
    if ($status === '') {
        $status = 'pendente';
    }

    if ($status === 'pago') {
        if (!empty($lancamento['data_pagamento'])) {
            $data_pagamento = new DateTime($lancamento['data_pagamento']);
            $data_pagamento->setTime(0, 0, 0);
            if ($data_pagamento > $vencimento) {
                return ['text' => 'Pago em Atraso', 'class' => 'bg-warning text-dark'];
            }
        }
        return ['text' => 'Pago', 'class' => 'bg-success'];
    } elseif ($status === 'pendente') {
        if ($vencimento < $today) {
            return ['text' => 'Vencido', 'class' => 'bg-danger'];
        }
        return ['text' => 'Em aberto', 'class' => 'bg-info text-dark'];
    } elseif ($status === 'contestado') {
        return ['text' => 'Contestado', 'class' => 'bg-danger'];
    } elseif ($status === 'confirmado_cliente') {
        return ['text' => 'Confirmado Cliente', 'class' => 'bg-primary'];
    } else {
        return ['text' => ucfirst($status), 'class' => 'bg-secondary']; // Fallback for other statuses
    }
}

?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Lançamentos Financeiros</h3>
    <div class="d-flex gap-2">
        <button id="btn-exportar" class="btn btn-outline-success" title="Exportar dados filtrados para CSV">
            <i class="bi bi-file-earmark-spreadsheet"></i> Exportar
        </button>
        <?php // Todos com acesso podem criar lançamentos ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoLancamento">
            <i class="bi bi-plus-circle"></i> Novo Lançamento
        </button>
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
                <label class="form-label">Pagamento (entre)</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="pag_inicio" value="<?php echo htmlspecialchars($filtro_pag_inicio ?? ''); ?>" title="Pagamento Início">
                    <input type="date" class="form-control" name="pag_fim" value="<?php echo htmlspecialchars($filtro_pag_fim ?? ''); ?>" title="Pagamento Fim">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label">Competência (entre)</label>
                <div class="input-group">
                    <input type="date" class="form-control" name="comp_inicio" value="<?php echo htmlspecialchars($filtro_comp_inicio ?? ''); ?>" title="Competência Início">
                    <input type="date" class="form-control" name="comp_fim" value="<?php echo htmlspecialchars($filtro_comp_fim ?? ''); ?>" title="Competência Fim">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label">Forma de Pagamento</label>
                <select id="forma_pagamento" name="forma_pagamento" class="form-select">
                    <option value="">Todas as Formas</option>
                    <?php foreach ($formas_pagamento as $fp): ?>
                        <option value="<?php echo $fp['id']; ?>" <?php echo ($filtro_forma_pag == $fp['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fp['nome']); ?></option>
                    <?php endforeach; ?>
                </select>
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
<!-- Filtros ativos (pills) -->
<?php
$activeFilters = [];
if (!empty($filtro_empresa_id)) $activeFilters[] = 'Empresa: ' . htmlspecialchars(getEmpresaNome($filtro_empresa_id) ?? $filtro_empresa_id);
if (!empty($filtro_status)) $activeFilters[] = 'Status: ' . htmlspecialchars($filtro_status);
if (!empty($filtro_venc_inicio) || !empty($filtro_venc_fim)) $activeFilters[] = 'Vencimento: ' . htmlspecialchars($filtro_venc_inicio ?? '-') . ' → ' . htmlspecialchars($filtro_venc_fim ?? '-');
if (!empty($filtro_pag_inicio) || !empty($filtro_pag_fim)) $activeFilters[] = 'Pagamento: ' . htmlspecialchars($filtro_pag_inicio ?? '-') . ' → ' . htmlspecialchars($filtro_pag_fim ?? '-');
if (!empty($filtro_comp_inicio) || !empty($filtro_comp_fim)) $activeFilters[] = 'Competência: ' . htmlspecialchars($filtro_comp_inicio ?? '-') . ' → ' . htmlspecialchars($filtro_comp_fim ?? '-');
if (!empty($filtro_forma_pag)) $activeFilters[] = 'Forma: ' . htmlspecialchars(getFormaPagamentoNome($filtro_forma_pag) ?? $filtro_forma_pag);
if (!empty($filtro_valor_min) || !empty($filtro_valor_max)) $activeFilters[] = 'Valor: ' . htmlspecialchars($filtro_valor_min ?? '-') . ' → ' . htmlspecialchars($filtro_valor_max ?? '-');
if (!empty($activeFilters)): ?>
    <div class="mb-3">
        <?php foreach ($activeFilters as $f): ?>
            <span class="badge bg-secondary me-1"><?php echo $f; ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Vencimento</th>
                        <th>Competência</th>
                        <th>Pagamento</th>
                        <th>Forma Pgto.</th>
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
                            <td colspan="8" class="text-center text-muted">Nenhum lançamento encontrado.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($lancamentos as $lanc): ?>
                        <?php 
                        error_log("Lancamento ID: " . $lanc['id'] . ", DB Status: " . $lanc['status']);
                        $status_info = getLancamentoStatusInfo($lanc);
                        error_log("Lancamento ID: " . $lanc['id'] . ", Display Status: " . $status_info['text']);
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($lanc['data_vencimento'])); ?></td>
                            <td><?php echo $lanc['data_competencia'] ? date('d/m/Y', strtotime($lanc['data_competencia'])) : '-'; ?></td>
                            <td><?php echo $lanc['data_pagamento'] ? date('d/m/Y', strtotime($lanc['data_pagamento'])) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($lanc['metodo_pagamento'] ?? '-'); ?></td>
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
                                <span class="badge <?php echo $status_info['class']; ?> fs-6">
                                    <?php echo $status_info['text']; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php // Ações para todos os usuários com acesso ?>
                                <?php if ($lanc['status'] != 'pago'): // Só pode marcar como pago se não estiver pago ?>
                                    <button type="button" class="btn btn-sm btn-success btn-confirmar-pagamento btn-compact" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalConfirmarPagamento"
                                            data-id_lancamento="<?php echo $lanc['id']; ?>"
                                            title="Marcar como Pago" aria-label="Marcar como Pago">
                                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                                    </button>
                                <?php else: // Se estiver pago, pode desfazer o pagamento ?>
                                    <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Deseja desfazer o pagamento deste lançamento? Ele retornará ao status Em aberto.');">
                                        <input type="hidden" name="action" value="atualizar_status_lancamento">
                                        <input type="hidden" name="id_lancamento" value="<?php echo $lanc['id']; ?>">
                                        <input type="hidden" name="status" value="Em aberto"> <!-- Assuming 'pendente' means 'Em aberto' -->
                                        <!-- Botão compacto: exibe só o ícone, com tooltip/aria-label para acesso -->
                                        <button type="submit" class="btn btn-sm btn-outline-warning btn-desfazer" title="Desfazer Pagamento" aria-label="Desfazer Pagamento">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <button class="btn btn-sm btn-outline-secondary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalEditarLancamento"
                                        data-id="<?php echo $lanc['id']; ?>"
                                        data-id_empresa="<?php echo $lanc['id_empresa']; ?>"
                                        data-descricao="<?php echo htmlspecialchars($lanc['descricao']); ?>"
                                        data-valor="<?php echo htmlspecialchars($lanc['valor']); ?>"
                                        data-tipo="<?php echo htmlspecialchars($lanc['tipo']); ?>"
                                                                                data-vencimento="<?php echo htmlspecialchars($lanc['data_vencimento']); ?>"
                                                                                data-data_competencia="<?php echo htmlspecialchars($lanc['data_competencia'] ?? ''); ?>"
                                                                                data-data_pagamento="<?php echo htmlspecialchars($lanc['data_pagamento'] ?? ''); ?>"
                                                                                data-metodo_pagamento="<?php echo htmlspecialchars($lanc['metodo_pagamento'] ?? ''); ?>"
                                                                                data-status="<?php echo htmlspecialchars($lanc['status']); ?>"                                        title="Editar Lançamento">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <a href="process/crud_handler.php?action=excluir_lancamento&id=<?php echo $lanc['id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Tem certeza que deseja excluir este lançamento? Esta ação não pode ser desfeita.');" 
                                   title="Excluir Lançamento">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Paginação -->
<?php if (!empty($total_items)): ?>
    <?php $total_pages = (int)ceil($total_items / $per_page); ?>
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Paginacao Lancamentos" class="mt-3">
            <ul class="pagination">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?php echo ($p == $page_num) ? 'active' : ''; ?>">
                        <?php
                        // Mantém os filtros atuais na querystring
                        $qs = $_GET;
                        $qs['page_num'] = $p;
                        $link = 'index.php?page=lancamentos&' . http_build_query($qs);
                        ?>
                        <a class="page-link" href="<?php echo $link; ?>"><?php echo $p; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

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

                                                    <label for="novo_id_empresa" class="form-label">Empresa</label>

                                                    <select id="novo_id_empresa" name="id_empresa" class="form-select" required>

                                                        <option value="" selected disabled>Selecione a empresa...</option>

                                                        <?php foreach ($empresas_modal as $empresa): ?>

                                                            <option value='<?php echo $empresa['id']; ?>'>

                                                                <?php echo htmlspecialchars($empresa['nome_responsavel'] . ' / ' . $empresa['razao_social']); ?>

                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>

                                                </div>

                        

                                                <div class="col-md-12">

                                                    <label for="novo_descricao" class="form-label">Descrição</label>

                                                    <input type="text" class="form-control" id="novo_descricao" name="descricao" required>

                                                </div>

                                                

                                                <div class="col-md-4">

                                                    <label for="valor" class="form-label">Valor</label>

                                                    <div class="input-group">

                                                        <span class="input-group-text">R$</span>

                                                        <input type="number" step="0.01" class="form-control" id="novo_valor" name="valor" required>

                                                    </div>

                                                </div>

                        

                                                <div class="col-md-4">

                                                     <label for="tipo" class="form-label">Tipo</label>

                                                    <select id="novo_tipo" name="tipo" class="form-select" required>

                                                        <option value="receita">Receita (Entrada)</option>

                                                        <option value="despesa">Despesa (Saída)</option>

                                                    </select>

                                                </div>

                        

                                                <div class="col-md-4">

                                                    <label for="novo_data_vencimento" class="form-label">Data Vencimento</label>

                                                    <input type="date" class="form-control" id="novo_data_vencimento" name="data_vencimento" value="<?php echo date('Y-m-d'); ?>" required>

                                                </div>

                        

                                                <div class="col-md-4">

                                                    <label for="novo_data_competencia" class="form-label">Data Competência</label>

                                                    <input type="date" class="form-control" id="novo_data_competencia" name="data_competencia">

                                                </div>

                        

                                                <div class="col-md-4">


                                                    <label for="novo_metodo_pagamento" class="form-label">Forma de Pagamento</label>

                                                    <select id="novo_metodo_pagamento" name="metodo_pagamento" class="form-select">

                                                        <option value="">Selecione...</option>

                                                        <?php foreach ($formas_pagamento as $forma): ?>

                                                            <option value="<?php echo htmlspecialchars($forma['nome']); ?>"><?php echo htmlspecialchars($forma['nome']); ?></option>

                                                        <?php endforeach; ?>

                                                    </select>

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

                        

                                                <div class="col-md-4">

                                                    <label for="edit_data_competencia" class="form-label">Data Competência</label>

                                                    <input type="date" class="form-control" id="edit_data_competencia" name="data_competencia">

                                                </div>

                                                <div class="col-md-4">

                                                    <label for="edit_data_pagamento" class="form-label">Data Pagamento</label>

                                                    <input type="date" class="form-control" id="edit_data_pagamento" name="data_pagamento">

                                                </div>

                        

                                                <div class="col-md-4">

                                                    <label for="edit_metodo_pagamento" class="form-label">Forma de Pagamento</label>

                                                    <select id="edit_metodo_pagamento" name="metodo_pagamento" class="form-select">

                                                        <option value="">Selecione...</option>

                                                        <?php foreach ($formas_pagamento as $forma): ?>

                                                            <option value="<?php echo htmlspecialchars($forma['nome']); ?>"><?php echo htmlspecialchars($forma['nome']); ?></option>

                                                        <?php endforeach; ?>

                                                    </select>

                                                </div>

                        

                                                <div class="col-md-12">

                                                    <label for="edit_status" class="form-label">Status</label>

                                                    <select id="edit_status" name="status" class="form-select" required>

                                                        <option value="pendente">Em aberto</option>

                                                        <option value="pago">Pago</option>

                                                    </select>

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

                        

                        

                        <?php // --- Modal: Confirmar Pagamento --- ?>

                        <div class="modal fade" id="modalConfirmarPagamento" tabindex="-1" aria-labelledby="modalConfirmarPagamentoLabel" aria-hidden="true">

                            <div class="modal-dialog">

                                <div class="modal-content">

                                    <div class="modal-header">

                                        <h5 class="modal-title" id="modalConfirmarPagamentoLabel">Confirmar Pagamento</h5>

                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                                    </div>

                                    <form action="process/crud_handler.php" method="POST">

                                        <input type="hidden" name="action" value="atualizar_status_lancamento">

                                        <input type="hidden" name="id_lancamento" id="confirm_id_lancamento">

                                        <input type="hidden" name="status" value="Pago">

                                        <div class="modal-body">

                                            <p>Você está prestes a marcar este lançamento como PAGO.</p>

                                            <div class="mb-3">

                                                <label for="confirm_data_pagamento" class="form-label">Data de Pagamento</label>

                                                <input type="date" class="form-control" id="confirm_data_pagamento" name="data_pagamento" value="<?php echo date('Y-m-d'); ?>" required>

                                            </div>

                                            <div class="mb-3">

                                                <label for="confirm_metodo_pagamento" class="form-label">Forma de Pagamento</label>

                                                <select id="confirm_metodo_pagamento" name="metodo_pagamento" class="form-select" required>

                                                    <option value="">Selecione...</option>

                                                    <?php foreach ($formas_pagamento as $forma): ?>

                                                        <option value="<?php echo htmlspecialchars($forma['nome']); ?>"><?php echo htmlspecialchars($forma['nome']); ?></option>

                                                    <?php endforeach; ?>

                                                </select>

                                            </div>

                                        </div>

                                        <div class="modal-footer">

                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>

                                            <button type="submit" class="btn btn-success">Confirmar Pagamento</button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>

                        

                        