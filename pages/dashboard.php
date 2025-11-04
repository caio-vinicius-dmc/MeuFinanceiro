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

// 3a. A Receber = Pendente OU Confirmado pelo Cliente (filtro por data_vencimento)
$sql_receber = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'receita' AND LOWER(l.status) IN ('pendente', 'confirmado_cliente')";
$stmt_receber = $pdo->prepare($sql_receber);
$stmt_receber->execute($params);
$total_a_receber = $stmt_receber->fetch()['total'] ?? 0;

// 3b. A Pagar = Pendente OU Confirmado pelo Cliente (filtro por data_vencimento)
$sql_pagar = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'despesa' AND LOWER(l.status) IN ('pendente', 'confirmado_cliente')";
$stmt_pagar = $pdo->prepare($sql_pagar);
$stmt_pagar->execute($params);
$total_a_pagar = $stmt_pagar->fetch()['total'] ?? 0;

// --- Preparar WHERE/PARAMS para consultas baseadas em data_pagamento (Realizado) ---
$where_conditions_pag = $where_conditions;
// Substitui o primeiro filtro (data_vencimento) por um filtro que prioriza data_pagamento
// e, caso data_pagamento seja NULL, usa data_vencimento como fallback.
if (!empty($where_conditions_pag)) {
    // assume que o primeiro elemento original é o filtro de data_vencimento
    $where_conditions_pag[0] = "(l.data_pagamento BETWEEN ? AND ? OR (l.data_pagamento IS NULL AND l.data_vencimento BETWEEN ? AND ?))";
}
// Precisamos passar os parâmetros de data duas vezes (para data_pagamento e para o fallback data_vencimento)
$params_pag = array_merge([$filtro_data_inicio, $filtro_data_fim, $filtro_data_inicio, $filtro_data_fim], $params_permissoes);
$where_sql_pag = implode(' AND ', $where_conditions_pag);
$where_sql_pag_com_prefixo = "WHERE " . $where_sql_pag;

// 3c. Saldo Realizado = APENAS o que está 'pago' (baixado) - usa data_pagamento
$sql_pago = "SELECT SUM(CASE WHEN l.tipo = 'receita' THEN valor ELSE -valor END) AS total $base_join $where_sql_pag_com_prefixo AND LOWER(l.status) = 'pago'";
$stmt_pago = $pdo->prepare($sql_pago);
$stmt_pago->execute($params_pag);
$total_pago = $stmt_pago->fetch()['total'] ?? 0;

// NOTE: 'contestado' status removed from application; não calculamos mais contestados aqui.

// 3f. Indicador: Total de Lançamentos Vencidos no Período (Inclui PAGO e PENDENTE) - mantém data_vencimento
$sql_total_vencido = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo";
$stmt_total_vencido = $pdo->prepare($sql_total_vencido);
$stmt_total_vencido->execute($params);
$total_vencido_periodo = $stmt_total_vencido->fetch()['total'] ?? 0;

// 3g. Indicador: Projeção de Saldo (Realizado + (Receber - Pagar))
$projecao_saldo = $total_pago + ($total_a_receber - $total_a_pagar);

// 3h. Indicador: Percentual de Receita Paga (usa data_pagamento para o numerador e denominador)
$sql_receita_paga = "SELECT SUM(valor) AS total $base_join $where_sql_pag_com_prefixo AND l.tipo = 'receita' AND LOWER(l.status) = 'pago'";
$stmt_receita_paga = $pdo->prepare($sql_receita_paga);
$stmt_receita_paga->execute($params_pag);
$valor_receita_paga = $stmt_receita_paga->fetch()['total'] ?? 0;

// Total de Receitas no Período (considerando realizadas no período — usar data_pagamento para consistência com 'realizado')
$sql_total_receita_periodo = "SELECT SUM(valor) AS total $base_join $where_sql_pag_com_prefixo AND l.tipo = 'receita'";
$stmt_total_receita_periodo = $pdo->prepare($sql_total_receita_periodo);
$stmt_total_receita_periodo->execute($params_pag);
$total_receita_periodo = $stmt_total_receita_periodo->fetch()['total'] ?? 0;

$percent_pago = ($total_receita_periodo > 0) ? ($valor_receita_paga / $total_receita_periodo) * 100 : 0;

// --- 3j. Métricas adicionais para visão do cliente (contagens e médias)
// Quantidade de receitas e despesas no período
$sql_qtd_receitas = "SELECT COUNT(l.id) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'receita'";
$stmt_qtd_receitas = $pdo->prepare($sql_qtd_receitas);
$stmt_qtd_receitas->execute($params);
$qtd_receitas = intval($stmt_qtd_receitas->fetch()['total'] ?? 0);

$sql_qtd_despesas = "SELECT COUNT(l.id) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'despesa'";
$stmt_qtd_despesas = $pdo->prepare($sql_qtd_despesas);
$stmt_qtd_despesas->execute($params);
$qtd_despesas = intval($stmt_qtd_despesas->fetch()['total'] ?? 0);

