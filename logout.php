<?php
// logout.php
require_once 'config/functions.php';

$user_nome = isset($_SESSION['user_nome']) ? $_SESSION['user_nome'] : 'Desconhecido';
logAction("Logout", null, null, "Usuário: $user_nome");

session_destroy();
header("Location: " . base_url('index.php?page=login'));
exit();
?>