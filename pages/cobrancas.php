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

global $pdo;
$cobrancas = [];
// detect company column name at runtime (empresa_id or id_empresa)
$company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';

if (isClient()) {
    // --- LÓGICA DE FILTROS PARA CLIENTE ---
    $id_cliente_logado = $_SESSION['id_cliente_associado'];

    // Busca empresas do cliente para o filtro
    $stmt_empresas_cliente = $pdo->prepare("SELECT id, razao_social FROM empresas WHERE id_cliente = ? ORDER BY razao_social");
    $stmt_empresas_cliente->execute([$id_cliente_logado]);
    $empresas_cliente = $stmt_empresas_cliente->fetchAll();

    // Carrega formas de pagamento para o select (visão cliente)
    $stmt_formas_cliente = $pdo->query("SELECT id, nome FROM formas_pagamento WHERE ativo = 1 ORDER BY nome");
    $formas_pagamento = $stmt_formas_cliente->fetchAll();

    // Captura os filtros da URL
    $filtro_tipo_data = $_GET['tipo_data'] ?? 'vencimento';
    $filtro_data_inicio = $_GET['data_inicio'] ?? null;
    $filtro_data_fim = $_GET['data_fim'] ?? null;
    $filtro_empresa_id = $_GET['id_empresa'] ?? null;
    $filtro_pag_inicio = $_GET['pag_inicio'] ?? null; // data de pagamento inicio
    $filtro_pag_fim = $_GET['pag_fim'] ?? null;       // data de pagamento fim
    $filtro_comp_inicio = $_GET['comp_inicio'] ?? null; // data de competencia inicio (aceita YYYY-MM)
    $filtro_comp_fim = $_GET['comp_fim'] ?? null;       // data de competencia fim (aceita YYYY-MM)
    $filtro_forma_pag = $_GET['forma_pagamento'] ?? null; // forma de pagamento

    // Normaliza valores para inputs do tipo "month" (YYYY-MM)
    $comp_inicio_input = '';
    $comp_fim_input = '';
    if (!empty($filtro_comp_inicio)) {
        if (preg_match('/^\d{4}-\d{2}$/', $filtro_comp_inicio)) $comp_inicio_input = $filtro_comp_inicio;
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_comp_inicio)) $comp_inicio_input = substr($filtro_comp_inicio,0,7);
        else $comp_inicio_input = $filtro_comp_inicio;
    }
    if (!empty($filtro_comp_fim)) {
        if (preg_match('/^\d{4}-\d{2}$/', $filtro_comp_fim)) $comp_fim_input = $filtro_comp_fim;
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_comp_fim)) $comp_fim_input = substr($filtro_comp_fim,0,7);
        else $comp_fim_input = $filtro_comp_fim;
    }

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
        $where_conditions_cliente[] = "cob.`" . $company_col . "` = ?";
        $params[] = $filtro_empresa_id;
    }
    // filtro por data de pagamento
    if (!empty($filtro_pag_inicio) && !empty($filtro_pag_fim)) {
        $where_conditions_cliente[] = "cob.data_pagamento BETWEEN ? AND ?";
        $params[] = $filtro_pag_inicio;
        $params[] = $filtro_pag_fim;
    } elseif (!empty($filtro_pag_inicio)) {
        $where_conditions_cliente[] = "cob.data_pagamento >= ?";
        $params[] = $filtro_pag_inicio;
    } elseif (!empty($filtro_pag_fim)) {
        $where_conditions_cliente[] = "cob.data_pagamento <= ?";
        $params[] = $filtro_pag_fim;
    }
    // filtro por competencia (aceita YYYY-MM no input). Normaliza para datas completas
    if (!empty($filtro_comp_inicio)) {
        if (preg_match('/^\d{4}-\d{2}$/', $filtro_comp_inicio)) {
            $filtro_comp_inicio = $filtro_comp_inicio . '-01';
        }
    }
    if (!empty($filtro_comp_fim)) {
        if (preg_match('/^\d{4}-\d{2}$/', $filtro_comp_fim)) {
            $filtro_comp_fim = date('Y-m-t', strtotime($filtro_comp_fim . '-01'));
        }
    }
    if (!empty($filtro_comp_inicio) && !empty($filtro_comp_fim)) {
        $where_conditions_cliente[] = "cob.data_competencia BETWEEN ? AND ?";
        $params[] = $filtro_comp_inicio;
        $params[] = $filtro_comp_fim;
    } elseif (!empty($filtro_comp_inicio)) {
        $where_conditions_cliente[] = "cob.data_competencia >= ?";
        $params[] = $filtro_comp_inicio;
    } elseif (!empty($filtro_comp_fim)) {
        $where_conditions_cliente[] = "cob.data_competencia <= ?";
        $params[] = $filtro_comp_fim;
    }
    // filtro por forma de pagamento
    if (!empty($filtro_forma_pag)) {
        $where_conditions_cliente[] = "cob.id_forma_pagamento = ?";
        $params[] = $filtro_forma_pag;
    }

    // Paginação (cliente)
    $count_sql = "SELECT COUNT(1) FROM cobrancas cob JOIN empresas emp ON cob.`" . $company_col . "` = emp.id WHERE emp.id_cliente = ? " . (!empty($where_conditions_cliente) ? " AND " . implode(" AND ", $where_conditions_cliente) : "");
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_items = (int)$stmt_count->fetchColumn();

    $per_page = 12; // cards por página
    $page_num = max(1, intval($_GET['page_num'] ?? 1));
    $offset = ($page_num - 1) * $per_page;

    $sql = "SELECT cob.*, emp.razao_social, emp.cnpj, fp.nome as forma_pagamento_nome, fp.icone_bootstrap, tc.nome as tipo_cobranca_nome
        FROM cobrancas cob
        JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
        JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
        LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
        WHERE emp.id_cliente = ? " . (!empty($where_conditions_cliente) ? " AND " . implode(" AND ", $where_conditions_cliente) : "") . "
        ORDER BY cob.data_vencimento ASC, cob.id DESC
        LIMIT ? OFFSET ?";

    $params_for_query = $params;
    $params_for_query[] = $per_page;
    $params_for_query[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params_for_query);
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
    $filtro_pag_inicio = $_GET['pag_inicio'] ?? null;
    $filtro_pag_fim = $_GET['pag_fim'] ?? null;
    $filtro_comp_inicio = $_GET['comp_inicio'] ?? null;
    $filtro_comp_fim = $_GET['comp_fim'] ?? null;

    // Normaliza valores para inputs do tipo "month" (YYYY-MM) — Admin/Contador
    $comp_inicio_input = '';
    $comp_fim_input = '';
    if (!empty($filtro_comp_inicio)) {
        if (preg_match('/^\d{4}-\d{2}$/', $filtro_comp_inicio)) $comp_inicio_input = $filtro_comp_inicio;
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_comp_inicio)) $comp_inicio_input = substr($filtro_comp_inicio,0,7);
        else $comp_inicio_input = $filtro_comp_inicio;
    }
    if (!empty($filtro_comp_fim)) {
        if (preg_match('/^\d{4}-\d{2}$/', $filtro_comp_fim)) $comp_fim_input = $filtro_comp_fim;
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_comp_fim)) $comp_fim_input = substr($filtro_comp_fim,0,7);
        else $comp_fim_input = $filtro_comp_fim;
    }
    $filtro_forma_pag = $_GET['forma_pagamento'] ?? null;
    $filtro_empresa_id = $_GET['id_empresa'] ?? null; // novo filtro por empresa

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

    if ($filtro_empresa_id) {
        $where_conditions[] = "cob.`" . $company_col . "` = ?";
        $params[] = $filtro_empresa_id;
    }

    // Aplicar scoping por empresa atual (se selecionada)
    $company_clause = company_where_clause('cob');
    if (!empty($company_clause['sql'])) {
        $where_conditions[] = $company_clause['sql'];
        $params = array_merge($params, $company_clause['params']);
    }

    // pagamento
    if (!empty($filtro_pag_inicio) && !empty($filtro_pag_fim)) {
        $where_conditions[] = "cob.data_pagamento BETWEEN ? AND ?";
        $params[] = $filtro_pag_inicio;
        $params[] = $filtro_pag_fim;
    } elseif (!empty($filtro_pag_inicio)) {
        $where_conditions[] = "cob.data_pagamento >= ?";
        $params[] = $filtro_pag_inicio;
    } elseif (!empty($filtro_pag_fim)) {
        $where_conditions[] = "cob.data_pagamento <= ?";
        $params[] = $filtro_pag_fim;
    }

    // competencia
    if (!empty($filtro_comp_inicio) && !empty($filtro_comp_fim)) {
        $where_conditions[] = "cob.data_competencia BETWEEN ? AND ?";
        $params[] = $filtro_comp_inicio;
        $params[] = $filtro_comp_fim;
    } elseif (!empty($filtro_comp_inicio)) {
        $where_conditions[] = "cob.data_competencia >= ?";
        $params[] = $filtro_comp_inicio;
    } elseif (!empty($filtro_comp_fim)) {
        $where_conditions[] = "cob.data_competencia <= ?";
        $params[] = $filtro_comp_fim;
    }

    // forma de pagamento
    if (!empty($filtro_forma_pag)) {
        $where_conditions[] = "cob.id_forma_pagamento = ?";
        $params[] = $filtro_forma_pag;
    }

    if ($filtro_empresa_id) {
        $where_conditions[] = "cob.`" . $company_col . "` = ?";
        $params[] = $filtro_empresa_id;
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

    $sql_cobrancas = "SELECT cob.*, emp.razao_social, emp.id_cliente, fp.nome as forma_pagamento_nome, fp.icone_bootstrap, tc.nome as tipo_cobranca_nome, cli.nome_responsavel as cliente_nome
                      FROM cobrancas cob
                      JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
                      JOIN clientes cli ON emp.id_cliente = cli.id
                      JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
                      LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
                      $where_sql
                      ORDER BY cob.data_vencimento DESC";
    $stmt_cobrancas = $pdo->prepare($sql_cobrancas);
    $stmt_cobrancas->execute($params);
    $cobrancas_admin = $stmt_cobrancas->fetchAll();

    // Se um cliente foi filtrado, recarrega a lista de empresas para que o select de empresas mostre apenas as empresas daquele cliente
    if (!empty($filtro_cliente_id)) {
        $stmt_empresas = $pdo->prepare("SELECT e.id, e.razao_social, c.nome_responsavel FROM empresas e JOIN clientes c ON e.id_cliente = c.id WHERE e.id_cliente = ? ORDER BY e.razao_social");
        $stmt_empresas->execute([$filtro_cliente_id]);
        $empresas = $stmt_empresas->fetchAll();
    }
}