// Médias por lançamento
$sql_avg_receita = "SELECT AVG(valor) AS media $base_join $where_sql_com_prefixo AND l.tipo = 'receita'";
$stmt_avg_receita = $pdo->prepare($sql_avg_receita);
$stmt_avg_receita->execute($params);
$media_receita = $stmt_avg_receita->fetch()['media'] ?? 0;

$sql_avg_despesa = "SELECT AVG(valor) AS media $base_join $where_sql_com_prefixo AND l.tipo = 'despesa'";
$stmt_avg_despesa = $pdo->prepare($sql_avg_despesa);
$stmt_avg_despesa->execute($params);
$media_despesa = $stmt_avg_despesa->fetch()['media'] ?? 0;

// Total de despesas marcadas como 'pago' (realizado despesa)
$sql_despesa_paga = "SELECT SUM(valor) AS total $base_join $where_sql_com_prefixo AND l.tipo = 'despesa' AND LOWER(l.status) = 'pago'";
$stmt_despesa_paga = $pdo->prepare($sql_despesa_paga);
$stmt_despesa_paga->execute($params);
$valor_despesa_paga = $stmt_despesa_paga->fetch()['total'] ?? 0;

// --- 3k. Breakdown por tipo para Lançamentos (opcional, só se tabela/types existir)
$lancamentos_por_tipo_receita = [];
$lancamentos_por_tipo_despesa = [];
try {
    $stmt_check = $pdo->query("SHOW TABLES LIKE 'tipos_lancamento'");
    $has_table = (bool) $stmt_check->fetchColumn();
    if ($has_table) {
        // Verifica se a coluna id_tipo_lancamento existe em lancamentos
        $stmt_col = $pdo->query("SHOW COLUMNS FROM lancamentos LIKE 'id_tipo_lancamento'");
        $has_col = (bool) $stmt_col->fetchColumn();
        if ($has_col) {
            // Receitas pagas por tipo
            $sql_tipo_rec = "SELECT tl.id, tl.nome, COALESCE(tp.total,0) AS total, COALESCE(tp.qtd,0) AS qtd
                FROM tipos_lancamento tl
                LEFT JOIN (
                    SELECT l.id_tipo_lancamento AS tipo_id, SUM(l.valor) AS total, COUNT(l.id) AS qtd
                    $base_join
                    $where_sql_com_prefixo AND l.tipo = 'receita' AND l.status = 'pago'
                    GROUP BY l.id_tipo_lancamento
                ) tp ON tl.id = tp.tipo_id
                ORDER BY tp.total DESC";
            $stmt_tipo_rec = $pdo->prepare($sql_tipo_rec);
            $stmt_tipo_rec->execute($params);
            $lancamentos_por_tipo_receita = $stmt_tipo_rec->fetchAll(PDO::FETCH_ASSOC);

            // Despesas pagas por tipo
            $sql_tipo_desp = "SELECT tl.id, tl.nome, COALESCE(tp.total,0) AS total, COALESCE(tp.qtd,0) AS qtd
                FROM tipos_lancamento tl
                LEFT JOIN (
                    SELECT l.id_tipo_lancamento AS tipo_id, SUM(l.valor) AS total, COUNT(l.id) AS qtd
                    $base_join
                    $where_sql_com_prefixo AND l.tipo = 'despesa' AND l.status = 'pago'
                    GROUP BY l.id_tipo_lancamento
                ) tp ON tl.id = tp.tipo_id
                ORDER BY tp.total DESC";
            $stmt_tipo_desp = $pdo->prepare($sql_tipo_desp);
            $stmt_tipo_desp->execute($params);
            $lancamentos_por_tipo_despesa = $stmt_tipo_desp->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    // Silenciar; não impacta dashboard se não existir tabela/coluna
}

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
$sql_cob_pendente = "SELECT SUM(cob.valor) AS total $base_join_cob $where_sql_cob AND LOWER(cob.status_pagamento) = 'pendente'";
$stmt_cob_pendente = $pdo->prepare($sql_cob_pendente);
$stmt_cob_pendente->execute($params_cob);
$total_cobrancas_pendentes = $stmt_cob_pendente->fetch()['total'] ?? 0;

// Consulta: Total Recebido (Cobranças pagas)
$sql_cob_pago = "SELECT SUM(cob.valor) AS total $base_join_cob $where_sql_cob AND LOWER(cob.status_pagamento) = 'pago'";
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

// Consulta: Total de Cobranças Vencidas (Ainda não pagas e com data de vencimento passada)
$where_vencidas = [];
$params_vencidas = [];
$where_vencidas[] = "cob.data_vencimento < CURDATE()";

if ($filtro_empresa_id) {
    $where_vencidas[] = "cob.id_empresa = ?";
    $params_vencidas[] = $filtro_empresa_id;
    if (isContador() && !empty($clientes_permitidos_ids)) {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $where_vencidas[] = "e.id_cliente IN ($placeholders)";
        $params_vencidas = array_merge($params_vencidas, $clientes_permitidos_ids);
    } elseif (isClient()) {
        $where_vencidas[] = "e.id_cliente = ?";
        $params_vencidas[] = $_SESSION['id_cliente_associado'];
    }

} elseif ($filtro_cliente_id) {
    $where_vencidas[] = "e.id_cliente = ?";
    $params_vencidas[] = $filtro_cliente_id;

} else {
    if (isContador()) {
        if (empty($clientes_permitidos_ids)) {
            $where_vencidas[] = "1=0";
        } else {
            $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
            $where_vencidas[] = "e.id_cliente IN ($placeholders)";
            $params_vencidas = array_merge($params_vencidas, $clientes_permitidos_ids);
        }
    } elseif (isClient()) {
        $where_vencidas[] = "e.id_cliente = ?";
        $params_vencidas[] = $_SESSION['id_cliente_associado'];
    }
}

$where_sql_vencidas = "WHERE " . implode(' AND ', $where_vencidas);
$sql_cob_vencidas_nao_pagas = "SELECT SUM(cob.valor) AS total $base_join_cob $where_sql_vencidas AND LOWER(cob.status_pagamento) != 'pago'";
$stmt_cob_vencidas = $pdo->prepare($sql_cob_vencidas_nao_pagas);
$stmt_cob_vencidas->execute($params_vencidas);
$total_cobrancas_vencidas = $stmt_cob_vencidas->fetch()['total'] ?? 0;

// Consulta: Total de cobranças por tipo (soma de valores por tipo) dentro do período filtrado
$sql_cob_por_tipo = "SELECT COALESCE(tc.nome, 'Sem Tipo') as tipo, SUM(cob.valor) as total $base_join_cob LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id $where_sql_cob GROUP BY tc.nome ORDER BY total DESC";
$stmt_cob_por_tipo = $pdo->prepare($sql_cob_por_tipo);
$stmt_cob_por_tipo->execute($params_cob);
$cobrancas_por_tipo = $stmt_cob_por_tipo->fetchAll(PDO::FETCH_ASSOC);
$top_tipo_cob = $cobrancas_por_tipo[0] ?? null;

// Consulta: Buscar TODOS os tipos cadastrados e agregar total de cobranças PAGAS por tipo (LEFT JOIN)
$sql_tipos_com_valor = "SELECT tc.id, tc.nome, COALESCE(tp.total, 0) as total, COALESCE(tp.qtd, 0) as qtd
FROM tipos_cobranca tc
LEFT JOIN (
    SELECT cob.id_tipo_cobranca as id_tipo_cobranca, SUM(cob.valor) as total, COUNT(cob.id) as qtd
    $base_join_cob
    $where_sql_cob AND cob.status_pagamento = 'Pago'
    GROUP BY cob.id_tipo_cobranca
) tp ON tc.id = tp.id_tipo_cobranca
ORDER BY tc.nome";
$stmt_tipos_com_valor = $pdo->prepare($sql_tipos_com_valor);
$stmt_tipos_com_valor->execute($params_cob);
$cobrancas_por_tipo_pagas = $stmt_tipos_com_valor->fetchAll(PDO::FETCH_ASSOC);

// Consulta: Total de cobranças VENCIDAS por tipo (respeitando filtros/permissões)
$sql_tipos_com_vencidas = "SELECT tc.id, tc.nome, COALESCE(tp.total, 0) as total, COALESCE(tp.qtd,0) as qtd
FROM tipos_cobranca tc
LEFT JOIN (
    SELECT cob.id_tipo_cobranca as id_tipo_cobranca, SUM(cob.valor) as total, COUNT(cob.id) as qtd
    $base_join_cob
    $where_sql_vencidas
    AND cob.status_pagamento != 'Pago'
    GROUP BY cob.id_tipo_cobranca
) tp ON tc.id = tp.id_tipo_cobranca
ORDER BY tc.nome";
$stmt_tipos_com_vencidas = $pdo->prepare($sql_tipos_com_vencidas);
$stmt_tipos_com_vencidas->execute($params_vencidas);
$cobrancas_por_tipo_vencidas = $stmt_tipos_com_vencidas->fetchAll(PDO::FETCH_ASSOC);

// Dados para gráficos de cobranças: status e por tipo
$sql_cob_status = "SELECT cob.status_pagamento as status, SUM(cob.valor) as total $base_join_cob $where_sql_cob GROUP BY cob.status_pagamento";
$stmt_cob_status = $pdo->prepare($sql_cob_status);
$stmt_cob_status->execute($params_cob);
$cob_status_raw = $stmt_cob_status->fetchAll(PDO::FETCH_ASSOC);

$cob_status_labels = [];
$cob_status_values = [];
foreach ($cob_status_raw as $r) {
    $cob_status_labels[] = $r['status'];
    $cob_status_values[] = (float)$r['total'];
}

$cob_por_tipo_labels = [];
$cob_por_tipo_values = [];
foreach ($cobrancas_por_tipo as $r) {
    $cob_por_tipo_labels[] = $r['tipo'];
    $cob_por_tipo_values[] = (float)$r['total'];
}

$cob_chart_status_json = json_encode(['labels' => $cob_status_labels, 'values' => $cob_status_values]);
$cob_chart_tipo_json = json_encode(['labels' => $cob_por_tipo_labels, 'values' => $cob_por_tipo_values]);


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

// 2. Status (case-insensitive)
$where_grafico_fluxo_conditions[] = "LOWER(l.status) = 'pago'";

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

// Processa dados brutos para o formato do gráfico (case-insensitive para status)
foreach ($status_data_raw as $item) {
    $st = strtolower(trim((string)$item['status'] ?? ''));
    if ($st === 'pago') {
        $status_data['Pago'] = ($status_data['Pago'] ?? 0) + $item['total'];
    } else {
        // Agrupa 'pendente' e 'confirmado_cliente' e qualquer outro status não-pago como 'A Receber'
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
    <form id="form-filtros-dashboard" method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="dashboard">
            
            <div class="col-md-6">
                <label class="form-label">Vencimento (entre)</label>
                <div class="input-group">
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>" title="Data Início">
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>" title="Data Fim">
                </div>
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

<?php if (hasLancamentosAccess()): ?>
<section id="dashboard-lancamentos-section" class="dashboard-section dashboard-section-lancamentos collapsed">
    <div class="section-header">
        <span class="badge bg-primary badge-topic"><i class="bi bi-arrow-left-right"></i></span>
        <div>
            <div class="title">Lançamentos</div>
            <div class="subtitle">Indicadores e projeções dos lançamentos financeiros</div>
        </div>
        <button type="button" class="btn btn-sm btn-link section-toggle ms-auto" aria-expanded="false" aria-controls="dashboard-lancamentos-section">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <?php
        // Títulos dos cards (ajustados para a visão do cliente)
        if (isClient()) {
            // títulos mais diretos para clientes
            $card_title_receita = 'Total a Receber';
            $card_title_despesa = 'Total a Pagar';
            $card_title_saldo_realizado = 'Saldo Realizado';
            $card_title_projecao = 'Projeção Total';
            $card_title_percent = '% Receitas Pagas';
            $card_title_total_vencido_periodo = 'Total Vencido (Período)';
        } else {
            $card_title_receita = 'A Receber (Proj.)';
            $card_title_despesa = 'A Pagar (Proj.)';
            $card_title_saldo_realizado = 'Saldo Realizado';
            $card_title_projecao = 'Projeção Total';
            $card_title_percent = '% Receita Paga';
            $card_title_total_vencido_periodo = 'Total Vencido (Período)';
        }
    ?>
    <div class="section-body" style="height:0px;">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Soma total das receitas com data de vencimento no período, que ainda não foram pagas.">
        <div class="card card-metric">
            <i class="bi bi-arrow-down-circle card-metric-icon"></i>
            <div class="metric-title"><?php echo $card_title_receita; ?></div>
            <div class="metric-value text-warning-emphasis">
                R$ <?php echo number_format($total_a_receber, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Soma total das despesas com data de vencimento no período, que ainda não foram pagas.">
        <div class="card card-metric">
            <i class="bi bi-arrow-up-circle card-metric-icon"></i>
            <div class="metric-title"><?php echo $card_title_despesa; ?></div>
            <div class="metric-value text-danger-emphasis">
                R$ <?php echo number_format($total_a_pagar, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Saldo líquido dos lançamentos que foram efetivamente pagos (Receitas Pagas - Despesas Pagas) no período.">
        <div class="card card-metric">
             <i class="bi bi-check-circle card-metric-icon"></i>
            <div class="metric-title"><?php echo $card_title_saldo_realizado; ?> <small class="text-muted"><i class="bi bi-info-circle" data-bs-toggle="tooltip" data-bs-placement="top" title="Calculado por valores marcados como pagos; utiliza a data de pagamento quando disponível, caso contrário usa a data de vencimento como fallback."></i></small></div>
            <div class="metric-value <?php echo ($total_pago >= 0) ? 'text-success-emphasis' : 'text-danger-emphasis'; ?>">
                R$ <?php echo number_format($total_pago, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <?php if (!isClient()): ?>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Saldo projetado, somando o Saldo Realizado com o saldo futuro (A Receber - A Pagar) no período.">
        <div class="card card-metric">
             <i class="bi bi-graph-up-arrow card-metric-icon"></i>
            <div class="metric-title"><?php echo $card_title_projecao; ?></div>
            <div class="metric-value <?php echo ($projecao_saldo >= 0) ? 'text-success-emphasis' : 'text-danger-emphasis'; ?>">
                R$ <?php echo number_format($projecao_saldo, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
            <?php if (!isClient()): ?>
            <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Percentual de Receitas que foram pagas em relação ao total de Receitas (Pagos + A Receber) no período.">
                <div class="card card-metric">
                     <i class="bi bi-percent card-metric-icon"></i>
                    <div class="metric-title"><?php echo $card_title_percent; ?></div>
                    <div class="metric-value">
                        <?php echo number_format($percent_pago, 1, ',', '.'); ?>%
                    </div>
                    <div class="metric-sub text-muted">R$ <?php echo number_format($valor_receita_paga ?? 0, 2, ',', '.'); ?> / R$ <?php echo number_format($total_receita_periodo ?? 0, 2, ',', '.'); ?></div>
                </div>
            </div>
            <?php endif; ?>
    <!-- blocos relacionados a 'contestado' removidos -->
    <?php if (!isClient()): ?>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Soma total de Receitas e Despesas (Pagos e Pendentes) com data de vencimento dentro do período filtrado.">
        <div class="card card-metric">
            <i class="bi bi-calendar-x card-metric-icon"></i>
            <div class="metric-title"><?php echo $card_title_total_vencido_periodo; ?></div>
            <div class="metric-value">
                R$ <?php echo number_format($total_vencido_periodo, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Blocos adicionais removidos: seção simplificada para manter apenas os indicadores essenciais (Total a Receber, Total a Pagar, Saldo Realizado, % Receita Paga). -->
<!-- Se desejar, posso reintroduzir cards menores (contagens/médias) opcionalmente em uma área colapsável. -->
<!-- Card colapsável: Métricas secundárias (contagens e médias) -->
<div class="mb-3">
    <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#secondaryMetrics" role="button" aria-expanded="false" aria-controls="secondaryMetrics">
        <i class="bi bi-list-check"></i> Métricas secundárias
    </a>
    <div class="collapse mt-3" id="secondaryMetrics">
        <div class="card card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <div class="small-card p-2 border rounded text-center">
                        <div class="fw-semibold">Receitas (Qtd)</div>
                        <div class="fs-5 text-body"><?php echo intval($qtd_receitas); ?></div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="small-card p-2 border rounded text-center">
                        <div class="fw-semibold">Despesas (Qtd)</div>
                        <div class="fs-5 text-body"><?php echo intval($qtd_despesas); ?></div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="small-card p-2 border rounded text-center">
                        <div class="fw-semibold">Média Receita</div>
                        <div class="fs-5 text-body">R$ <?php echo number_format($media_receita, 2, ',', '.'); ?></div>
                    </div>
                </div>
                <div class="col-sm-6 col-md-3">
                    <div class="small-card p-2 border rounded text-center">
                        <div class="fw-semibold">Média Despesa</div>
                        <div class="fs-5 text-body">R$ <?php echo number_format($media_despesa, 2, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($lancamentos_por_tipo_receita) || !empty($lancamentos_por_tipo_despesa)): ?>
    <div class="row g-4 mb-4">
        <?php if (!empty($lancamentos_por_tipo_receita)): ?>
        <div class="col-12">
            <h5>Receitas por Tipo (Pagas)</h5>
            <div class="row g-3">
                <?php foreach ($lancamentos_por_tipo_receita as $tipo): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card card-metric">
                            <div class="metric-title"><?php echo htmlspecialchars($tipo['nome']); ?></div>
                            <div class="metric-value text-success-emphasis">R$ <?php echo number_format($tipo['total'], 2, ',', '.'); ?></div>
                            <div class="metric-sub">Qtd: <?php echo intval($tipo['qtd']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($lancamentos_por_tipo_despesa)): ?>
        <div class="col-12 mt-3">
            <h5>Despesas por Tipo (Pagas)</h5>
            <div class="row g-3">
                <?php foreach ($lancamentos_por_tipo_despesa as $tipo): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card card-metric">
                            <div class="metric-title"><?php echo htmlspecialchars($tipo['nome']); ?></div>
                            <div class="metric-value text-danger-emphasis">R$ <?php echo number_format($tipo['total'], 2, ',', '.'); ?></div>
                            <div class="metric-sub">Qtd: <?php echo intval($tipo['qtd']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
    <!-- Gráficos relacionados a Lançamentos (apenas para quem tem acesso) -->
    <div class="row g-4 mb-4 mt-3">
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
            </div>
        </div>
    </div> <!-- .section-body -->
    </section>
    <?php endif; ?>


<!-- Seção: Indicadores de Cobranças (separado de Lançamentos) -->
<section id="dashboard-cobrancas-section" class="dashboard-section dashboard-section-cobrancas collapsed">
    <div class="section-header">
        <span class="badge bg-warning text-dark badge-topic"><i class="bi bi-receipt-cutoff"></i></span>
        <div>
            <div class="title">Cobranças</div>
            <div class="subtitle">Resumo e cartões relacionados às cobranças</div>
        </div>
        <button type="button" class="btn btn-sm btn-link section-toggle ms-auto" aria-expanded="false" aria-controls="dashboard-cobrancas-section">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="section-body" style="height:0px;">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-4 mb-4">
    <?php
        $label_pendentes = isClient() ? 'Cobranças a Pagar' : 'Cobranças a Receber';
        $label_recebidas = isClient() ? 'Cobranças pagas' : 'Cobranças Recebidas';
    ?>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Total de cobranças com vencimento no período que estão pendentes.">
        <div class="card card-metric">
            <i class="bi bi-wallet2 card-metric-icon"></i>
            <div class="metric-title"><?php echo $label_pendentes; ?></div>
            <div class="metric-value text-warning-emphasis">
                R$ <?php echo number_format($total_cobrancas_pendentes, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Total de cobranças marcadas como pagas no período filtrado.">
        <div class="card card-metric">
            <i class="bi bi-cash-stack card-metric-icon"></i>
            <div class="metric-title"><?php echo $label_recebidas; ?></div>
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
    <div class="col-md-6 col-lg-3" data-bs-toggle="tooltip" data-bs-placement="top" title="Total de cobranças ainda não pagas com vencimento anterior à hoje (vencidas).">
        <div class="card card-metric">
            <i class="bi bi-exclamation-triangle card-metric-icon"></i>
            <div class="metric-title">Cobranças Vencidas</div>
            <div class="metric-value text-danger-emphasis">
                R$ <?php echo number_format($total_cobrancas_vencidas, 2, ',', '.'); ?>
            </div>
        </div>
    </div>
            </div>
        </div>
    </div> <!-- .section-body -->
</section>

<!-- Seção: Cobranças por Tipo (agrupadas dentro de um card) -->
<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex align-items-center">
            <h5 class="mb-0">Cobranças — Por Tipo</h5>
            <small class="text-muted ms-3">Pagas e Vencidas</small>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4 mb-4">
            <div class="col-12">
                <h6 class="mb-3">Cobranças Pagas por Tipo</h6>
            </div>
            <?php if (!empty($cobrancas_por_tipo_pagas)): ?>
                <?php foreach ($cobrancas_por_tipo_pagas as $tipo): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card card-metric">
                            <i class="bi bi-tag card-metric-icon"></i>
                            <div class="metric-title"><?php echo htmlspecialchars($tipo['nome']); ?></div>
                            <div class="metric-value text-success-emphasis">
                                R$ <?php echo number_format($tipo['total'] ?? 0, 2, ',', '.'); ?>
                                <div class="small text-muted"><?php echo intval($tipo['qtd'] ?? 0); ?> itens</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-muted">Nenhuma cobrança paga encontrada para o período selecionado.</div>
            <?php endif; ?>
        </div>

        <div class="row g-4 mb-0">
            <div class="col-12">
                <h6 class="mb-3">Cobranças Vencidas por Tipo</h6>
            </div>
            <?php if (!empty($cobrancas_por_tipo_vencidas)): ?>
                <?php foreach ($cobrancas_por_tipo_vencidas as $tipo_v): ?>
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card card-metric">
                            <i class="bi bi-exclamation-triangle card-metric-icon"></i>
                            <div class="metric-title"><?php echo htmlspecialchars($tipo_v['nome']); ?></div>
                            <div class="metric-value text-danger-emphasis">
                                R$ <?php echo number_format($tipo_v['total'] ?? 0, 2, ',', '.'); ?>
                                <div class="small text-muted"><?php echo intval($tipo_v['qtd'] ?? 0); ?> itens</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-muted">Nenhuma cobrança vencida encontrada.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Gráficos relacionados a Cobranças -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Distribuição por Status (Cobranças)</div>
            <div class="card-body">
                <canvas id="cobStatusChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Total por Tipo (Cobranças)</div>
            <div class="card-body">
                <canvas id="cobTipoChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

    <?php if (isAdmin()): ?>
    <section id="dashboard-atividade-section" class="dashboard-section dashboard-section-atividade collapsed">
        <div class="section-header">
            <span class="badge bg-secondary badge-topic"><i class="bi bi-clock-history"></i></span>
            <div>
                <div class="title">Atividade do Sistema (Logs)</div>
                <div class="subtitle">Últimos 5 registros de logs</div>
            </div>
            <button type="button" class="btn btn-sm btn-link section-toggle ms-auto" aria-expanded="true" aria-controls="dashboard-atividade-section">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="section-body" style="height:0px;">
            <div class="card mb-0">
                <div class="card-body p-0">
                    <?php
                    // --- Lógica da Atividade Recente (Mantida) ---
                    $params_activity = [];
                    $where_activity_conditions = [];
                    
                    // Admin vê o log de ações (logs), mostrado aqui como últimos 5 registros
                    $stmt_activity = $pdo->query("SELECT l.*, u.nome FROM logs l LEFT JOIN usuarios u ON l.id_usuario = u.id ORDER BY l.timestamp DESC LIMIT 5");
                    $activities = $stmt_activity->fetchAll();
                    ?>

                    <ul class="list-group list-group-flush">
                        <?php if (empty($activities)): ?>
                             <li class="list-group-item text-muted text-center">Nenhuma atividade recente.</li>
                        <?php endif; ?>
                        <?php foreach($activities as $item): ?>
                            <?php $log_id = intval($item['id'] ?? 0); ?>
                            <li class="list-group-item p-0">
                                <a href="index.php?page=logs#log-<?php echo $log_id; ?>" class="d-flex w-100 text-decoration-none text-reset py-2 px-3">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($item['acao']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($item['nome'] ?? 'Sistema'); ?> em <?php echo htmlspecialchars($item['tabela_afetada'] ?? ''); ?></small>
                                    </div>
                                    <small class="text-muted ms-3"><?php echo date('d/m H:i', strtotime($item['timestamp'])); ?></small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="card-footer text-center bg-white border-0">
                        <a href="index.php?page=logs" class="text-decoration-none fw-medium">
                            Ver tudo
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <?php if (hasLancamentosAccess()): ?>
    <!-- Seção: Atividade Recente - Últimos 5 Lançamentos (visível para Admin e usuários com acesso a Lançamentos) -->
    <section id="dashboard-atividade-lancamentos-section" class="dashboard-section dashboard-section-atividade-lancamentos">
        <div class="section-header">
            <span class="badge bg-info text-dark badge-topic"><i class="bi bi-list-check"></i></span>
            <div>
                    <div class="title">Atividade Recente — Lançamentos</div>
                <div class="subtitle">Últimos 5 Lançamentos</div>
            </div>
            <button type="button" class="btn btn-sm btn-link section-toggle ms-auto" aria-expanded="false" aria-controls="dashboard-atividade-lancamentos-section">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="section-body" style="height:0px;">
            <div class="card mb-0">
                <div class="card-body p-0">
                    <?php
                    // Busca os 5 lançamentos mais recentes respeitando filtros e permissões
                    $params_recent = [];
                    $where_recent = [];
                    $base_join_recent = "FROM lancamentos l JOIN empresas e ON l.id_empresa = e.id";

                    if ($filtro_empresa_id) {
                        $where_recent[] = "l.id_empresa = ?";
                        $params_recent[] = $filtro_empresa_id;
                    } elseif ($filtro_cliente_id) {
                        $where_recent[] = "e.id_cliente = ?";
                        $params_recent[] = $filtro_cliente_id;
                    } else {
                        if (isContador()) {
                            if (empty($clientes_permitidos_ids)) {
                                $where_recent[] = "1=0";
                            } else {
                                $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
                                $where_recent[] = "e.id_cliente IN ($placeholders)";
                                $params_recent = array_merge($params_recent, $clientes_permitidos_ids);
                            }
                        } elseif (isClient()) {
                            $where_recent[] = "e.id_cliente = ?";
                            $params_recent[] = $_SESSION['id_cliente_associado'];
                        }
                    }

                    $where_recent_sql = "";
                    if (!empty($where_recent)) {
                        $where_recent_sql = "WHERE " . implode(' AND ', $where_recent);
                    }

                    $sql_recent = "SELECT l.id, l.descricao, l.valor, l.tipo, l.data_vencimento, e.razao_social $base_join_recent $where_recent_sql ORDER BY l.id DESC LIMIT 5";
                    $stmt_recent = $pdo->prepare($sql_recent);
                    $stmt_recent->execute($params_recent);
                    $recent_items = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <ul class="list-group list-group-flush">
                        <?php if (empty($recent_items)): ?>
                            <li class="list-group-item text-muted text-center">Nenhum lançamento recente.</li>
                        <?php endif; ?>
                        <?php foreach ($recent_items as $it): ?>
                            <?php $lid = intval($it['id'] ?? 0); ?>
                            <li class="list-group-item p-0">
                                <a href="index.php?page=lancamentos#lancamento-<?php echo $lid; ?>" class="d-flex w-100 text-decoration-none text-reset py-2 px-3">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($it['descricao']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($it['razao_social']); ?> — <?php echo date('d/m/Y', strtotime($it['data_vencimento'])); ?></small>
                                    </div>
                                    <span class="fw-bold ms-3 <?php echo ($it['tipo'] == 'receita') ? 'text-success' : 'text-danger'; ?>">R$ <?php echo number_format($it['valor'], 2, ',', '.'); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="card-footer text-center bg-white border-0">
                        <a href="index.php?page=lancamentos" class="text-decoration-none fw-medium">
                            Ver todos os lançamentos
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (isAdmin() || isContador() || isClient()): ?>
    <!-- Seção: Atividade Recente - Últimas 5 Cobranças -->
    <section id="dashboard-atividade-cobrancas-section" class="dashboard-section dashboard-section-atividade-cobrancas collapsed">
        <div class="section-header">
            <span class="badge bg-info text-dark badge-topic"><i class="bi bi-receipt-cutoff"></i></span>
            <div>
                <div class="title">Atividade Recente — Cobranças</div>
                <div class="subtitle">Últimas 5 Cobranças</div>
            </div>
            <button type="button" class="btn btn-sm btn-link section-toggle ms-auto" aria-expanded="false" aria-controls="dashboard-atividade-cobrancas-section">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="section-body" style="height:0px;">
            <div class="card mb-0">
                <div class="card-body p-0">
                    <?php
                    // Busca as 5 cobranças mais recentes respeitando filtros/permissões
                    $params_recent_cob = [];
                    $where_recent_cob = [];
                    $base_join_recent_cob = "FROM cobrancas cob JOIN empresas e ON cob.id_empresa = e.id";

                    // Usa a mesma hierarquia de filtros de cobrancas já calculada (se existirem vars)
                    if (!empty($filtro_empresa_id)) {
                        $where_recent_cob[] = "cob.id_empresa = ?";
                        $params_recent_cob[] = $filtro_empresa_id;
                    } elseif (!empty($filtro_cliente_id)) {
                        $where_recent_cob[] = "e.id_cliente = ?";
                        $params_recent_cob[] = $filtro_cliente_id;
                    } else {
                        if (isContador()) {
                            if (empty($clientes_permitidos_ids)) {
                                $where_recent_cob[] = "1=0";
                            } else {
                                $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
                                $where_recent_cob[] = "e.id_cliente IN ($placeholders)";
                                $params_recent_cob = array_merge($params_recent_cob, $clientes_permitidos_ids);
                            }
                        } elseif (isClient()) {
                            $where_recent_cob[] = "e.id_cliente = ?";
                            $params_recent_cob[] = $_SESSION['id_cliente_associado'];
                        }
                    }

                    $where_recent_cob_sql = "";
                    if (!empty($where_recent_cob)) {
                        $where_recent_cob_sql = "WHERE " . implode(' AND ', $where_recent_cob);
                    }

                    // Seleciona o tipo da cobrança (se existir) — caso contrário, usa a descrição como fallback
                    $sql_recent_cob = "SELECT cob.id, COALESCE(tc.nome, cob.descricao) AS tipo_nome, cob.valor, cob.status_pagamento, cob.data_vencimento, e.razao_social 
                        FROM cobrancas cob
                        JOIN empresas e ON cob.id_empresa = e.id
                        LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
                        $where_recent_cob_sql ORDER BY cob.id DESC LIMIT 5";
                    $stmt_recent_cob = $pdo->prepare($sql_recent_cob);
                    $stmt_recent_cob->execute($params_recent_cob);
                    $recent_cobs = $stmt_recent_cob->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <ul class="list-group list-group-flush">
                        <?php if (empty($recent_cobs)): ?>
                            <li class="list-group-item text-muted text-center">Nenhuma cobrança recente.</li>
                        <?php endif; ?>
                        <?php foreach ($recent_cobs as $c): ?>
                            <?php $cid = intval($c['id'] ?? 0); ?>
                            <li class="list-group-item p-0">
                                <a href="index.php?page=cobrancas#cobranca-<?php echo $cid; ?>" class="d-flex w-100 text-decoration-none text-reset py-2 px-3">
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo htmlspecialchars($c['tipo_nome'] ?? $c['descricao'] ?? '—'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($c['razao_social']); ?> — <?php echo date('d/m/Y', strtotime($c['data_vencimento'])); ?></small>
                                    </div>
                                            <?php $c_status = strtolower(trim((string)($c['status_pagamento'] ?? ''))); ?>
                                            <span class="fw-bold ms-3 <?php echo ($c_status === 'pago') ? 'text-success' : 'text-danger'; ?>">R$ <?php echo number_format($c['valor'], 2, ',', '.'); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="card-footer text-center bg-white border-0">
                        <a href="index.php?page=cobrancas" class="text-decoration-none fw-medium">
                            Ver todas as cobranças
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<script id="chartData" type="application/json">
    <?php echo $chart_data_json; ?>
</script>

<script id="statusChartData" type="application/json">
    <?php echo $status_chart_json; ?>
</script>

<script id="cobStatusChartData" type="application/json">
    <?php echo $cob_chart_status_json ?? json_encode(['labels'=>[], 'values'=>[]]); ?>
</script>

<script id="cobTipoChartData" type="application/json">
    <?php echo $cob_chart_tipo_json ?? json_encode(['labels'=>[], 'values'=>[]]); ?>
</script>