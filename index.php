<?php
// index.php
require_once 'config/functions.php';

// Definir a página padrão
$page = 'login'; // $page é definida aqui primeiro

// Se estiver logado, a página padrão é o dashboard
if (isLoggedIn()) {
    $page = 'dashboard'; // $page é redefinida aqui
}

// Verifica se uma página foi solicitada na URL
if (isset($_GET['page'])) {
    $page = $_GET['page']; // $page é redefinida aqui
}

// Lista de páginas permitidas (Whitelist de segurança)
$allowed_pages = [
    'login', 
    'dashboard', 
    'lancamentos', 
    'cadastro_usuarios', 
    'cadastro_clientes', 
    'cadastro_empresas',
    'logs',
    'meu_perfil',
    'configuracoes_email',
    'gerenciar_formas_pagamento',
    'cobrancas',
    'gerenciar_tipos_cobranca',
    'gerenciar_categorias',
    'documentos',
    'gerenciar_documentos',
    'configuracoes_documentos',
    'associacoes_contador',
    // Adiciona páginas de administração RBAC (criadas recentemente)
    'gerenciar_papeis',
    'gerenciar_permissoes',
    'editar_papel'
];

// Se a página não for de login E não estiver logado, força o login
if ($page != 'login' && !isLoggedIn()) {
    header("Location: " . base_url('index.php?page=login'));
    exit();
}

// Se a página não estiver na lista, vai para o dashboard (ou login)
if (!in_array($page, $allowed_pages)) {
    $page = isLoggedIn() ? 'dashboard' : 'login';
}

// Controle de Acesso por Role (Regras de negócio)
if (isContador()) {
    // Contador não pode ver cadastro de usuários ou logs
    if ($page == 'cadastro_usuarios' || $page == 'logs' || $page == 'configuracoes_email') { // Adicionado 'configuracoes_email' aqui por segurança
        $page = 'dashboard'; // Redireciona
    }
} 
elseif (isClient()) {
    // Cliente só pode ver dashboard e lançamentos
    $allowed_client_pages = ['dashboard', 'lancamentos', 'meu_perfil','cobrancas','documentos']; // <-- Adicionado perfil
    if (!in_array($page, $allowed_client_pages)) {
        $page = 'dashboard'; // Redireciona
    }
}


// Carrega o Cabeçalho (só não carrega na tela de login)
if ($page != 'login') {
    // O header agora pode ler a variável $page
    include 'includes/header.php';
}

// Carrega a Página
$page_path = "pages/{$page}.php";
if (file_exists($page_path)) {
    include $page_path;
} else {
    // Fallback caso o arquivo não exista
    echo "<div class='alert alert-danger'>Erro: A página solicitada ($page) não foi encontrada.</div>";
}

// Carrega o Rodapé (só não carrega na tela de login)
if ($page != 'login') {
    include 'includes/footer.php';
}

?>