<?php
// app/controllers/metas_action.php
// Controller do Sistema de Metas — Activação, Evidências, Validação
require_once __DIR__ . '/../../config/auth.php';
obrigarLogin();

// ── Verificação CSRF ──
csrf_verificar();

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
   Admin/SuperAdmin activa uma meta para um projecto
════════════════════════════════════════════════ */
if ($action === 'activar_meta' && in_array($perfil, ['superadmin', 'admin'])) {
    $idMetaProjeto = (int)($_POST['id_meta_projeto'] ?? 0);
    $opcaoPrazo    = $_POST['opcao_prazo'] ?? 'padrao';
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
            if ($opcaoPrazo === 'semana') {
                $dataLimite = date('Y-m-d', strtotime('+7 days'));
            } elseif ($opcaoPrazo === 'mes') {
                $dataLimite = date('Y-m-d', strtotime('+30 days'));
            } elseif ($opcaoPrazo === 'personalizado' && !empty($prazoManual)) {
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
   Admin/SuperAdmin activa todas as metas de uma fase de uma vez
════════════════════════════════════════════════ */
if ($action === 'activar_todas_fase' && in_array($perfil, ['superadmin', 'admin'])) {
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
                $statusPrazo = "";
                if (!empty($meta['data_limite']) && strtotime($meta['data_limite']) < strtotime(date('Y-m-d'))) {
                    $statusPrazo = " (SUBMETIDA COM ATRASO)";
                }
                enviarNotificacao($mentor['id_usuario'], "📎 Evidência Submetida" . $statusPrazo, 
                    "O estudante submeteu evidência para a meta \"" . htmlspecialchars($meta['meta_titulo']) . "\" do projecto \"" . htmlspecialchars($meta['proj_titulo']) . "\"." . ($statusPrazo ? " Nota: Foi submetida após o prazo limite!" : "") . " Revise e valide.", 'info');
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
                
                // Atribuir pontos ao projecto — prepared statement (sem interpolação)
                $projIdInt = (int)$meta['proj_id'];
                $stmtPts = $mysqli->prepare("UPDATE projetos SET pontos = pontos + ? WHERE id = ?");
                $stmtPts->bind_param('ii', $pontosGanhos, $projIdInt);
                $stmtPts->execute();
                $stmtPts->close();
                
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
   Admin/SuperAdmin desactiva uma meta (volta a inactiva)
════════════════════════════════════════════════ */
if ($action === 'desactivar_meta' && in_array($perfil, ['superadmin', 'admin'])) {
    $idMetaProjeto = (int)($_POST['id_meta_projeto'] ?? 0);
    if ($idMetaProjeto > 0) {
        $stmt = $mysqli->prepare("UPDATE metas_projeto SET estado='inactiva', activada_por=NULL, activada_em=NULL, data_limite=NULL WHERE id=? AND estado='activa'");
        $stmt->bind_param('i', $idMetaProjeto);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_ok'] = "Meta desactivada com sucesso.";
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'inicializar_e_activar_todas' && in_array($perfil, ['superadmin','admin'])) {
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);
    $fase = $_POST['fase'] ?? 'ideacao';
    
    if ($idProjeto > 0 && in_array($fase, ['ideacao','validacao','mvp','tracao','mercado'])) {
        // 1. Buscar metas padrão da fase
        $stmt = $mysqli->prepare("SELECT id, prazo_dias FROM metas_padrao WHERE fase = ? AND activo = 1 ORDER BY numero");
        $stmt->bind_param('s', $fase);
        $stmt->execute();
        $metas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // 2. Inserir (ignore) em metas_projeto
        $stmtInsert = $mysqli->prepare("INSERT IGNORE INTO metas_projeto (id_projeto, id_meta_padrao, estado) VALUES (?, ?, 'inactiva')");
        foreach ($metas as $m) {
            $stmtInsert->bind_param('ii', $idProjeto, $m['id']);
            $stmtInsert->execute();
        }
        $stmtInsert->close();
        
        // 3. Activar todas as metas desta fase para o projecto que estejam inactivas
        $stmtSelect = $mysqli->prepare("
            SELECT mp.id, mpd.prazo_dias, mpd.titulo as meta_titulo, p.criado_por, p.titulo as proj_titulo
            FROM metas_projeto mp
            JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
            JOIN projetos p ON p.id = mp.id_projeto
            WHERE mp.id_projeto = ? AND mpd.fase = ? AND mp.estado = 'inactiva'
        ");
        $stmtSelect->bind_param('is', $idProjeto, $fase);
        $stmtSelect->execute();
        $metasInactivas = $stmtSelect->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtSelect->close();
        
        $stmtUpdate = $mysqli->prepare("UPDATE metas_projeto SET estado='activa', activada_por=?, activada_em=NOW(), data_limite=? WHERE id=?");
        foreach ($metasInactivas as $mi) {
            $dataLimite = date('Y-m-d', strtotime('+' . $mi['prazo_dias'] . ' days'));
            $stmtUpdate->bind_param('isi', $idUsuario, $dataLimite, $mi['id']);
            $stmtUpdate->execute();
            
            // Notificar o estudante
            $tituloNotif = "🎯 Nova Meta Activada!";
            $msgNotif = "A meta \"" . htmlspecialchars($mi['meta_titulo']) . "\" foi activada para o teu projecto \"" . htmlspecialchars($mi['proj_titulo']) . "\". Prazo: " . date('d/m/Y', strtotime($dataLimite)) . ".";
            enviarNotificacao($mi['criado_por'], $tituloNotif, $msgNotif, 'info');
        }
        $stmtUpdate->close();
        
        $_SESSION['flash_ok'] = "Metas da fase " . strtoupper($fase) . " inicializadas e activadas com sucesso!";
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'editar_meta_padrao' && $perfil === 'superadmin') {
    $idMetaPadrao = (int)($_POST['id_meta_padrao'] ?? 0);
    $fase = $_POST['fase'] ?? '';
    $numero = (int)($_POST['numero'] ?? 1);
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $evidencia_tipo = $_POST['evidencia_tipo'] ?? 'ficheiro';
    $evidencia_desc = trim($_POST['evidencia_desc'] ?? '');
    $peso_percentual = (int)($_POST['peso_percentual'] ?? 20);
    $prazo_dias = (int)($_POST['prazo_dias'] ?? 15);
    
    if ($idMetaPadrao > 0 && !empty($fase) && !empty($titulo) && !empty($descricao)) {
        $stmt = $mysqli->prepare("
            UPDATE metas_padrao SET 
                fase = ?, numero = ?, titulo = ?, descricao = ?, 
                evidencia_tipo = ?, evidencia_desc = ?, 
                peso_percentual = ?, prazo_dias = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sissssiii', $fase, $numero, $titulo, $descricao, $evidencia_tipo, $evidencia_desc, $peso_percentual, $prazo_dias, $idMetaPadrao);
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = "Meta padrão atualizada com sucesso!";
        } else {
            $_SESSION['flash_erro'] = "Erro ao atualizar meta padrão: " . $mysqli->error;
        }
        $stmt->close();
    } else {
        $_SESSION['flash_erro'] = "Preencha todos os campos obrigatórios.";
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: criar_meta_padrao
   SuperAdmin cria/define uma nova meta padrão no dicionário
════════════════════════════════════════════════ */
if ($action === 'criar_meta_padrao' && $perfil === 'superadmin') {
    $fase = $_POST['fase'] ?? '';
    $numero = (int)($_POST['numero'] ?? 1);
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $evidencia_tipo = $_POST['evidencia_tipo'] ?? 'ficheiro';
    $evidencia_desc = trim($_POST['evidencia_desc'] ?? '');
    $peso_percentual = (int)($_POST['peso_percentual'] ?? 20);
    $prazo_dias = (int)($_POST['prazo_dias'] ?? 15);
    
    if (!empty($fase) && !empty($titulo) && !empty($descricao)) {
        // Inserir na base de dados
        $stmt = $mysqli->prepare("
            INSERT INTO metas_padrao (fase, numero, titulo, descricao, evidencia_tipo, evidencia_desc, peso_percentual, prazo_dias, activo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param('sissssii', $fase, $numero, $titulo, $descricao, $evidencia_tipo, $evidencia_desc, $peso_percentual, $prazo_dias);
        
        if ($stmt->execute()) {
            $newMetaId = $stmt->insert_id;
            $stmt->close();
            
            // Associar automaticamente a meta criada (como inactiva) a todos os projetos que estão na respetiva fase
            $resProjs = $mysqli->prepare("SELECT id FROM projetos WHERE fase = ?");
            $resProjs->bind_param('s', $fase);
            $resProjs->execute();
            $projIds = $resProjs->get_result()->fetch_all(MYSQLI_ASSOC);
            $resProjs->close();
            
            $stmtInsertProj = $mysqli->prepare("INSERT IGNORE INTO metas_projeto (id_projeto, id_meta_padrao, estado) VALUES (?, ?, 'inactiva')");
            foreach ($projIds as $p) {
                $stmtInsertProj->bind_param('ii', $p['id'], $newMetaId);
                $stmtInsertProj->execute();
            }
            $stmtInsertProj->close();
            
            $_SESSION['flash_ok'] = "Meta padrão criada e associada aos projectos na fase " . strtoupper($fase) . "!";
        } else {
            $_SESSION['flash_erro'] = "Erro ao criar meta padrão: " . $mysqli->error;
        }
    } else {
        $_SESSION['flash_erro'] = "Preencha todos os campos obrigatórios.";
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: eliminar_meta_padrao
   SuperAdmin elimina uma meta padrão do dicionário
════════════════════════════════════════════════ */
if ($action === 'eliminar_meta_padrao' && $perfil === 'superadmin') {
    $idMetaPadrao = (int)($_POST['id_meta_padrao'] ?? 0);
    if ($idMetaPadrao > 0) {
        // Desativar meta padrão
        $stmt = $mysqli->prepare("UPDATE metas_padrao SET activo = 0 WHERE id = ?");
        $stmt->bind_param('i', $idMetaPadrao);
        if ($stmt->execute()) {
            $stmt->close();
            
            // Remover da tabela metas_projeto as que ainda estiverem inactivas (não começadas) — prepared statement
            $stmtDel = $mysqli->prepare("DELETE FROM metas_projeto WHERE id_meta_padrao = ? AND estado = 'inactiva'");
            $stmtDel->bind_param('i', $idMetaPadrao);
            $stmtDel->execute();
            $stmtDel->close();
            
            $_SESSION['flash_ok'] = "Meta padrão eliminada com sucesso!";
        } else {
            $_SESSION['flash_erro'] = "Erro ao eliminar meta padrão.";
        }
    }
    header("Location: $redirect");
    exit;
}

// Se chegou aqui sem action válida
header("Location: $redirect");
exit;
