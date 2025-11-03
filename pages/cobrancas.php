<?php
// pages/cobrancas.php

// Função para determinar a cor e o texto do status da cobrança
function getStatusInfo($cobranca) {
    switch ($cobranca['status_pagamento']) {
        case 'Pago':
            $date = date('d/m/Y', strtotime($cobranca['data_pagamento']));
            $vencimento = new DateTime($cobranca['data_vencimento']);
            $pagamento = new DateTime($cobranca['data_pagamento']);
            $status_text = ($pagamento > $vencimento) ? "Pago em atraso ($date)" : "Pago em $date";
            return ['class' => 'bg-success', 'text' => $status_text];
        
        case 'Vencido':
            $today = new DateTime();
            $dueDate = new DateTime($cobranca['data_vencimento']);
            $days_overdue = $today->diff($dueDate)->days;
            return ['class' => 'bg-danger', 'text' => "Vencido há $days_overdue dia(s)"];

        case 'Pendente':
            $today = new DateTime();
            $dueDate = new DateTime($cobranca['data_vencimento']);
            $today->setTime(0, 0, 0);
            $dueDate->setTime(0, 0, 0);

            if ($today > $dueDate) {
                $days_overdue = $today->diff($dueDate)->days;
                return ['class' => 'bg-danger', 'text' => "Vencido há $days_overdue dia(s)"];
            }

            $interval = $today->diff($dueDate);
            $daysLeft = $interval->days;

            if ($interval->invert == 1) { // Data de vencimento já passou
                 return ['class' => 'bg-danger', 'text' => 'Vencido'];
            }

            if ($daysLeft > 5) {
                return ['class' => 'bg-primary', 'text' => 'Vence em ' . $daysLeft . ' dias'];
            } elseif ($daysLeft >= 2) {
                return ['class' => 'bg-warning', 'text' => 'Vence em ' . $daysLeft . ' dias'];
            } else {
                return ['class' => 'bg-orange', 'text' => ($daysLeft == 0 ? 'Vence hoje' : 'Vence amanhã')];
            }

        default:
            return ['class' => 'bg-secondary', 'text' => $cobranca['status_pagamento']];
    }
}

$cobrancas = [];

