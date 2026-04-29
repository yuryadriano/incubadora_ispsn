<?php
require_once __DIR__ . '/../../config/auth.php';
obrigarLogin();

$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/admin/gestao_espacos.php';

// --- GESTÃO DE VISITANTES ---
if ($action === 'registar_visitante') {
    $nome = trim($_POST['nome']);
    $documento = trim($_POST['documento']);
    $motivo = trim($_POST['motivo']);
    $idVisitado = (int)$_POST['id_visitado'];

    $stmt = $mysqli->prepare("INSERT INTO visitantes (nome, documento_identidade, empresa_motivo, id_usuario_visitado) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $nome, $documento, $motivo, $idVisitado);
    
    if ($stmt->execute()) {
        $_SESSION['flash_ok'] = "Entrada do visitante registada!";
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'registar_saida_visitante') {
    $id = (int)$_POST['id_visitante'];
    $mysqli->query("UPDATE visitantes SET data_saida = NOW(), status = 'saiu' WHERE id = $id");
    $_SESSION['flash_ok'] = "Saída do visitante registada.";
    header("Location: $redirect");
    exit;
}

// --- GESTÃO DE EMPRÉSTIMOS ---
if ($action === 'novo_emprestimo') {
    $idEquipamento = (int)$_POST['id_equipamento'];
    $idUsuario = (int)$_POST['id_usuario'];
    $dataDevolucao = $_POST['data_devolucao'];

    $mysqli->begin_transaction();
    try {
        // 1. Criar empréstimo
        $stmt = $mysqli->prepare("INSERT INTO emprestimos_equipamento (id_equipamento, id_usuario, data_devolucao_prevista) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $idEquipamento, $idUsuario, $dataDevolucao);
        $stmt->execute();

        // 2. Atualizar estado do equipamento
        $mysqli->query("UPDATE equipamentos SET estado = 'em_uso' WHERE id = $idEquipamento");

        $mysqli->commit();
        $_SESSION['flash_ok'] = "Empréstimo registado com sucesso!";
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['flash_erro'] = "Erro ao registar: " . $e->getMessage();
    }
    header("Location: $redirect");
    exit;
}
