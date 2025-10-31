<?php
// pages/cadastro_usuarios.php
global $pdo;

// Apenas Admin pode ver esta página
if (!isAdmin()) {
    header("Location: " . base_url('index.php?page=dashboard'));
    exit;
}

// Buscar usuários existentes
$stmt_users = $pdo->query("SELECT id, nome, email, telefone, tipo, ativo, id_cliente_associado FROM usuarios ORDER BY nome");
$usuarios = $stmt_users->fetchAll();

// Buscar clientes para os selects (usado no form de 'Novo' e no 'Modal de Edição')
$stmt_clientes = $pdo->query("SELECT id, nome_responsavel FROM clientes ORDER BY nome_responsavel");
$clientes = $stmt_clientes->fetchAll();

// Buscar TODAS as associações de contadores para popular os modais
$stmt_assoc = $pdo->query("SELECT id_usuario_contador, id_cliente FROM contador_clientes_assoc");
$all_associations_raw = $stmt_assoc->fetchAll(PDO::FETCH_ASSOC);

// Mapear associações para fácil acesso: [id_usuario_contador => [id_cliente1, id_cliente2]]
$all_associations = [];
foreach ($all_associations_raw as $assoc) {
    $all_associations[$assoc['id_usuario_contador']][] = $assoc['id_cliente'];
}

?>

<h3>Cadastro de Usuários</h3>
<p class="text-muted">Gerencie os acessos ao sistema (Admins, Contadores e Clientes).</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Novo Usuário</h5>
            </div>
            <div class="card-body">
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="cadastrar_usuario">
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email (Login)</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>

                    <div class="mb-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" placeholder="(XX) XXXXX-XXXX">
                    </div>

                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha Provisória</label>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipo_usuario" class="form-label">Tipo de Usuário</label>
                        <select class="form-select" id="tipo_usuario" name="tipo_usuario" required>
                            <option value="" selected disabled>Selecione o tipo...</option>
                            <option value="admin">Admin</option>
                            <option value="contador">Contador</option>
                            <option value="cliente">Cliente (Acesso Portal)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="assoc_cliente_div" style="display: none;">
                        <label for="id_cliente_associado" class="form-label">Associar ao Cliente:</label>
                        <select class="form-select" id="id_cliente_associado" name="id_cliente_associado">
                            <option value="">Nenhum</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="assoc_contador_div" style="display: none;">
                        <label for="id_clientes_associados" class="form-label">Associar Contador aos Clientes:</label>
                        <select class="form-select" id="id_clientes_associados" name="id_clientes_associados[]" multiple size="5">
                             <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Segure Ctrl (ou Cmd) para selecionar vários.</small>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Salvar Usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
             <div class="card-header">
                <h5 class="mb-0">Usuários Cadastrados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email/Telefone</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario): ?>
                            <?php
                                // Pega as associações (IDs de clientes) deste contador
                                $user_assoc_ids = $all_associations[$usuario['id']] ?? [];
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($usuario['email']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($usuario['telefone'] ?? 'N/A'); ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo ucfirst($usuario['tipo']); ?></span></td>
                                <td>
                                    <?php echo ($usuario['ativo']) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-danger">Inativo</span>'; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditarUsuario"
                                            data-id="<?php echo $usuario['id']; ?>"
                                            data-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"
                                            data-email="<?php echo htmlspecialchars($usuario['email']); ?>"
                                            data-telefone="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>"
                                            data-tipo="<?php echo $usuario['tipo']; ?>"
                                            data-id_cliente_associado="<?php echo $usuario['id_cliente_associado']; ?>"
                                            data-assoc_clientes='<?php echo json_encode($user_assoc_ids); ?>'
                                            title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                        <input type="hidden" name="action" value="deletar_usuario">
                                        <input type="hidden" name="id_usuario" value="<?php echo $usuario['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir" <?php echo ($usuario['id'] == $_SESSION['user_id']) ? 'disabled' : ''; ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarUsuarioLabel">Editar Usuário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <input type="hidden" name="action" value="editar_usuario">
                <input type="hidden" name="id_usuario" id="edit_id_usuario">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_nome" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="edit_nome" name="nome" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email (Login)</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="edit_telefone" name="telefone" placeholder="(XX) XXXXX-XXXX">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_tipo_usuario" class="form-label">Tipo de Usuário</label>
                            <select class="form-select" id="edit_tipo_usuario" name="tipo_usuario" required>
                                <option value="admin">Admin</option>
                                <option value="contador">Contador</option>
                                <option value="cliente">Cliente (Acesso Portal)</option>
                            </select>
                        </div>

                        <hr>
                        
                        <div class="col-12" id="edit_assoc_cliente_div" style="display: none;">
                            <label for="edit_id_cliente_associado" class="form-label">Associar ao Cliente:</label>
                            <select class="form-select" id="edit_id_cliente_associado" name="id_cliente_associado">
                                <option value="">Nenhum</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12" id="edit_assoc_contador_div" style="display: none;">
                            <label for="edit_id_clientes_associados" class="form-label">Associar Contador aos Clientes:</label>
                            <select class="form-select" id="edit_id_clientes_associados" name="id_clientes_associados[]" multiple size="5">
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_responsavel']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Segure Ctrl (ou Cmd) para selecionar vários.</small>
                        </div>

                        <hr>
                        
                        <div class="col-12">
                             <label for="nova_senha" class="form-label">Nova Senha</label>
                             <input type="password" class="form-control" id="nova_senha" name="nova_senha">
                             <small class="form-text text-muted">Deixe em branco para não alterar a senha.</small>
                        </div>
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