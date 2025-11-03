<?php
// pages/dashboard.php
global $pdo;

// --- 1. Lógica de Filtros (Novo filtro id_empresa) ---
$filtro_cliente_id = $_GET['cliente_id'] ?? null;
$filtro_empresa_id = $_GET['id_empresa'] ?? null; 
$filtro_data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$filtro_data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// --- 2. Lógica de Permissão e Construção WHERE/PARAMS ---
$where_conditions = [];
$params = [];
$base_join = "FROM lancamentos l JOIN empresas e ON l.id_empresa = e.id";

// Pre-busca de clientes permitidos (Contador)
$clientes_permitidos_ids = []; 
if (isContador()) {
    $stmt_clientes_assoc = $pdo->prepare("SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
    $stmt_clientes_assoc->execute([$_SESSION['user_id']]);
    $clientes_permitidos_ids = $stmt_clientes_assoc->fetchAll(PDO::FETCH_COLUMN);
}

// Filtro 1: Datas (Sempre aplicado)
// OBS: Este filtro de data_vencimento afeta os CARDS e o GRÁFICO DE STATUS.
$where_conditions[] = "l.data_vencimento BETWEEN ? AND ?";
$params_cards = [$filtro_data_inicio, $filtro_data_fim];

// Filtro 2: Hierarquia de Filtro (Empresa > Cliente > Permissão)
$params_permissoes = [];
if ($filtro_empresa_id) {
    // Prioridade 1: Filtro de Empresa
    $where_conditions[] = "l.id_empresa = ?";
    $params_permissoes[] = $filtro_empresa_id;

    // Garante que a permissão é respeitada, mesmo com o filtro de empresa
    if (isContador() && !empty($clientes_permitidos_ids)) {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $where_conditions[] = "e.id_cliente IN ($placeholders)";
        $params_permissoes = array_merge($params_permissoes, $clientes_permitidos_ids);
    } elseif (isClient()) {
        $where_conditions[] = "e.id_cliente = ?";
        $params_permissoes[] = $_SESSION['id_cliente_associado'];
    }

} elseif ($filtro_cliente_id) {
    // Prioridade 2: Filtro de Cliente
    $where_conditions[] = "e.id_cliente = ?";
    $params_permissoes[] = $filtro_cliente_id;
    
} else {
    // Prioridade 3: Permissão Padrão (Sem filtros)
    if (isContador()) {
        if (empty($clientes_permitidos_ids)) {
            $where_conditions[] = "1=0"; 
        } else {
            $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
            $where_conditions[] = "e.id_cliente IN ($placeholders)";
            $params_permissoes = $clientes_permitidos_ids;
        }
    } elseif (isClient()) {
        $where_conditions[] = "e.id_cliente = ?";
        $params_permissoes[] = $_SESSION['id_cliente_associado'];
    }
}

// Junta todos os parâmetros para as consultas de CARDS e STATUS
$params = array_merge($params_cards, $params_permissoes);
$where_sql = implode(' AND ', $where_conditions);
$where_sql_com_prefixo = "WHERE " . $where_sql;


// --- 3. Consultas para os Cards e Indicadores ---

// 3a. A Receber = Pendente OU Confirmado pelo Cliente
$sql_receber = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'receita' AND l.status IN ('pendente', 'confirmado_cliente')";
$stmt_receber = $pdo->prepare($sql_receber);
$stmt_receber->execute($params);
$total_a_receber = $stmt_receber->fetch()['total'] ?? 0;

// 3b. A Pagar = Pendente OU Confirmado pelo Cliente
$sql_pagar = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'despesa' AND l.status IN ('pendente', 'confirmado_cliente')";
$stmt_pagar = $pdo->prepare($sql_pagar);
$stmt_pagar->execute($params);
$total_a_pagar = $stmt_pagar->fetch()['total'] ?? 0;

// 3c. Saldo Realizado = APENAS o que está 'pago' (baixado)
$sql_pago = "SELECT SUM(CASE WHEN l.tipo = 'receita' THEN valor ELSE -valor END) AS total $base_join $where_sql_com_prefixo AND l.status = 'pago'";
$stmt_pago = $pdo->prepare($sql_pago);
$stmt_pago->execute($params);
$total_pago = $stmt_pago->fetch()['total'] ?? 0;

// 3d. Indicador: Valor Total Contestados (NOVO)
$sql_valor_contest = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo AND l.status = 'contestado'";
$stmt_valor_contest = $pdo->prepare($sql_valor_contest);
$stmt_valor_contest->execute($params);
$total_valor_contestado = $stmt_valor_contest->fetch()['total'] ?? 0;

// 3e. Indicador: Quantidade Contestados (MANTIDO)
$sql_contest_count = "SELECT COUNT(l.id) AS total $base_join $where_sql_com_prefixo AND l.status = 'contestado'";
$stmt_contest_count = $pdo->prepare($sql_contest_count);
$stmt_contest_count->execute($params);
$total_contestado = $stmt_contest_count->fetch()['total'] ?? 0;

// 3f. Indicador: Total de Lançamentos Vencidos no Período (Inclui PAGO e PENDENTE)
$sql_total_vencido = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo";
$stmt_total_vencido = $pdo->prepare($sql_total_vencido);
$stmt_total_vencido->execute($params);
$total_vencido_periodo = $stmt_total_vencido->fetch()['total'] ?? 0;

// 3g. Indicador: Projeção de Saldo (Realizado + (Receber - Pagar))
$projecao_saldo = $total_pago + ($total_a_receber - $total_a_pagar);

// 3h. Indicador: Percentual de Receita Paga
$sql_receita_paga = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'receita' AND l.status = 'pago'";
$stmt_receita_paga = $pdo->prepare($sql_receita_paga);
$stmt_receita_paga->execute($params);
$valor_receita_paga = $stmt_receita_paga->fetch()['total'] ?? 0;

// Total de Receitas no Período
$sql_total_receita_periodo = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'receita'";
$stmt_total_receita_periodo = $pdo->prepare($sql_total_receita_periodo);
$stmt_total_receita_periodo->execute($params);
$total_receita_periodo = $stmt_total_receita_periodo->fetch()['total'] ?? 0;

$percent_pago = ($total_receita_periodo > 0) ? ($valor_receita_paga / $total_receita_periodo) * 100 : 0;

// --- 3i. Indicadores para COBRANÇAS (separados de Lançamentos)
// Constrói WHERE e PARAMS para cobranças respeitando o filtro de data (data_vencimento) e permissões
$where_conditions_cob = [];
$params_cob = [];
$base_join_cob = "FROM cobrancas cob JOIN empresas e ON cob.id_empresa = e.id";

$where_conditions_cob[] = "cob.data_vencimento BETWEEN ? AND ?";
$params_cob[] = $filtro_data_inicio;
$params_cob[] = $filtro_data_fim;

// Aplica hierarquia de filtros (Empresa > Cliente > Permissão) para cobrancas
if ($filtro_empresa_id) {
    $where_conditions_cob[] = "cob.id_empresa = ?";
    $params_cob[] = $filtro_empresa_id;

    if (isContador() && !empty($clientes_permitidos_ids)) {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $where_conditions_cob[] = "e.id_cliente IN ($placeholders)";
        $params_cob = array_merge($params_cob, $clientes_permitidos_ids);
    } elseif (isClient()) {
        $where_conditions_cob[] = "e.id_cliente = ?";
        $params_cob[] = $_SESSION['id_cliente_associado'];
    }

} elseif ($filtro_cliente_id) {
    $where_conditions_cob[] = "e.id_cliente = ?";
    $params_cob[] = $filtro_cliente_id;

} else {
    if (isContador()) {
        if (empty($clientes_permitidos_ids)) {
            $where_conditions_cob[] = "1=0";
        } else {
            $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
            $where_conditions_cob[] = "e.id_cliente IN ($placeholders)";
            $params_cob = array_merge($params_cob, $clientes_permitidos_ids);
        }
    } elseif (isClient()) {
        $where_conditions_cob[] = "e.id_cliente = ?";
        $params_cob[] = $_SESSION['id_cliente_associado'];
    }
}

$where_sql_cob = "WHERE " . implode(' AND ', $where_conditions_cob);

// Consulta: Total a Receber (Cobranças pendentes)
$sql_cob_pendente = "SELECT SUM(cob.valor) AS total $base_join_cob $where_sql_cob AND cob.status_pagamento = 'Pendente'";
$stmt_cob_pendente = $pdo->prepare($sql_cob_pendente);
$stmt_cob_pendente->execute($params_cob);
$total_cobrancas_pendentes = $stmt_cob_pendente->fetch()['total'] ?? 0;

// Consulta: Total Recebido (Cobranças pagas)
$sql_cob_pago = "SELECT SUM(cob.valor) AS total $base_join_cob $where_sql_cob AND cob.status_pagamento = 'Pago'";
$stmt_cob_pago = $pdo->prepare($sql_cob_pago);
$stmt_cob_pago->execute($params_cob);
$total_cobrancas_recebidas = $stmt_cob_pago->fetch()['total'] ?? 0;

// Consulta: Quantidade de Cobranças no Período
$sql_cob_count = "SELECT COUNT(cob.id) AS total $base_join_cob $where_sql_cob";
$stmt_cob_count = $pdo->prepare($sql_cob_count);
$stmt_cob_count->execute($params_cob);
$qtd_cobrancas_periodo = $stmt_cob_count->fetch()['total'] ?? 0;

// Consulta: Total Vencido (Cobranças no período)
$sql_cob_vencido = "SELECT SUM(cob.valor) AS total $base_join_cob $where_sql_cob";
$stmt_cob_vencido = $pdo->prepare($sql_cob_vencido);
$stmt_cob_vencido->execute($params_cob);
$total_cobrancas_vencido = $stmt_cob_vencido->fetch()['total'] ?? 0;


// --- 4. Lógica para o Dropdown de Empresas (MANTIDA) ---
$sql_empresas_dropdown = "SELECT e.id, e.razao_social, c.nome_responsavel 
                        FROM empresas e
                        JOIN clientes c ON e.id_cliente = c.id";
$params_empresas_dropdown = [];
$where_empresas_dropdown = [];

if ($filtro_cliente_id) {
    $where_empresas_dropdown[] = "e.id_cliente = ?";
    $params_empresas_dropdown[] = $filtro_cliente_id;
} else {
    if (isContador()) {
        if (empty($clientes_permitidos_ids)) {
             $where_empresas_dropdown[] = "1=0";
        } else {
            $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
            $where_empresas_dropdown[] = "e.id_cliente IN ($placeholders)";
            $params_empresas_dropdown = $clientes_permitidos_ids;
        }
    } elseif (isClient()) {
        $where_empresas_dropdown[] = "e.id_cliente = ?";
        $params_empresas_dropdown[] = $_SESSION['id_cliente_associado'];
    }
}

if (!empty($where_empresas_dropdown)) {
    $sql_empresas_dropdown .= " WHERE " . implode(' AND ', $where_empresas_dropdown);
}
$sql_empresas_dropdown .= " ORDER BY e.razao_social";
$stmt_empresas_dropdown = $pdo->prepare($sql_empresas_dropdown);
$stmt_empresas_dropdown->execute($params_empresas_dropdown);
$empresas_para_select = $stmt_empresas_dropdown->fetchAll(PDO::FETCH_ASSOC);


// --- 5. Lógica para os Gráficos ---

// 5a. Gráfico de Fluxo de Caixa (Realizado NO PERÍODO FILTRADO)
// O filtro de data aqui deve usar a data_pagamento, mas o filtro de cliente/empresa/permissão é mantido
// O problema era que o filtro de data estava fixo em 15 dias. Agora usa o filtro de interface.

$params_grafico_fluxo = [];
$where_grafico_fluxo_conditions = [];
$base_join_fluxo = "FROM lancamentos l JOIN empresas e ON l.id_empresa = e.id";

// 1. Filtro de Data (Usa data_pagamento para "Realizado")
$where_grafico_fluxo_conditions[] = "l.data_pagamento BETWEEN ? AND ?";
$params_grafico_fluxo[] = $filtro_data_inicio;
$params_grafico_fluxo[] = $filtro_data_fim;

// 2. Status
$where_grafico_fluxo_conditions[] = "l.status = 'pago'";

// 3. Permissão/Filtros (Baseado na lógica da Seção 2, mas sem o filtro de data_vencimento)
$where_fluxo_perm = [];
if ($filtro_empresa_id) {
    $where_fluxo_perm[] = "l.id_empresa = ?";
    $params_grafico_fluxo[] = $filtro_empresa_id;
    // Lógica de permissão de Contador/Cliente aqui, se necessário, adaptada para l.id_empresa
    // ... [replicar a lógica de permissão $params_permissoes aqui, se necessário] ...
} elseif ($filtro_cliente_id) {
    $where_fluxo_perm[] = "e.id_cliente = ?";
    $params_grafico_fluxo[] = $filtro_cliente_id;
} elseif (isContador()) {
    if (empty($clientes_permitidos_ids)) {
         $where_fluxo_perm[] = "1=0";
    } else {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $where_fluxo_perm[] = "e.id_cliente IN ($placeholders)";
        $params_grafico_fluxo = array_merge($params_grafico_fluxo, $clientes_permitidos_ids);
    }
} elseif (isClient()) {
    $where_fluxo_perm[] = "e.id_cliente = ?";
    $params_grafico_fluxo[] = $_SESSION['id_cliente_associado'];
}

$where_grafico_fluxo_conditions = array_merge($where_grafico_fluxo_conditions, $where_fluxo_perm);

$where_grafico_sql = "WHERE " . implode(' AND ', $where_grafico_fluxo_conditions);

$sql_grafico = "
    SELECT 
        l.data_pagamento,
        SUM(CASE WHEN l.tipo = 'receita' THEN l.valor ELSE 0 END) as total_receitas,
        SUM(CASE WHEN l.tipo = 'despesa' THEN l.valor ELSE 0 END) as total_despesas
    $base_join_fluxo
    $where_grafico_sql
    GROUP BY l.data_pagamento
    ORDER BY l.data_pagamento ASC
";
$stmt_grafico = $pdo->prepare($sql_grafico);
$stmt_grafico->execute($params_grafico_fluxo);
$dados_grafico = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

// Geração de Labels e Dados para o período filtrado
$chart_labels = [];
$chart_receitas = [];
$chart_despesas = [];
$dias_no_periodo = [];

$data_atual = new DateTime($filtro_data_inicio);
$data_fim_dt = new DateTime($filtro_data_fim);

while ($data_atual <= $data_fim_dt) {
    $dia = $data_atual->format('Y-m-d');
    $dias_no_periodo[$dia] = ['receitas' => 0, 'despesas' => 0];
    $data_atual->modify('+1 day');
}

// Mapeia os dados do banco
foreach ($dados_grafico as $dado) {
    if (isset($dias_no_periodo[$dado['data_pagamento']])) {
        $dias_no_periodo[$dado['data_pagamento']]['receitas'] = $dado['total_receitas'];
        $dias_no_periodo[$dado['data_pagamento']]['despesas'] = $dado['total_despesas'];
    }
}

// Preenche os arrays finais
foreach ($dias_no_periodo as $dia => $valores) {
    $chart_labels[] = date('d/m', strtotime($dia));
    $chart_receitas[] = $valores['receitas'];
    $chart_despesas[] = $valores['despesas'];
}

$chart_data_json = json_encode([
    'labels' => $chart_labels,
    'receitas' => $chart_receitas,
    'despesas' => $chart_despesas
]);

// 5b. Gráfico de Comparação de Status (MANTIDO, pois já usava o filtro de período)
// Consulta de STATUS para RECEITAS no período FILTRADO
$sql_status_receita = "SELECT 
        l.status, 
        SUM(l.valor) AS total 
    $base_join 
    $where_sql_com_prefixo AND l.tipo = 'receita'
    GROUP BY l.status";
$stmt_status_receita = $pdo->prepare($sql_status_receita);
$stmt_status_receita->execute($params);
$status_data_raw = $stmt_status_receita->fetchAll(PDO::FETCH_ASSOC);

$status_data = [];

// Processa dados brutos para o formato do gráfico
foreach ($status_data_raw as $item) {
    if ($item['status'] == 'pago') {
        $status_data['Pago'] = ($status_data['Pago'] ?? 0) + $item['total'];
    } elseif ($item['status'] == 'contestado') {
        $status_data['Contestado'] = ($status_data['Contestado'] ?? 0) + $item['total'];
    } else {
        // Agrupa 'pendente' e 'confirmado_cliente' como 'A Receber'
        $status_data['A Receber'] = ($status_data['A Receber'] ?? 0) + $item['total'];
    }
}

// Remove entradas zero para evitar gráficos vazios
$status_data = array_filter($status_data, function($value) {
    return $value > 0;
});

// Se não houver receitas, o gráfico deve mostrar zeros ou ser ignorado
$status_chart_labels = array_keys($status_data);
$status_chart_values = array_values($status_data);

$status_chart_json = json_encode([
    'labels' => $status_chart_labels,
    'values' => $status_chart_values
]);
?>

<div class="mb-4">
    <h3>Dashboard</h3>
    <p class="text-muted">Resumo financeiro da sua operação.</p>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-filter me-2"></i> Filtros</span>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="dashboard">
            
            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Vencimento Início</label>
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
            </div>
            
            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Vencimento Fim</label>
                <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
            </div>

            <?php if (isAdmin() || isContador()): ?>
            <div class="col-md-3">
                <label for="cliente_id" class="form-label">Cliente</label>
                <select class="form-select" id="cliente_id" name="cliente_id" onchange="this.form.submit()">
                    <option value="">Todos os Clientes</option>
                    <?php
                    $sql_clientes = "SELECT id, nome_responsavel FROM clientes";
                    $params_clientes = [];
                    if(isContador()) {
                        $sql_clientes = "SELECT c.id, c.nome_responsavel 
                                         FROM clientes c
                                         JOIN contador_clientes_assoc ca ON c.id = ca.id_cliente
                                         WHERE ca.id_usuario_contador = ?";
                        $params_clientes[] = $_SESSION['user_id'];
                    }
                    $stmt_clientes = $pdo->prepare($sql_clientes);
                    $stmt_clientes->execute($params_clientes);
                    
                    foreach ($stmt_clientes->fetchAll() as $cliente) {
                        $selected = ($filtro_cliente_id == $cliente['id']) ? 'selected' : '';
                        echo "<option value='{$cliente['id']}' $selected>" . htmlspecialchars($cliente['nome_responsavel']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="<?php echo (isAdmin() || isContador()) ? 'col-md-3' : 'col-md-3'; ?>">
                <label for="id_empresa" class="form-label">Empresa</label>
                <select class="form-select" id="id_empresa" name="id_empresa">
                    <?php 
                    $show_all = (isAdmin() || isContador()) || (isClient() && count($empresas_para_select) > 1);
                    ?>
                    <?php if ($show_all): ?>
                         <option value="">Todas as empresas</option>
                    <?php endif; ?>
                    
                    <?php foreach ($empresas_para_select as $empresa): ?>
                        <option value="<?php echo $empresa['id']; ?>" <?php echo ($filtro_empresa_id == $empresa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($empresa['razao_social']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Soma total das receitas com data de vencimento no período, que ainda não foram pagas.">
        <div class="card card-metric">
            <i class="bi bi-arrow-down-circle card-metric-icon"></i>
            <div class="metric-title">A Receber (Proj.)</div>
            <div class="metric-value text-warning-emphasis">
                R$ <?php echo number_format($total_a_receber, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Soma total das despesas com data de vencimento no período, que ainda não foram pagas.">
        <div class="card card-metric">
            <i class="bi bi-arrow-up-circle card-metric-icon"></i>
            <div class="metric-title">A Pagar (Proj.)</div>
            <div class="metric-value text-danger-emphasis">
                R$ <?php echo number_format($total_a_pagar, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Saldo líquido dos lançamentos que foram efetivamente pagos (Receitas Pagas - Despesas Pagas) no período.">
        <div class="card card-metric">
             <i class="bi bi-check-circle card-metric-icon"></i>
            <div class="metric-title">Saldo Realizado</div>
            <div class="metric-value <?php echo ($total_pago >= 0) ? 'text-success-emphasis' : 'text-danger-emphasis'; ?>">
                R$ <?php echo number_format($total_pago, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Saldo projetado, somando o Saldo Realizado com o saldo futuro (A Receber - A Pagar) no período.">
        <div class="card card-metric">
             <i class="bi bi-graph-up-arrow card-metric-icon"></i>
            <div class="metric-title">Projeção Total</div>
            <div class="metric-value <?php echo ($projecao_saldo >= 0) ? 'text-success-emphasis' : 'text-danger-emphasis'; ?>">
                R$ <?php echo number_format($projecao_saldo, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Percentual de Receitas que foram pagas em relação ao total de Receitas (Pagos + A Receber + Contestados) no período.">
        <div class="card card-metric">
             <i class="bi bi-percent card-metric-icon"></i>
            <div class="metric-title">% Receita Paga</div>
            <div class="metric-value">
                <?php echo number_format($percent_pago, 1, ',', '.'); ?>%
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Soma do valor de todos os lançamentos que foram marcados como 'Contestados' no período.">
        <div class="card card-metric">
             <i class="bi bi-exclamation-octagon card-metric-icon"></i>
            <div class="metric-title">Valor Contestados</div>
            <div class="metric-value text-danger-emphasis">
                R$ <?php echo number_format($total_valor_contestado, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Contagem de quantos lançamentos foram marcados como 'Contestados' no período.">
        <div class="card card-metric">
            <i class="bi bi-files card-metric-icon"></i>
            <div class="metric-title">Qtd. Contestados</div>
            <div class="metric-value">
                <?php echo $total_contestado; ?>
            </div>
        </div>
    </div>
     <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Soma total de Receitas e Despesas (Pagos e Pendentes) com data de vencimento dentro do período filtrado.">
        <div class="card card-metric">
            <i class="bi bi-calendar-x card-metric-icon"></i>
            <div class="metric-title">Total Vencido (Período)</div>
            <div class="metric-value">
                R$ <?php echo number_format($total_vencido_periodo, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
</div>


<!-- Seção: Indicadores de Cobranças (separado de Lançamentos) -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <h5 class="mb-3">Cobranças</h5>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Total de cobranças com vencimento no período que estão pendentes.">
        <div class="card card-metric">
            <i class="bi bi-wallet2 card-metric-icon"></i>
            <div class="metric-title">Cobranças a Receber</div>
            <div class="metric-value text-warning-emphasis">
                R$ <?php echo number_format($total_cobrancas_pendentes, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Total de cobranças marcadas como pagas no período filtrado.">
        <div class="card card-metric">
            <i class="bi bi-cash-stack card-metric-icon"></i>
            <div class="metric-title">Cobranças Recebidas</div>
            <div class="metric-value text-success-emphasis">
                R$ <?php echo number_format($total_cobrancas_recebidas, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Quantidade de cobranças no período considerado.">
        <div class="card card-metric">
            <i class="bi bi-list-columns card-metric-icon"></i>
            <div class="metric-title">Qtd. Cobranças</div>
            <div class="metric-value">
                <?php echo intval($qtd_cobrancas_periodo); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Soma total de cobranças com vencimento no período filtrado.">
        <div class="card card-metric">
            <i class="bi bi-calendar3 card-metric-icon"></i>
            <div class="metric-title">Total Vencido (Cobranças)</div>
            <div class="metric-value">
                R$ <?php echo number_format($total_cobrancas_vencido, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                Fluxo de Caixa (Realizado de <?php echo date('d/m/Y', strtotime($filtro_data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($filtro_data_fim)); ?>)
            </div>
            <div class="card-body">
                <canvas id="fluxoCaixaChart" height="150"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                Comparação de Status (Receitas de <?php echo date('d/m/Y', strtotime($filtro_data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($filtro_data_fim)); ?>)
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <canvas id="statusChart" height="150"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-12 mt-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history me-2"></i> Atividade Recente (Últimos 5 Lançamentos)
            </div>
            <div class="card-body p-0">
                <?php
                // --- Lógica da Atividade Recente (Mantida) ---
                $params_activity = [];
                $where_activity_conditions = [];
                
                if(isAdmin()) {
                    $stmt_activity = $pdo->query("SELECT l.*, u.nome FROM logs l LEFT JOIN usuarios u ON l.id_usuario = u.id ORDER BY l.timestamp DESC LIMIT 5");
                    $activities = $stmt_activity->fetchAll();
                } else {
                    $base_join_activity = "FROM lancamentos l JOIN empresas e ON l.id_empresa = e.id";

                    if ($filtro_empresa_id) {
                         $where_activity_conditions[] = "l.id_empresa = ?";
                         $params_activity[] = $filtro_empresa_id;
                    } elseif ($filtro_cliente_id) {
                         $where_activity_conditions[] = "e.id_cliente = ?";
                         $params_activity[] = $filtro_cliente_id;
                    }

                    if (isContador()) {
                        if (!empty($clientes_permitidos_ids)) {
                             $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
                             $where_activity_conditions[] = "e.id_cliente IN ($placeholders)";
                             $params_activity = array_merge($params_activity, $clientes_permitidos_ids);
                        } else {
                             $where_activity_conditions[] = "1=0";
                        }
                    } elseif (isClient()) {
                        $where_activity_conditions[] = "e.id_cliente = ?";
                        $params_activity[] = $_SESSION['id_cliente_associado'];
                    }

                    $where_activity_sql = "WHERE 1=1";
                    if(!empty($where_activity_conditions)) {
                        $where_activity_sql = "WHERE " . implode(' AND ', $where_activity_conditions);
                    }
                    
                    $sql_activity = "SELECT l.*, e.razao_social $base_join_activity 
                                     $where_activity_sql
                                     ORDER BY l.id DESC LIMIT 5";
                    $stmt_activity = $pdo->prepare($sql_activity);
                    $stmt_activity->execute($params_activity);
                    $activities = $stmt_activity->fetchAll();
                }
                ?>
                
                <ul class="list-group list-group-flush">
                    <?php if (empty($activities)): ?>
                         <li class="list-group-item text-muted text-center">Nenhuma atividade recente.</li>
                    <?php endif; ?>
                    <?php foreach($activities as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php if(isAdmin()): ?>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($item['acao']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['nome'] ?? 'Sistema'); ?> em <?php echo $item['tabela_afetada']; ?></small>
                                </div>
                                <small><?php echo date('d/m H:i', strtotime($item['timestamp'])); ?></small>
                            <?php else: ?>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($item['descricao']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['razao_social']); ?></small>
                                </div>
                                <span class="fw-bold <?php echo ($item['tipo'] == 'receita') ? 'text-success' : 'text-danger'; ?>">
                                     R$ <?php echo number_format($item['valor'], 2, ',', '.'); ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="card-footer text-center bg-white border-0">
                    <a href="index.php?page=<?php echo isAdmin() ? 'logs' : 'lancamentos'; ?>" class="text-decoration-none fw-medium">
                        Ver tudo
                        <i class="bi bi-arrow-right-short"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script id="chartData" type="application/json">
    <?php echo $chart_data_json; ?>
</script>

<script id="statusChartData" type="application/json">
    <?php echo $status_chart_json; ?>
</script>