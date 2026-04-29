<?php
require_once __DIR__ . '/../config/config.php';

// 1. Alterar a coluna estado para incluir os novos valores
$sqlAlter = "ALTER TABLE projetos MODIFY COLUMN estado ENUM(
    'submetido', 
    'em_avaliacao', 
    'aprovado', 
    'rejeitado', 
    'incubado', 
    'fundo_investimento', 
    'concluido'
) DEFAULT 'submetido'";

if ($mysqli->query($sqlAlter)) {
    echo "Coluna 'estado' atualizada com sucesso.\n";
} else {
    // Se falhar por causa de valores existentes incompatíveis, vamos converter primeiro os valores
    echo "Erro ao alterar coluna diretamente. Tentando migração de dados...\n";
    
    // Vou usar VARCHAR temporário para migrar dados com segurança
    $mysqli->query("ALTER TABLE projetos MODIFY COLUMN estado VARCHAR(50)");
    
    $mappings = [
        'em_analise'   => 'submetido',
        'presencial'   => 'em_avaliacao',
        'em_andamento' => 'incubado',
        'financiar'    => 'fundo_investimento',
        'cancelado'    => 'rejeitado'
    ];
    
    foreach ($mappings as $old => $new) {
        $mysqli->query("UPDATE projetos SET estado = '$new' WHERE estado = '$old'");
    }
    
    // Agora tenta aplicar o ENUM final
    if ($mysqli->query($sqlAlter)) {
        echo "Migração e alteração de ENUM concluídas com sucesso.\n";
    } else {
        echo "Erro final: " . $mysqli->error . "\n";
    }
}
