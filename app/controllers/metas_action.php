<?php
// app/controllers/metas_action.php
// Controller do Sistema de Metas — Activação, Evidências, Validação
require_once __DIR__ . '/../../config/auth.php';
obrigarLogin();

$perfil    = $_SESSION['usuario_perfil'] ?? 'utilizador';
$idUsuario = (int)$_SESSION['usuario_id'];
$action    = $_POST['action'] ?? '';
$redirect  = $_POST['redirect'] ?? '/incubadora_ispsn/public/index.php';

if (!str_starts_with($redirect, '/incubadora_ispsn/')) {
    $redirect = '/incubadora_ispsn/public/index.php';
}

/* ════════════════════════════════════════════════
   ACÇÃO: inicializar_metas
   Cria as entradas de metas_projeto para um projecto incubado
   (executado automaticamente ao incubar)
════════════════════════════════════════════════ */
if ($action === 'inicializar_metas' && in_array($perfil, ['superadmin','admin'])) {
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);
    $faseInicial = $_POST['fase'] ?? 'ideacao';
    
    if ($idProjeto > 0) {
        // Buscar metas padrão da fase
        $stmt = $mysqli->prepare("SELECT id FROM metas_padrao WHERE fase = ? AND activo = 1 ORDER BY numero");
        $stmt->bind_param('s', $faseInicial);
        $stmt->execute();
        $metas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $stmtInsert = $mysqli->prepare("INSERT IGNORE INTO metas_projeto (id_projeto, id_meta_padrao, estado) VALUES (?, ?, 'inactiva')");
        foreach ($metas as $m) {
            $stmtInsert->bind_param('ii', $idProjeto, $m['id']);
            $stmtInsert->execute();
        }
        $stmtInsert->close();
        
        $_SESSION['flash_ok'] = "Metas da fase " . strtoupper($faseInicial) . " inicializadas com sucesso!";
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: activar_meta
   SuperAdmin activa uma meta para um projecto
════════════════════════════════════════════════ */
if ($action === 'activar_meta' && in_array($perfil, ['superadmin'])) {
    $idMetaProjeto = (int)($_POST['id_meta_projeto'] ?? 0);
    $prazoManual   = $_POST['prazo_manual'] ?? '';
    
    if ($idMetaProjeto > 0) {
        // Buscar meta e projecto
        $stmt = $mysqli->prepare("
            SELECT mp.*, mpd.prazo_dias, mpd.titulo as meta_titulo, p.criado_por, p.titulo as proj_titulo
            FROM metas_projeto mp
            JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
            JOIN projetos p ON p.id = mp.id_projeto
            WHERE mp.id = ? AND mp.estado = 'inactiva'
        ");
        $stmt->bind_param('i', $idMetaProjeto);
        $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($meta) {
            // Calcular data limite
            if (!empty($prazoManual)) {
                $dataLimite = $prazoManual;
            } else {
                $dataLimite = date('Y-m-d', strtotime('+' . $meta['prazo_dias'] . ' days'));
            }
            
            // Activar a meta
            $stmt = $mysqli->prepare("UPDATE metas_projeto SET estado='activa', activada_por=?, activada_em=NOW(), data_limite=? WHERE id=?");
            $stmt->bind_param('isi', $idUsuario, $dataLimite, $idMetaProjeto);
            $stmt->execute();
            $stmt->close();
            
            // Notificar o estudante
            $tituloNotif = "🎯 Nova Meta Activada!";
            $msgNotif = "A meta \"" . htmlspecialchars($meta['meta_titulo']) . "\" foi activada para o teu projecto \"" . htmlspecialchars($meta['proj_titulo']) . "\". Prazo: " . date('d/m/Y', strtotime($dataLimite)) . ".";
            enviarNotificacao($meta['criado_por'], $tituloNotif, $msgNotif, 'info');
            
            // Notificar o mentor (se existir)
            $stmtMentor = $mysqli->prepare("
                SELECT m.id_usuario FROM mentorias mt 
                JOIN mentores m ON m.id = mt.id_mentor 
                WHERE mt.id_projeto = ? AND mt.estado = 'activa' LIMIT 1
            ");
            $stmtMentor->bind_param('i', $meta['id_projeto']);
            $stmtMentor->execute();
            $mentor = $stmtMentor->get_result()->fetch_assoc();
            $stmtMentor->close();
            
            if ($mentor) {
                enviarNotificacao($mentor['id_usuario'], "🎯 Meta Activada — " . htmlspecialchars($meta['proj_titulo']), 
                    "A meta \"" . htmlspecialchars($meta['meta_titulo']) . "\" foi activada. Acompanhe o estudante e crie tarefas de apoio.", 'info');
            }
            
            $_SESSION['flash_ok'] = "Meta \"" . htmlspecialchars($meta['meta_titulo']) . "\" activada com sucesso! Prazo: " . date('d/m/Y', strtotime($dataLimite));
        } else {
            $_SESSION['flash_erro'] = "Meta não encontrada ou já foi activada.";
        }
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: activar_todas_fase
   SuperAdmin activa todas as metas de uma fase de uma vez
════════════════════════════════════════════════ */
if ($action === 'activar_todas_fase' && in_array($perfil, ['superadmin'])) {
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);
    $fase = $_POST['fase'] ?? '';
    
    if ($idProjeto > 0 && $fase) {
        $stmt = $mysqli->prepare("
            UPDATE metas_projeto mp
            JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
            SET mp.estado = 'activa', mp.activada_por = ?, mp.activada_em = NOW(),
                mp.data_limite = DATE_ADD(NOW(), INTERVAL mpd.prazo_dias DAY)
            WHERE mp.id_projeto = ? AND mpd.fase = ? AND mp.estado = 'inactiva'
        ");
        $stmt->bind_param('iis', $idUsuario, $idProjeto, $fase);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        $_SESSION['flash_ok'] = "$affected metas da fase " . strtoupper($fase) . " activadas!";
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: submeter_evidencia
   Estudante submete evidência para uma meta activa
════════════════════════════════════════════════ */
if ($action === 'submeter_evidencia') {
    $idMetaProjeto = (int)($_POST['id_meta_projeto'] ?? 0);
    $evidenciaTexto = trim($_POST['evidencia_texto'] ?? '');
    $evidenciaLink  = trim($_POST['evidencia_link'] ?? '');
    
    if ($idMetaProjeto > 0) {
        // Verificar que a meta é do projecto do estudante e está activa
        $stmt = $mysqli->prepare("
            SELECT mp.*, p.criado_por, p.titulo as proj_titulo, mpd.titulo as meta_titulo
            FROM metas_projeto mp
            JOIN projetos p ON p.id = mp.id_projeto
            JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
            LEFT JOIN membros_projeto memb ON memb.id_projeto = p.id AND memb.id_usuario = ?
            WHERE mp.id = ? AND mp.estado IN ('activa','reprovada')
            AND (p.criado_por = ? OR memb.id_usuario IS NOT NULL)
        ");
        $stmt->bind_param('iii', $idUsuario, $idMetaProjeto, $idUsuario);
        $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($meta) {
            $evidenciaPath = null;
            
            // Upload de ficheiro
            if (isset($_FILES['evidencia_ficheiro']) && $_FILES['evidencia_ficheiro']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['evidencia_ficheiro']['name'], PATHINFO_EXTENSION);
                $novoNome = "meta_" . $idMetaProjeto . "_" . time() . "_" . uniqid() . "." . $ext;
                $folder = __DIR__ . "/../../uploads/metas/";
                if (!is_dir($folder)) mkdir($folder, 0777, true);
                
                if (move_uploaded_file($_FILES['evidencia_ficheiro']['tmp_name'], $folder . $novoNome)) {
                    $evidenciaPath = "uploads/metas/" . $novoNome;
                }
            }
            
            // Actualizar a meta com a evidência
            $stmt = $mysqli->prepare("
                UPDATE metas_projeto SET 
                    estado = 'em_avaliacao',
                    evidencia_path = COALESCE(?, evidencia_path),
                    evidencia_link = ?,
                    evidencia_texto = ?,
                    evidencia_em = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('sssi', $evidenciaPath, $evidenciaLink, $evidenciaTexto, $idMetaProjeto);
            $stmt->execute();
            $stmt->close();
            
            // Notificar mentor
            $stmtMentor = $mysqli->prepare("
                SELECT m.id_usuario FROM mentorias mt 
                JOIN mentores m ON m.id = mt.id_mentor 
                WHERE mt.id_projeto = ? AND mt.estado = 'activa' LIMIT 1
            ");
            $stmtMentor->bind_param('i', $meta['id_projeto']);
            $stmtMentor->execute();
            $mentor = $stmtMentor->get_result()->fetch_assoc();
            $stmtMentor->close();
            
            if ($mentor) {
                enviarNotificacao($mentor['id_usuario'], "📎 Evidência Submetida", 
                    "O estudante submeteu evidência para a meta \"" . htmlspecialchars($meta['meta_titulo']) . "\" do projecto \"" . htmlspecialchars($meta['proj_titulo']) . "\". Revise e valide.", 'info');
            }
            
            $_SESSION['flash_ok'] = "Evidência submetida com sucesso! A aguardar validação do mentor.";
        } else {
            $_SESSION['flash_erro'] = "Meta não encontrada ou não tens permissão.";
        }
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: validar_evidencia
   Mentor valida ou reprova evidência submetida
════════════════════════════════════════════════ */
if ($action === 'validar_evidencia' && in_array($perfil, ['mentor','admin','superadmin'])) {
    $idMetaProjeto  = (int)($_POST['id_meta_projeto'] ?? 0);
    $decisao        = $_POST['decisao'] ?? ''; // 'aprovar' ou 'reprovar'
    $feedbackMentor = trim($_POST['feedback_mentor'] ?? '');
    $notaMentor     = min(5, max(1, (int)($_POST['nota_mentor'] ?? 3)));
    
    if ($idMetaProjeto > 0 && in_array($decisao, ['aprovar','reprovar'])) {
        $stmt = $mysqli->prepare("
            SELECT mp.*, p.criado_por, p.titulo as proj_titulo, p.id as proj_id,
                   mpd.titulo as meta_titulo, mpd.peso_percentual
            FROM metas_projeto mp
            JOIN projetos p ON p.id = mp.id_projeto
            JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
            WHERE mp.id = ? AND mp.estado = 'em_avaliacao'
        ");
        $stmt->bind_param('i', $idMetaProjeto);
        $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($meta) {
            if ($decisao === 'aprovar') {
                // Concluir a meta
                $pontosGanhos = (int)round($meta['peso_percentual']);
                $stmt = $mysqli->prepare("
                    UPDATE metas_projeto SET 
                        estado = 'concluida', validada_por = ?, validada_em = NOW(),
                        feedback_mentor = ?, nota_mentor = ?, concluida_em = NOW(),
                        pontos_ganhos = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('isiii', $idUsuario, $feedbackMentor, $notaMentor, $pontosGanhos, $idMetaProjeto);
                $stmt->execute();
                $stmt->close();
                
                // Atribuir pontos ao projecto
                $mysqli->query("UPDATE projetos SET pontos = pontos + $pontosGanhos WHERE id = " . (int)$meta['proj_id']);
                
                // Notificar estudante
                enviarNotificacao($meta['criado_por'], "🏆 Meta Concluída!", 
                    "Parabéns! A meta \"" . htmlspecialchars($meta['meta_titulo']) . "\" foi validada pelo mentor. +" . $pontosGanhos . " SP atribuídos!", 'sucesso');
                
                // Verificar se todas as metas da fase estão concluídas
                $stmtCheck = $mysqli->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN mp.estado = 'concluida' THEN 1 ELSE 0 END) as concluidas
                    FROM metas_projeto mp
                    JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
                    WHERE mp.id_projeto = ? AND mpd.fase = (
                        SELECT fase FROM metas_padrao WHERE id = ?
                    )
                ");
                $stmtCheck->bind_param('ii', $meta['proj_id'], $meta['id_meta_padrao']);
                $stmtCheck->execute();
                $progresso = $stmtCheck->get_result()->fetch_assoc();
                $stmtCheck->close();
                
                if ($progresso && $progresso['total'] > 0 && $progresso['concluidas'] == $progresso['total']) {
                    // Todas as metas da fase concluídas — notificar SuperAdmin
                    $admins = $mysqli->query("SELECT id FROM usuarios WHERE perfil = 'superadmin' AND activo = 1");
                    if ($admins) {
                        while ($a = $admins->fetch_assoc()) {
                            enviarNotificacao($a['id'], "🚀 Fase Completa!", 
                                "O projecto \"" . htmlspecialchars($meta['proj_titulo']) . "\" completou todas as metas da fase actual e está elegível para avançar!", 'sucesso');
                        }
                    }
                    enviarNotificacao($meta['criado_por'], "🚀 Fase Completa!", 
                        "Parabéns! Completaste todas as metas da fase actual. O SuperAdmin será notificado para avaliar o avanço de fase.", 'sucesso');
                }
                
                $_SESSION['flash_ok'] = "Meta validada com sucesso! +" . $pontosGanhos . " SP atribuídos.";
            } else {
                // Reprovar — devolver ao estudante
                $stmt = $mysqli->prepare("
                    UPDATE metas_projeto SET 
                        estado = 'reprovada', validada_por = ?, validada_em = NOW(),
                        feedback_mentor = ?, nota_mentor = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('isii', $idUsuario, $feedbackMentor, $notaMentor, $idMetaProjeto);
                $stmt->execute();
                $stmt->close();
                
                enviarNotificacao($meta['criado_por'], "🔄 Evidência Devolvida", 
                    "A evidência da meta \"" . htmlspecialchars($meta['meta_titulo']) . "\" precisa de melhorias. Feedback do mentor: " . htmlspecialchars($feedbackMentor), 'aviso');
                
                $_SESSION['flash_ok'] = "Evidência devolvida ao estudante com feedback.";
            }
        }
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: desactivar_meta
   SuperAdmin desactiva uma meta (volta a inactiva)
════════════════════════════════════════════════ */
if ($action === 'desactivar_meta' && $perfil === 'superadmin') {
    $idMetaProjeto = (int)($_POST['id_meta_projeto'] ?? 0);
    if ($idMetaProjeto > 0) {
        $stmt = $mysqli->prepare("UPDATE metas_projeto SET estado='inactiva', activada_por=NULL, activada_em=NULL, data_limite=NULL WHERE id=? AND estado='activa'");
        $stmt->bind_param('i', $idMetaProjeto);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_ok'] = "Meta desactivada.";
    }
    header("Location: $redirect");
    exit;
}

// Se chegou aqui sem action válida
header("Location: $redirect");
exit;
