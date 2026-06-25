<?php
// app/controllers/mentoria_action.php
require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['funcionario','admin','superadmin']);

csrf_verificar();

$action   = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/funcionario/mentorias.php';

// Sanitizar redirect (apenas URLs internas)
if (!str_starts_with($redirect, '/incubadora_ispsn/')) {
    $redirect = '/incubadora_ispsn/app/views/funcionario/mentorias.php';
}

if ($action === 'criar_mentoria') {
    $idProjeto  = (int)($_POST['id_projeto'] ?? 0);
    $idMentor   = (int)($_POST['id_mentor']  ?? 0);
    $dataInicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $dataFim    = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;

    if ($idProjeto && $idMentor) {
        // Verificar se projeto existe e mentor existe e está disponível
        $checkProj = $mysqli->prepare("SELECT id FROM projetos WHERE id = ?");
        $checkProj->bind_param('i', $idProjeto);
        $checkProj->execute();
        $resProj = $checkProj->get_result()->fetch_assoc();
        $checkProj->close();

        $checkMentor = $mysqli->prepare("SELECT id FROM mentores WHERE id = ? AND disponivel = 1");
        $checkMentor->bind_param('i', $idMentor);
        $checkMentor->execute();
        $resMentor = $checkMentor->get_result()->fetch_assoc();
        $checkMentor->close();

        if ($resProj && $resMentor) {
            $stmt = $mysqli->prepare("INSERT INTO mentorias (id_projeto, id_mentor, data_inicio, data_fim, estado) VALUES (?, ?, ?, ?, 'activa')");
            $stmt->bind_param('iiss', $idProjeto, $idMentor, $dataInicio, $dataFim);
            if ($stmt->execute()) {
                $_SESSION['flash_ok'] = 'Mentoria criada com sucesso!';
            } else {
                $_SESSION['flash_erro'] = 'Erro ao criar mentoria no banco de dados.';
            }
            $stmt->close();
        } else {
            $_SESSION['flash_erro'] = 'Projeto ou Mentor inválidos ou indisponíveis.';
        }
    } else {
        $_SESSION['flash_erro'] = 'Seleccione um projecto e um mentor.';
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'mudar_estado_mentoria') {
    $idMentoria = (int)($_POST['id_mentoria'] ?? 0);
    $estado     = $_POST['estado_m'] ?? '';

    $estadosValidos = ['activa', 'concluida', 'cancelada'];

    if ($idMentoria > 0 && in_array($estado, $estadosValidos)) {
        $stmt = $mysqli->prepare("UPDATE mentorias SET estado = ? WHERE id = ?");
        $stmt->bind_param('si', $estado, $idMentoria);
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = 'Estado da mentoria actualizado com sucesso para ' . ucfirst($estado) . '.';
        } else {
            $_SESSION['flash_erro'] = 'Erro ao actualizar o estado da mentoria.';
        }
        $stmt->close();
    } else {
        $_SESSION['flash_erro'] = 'Dados inválidos para alteração de estado.';
    }
    header("Location: $redirect");
    exit;
}

// Se nenhuma ação válida
header("Location: $redirect");
exit;
