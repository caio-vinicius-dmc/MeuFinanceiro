<?php
// pages/refresh_permissions.php
require_once 'config/functions.php';
requireLogin();

// Apenas usuarios logados podem executar. Se helper existir, força reload.
if (function_exists('rbac_maybe_refresh_permissions')) {
    rbac_maybe_refresh_permissions($_SESSION['user_id']);
    $_SESSION['success_message'] = 'Permissões atualizadas (verificação de versão).';
} else {
    $_SESSION['error_message'] = 'RBAC não está configurado no momento.';
}

header('Location: ' . base_url('index.php?page=dashboard'));
exit;

?>
