<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
// includes/header.php
// (functions.php deve ser chamado ANTES do header)

// A variável $page é definida no index.php ANTES deste include
global $page; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Financeira</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css?v=1.9'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+39&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
</head>
<body class="bg-light-subtle">

<?php if (isLoggedIn()): ?>

    <div class="offcanvas offcanvas-start offcanvas-lg sidebar-nav" tabindex="-1" id="sidebarMenu" 
         data-bs-scroll="true" data-bs-backdrop="false" aria-labelledby="sidebarMenuLabel">
        
        <div class="offcanvas-header">
            <a class="navbar-brand fw-bold text-white" href="<?php echo base_url('index.php?page=dashboard'); ?>">
                <i class="bi bi-cash-coin me-2"></i> 
                <span class="sidebar-brand-text">MeuFinanceiro</span>
            </a>
            
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body d-flex flex-column">
            <div class="sidebar-content flex-grow-1">
                <ul class="navbar-nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'dashboard') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=dashboard'); ?>">
                            <i class="bi bi-grid-fill me-3"></i> 
                            <span class="sidebar-link-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'lancamentos') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=lancamentos'); ?>">
                            <i class="bi bi-arrow-down-up me-3"></i> 
                            <span class="sidebar-link-text">Lançamentos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'cobrancas') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=cobrancas'); ?>">
                            <i class="bi bi-receipt-cutoff me-3"></i> 
                            <span class="sidebar-link-text">Cobranças</span>
                        </a>
                    </li>

                    <?php // Menus do Admin e Contador ?>
                    <?php if (isAdmin() || isContador()): ?>
                        <li class="nav-item-divider"></li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page == 'cadastro_clientes') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=cadastro_clientes'); ?>">
                                <i class="bi bi-person-lines-fill me-3"></i> 
                                <span class="sidebar-link-text">Clientes</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page == 'cadastro_empresas') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=cadastro_empresas'); ?>">
                                <i class="bi bi-building me-3"></i> 
                                <span class="sidebar-link-text">Empresas</span>
                            </a>
                        </li>
                        
                    <?php endif; ?>

                    <?php // Menu exclusivo do Admin ?>
                    <?php if (isAdmin()): ?>
                        <li class="nav-item-divider"></li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page == 'cadastro_usuarios') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=cadastro_usuarios'); ?>">
                                <i class="bi bi-people-fill me-3"></i> 
                                <span class="sidebar-link-text">Usuários</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page == 'logs') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=logs'); ?>">
                                <i class="bi bi-file-earmark-text me-3"></i> 
                                <span class="sidebar-link-text">Logs</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page == 'configuracoes_email') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=configuracoes_email'); ?>">
                                <i class="bi bi-file-earmark-text me-3"></i> 
                                <span class="sidebar-link-text">Config. Email</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page == 'gerenciar_formas_pagamento') ? 'active' : ''; ?>" href="<?php echo base_url('index.php?page=gerenciar_formas_pagamento'); ?>">
                                <i class="bi bi-wallet2 me-3"></i> 
                                <span class="sidebar-link-text">Formas de Pagamento</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="sidebar-footer">
                <?php // Bloco da logo removido daqui ?>
                <div id="toggle-container" class="d-grid">
                    <button type="button" class="btn btn-outline-light" id="desktop-sidebar-toggle">
                        <i class="bi bi-list"></i> 
                        <span class="sidebar-link-text">Recolher Menu</span>
                    </button>
                </div>

                <hr class="border-secondary-subtle">

                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white <?php echo ($page == 'meu_perfil') ? 'active' : ''; ?>" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-2"></i> 
                        <span class="sidebar-link-text"><?php echo htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="<?php echo base_url('index.php?page=meu_perfil'); ?>">
                            <i class="bi bi-person-gear me-2"></i> Meu Perfil
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo base_url('logout.php'); ?>">
                            <i class="bi bi-box-arrow-right me-2"></i> Sair
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>


    <header class="navbar navbar-dark bg-primary-dark-grad sticky-top d-lg-none flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6 text-white" href="#">
            <i class="bi bi-cash-coin me-2"></i> MeuFinanceiro
        </a>
        <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </header>

    <main class="main-content">
    
        <div class="flash-message-container">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); // Limpa a mensagem ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                 <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); // Limpa a mensagem ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>