if (isClient()) {
    // --- LÓGICA DE FILTROS PARA CLIENTE ---
    $id_cliente_logado = $_SESSION['id_cliente_associado'];

    // Busca empresas do cliente para o filtro
    $stmt_empresas_cliente = $pdo->prepare("SELECT id, razao_social FROM empresas WHERE id_cliente = ? ORDER BY razao_social");
    $stmt_empresas_cliente->execute([$id_cliente_logado]);
    $empresas_cliente = $stmt_empresas_cliente->fetchAll();

    // Captura os filtros da URL
    $filtro_tipo_data = $_GET['tipo_data'] ?? 'vencimento';
    $filtro_data_inicio = $_GET['data_inicio'] ?? null;
    $filtro_data_fim = $_GET['data_fim'] ?? null;
    $filtro_empresa_id = $_GET['id_empresa'] ?? null;

    // Constrói a query dinâmica
    $params = [$id_cliente_logado];
    $where_conditions_cliente = [];

    if ($filtro_data_inicio && $filtro_data_fim) {
        $date_column = ($filtro_tipo_data == 'competencia') ? 'cob.data_competencia' : 'cob.data_vencimento';
        $where_conditions_cliente[] = "$date_column BETWEEN ? AND ?";
        $params[] = $filtro_data_inicio;
        $params[] = $filtro_data_fim;
    }
    if ($filtro_empresa_id) {
        $where_conditions_cliente[] = "cob.id_empresa = ?";
        $params[] = $filtro_empresa_id;
    }

    $sql = "SELECT cob.*, emp.razao_social, emp.cnpj, fp.nome as forma_pagamento_nome, fp.icone_bootstrap, tc.nome as tipo_cobranca_nome
            FROM cobrancas cob
            JOIN empresas emp ON cob.id_empresa = emp.id
            JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
            LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
            WHERE emp.id_cliente = ? " . (!empty($where_conditions_cliente) ? " AND " . implode(" AND ", $where_conditions_cliente) : "") . "
            ORDER BY cob.data_vencimento ASC, cob.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cobrancas = $stmt->fetchAll();

} elseif (isAdmin() || isContador()) {
    // Lógica para Admin/Contador (formulário e tabela)
    $stmt_empresas = $pdo->query("SELECT e.id, e.razao_social, c.nome_responsavel FROM empresas e JOIN clientes c ON e.id_cliente = c.id ORDER BY e.razao_social");
    $empresas = $stmt_empresas->fetchAll();
    $stmt_formas = $pdo->query("SELECT id, nome FROM formas_pagamento WHERE ativo = 1 ORDER BY nome");
    $formas_pagamento = $stmt_formas->fetchAll();
    $stmt_clientes = $pdo->query("SELECT id, nome_responsavel FROM clientes ORDER BY nome_responsavel");
    $clientes_filtro = $stmt_clientes->fetchAll();
    $stmt_tipos = $pdo->query("SELECT id, nome FROM tipos_cobranca WHERE ativo = 1 ORDER BY nome");
    $tipos_cobranca = $stmt_tipos->fetchAll();

    // Lógica de Filtros
    // Por padrão não pré-preenche as datas — quando vazias, carrega todas as cobranças
    $filtro_data_inicio = $_GET['data_inicio'] ?? null;
    $filtro_data_fim = $_GET['data_fim'] ?? null;
    $filtro_cliente_id = $_GET['cliente_id'] ?? null;

    $where_conditions = [];
    $params = [];

    // Aplica filtro de data apenas se ambas as datas forem fornecidas
    if ($filtro_data_inicio && $filtro_data_fim) {
        $where_conditions[] = "cob.data_vencimento BETWEEN ? AND ?";
        $params[] = $filtro_data_inicio;
        $params[] = $filtro_data_fim;
    }

    if ($filtro_cliente_id) {
        $where_conditions[] = "emp.id_cliente = ?";
        $params[] = $filtro_cliente_id;
    }

    // Lógica de permissão para Contador
    if (isContador()) {
        $stmt_clientes_assoc = $pdo->prepare("SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
        $stmt_clientes_assoc->execute([$_SESSION['user_id']]);
        $clientes_permitidos_ids = $stmt_clientes_assoc->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($clientes_permitidos_ids)) {
            $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
            $where_conditions[] = "emp.id_cliente IN ($placeholders)";
            $params = array_merge($params, $clientes_permitidos_ids);
        } else {
            // Se o contador não tem clientes, não mostra nada
            $where_conditions[] = "1=0";
        }
    }

    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = "WHERE " . implode(' AND ', $where_conditions);
    }

    $sql_cobrancas = "SELECT cob.*, emp.razao_social, fp.nome as forma_pagamento_nome, fp.icone_bootstrap, tc.nome as tipo_cobranca_nome 
                      FROM cobrancas cob
                      JOIN empresas emp ON cob.id_empresa = emp.id
                      JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
                      LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
                      $where_sql
                      ORDER BY cob.data_vencimento DESC";
    $stmt_cobrancas = $pdo->prepare($sql_cobrancas);
    $stmt_cobrancas->execute($params);
    $cobrancas_admin = $stmt_cobrancas->fetchAll();
}

?>

