<?php
// pages/documentos.php
require_once __DIR__ . '/../config/functions.php';
requireLogin();

$folder_id = intval($_GET['folder_id'] ?? 0);
$user_id = $_SESSION['user_id'];

// If folder_id provided, show files inside; otherwise list root folders associated to user
if ($folder_id > 0) {
    // fetch folder
    $stmt = $pdo->prepare('SELECT * FROM documentos_pastas WHERE id = ?');
    $stmt->execute([$folder_id]);
    $pasta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pasta) {
        $_SESSION['error_message'] = 'Pasta não encontrada.';
        header('Location: index.php?page=documentos');
        exit;
    }
    // check access: admin or associated to the ROOT folder (associations are stored on root and inherit to subfolders)
    $hasAccess = false;
    if (isAdmin()) {
        $hasAccess = true;
    } else {
        // find root ancestor of the requested folder (walk up until parent_id is NULL or 0)
        $rootId = $pasta['id'];
        try {
            $cur = $pasta;
            while (!empty($cur['parent_id']) && intval($cur['parent_id']) != 0) {
                $stmtCr = $pdo->prepare('SELECT id, parent_id FROM documentos_pastas WHERE id = ?');
                $stmtCr->execute([intval($cur['parent_id'])]);
                $cur = $stmtCr->fetch(PDO::FETCH_ASSOC);
                if (!$cur) break;
                $rootId = $cur['id'];
            }
        } catch (Exception $e) {
            // fallback: use the current folder as root
            $rootId = $pasta['id'];
        }

        if (isClient()) {
            $cliente_id = $_SESSION['id_cliente_associado'] ?? null;
            if ($cliente_id && isClientAssociatedToPasta($cliente_id, $rootId)) $hasAccess = true;
            if (!$hasAccess && isUserAssociatedToPasta($user_id, $rootId)) $hasAccess = true;
        } else {
            if (isUserAssociatedToPasta($user_id, $rootId)) $hasAccess = true;
        }
    }

    if (!$hasAccess) {
        $_SESSION['error_message'] = 'Você não tem acesso a essa pasta.';
        header('Location: index.php?page=documentos');
        exit;
    }

    // fetch subfolders
    $stmt = $pdo->prepare('SELECT * FROM documentos_pastas WHERE parent_id = ? ORDER BY nome ASC');
    $stmt->execute([$folder_id]);
    $subpastas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // fetch arquivos
    $stmt = $pdo->prepare('SELECT a.*, u.nome as enviado_por_nome FROM documentos_arquivos a LEFT JOIN usuarios u ON a.enviado_por_user_id = u.id WHERE a.pasta_id = ? ORDER BY a.criado_em DESC');
    $stmt->execute([$folder_id]);
    $arquivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // build breadcrumb (walk up)
    $breadcrumb = [];
    $cur = $pasta;
    while ($cur) {
        $breadcrumb[] = ['id' => $cur['id'], 'nome' => $cur['nome']];
        if (empty($cur['parent_id'])) break;
        $stmtCr = $pdo->prepare('SELECT id, nome, parent_id FROM documentos_pastas WHERE id = ?');
        $stmtCr->execute([$cur['parent_id']]);
        $cur = $stmtCr->fetch(PDO::FETCH_ASSOC);
    }
    $breadcrumb = array_reverse($breadcrumb);

    } else {
    // list root folders for this user
    if (isAdmin()) {
        $stmt = $pdo->query('SELECT p.*, u.nome as owner_nome FROM documentos_pastas p LEFT JOIN usuarios u ON p.owner_user_id = u.id WHERE p.parent_id IS NULL ORDER BY p.nome ASC');
        $pastas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else if (isClient()) {
        $cliente_id = $_SESSION['id_cliente_associado'] ?? null;
        if (!$cliente_id) {
            $pastas = [];
        } else {
            try {
                $stmt = $pdo->prepare('SELECT p.*, GROUP_CONCAT(DISTINCT uu.nome SEPARATOR ", ") as associados FROM documentos_pastas p JOIN documentos_pastas_usuarios dpu ON p.id = dpu.pasta_id JOIN usuarios uu ON dpu.user_id = uu.id WHERE p.parent_id IS NULL AND uu.id_cliente_associado = ? GROUP BY p.id ORDER BY p.nome ASC');
                $stmt->execute([$cliente_id]);
                $pastas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // fallback to owner_user_id match
                $stmt = $pdo->prepare('SELECT p.*, u.nome as owner_nome FROM documentos_pastas p LEFT JOIN usuarios u ON p.owner_user_id = u.id WHERE p.parent_id IS NULL AND u.id_cliente_associado = ? ORDER BY p.nome ASC');
                $stmt->execute([$cliente_id]);
                $pastas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } else {
        // Try to fetch folders via pivot for normal users
        try {
            $stmt = $pdo->prepare('SELECT p.*, GROUP_CONCAT(uu.nome SEPARATOR ", ") as associados FROM documentos_pastas p JOIN documentos_pastas_usuarios dpu ON p.id = dpu.pasta_id LEFT JOIN usuarios uu ON dpu.user_id = uu.id WHERE p.parent_id IS NULL AND dpu.user_id = ? GROUP BY p.id ORDER BY p.nome ASC');
            $stmt->execute([$user_id]);
            $pastas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // fallback to owner_user_id
            $stmt = $pdo->prepare('SELECT p.*, u.nome as owner_nome FROM documentos_pastas p LEFT JOIN usuarios u ON p.owner_user_id = u.id WHERE p.parent_id IS NULL AND p.owner_user_id = ? ORDER BY p.nome ASC');
            $stmt->execute([$user_id]);
            $pastas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
?>

<div class="container-fluid">
    <?php // Título padrão: usa o mapeamento já calculado em header.php via $title_text quando disponível
    if (!function_exists('render_page_title')) {
        // caso raro: inclui helpers
        require_once __DIR__ . '/../config/functions.php';
    }
    // Só insere um título se a página ainda não definiu um título customizado
    if (!isset($page_title)) {
        // Exibe o título com ícone igual ao padrão usado em configuracoes_email.php
        render_page_title('Documentos', '', 'bi-folder2-open');
    }
    ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo $folder_id ? 'Conteúdo da Pasta: ' . htmlspecialchars($pasta['nome']) : 'Documentos'; ?></h5>
                    <?php if ($folder_id): ?>
                        <a class="btn btn-secondary" href="index.php?page=documentos">Voltar</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($folder_id): ?>
                        <!-- Breadcrumb -->
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php?page=documentos">Documentos</a></li>
                                <?php foreach ($breadcrumb as $bc): ?>
                                    <li class="breadcrumb-item <?php echo end($breadcrumb)['id'] === $bc['id'] ? 'active' : ''; ?>" <?php echo end($breadcrumb)['id'] === $bc['id'] ? 'aria-current="page"' : ''; ?>>
                                        <?php if (end($breadcrumb)['id'] === $bc['id']): ?>
                                            <?php echo htmlspecialchars($bc['nome']); ?>
                                        <?php else: ?>
                                            <a href="index.php?page=documentos&folder_id=<?php echo $bc['id']; ?>"><?php echo htmlspecialchars($bc['nome']); ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>

                        <div class="row g-3">
                            <?php if (empty($subpastas) && empty($arquivos)): ?>
                                <div class="col-12"><p class="text-muted">Nenhum item nesta pasta.</p></div>
                            <?php else: ?>
                                <?php
                                    // merge items: folders first then files (but rendered in same grid)
                                    $items = [];
                                    foreach ($subpastas as $sp) $items[] = ['type' => 'folder', 'data' => $sp];
                                    foreach ($arquivos as $a) $items[] = ['type' => 'file', 'data' => $a];
                                ?>
                                <?php foreach ($items as $it): ?>
                                    <?php if ($it['type'] === 'folder'): $sp = $it['data']; ?>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="mb-2">
                                                        <i class="bi bi-folder2-open fs-2 text-warning"></i>
                                                    </div>
                                                    <h6 class="card-title mb-1"><a href="index.php?page=documentos&folder_id=<?php echo $sp['id']; ?>"><?php echo htmlspecialchars($sp['nome']); ?></a></h6>
                                                    <div class="text-muted small mb-2">Criada: <?php echo htmlspecialchars($sp['created_at'] ?? '-'); ?></div>
                                                    <div class="mt-auto d-flex justify-content-between align-items-center">
                                                        <a class="btn btn-sm btn-outline-primary" href="index.php?page=documentos&folder_id=<?php echo $sp['id']; ?>">Abrir</a>
                                                        <span class="text-muted small">&nbsp;</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: $a = $it['data']; ?>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="mb-2">
                                                        <i class="bi bi-file-earmark-text fs-2 text-secondary"></i>
                                                    </div>
                                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($a['nome_original']); ?></h6>
                                                    <div class="text-muted small mb-2">Enviado por: <?php echo htmlspecialchars($a['enviado_por_nome'] ?? '-'); ?></div>
                                                    <div class="mb-2">
                                                        <?php if ($a['status'] === 'approved'): ?>
                                                            <span class="badge bg-success">Aprovado</span>
                                                        <?php elseif ($a['status'] === 'pending'): ?>
                                                            <span class="badge bg-warning text-dark">Pendente</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Rejeitado</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="mt-auto d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <?php if ($a['status'] === 'approved' || isAdmin() || intval($a['enviado_por_user_id']) === intval($user_id)): ?>
                                                                <a class="btn btn-sm btn-outline-primary" href="<?php echo base_url('process/serve_documento.php?id=' . $a['id']); ?>" target="_blank">Abrir</a>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <?php if (isAdmin()): ?>
                                                                <form style="display:inline" method="POST" action="process/documentos_handler.php">
                                                                    <input type="hidden" name="action" value="deletar_arquivo">
                                                                    <input type="hidden" name="arquivo_id" value="<?php echo $a['id']; ?>">
                                                                    <button class="btn btn-sm btn-danger" onclick="return confirm('Deseja excluir este arquivo?');">Excluir</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <hr>
                        <h6>Enviar novo arquivo</h6>
                        <form action="process/documentos_handler.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_arquivo">
                            <input type="hidden" name="pasta_id" value="<?php echo $folder_id; ?>">
                            <div class="mb-3">
                                <label for="arquivo" class="form-label">Arquivo</label>
                                <input type="file" class="form-control" id="arquivo" name="arquivo" required>
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-primary">Enviar</button>
                            </div>
                        </form>

                    <?php else: ?>
                        <?php if (empty($pastas)): ?>
                            <p class="text-muted">Nenhuma pasta disponível para você.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($pastas as $p): ?>
                                    <a href="index.php?page=documentos&folder_id=<?php echo $p['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($p['nome']); ?></strong>
                                            <div class="small text-muted">Associados: <?php echo htmlspecialchars($p['associados'] ?? ($p['owner_nome'] ?? '—')); ?></div>
                                        </div>
                                        <div class="text-end">
                                            <i class="bi bi-folder2-open fs-3"></i>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
