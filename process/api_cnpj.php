<?php
// process/api_cnpj.php
header('Content-Type: application/json');

// Medida de segurança básica
if (!isset($_GET['cnpj'])) {
    echo json_encode(['error' => 'CNPJ não fornecido']);
    exit;
}

$cnpj = preg_replace('/\D/', '', $_GET['cnpj']); // Limpa máscara

if (strlen($cnpj) != 14) {
    echo json_encode(['error' => 'CNPJ inválido']);
    exit;
}

// URL da BrasilAPI (gratuita)
$url = "https://brasilapi.com.br/api/cnpj/v1/{$cnpj}";

// Usando cURL para a requisição (mais robusto que file_get_contents)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10s
curl_setopt($ch, CURLOPT_USERAGENT, 'GestaoFinanceiraApp'); // Boa prática
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    // Retorna a resposta da API diretamente
    echo $response;
} else {
    // Retorna um erro
    echo json_encode(['error' => 'Falha ao consultar API', 'status' => $http_code]);
}
?>