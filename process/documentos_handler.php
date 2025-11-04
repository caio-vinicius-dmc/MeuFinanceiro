<?php
// process/documentos_handler.php
require_once '../config/functions.php';
requireLogin();
global $pdo;
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$user_id = $_SESSION['user_id'];

// detect ajax requests: X-Requested-With OR explicit ajax=1
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (!empty($_POST['ajax']) || !empty($_GET['ajax']));

function json_response($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// utility to redirect
function redirect_back($page = 'documentos', $extra = ''){
    header('Location: ' . base_url("index.php?page={$page}" . ($extra ? '&' . $extra : '')));
    exit;
}

try {
    switch ($action) {
        case 'criar_pasta_raiz':
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem criar pastas raiz.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            $nome = trim($_POST['nome'] ?? '');
            // Support multiple associated users
            $user_ids = $_POST['user_ids'] ?? [];
            if (!is_array($user_ids)) $user_ids = [$user_ids];
            if ($nome === '') {
                $_SESSION['error_message'] = 'Nome da pasta obrigatório.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            $stmt = $pdo->prepare('INSERT INTO documentos_pastas (nome, parent_id, owner_user_id) VALUES (?, NULL, ?)');
            // Keep owner_user_id for backward compatibility: use first selected user if provided
            $owner_user_id = null;
            if (!empty($user_ids)) {
                $owner_user_id = intval($user_ids[0]) ?: null;
            }
            $stmt->execute([$nome, $owner_user_id]);
            $_SESSION['success_message'] = 'Pasta raiz criada com sucesso.';
                $newId = $pdo->lastInsertId();
                logAction('Criou Pasta Raiz', 'documentos_pastas', $newId, $nome);

                // Insert associations into pivot table (if any)
                if (!empty($user_ids)) {
                    try {
                        $stmtIns = $pdo->prepare('INSERT INTO documentos_pastas_usuarios (pasta_id, user_id) VALUES (?, ?)');
                        foreach ($user_ids as $uid) {
                            $uid = intval($uid);
                            if ($uid <= 0) continue;
                            $stmtIns->execute([$newId, $uid]);
                        }
                    } catch (Exception $e) {
                        // if pivot missing, ignore (backward compatibility)
                        error_log('Não foi possível inserir associações na pivot: ' . $e->getMessage());
                    }
                }
                if ($isAjax) {
                    // return basic created folder info
                    $stmtInfo = $pdo->prepare('SELECT p.id, p.nome, p.parent_id, p.owner_user_id, p.created_at, u.nome as owner_nome FROM documentos_pastas p LEFT JOIN usuarios u ON p.owner_user_id = u.id WHERE p.id = ?');
                    $stmtInfo->execute([$newId]);
                    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                    json_response(['ok' => true, 'message' => 'Pasta raiz criada com sucesso.', 'pasta' => $info]);
                }
            redirect_back('gerenciar_documentos');
            break;

        case 'criar_subpasta':
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem criar subpastas.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            $nome = trim($_POST['nome'] ?? '');
            $parent_id = intval($_POST['parent_id'] ?? 0);
            if ($nome === '' || $parent_id <= 0) {
                $_SESSION['error_message'] = 'Dados inválidos para criar subpasta.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            $stmt = $pdo->prepare('INSERT INTO documentos_pastas (nome, parent_id, owner_user_id) VALUES (?, ?, NULL)');
            $stmt->execute([$nome, $parent_id]);
            $newId = $pdo->lastInsertId();
            $_SESSION['success_message'] = 'Subpasta criada com sucesso.';
            logAction('Criou Subpasta', 'documentos_pastas', $newId, $nome);
            if ($isAjax) {
                $stmtInfo = $pdo->prepare('SELECT p.id, p.nome, p.parent_id, p.owner_user_id, p.created_at, pr.nome as parent_nome, u.nome as owner_nome FROM documentos_pastas p LEFT JOIN documentos_pastas pr ON p.parent_id = pr.id LEFT JOIN usuarios u ON p.owner_user_id = u.id WHERE p.id = ?');
                $stmtInfo->execute([$newId]);
                $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                json_response(['ok' => true, 'message' => 'Subpasta criada com sucesso.', 'pasta' => $info]);
            }
            redirect_back('gerenciar_documentos');
            break;

        case 'associar_pasta_usuario':
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem associar pastas a usuários.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            $pasta_id = intval($_POST['pasta_id'] ?? 0);
            $user_ids = $_POST['user_ids'] ?? [];
            if ($pasta_id <= 0) {
                $_SESSION['error_message'] = 'Pasta inválida.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }

            // Business rule: associations only allowed on root folders (parent_id IS NULL)
            try {
                $stmtRootCheck = $pdo->prepare('SELECT parent_id FROM documentos_pastas WHERE id = ?');
                $stmtRootCheck->execute([$pasta_id]);
                $pr = $stmtRootCheck->fetch(PDO::FETCH_ASSOC);
                if ($pr && $pr['parent_id'] !== null) {
                    $_SESSION['error_message'] = 'Associações só são permitidas para pastas raiz.';
                    if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                    redirect_back('gerenciar_documentos');
                }
            } catch (Exception $e) {
                // ignore check errors and proceed (fallback behavior)
            }

            // Normalize array
            if (!is_array($user_ids)) $user_ids = [$user_ids];

            try {
                // Try to use pivot table: delete existing and insert new
                $pdo->beginTransaction();
                try {
                    $stmtDel = $pdo->prepare('DELETE FROM documentos_pastas_usuarios WHERE pasta_id = ?');
                    $stmtDel->execute([$pasta_id]);
                    $stmtIns = $pdo->prepare('INSERT INTO documentos_pastas_usuarios (pasta_id, user_id) VALUES (?, ?)');
                    foreach ($user_ids as $uid) {
                        $uid = intval($uid);
                        if ($uid <= 0) continue;
                        $stmtIns->execute([$pasta_id, $uid]);
                    }
                    // Also update owner_user_id for backward compatibility using first user
                    $firstOwner = !empty($user_ids) ? intval($user_ids[0]) : null;
                    $stmtUp = $pdo->prepare('UPDATE documentos_pastas SET owner_user_id = ? WHERE id = ?');
                    $stmtUp->execute([$firstOwner, $pasta_id]);
                    $pdo->commit();
                    $_SESSION['success_message'] = 'Associação atualizada.';
                    logAction('Associou Pasta a Usuários', 'documentos_pastas', $pasta_id, 'user_ids=' . implode(',', $user_ids));
                    if ($isAjax) {
                        json_response(['ok' => true, 'message' => 'Associação atualizada.', 'count' => count($user_ids), 'user_ids' => $user_ids]);
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            } catch (Exception $e) {
                // Fallback: update owner_user_id only
                try {
                    $firstOwner = !empty($user_ids) ? intval($user_ids[0]) : null;
                    $stmt = $pdo->prepare('UPDATE documentos_pastas SET owner_user_id = ? WHERE id = ?');
                    $stmt->execute([$firstOwner, $pasta_id]);
                    $_SESSION['success_message'] = 'Associação atualizada (fallback owner_user_id).';
                    logAction('Associou Pasta a Usuário (fallback)', 'documentos_pastas', $pasta_id, 'user_id=' . $firstOwner);
                    if ($isAjax) {
                        json_response(['ok' => true, 'message' => 'Associação atualizada (fallback owner_user_id).', 'count' => ($firstOwner ? 1 : 0), 'user_ids' => $user_ids]);
                    }
                } catch (Exception $e2) {
                    $_SESSION['error_message'] = 'Erro ao atualizar associação: ' . $e2->getMessage();
                    if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                }
            }
            redirect_back('gerenciar_documentos');
            break;

        case 'editar_pasta':
            // Edita nome e parent de uma pasta
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem editar pastas.';
                redirect_back('gerenciar_documentos');
            }
            $pasta_id = intval($_POST['pasta_id'] ?? 0);
            // fetch pasta to check owner
            $stmtCheck = $pdo->prepare('SELECT * FROM documentos_pastas WHERE id = ?');
            $stmtCheck->execute([$pasta_id]);
            $pasta_check = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$pasta_check) {
                $_SESSION['error_message'] = 'Pasta não encontrada.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            // Administrador pode editar qualquer pasta (regra de negócio: apenas administradores acessam essa tela)
            $nome = trim($_POST['nome'] ?? '');
            $new_parent = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null;
            if ($pasta_id <= 0 || $nome === '') {
                $_SESSION['error_message'] = 'Dados inválidos para editar pasta.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            // Prevent invalid parent (self or descendant)
            if ($new_parent === $pasta_id) {
                $_SESSION['error_message'] = 'A pasta não pode ser pai de si mesma.';
                redirect_back('gerenciar_documentos');
            }
            // check for descendant loop
            if ($new_parent !== null) {
                $cur = $new_parent;
                $isDesc = false;
                while ($cur !== null) {
                    $stmtTmp = $pdo->prepare('SELECT parent_id FROM documentos_pastas WHERE id = ?');
                    $stmtTmp->execute([$cur]);
                    $row = $stmtTmp->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { $cur = null; break; }
                    $cur = $row['parent_id'] !== null ? intval($row['parent_id']) : null;
                    if ($cur === $pasta_id) { $isDesc = true; break; }
                }
                if ($isDesc) {
                    $_SESSION['error_message'] = 'Não é possível definir um descendente como pai.';
                    if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                    redirect_back('gerenciar_documentos');
                }
            }
            $stmt = $pdo->prepare('UPDATE documentos_pastas SET nome = ?, parent_id = ? WHERE id = ?');
            $stmt->execute([$nome, $new_parent, $pasta_id]);
            $_SESSION['success_message'] = 'Pasta atualizada.';
            logAction('Editou Pasta', 'documentos_pastas', $pasta_id, $nome);
            if ($isAjax) {
                // return updated pasta info
                $stmtInfo = $pdo->prepare('SELECT p.id, p.nome, p.parent_id, p.owner_user_id, p.created_at, pr.nome as parent_nome, u.nome as owner_nome FROM documentos_pastas p LEFT JOIN documentos_pastas pr ON p.parent_id = pr.id LEFT JOIN usuarios u ON p.owner_user_id = u.id WHERE p.id = ?');
                $stmtInfo->execute([$pasta_id]);
                $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
                json_response(['ok' => true, 'message' => 'Pasta atualizada.', 'pasta' => $info]);
            }
            redirect_back('gerenciar_documentos');
            break;

        case 'deletar_pasta':
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem excluir pastas.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            $pasta_id = intval($_POST['pasta_id'] ?? 0);
            if ($pasta_id <= 0) {
                $_SESSION['error_message'] = 'Pasta inválida.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            // fetch pasta
            $stmt = $pdo->prepare('SELECT * FROM documentos_pastas WHERE id = ?');
            $stmt->execute([$pasta_id]);
            $pasta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pasta) {
                $_SESSION['error_message'] = 'Pasta não encontrada.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }
            // Administrador pode excluir qualquer pasta (regra de negócio: apenas administradores acessam essa tela)

                // fetch children ids and count children and files
                $stmtChildren = $pdo->prepare('SELECT id FROM documentos_pastas WHERE parent_id = ?');
                $stmtChildren->execute([$pasta_id]);
                $childrenRows = $stmtChildren->fetchAll(PDO::FETCH_COLUMN);
                $childrenCount = count($childrenRows);
            // collect child ids to return to client after reparenting (if ajax)
            $stmtCh = $pdo->prepare('SELECT id FROM documentos_pastas WHERE parent_id = ?');
            $stmtCh->execute([$pasta_id]);
            $childRows = $stmtCh->fetchAll(PDO::FETCH_ASSOC);
            $childrenIds = array_map(function($r){ return $r['id']; }, $childRows);
            $stmt = $pdo->prepare('SELECT COUNT(1) FROM documentos_arquivos WHERE pasta_id = ?');
            $stmt->execute([$pasta_id]);
            $filesCount = intval($stmt->fetchColumn());

            // If files exist and no parent to move to, prevent deletion to avoid orphaning
            $parent_id = $pasta['parent_id'] !== null ? intval($pasta['parent_id']) : null;
            if ($filesCount > 0 && $parent_id === null) {
                $_SESSION['error_message'] = 'A pasta contém arquivos. Mova-os ou crie uma pasta pai antes de excluir.';
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
                redirect_back('gerenciar_documentos');
            }

            try {
                $pdo->beginTransaction();

                // Reparent children (preserve subfolder references)
                $stmt = $pdo->prepare('UPDATE documentos_pastas SET parent_id = ? WHERE parent_id = ?');
                $stmt->execute([$parent_id, $pasta_id]);

                // Move files to parent (both DB and file system) if any
                if ($filesCount > 0 && $parent_id !== null) {
                    // ensure parent dir exists
                    $srcDir = __DIR__ . '/../uploads/documentos/' . $pasta_id;
                    $destDir = __DIR__ . '/../uploads/documentos/' . $parent_id;
                    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

                    $stmtFiles = $pdo->prepare('SELECT id, caminho FROM documentos_arquivos WHERE pasta_id = ?');
                    $stmtFiles->execute([$pasta_id]);
                    $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($files as $f) {
                        $oldPath = __DIR__ . '/../' . $f['caminho'];
                        $filename = basename($oldPath);
                        $newRelPath = 'uploads/documentos/' . $parent_id . '/' . $filename;
                        $newFullPath = __DIR__ . '/../' . $newRelPath;
                        // try move file
                            if (file_exists($oldPath)) {
                                // if target exists, generate unique name
                                if (file_exists($newFullPath)) {
                                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                                    $nameNoExt = pathinfo($filename, PATHINFO_FILENAME);
                                    $filename = $nameNoExt . '_' . time() . '.' . $ext;
                                    $newRelPath = 'uploads/documentos/' . $parent_id . '/' . $filename;
                                    $newFullPath = __DIR__ . '/../' . $newRelPath;
                                }

                                // Try to move with rename first (fast, preserves inode). If it fails (different mounts), fallback to copy+unlink.
                                $moved = false;
                                try {
                                    if (@rename($oldPath, $newFullPath)) {
                                        $moved = true;
                                    } else {
                                        // Attempt copy then unlink
                                        if (@copy($oldPath, $newFullPath)) {
                                            // verify copy succeeded
                                            if (file_exists($newFullPath)) {
                                                @unlink($oldPath);
                                                $moved = true;
                                            }
                                        }
                                    }
                                } catch (Exception $mvEx) {
                                    // ignore here and allow $moved to be false
                                    error_log('Erro ao mover arquivo durante exclusão de pasta: ' . $mvEx->getMessage());
                                }

                                if (!$moved) {
                                    // If move failed, throw to rollback transaction and notify admin
                                    throw new Exception('Falha ao mover arquivo "' . $filename . '" para a pasta pai. Operação abortada.');
                                }
                            }
                        // update DB record
                        $stmtUp = $pdo->prepare('UPDATE documentos_arquivos SET pasta_id = ?, caminho = ? WHERE id = ?');
                        $stmtUp->execute([$parent_id, $newRelPath, $f['id']]);
                    }
                }

                // delete pivot associations for this pasta
                try {
                    $stmtDelPivot = $pdo->prepare('DELETE FROM documentos_pastas_usuarios WHERE pasta_id = ?');
                    $stmtDelPivot->execute([$pasta_id]);
                } catch (Exception $e) {
                    // ignore if pivot table missing
                }

                // remove the folder record
                $stmtDel = $pdo->prepare('DELETE FROM documentos_pastas WHERE id = ?');
                $stmtDel->execute([$pasta_id]);

                // attempt to remove empty directory
                $dirToRemove = __DIR__ . '/../uploads/documentos/' . $pasta_id;
                if (is_dir($dirToRemove)) {
                    // remove files if any remain
                    $filesRemaining = glob($dirToRemove . '/*');
                    if (empty($filesRemaining)) {
                        @rmdir($dirToRemove);
                    }
                }

                $pdo->commit();
                $_SESSION['success_message'] = 'Pasta excluída com sucesso. Subpastas foram reatribuídas.';
                logAction('Excluiu Pasta', 'documentos_pastas', $pasta_id);
                if ($isAjax) {
                    // fetch updated info for affected children
                    $affected = [];
                    if (!empty($childrenIds)) {
                        $in = implode(',', array_map('intval', $childrenIds));
                        $stmtAff = $pdo->query("SELECT p.id, p.parent_id, pr.nome as parent_nome FROM documentos_pastas p LEFT JOIN documentos_pastas pr ON p.parent_id = pr.id WHERE p.id IN ({$in})");
                        $affected = $stmtAff->fetchAll(PDO::FETCH_ASSOC);
                    }
                    json_response(['ok' => true, 'message' => 'Pasta excluída com sucesso.', 'pasta_id' => $pasta_id, 'affected_children' => $affected]);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['error_message'] = 'Erro ao excluir pasta: ' . $e->getMessage();
                if ($isAjax) json_response(['ok' => false, 'error' => $_SESSION['error_message']]);
            }
            redirect_back('gerenciar_documentos');
            break;

        case 'upload_arquivo':
            // Any logged user can upload into a folder that they have access to (owner) or admin can upload anywhere.
            $pasta_id = intval($_POST['pasta_id'] ?? 0);
            if ($pasta_id <= 0) {
                $_SESSION['error_message'] = 'Pasta inválida.';
                redirect_back('documentos');
            }
            // check access: user can upload only if associated to the folder or admin
            $stmt = $pdo->prepare('SELECT * FROM documentos_pastas WHERE id = ?');
            $stmt->execute([$pasta_id]);
            $pasta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pasta) {
                $_SESSION['error_message'] = 'Pasta não encontrada.';
                redirect_back('documentos');
            }
            if (!isAdmin() && !isUserAssociatedToPasta($user_id, $pasta_id)) {
                $_SESSION['error_message'] = 'Você não tem permissão para enviar para essa pasta.';
                redirect_back('documentos');
            }

            if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error_message'] = 'Falha no upload. Verifique o arquivo e tente novamente.';
                redirect_back('documentos', 'folder_id=' . $pasta_id);
            }

            $upload = $_FILES['arquivo'];
            $originalName = $upload['name'];
            $tmp = $upload['tmp_name'];
            $size = $upload['size'];
            $mime = $upload['type'] ?? mime_content_type($tmp);

            // Validações: tipo e tamanho (usando política centralizada)
            $policy = getDocumentUploadPolicy();
            $allowed_mimes = $policy['allowed_mimes'];
            $max_size_bytes = $policy['max_size_bytes'];

            if ($size > $max_size_bytes) {
                $_SESSION['error_message'] = 'Arquivo maior que o permitido (' . round($max_size_bytes / 1024 / 1024, 2) . ' MB).';
                redirect_back('documentos', 'folder_id=' . $pasta_id);
            }

            if (!in_array($mime, $allowed_mimes)) {
                $_SESSION['error_message'] = 'Tipo de arquivo não permitido. Permitidos: ' . implode(', ', array_map(function($m){ return $m; }, $allowed_mimes));
                redirect_back('documentos', 'folder_id=' . $pasta_id);
            }

            // Prepare directory
            $baseDir = __DIR__ . '/../uploads/documentos/' . $pasta_id;
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0755, true);
            }

            // Generate stored filename
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $storedName = time() . '_' . bin2hex(random_bytes(6)) . ($ext ? '.' . $ext : '');
            $destPath = $baseDir . '/' . $storedName;

            if (!move_uploaded_file($tmp, $destPath)) {
                $_SESSION['error_message'] = 'Erro ao mover arquivo para pasta de destino.';
                redirect_back('documentos', 'folder_id=' . $pasta_id);
            }

            // Save record
            $relativePath = 'uploads/documentos/' . $pasta_id . '/' . $storedName;

            // If uploader is admin, auto-approve; otherwise set as pending
            if (isAdmin()) {
                $stmt = $pdo->prepare('INSERT INTO documentos_arquivos (pasta_id, nome_original, caminho, tamanho, tipo_mime, enviado_por_user_id, status, aprovado_por, aprovado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$pasta_id, $originalName, $relativePath, $size, $mime, $user_id, 'approved', $user_id]);
                $newFileId = $pdo->lastInsertId();
                $_SESSION['success_message'] = 'Arquivo enviado e aprovado.';
                logAction('Upload Arquivo (auto-aprovado)', 'documentos_arquivos', $newFileId, $originalName);
            } else {
                $stmt = $pdo->prepare('INSERT INTO documentos_arquivos (pasta_id, nome_original, caminho, tamanho, tipo_mime, enviado_por_user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$pasta_id, $originalName, $relativePath, $size, $mime, $user_id, 'pending']);
                $newFileId = $pdo->lastInsertId();
                $_SESSION['success_message'] = 'Arquivo enviado e aguarda aprovação do administrador.';
                logAction('Upload Arquivo', 'documentos_arquivos', $newFileId, $originalName);

                // Notifica administradores por email (se configurado)
                try {
                    $stmtAdmins = $pdo->query("SELECT nome, email FROM usuarios WHERE user_tipo = 'admin' AND email IS NOT NULL AND email != ''");
                    $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
                    $subject = "Novo arquivo aguardando aprovação: {$originalName}";
                    $body = "<p>Olá,</p><p>Um novo arquivo foi enviado para a pasta <strong>" . htmlspecialchars($pasta['nome']) . "</strong> pelo usuário ID {$user_id} e aguarda aprovação.</p><p>Arquivo: <strong>" . htmlspecialchars($originalName) . "</strong></p><p><a href='" . base_url("index.php?page=gerenciar_documentos") . "'>Ir para fila de aprovação</a></p>";
                    foreach ($admins as $adm) {
                        if (!empty($adm['email'])) {
                            sendDocumentNotification($adm['email'], $adm['nome'] ?? 'Admin', $subject, $body);
                        }
                    }
                } catch (Exception $e) {
                    error_log('Erro ao notificar administradores: ' . $e->getMessage());
                }
            }
            redirect_back('documentos', 'folder_id=' . $pasta_id);
            break;

        case 'aprovar_arquivo':
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem aprovar arquivos.';
                redirect_back('gerenciar_documentos');
            }
            $arquivo_id = intval($_POST['arquivo_id'] ?? 0);
            $aprovado = ($_POST['acao'] ?? '') === 'aprovar';
            if ($arquivo_id <= 0) {
                $_SESSION['error_message'] = 'Arquivo inválido.';
                redirect_back('gerenciar_documentos');
            }
            if ($aprovado) {
                $stmt = $pdo->prepare('UPDATE documentos_arquivos SET status = ?, aprovado_por = ?, aprovado_em = NOW() WHERE id = ?');
                $stmt->execute(['approved', $user_id, $arquivo_id]);
                $_SESSION['success_message'] = 'Arquivo aprovado.';
                logAction('Aprovou Arquivo', 'documentos_arquivos', $arquivo_id, 'aprovar');

                // Notificar o autor do arquivo
                try {
                    $stmtUser = $pdo->prepare('SELECT u.email, u.nome FROM usuarios u JOIN documentos_arquivos a ON a.enviado_por_user_id = u.id WHERE a.id = ?');
                    $stmtUser->execute([$arquivo_id]);
                    $u = $stmtUser->fetch(PDO::FETCH_ASSOC);
                    if ($u && !empty($u['email'])) {
                        $subject = 'Seu arquivo foi aprovado';
                        $body = "<p>Olá " . htmlspecialchars($u['nome']) . ",</p><p>Seu arquivo foi aprovado pelo administrador e já está disponível.</p><p>Você pode visualizá-lo em: <a href='" . base_url("process/serve_documento.php?id={$arquivo_id}") . "'>Abrir arquivo</a></p>";
                        sendDocumentNotification($u['email'], $u['nome'], $subject, $body);
                    }
                } catch (Exception $e) {
                    error_log('Erro ao notificar autor do arquivo: ' . $e->getMessage());
                }

            } else {
                $stmt = $pdo->prepare('UPDATE documentos_arquivos SET status = ? WHERE id = ?');
                $stmt->execute(['rejected', $arquivo_id]);
                $_SESSION['success_message'] = 'Arquivo rejeitado.';
                logAction('Reprovou Arquivo', 'documentos_arquivos', $arquivo_id, 'reprovar');

                // Notificar o autor do arquivo sobre rejeição
                try {
                    $stmtUser = $pdo->prepare('SELECT u.email, u.nome FROM usuarios u JOIN documentos_arquivos a ON a.enviado_por_user_id = u.id WHERE a.id = ?');
                    $stmtUser->execute([$arquivo_id]);
                    $u = $stmtUser->fetch(PDO::FETCH_ASSOC);
                    if ($u && !empty($u['email'])) {
                        $subject = 'Seu arquivo foi rejeitado';
                        $body = "<p>Olá " . htmlspecialchars($u['nome']) . ",</p><p>Seu arquivo foi rejeitado pelo administrador.</p>";
                        sendDocumentNotification($u['email'], $u['nome'], $subject, $body);
                    }
                } catch (Exception $e) {
                    error_log('Erro ao notificar autor do arquivo (rejeição): ' . $e->getMessage());
                }
            }
            redirect_back('gerenciar_documentos');
            break;

        case 'deletar_arquivo':
            // allow owner or admin to delete
            $arquivo_id = intval($_POST['arquivo_id'] ?? 0);
            if ($arquivo_id <= 0) {
                $_SESSION['error_message'] = 'Arquivo inválido.';
                redirect_back('documentos');
            }
            $stmt = $pdo->prepare('SELECT * FROM documentos_arquivos WHERE id = ?');
            $stmt->execute([$arquivo_id]);
            $arquivo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$arquivo) {
                $_SESSION['error_message'] = 'Arquivo não encontrado.';
                redirect_back('documentos');
            }
            // Only administrators are allowed to delete files (clients/contadors cannot delete)
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem excluir arquivos.';
                redirect_back('documentos');
            }
            // delete file from disk
            $path = __DIR__ . '/../' . $arquivo['caminho'];
            if (file_exists($path)) {
                @unlink($path);
            }
            $stmt = $pdo->prepare('DELETE FROM documentos_arquivos WHERE id = ?');
            $stmt->execute([$arquivo_id]);
            $_SESSION['success_message'] = 'Arquivo excluído.';
            logAction('Excluiu Arquivo', 'documentos_arquivos', $arquivo_id);
            redirect_back('documentos');
            break;

        default:
            $_SESSION['error_message'] = 'Ação inválida.';
            redirect_back();
            break;
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro: ' . $e->getMessage();
    redirect_back();
}
