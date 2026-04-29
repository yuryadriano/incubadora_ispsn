<?php
require_once __DIR__ . '/../config/config.php';
$res = $mysqli->query("SHOW COLUMNS FROM projetos LIKE 'estado'");
$row = $res->fetch_assoc();
echo $row['Type'];