?>

<div class="container-fluid">

    <?php if (isClient()): ?>
        <!-- Visão do Cliente -->
        <?php render_page_title('Minhas Cobranças', 'Filtre e visualize cobranças do seu cliente.', 'bi-receipt'); ?>
        <div class="d-grid gap-2 d-md-flex">
                <form method="GET" action="process/export_cobrancas.php" class="m-0">
                    <input type="hidden" name="page" value="cobrancas">
                    <input type="hidden" name="tipo_data" value="<?php echo htmlspecialchars($filtro_tipo_data ?? 'vencimento'); ?>">
                    <input type="hidden" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio ?? ''); ?>">
                    <input type="hidden" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim ?? ''); ?>">
                    <input type="hidden" name="id_empresa" value="<?php echo htmlspecialchars($filtro_empresa_id ?? ''); ?>">
                    <input type="hidden" name="pag_inicio" value="<?php echo htmlspecialchars($filtro_pag_inicio ?? ''); ?>">
                    <input type="hidden" name="pag_fim" value="<?php echo htmlspecialchars($filtro_pag_fim ?? ''); ?>">
                    <input type="hidden" name="comp_inicio" value="<?php echo htmlspecialchars($filtro_comp_inicio ?? ''); ?>">
                    <input type="hidden" name="comp_fim" value="<?php echo htmlspecialchars($filtro_comp_fim ?? ''); ?>">
                    <input type="hidden" name="forma_pagamento" value="<?php echo htmlspecialchars($filtro_forma_pag ?? ''); ?>">
                    <button type="submit" class="btn btn-outline-success btn-full-mobile" title="Exportar CSV com as cobranças filtradas para suas empresas">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar CSV
                    </button>
                </form>
                <!-- Botão de Termo de Quitação removido da visão do cliente: somente Admin/Contador podem gerar -->
            </div>
        </div>
        <!-- Filtros ativos (admin/contador) -->
        <?php
        $activeFiltersAdmin = [];
        // Helper para exibir competência no formato MM/YYYY
        function _fmt_comp_cob($v) {
            if (empty($v)) return '-';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return date('m/Y', strtotime($v));
            if (preg_match('/^\d{4}-\d{2}$/', $v)) return date('m/Y', strtotime($v . '-01'));
            $t = strtotime($v);
            return $t ? date('m/Y', $t) : $v;
        }
        if (!empty($filtro_cliente_id)) $activeFiltersAdmin[] = 'Cliente: ' . htmlspecialchars(array_column($clientes_filtro, 'nome_responsavel', 'id')[$filtro_cliente_id] ?? $filtro_cliente_id);
        if (!empty($filtro_empresa_id)) $activeFiltersAdmin[] = 'Empresa: ' . htmlspecialchars(array_column($empresas, 'razao_social', 'id')[$filtro_empresa_id] ?? $filtro_empresa_id);
        if (!empty($filtro_data_inicio) || !empty($filtro_data_fim)) $activeFiltersAdmin[] = 'Vencimento: ' . htmlspecialchars($filtro_data_inicio ?? '-') . ' → ' . htmlspecialchars($filtro_data_fim ?? '-');
        if (!empty($filtro_pag_inicio) || !empty($filtro_pag_fim)) $activeFiltersAdmin[] = 'Pagamento: ' . htmlspecialchars($filtro_pag_inicio ?? '-') . ' → ' . htmlspecialchars($filtro_pag_fim ?? '-');
        if (!empty($filtro_comp_inicio) || !empty($filtro_comp_fim)) $activeFiltersAdmin[] = 'Mês de competência: ' . htmlspecialchars(_fmt_comp_cob($filtro_comp_inicio) ?? '-') . ' → ' . htmlspecialchars(_fmt_comp_cob($filtro_comp_fim) ?? '-');
        if (!empty($filtro_forma_pag)) $activeFiltersAdmin[] = 'Forma: ' . htmlspecialchars(array_column($formas_pagamento, 'nome', 'id')[$filtro_forma_pag] ?? $filtro_forma_pag);
        if (!empty($activeFiltersAdmin)): ?>
            <div class="mb-3">
                <?php foreach ($activeFiltersAdmin as $f): ?>
                    <span class="badge bg-secondary me-1"><?php echo $f; ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Card de Filtros do Cliente -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-filter me-2"></i> Filtros</span>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapseCobrancasCliente" aria-expanded="false" aria-controls="filtersCollapseCobrancasCliente">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div id="filtersCollapseCobrancasCliente" class="collapse">
            <div class="card-body">
            <form id="form-filtros-cobrancas-cliente" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="cobrancas">
                    <div class="col-md-3">
                        <label for="tipo_data" class="form-label">Filtrar por Data de</label>
                        <select class="form-select" id="tipo_data" name="tipo_data">
                            <option value="vencimento" <?php echo ($filtro_tipo_data == 'vencimento') ? 'selected' : ''; ?>>Vencimento</option>
                            <option value="competencia" <?php echo ($filtro_tipo_data == 'competencia') ? 'selected' : ''; ?>>Mês de competência</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vencimento (entre)</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio ?? ''); ?>" title="Data Início">
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim ?? ''); ?>" title="Data Fim">
                        </div>
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
                    <div class="col-md-3">
                        <label class="form-label">Pagamento (entre)</label>
                        <div class="input-group">
                            <input type="date" class="form-control" name="pag_inicio" value="<?php echo htmlspecialchars($filtro_pag_inicio ?? ''); ?>">
                            <input type="date" class="form-control" name="pag_fim" value="<?php echo htmlspecialchars($filtro_pag_fim ?? ''); ?>">
                        </div>
                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Mês de competência (entre)</label>
                                        <div class="input-group">
                                            <input type="month" class="form-control" name="comp_inicio" value="<?php echo htmlspecialchars($comp_inicio_input ?? ''); ?>">
                                            <input type="month" class="form-control" name="comp_fim" value="<?php echo htmlspecialchars($comp_fim_input ?? ''); ?>">
                                        </div>
                                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" name="forma_pagamento">
                            <option value="">Todas</option>
                            <?php foreach ($formas_pagamento as $fp): ?>
                                <option value="<?php echo $fp['id']; ?>" <?php echo ($filtro_forma_pag == $fp['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fp['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 mt-3 d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search me-2"></i>Filtrar</button>
                        <a href="index.php?page=cobrancas" class="btn btn-outline-secondary">Limpar Filtros</a>
                    </div>
                </form>
            </div>
            </div>
        </div>


        <!-- Filtros ativos (cliente) -->
        <?php
            $activeFilters = [];
            if (!empty($filtro_tipo_data)) $activeFilters[] = 'Tipo Data: ' . htmlspecialchars($filtro_tipo_data);
            if (!empty($filtro_data_inicio) || !empty($filtro_data_fim)) $activeFilters[] = 'Vencimento: ' . htmlspecialchars($filtro_data_inicio ?? '-') . ' → ' . htmlspecialchars($filtro_data_fim ?? '-');
            if (!empty($filtro_empresa_id)) $activeFilters[] = 'Empresa: ' . htmlspecialchars(array_column($empresas_cliente, 'razao_social', 'id')[$filtro_empresa_id] ?? $filtro_empresa_id);
            if (!empty($filtro_pag_inicio) || !empty($filtro_pag_fim)) $activeFilters[] = 'Pagamento: ' . htmlspecialchars($filtro_pag_inicio ?? '-') . ' → ' . htmlspecialchars($filtro_pag_fim ?? '-');
            if (!empty($filtro_comp_inicio) || !empty($filtro_comp_fim)) $activeFilters[] = 'Mês de competência: ' . htmlspecialchars(_fmt_comp_cob($filtro_comp_inicio) ?? '-') . ' → ' . htmlspecialchars(_fmt_comp_cob($filtro_comp_fim) ?? '-');
            if (!empty($filtro_forma_pag)) $activeFilters[] = 'Forma: ' . htmlspecialchars(array_column($formas_pagamento, 'nome', 'id')[$filtro_forma_pag] ?? $filtro_forma_pag);
            if (!empty($activeFilters)): ?>
                <div class="mb-3">
                    <?php foreach ($activeFilters as $f): ?>
                        <span class="badge bg-secondary me-1"><?php echo $f; ?></span>
                    <?php endforeach; ?>
                </div>
        <?php endif; ?>
        


    <div class="card shadow-sm mt-4">
            <div class="card-body">
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
                                        <i class="bi bi-calendar-check me-1"></i> <strong>Mês de competência</strong>
                                        <div><?php echo date('m/Y', strtotime($cobranca['data_competencia'])); ?></div>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <?php if ($cobranca['status_pagamento'] === 'Pago'): ?>
                                        <div class="text-center text-success">
                                            <i class="bi bi-check-circle-fill h4"></i>
                                            <p class="mb-0">Pagamento confirmado!</p>
                                            <div class="mt-2">
                                                <a href="process/crud_handler.php?action=recibo_pagamento&id=<?php echo $cobranca['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-receipt"></i> Recibo
                                                </a>
                                            </div>
                                        </div>
                                    <?php elseif (!empty($cobranca['contexto_pagamento']) && $cobranca['status_pagamento'] === 'Pendente'): ?>
                                        <div class="d-grid gap-2">
                                            <p class="text-center mb-2 small text-muted"><i class="bi <?php echo htmlspecialchars($cobranca['icone_bootstrap']); ?> me-2"></i><?php echo htmlspecialchars($cobranca['forma_pagamento_nome']); ?></p>
                                            <button class="btn btn-primary copy-btn" type="button" data-clipboard-text="<?php echo htmlspecialchars($cobranca['contexto_pagamento']); ?>">
                                                <i class="bi bi-clipboard me-2"></i>Copiar Código de Pagamento
                                            </button>
                                            <?php if (stripos($cobranca['forma_pagamento_nome'], 'Boleto') !== false): ?>
                                                <!-- Removido: preview e botão de visualização do código de barras (implementação anterior removida) -->
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
            </div>
        </div>

    <?php elseif (isAdmin() || isContador()): ?>
        <!-- Visão do Admin/Contador -->
        <?php render_page_title('Gerenciar Cobranças', 'Filtre e gerencie cobranças do sistema.', 'bi-receipt'); ?>
        <div class="d-grid gap-2 d-md-flex">
            <form method="GET" action="process/export_cobrancas.php" class="m-0">
                    <input type="hidden" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                    <input type="hidden" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                    <input type="hidden" name="cliente_id" value="<?php echo htmlspecialchars($filtro_cliente_id); ?>">
                    <input type="hidden" name="id_empresa" value="<?php echo htmlspecialchars($filtro_empresa_id ?? ''); ?>">
                    <input type="hidden" name="pag_inicio" value="<?php echo htmlspecialchars($filtro_pag_inicio ?? ''); ?>">
                    <input type="hidden" name="pag_fim" value="<?php echo htmlspecialchars($filtro_pag_fim ?? ''); ?>">
                    <input type="hidden" name="comp_inicio" value="<?php echo htmlspecialchars($filtro_comp_inicio ?? ''); ?>">
                    <input type="hidden" name="comp_fim" value="<?php echo htmlspecialchars($filtro_comp_fim ?? ''); ?>">
                    <input type="hidden" name="forma_pagamento" value="<?php echo htmlspecialchars($filtro_forma_pag ?? ''); ?>">
                    <button type="submit" class="btn btn-outline-success btn-full-mobile"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar CSV</button>
                    </form>
                
                    <!-- Form para gerar Termo de Quitação para cliente específico (Admin/Contador) -->
                                        <?php if (isAdmin() || isContador()): ?>
                                                <button type="button" id="btn-termo" class="btn btn-outline-primary btn-full-mobile" data-bs-toggle="modal" data-bs-target="#termoModal"><i class="bi bi-file-earmark-text"></i>Termo de Quitação</button>

                                                <!-- Modal Termo de Quitação -->
                                                <div class="modal fade" id="termoModal" tabindex="-1" aria-labelledby="termoModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="termoModalLabel">Gerar Termo de Quitação <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" data-bs-placement="right" title="Ao selecionar Cliente, o termo será gerado com todas as empresas associadas ao cliente selecionado. Ao selecionar Empresa, escolha uma única empresa para gerar o termo somente dela.">?</button></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <form id="termoForm" method="GET" action="process/crud_handler.php" target="_blank">
                                                                    <input type="hidden" name="action" value="termo_quitacao">

                                                                    <div class="mb-2">
                                                                        <label class="form-label">Gerar por:</label>
                                                                        <div>
                                                                            <div class="form-check form-check-inline">
                                                                                <input class="form-check-input" type="radio" name="termo_scope" id="termo_radio_cliente" value="cliente" checked>
                                                                                <label class="form-check-label" for="termo_radio_cliente">Cliente (todas as empresas do cliente)</label>
                                                                            </div>
                                                                            <div class="form-check form-check-inline">
                                                                                <input class="form-check-input" type="radio" name="termo_scope" id="termo_radio_empresa" value="empresa">
                                                                                <label class="form-check-label" for="termo_radio_empresa">Empresa específica</label>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mb-2" id="termo_cliente_group">
                                                                        <label class="form-label">Selecionar Cliente</label>
                                                                        <select name="cliente_id" id="termo_cliente_select" class="form-select form-select-sm">
                                                                            <option value="">Selecione um cliente</option>
                                                                            <?php foreach ($clientes_filtro as $cf): ?>
                                                                                <option value="<?php echo $cf['id']; ?>"><?php echo htmlspecialchars($cf['nome_responsavel']); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>

                                                                    <div class="mb-2" id="termo_empresa_group" style="display:none;">
                                                                        <label class="form-label">Selecionar Empresa</label>
                                                                        <select name="empresa_id" id="termo_empresa_select" class="form-select form-select-sm">
                                                                            <option value="">Selecione uma empresa</option>
                                                                            <?php foreach ($empresas as $em): ?>
                                                                                <option value="<?php echo $em['id']; ?>"><?php echo htmlspecialchars($em['razao_social']); ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>

                                                                    <div class="text-end">
                                                                        <button type="button" class="btn btn-secondary btn-sm me-2" data-bs-dismiss="modal">Fechar</button>
                                                                        <button type="submit" class="btn btn-primary btn-sm">Gerar Termo</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <script>
                                                document.addEventListener('DOMContentLoaded', function(){
                                                    var radioCliente = document.getElementById('termo_radio_cliente');
                                                    var radioEmpresa = document.getElementById('termo_radio_empresa');
                                                    var grupoCliente = document.getElementById('termo_cliente_group');
                                                    var grupoEmpresa = document.getElementById('termo_empresa_group');
                                                    var form = document.getElementById('termoForm');

                                                    function toggleGroups(){
                                                        if(radioEmpresa.checked){
                                                            grupoEmpresa.style.display = 'block';
                                                            grupoCliente.style.display = 'none';
                                                        } else {
                                                            grupoEmpresa.style.display = 'none';
                                                            grupoCliente.style.display = 'block';
                                                        }
                                                    }

                                                    if(radioCliente && radioEmpresa){
                                                        radioCliente.addEventListener('change', toggleGroups);
                                                        radioEmpresa.addEventListener('change', toggleGroups);
                                                        toggleGroups();
                                                    }

                                                    if(form){
                                                        form.addEventListener('submit', function(e){
                                                            // validação simples antes de submeter
                                                            if(radioEmpresa.checked){
                                                                var emp = document.getElementById('termo_empresa_select');
                                                                if(!emp.value){
                                                                    e.preventDefault(); alert('Selecione uma empresa para gerar o termo.'); return false;
                                                                }
                                                            } else {
                                                                var cli = document.getElementById('termo_cliente_select');
                                                                if(!cli.value){
                                                                    e.preventDefault(); alert('Selecione um cliente para gerar o termo.'); return false;
                                                                }
                                                            }
                                                            // se tudo ok, deixa submeter (abre em nova aba)
                                                        });
                                                    }

                                                    // Inicializa tooltips do Bootstrap (se presente)
                                                    try {
                                                        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                                                        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                                                            return new bootstrap.Tooltip(tooltipTriggerEl);
                                                        });
                                                    } catch (err) {
                                                        // Falhar silenciosamente se bootstrap não estiver disponível
                                                    }
                                                });
                                                </script>
                                        <?php endif; ?>
                                        <button class="btn btn-primary btn-full-mobile" data-bs-toggle="modal" data-bs-target="#modalNovaCobranca"><i class="bi bi-plus-circle me-2"></i> Gerar Nova Cobrança</button>
            </div>
        </div>

        <!-- Card de Filtros -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-filter me-2"></i> Filtros</span>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapseCobrancas" aria-expanded="false" aria-controls="filtersCollapseCobrancas">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div id="filtersCollapseCobrancas" class="collapse">
            <div class="card-body">
                <form id="form-filtros-cobrancas-admin" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="cobrancas">
                    <div class="col-md-6">
                        <label class="form-label">Vencimento (entre)</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>" title="Data Início">
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>" title="Data Fim">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="cliente_id" class="form-label">Cliente</label>
                        <select class="form-select" id="cliente_id" name="cliente_id">
                            <option value="">Todos os Clientes</option>
                            <?php foreach ($clientes_filtro as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($filtro_cliente_id == $cliente['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="id_empresa" class="form-label">Empresa</label>
                        <select class="form-select" id="id_empresa" name="id_empresa">
                            <option value="">Todas as Empresas</option>
                            <?php foreach ($empresas as $empresa): ?>
                                <option value="<?php echo $empresa['id']; ?>" <?php echo ($filtro_empresa_id == $empresa['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($empresa['razao_social']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Pagamento (entre)</label>
                        <div class="input-group">
                            <input type="date" class="form-control" name="pag_inicio" value="<?php echo htmlspecialchars($filtro_pag_inicio ?? ''); ?>">
                            <input type="date" class="form-control" name="pag_fim" value="<?php echo htmlspecialchars($filtro_pag_fim ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mês de competência (entre)</label>
                        <div class="input-group">
                            <input type="month" class="form-control" name="comp_inicio" value="<?php echo htmlspecialchars($comp_inicio_input ?? ''); ?>">
                            <input type="month" class="form-control" name="comp_fim" value="<?php echo htmlspecialchars($comp_fim_input ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Forma de Pagamento</label>
                        <select class="form-select" name="forma_pagamento">
                            <option value="">Todas</option>
                            <?php foreach ($formas_pagamento as $fp): ?>
                                <option value="<?php echo $fp['id']; ?>" <?php echo ($filtro_forma_pag == $fp['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fp['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Filtrar</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
                                
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Histórico de Cobranças</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Empresa</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cobrancas_admin)): ?>
                                <tr><td colspan="7" class="text-center">Nenhuma cobrança gerada ainda.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cobrancas_admin as $cobranca): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cobranca['razao_social']); ?></td>
                                        <td><?php echo htmlspecialchars($cobranca['cliente_nome'] ?? ($cobranca['id_cliente'] ?? '')); ?></td>
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
                                                        data-id-empresa="<?php echo $cobranca[$company_col]; ?>"
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
                                                        data-data-vencimento="<?php echo $cobranca['data_vencimento']; ?>"
                                                        data-id-forma-pagamento="<?php echo $cobranca['id_forma_pagamento']; ?>"
                                                        data-contexto-pagamento="<?php echo htmlspecialchars($cobranca['contexto_pagamento'] ?? ''); ?>">
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
                        <div class="col-md-3 mb-3"><label for="data_competencia" class="form-label">Mês de competência</label><input type="month" class="form-control" id="data_competencia" name="data_competencia" required value="<?php echo date('Y-m'); ?>"></div>
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
                        <div class="col-md-3 mb-3"><label for="edit_data_competencia" class="form-label">Mês de competência</label><input type="month" class="form-control" id="edit_data_competencia" name="data_competencia" required></div>
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
                    <div class="mb-3">
                        <label for="confirm_id_forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="confirm_id_forma_pagamento" name="id_forma_pagamento" required>
                            <option value="" disabled>Selecione a forma de pagamento...</option>
                            <?php foreach ($formas_pagamento as $fp): ?>
                                <option value="<?php echo $fp['id']; ?>"><?php echo htmlspecialchars($fp['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_contexto_pagamento" class="form-label">Contexto do Pagamento (opcional)</label>
                        <textarea class="form-control" id="confirm_contexto_pagamento" name="contexto_pagamento" rows="3" placeholder="Cole a chave PIX, código do boleto, link, etc."></textarea>
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
            const contexto = button.getAttribute('data-contexto') || '';
            const titulo = button.getAttribute('data-titulo') || 'Detalhes do Pagamento';

            const modalTitle = modalVerContexto.querySelector('.modal-title');
            const modalBody = modalVerContexto.querySelector('.contexto-pagamento-modal');

            // Apenas mostrar o texto do contexto — removida toda a lógica de geração/preview de boleto
            modalTitle.textContent = titulo;
            modalBody.textContent = contexto;
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

            // Pre-fill forma de pagamento and contexto if provided on the triggering button
            const idForma = button.getAttribute('data-id-forma-pagamento');
            const contexto = button.getAttribute('data-contexto-pagamento') || '';
            const selectForma = modalConfirmarPagamento.querySelector('#confirm_id_forma_pagamento');
            if (selectForma && idForma) {
                // Try to set the value; if option doesn't exist, append it (fallback)
                const opt = selectForma.querySelector(`option[value="${idForma}"]`);
                if (opt) {
                    selectForma.value = idForma;
                } else {
                    const appended = document.createElement('option');
                    appended.value = idForma;
                    appended.text = 'Forma (não encontrada)';
                    selectForma.appendChild(appended);
                    selectForma.value = idForma;
                }
            }
            const txtContexto = modalConfirmarPagamento.querySelector('#confirm_contexto_pagamento');
            if (txtContexto) txtContexto.value = contexto;
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
        // Normalize competencia for month input (YYYY-MM expected)
        try {
            var editCompEl = modalEditarCobranca.querySelector('#edit_data_competencia');
            if (editCompEl) {
                var compVal = '';
                if (data_competencia) {
                    if (/^\d{4}-\d{2}-\d{2}$/.test(data_competencia)) compVal = data_competencia.substr(0,7);
                    else if (/^\d{4}-\d{2}$/.test(data_competencia)) compVal = data_competencia;
                }
                editCompEl.value = compVal;
            }
        } catch (e) {
            console.warn('Erro ao popular data_competencia no modal de cobrança:', e);
        }
        modalEditarCobranca.querySelector('#edit_data_vencimento').value = data_vencimento;
        modalEditarCobranca.querySelector('#edit_valor').value = valor;
        modalEditarCobranca.querySelector('#edit_id_forma_pagamento').value = id_forma_pagamento;
        modalEditarCobranca.querySelector('#edit_id_tipo_cobranca').value = id_tipo_cobranca;
        modalEditarCobranca.querySelector('#edit_descricao').value = descricao;
        modalEditarCobranca.querySelector('#edit_contexto_pagamento').value = contexto;
    });
});
</script>