<?php
// config/functions.php
session_start();
require_once 'db.php'; // Assumindo que este arquivo contém $pdo global
// Tentativa de carregar autoload do Composer (PHPMailer)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}
define('BASE_URL', 'http://localhost/DMC-Finanças/');
//define('BASE_URL', 'https://jpconsultoriacontabil.dynamicmotioncentury.com.br/');

// Função para gerar URLs absolutas
function base_url($path = '') {
    return BASE_URL . $path;
}

// Verifica se o usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redireciona se não estiver logado
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . base_url('index.php?page=login'));
        exit();
    }
}

// Funções de verificação de tipo (Role)
function isAdmin() {
    return (isLoggedIn() && isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] == 'admin');
}

function isContador() {
    return (isLoggedIn() && isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] == 'contador');
}

function isClient() {
    return (isLoggedIn() && isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] == 'cliente');
}

// Função para verificar se o usuário tem acesso à tela de lançamentos
function hasLancamentosAccess() {
    global $pdo;
    if (isAdmin() || isContador()) {
        return true;
    }
    if (isClient() && isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT acesso_lancamentos FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return (bool) $stmt->fetchColumn();
    }
    return false;
}

// Função de Log
function logAction($acao, $tabela = null, $id_registro = null, $detalhes = null) {
    global $pdo;
    
    $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $sql = "INSERT INTO logs (id_usuario, acao, tabela_afetada, id_registro_afetado, detalhes) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_usuario, $acao, $tabela, $id_registro, $detalhes]);
}

/**
 * Busca as configurações de SMTP do banco de dados.
 * @return array Configurações de SMTP.
 */
function getSmtpSettings() {
    global $pdo;
    
    $default_settings = [
        'smtp_host' => 'smtp.exemplo.com',
        'smtp_port' => '587',
        'smtp_username' => '', 
        'smtp_password' => '', // Nunca exibe a senha real do DB
        'smtp_secure' => 'tls',
        'email_from' => 'nao-responda@seuapp.com'
    ];

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return array_merge($default_settings, $settings_db);

    } catch (PDOException $e) {
        // Se a tabela system_settings não existir ou houver outro erro, retorna os padrões.
        error_log("Erro ao buscar configurações SMTP: " . $e->getMessage());
        return $default_settings;
    }
}

/**
 * Retorna política de upload de documentos (mime whitelist e tamanho máximo em bytes).
 * Pode ser adaptada para ler do DB (system_settings) se necessário.
 */
function getDocumentUploadPolicy() {
    // Valores padrão
    $policy = [
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ],
        'max_size_bytes' => 10 * 1024 * 1024 // 10 MB
    ];

    // Tentar carregar ajustes do DB se existir tabela system_settings
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('documents_max_size_bytes','documents_allowed_mimes')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($rows['documents_max_size_bytes'])) {
            $val = intval($rows['documents_max_size_bytes']);
            if ($val > 1024) $policy['max_size_bytes'] = $val;
        }
        if (!empty($rows['documents_allowed_mimes'])) {
            $m = array_map('trim', explode(',', $rows['documents_allowed_mimes']));
            if (!empty($m)) $policy['allowed_mimes'] = $m;
        }
    } catch (Exception $e) {
        // ignore and use defaults
    }

    return $policy;
}

/**
 * Verifica se um usuário está associado a uma pasta (tabela pivot) ou é o owner antigo.
 * Retorna true se associado (ou se o usuário for admin).
 */
function isUserAssociatedToPasta($user_id, $pasta_id) {
    if (isAdmin()) return true;
    global $pdo;
    try {
        // verifica existência da tabela pivot
        $stmt = $pdo->prepare("SELECT 1 FROM documentos_pastas_usuarios LIMIT 1");
        $stmt->execute();
        // tabela existe, checa associação
        $stmt2 = $pdo->prepare('SELECT COUNT(1) FROM documentos_pastas_usuarios WHERE pasta_id = ? AND user_id = ?');
        $stmt2->execute([$pasta_id, $user_id]);
        return $stmt2->fetchColumn() > 0;
    } catch (Exception $e) {
        // fallback para coluna owner_user_id (compatibilidade com versões anteriores)
        try {
            $stmt = $pdo->prepare('SELECT owner_user_id FROM documentos_pastas WHERE id = ?');
            $stmt->execute([$pasta_id]);
            $owner = $stmt->fetchColumn();
            return ($owner !== false && intval($owner) === intval($user_id));
        } catch (Exception $e2) {
            return false;
        }
    }
}

/**
 * Verifica se um cliente (empresa) possui qualquer usuário associado a uma pasta.
 * Útil para contas do tipo 'cliente' que representam empresas com múltiplos usuários.
 */
