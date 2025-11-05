<?php
// process/crud_handler.php
require_once '../config/functions.php';
requireLogin(); 

global $pdo;
// detect company column name at runtime (empresa_id or id_empresa)
$company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
// Permite receber 'action' via POST (formulários) ou GET (botão de email)
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$user_id = $_SESSION['user_id'];

// Limpa mensagens antigas
unset($_SESSION['success_message'], $_SESSION['error_message']);

switch ($action) {

        case 'enviar_cobranca_email':
            // Envia por email uma cobrança específica para o contato do cliente/empresa
            $id_cobranca = $_GET['id'] ?? $_POST['id'] ?? null;
            if (!$id_cobranca) {
                $_SESSION['error_message'] = 'ID da cobrança ausente.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            try {
        // Busca dados da cobrança com empresa e cliente (usa coluna dinâmica de empresa)
        $sql = "SELECT cob.*, emp.razao_social, emp.id_cliente, cli.nome_responsavel, cli.email_contato
            FROM cobrancas cob
            JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
            LEFT JOIN clientes cli ON emp.id_cliente = cli.id
            WHERE cob.id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_cobranca]);
                $cob = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$cob) {
                    $_SESSION['error_message'] = 'Cobrança não encontrada.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }

                // Permissões: admin/contador podem enviar qualquer cobrança; cliente só sua
                if (isClient()) {
                    $id_cliente_logado = $_SESSION['id_cliente_associado'] ?? null;
                    if ($id_cliente_logado != $cob['id_cliente']) {
                        $_SESSION['error_message'] = 'Você não tem permissão para enviar esta cobrança.';
                        header('Location: ' . base_url('index.php?page=cobrancas'));
                        exit;
                    }
                }

                // Destinatário: email do cliente vinculado à empresa
                $toEmail = $cob['email_contato'] ?? null;
                $toName = $cob['nome_responsavel'] ?? ($cob['razao_social'] ?? 'Cliente');

                if (empty($toEmail)) {
                    $_SESSION['error_message'] = 'Email do cliente não encontrado. Verifique cadastro do cliente.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }

                // Prepara dados no formato esperado pela função de notificação
                $lancamento_like = [
                    'descricao' => $cob['descricao'] ?? 'Cobrança',
                    'valor' => $cob['valor'],
                    'data_vencimento' => $cob['data_vencimento'],
                    'tipo' => 'receita'
                ];

                $sent = sendNotificationEmail($toEmail, $toName, $lancamento_like);

                if ($sent) {
                    $_SESSION['success_message'] = 'Email enviado com sucesso para ' . htmlspecialchars($toEmail);
                    logAction('Enviou Cobrança por Email', 'cobrancas', $id_cobranca, 'Email para: ' . $toEmail);
                } else {
                    $_SESSION['error_message'] = 'Falha no envio do email. Verifique as configurações SMTP.';
                    logAction('Falha no envio de Cobrança por Email', 'cobrancas', $id_cobranca, 'Tentativa para: ' . $toEmail);
                }

            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Erro ao tentar enviar email: ' . $e->getMessage();
            }

            header('Location: ' . base_url('index.php?page=cobrancas'));
            exit;
            break;

        case 'test_smtp':
            // Testar envio SMTP usando as configurações salvas
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem testar a conexão SMTP.';
                header('Location: ' . base_url('index.php?page=configuracoes_email'));
                exit;
            }

            $settings = getSmtpSettings();
            $to = $settings['smtp_username'] ?? $settings['email_from'];
            $toName = 'Administrador';

            if (empty($to)) {
                $_SESSION['error_message'] = 'Nenhum destinatário válido encontrado (smtp_username ou email_from). Configure antes de testar.';
                header('Location: ' . base_url('index.php?page=configuracoes_email'));
                exit;
            }

            $testLanc = [
                'descricao' => 'Teste de Conexão SMTP',
                'valor' => 0,
                'data_vencimento' => date('Y-m-d'),
                'tipo' => 'receita'
            ];

            $sent = sendNotificationEmail($to, $toName, $testLanc);
            if ($sent) {
                $_SESSION['success_message'] = 'Teste de SMTP enviado com sucesso para ' . htmlspecialchars($to);
            } else {
                $_SESSION['error_message'] = 'Falha ao enviar e-mail de teste. Verifique as configurações SMTP e execute composer install.';
            }
            header('Location: ' . base_url('index.php?page=configuracoes_email'));
            exit;
            break;

    case 'criar_categoria_lancamento':
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem criar categorias.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if ($nome === '') {
            $_SESSION['error_message'] = 'Nome da categoria obrigatório.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO categorias_lancamento (nome, descricao, ativo) VALUES (?, ?, ?)');
            $stmt->execute([$nome, $descricao, $ativo]);
            $_SESSION['success_message'] = 'Categoria criada com sucesso.';
            logAction('Criou Categoria Lancamento', 'categorias_lancamento', $pdo->lastInsertId(), $nome);
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao criar categoria: ' . $e->getMessage();
        }
        header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
        exit;
        break;

    case 'editar_categoria_lancamento':
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem editar categorias.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        $id = intval($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if ($id <= 0 || $nome === '') {
            $_SESSION['error_message'] = 'Dados inválidos para atualização.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        try {
            $stmt = $pdo->prepare('UPDATE categorias_lancamento SET nome = ?, descricao = ?, ativo = ? WHERE id = ?');
            $stmt->execute([$nome, $descricao, $ativo, $id]);
            $_SESSION['success_message'] = 'Categoria atualizada com sucesso.';
            logAction('Editou Categoria Lancamento', 'categorias_lancamento', $id, $nome);
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao atualizar categoria: ' . $e->getMessage();
        }
        header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
        exit;
        break;

    case 'excluir_categoria_lancamento':
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem excluir categorias.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error_message'] = 'ID inválido.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        try {
            // Antes de excluir, removemos referência em lançamentos (set null)
            $pdo->beginTransaction();
            $stmtNull = $pdo->prepare('UPDATE lancamentos SET id_categoria = NULL WHERE id_categoria = ?');
            $stmtNull->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM categorias_lancamento WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();
            $_SESSION['success_message'] = 'Categoria excluída com sucesso.';
            logAction('Excluiu Categoria Lancamento', 'categorias_lancamento', $id);
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Erro ao excluir categoria: ' . $e->getMessage();
        }
        header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
        exit;
        break;

    case 'import_lancamentos':
        // Importa um CSV de lançamentos para a empresa selecionada.
        if (!(isAdmin() || isContador() || isClient())) {
            $_SESSION['error_message'] = 'Você não tem permissão para importar lançamentos.';
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Checa arquivo
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error_message'] = 'Arquivo CSV não enviado ou inválido.';
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Determine target company: prefer posted id_empresa, fallback to current selected company
        $id_empresa = null;
        if (!empty($_POST['id_empresa'])) $id_empresa = intval($_POST['id_empresa']);
        if (empty($id_empresa)) {
            $id_empresa = current_company_id();
        }
        // additional enforcement: ensure user may act on this company
        if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
        if (empty($id_empresa)) {
            $_SESSION['error_message'] = 'Selecione a empresa destino para a importação.';
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Verifica permissão para a empresa selecionada: allow global admin or user associated with company
        try {
            $stmtCheck = $pdo->prepare('SELECT id_cliente FROM empresas WHERE id = ?');
            $stmtCheck->execute([$id_empresa]);
            $empresa_cliente = $stmtCheck->fetchColumn();
            if ($empresa_cliente === false) {
                $_SESSION['error_message'] = 'Empresa selecionada não encontrada.';
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }

            if (!isAdmin()) {
                // if client role exists in legacy app, keep client rules
                if (isClient()) {
                    $id_cliente_logado = $_SESSION['id_cliente_associado'] ?? null;
                    if ($id_cliente_logado != $empresa_cliente) {
                        $_SESSION['error_message'] = 'Você não tem permissão para importar para esta empresa.';
                        header('Location: ' . base_url('index.php?page=lancamentos'));
                        exit;
                    }
                }
                // For contador or other roles, require the user to be associated to the company
                if (!user_has_any_role($_SESSION['user_id'], $id_empresa)) {
                    // contador legacy rule: check contador_clientes_assoc if that table is used
                    if (isContador()) {
                        $stmtAssoc = $pdo->prepare('SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?');
                        $stmtAssoc->execute([$_SESSION['user_id']]);
                        $assoc = $stmtAssoc->fetchAll(PDO::FETCH_COLUMN);
                        if (!in_array($empresa_cliente, $assoc)) {
                            $_SESSION['error_message'] = 'Você não está associado ao cliente desta empresa.';
                            header('Location: ' . base_url('index.php?page=lancamentos'));
                            exit;
                        }
                    } else {
                        $_SESSION['error_message'] = 'Você não tem permissão para importar para esta empresa.';
                        header('Location: ' . base_url('index.php?page=lancamentos'));
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao validar permissão: ' . $e->getMessage();
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Helpers locais
        function normalize_header($s) {
            $s = mb_strtolower(trim($s));
            $trans = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c'];
            $s = strtr($s, $trans);
            $s = preg_replace('/[^a-z0-9]/', '', $s);
            return $s;
        }

        function parse_decimal($raw) {
            $r = trim((string)$raw);
            if ($r === '') return null;
            // Remove currency symbols and spaces
            $r = preg_replace('/[R$\s]/u','',$r);
            // If contains comma and dot, assume dot thousand and comma decimal -> remove dots, replace comma
            if (strpos($r, ',') !== false && strpos($r, '.') !== false) {
                $r = str_replace('.', '', $r);
                $r = str_replace(',', '.', $r);
            } else {
                // replace comma with dot
                $r = str_replace(',', '.', $r);
            }
            // remove any non-numeric except dot and minus
            $r = preg_replace('/[^0-9.\-]/', '', $r);
            return is_numeric($r) ? (float)$r : null;
        }

        function parse_date_flex($s) {
            $s = trim((string)$s);
            if ($s === '') return null;
            // YYYY-MM-DD
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
            // DD/MM/YYYY
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                $parts = explode('/',$s);
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
            // Try strtotime
            $t = strtotime($s);
            if ($t !== false) return date('Y-m-d', $t);
            return null;
        }

        // Mapeia formas de pagamento e categorias do sistema (nome => id) para validação rápida
        $formas_map = [];
        $stmt_fp = $pdo->prepare('SELECT id, nome FROM formas_pagamento');
        $stmt_fp->execute();
        $all_formas = $stmt_fp->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_formas as $fp) {
            $formas_map[mb_strtolower(trim($fp['nome']))] = $fp['id'];
        }

        // Categorias (pode não existir ainda se migration não aplicada)
        $categorias_map = [];
        try {
            $stmt_cat = $pdo->prepare('SELECT id, nome FROM categorias_lancamento WHERE ativo = 1');
            $stmt_cat->execute();
            $all_cats = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all_cats as $c) {
                $categorias_map[mb_strtolower(trim($c['nome']))] = $c['id'];
            }
        } catch (Exception $e) {
            // Se a tabela categorias_lancamento não existir ainda, apenas ignoramos (import/cadastro seguirá sem validação por nome)
        }

        $tmp = $_FILES['csv_file']['tmp_name'];
        $inserted = 0;
        $errors = [];

        try {
            if (!is_uploaded_file($tmp)) {
                throw new Exception('Arquivo inválido ou não enviado corretamente.');
            }
            // Verifica extensão do arquivo (ajuda a pegar uploads incorretos)
            $originalName = $_FILES['csv_file']['name'] ?? '';
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'])) {
                throw new Exception('Tipo de arquivo inválido. Envie um arquivo com extensão .csv');
            }
            $filesize = @filesize($tmp);
            if ($filesize === 0 || $filesize === false) {
                throw new Exception('Arquivo vazio. Verifique o conteúdo do CSV.');
            }

            if (($handle = fopen($tmp, 'r')) === false) {
                throw new Exception('Não foi possível abrir o arquivo CSV para leitura.');
            }

            // Read header (CSV com separador ';')
            $header = fgetcsv($handle, 0, ';', '"');
            if ($header === false) {
                throw new Exception('Arquivo CSV vazio ou inválido.');
            }
            // Detect common mistake: file uses comma as delimiter
            if (count($header) === 1 && strpos($header[0], ',') !== false) {
                throw new Exception('Parece que seu arquivo usa vírgula (,) como separador. O importador aceita apenas ponto-e-vírgula (;). Por favor, converta o arquivo para usar ponto-e-vírgula.');
            }
            $map = [];
            foreach ($header as $i => $h) {
                $norm = normalize_header($h);
                // Map expected names
                if (in_array($norm, ['descricao','desc','descr'])) $map[$i] = 'descricao';
                else if (in_array($norm, ['valor','val','vlor'])) $map[$i] = 'valor';
                else if (in_array($norm, ['tipo','tip'])) $map[$i] = 'tipo';
                else if (in_array($norm, ['datavencimento','datavenc','vencimento','datavto','datavenc'])) $map[$i] = 'data_vencimento';
                else if (in_array($norm, ['datacompetencia','datacompet','competencia','compet'])) $map[$i] = 'data_competencia';
                else if (in_array($norm, ['datapagamento','datapag','data_pagamento','pagamento'])) $map[$i] = 'data_pagamento';
                else if (in_array($norm, ['formadepagamento','formapagamento','formapagto','forma_pagamento','metodopagamento','metodo_pagamento'])) $map[$i] = 'metodo_pagamento';
                else if (in_array($norm, ['status','situacao','situacao_pagamento','estado'])) $map[$i] = 'status';
                else if (in_array($norm, ['categoria','categoria_lancamento','categoria_lanc','categoria_lancamentos'])) $map[$i] = 'categoria';
                else $map[$i] = null; // ignora colunas não reconhecidas
            }

            // Verifica se o cabeçalho possui as colunas obrigatórias
            $mappedFields = array_values(array_filter($map));
            $hasDescricao = in_array('descricao', $mappedFields);
            $hasValor = in_array('valor', $mappedFields);
            $hasTipo = in_array('tipo', $mappedFields);
            $hasVenc = in_array('data_vencimento', $mappedFields);
            if (!($hasDescricao && $hasValor && $hasTipo && $hasVenc)) {
                throw new Exception('Cabeçalho inválido. Colunas obrigatórias: Descrição, Valor, Tipo e Data vencimento. Verifique o arquivo e tente novamente.');
            }

            // Prepara statements de insert (detecta coluna id_forma_pagamento e id_categoria)
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
            $colStmt->execute();
            $has_forma_col = $colStmt->fetchColumn() > 0;

            $colStmt2 = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_categoria'");
            $colStmt2->execute();
            $has_categoria_col = $colStmt2->fetchColumn() > 0;

            // Monta SQL dinâmico conforme colunas disponíveis
        // Use the configured company column name (empresa_id or id_empresa)
        $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
        $insertCols = array_merge([$company_col], ['descricao','valor','tipo','data_vencimento','data_competencia','data_pagamento','metodo_pagamento']);
            if ($has_forma_col) $insertCols[] = 'id_forma_pagamento';
            if ($has_categoria_col) $insertCols[] = 'id_categoria';
            $insertCols[] = 'status';
            $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
            $insertSql = "INSERT INTO lancamentos (" . implode(',', $insertCols) . ") VALUES ($placeholders)";
            $stmtInsert = $pdo->prepare($insertSql);

            // Leitura e validação de todas as linhas antes de inserir (tudo ou nada)
            $allRows = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
                $line++;
                $allRows[] = ['line' => $line, 'row' => $row];
            }

            $prepared = [];
            foreach ($allRows as $rinfo) {
                $line = $rinfo['line'];
                $row = $rinfo['row'];
                $data = ['descricao'=>null,'valor'=>null,'tipo'=>null,'data_vencimento'=>null,'data_competencia'=>null,'data_pagamento'=>null,'metodo_pagamento'=>null,'status'=>null];
                foreach ($row as $i => $cell) {
                    if (!isset($map[$i]) || $map[$i] === null) continue;
                    $data[$map[$i]] = $cell;
                }

                $rowErrors = [];
                if (empty(trim((string)$data['descricao']))) $rowErrors[] = 'Descrição ausente';
                $valor = parse_decimal($data['valor']);
                if ($valor === null) $rowErrors[] = 'Valor inválido';
                $tipo = mb_strtolower(trim((string)$data['tipo']));
                if (!in_array($tipo, ['receita','despesa'])) $rowErrors[] = 'Tipo inválido (use receita/despesa)';
                $dv = parse_date_flex($data['data_vencimento']);
                if ($dv === null) $rowErrors[] = 'Data vencimento inválida ou ausente';
                $dc = parse_date_flex($data['data_competencia']);
                $dp = parse_date_flex($data['data_pagamento']);

                $metodo = trim((string)$data['metodo_pagamento']);
                $id_forma = null;
                if ($metodo !== '') {
                    $key = mb_strtolower($metodo);
                    if (isset($formas_map[$key])) {
                        $id_forma = $formas_map[$key];
                    } else {
                        $rowErrors[] = 'Forma de pagamento não encontrada: ' . $metodo;
                    }
                }

                // Categoria (opcional) - mapear nome -> id se a coluna existir
                $id_categoria = null;
                if (isset($data['categoria']) && trim((string)$data['categoria']) !== '') {
                    $cat_key = mb_strtolower(trim((string)$data['categoria']));
                    if (isset($categorias_map[$cat_key])) {
                        $id_categoria = $categorias_map[$cat_key];
                    } else {
                        $rowErrors[] = 'Categoria não encontrada: ' . $data['categoria'];
                    }
                }

                $status_raw = trim((string)$data['status']);
                $status_norm = null;
                if ($status_raw !== '') {
                    $skey = mb_strtolower($status_raw);
                    if (strpos($skey, 'pago') !== false) $status_norm = 'pago';
                    elseif (strpos($skey, 'aberto') !== false || strpos($skey, 'pendente') !== false) $status_norm = 'pendente';
                    else $rowErrors[] = 'Status inválido (use Em aberto ou Pago)';
                } else {
                    $status_norm = 'pendente';
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Linha $line: " . implode('; ', $rowErrors);
                    continue;
                }

                $valor_grav = $valor;
                $metodo_txt = $metodo !== '' ? $metodo : null;

                // Monta params seguindo a ordem de $insertCols
                $params = [];
                $params[] = $id_empresa;
                $params[] = $data['descricao'];
                $params[] = $valor_grav;
                $params[] = $tipo;
                $params[] = $dv;
                $params[] = $dc ?: null;
                $params[] = $dp ?: null;
                $params[] = $metodo_txt;
                if ($has_forma_col) $params[] = $id_forma;
                if ($has_categoria_col) $params[] = $id_categoria;
                $params[] = $status_norm;
                $prepared[] = $params;
            }

            if (!empty($errors)) {
                fclose($handle);
                $_SESSION['error_message'] = 'Arquivo possui linhas inválidas. Nenhum lançamento foi importado.';
                $_SESSION['import_errors'] = $errors;
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }

            // Insere todos os registros validados dentro de uma transação
            $pdo->beginTransaction();
            try {
                foreach ($prepared as $params) {
                    $stmtInsert->execute($params);
                    $inserted++;
                }
                $pdo->commit();
                fclose($handle);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if (isset($handle) && is_resource($handle)) fclose($handle);
                $_SESSION['error_message'] = 'Erro ao inserir registros: ' . $e->getMessage();
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }
        } catch (Exception $e) {
            // Em caso de erro, desfaz qualquer inserção parcial e informa o usuário
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            $_SESSION['error_message'] = 'Importação inválida: ' . $e->getMessage();
            // Anexa detalhes das linhas que falharam, se houver
            if (!empty($errors)) {
                $_SESSION['import_errors'] = $errors;
            }
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Prepara mensagem de retorno
        $msgs = [];
        if ($inserted > 0) $msgs[] = "{$inserted} lançamentos importados com sucesso.";
        if (!empty($errors)) {
            // grava erros no session como lista (limitado)
            $_SESSION['error_message'] = 'Algumas linhas não foram importadas. Veja detalhes abaixo.';
            $_SESSION['import_errors'] = $errors;
        } else {
            $_SESSION['success_message'] = implode(' ', $msgs);
        }
        // Se houver mensagens de sucesso e erro simultâneas, prioriza exibir erro + sucesso
        if (!empty($errors) && $inserted > 0) {
            $_SESSION['success_message'] = implode(' ', $msgs);
        }

        header('Location: ' . base_url('index.php?page=lancamentos'));
        exit;
        break;

    case 'cadastrar_lancamento':
        if (isAdmin() || isContador() || isClient()) {
            $id_empresa = $_POST['id_empresa'] ?? null;
            // se não enviado, tenta usar a empresa atual da sessão
            if (empty($id_empresa)) {
                $id_empresa = current_company_id();
            }
            if (empty($id_empresa)) {
                $_SESSION['error_message'] = 'Selecione a empresa para o lançamento.';
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }
            // Verifica se o usuário tem associação com a empresa (a menos que seja admin global)
            if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $id_empresa)) {
                $_SESSION['error_message'] = 'Você não tem permissão para criar lançamentos nessa empresa.';
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }
            $descricao = $_POST['descricao'] ?? null;
            $valor = $_POST['valor'] ?? null;
            $tipo = $_POST['tipo'] ?? 'receita';
            $data_vencimento = $_POST['data_vencimento'] ?? null;
            $data_competencia = $_POST['data_competencia'] ?? null;
            $data_pagamento = $_POST['data_pagamento'] ?? null;
            $metodo_pagamento = $_POST['metodo_pagamento'] ?? null;
            $id_forma = isset($_POST['id_forma_pagamento']) ? ($_POST['id_forma_pagamento'] !== '' ? $_POST['id_forma_pagamento'] : null) : null;
            $id_categoria = isset($_POST['id_categoria']) ? ($_POST['id_categoria'] !== '' ? $_POST['id_categoria'] : null) : null;
            $status = $_POST['status'] ?? 'pendente';

            // Detecta colunas opcionais
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
            $colStmt->execute();
            $has_forma_col = $colStmt->fetchColumn() > 0;
            $colStmt2 = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_categoria'");
            $colStmt2->execute();
            $has_categoria_col = $colStmt2->fetchColumn() > 0;

            // Monta colunas e params dinamicamente (usa coluna de empresa detectada dinamicamente)
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            $cols = array_merge([$company_col], ['descricao','valor','tipo','data_vencimento','data_competencia','data_pagamento','metodo_pagamento']);
            $params = [$id_empresa,$descricao,$valor,$tipo,$data_vencimento,$data_competencia ?: null,$data_pagamento ?: null,$metodo_pagamento];
            if ($has_forma_col) { $cols[] = 'id_forma_pagamento'; $params[] = $id_forma; }
            if ($has_categoria_col) { $cols[] = 'id_categoria'; $params[] = $id_categoria; }
            $cols[] = 'status'; $params[] = $status;

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO lancamentos (" . implode(',', $cols) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            try {
                if ($stmt->execute($params)) {
                    $novo_id = $pdo->lastInsertId();
                    $_SESSION['success_message'] = 'Lançamento criado com sucesso.';
                    logAction('Criou Lançamento', 'lancamentos', $novo_id, $descricao);
                } else {
                    $_SESSION['error_message'] = 'Erro ao criar lançamento.';
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Erro ao criar lançamento: ' . $e->getMessage();
            }
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }
        break;

    case 'editar_lancamento':
        if (isAdmin() || isContador() || isClient()) { // All can edit
            $id = $_POST['id_lancamento'];
            $id_empresa_novo = $_POST['id_empresa'];
            $descricao_novo = $_POST['descricao'];
            $valor_novo = $_POST['valor'];
            $tipo_novo = $_POST['tipo'];
            $data_vencimento_novo = $_POST['data_vencimento'];
            $data_competencia_novo = $_POST['data_competencia'] ?? null;
            $metodo_pagamento_novo = $_POST['metodo_pagamento'] ?? null;
            $id_forma_pagamento_novo = isset($_POST['id_forma_pagamento']) ? ($_POST['id_forma_pagamento'] !== '' ? $_POST['id_forma_pagamento'] : null) : null;
            $id_categoria_novo = isset($_POST['id_categoria']) ? ($_POST['id_categoria'] !== '' ? $_POST['id_categoria'] : null) : null;
            // Detecta se a coluna id_forma_pagamento existe
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
            $colStmt->execute();
            $has_forma_col = $colStmt->fetchColumn() > 0;
            // Detecta se a coluna id_categoria existe
            $colStmtCat = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_categoria'");
            $colStmtCat->execute();
            $has_categoria_col = $colStmtCat->fetchColumn() > 0;
            $status_novo = $_POST['status']; // New: status can be edited

            // 1. AUDITORIA: Busca dados antigos
            // Seleciona colunas antigas para auditoria (inclui id_forma_pagamento e id_categoria quando existirem)
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            // alias company column to empresa_ref for consistent access
            $selectCols = ["{$company_col} AS empresa_ref",'descricao','valor','tipo','data_vencimento','data_competencia','metodo_pagamento','status'];
            if ($has_forma_col) $selectCols[] = 'id_forma_pagamento';
            if ($has_categoria_col) $selectCols[] = 'id_categoria';
            $stmt_old = $pdo->prepare("SELECT " . implode(',', $selectCols) . " FROM lancamentos WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
            // Security: ensure non-admin users can only edit lancamentos for companies they belong to
            if (!isAdmin()) {
                $empresa_antiga = $old_data['empresa_ref'] ?? null;
                if ($empresa_antiga === null) {
                    $_SESSION['error_message'] = 'Empresa vinculada ao lançamento não encontrada.';
                    header('Location: ' . base_url('index.php?page=lancamentos'));
                    exit;
                }
                if (!user_has_any_role($_SESSION['user_id'], $empresa_antiga)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para editar este lançamento.';
                    header('Location: ' . base_url('index.php?page=lancamentos'));
                    exit;
                }
            }
            
            // 2. AUDITORIA: Compara e monta a string de detalhes
            $detalhes_log = [];
            
          if (($old_data['empresa_ref'] ?? null) !== $id_empresa_novo) {
              $stmt_empresa = $pdo->prepare("SELECT razao_social FROM empresas WHERE id = ?");
              $stmt_empresa->execute([$id_empresa_novo]);
              $nome_empresa_novo = $stmt_empresa->fetchColumn() ?? 'N/D';
              $stmt_empresa->execute([$old_data['empresa_ref'] ?? 0]);
              $nome_empresa_antigo = $stmt_empresa->fetchColumn() ?? 'N/D';
              $detalhes_log[] = "Empresa: {$nome_empresa_antigo} -> {$nome_empresa_novo}";
          }
            if ($old_data['descricao'] !== $descricao_novo) {
                 $detalhes_log[] = "Descrição: {$old_data['descricao']} -> {$descricao_novo}";
            }
            if ($old_data['valor'] != $valor_novo) {
                 $detalhes_log[] = "Valor: R$ " . number_format($old_data['valor'], 2, ',', '.') . " -> R$ " . number_format($valor_novo, 2, ',', '.');
            }
            if ($old_data['tipo'] !== $tipo_novo) {
                 $detalhes_log[] = "Tipo: {$old_data['tipo']} -> {$tipo_novo}";
            }
            if ($old_data['data_vencimento'] !== $data_vencimento_novo) {
                 $detalhes_log[] = "Vencimento: " . date('d/m/Y', strtotime($old_data['data_vencimento'])) . " -> " . date('d/m/Y', strtotime($data_vencimento_novo));
            }
            if (($old_data['data_competencia'] ?? null) !== ($data_competencia_novo ?? null)) {
                $detalhes_log[] = "Competência: " . ($old_data['data_competencia'] ? date('d/m/Y', strtotime($old_data['data_competencia'])) : 'N/D') . " -> " . ($data_competencia_novo ? date('d/m/Y', strtotime($data_competencia_novo)) : 'N/D');
            }
            if (($old_data['metodo_pagamento'] ?? null) !== ($metodo_pagamento_novo ?? null) || ($has_forma_col && (($old_data['id_forma_pagamento'] ?? null) !== ($id_forma_pagamento_novo ?? null)))) {
                $det_old = ($old_data['metodo_pagamento'] ?? 'N/D') . ($has_forma_col ? (' (id: ' . ($old_data['id_forma_pagamento'] ?? 'N/D') . ')') : '');
                $det_new = ($metodo_pagamento_novo ?? 'N/D') . ($has_forma_col ? (' (id: ' . ($id_forma_pagamento_novo ?? 'N/D') . ')') : '');
                $detalhes_log[] = "Forma Pgto: " . $det_old . " -> " . $det_new;
            }
            // Categoria
            if ($has_categoria_col) {
                if (($old_data['id_categoria'] ?? null) !== ($id_categoria_novo ?? null)) {
                    // busca nomes para log amigável
                    $old_cat_name = 'N/D';
                    $new_cat_name = 'N/D';
                    if (!empty($old_data['id_categoria'])) {
                        $s = $pdo->prepare("SELECT nome FROM categorias_lancamento WHERE id = ?");
                        $s->execute([$old_data['id_categoria']]);
                        $old_cat_name = $s->fetchColumn() ?? 'N/D';
                    }
                    if (!empty($id_categoria_novo)) {
                        $s2 = $pdo->prepare("SELECT nome FROM categorias_lancamento WHERE id = ?");
                        $s2->execute([$id_categoria_novo]);
                        $new_cat_name = $s2->fetchColumn() ?? 'N/D';
                    }
                    $detalhes_log[] = "Categoria: {$old_cat_name} -> {$new_cat_name}";
                }
            }
            if ($old_data['status'] !== $status_novo) {
                 $detalhes_log[] = "Status: {$old_data['status']} -> {$status_novo}";
            }

            if (empty($detalhes_log)) {
                 $_SESSION['success_message'] = "Lançamento não alterado (nenhuma mudança detectada).";
                 $pagina_redirecionar = base_url('index.php?page=lancamentos') . '&_t=' . time();
                 header("Location: $pagina_redirecionar");
                 exit;
            }
            
            $log_details_string = "Campos alterados: " . implode('; ', $detalhes_log);

            // 3. Atualiza o banco de dados
            // Monta UPDATE dinâmico conforme colunas presentes (usa nome de coluna de empresa dinamicamente)
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            $update_fields = [
                "{$company_col} = ?", 'descricao = ?', 'valor = ?', 'tipo = ?', 'data_vencimento = ?', 'data_competencia = ?', 'metodo_pagamento = ?'
            ];
            $update_params = [$id_empresa_novo, $descricao_novo, $valor_novo, $tipo_novo, $data_vencimento_novo, $data_competencia_novo, $metodo_pagamento_novo];
            if ($has_forma_col) {
                $update_fields[] = 'id_forma_pagamento = ?';
                $update_params[] = $id_forma_pagamento_novo;
            }
            if ($has_categoria_col) {
                $update_fields[] = 'id_categoria = ?';
                $update_params[] = $id_categoria_novo;
            }
            $update_fields[] = 'status = ?';
            $update_params[] = $status_novo;

            $sql = "UPDATE lancamentos SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $update_params[] = $id;
            $params = $update_params;
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $_SESSION['success_message'] = "Lançamento atualizado com sucesso!";
                logAction("Edição Lançamento", "lancamentos", $id, $log_details_string);
            } else {
                $_SESSION['error_message'] = "Erro ao atualizar lançamento.";
            }
            $pagina_redirecionar = base_url('index.php?page=lancamentos') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    case 'excluir_lancamento': // Ensure company scoping before delete
        if (isAdmin() || isContador() || isClient()) {
            $id = intval($_GET['id'] ?? 0);
            if ($id) {
                try {
                    $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                    $stmtC = $pdo->prepare("SELECT `" . $company_col . "` FROM lancamentos WHERE id = ?");
                    $stmtC->execute([$id]);
                    $empresa_ref = $stmtC->fetchColumn();
                    if ($empresa_ref === false) {
                        $_SESSION['error_message'] = 'Lançamento não encontrado.';
                        header('Location: ' . base_url('index.php?page=lancamentos'));
                        exit;
                    }
                    if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                        $_SESSION['error_message'] = 'Você não tem permissão para excluir este lançamento.';
                        header('Location: ' . base_url('index.php?page=lancamentos'));
                        exit;
                    }
                    $sql = "DELETE FROM lancamentos WHERE id = ? AND `" . $company_col . "` = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$id, $empresa_ref])) {
                        $_SESSION['success_message'] = "Lançamento excluído com sucesso!";
                        logAction("Excluiu Lançamento", "lancamentos", $id);
                    } else {
                        $_SESSION['error_message'] = "Erro ao excluir lançamento.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
            $pagina_redirecionar = base_url('index.php?page=lancamentos') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    case 'atualizar_status_lancamento':
        if (isAdmin() || isContador() || isClient()) {
            $id = $_POST['id_lancamento'];
            $novo_status = $_POST['status']; // Expected to be 'Pago' or 'Em aberto'
            $data_pagamento = $_POST['data_pagamento'] ?? null;
            $metodo_pagamento = $_POST['metodo_pagamento'] ?? null;

            error_log("DEBUG: atualizar_status_lancamento - ID: $id, Novo Status Recebido: $novo_status");

            // Get current lancamento data for comparison and logging
            // Fetch old lancamento data including company for permission checks
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            $stmt_old_data = $pdo->prepare("SELECT status, data_vencimento, data_pagamento, metodo_pagamento, `" . $company_col . "` AS empresa_ref FROM lancamentos WHERE id = ?");
            $stmt_old_data->execute([$id]);
            $old_lancamento_data = $stmt_old_data->fetch(PDO::FETCH_ASSOC);

            if (!$old_lancamento_data) {
                $_SESSION['error_message'] = "Lançamento não encontrado.";
                break;
            }

            // Permission: non-admins must be associated to the company
            if (!isAdmin()) {
                $empresa_ref = $old_lancamento_data['empresa_ref'] ?? null;
                if ($empresa_ref === null) {
                    $_SESSION['error_message'] = 'Empresa vinculada ao lançamento não encontrada.';
                    break;
                }
                if (!user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para alterar o status deste lançamento.';
                    break;
                }
            }

            if (!$old_lancamento_data) {
                $_SESSION['error_message'] = "Lançamento não encontrado.";
                error_log("DEBUG: Lançamento ID $id não encontrado.");
                header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
                exit;
            }

            $old_status = $old_lancamento_data['status'];
            $old_data_pagamento = $old_lancamento_data['data_pagamento'];
                $data_pagamento = $_POST['data_pagamento'] ?? null;
                $metodo_pagamento = $_POST['metodo_pagamento'] ?? null;
                $id_forma_pagamento = isset($_POST['id_forma_pagamento']) ? ($_POST['id_forma_pagamento'] !== '' ? $_POST['id_forma_pagamento'] : null) : null;
            error_log("DEBUG: Old Status: $old_status, Old Data Pagamento: $old_data_pagamento, Old Metodo Pagamento: $old_metodo_pagamento");

            $log_details = [];
            $update_fields = [];
            $update_params = [];

            // Handle status change
                if (!$old_lancamento_data) {
                    $_SESSION['error_message'] = "Lançamento não encontrado.";
                    error_log("DEBUG: Lançamento ID $id não encontrado.");
                    header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
                    exit;
                }
            if ($novo_status == 'Pago') {
                if ($old_status != 'pago') {
                    $update_fields[] = "status = ?";
                    $update_params[] = 'pago';
                    $log_details[] = "Status: $old_status -> pago";
                }
                // Only update data_pagamento and metodo_pagamento if marking as paid
                if ($data_pagamento && $data_pagamento != $old_data_pagamento) {
                    $update_fields[] = "data_pagamento = ?";
                    $update_params[] = $data_pagamento;
                    $log_details[] = "Data Pagamento: " . ($old_data_pagamento ?? 'N/D') . " -> $data_pagamento";
                }
                if ($metodo_pagamento && $metodo_pagamento != $old_metodo_pagamento) {
                    $update_fields[] = "metodo_pagamento = ?";
                    $update_params[] = $metodo_pagamento;
                    $log_details[] = "Forma Pgto: " . ($old_metodo_pagamento ?? 'N/D') . " -> $metodo_pagamento";
                }
            } elseif ($novo_status == 'Em aberto') {
                
                // Se o status antigo era 'pago', forçamos a mudança para 'pendente' e limpamos os campos de pagamento.
                // Adicionamos os campos de atualização diretamente aqui.
                if (strtolower($old_status) == 'pago') {
                    $update_fields[] = "status = ?";
                    $update_params[] = 'pendente';
                    $log_details[] = "Status: $old_status -> pendente";

                    // Limpa data_pagamento
                    $update_fields[] = "data_pagamento = NULL";
                    $log_details[] = "Data Pagamento: " . ($old_data_pagamento ?? 'N/D') . " -> NULL";
                    if ($id_forma_pagamento !== null && $id_forma_pagamento != ($old_lancamento_data['id_forma_pagamento'] ?? null)) {
                        $update_fields[] = "id_forma_pagamento = ?";
                        $update_params[] = $id_forma_pagamento;
                        $log_details[] = "Forma Pgto (id): " . (($old_lancamento_data['id_forma_pagamento'] ?? 'N/D')) . " -> " . $id_forma_pagamento;
                    }
                    
                    // Limpa metodo_pagamento
                    $update_fields[] = "metodo_pagamento = NULL";
                    $log_details[] = "Forma Pgto: " . ($old_metodo_pagamento ?? 'N/D') . " -> NULL";
                }
            }

            if (empty($update_fields)) {
                $_SESSION['success_message'] = "Nenhuma alteração necessária para o lançamento.";
                header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
                exit;
            }

            try {
                $sql = "UPDATE lancamentos SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $update_params[] = $id;
                        // Limpa id_forma_pagamento também se existir
                        if ($has_forma_col) {
                            $update_fields[] = "id_forma_pagamento = NULL";
                            $log_details[] = "Forma Pgto (id): " . (($old_lancamento_data['id_forma_pagamento'] ?? 'N/D')) . " -> NULL";
                        }
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute($update_params)) {
                    $_SESSION['success_message'] = "Lançamento atualizado com sucesso!";
                    logAction("Atualizou Lançamento", "lancamentos", $id, implode("; ", $log_details));
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $_SESSION['error_message'] = "Erro ao atualizar lançamento: " . ($errorInfo[2] ?? "Erro desconhecido.");
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
            header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
            exit;
        }
        break;


    // --- AÇÕES DE CADASTRO (EMPRESA) ---

    case 'cadastrar_empresa':
        if (isAdmin() || isContador()) {
            $id_cliente = $_POST['id_cliente'];
            $cnpj = $_POST['cnpj'];
            $razao_social = $_POST['razao_social'];
            $data_abertura = !empty($_POST['data_abertura']) ? $_POST['data_abertura'] : null;

            $sql = "INSERT INTO empresas (id_cliente, cnpj, razao_social, nome_fantasia, data_abertura) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id_cliente, $cnpj, $_POST['razao_social'], $_POST['nome_fantasia'], $data_abertura])) {
                $id_novo = $pdo->lastInsertId();
                $_SESSION['success_message'] = "Empresa cadastrada com sucesso!";
                logAction("Cadastro Empresa", "empresas", $id_novo, "CNPJ: $cnpj"); 
            } else {
                $_SESSION['error_message'] = "Erro ao cadastrar empresa.";
            }
        }
        break;

    case 'editar_empresa':
        if (isAdmin() || isContador()) {
             $id_empresa = $_POST['id_empresa'];
             if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
             $data_abertura = !empty($_POST['data_abertura']) ? $_POST['data_abertura'] : null;

             $sql_update = "UPDATE empresas SET id_cliente = ?, cnpj = ?, razao_social = ?, nome_fantasia = ?, data_abertura = ? WHERE id = ?";
             $stmt_update = $pdo->prepare($sql_update);
             
             if ($stmt_update->execute([$_POST['id_cliente'], $_POST['cnpj'], $_POST['razao_social'], $_POST['nome_fantasia'], $data_abertura, $id_empresa])) {
                 $_SESSION['success_message'] = "Empresa atualizada com sucesso!";
                 logAction("Edição Empresa", "empresas", $id_empresa, "CNPJ: " . $_POST['cnpj']);
             } else {
                 $_SESSION['error_message'] = "Erro ao atualizar empresa.";
             }
        }
        break;

    case 'deletar_empresa':
        if (isAdmin() || isContador()) {
             $id_empresa = $_POST['id_empresa'];
             if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
             $sql = "DELETE FROM empresas WHERE id = ?";
             $stmt = $pdo->prepare($sql);
             if ($stmt->execute([$id_empresa])) {
                 $_SESSION['success_message'] = "Empresa excluída com sucesso!";
                 logAction("Exclusão Empresa", "empresas", $id_empresa);
             } else {
                 $_SESSION['error_message'] = "Erro ao excluir empresa.";
             }
        }
        break;


    // --- AÇÕES DE CADASTRO (CLIENTE) ---
    
    case 'cadastrar_cliente':
         if (isAdmin() || isContador()) {
            $sql = "INSERT INTO clientes (nome_responsavel, email_contato, telefone) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$_POST['nome_responsavel'], $_POST['email_contato'], $_POST['telefone']])) {
                $id_novo = $pdo->lastInsertId();
                $_SESSION['success_message'] = "Cliente cadastrado com sucesso!";
                logAction("Cadastro Cliente", "clientes", $id_novo, "Nome: " . $_POST['nome_responsavel']);
            } else {
                 $_SESSION['error_message'] = "Erro ao cadastrar cliente.";
            }
         }
        break;

    case 'editar_cliente':
         if (isAdmin() || isContador()) {
            $id = $_POST['id_cliente'];
            $nome_novo = $_POST['nome_responsavel'];
            $email_novo = $_POST['email_contato'];
            $telefone_novo = $_POST['telefone'];

            // 1. AUDITORIA: Busca dados antigos
            $stmt_old = $pdo->prepare("SELECT nome_responsavel, email_contato, telefone FROM clientes WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

            // 2. AUDITORIA: Compara e monta a string de detalhes
            $detalhes_log = [];
            if ($old_data['nome_responsavel'] !== $nome_novo) {
                $detalhes_log[] = "Nome: {$old_data['nome_responsavel']} -> {$nome_novo}";
            }
            if ($old_data['email_contato'] !== $email_novo) {
                $detalhes_log[] = "Email: {$old_data['email_contato']} -> {$email_novo}";
            }
            if (($old_data['telefone'] ?? '') !== ($telefone_novo ?? '')) {
                 $detalhes_log[] = "Telefone: " . ($old_data['telefone'] ?? 'N/D') . " -> " . ($telefone_novo ?? 'N/D');
            }
            
            // Se nada mudou, cancela o log e a atualização
            if (empty($detalhes_log)) {
                 $_SESSION['success_message'] = "Cliente não alterado (nenhuma mudança detectada).";
                 break;
            }
            
            $log_details_string = "Campos alterados: " . implode('; ', $detalhes_log);


            // 3. Atualiza o banco de dados
            $sql = "UPDATE clientes SET nome_responsavel = ?, email_contato = ?, telefone = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nome_novo, $email_novo, $telefone_novo, $id])) {
                $_SESSION['success_message'] = "Cliente atualizado com sucesso!";
                logAction("Edição Cliente", "clientes", $id, $log_details_string);
            } else {
                $_SESSION['error_message'] = "Erro ao atualizar cliente.";
            }
         }
        break;

    case 'deletar_cliente':
        if (isAdmin()) {
            $id = $_POST['id_cliente'];
            $stmt_name = $pdo->prepare("SELECT nome_responsavel FROM clientes WHERE id = ?");
            $stmt_name->execute([$id]);
            $cliente_nome = $stmt_name->fetchColumn() ?? "ID $id";
            
            $sql = "DELETE FROM clientes WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = "Cliente ($cliente_nome) e todos os seus dados (empresas, lançamentos) foram excluídos com sucesso!";
                logAction("Exclusão Cliente", "clientes", $id, "Cliente excluído: $cliente_nome");
            } else {
                 $_SESSION['error_message'] = "Erro ao excluir cliente.";
            }
        }
        break;

    // --- AÇÕES DE CADASTRO (USUÁRIO) ---
    
    case 'cadastrar_usuario':
        if (isAdmin()) {
            $nome = $_POST['nome'];
            $email = $_POST['email'];
            $telefone = $_POST['telefone'] ?? null;
            $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT); 
            $tipo = $_POST['tipo_usuario'];
            $id_cliente_associado = ($tipo == 'cliente' && !empty($_POST['id_cliente_associado'])) ? $_POST['id_cliente_associado'] : null;
            $acesso_lancamentos = ($tipo == 'cliente' && isset($_POST['acesso_lancamentos'])) ? 1 : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            $sql = "INSERT INTO usuarios (nome, email, telefone, senha, tipo, id_cliente_associado, acesso_lancamentos, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$nome, $email, $telefone, $senha, $tipo, $id_cliente_associado, $acesso_lancamentos, $ativo])) {
                $id_novo_usuario = $pdo->lastInsertId();
                $_SESSION['success_message'] = "Usuário cadastrado com sucesso!";
                logAction("Cadastro Usuário", "usuarios", $id_novo_usuario, "Email: $email, Tipo: $tipo");
                
                if ($tipo == 'contador' && !empty($_POST['id_clientes_associados'])) {
                    $ids_clientes_assoc = $_POST['id_clientes_associados'];
                    $sql_assoc = "INSERT INTO contador_clientes_assoc (id_usuario_contador, id_cliente) VALUES (?, ?)";
                    $stmt_assoc = $pdo->prepare($sql_assoc);
                    foreach ($ids_clientes_assoc as $id_cliente) {
                        $stmt_assoc->execute([$id_novo_usuario, $id_cliente]);
                    }
                }
            } else {
                 $_SESSION['error_message'] = "Erro ao cadastrar usuário.";
            }
        }
        break;
    
    case 'editar_usuario':
        if (isAdmin()) {
            $id_usuario_edit = $_POST['id_usuario'];
            $nome = $_POST['nome'];
            $email = $_POST['email'];
            $telefone = $_POST['telefone'] ?? null;
            $tipo = $_POST['tipo_usuario'];
            $id_cliente_associado = ($tipo == 'cliente' && !empty($_POST['id_cliente_associado'])) ? $_POST['id_cliente_associado'] : null;
            $acesso_lancamentos = ($tipo == 'cliente' && isset($_POST['acesso_lancamentos'])) ? 1 : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            $stmt_old = $pdo->prepare("SELECT nome, email, telefone, tipo, id_cliente_associado, acesso_lancamentos FROM usuarios WHERE id = ?");
            $stmt_old->execute([$id_usuario_edit]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

            // Segurança: não permitir que um usuário desative a si próprio via formulário (evita lockout)
            if ($id_usuario_edit == $user_id && $ativo == 0) {
                $ativo = 1;
                $_SESSION['error_message'] = "Ação não permitida: você não pode desativar seu próprio usuário. Mantendo ativo.";
            }


            $sql_senha_part = "";
            $params_sql = [$nome, $email, $telefone, $tipo, $id_cliente_associado, $acesso_lancamentos, $ativo];
            
            if (!empty($_POST['nova_senha'])) {
                $novo_hash = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
                $sql_senha_part = ", senha = ?";
                $params_sql[] = $novo_hash;
            }
            
            $params_sql[] = $id_usuario_edit; 

            $sql = "UPDATE usuarios SET nome = ?, email = ?, telefone = ?, tipo = ?, id_cliente_associado = ?, acesso_lancamentos = ?, ativo = ? $sql_senha_part WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($params_sql)) {
                 $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
                 logAction("Edição Usuário", "usuarios", $id_usuario_edit, "Email: $email, Tipo: $tipo"); 

                $stmt_delete_assoc = $pdo->prepare("DELETE FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
                $stmt_delete_assoc->execute([$id_usuario_edit]);

                if ($tipo == 'contador' && !empty($_POST['id_clientes_associados'])) {
                    $ids_clientes_assoc = $_POST['id_clientes_associados'];
                    $sql_assoc = "INSERT INTO contador_clientes_assoc (id_usuario_contador, id_cliente) VALUES (?, ?)";
                    $stmt_assoc = $pdo->prepare($sql_assoc);
                    foreach ($ids_clientes_assoc as $id_cliente) {
                        $stmt_assoc->execute([$id_usuario_edit, $id_cliente]);
                    }
                }
            } else {
                $_SESSION['error_message'] = "Erro ao atualizar usuário.";
            }
        }
        break;

    case 'deletar_usuario':
        if (isAdmin()) {
            $id = $_POST['id_usuario'];
            if ($id == $user_id) {
                $_SESSION['error_message'] = "Você não pode excluir seu próprio usuário.";
                break; 
            }
            
            $sql = "DELETE FROM usuarios WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                 $_SESSION['success_message'] = "Usuário excluído com sucesso!";
                 logAction("Exclusão Usuário", "usuarios", $id);
            } else {
                 $_SESSION['error_message'] = "Erro ao excluir usuário.";
            }
        }
        break;

    // --- AÇÕES DE PERFIL (MEU PERFIL) ---
    
    case 'atualizar_perfil':
        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $telefone = $_POST['telefone'];
        
        $sql = "UPDATE usuarios SET nome = ?, email = ?, telefone = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$nome, $email, $telefone, $user_id])) {
            logAction("Atualização de Perfil", "usuarios", $user_id);
            $_SESSION['user_nome'] = $nome; 
            
            // Se o usuário for cliente, atualiza também a permissão de lançamentos na sessão
            if (isClient()) {
                $stmt_acesso = $pdo->prepare("SELECT acesso_lancamentos FROM usuarios WHERE id = ?");
                $stmt_acesso->execute([$user_id]);
                $_SESSION['user_acesso_lancamentos'] = $stmt_acesso->fetchColumn();
            }

            $_SESSION['success_message'] = "Perfil atualizado com sucesso!";
        } else {
             $_SESSION['error_message'] = "Erro ao atualizar perfil.";
        }
        break;

    case 'alterar_senha':
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        if ($nova_senha !== $confirmar_senha) {
            $_SESSION['error_message'] = "A nova senha e a confirmação não coincidem.";
            break;
        }

        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash_atual = $stmt->fetchColumn();

        if (password_verify($senha_atual, $hash_atual)) {
            $novo_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $sql_update = "UPDATE usuarios SET senha = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$novo_hash, $user_id]);
            
            logAction("Alteração de Senha", "usuarios", $user_id);
            $_SESSION['success_message'] = "Senha alterada com sucesso!";
        } else {
            $_SESSION['error_message'] = "A senha atual está incorreta.";
        }
        break;
        

    case 'criar_cobranca':
        if (isAdmin() || isContador()) {
            $id_empresa = $_POST['id_empresa'];
            if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
            $data_competencia = $_POST['data_competencia'];
            $data_vencimento = $_POST['data_vencimento'];
            $valor = $_POST['valor'];
            $id_forma_pagamento = $_POST['id_forma_pagamento'];
                $id_tipo_cobranca = $_POST['id_tipo_cobranca'] ?? null;
                $id_empresa = $_POST['id_empresa'] ?? null;
                if (empty($id_empresa)) {
                    $id_empresa = current_company_id();
                }
                if (empty($id_empresa)) {
                    $_SESSION['error_message'] = 'Selecione a empresa para criar a cobrança.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }
                if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $id_empresa)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para criar cobranças nessa empresa.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }
            $descricao = $_POST['descricao'];
            $contexto_pagamento = $_POST['contexto_pagamento'] ?? null;

            try {
        // use dynamic company column name
        $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
        $sql = "INSERT INTO cobrancas ({$company_col}, data_competencia, data_vencimento, valor, id_forma_pagamento, id_tipo_cobranca, descricao, contexto_pagamento, status_pagamento) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendente')";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$id_empresa, $data_competencia, $data_vencimento, $valor, $id_forma_pagamento, $id_tipo_cobranca, $descricao, $contexto_pagamento])) {
                    $_SESSION['success_message'] = "Cobrança gerada com sucesso!";
                    logAction("Gerou Cobrança", "cobrancas", $pdo->lastInsertId(), "Valor: R$ $valor para empresa ID: $id_empresa");
                } else {
                    $_SESSION['error_message'] = "Erro ao gerar cobrança.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
        }
        break;

    case 'excluir_cobranca':
        if (isAdmin() || isContador()) {
            $id = intval($_GET['id'] ?? 0);
            if ($id) {
                try {
                    $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                    $stmtC = $pdo->prepare("SELECT `" . $company_col . "` FROM cobrancas WHERE id = ?");
                    $stmtC->execute([$id]);
                    $empresa_ref = $stmtC->fetchColumn();
                    if ($empresa_ref === false) {
                        $_SESSION['error_message'] = 'Cobrança não encontrada.';
                        header('Location: ' . base_url('index.php?page=cobrancas'));
                        exit;
                    }
                    if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                        $_SESSION['error_message'] = 'Você não tem permissão para excluir esta cobrança.';
                        header('Location: ' . base_url('index.php?page=cobrancas'));
                        exit;
                    }
                    $sql = "DELETE FROM cobrancas WHERE id = ? AND `" . $company_col . "` = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$id, $empresa_ref])) {
                        $_SESSION['success_message'] = "Cobrança excluída com sucesso!";
                        logAction("Excluiu Cobrança", "cobrancas", $id);
                    } else {
                        $_SESSION['error_message'] = "Erro ao excluir cobrança.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'editar_cobranca':
        if (isAdmin() || isContador()) {
            $id = $_POST['id_cobranca'];
            $id_empresa = $_POST['id_empresa'];
            if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
            $data_competencia = $_POST['data_competencia'];
            $data_vencimento = $_POST['data_vencimento'];
            $valor = $_POST['valor'];
            $id_forma_pagamento = $_POST['id_forma_pagamento'];
            $id_tipo_cobranca = $_POST['id_tipo_cobranca'] ?? null;
            $descricao = $_POST['descricao'];
            $contexto_pagamento = $_POST['contexto_pagamento'] ?? null;

            // If id_empresa not provided, fallback to current company
            if (empty($id_empresa)) $id_empresa = current_company_id();
            if (empty($id_empresa)) {
                $_SESSION['error_message'] = 'Selecione a empresa para a cobrança.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }
            // Permission: non-admin must be associated to the selected company
            if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $id_empresa)) {
                $_SESSION['error_message'] = 'Você não tem permissão para editar cobranças nesta empresa.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            try {
                $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                $sql = "UPDATE cobrancas SET 
                            {$company_col} = ?, 
                            data_competencia = ?, 
                            data_vencimento = ?, 
                            valor = ?, 
                            id_forma_pagamento = ?, 
                            id_tipo_cobranca = ?, 
                            descricao = ?, 
                            contexto_pagamento = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id_empresa, $data_competencia, $data_vencimento, $valor, $id_forma_pagamento, $id_tipo_cobranca, $descricao, $contexto_pagamento, $id])) {
                    $_SESSION['success_message'] = "Cobrança atualizada com sucesso!";
                    logAction("Editou Cobrança", "cobrancas", $id, "Valor: R$ $valor, Descrição: $descricao");
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $_SESSION['error_message'] = "Erro ao atualizar cobrança: " . ($errorInfo[2] ?? "Erro desconhecido.");
                    error_log("Erro ao atualizar cobrança (ID: $id): " . ($errorInfo[2] ?? "Erro desconhecido."));
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
            $pagina_redirecionar = base_url('index.php?page=cobrancas') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    case 'marcar_pago_cobranca':
        if (isAdmin() || isContador()) {
            $id = $_POST['id_cobranca'] ?? null;
            $data_pagamento = $_POST['data_pagamento'] ?? null;

            if (!$id || !$data_pagamento) {
                $_SESSION['error_message'] = "Erro: ID da cobrança ou data de pagamento não fornecidos.";
                break;
            }

            try {
                // 1. Obter a data de vencimento da cobrança e a empresa associada
                $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                $stmt_vencimento = $pdo->prepare("SELECT data_vencimento, `" . $company_col . "` AS empresa_ref FROM cobrancas WHERE id = ?");
                $stmt_vencimento->execute([$id]);
                $cobranca = $stmt_vencimento->fetch(PDO::FETCH_ASSOC);

                if (!$cobranca) {
                    $_SESSION['error_message'] = "Erro: Cobrança não encontrada.";
                    break;
                }

                // Permission check
                if (!isAdmin()) {
                    $empresa_ref = $cobranca['empresa_ref'] ?? null;
                    if ($empresa_ref === null) {
                        $_SESSION['error_message'] = 'Empresa vinculada à cobrança não encontrada.';
                        break;
                    }
                    if (!user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                        $_SESSION['error_message'] = 'Você não tem permissão para dar baixa nesta cobrança.';
                        break;
                    }
                }

                $data_vencimento = new DateTime($cobranca['data_vencimento']);
                $data_pagamento_obj = new DateTime($data_pagamento);

                $status_pagamento = 'Pago';

                // 2. Atualizar a cobrança com a data de pagamento e o status determinado
                $sql = "UPDATE cobrancas SET status_pagamento = ?, data_pagamento = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$status_pagamento, $data_pagamento, $id])) {
                    $_SESSION['success_message'] = "Cobrança marcada como $status_pagamento em " . date('d/m/Y', strtotime($data_pagamento)) . "!";
                    logAction("Baixa Cobrança", "cobrancas", $id, "Status: $status_pagamento, Data Pagamento: $data_pagamento");
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $_SESSION['error_message'] = "Erro ao dar baixa na cobrança: " . ($errorInfo[2] ?? "Erro desconhecido.");
                    error_log("Erro ao dar baixa na cobrança (ID: $id): " . ($errorInfo[2] ?? "Erro desconhecido."));
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
            $pagina_redirecionar = base_url('index.php?page=cobrancas') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    case 'reverter_pago_cobranca':
        if (isAdmin() || isContador()) {
            $id = $_POST['id_cobranca'] ?? null;
            if ($id) {
                // Check company permission before reverting
                $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                $stmtC = $pdo->prepare("SELECT `" . $company_col . "` FROM cobrancas WHERE id = ?");
                $stmtC->execute([$id]);
                $empresa_ref = $stmtC->fetchColumn();
                if ($empresa_ref === false) {
                    $_SESSION['error_message'] = 'Cobrança não encontrada.';
                    break;
                }
                if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para reverter a baixa desta cobrança.';
                    break;
                }
                $sql = "UPDATE cobrancas SET status_pagamento = 'Pendente', data_pagamento = NULL WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id])) {
                    $_SESSION['success_message'] = "Pagamento da cobrança revertido para Pendente!";
                    logAction("Reverteu Baixa Cobrança", "cobrancas", $id);
                } else {
                    $_SESSION['error_message'] = "Erro ao reverter a baixa.";
                }
            }
            $pagina_redirecionar = base_url('index.php?page=cobrancas') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    // --- AÇÕES DE FORMAS DE PAGAMENTO (Admin) ---

    case 'criar_forma_pagamento':
        if (isAdmin()) {
            $nome = $_POST['nome'];
            $icone = $_POST['icone_bootstrap'] ?? null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            try {
                $sql = "INSERT INTO formas_pagamento (nome, icone_bootstrap, ativo) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nome, $icone, $ativo])) {
                    $_SESSION['success_message'] = "Forma de pagamento criada com sucesso!";
                    logAction("Criou Forma de Pagamento", "formas_pagamento", $pdo->lastInsertId(), "Nome: $nome");
                } else {
                    $_SESSION['error_message'] = "Erro ao criar forma de pagamento.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error_message'] = "Erro: Já existe uma forma de pagamento com este nome.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'editar_forma_pagamento':
        if (isAdmin()) {
            $id = $_POST['id'];
            $nome = $_POST['nome'];
            $icone = $_POST['icone_bootstrap'] ?? null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            try {
                $sql = "UPDATE formas_pagamento SET nome = ?, icone_bootstrap = ?, ativo = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nome, $icone, $ativo, $id])) {
                    $_SESSION['success_message'] = "Forma de pagamento atualizada com sucesso!";
                    logAction("Editou Forma de Pagamento", "formas_pagamento", $id, "Nome: $nome");
                } else {
                    $_SESSION['error_message'] = "Erro ao atualizar forma de pagamento.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error_message'] = "Erro: Já existe uma forma de pagamento com este nome.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'excluir_forma_pagamento':
        if (isAdmin()) {
            $id = $_GET['id'];
            try {
                $sql = "DELETE FROM formas_pagamento WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id])) {
                    $_SESSION['success_message'] = "Forma de pagamento excluída com sucesso!";
                    logAction("Excluiu Forma de Pagamento", "formas_pagamento", $id);
                } else {
                    $_SESSION['error_message'] = "Erro ao excluir forma de pagamento.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1451) {
                    $_SESSION['error_message'] = "Erro: Esta forma de pagamento não pode ser excluída pois está sendo utilizada em uma ou mais cobranças.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    // --- AÇÕES DE TIPOS DE COBRANÇA (Admin) ---

    case 'criar_tipo_cobranca':
        if (isAdmin()) {
            $nome = $_POST['nome'];
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            try {
                $sql = "INSERT INTO tipos_cobranca (nome, ativo) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nome, $ativo])) {
                    $_SESSION['success_message'] = "Tipo de cobrança criado com sucesso!";
                    logAction("Criou Tipo de Cobrança", "tipos_cobranca", $pdo->lastInsertId(), "Nome: $nome");
                } else {
                    $_SESSION['error_message'] = "Erro ao criar tipo de cobrança.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error_message'] = "Erro: Já existe um tipo de cobrança com este nome.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'editar_tipo_cobranca':
        if (isAdmin()) {
            $id = $_POST['id'];
            $nome = $_POST['nome'];
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            try {
                $sql = "UPDATE tipos_cobranca SET nome = ?, ativo = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nome, $ativo, $id])) {
                    $_SESSION['success_message'] = "Tipo de cobrança atualizado com sucesso!";
                    logAction("Editou Tipo de Cobrança", "tipos_cobranca", $id, "Nome: $nome");
                } else {
                    $_SESSION['error_message'] = "Erro ao atualizar tipo de cobrança.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error_message'] = "Erro: Já existe um tipo de cobrança com este nome.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'excluir_tipo_cobranca':
        if (isAdmin()) {
            $id = $_GET['id'];
            try {
                $sql = "DELETE FROM tipos_cobranca WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id])) {
                    $_SESSION['success_message'] = "Tipo de cobrança excluído com sucesso!";
                    logAction("Excluiu Tipo de Cobrança", "tipos_cobranca", $id);
                } else {
                    $_SESSION['error_message'] = "Erro ao excluir tipo de cobrança.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1451) { // Foreign key constraint
                    $_SESSION['error_message'] = "Erro: Este tipo de cobrança não pode ser excluído pois está sendo utilizado em uma ou mais cobranças.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    default:
        // Caso a action não seja reconhecida
        if ($action) {
             $_SESSION['error_message'] = "Ação '$action' desconhecida.";
        }
        break;
}

// Redireciona de volta para a página anterior
$pagina_anterior = $_SERVER['HTTP_REFERER'] ?? base_url('index.php?page=dashboard');
header("Location: $pagina_anterior");
exit;