<?php
require 'config/config.php';
$res = $mysqli->query("SHOW TABLES LIKE 'config_website'");
if($res && $res->num_rows > 0) {
    echo "Tabela OK\n";
    $res2 = $mysqli->query("SELECT * FROM config_website");
    if($res2) {
        echo "Dados OK: " . $res2->num_rows . " linhas\n";
    } else {
        echo "Erro ao ler dados\n";
    }
} else {
    echo "Tabela em falta ou erro na BD\n";
}
