<?php
require_once __DIR__ . '/../config/functions.php';
global $pdo;
$rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
print_r($rows);
