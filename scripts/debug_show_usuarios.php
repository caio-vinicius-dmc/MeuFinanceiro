<?php
require_once __DIR__ . '/../config/functions.php';
global $pdo;
$r = $pdo->query("SHOW CREATE TABLE usuarios")->fetch(PDO::FETCH_ASSOC);
echo $r['Create Table'] ?? print_r($r, true);