<div class="container-fluid">

    <?php if (isClient()): ?>
        <!-- Visão do Cliente -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Minhas Cobranças</h3>
            <div class="d-flex gap-2">
                <form method="GET" action="process/export_cobrancas.php" class="m-0">
                    <input type="hidden" name="page" value="cobrancas">
                    <input type="hidden" name="tipo_data" value="<?php echo htmlspecialchars($filtro_tipo_data ?? 'vencimento'); ?>">
                    <input type="hidden" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio ?? ''); ?>">
                    <input type="hidden" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim ?? ''); ?>">
                    <input type="hidden" name="id_empresa" value="<?php echo htmlspecialchars($filtro_empresa_id ?? ''); ?>">
                    <button type="submit" class="btn btn-outline-success" title="Exportar CSV com as cobranças filtradas para suas empresas">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- Card de Filtros do Cliente -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-filter me-2"></i>Filtros
            </div>
            <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="cobrancas">
                    <div class="col-md-3">
                        <label for="tipo_data" class="form-label">Filtrar por Data de</label>
                        <select class="form-select" id="tipo_data" name="tipo_data">
                            <option value="vencimento" <?php echo ($filtro_tipo_data == 'vencimento') ? 'selected' : ''; ?>>Vencimento</option>
                            <option value="competencia" <?php echo ($filtro_tipo_data == 'competencia') ? 'selected' : ''; ?>>Competência</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Período Início</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="data_fim" class="form-label">Período Fim</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim ?? ''); ?>">
                    </div>
                    <?php if (count($empresas_cliente) > 1): ?>
                    <div class="col-md-3">
                        <label for="id_empresa" class="form-label">Empresa</label>
                        <select class="form-select" id="id_empresa" name="id_empresa">
                            <option value="">Todas as Empresas</option>
                            <?php foreach ($empresas_cliente as $empresa): ?>
                                <option value="<?php echo $empresa['id']; ?>" <?php echo ($filtro_empresa_id == $empresa['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($empresa['razao_social']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-12 mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-2"></i>Filtrar</button>
                        <a href="index.php?page=cobrancas" class="btn btn-outline-secondary">Limpar Filtros</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row gy-4">
            <?php if (empty($cobrancas)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">Nenhuma cobrança encontrada para você no momento.</div>
                </div>
            <?php else: ?>
                <?php foreach ($cobrancas as $cobranca): ?>
                    <?php $status = getStatusInfo($cobranca); ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm cobranca-card-new">
                            <div class="card-header <?php echo $status['class']; ?> text-white">
                                <h6 class="mb-0 text-center"><?php echo $status['text']; ?></h6>
                            </div>
                            <div class="card-body d-flex flex-column p-4">
                                <div class="mb-3">
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($cobranca['razao_social']); ?></p>
                                    <?php if (!empty($cobranca['tipo_cobranca_nome'])): ?>
                                        <p class="card-text text-muted small">Tipo: <?php echo htmlspecialchars($cobranca['tipo_cobranca_nome']); ?></p>
                                    <?php endif; ?>
                                    <h5 class="card-title"><?php echo htmlspecialchars($cobranca['descricao']); ?></h5>
                                </div>

                                <div class="text-center my-4">
                                    <small class="text-muted">VALOR</small>
                                    <p class="h2 fw-bold text-primary">R$ <?php echo number_format($cobranca['valor'], 2, ',', '.'); ?></p>
                                </div>

                                <div class="row justify-content-center text-center small my-3">
                                    <div class="col-6">
                                        <i class="bi bi-calendar-event me-1"></i> <strong>Vencimento</strong>
                                        <div><?php echo date('d/m/Y', strtotime($cobranca['data_vencimento'])); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <i class="bi bi-calendar-check me-1"></i> <strong>Competência</strong>
                                        <div><?php echo date('m/Y', strtotime($cobranca['data_competencia'])); ?></div>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <?php if ($cobranca['status_pagamento'] === 'Pago'): ?>
                                        <div class="text-center text-success">
                                            <i class="bi bi-check-circle-fill h4"></i>
                                            <p class="mb-0">Pagamento confirmado!</p>
                                        </div>
                                    <?php elseif (!empty($cobranca['contexto_pagamento']) && $cobranca['status_pagamento'] === 'Pendente'): ?>
                                        <div class="d-grid gap-2">
                                            <p class="text-center mb-2 small text-muted"><i class="bi <?php echo htmlspecialchars($cobranca['icone_bootstrap']); ?> me-2"></i><?php echo htmlspecialchars($cobranca['forma_pagamento_nome']); ?></p>
                                            <button class="btn btn-primary copy-btn" type="button" data-clipboard-text="<?php echo htmlspecialchars($cobranca['contexto_pagamento']); ?>">
                                                <i class="bi bi-clipboard me-2"></i>Copiar Código de Pagamento
                                            </button>
                                            <?php if (stripos($cobranca['forma_pagamento_nome'], 'Boleto') !== false): ?>
                                                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalVerContexto" data-contexto="<?php echo htmlspecialchars($cobranca['contexto_pagamento']); ?>" data-titulo="Código de Barras">
                                                    <i class="bi bi-upc-scan me-2"></i>Visualizar Código de Barras
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <small class="text-muted">ID: #<?php echo $cobranca['id']; ?></small>
                                    <div>
                                        <a href="process/crud_handler.php?action=enviar_cobranca_email&id=<?php echo $cobranca['id']; ?>" class="btn btn-sm btn-outline-primary" onclick="return confirm('Enviar cobrança por email para o contato cadastrado?');">
                                            <i class="bi bi-envelope-fill"></i> Enviar por Email
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif (isAdmin() || isContador()): ?>
        <!-- Visão do Admin/Contador -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Gerenciar Cobranças</h3>
            <div class="d-flex gap-2">
                <form method="GET" action="process/export_cobrancas.php" class="m-0">
                    <input type="hidden" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                    <input type="hidden" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                    <input type="hidden" name="cliente_id" value="<?php echo htmlspecialchars($filtro_cliente_id); ?>">
                    <button type="submit" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar CSV</button>
                </form>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaCobranca"><i class="bi bi-plus-circle me-2"></i>Gerar Nova Cobrança</button>
            </div>
        </div>

        <!-- Card de Filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-filter me-2"></i>Filtros
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="cobrancas">
                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Vencimento Início</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="data_fim" class="form-label">Vencimento Fim</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="cliente_id" class="form-label">Cliente</label>
                        <select class="form-select" id="cliente_id" name="cliente_id">
                            <option value="">Todos os Clientes</option>
                            <?php foreach ($clientes_filtro as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($filtro_cliente_id == $cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome_responsavel']); ?>
                                </option>
                                <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Histórico de Cobranças</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Empresa</th><th>Tipo</th><th>Vencimento</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php if (empty($cobrancas_admin)): ?>
                                <tr><td colspan="5" class="text-center">Nenhuma cobrança gerada ainda.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cobrancas_admin as $cobranca): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cobranca['razao_social']); ?></td>
                                        <td><?php echo htmlspecialchars($cobranca['tipo_cobranca_nome'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($cobranca['data_vencimento'])); ?></td>
                                        <td>R$ <?php echo number_format($cobranca['valor'], 2, ',', '.'); ?></td>
                                        <td><span class="badge <?php echo getStatusInfo($cobranca)['class']; ?>"><?php echo getStatusInfo($cobranca)['text']; ?></span></td>
                                        <td>
                                            <?php 
                                            $status_pagamento = $cobranca['status_pagamento'];
                                            
                                            // Botão de Editar: Visível apenas se pendente
                                            if ($status_pagamento === 'Pendente'): ?>
                                                <button class="btn btn-sm btn-warning" title="Editar Cobrança"
                                                        data-bs-toggle="modal" data-bs-target="#modalEditarCobranca"
                                                        data-id="<?php echo $cobranca['id']; ?>"
                                                        data-id-empresa="<?php echo $cobranca['id_empresa']; ?>"
                                                        data-competencia="<?php echo $cobranca['data_competencia']; ?>"
                                                        data-vencimento="<?php echo $cobranca['data_vencimento']; ?>"
                                                        data-valor="<?php echo $cobranca['valor']; ?>"
                                                        data-id-forma-pagamento="<?php echo $cobranca['id_forma_pagamento']; ?>"
                                                        data-id-tipo-cobranca="<?php echo $cobranca['id_tipo_cobranca']; ?>"
                                                        data-descricao="<?php echo htmlspecialchars($cobranca['descricao']); ?>"
                                                        data-contexto="<?php echo htmlspecialchars($cobranca['contexto_pagamento']); ?>">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php // Botão de Reverter: Visível apenas se pago
                                            if ($status_pagamento === 'Pago'): ?>
                                                <form action="process/crud_handler.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="reverter_pago_cobranca">
                                                    <input type="hidden" name="id_cobranca" value="<?php echo $cobranca['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-secondary" title="Reverter para Pendente"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </form>
                                            <?php // Botão de Marcar como Pago: Visível se pendente ou vencido
                                            elseif ($status_pagamento === 'Pendente' || $status_pagamento === 'Vencido'): ?>
                                                <button type="button" class="btn btn-sm btn-success" title="Marcar como Pago"
                                                        data-bs-toggle="modal" data-bs-target="#modalConfirmarPagamento"
                                                        data-id-cobranca="<?php echo $cobranca['id']; ?>"
                                                        data-data-vencimento="<?php echo $cobranca['data_vencimento']; ?>">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php // Botão de Excluir: Visível apenas se pendente
                                            if ($status_pagamento === 'Pendente'): ?>
                                                <a href="process/crud_handler.php?action=excluir_cobranca&id=<?php echo $cobranca['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir esta cobrança? Esta ação não pode ser desfeita.');" title="Excluir Cobrança">
                                                    <i class="bi bi-trash-fill"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                                <!-- Botão Enviar por Email -->
                                                <a href="process/crud_handler.php?action=enviar_cobranca_email&id=<?php echo $cobranca['id']; ?>" class="btn btn-sm btn-outline-primary" title="Enviar por Email" onclick="return confirm('Enviar cobrança por email para o contato cadastrado?');">
                                                    <i class="bi bi-envelope-fill"></i>
                                                </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Nova Cobrança -->
<div class="modal fade" id="modalNovaCobranca" tabindex="-1" aria-labelledby="modalNovaCobrancaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNovaCobrancaLabel">Gerar Nova Cobrança</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="criar_cobranca">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="id_empresa" class="form-label">Empresa</label>
                            <select class="form-select" id="id_empresa" name="id_empresa" required>
                                <option value="" disabled selected>Selecione a empresa...</option>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?php echo $empresa['id']; ?>"><?php echo htmlspecialchars($empresa['razao_social'] . ' (Responsável: ' . $empresa['nome_responsavel'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3"><label for="data_competencia" class="form-label">Data de Competência</label><input type="date" class="form-control" id="data_competencia" name="data_competencia" required></div>
                        <div class="col-md-3 mb-3"><label for="data_vencimento" class="form-label">Data de Vencimento</label><input type="date" class="form-control" id="data_vencimento" name="data_vencimento" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="valor" class="form-label">Valor</label>
                            <div class="input-group"><span class="input-group-text">R$</span><input type="number" class="form-control" id="valor" name="valor" step="0.01" min="0" placeholder="0,00" required></div>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="id_forma_pagamento" class="form-label">Forma de Pagamento</label>
                            <select class="form-select" id="id_forma_pagamento" name="id_forma_pagamento" required>
                                <option value="" disabled selected>Selecione a forma de pagamento...</option>
                                <?php foreach ($formas_pagamento as $forma): ?>
                                    <option value="<?php echo $forma['id']; ?>"><?php echo htmlspecialchars($forma['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="id_tipo_cobranca" class="form-label">Tipo de Cobrança</label>
                            <select class="form-select" id="id_tipo_cobranca" name="id_tipo_cobranca" required>
                                <option value="" disabled selected>Selecione o tipo de cobrança...</option>
                                <?php foreach ($tipos_cobranca as $tipo): ?>
                                    <option value="<?php echo $tipo['id']; ?>"><?php echo htmlspecialchars($tipo['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3"><label for="descricao" class="form-label">Descrição</label><textarea class="form-control" id="descricao" name="descricao" rows="2" placeholder="Ex: Honorários contábeis, serviço de despachante, etc." required></textarea></div>
                    <div class="mb-3">
                        <label for="contexto_pagamento" class="form-label">Contexto do Pagamento (Opcional)</label>
                        <textarea class="form-control" id="contexto_pagamento" name="contexto_pagamento" rows="3" placeholder="Cole aqui a chave PIX, o código de barras do boleto, um link de pagamento, etc."></textarea>
                        <div class="form-text">Esta informação será visível para o cliente no card de cobrança.</div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Gerar Cobrança</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Visualização de Contexto -->
<div class="modal fade" id="modalVerContexto" tabindex="-1" aria-labelledby="modalVerContextoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVerContextoLabel">Detalhes do Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Código para pagamento:</strong></p>
                <div class="contexto-pagamento-modal bg-light p-3 rounded" style="word-wrap: break-word;">
                    
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edição de Cobrança -->
<div class="modal fade" id="modalEditarCobranca" tabindex="-1" aria-labelledby="modalEditarCobrancaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarCobrancaLabel">Editar Cobrança</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="editar_cobranca">
                    <input type="hidden" name="id_cobranca" id="edit_id_cobranca">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_id_empresa" class="form-label">Empresa</label>
                            <select class="form-select" id="edit_id_empresa" name="id_empresa" required>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?php echo $empresa['id']; ?>"><?php echo htmlspecialchars($empresa['razao_social']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3"><label for="edit_data_competencia" class="form-label">Data de Competência</label><input type="date" class="form-control" id="edit_data_competencia" name="data_competencia" required></div>
                        <div class="col-md-3 mb-3"><label for="edit_data_vencimento" class="form-label">Data de Vencimento</label><input type="date" class="form-control" id="edit_data_vencimento" name="data_vencimento" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="edit_valor" class="form-label">Valor</label>
                            <div class="input-group"><span class="input-group-text">R$</span><input type="number" class="form-control" id="edit_valor" name="valor" step="0.01" min="0" required></div>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label for="edit_id_forma_pagamento" class="form-label">Forma de Pagamento</label>
                            <select class="form-select" id="edit_id_forma_pagamento" name="id_forma_pagamento" required>
                                <?php foreach ($formas_pagamento as $forma): ?>
                                    <option value="<?php echo $forma['id']; ?>"><?php echo htmlspecialchars($forma['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="edit_id_tipo_cobranca" class="form-label">Tipo de Cobrança</label>
                            <select class="form-select" id="edit_id_tipo_cobranca" name="id_tipo_cobranca" required>
                                <?php foreach ($tipos_cobranca as $tipo): ?>
                                    <option value="<?php echo $tipo['id']; ?>"><?php echo htmlspecialchars($tipo['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3"><label for="edit_descricao" class="form-label">Descrição</label><textarea class="form-control" id="edit_descricao" name="descricao" rows="2" required></textarea></div>
                    <div class="mb-3">
                        <label for="edit_contexto_pagamento" class="form-label">Contexto do Pagamento (Opcional)</label>
                        <textarea class="form-control" id="edit_contexto_pagamento" name="contexto_pagamento" rows="3"></textarea>
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



<!-- Modal de Confirmação de Pagamento -->
<div class="modal fade" id="modalConfirmarPagamento" tabindex="-1" aria-labelledby="modalConfirmarPagamentoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmarPagamentoLabel">Confirmar Pagamento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="marcar_pago_cobranca">
                    <input type="hidden" name="id_cobranca" id="confirm_id_cobranca">
                    <div class="mb-3">
                        <label for="data_pagamento" class="form-label">Data do Pagamento</label>
                        <input type="date" class="form-control" id="data_pagamento" name="data_pagamento" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.11/dist/clipboard.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var clipboard = new ClipboardJS('.copy-btn');

    clipboard.on('success', function(e) {
        const originalText = e.trigger.innerHTML;
        e.trigger.innerHTML = '<i class="bi bi-check-lg me-2"></i>Copiado!';
        e.trigger.classList.add('btn-success');
        e.trigger.classList.remove('btn-outline-secondary');

        setTimeout(() => {
            e.trigger.innerHTML = originalText;
            e.trigger.classList.remove('btn-success');
            e.trigger.classList.add('btn-outline-secondary');
        }, 2000);
        e.clearSelection();
    });

    clipboard.on('error', function(e) {
        alert('Não foi possível copiar. Por favor, copie manualmente.');
    });

    const modalVerContexto = document.getElementById('modalVerContexto');
    if (modalVerContexto) {
        modalVerContexto.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const contexto = button.getAttribute('data-contexto');
            const titulo = button.getAttribute('data-titulo');
            const isBarcode = titulo.includes('Barras');

            const modalTitle = modalVerContexto.querySelector('.modal-title');
            const modalBody = modalVerContexto.querySelector('.contexto-pagamento-modal');

            modalTitle.textContent = titulo;
            
            if (isBarcode) {
                console.log('Attempting to generate barcode.');
                console.log('Contexto (barcode data):', contexto);

                if (typeof JsBarcode === 'undefined') {
                    console.error('JsBarcode is not defined. Make sure the library is loaded.');
                    modalBody.innerHTML = '<p class="text-danger">Erro: Biblioteca JsBarcode não carregada.</p>';
                    return;
                }

                // Validate contexto for ITF format
                if (!contexto || typeof contexto !== 'string' || !/^\d+$/.test(contexto)) {
                    console.error('Invalid barcode data for ITF format. Expected a string of digits.', contexto);
                    modalBody.innerHTML = '<p class="text-danger">Erro: Dados do código de barras inválidos. Esperado uma sequência de dígitos.</p>';
                    return;
                }

                let barcodeData = contexto;
                if (barcodeData.length % 2 !== 0) {
                    console.warn('Barcode data has an odd number of digits. Padding with a leading zero for ITF format.');
                    barcodeData = '0' + barcodeData;
                }

                // Create a container for the SVG to handle overflow
                const barcodeContainer = document.createElement('div');
                barcodeContainer.style.overflowX = 'auto';
                barcodeContainer.style.padding = '10px 0'; // Add some padding for better appearance
                barcodeContainer.style.display = 'flex'; // Make it a flex container
                barcodeContainer.style.justifyContent = 'center'; // Center items horizontally
                barcodeContainer.style.alignItems = 'center'; // Center items vertically (though not strictly necessary for a single SVG)

                // Create an SVG element for JsBarcode
                const svgElement = document.createElementNS("http://www.w3.org/2000/svg", "svg");
                svgElement.id = "barcode-svg";
                svgElement.style.maxWidth = '100%'; // Ensure SVG scales down
                svgElement.style.height = 'auto'; // Maintain aspect ratio

                barcodeContainer.appendChild(svgElement);
                modalBody.innerHTML = ''; // Clear previous content
                modalBody.appendChild(barcodeContainer); // Append the container


                try {
                    // Generate the barcode
                    JsBarcode("#barcode-svg", barcodeData, { // Use barcodeData here
                        format: "ITF", // Interleaved 2 of 5 is common for Brazilian boletos
                        displayValue: true, // Show the human-readable value below the barcode
                        width: 2, // Adjust width of bars
                        height: 100, // Adjust height of barcode
                        margin: 10,
                        fontSize: 18
                    });
                    console.log('Barcode generated successfully.');
                } catch (error) {
                    console.error('Error generating barcode:', error);
                    modalBody.innerHTML = `<p class="text-danger">Erro ao gerar código de barras: ${error.message || 'Verifique o formato dos dados.'}</p>`;
                }
            } else {
                modalBody.textContent = contexto;
            }
        });
    }

    const modalConfirmarPagamento = document.getElementById('modalConfirmarPagamento');
    if (modalConfirmarPagamento) {
        modalConfirmarPagamento.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const idCobranca = button.getAttribute('data-id-cobranca');
            const dataVencimento = button.getAttribute('data-data-vencimento');

            modalConfirmarPagamento.querySelector('#confirm_id_cobranca').value = idCobranca;
            
            // Set default date to today
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0'); // Months start at 0!
            const dd = String(today.getDate()).padStart(2, '0');
            modalConfirmarPagamento.querySelector('#data_pagamento').value = `${yyyy}-${mm}-${dd}`;
        });
    }

    const modalEditarCobranca = document.getElementById('modalEditarCobranca');
    modalEditarCobranca.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        
        const id = button.getAttribute('data-id');
        const id_empresa = button.getAttribute('data-id-empresa');
        const data_competencia = button.getAttribute('data-competencia');
        const data_vencimento = button.getAttribute('data-vencimento');
        const valor = button.getAttribute('data-valor');
        const id_forma_pagamento = button.getAttribute('data-id-forma-pagamento');
        const id_tipo_cobranca = button.getAttribute('data-id-tipo-cobranca');
        const descricao = button.getAttribute('data-descricao');
        const contexto = button.getAttribute('data-contexto');

        // Popula o formulário
        modalEditarCobranca.querySelector('#edit_id_cobranca').value = id;
        modalEditarCobranca.querySelector('#edit_id_empresa').value = id_empresa;
        modalEditarCobranca.querySelector('#edit_data_competencia').value = data_competencia;
        modalEditarCobranca.querySelector('#edit_data_vencimento').value = data_vencimento;
        modalEditarCobranca.querySelector('#edit_valor').value = valor;
        modalEditarCobranca.querySelector('#edit_id_forma_pagamento').value = id_forma_pagamento;
        modalEditarCobranca.querySelector('#edit_id_tipo_cobranca').value = id_tipo_cobranca;
        modalEditarCobranca.querySelector('#edit_descricao').value = descricao;
        modalEditarCobranca.querySelector('#edit_contexto_pagamento').value = contexto;
    });
});
</script>