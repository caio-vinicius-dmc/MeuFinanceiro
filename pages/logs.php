<?php
// pages/logs.php
global $pdo;

// 1. Apenas Admin pode ver esta página
if (!isAdmin()) {
    header("Location: " . base_url('index.php?page=dashboard'));
    exit;
}

// 2. Capturar Filtros
// (Usamos 'null' coalescing. Campos vazios '' se tornarão null)
$filtro_usuario_id = $_GET['usuario_id'] ?? null;
$filtro_acao = $_GET['acao'] ?? null;
$filtro_data_inicio = $_GET['data_inicio'] ?? null;
$filtro_data_fim = $_GET['data_fim'] ?? null;

// 3. Buscar Dados para os Dropdowns de Filtro
// 3a. Lista de Usuários
$stmt_users = $pdo->query("SELECT id, nome FROM usuarios ORDER BY nome");
$lista_usuarios = $stmt_users->fetchAll();
// 3b. Lista de Ações Distintas
$stmt_acoes = $pdo->query("SELECT DISTINCT acao FROM logs WHERE acao IS NOT NULL AND acao != '' ORDER BY acao");
$lista_acoes = $stmt_acoes->fetchAll();

// 4. Construir a Consulta SQL Dinâmica
$where_conditions = [];
$params = [];

// Adiciona filtros se eles não estiverem vazios
if (!empty($filtro_usuario_id)) {
    if ($filtro_usuario_id === '0') {
        // '0' é o valor que definimos para 'Sistema' (id_usuario IS NULL)
        $where_conditions[] = "l.id_usuario IS NULL";
    } else {
        $where_conditions[] = "l.id_usuario = ?";
        $params[] = $filtro_usuario_id;
    }
}
if (!empty($filtro_acao)) {
    $where_conditions[] = "l.acao = ?";
    $params[] = $filtro_acao;
}
if (!empty($filtro_data_inicio)) {
    // O formato 'datetime-local' (Y-m-d\TH:i) é compatível com o MySQL
    $where_conditions[] = "l.timestamp >= ?";
    $params[] = $filtro_data_inicio;
}
if (!empty($filtro_data_fim)) {
    $where_conditions[] = "l.timestamp <= ?";
    $params[] = $filtro_data_fim;
}

// Monta a query
$sql = "SELECT l.*, u.nome 
        FROM logs l 
        LEFT JOIN usuarios u ON l.id_usuario = u.id";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}

// Adiciona ordenação e um limite de segurança
$sql .= " ORDER BY l.timestamp DESC LIMIT 200";

$stmt_logs = $pdo->prepare($sql);
$stmt_logs->execute($params);
$logs = $stmt_logs->fetchAll();
?>

<?php render_page_title('Logs do Sistema', 'Rastreie as atividades importantes no sistema.', 'bi-clock-history'); ?>

<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-filter me-2"></i> Filtros de Log
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="logs">
            
            <div class="col-md-3">
                <label for="usuario_id" class="form-label">Usuário</label>
                <select id="usuario_id" name="usuario_id" class="form-select">
                    <option value="">Todos os Usuários</option>
                    <option value="0" <?php echo ($filtro_usuario_id === '0') ? 'selected' : ''; ?>>Sistema</option>
                    <?php foreach ($lista_usuarios as $usuario): ?>
                        <option value="<?php echo $usuario['id']; ?>" <?php echo ($filtro_usuario_id == $usuario['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($usuario['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="acao" class="form-label">Ação</label>
                <select id="acao" name="acao" class="form-select">
                    <option value="">Todas as Ações</option>
                     <?php foreach ($lista_acoes as $acao): ?>
                        <option value="<?php echo htmlspecialchars($acao['acao']); ?>" <?php echo ($filtro_acao == $acao['acao']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($acao['acao']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="data_inicio" class="form-label">De:</label>
                <input type="datetime-local" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio ?? ''); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="data_fim" class="form-label">Até:</label>
                <input type="datetime-local" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim ?? ''); ?>">
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Filtrar
                </button>
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
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Tabela</th>
                        <th>ID Afetado</th>
                        <th>Detalhes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Nenhum log encontrado para os filtros selecionados.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?></td>
                            <td class="fw-bold">
                                <?php echo htmlspecialchars($log['nome'] ?? 'Sistema'); ?>
                            </td>
                            <td><span class="badge bg-primary-subtle text-primary-emphasis"><?php echo htmlspecialchars($log['acao']); ?></span></td>
                            
                            <td><?php echo htmlspecialchars($log['tabela_afetada'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($log['id_afetado'] ?? 'N/A'); ?></td>
                            
                            <td><small class="text-muted" title="<?php echo htmlspecialchars($log['detalhes'] ?? ''); ?>">
                                <?php 
                                // Trunca detalhes longos para exibição
                                $detalhes = $log['detalhes'] ?? 'N/D';
                                if (strlen($detalhes) > 70) {
                                    echo htmlspecialchars(substr($detalhes, 0, 70)) . '...';
                                } else {
                                    echo htmlspecialchars($detalhes);
                                }
                                ?>
                            </small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>