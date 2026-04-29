<?php
// app/controllers/mentor_action.php
require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['mentor', 'admin', 'superadmin']);

$idUsuario = (int)$_SESSION['usuario_id'];
$action    = $_POST['action'] ?? '';
$redirect  = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/dashboard/mentor.php';

if ($action === 'criar_tarefa') {
    $idProjeto  = (int)$_POST['id_projeto'];
    $titulo     = trim($_POST['titulo']);
    $descricao  = trim($_POST['descricao']);
    $dataLimite = $_POST['data_limite'];
    $prioridade = $_POST['prioridade'] ?? 'media';

    if ($idProjeto > 0 && !empty($titulo)) {
        $stmt = $mysqli->prepare("
            INSERT INTO tarefas (id_projeto, id_criador, titulo, descricao, data_limite, prioridade, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pendente')
        ");
        $stmt->bind_param('iissss', $idProjeto, $idUsuario, $titulo, $descricao, $dataLimite, $prioridade);
        
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = "Tarefa criada com sucesso!";
        } else {
            $_SESSION['flash_erro'] = "Erro ao criar tarefa: " . $mysqli->error;
        }
        $stmt->close();
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'agendar_reuniao') {
    $idProjeto    = (int)$_POST['id_projeto'];
    $titulo       = trim($_POST['titulo']);
    $dataReuniao  = $_POST['data_reuniao'];
    $linkReuniao  = trim($_POST['link_reuniao'] ?? '');
    $local        = trim($_POST['local'] ?? '');
    $tipo         = $_POST['tipo_reuniao'] ?? 'virtual';

    // Gerar link automático se for virtual e estiver vazio
    if ($tipo === 'virtual' && empty($linkReuniao)) {
        $slug = preg_replace('/[^a-zA-Z0-9]/', '-', $titulo);
        $linkReuniao = "https://meet.jit.si/ISPSN-" . $slug . "-" . uniqid();
    }

    if ($idProjeto > 0 && !empty($titulo) && !empty($dataReuniao)) {
        $stmt = $mysqli->prepare("
            INSERT INTO reunioes (id_projeto, id_mentor, titulo, data_reuniao, link_reuniao, local, status)
            VALUES (?, ?, ?, ?, ?, ?, 'agendada')
        ");
        $stmt->bind_param('iissss', $idProjeto, $idUsuario, $titulo, $dataReuniao, $linkReuniao, $local);
        
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = "Reunião agendada com sucesso!";
            
            // Notificar o aluno
            $msg = "Nova reunião agendada: $titulo para o dia " . date('d/m/Y H:i', strtotime($dataReuniao));
            $stmtNotif = $mysqli->prepare("INSERT INTO notificacoes (id_usuario, mensagem, tipo) SELECT criado_por, ?, 'reuniao' FROM projetos WHERE id = ?");
            $stmtNotif->bind_param('si', $msg, $idProjeto);
            $stmtNotif->execute();
            $stmtNotif->close();

        } else {
            $_SESSION['flash_erro'] = "Erro ao agendar reunião: " . $mysqli->error;
        }
        $stmt->close();
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'atualizar_estado_tarefa') {
    $idTarefa = (int)$_POST['id_tarefa'];
    $status   = $_POST['status']; 

    if ($idTarefa > 0) {
        $stmt = $mysqli->prepare("UPDATE tarefas SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $idTarefa);
        $stmt->execute();
        $stmt->close();

        // Se concluída, dar pontos
        if ($status === 'concluida') {
            $mysqli->query("UPDATE projetos SET pontos = pontos + 10 WHERE id = (SELECT id_projeto FROM tarefas WHERE id = $idTarefa)");
        }
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'avaliar_progresso') {
    $idProjeto      = (int)$_POST['id_projeto'];
    $periodo        = trim($_POST['periodo']);
    $progressoGeral = (int)$_POST['progresso_geral'];
    $feedback       = trim($_POST['feedback']);
    $pontosFortes   = trim($_POST['pontos_fortes'] ?? '');
    $pontosMelhorar = trim($_POST['pontos_melhorar'] ?? '');
    $recomendacoes  = trim($_POST['recomendacoes'] ?? '');

    // 1. Buscar ID do mentor
    $stmt = $mysqli->prepare("SELECT id FROM mentores WHERE id_usuario = ?");
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $idMentor = $stmt->get_result()->fetch_assoc()['id'] ?? 0;
    $stmt->close();

    if ($idProjeto > 0 && $idMentor > 0 && !empty($feedback)) {
        $stmt = $mysqli->prepare("
            INSERT INTO avaliacoes_mentor (id_projeto, id_mentor, periodo, progresso_geral, feedback, pontos_fortes, pontos_melhorar, recomendacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iisissss', $idProjeto, $idMentor, $periodo, $progressoGeral, $feedback, $pontosFortes, $pontosMelhorar, $recomendacoes);
        
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = "Avaliação de acompanhamento registada!";
        } else {
            $_SESSION['flash_erro'] = "Erro ao guardar avaliação: " . $mysqli->error;
        }
        $stmt->close();
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'enviar_relatorio') {
    $idProjeto = (int)$_POST['id_projeto'];
    $titulo    = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $tipo      = $_POST['tipo'] ?? 'mensal';
    $destino   = $_POST['destino'] ?? 'admin';
    $nomeFicheiro = null;

    if ($idProjeto > 0 && !empty($titulo) && isset($_FILES['ficheiro_relatorio'])) {
        $file = $_FILES['ficheiro_relatorio'];
        
        if ($file['error'] === 0) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nomeFicheiro = "rel_" . time() . "_" . uniqid() . "." . $ext;
            $uploadDir = __DIR__ . '/../../uploads/relatorios/';
            
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $nomeFicheiro)) {
                $stmt = $mysqli->prepare("
                    INSERT INTO relatorios (id_projeto, id_autor, titulo, descricao, ficheiro, tipo, destino)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('iisssss', $idProjeto, $idUsuario, $titulo, $descricao, $nomeFicheiro, $tipo, $destino);
                
                if ($stmt->execute()) {
                    $_SESSION['flash_ok'] = "Relatório enviado com sucesso!";
                } else {
                    $_SESSION['flash_erro'] = "Erro ao salvar relatório no banco de dados.";
                }
                $stmt->close();
            } else {
                $_SESSION['flash_erro'] = "Erro ao mover o ficheiro para a pasta de destino.";
            }
        } else {
            $_SESSION['flash_erro'] = "Erro no upload do ficheiro.";
        }
    } else {
        $_SESSION['flash_erro'] = "Dados incompletos para o envio do relatório.";
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'registar_sessao') {
    $idMentoria   = (int)$_POST['id_mentoria'];
    $dataSessao   = $_POST['data_sessao'];
    $duracao      = (int)$_POST['duracao'];
    $topicos      = trim($_POST['topicos']);
    $proximos     = trim($_POST['proximos_passos'] ?? '');
    $avalEquipa   = (int)$_POST['aval_equipa'];
    $avalMentor   = (int)$_POST['aval_mentor'];

    if ($idMentoria > 0 && !empty($topicos) && !empty($dataSessao)) {
        $stmt = $mysqli->prepare("
            INSERT INTO sessoes_mentoria (id_mentoria, data_sessao, duracao_min, topicos, proximos_passos, avaliacao_mentor, avaliacao_equipa)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isisiii', $idMentoria, $dataSessao, $duracao, $topicos, $proximos, $avalMentor, $avalEquipa);
        
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = "Sessão de mentoria registada!";
        } else {
            $_SESSION['flash_erro'] = "Erro ao registar sessão: " . $mysqli->error;
        }
        $stmt->close();
    }
    header("Location: $redirect");
    exit;
}
