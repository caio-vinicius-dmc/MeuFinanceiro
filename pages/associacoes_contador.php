<?php
// pages/associacoes_contador.php
global $pdo;

// Permissão RBAC: 'acessar_associacoes_contador'
// Mantemos os checks legados (isAdmin/isSuperAdmin) para compatibilidade.
if (!isLoggedIn() || !(isAdmin() || isSuperAdmin() || current_user_has_permission('acessar_associacoes_contador'))) {
    header('Location: ' . base_url('index.php?page=dashboard'));
    exit;
}

// Buscar solicitações
try {
    $stmt = $pdo->query("SELECT r.*, u.nome AS contador_nome, c.nome_responsavel AS cliente_nome
                         FROM contador_assoc_requests r
                         LEFT JOIN usuarios u ON r.id_usuario_contador = u.id
                         LEFT JOIN clientes c ON r.id_cliente = c.id
                         ORDER BY r.created_at DESC");
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}

render_page_title('Solicitações de Associação', 'Aprovar ou recusar pedidos de associação de contadores a clientes.', 'bi-people');
?>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Solicitações de Associação</h5>
    </div>
    <div class="card-body">
        <?php if (empty($requests)): ?>
            <div class="text-center text-muted">Nenhuma solicitação encontrada.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Contador</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['contador_nome'] ?? ('ID ' . $r['id_usuario_contador'])); ?></td>
                            <td><?php echo htmlspecialchars($r['cliente_nome'] ?? ('ID ' . $r['id_cliente'])); ?></td>
                            <td><?php echo htmlspecialchars($r['created_at'] ?? ''); ?></td>
                            <td>
                                <?php if ($r['status'] == 'pending'): ?>
                                    <span class="badge bg-warning text-dark">Pendente</span>
                                <?php elseif ($r['status'] == 'approved'): ?>
                                    <span class="badge bg-success">Aprovada</span>
                                <?php elseif ($r['status'] == 'rejected'): ?>
                                    <span class="badge bg-danger">Recusada</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($r['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['status'] == 'pending'): ?>
                                    <form action="process/crud_handler.php" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="aprovar_assoc_request">
                                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">Aprovar</button>
                                    </form>
                                    <form action="process/crud_handler.php" method="POST" class="d-inline ms-1">
                                        <input type="hidden" name="action" value="recusar_assoc_request">
                                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Recusar</button>
                                    </form>
                                <?php else: ?>
                                    <small class="text-muted">Sem ações</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
