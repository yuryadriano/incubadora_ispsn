<?php
require_once __DIR__ . '/../config/config.php';
$res = $mysqli->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
