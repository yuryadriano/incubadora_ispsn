<?php
require_once __DIR__ . '/../../config/auth.php';
obrigarLogin();

$idUsuario = (int)$_SESSION['usuario_id'];
$action    = $_POST['action'] ?? '';
$redirect  = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/utilizador/reservas.php';

if ($action === 'solicitar_reserva') {
    $idEspaco    = (int)$_POST['id_espaco'];
    $dataReserva = $_POST['data_reserva'];
    $horaInicio  = $_POST['hora_inicio'];
    $horaFim     = $_POST['hora_fim'];
    $objetivo    = trim($_POST['objetivo'] ?? '');

    // 1. Validar se o horário está disponível
    $check = $mysqli->prepare("
        SELECT id FROM reservas_espaco 
        WHERE id_espaco = ? 
        AND data_reserva = ? 
        AND status = 'confirmada'
        AND (
            (hora_inicio < ? AND hora_fim > ?) OR
            (hora_inicio < ? AND hora_fim > ?) OR
            (hora_inicio >= ? AND hora_fim <= ?)
        )
    ");
    $check->bind_param('isssssss', $idEspaco, $dataReserva, $horaFim, $horaInicio, $horaFim, $horaInicio, $horaInicio, $horaFim);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['flash_erro'] = "Este espaço já está ocupado no horário selecionado.";
        header("Location: $redirect");
        exit;
    }

    // 2. Criar reserva (como pendente para o recepcionista aprovar)
    $stmt = $mysqli->prepare("
        INSERT INTO reservas_espaco (id_espaco, id_usuario, data_reserva, hora_inicio, hora_fim, objetivo, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pendente')
    ");
    $stmt->bind_param('iissss', $idEspaco, $idUsuario, $dataReserva, $horaInicio, $horaFim, $objetivo);
    
    if ($stmt->execute()) {
        $_SESSION['flash_ok'] = "Reserva solicitada! Aguarde a confirmação da recepção.";
    } else {
        $_SESSION['flash_erro'] = "Erro ao processar reserva: " . $mysqli->error;
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'check_in') {
    $idReserva = (int)$_POST['id_reserva'];
    if (in_array($_SESSION['usuario_perfil'], ['admin', 'superadmin', 'funcionario'])) {
        $stmt = $mysqli->prepare("UPDATE reservas_espaco SET check_in_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $idReserva);
        $stmt->execute();
        $_SESSION['flash_ok'] = "Check-in efetuado com sucesso!";
    }
    header("Location: " . ($_POST['redirect'] ?? '/incubadora_ispsn/app/views/admin/gestao_espacos.php'));
    exit;
}

if ($action === 'adicionar_espaco') {
    $nome = $_POST['nome'];
    $tipo = $_POST['tipo'];
    $capacidade = (int)$_POST['capacidade'];

    $stmt = $mysqli->prepare("INSERT INTO espacos (nome, tipo, capacidade) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $nome, $tipo, $capacidade);
    $stmt->execute();
    $_SESSION['flash_ok'] = "Novo recurso físico registado!";
    header("Location: /incubadora_ispsn/app/views/admin/gestao_espacos.php");
    exit;
}

if ($action === 'gestao_reserva') {
    $idReserva = (int)$_POST['id_reserva'];
    $novoStatus = $_POST['novo_status'];

    if (in_array($_SESSION['usuario_perfil'], ['admin', 'superadmin', 'funcionario'])) {
        $stmt = $mysqli->prepare("UPDATE reservas_espaco SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $novoStatus, $idReserva);
        $stmt->execute();
        $_SESSION['flash_ok'] = "O status da reserva foi atualizado.";
    }
    header("Location: " . ($_POST['redirect'] ?? '/incubadora_ispsn/app/views/admin/gestao_espacos.php'));
    exit;
}
