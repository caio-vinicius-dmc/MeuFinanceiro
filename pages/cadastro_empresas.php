<?php
// pages/cadastro_empresas.php
global $pdo;

// Apenas Admin e Contador podem ver esta página
if (!isAdmin() && !isContador()) {
    header("Location: " . base_url('index.php?page=dashboard'));
    exit;
}

// Buscar clientes para o <select> do formulário de cadastro
$sql_clientes_form = "SELECT id, nome_responsavel FROM clientes";
$params_clientes_form = [];
if(isContador()) {
    // Contador só pode cadastrar para seus próprios clientes
    $sql_clientes_form .= " JOIN contador_clientes_assoc ca ON clientes.id = ca.id_cliente WHERE ca.id_usuario_contador = ?";
    $params_clientes_form[] = $_SESSION['user_id'];
}
$sql_clientes_form .= " ORDER BY nome_responsavel";
$stmt_clientes_form = $pdo->prepare($sql_clientes_form);
$stmt_clientes_form->execute($params_clientes_form);
$clientes_para_select = $stmt_clientes_form->fetchAll();


// Buscar empresas existentes para a listagem
$sql_empresas = "SELECT e.*, c.nome_responsavel 
                 FROM empresas e 
                 JOIN clientes c ON e.id_cliente = c.id";
$params_empresas = [];

if (isContador()) {
    // Contador só vê empresas dos seus clientes
    $stmt_clientes_assoc = $pdo->prepare("SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
    $stmt_clientes_assoc->execute([$_SESSION['user_id']]);
    $clientes_permitidos_ids = $stmt_clientes_assoc->fetchAll(PDO::FETCH_COLUMN);

    if (empty($clientes_permitidos_ids)) {
        $sql_empresas .= " WHERE 1=0"; // Não pode ver nada
    } else {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $sql_empresas .= " WHERE e.id_cliente IN ($placeholders)";
        $params_empresas = $clientes_permitidos_ids;
    }
}
$sql_empresas .= " ORDER BY c.nome_responsavel, e.razao_social";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->execute($params_empresas);
$empresas = $stmt_empresas->fetchAll();

?>

<h3>Cadastro de Empresas</h3>
<p class="text-muted">Gerencie as empresas e as vincule aos clientes.</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-building-add me-2"></i> Nova Empresa</h5>
            </div>
            <div class="card-body">
                <form action="process/crud_handler.php" method="POST">
                    <input type="hidden" name="action" value="cadastrar_empresa">
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="id_cliente" class="form-label">Cliente Proprietário</label>
                            <select id="id_cliente" name="id_cliente" class="form-select" required>
                                <option value="" selected disabled>Selecione o cliente...</option>
                                <?php foreach ($clientes_para_select as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>">
                                        <?php echo htmlspecialchars($cliente['nome_responsavel']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-7">
                            <label for="cnpj" class="form-label">CNPJ</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="cnpj" name="cnpj" placeholder="Digite o CNPJ..." required>
                                <span class="input-group-text" id="cnpj-loading" style="display: none;">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <label for="data_abertura" class="form-label">Data de Abertura</label>
                            <input type="date" class="form-control" id="data_abertura" name="data_abertura">
                        </div>

                        <div class="col-md-12">
                            <label for="razao_social" class="form-label">Razão Social</label>
                            <input type="text" class="form-control" id="razao_social" name="razao_social" required>
                        </div>

                        <div class="col-md-12">
                            <label for="nome_fantasia" class="form-label">Nome Fantasia</label>
                            <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia">
                        </div>
                        
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary">Salvar Empresa</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-task me-2"></i> Empresas Cadastradas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Razão Social</th>
                                <th>CNPJ</th>
                                <th>Cliente Vinculado</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($empresas)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Nenhuma empresa cadastrada.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($empresas as $empresa): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($empresa['razao_social']); ?></td>
                                <td><?php echo htmlspecialchars($empresa['cnpj']); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($empresa['nome_responsavel']); ?></small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditarEmpresa"
                                            data-id="<?php echo $empresa['id']; ?>"
                                            data-id_cliente="<?php echo $empresa['id_cliente']; ?>"
                                            data-cnpj="<?php echo htmlspecialchars($empresa['cnpj']); ?>"
                                            data-razao_social="<?php echo htmlspecialchars($empresa['razao_social']); ?>"
                                            data-nome_fantasia="<?php echo htmlspecialchars($empresa['nome_fantasia'] ?? ''); ?>"
                                            data-data_abertura="<?php echo htmlspecialchars($empresa['data_abertura'] ?? ''); ?>"
                                            title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    
                                    <form action="process/crud_handler.php" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta empresa? Todos os lançamentos associados a ela serão perdidos.');">
                                        <input type="hidden" name="action" value="deletar_empresa">
                                        <input type="hidden" name="id_empresa" value="<?php echo $empresa['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
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


<div class="modal fade" id="modalEditarEmpresa" tabindex="-1" aria-labelledby="modalEditarEmpresaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditarEmpresaLabel">Editar Empresa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process/crud_handler.php" method="POST">
                <input type="hidden" name="action" value="editar_empresa">
                <input type="hidden" name="id_empresa" id="edit_id_empresa">
                
                <div class="modal-body">
                     <div class="row g-3">
                        <div class="col-md-12">
                            <label for="edit_id_cliente" class="form-label">Cliente Proprietário</label>
                            <select id="edit_id_cliente" name="id_cliente" class="form-select" required>
                                <option value="" selected disabled>Selecione o cliente...</option>
                                <?php foreach ($clientes_para_select as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>">
                                        <?php echo htmlspecialchars($cliente['nome_responsavel']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-7">
                            <label for="edit_cnpj" class="form-label">CNPJ</label>
                            <input type="text" class="form-control" id="edit_cnpj" name="cnpj" required>
                        </div>
                        
                        <div class="col-md-5">
                            <label for="edit_data_abertura" class="form-label">Data de Abertura</label>
                            <input type="date" class="form-control" id="edit_data_abertura" name="data_abertura">
                        </div>

                        <div class="col-md-12">
                            <label for="edit_razao_social" class="form-label">Razão Social</label>
                            <input type="text" class="form-control" id="edit_razao_social" name="razao_social" required>
                        </div>

                        <div class="col-md-12">
                            <label for="edit_nome_fantasia" class="form-label">Nome Fantasia</label>
                            <input type="text" class="form-control" id="edit_nome_fantasia" name="nome_fantasia">
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