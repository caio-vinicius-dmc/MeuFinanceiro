<?php
// process/export_lancamentos.php
require_once '../config/functions.php';
requireLogin(); 
global $pdo;

// --- 1. Capturar e Sanitizar Filtros (Via GET) ---
$filtro_empresa_id = $_GET['id_empresa'] ?? null;
$filtro_status = $_GET['status'] ?? null;
$filtro_venc_inicio = $_GET['venc_inicio'] ?? null;
$filtro_venc_fim = $_GET['venc_fim'] ?? null;
$filtro_valor_min = $_GET['valor_min'] ?? null;
$filtro_valor_max = $_GET['valor_max'] ?? null;
// novos filtros: pagamento, competencia, forma de pagamento
$filtro_pag_inicio = $_GET['pag_inicio'] ?? null;
$filtro_pag_fim = $_GET['pag_fim'] ?? null;
$filtro_comp_inicio = $_GET['comp_inicio'] ?? null;
$filtro_comp_fim = $_GET['comp_fim'] ?? null;
// Recebe forma de pagamento (pode ser nome ou id dependendo do schema).
$filtro_forma_pag = $_GET['forma_pagamento'] ?? null;
$filtro_categoria = $_GET['categoria'] ?? null;

// Detecta se a coluna id_forma_pagamento existe em `lancamentos` no schema atual
$colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
$colStmt->execute();
$has_forma_id_col = (bool)$colStmt->fetchColumn();

// --- 2. Lógica de Permissão e Construção WHERE/PARAMS (REPLICADA) ---
$where_conditions = [];
$params = [];

// 2a. Permissão de Dados (Clientes e Contadores)
$clientes_permitidos_ids = []; 
if (isContador()) {
    $stmt_clientes_assoc = $pdo->prepare("SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
    $stmt_clientes_assoc->execute([$_SESSION['user_id']]);
    $clientes_permitidos_ids = $stmt_clientes_assoc->fetchAll(PDO::FETCH_COLUMN);

    if (empty($clientes_permitidos_ids)) {
        $where_conditions[] = "e.id_cliente IN (0)"; 
    } else {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $where_conditions[] = "e.id_cliente IN ($placeholders)";
        // ATENÇÃO: Adiciona os IDs dos clientes permitidos aos parâmetros
        $params = array_merge($params, $clientes_permitidos_ids);
    }
} 
elseif (isClient()) {
    $where_conditions[] = "e.id_cliente = ?";
    $params[] = $_SESSION['id_cliente_associado'];
}

// 2b. Filtros de Interface
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
// pagamento
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

// competencia
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

// forma de pagamento
if (!empty($filtro_forma_pag)) {
    if ($has_forma_id_col) {
        $where_conditions[] = "l.id_forma_pagamento = ?";
        $params[] = $filtro_forma_pag;
    } else {
        // compara com o campo texto metodo_pagamento
        $where_conditions[] = "l.metodo_pagamento = ?";
        $params[] = $filtro_forma_pag;
    }
}
// categoria
if (!empty($filtro_categoria)) {
    $where_conditions[] = "l.id_categoria = ?";
    $params[] = $filtro_categoria;
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// --- 3. Consulta de Exportação (Corrigida a sintaxe e espaçamento) ---
$select_forma = $has_forma_id_col ? 'fp.nome as forma_pagamento_nome' : "l.metodo_pagamento as forma_pagamento_nome";
$join_forma = $has_forma_id_col ? 'LEFT JOIN formas_pagamento fp ON l.id_forma_pagamento = fp.id' : '';
$join_cat = '';
$categoria_select = '';
try {
    $tStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categorias_lancamento'");
    $tStmt->execute();
    $has_categorias = $tStmt->fetchColumn() > 0;
} catch (Exception $e) {
    $has_categorias = false;
}
if ($has_categorias) {
    $join_cat = 'LEFT JOIN categorias_lancamento cat ON l.id_categoria = cat.id';
    $categoria_select = 'cat.nome AS categoria_nome,';
}

$sql_export = "SELECT 
    l.data_vencimento,
    l.data_pagamento,
    c.nome_responsavel AS nome_cliente,
    e.razao_social AS nome_empresa,
    l.descricao,
    l.tipo,
    l.valor,
    $select_forma,
    $categoria_select
    l.status
    FROM lancamentos l
    JOIN empresas e ON l.id_empresa = e.id
    JOIN clientes c ON e.id_cliente = c.id
    $join_forma
    $join_cat
    $where_sql
    ORDER BY l.data_vencimento ASC";

$stmt_export = $pdo->prepare($sql_export);
// A linha de execução é onde a PDO lança a exceção se a query estiver errada
if (!$stmt_export->execute($params)) {
    // Tratamento de erro adicional, caso o prepare/execute falhe por outros motivos
    error_log("Erro de exportação: " . print_r($stmt_export->errorInfo(), true));
    die("Erro ao executar a consulta de exportação.");
}
$dados = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

// --- 4. Geração do Arquivo CSV ---

// Cabeçalhos HTTP para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="lancamentos_export_' . date('Ymd_His') . '.csv"');

// Abre o output como um arquivo (write to output stream)
$output = fopen('php://output', 'w');

// Colunas do CSV
$cabecalho = [
    'Data Vencimento',
    'Data Pagamento',
    'Cliente',
    'Empresa',
    'Categoria',
    'Descricao',
    'Tipo (receita/despesa)',
    'Forma Pagamento',
    'Valor (R$)',
    'Status',
    'Observacao Contestacao'
];

// Escreve o cabeçalho no CSV
fputcsv($output, $cabecalho, ';'); // Usando ponto e vírgula como delimitador padrão BR

// Escreve os dados
foreach ($dados as $linha) {
    // Traduz o tipo
    $linha['tipo'] = ($linha['tipo'] == 'receita' ? 'Receita' : 'Despesa');
    
    // Formata o valor e substitui ponto por vírgula para compatibilidade com Excel BR
    $linha['valor'] = number_format($linha['valor'], 2, ',', '.');
    
    // Converte a linha para CSV e escreve
    // Garante que a ordem das colunas no CSV seja a do cabeçalho
    $csvLine = [
        $linha['data_vencimento'],
        $linha['data_pagamento'],
        $linha['nome_cliente'],
        $linha['nome_empresa'],
        $linha['categoria_nome'] ?? '',
        $linha['descricao'],
        $linha['tipo'],
        $linha['forma_pagamento_nome'] ?? '',
        $linha['valor'],
        $linha['status'],
        $linha['observacao_contestacao'] ?? ''
    ];
    fputcsv($output, $csvLine, ';');
}

fclose($output);
exit;
?>