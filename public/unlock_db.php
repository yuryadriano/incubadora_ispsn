<?php
// public/unlock_db.php
// Script de emergência para limpar conexões travadas na base de dados (Metadata Locks)

require_once __DIR__ . '/../config/config.php';

// Apenas permitir execução por admins ou se for solicitado explicitamente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$perfil = $_SESSION['usuario_perfil'] ?? '';
$pass = $_GET['pass'] ?? '';

// Segurança: Exigir que seja superadmin ou conheça o parâmetro de segurança
if ($perfil !== 'superadmin' && $pass !== 'unlock123') {
    die("Acesso negado. Para rodar este script, passe ?pass=unlock123 na URL.");
}

echo "<h3>Ferramenta de Desbloqueio da Base de Dados</h3>";

$processosTerminados = 0;
$res = $mysqli->query("SHOW FULL PROCESSLIST");

if ($res) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; font-family: monospace; font-size: 12px;'>";
    echo "<tr><th>ID</th><th>User</th><th>Host</th><th>db</th><th>Command</th><th>Time</th><th>State</th><th>Info</th><th>Ação</th></tr>";
    
    $killList = [];
    
    while ($row = $res->fetch_assoc()) {
        $id = $row['Id'];
        $time = $row['Time'];
        $state = $row['State'];
        $info = $row['Info'];
        $command = $row['Command'];
        
        echo "<tr>";
        echo "<td>{$id}</td>";
        echo "<td>{$row['User']}</td>";
        echo "<td>{$row['Host']}</td>";
        echo "<td>{$row['db']}</td>";
        echo "<td>{$command}</td>";
        echo "<td>{$time}s</td>";
        echo "<td>{$state}</td>";
        echo "<td>" . htmlspecialchars(substr($info ?? '', 0, 100)) . "</td>";
        
        // Critério para matar: queries que estão a correr há mais de 15 segundos
        // Evitar matar o próprio processo
        if ($time > 15 && $command !== 'Daemon' && $id != $mysqli->thread_id) {
            $killList[] = $id;
            echo "<td style='color: red; font-weight: bold;'>KILL AGENDADO</td>";
        } else {
            echo "<td style='color: green;'>Manter</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    if (!empty($killList)) {
        echo "<h4>A terminar processos travados...</h4><ul>";
        foreach ($killList as $pid) {
            if ($mysqli->query("KILL {$pid}")) {
                echo "<li>Processo #{$pid} terminado com sucesso.</li>";
                $processosTerminados++;
            } else {
                echo "<li style='color: red;'>Falha ao terminar processo #{$pid}: " . $mysqli->error . "</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>Nenhum processo travado detetado (tempo > 15s).</p>";
    }
} else {
    echo "<p style='color: red;'>Erro ao ler a lista de processos: " . $mysqli->error . "</p>";
}

echo "<p><strong>Total de processos terminados:</strong> {$processosTerminados}</p>";
echo "<p><a href='?pass=unlock123'>Recarregar</a> | <a href='index.php'>Voltar ao Painel</a></p>";
?>
