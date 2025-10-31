<?php
// pages/login.php

// Inicia o buffer de saída (necessário para o header() funcionar em todos os ambientes)
ob_start();

// Supondo que functions.php inclui a conexão e a função isLoggedIn()
require_once 'config/functions.php';

// --- DEFINIÇÃO DO CAMINHO ABSOLUTO PARA AÇÃO DO FORMULÁRIO E REDIRECIONAMENTO ---
$form_action_url = base_url('process/auth.php');
$redirect_success_url = base_url('index.php?page=dashboard');
// -----------------------------------------------------------


// Se já estiver logado, redireciona para o dashboard
if (isLoggedIn()) {
    header("Location: " . $redirect_success_url);
    exit();
}

// Nota: A lógica de POST (handleLogin) foi movida para process/auth.php

// Exibe a mensagem de erro da sessão (se houver)
$error_message = $_SESSION['error_message'] ?? null;
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']); // Limpa a mensagem após exibir
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MeuFinanceiro</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <link rel="stylesheet" href="<?php echo base_url('assets/css/style.css?v=2.0'); ?>"> </head>
<body class="login-wrapper">

    <div class="container-fluid vh-100 p-0 login-wrapper">
        <div class="row g-0 h-100">
        
            <div class="col-lg-7 d-none d-lg-block login-image-side">
                <div class="login-branding-overlay">
                    <img src="<?php echo base_url('assets/img/logo.png'); ?>" alt="Logo MeuFinanceiro" style="max-width: 200px; filter: drop-shadow(0 0 10px rgba(0,0,0,0.5));">
                    <h1 class="text-white mt-3 display-4 fw-bold">MeuFinanceiro</h1>
                    <p class="text-white-50 lead">Sua gestão financeira, simples e moderna.</p>
                </div>
            </div>

            <div class="col-lg-5 col-md-12 login-form-side">
                
                <div class="login-card p-4 p-md-5">
                    <div class="w-100">
                        
                        <div class="text-center mb-4 d-lg-none"> 
                            <img src="<?php echo base_url('assets/img/logo.png'); ?>" alt="Logo Mobile" id="mobile-login-logo" style="max-width: 150px; margin: 0 auto 1.5rem;">
                            <h3 class="fw-bold mt-2 text-primary">MeuFinanceiro</h3>
                        </div>
                        
                        <h2 class="fw-bold mb-3">Login</h2>
                        <p class="text-muted mb-4">Acesse sua conta para continuar.</p>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <form action="<?php echo $form_action_url; ?>" method="POST">
                            <input type="hidden" name="action" value="login">
                            
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-envelope-fill text-muted"></i>
                                    </span>
                                    <input type="email" class="form-control bg-light border-start-0" id="email" name="email" placeholder="seu@email.com" required>
                                </div>
                            </div>
                            
                            <div class="form-group mb-4">
                                <label for="senha" class="form-label">Senha</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-lock-fill text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control bg-light border-start-0" id="senha" name="senha" placeholder="Sua senha" required>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mb-4">
                                <a href="#" class="text-decoration-none text-primary fw-medium">Esqueceu a senha?</a>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg fw-bold">Entrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>