<?php
// tools/test_documentos_flow.php
// Script rápido para validar presença das tabelas e permissões básicas.
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $tables = ['documentos_pastas', 'documentos_arquivos'];
    foreach ($tables as $t) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$t]);
        $exists = $stmt->fetchColumn();
        echo $t . ': ' . ($exists ? 'EXISTS' : 'MISSING') . PHP_EOL;
    }

    $uploadDir = __DIR__ . '/../uploads/documentos';
    echo 'Upload dir: ' . $uploadDir . PHP_EOL;
    echo 'Exists: ' . (is_dir($uploadDir) ? 'YES' : 'NO') . PHP_EOL;
    if (is_dir($uploadDir)) {
        echo 'Writable: ' . (is_writable($uploadDir) ? 'YES' : 'NO') . PHP_EOL;
    }

    echo '\nManual steps to test:\n';
    echo '1) Login como admin e criar pasta em Gerenciar Documentos.\n';
    echo '2) Associar a um usuário.\n';
    echo '3) Login como usuário associado e subir um arquivo (PDF).\n';
    echo '4) Como admin, aprovar o arquivo em Gerenciar Documentos.\n';
    echo '5) Conferir se o usuário recebeu email (se SMTP configurado) e se o arquivo abre via serve_documento.php.\n';

} catch (Exception $e) {
    echo 'Erro: ' . $e->getMessage();
}
