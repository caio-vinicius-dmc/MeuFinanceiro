<?php
// config/db.php

$host = '127.0.0.1'; // ou 'localhost'
$db   = 'gestao_financeira';
$user = 'root'; // Seu usuário do MySQL
$pass = '';     // Sua senha do MySQL
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Registrar mensagem completa no log de erros do PHP para diagnóstico
    error_log("PDO connection error: " . $e->getMessage());

    // Mensagem amigável para o navegador (ambiente local)
    // Não exibir detalhes sensíveis em produção.
    http_response_code(500);
    echo '<h2>Erro ao conectar ao banco de dados</h2>';
    echo '<p>O sistema não conseguiu estabelecer conexão com o MySQL.</p>';
    echo '<ul>';
    echo '<li>Verifique se o MySQL está rodando (abra o XAMPP Control Panel e inicie <strong>MySQL</strong>).</li>';
    echo '<li>Confira as credenciais em <code>config/db.php</code> (host, porta, usuário e senha).</li>';
    echo '<li>Se estiver usando Windows, verifique o firewall/antivírus que possa bloquear a porta 3306.</li>';
    echo '<li>Se você usa socket ou pipe especial, ajuste <code>$host</code> e <code>$dsn</code> conforme necessário.</li>';
    echo '</ul>';
    echo '<p>Detalhes do erro foram registrados no log do PHP para análise.</p>';
    // Finaliza a execução para evitar exceção não tratada
    exit;
}
?>