function isClientAssociatedToPasta($cliente_id, $pasta_id) {
    if (isAdmin()) return true;
    global $pdo;
    try {
        // verifica pivot via usuários que pertencem ao cliente
        $stmt = $pdo->prepare('SELECT COUNT(1) FROM documentos_pastas_usuarios dpu JOIN usuarios u ON dpu.user_id = u.id WHERE dpu.pasta_id = ? AND u.id_cliente_associado = ?');
        $stmt->execute([$pasta_id, $cliente_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        // fallback: verifica owner_user_id
        try {
            $stmt = $pdo->prepare('SELECT owner_user_id FROM documentos_pastas WHERE id = ?');
            $stmt->execute([$pasta_id]);
            $owner = $stmt->fetchColumn();
            if ($owner === false || $owner === null) return false;
            $stmt2 = $pdo->prepare('SELECT id_cliente_associado FROM usuarios WHERE id = ?');
            $stmt2->execute([$owner]);
            $owner_cliente = $stmt2->fetchColumn();
            return ($owner_cliente !== false && intval($owner_cliente) === intval($cliente_id));
        } catch (Exception $e2) {
            return false;
        }
    }
}

/**
 * Retorna array com usuários associados a uma pasta (id e nome). Se pivot ausente, tenta owner_user_id.
 */
function getUsuariosAssociadosPasta($pasta_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT u.id, u.nome FROM documentos_pastas_usuarios dpu JOIN usuarios u ON dpu.user_id = u.id WHERE dpu.pasta_id = ?');
        $stmt->execute([$pasta_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // fallback
        try {
            $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE id = (SELECT owner_user_id FROM documentos_pastas WHERE id = ?)');
            $stmt->execute([$pasta_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? [$row] : [];
        } catch (Exception $e2) {
            return [];
        }
    }
}

/**
 * Envia um email de notificação sobre um lançamento.
 * @param string $toEmail Email do destinatário (cliente).
 * @param string $toName Nome do destinatário.
 * @param array $lancamento Dados do lançamento (descricao, valor, data_vencimento, tipo).
 * @return bool True se enviado com sucesso, False caso contrário.
 */
function sendNotificationEmail($toEmail, $toName, $lancamento) {
    // IMPORTANTE: Adicione o require da sua biblioteca PHPMailer aqui!
    // Ex: require 'vendor/autoload.php'; 
    
    $settings = getSmtpSettings();

    if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        error_log("Erro de E-mail: Configurações SMTP ausentes ou incompletas.");
        return false;
    }

    $valor = number_format($lancamento['valor'], 2, ',', '.');
    $data_vencimento = date('d/m/Y', strtotime($lancamento['data_vencimento']));
    $tipo = ($lancamento['tipo'] == 'receita') ? 'Receita' : 'Despesa';

    $subject = "Novo Lançamento Financeiro Disponível: R$ $valor";
    $body = "
        <p>Prezado(a) **{$toName}**,</p>
        <p>Informamos que um novo lançamento financeiro foi disponibilizado para sua empresa. Por favor, acesse o portal para visualizar os detalhes e a situação de pagamento.</p>
        <table style='border: 1px solid #ccc; border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>
            <tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0; width: 30%;'>Descrição</td><td style='padding: 8px; border: 1px solid #ccc;'>{$lancamento['descricao']}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0;'>Valor</td><td style='padding: 8px; border: 1px solid #ccc;'>**R$ {$valor}**</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0;'>Vencimento</td><td style='padding: 8px; border: 1px solid #ccc;'>{$data_vencimento}</td></tr>
            <tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0;'>Tipo</td><td style='padding: 8px; border: 1px solid #ccc;'>{$tipo}</td></tr>
        </table>
        <p>Atenciosamente,</p>
        <p>Equipe Financeira</p>
    ";
    
    // --- Lógica de Envio (SUBSTITUIR PELO PHPMailer REAL) ---
    // Simulação de sucesso para não quebrar a aplicação sem o PHPMailer:
    $mail_success = true; 
    
    /*
    // EXEMPLO DE CÓDIGO PHPMailer (DESCOMENTAR E USAR O REAL)
    // Verifica se PHPMailer está disponível
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('PHPMailer não encontrado. Execute "composer install" no diretório do projeto para habilitar envio real de emails.');
        // Retorna false para indicar que envio real não foi efetuado
        return false;
    }

    try {
        $mail = new PHPMailer\\PHPMailer\\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->Port = $settings['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        // Mapear valores amigáveis para o que o PHPMailer espera
        $secure = strtolower(trim($settings['smtp_secure'] ?? ''));
        if ($secure === 'starttls') {
            $secure = 'tls';
        }
        if (!empty($secure)) {
            $mail->SMTPSecure = $secure;
        }
        $mail->setFrom($settings['email_from'], 'Sistema Financeiro');
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(true);

        $mail_success = $mail->send();
    } catch (Exception $e) {
        error_log("Erro no PHPMailer: " . $e->getMessage());
        $mail_success = false;
    }
    */
    
    return $mail_success;
}

/**
 * Envia um e-mail genérico de notificação (documentos)
 */
function sendDocumentNotification($toEmail, $toName, $subject, $bodyHtml) {
    $settings = getSmtpSettings();

    if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        error_log("Erro de E-mail (Documentos): Configurações SMTP ausentes ou incompletas.");
        return false;
    }

    // Simulação de envio (mantemos comportamento seguro se PHPMailer não estiver instalado)
    $mail_success = true;

    /*
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('PHPMailer não encontrado. Execute "composer install" no diretório do projeto para habilitar envio real de emails.');
        return false;
    }

    try {
        $mail = new PHPMailer\\PHPMailer\\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->Port = $settings['smtp_port'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];
        $secure = strtolower(trim($settings['smtp_secure'] ?? ''));
        if ($secure === 'starttls') $secure = 'tls';
        if (!empty($secure)) $mail->SMTPSecure = $secure;
        $mail->setFrom($settings['email_from'], 'Sistema Financeiro');
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->isHTML(true);
        $mail_success = $mail->send();
    } catch (Exception $e) {
        error_log("Erro no PHPMailer (Documentos): " . $e->getMessage());
        $mail_success = false;
    }
    */

    return $mail_success;
}

// A função enviarEmailNotificacao antiga foi removida e substituída por sendNotificationEmail.
?>