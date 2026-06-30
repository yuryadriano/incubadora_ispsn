<?php
// app/controllers/projeto_action.php
// Controlador central de acções sobre projectos
// Chamado via POST de qualquer ecrã de projecto

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../utils/GeminiAI.php';
obrigarLogin();

// ── Verificação CSRF (todos os POST excepto AJAX JSON) ──
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
          str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
if (!$isAjax) {
    csrf_verificar();
}

$perfil    = $_SESSION['usuario_perfil'] ?? 'utilizador';
$idUsuario = (int)$_SESSION['usuario_id'];
$action    = $_POST['action'] ?? '';
$redirect  = $_POST['redirect'] ?? '/incubadora_ispsn/public/index.php';

// ── Sanitizar redirect (apenas URLs internas) ──
if (!str_starts_with($redirect, '/incubadora_ispsn/')) {
    $redirect = '/incubadora_ispsn/public/index.php';
}

/* ════════════════════════════════════════════════
   ACÇÃO: criar_projeto
   Cria um novo projecto (qualquer utilizador logado)
════════════════════════════════════════════════ */
if ($action === 'criar_projeto') {
    $titulo        = trim($_POST['titulo'] ?? '');
    $tipo          = $_POST['tipo'] ?? 'incubado';
    $descricao     = trim($_POST['descricao'] ?? '');
    $problema      = trim($_POST['problema'] ?? '');
    $solucao       = trim($_POST['solucao'] ?? '');
    $area_tematica = $_POST['area'] ?? 'tecnologia';

    $tiposValidos = ['startup_tecnologica','negocio_tradicional','impacto_social','outro'];
    $areasValidas = ['tecnologia','saude','educacao','agro','financas','outro'];

    if (strlen($titulo) < 5) {
        $_SESSION['flash_erro'] = 'O título deve ter pelo menos 5 caracteres.';
    } elseif (strlen($descricao) < 20) {
        $_SESSION['flash_erro'] = 'A descrição deve ter pelo menos 20 caracteres.';
    } elseif (!in_array($tipo, $tiposValidos)) {
        $_SESSION['flash_erro'] = 'Tipo de projecto inválido.';
    } else {
        $pitch_path = '';
        if (isset($_FILES['pitch_ficheiro']) && $_FILES['pitch_ficheiro']['error'] === UPLOAD_ERR_OK) {
            $maxBytes = 15 * 1024 * 1024; // 15 MB
            $exts = ['pdf', 'ppt', 'pptx', 'zip'];
            $mimes = [
                'application/pdf',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
                'application/x-zip-compressed'
            ];
            
            $fileSize = $_FILES['pitch_ficheiro']['size'];
            $tmpPath = $_FILES['pitch_ficheiro']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['pitch_ficheiro']['name'], PATHINFO_EXTENSION));
            
            if ($fileSize > $maxBytes) {
                $_SESSION['flash_erro'] = 'Ficheiro do Pitch demasiado grande. Limite máximo: 15 MB.';
            } elseif (!in_array($ext, $exts)) {
                $_SESSION['flash_erro'] = 'Tipo de ficheiro para Pitch não permitido. Extensões aceites: PDF, PPT, PPTX, ZIP.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeReal = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                
                if (!in_array($mimeReal, $mimes)) {
                    $_SESSION['flash_erro'] = 'O conteúdo do ficheiro do Pitch não corresponde ao tipo de ficheiro aceite.';
                } else {
                    $novoNome = "pitch_proj_" . time() . '_' . bin2hex(random_bytes(4)) . ".{$ext}";
                    $folder = __DIR__ . '/../../uploads/pitches/';
                    if (!is_dir($folder)) mkdir($folder, 0755, true);
                    
                    if (move_uploaded_file($tmpPath, $folder . $novoNome)) {
                        $pitch_path = 'uploads/pitches/' . $novoNome;
                    } else {
                        $_SESSION['flash_erro'] = 'Falha ao mover o ficheiro do Pitch para o servidor.';
                    }
                }
            }
        }

        if (empty($_SESSION['flash_erro'])) {
            // id_responsavel = o próprio criador por defeito
            // Todos os projetos novos iniciam como 'submetido'
            $stmt = $mysqli->prepare("
                INSERT INTO projetos
                    (titulo, tipo, descricao, problema, solucao, area_tematica, estado, id_responsavel, criado_por, pitch_path)
                VALUES (?, ?, ?, ?, ?, ?, 'submetido', ?, ?, ?)
            ");
            $stmt->bind_param('ssssssiiis',
                $titulo, $tipo, $descricao, $problema, $solucao,
                $area_tematica, $idUsuario, $idUsuario, $pitch_path
            );

            if ($stmt->execute()) {
            $idNovoProjeto = $mysqli->insert_id;
            // Adicionar criador como membro
            $s2 = $mysqli->prepare("INSERT IGNORE INTO membros_projeto (id_projeto, id_usuario, papel) VALUES (?,?,'Líder')");
            $s2->bind_param('ii', $idNovoProjeto, $idUsuario);
            $s2->execute();

            // Notificação interna para admins
            $admins = $mysqli->query("SELECT id FROM usuarios WHERE perfil IN ('admin','superadmin') AND activo=1");
            if ($admins) {
                $sn = $mysqli->prepare("INSERT INTO notificacoes (id_usuario,titulo,mensagem,tipo) VALUES (?,?,?,'info')");
                $msg = "Novo projecto submetido: \"$titulo\" por {$_SESSION['usuario_nome']}";
                while ($a = $admins->fetch_assoc()) {
                    $sn->bind_param('iss', $a['id'], $titulo, $msg);
                    $sn->execute();
                }
            }
            $_SESSION['flash_ok'] = 'Projecto submetido com sucesso! Aguarda avaliação.';
        } else {
            $_SESSION['flash_erro'] = 'Erro ao salvar o projecto. Tente novamente.';
        }
        }
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: mudar_estado
   Muda o estado de um projecto (apenas admin/superadmin)
════════════════════════════════════════════════ */
if ($action === 'mudar_estado' && in_array($perfil, ['admin','superadmin','funcionario'])) {
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);
    $novoEstado = $_POST['estado'] ?? '';
    $motivo     = trim($_POST['motivo_rejeicao'] ?? '');

    $estadosValidos = ['submetido', 'em_avaliacao', 'aprovado', 'rejeitado', 'incubado', 'fundo_investimento', 'concluido'];

    if ($idProjeto && in_array($novoEstado, $estadosValidos)) {
        // Bloquear transição directa para incubado - deve ir pelo fluxo de termo + assinatura digital
        if ($novoEstado === 'incubado') {
            $_SESSION['flash_erro'] = 'O estado Incubado só pode ser activado através da assinatura digital do Termo de Incubação.';
            header("Location: $redirect");
            exit;
        }

        // Buscar estado anterior para o histórico
        $stmtEst = $mysqli->prepare("SELECT estado FROM projetos WHERE id = ?");
        $stmtEst->bind_param('i', $idProjeto);
        $stmtEst->execute();
        $projData = $stmtEst->get_result()->fetch_assoc();
        $stmtEst->close();
        $estadoAnterior = $projData ? $projData['estado'] : '';

        $stmt = $mysqli->prepare("UPDATE projetos SET estado=?, motivo_rejeicao=? WHERE id=?");
        $stmt->bind_param('ssi', $novoEstado, $motivo, $idProjeto);
        $stmt->execute();
        $stmt->close();

        // Gravar histórico
        if ($estadoAnterior !== $novoEstado) {
            $stmtLog = $mysqli->prepare("INSERT INTO historico_estados (id_projeto, estado_anterior, estado_novo, id_usuario, motivo) VALUES (?, ?, ?, ?, ?)");
            $motivoLog = "Mudança de estado manual pelo administrador. " . ($motivo ? "Motivo: $motivo" : "");
            $stmtLog->bind_param('issis', $idProjeto, $estadoAnterior, $novoEstado, $idUsuario, $motivoLog);
            $stmtLog->execute();
            $stmtLog->close();
        }

        // Notificar o criador do projecto
        $rp = $mysqli->prepare("SELECT criado_por, titulo FROM projetos WHERE id=?");
        $rp->bind_param('i', $idProjeto);
        $rp->execute();
        $proj = $rp->get_result()->fetch_assoc();
        $rp->close();

        if ($proj) {
            $labelEstado = [
                'submetido'          => 'Submetido (Aguardando Triagem)',
                'em_avaliacao'       => 'Em Avaliação Técnica 🔍',
                'aprovado'           => 'Aprovado para Incubação ✓',
                'rejeitado'          => 'Não Selecionado ✗',
                'incubado'           => 'Em Processo de Incubação 🚀',
                'fundo_investimento' => 'Pronto para Financiamento 💰',
                'concluido'          => 'Graduado / Concluído ✨'
            ];
            $tit = "Actualização de Estado: " . htmlspecialchars($proj['titulo']);
            $nomeEstado = $labelEstado[$novoEstado] ?? $novoEstado;
            $msg = "O estado do seu projecto foi actualizado para: **$nomeEstado**."
                . ($motivo ? "\n\n**Nota do Administrador:** $motivo" : '');
            
            enviarNotificacao($proj['criado_por'], $tit, $msg, ($novoEstado === 'rejeitado' ? 'erro' : 'sucesso'));
        }
        $_SESSION['flash_ok'] = 'Estado actualizado com sucesso.';
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: adicionar_comentario
   Adiciona comentário a um projecto
════════════════════════════════════════════════ */
if ($action === 'adicionar_comentario') {
    $idProjeto  = (int)($_POST['id_projeto'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');
    $fase       = $_POST['fase'] ?? 'em_analise';

    $fasesValidas = ['em_analise','em_andamento','concluido'];

    if ($idProjeto && strlen($comentario) >= 5 && in_array($fase, $fasesValidas)) {
        $stmt = $mysqli->prepare("
            INSERT INTO comentarios_projetos (id_projeto, id_usuario, comentario, fase)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('iiss', $idProjeto, $idUsuario, $comentario, $fase);
        $stmt->execute();
        $_SESSION['flash_ok'] = 'Comentário adicionado.';

        // Notificar o dono do projeto
        $sqlDono = "SELECT criado_por FROM projetos WHERE id = ?";
        $stD = $mysqli->prepare($sqlDono);
        $stD->bind_param('i', $idProjeto);
        $stD->execute();
        $dono = $stD->get_result()->fetch_assoc();
        if ($dono && $dono['criado_por'] != $idUsuario) {
             enviarNotificacao($dono['criado_por'], "Novo Feedback", "Recebeste um novo comentário no teu projeto.", 'info');
        }
    } else {
        $_SESSION['flash_erro'] = 'Comentário inválido (mínimo 5 caracteres).';
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: avaliar_projeto
   Salva avaliação formal (admin/superadmin)
════════════════════════════════════════════════ */
if ($action === 'avaliar_projeto' && in_array($perfil, ['admin','superadmin'])) {
    $idProjeto            = (int)($_POST['id_projeto'] ?? 0);
    $notaInovacao         = min(10, max(0, (int)($_POST['nota_inovacao']         ?? 0)));
    $notaViabilidade      = min(10, max(0, (int)($_POST['nota_viabilidade']      ?? 0)));
    $notaImpacto          = min(10, max(0, (int)($_POST['nota_impacto']          ?? 0)));
    $notaEquipa           = min(10, max(0, (int)($_POST['nota_equipa']           ?? 0)));
    $notaSustentabilidade = min(10, max(0, (int)($_POST['nota_sustentabilidade'] ?? 0)));
    $notaEscalabilidade   = min(10, max(0, (int)($_POST['nota_escalabilidade']   ?? 0)));
    $notaMercado          = min(10, max(0, (int)($_POST['nota_mercado']          ?? 0)));
    $notaProposta         = min(10, max(0, (int)($_POST['nota_proposta']         ?? 0)));
    $observacoes          = trim($_POST['observacoes'] ?? '');
    
    // Pesos dos 8 critérios
    $totalFloat = (
        $notaInovacao * 0.20 + 
        $notaSustentabilidade * 0.15 +
        $notaEscalabilidade * 0.10 + 
        $notaImpacto * 0.15 +
        $notaViabilidade * 0.10 + 
        $notaEquipa * 0.10 +
        $notaMercado * 0.10 + 
        $notaProposta * 0.10
    );
    $total = (int)round($totalFloat);

    // Lógica de decisão recomendada
    $notas = [$notaInovacao, $notaViabilidade, $notaImpacto, $notaEquipa, $notaSustentabilidade, $notaEscalabilidade, $notaMercado, $notaProposta];
    $minNota = min($notas);

    if ($notaInovacao < 5 || $notaSustentabilidade < 4) {
        $decisaoSugerida = 'em_revisao'; // Veto automático
    } elseif ($totalFloat >= 7.0 && $minNota >= 4) {
        $decisaoSugerida = 'aprovado';
    } elseif ($totalFloat < 5.5) {
        $decisaoSugerida = 'rejeitado';
    } else {
        $decisaoSugerida = 'em_revisao';
    }

    $decisao = $_POST['decisao'] ?? $decisaoSugerida;
    // Forçar em_revisao se veto estiver ativo mas admin tentou aprovar
    if ($decisao === 'aprovado' && ($notaInovacao < 5 || $notaSustentabilidade < 4)) {
        $decisao = 'em_revisao';
        $_SESSION['flash_aviso'] = 'Aprovado impedido devido a critérios eliminatórios (Inovação < 5 ou Autossustentabilidade < 4). Definido como Em Revisão.';
    }

    $decisoesValidas = ['pendente','aprovado','rejeitado','em_revisao'];
    if (!in_array($decisao, $decisoesValidas)) $decisao = 'pendente';

    if ($idProjeto) {
        // Verificar se já avaliou
        $check = $mysqli->prepare("SELECT id FROM avaliacoes WHERE id_projeto=? AND id_avaliador=?");
        $check->bind_param('ii', $idProjeto, $idUsuario);
        $check->execute();
        $jaExiste = $check->get_result()->fetch_assoc();
        $check->close();

        if ($jaExiste) {
            $stmt = $mysqli->prepare("
                UPDATE avaliacoes SET
                    nota_inovacao=?, nota_viabilidade=?, nota_impacto=?, nota_equipa=?,
                    nota_sustentabilidade=?, nota_escalabilidade=?, nota_mercado=?, nota_proposta=?,
                    pontuacao_total=?, observacoes=?, decisao=?, avaliado_em=NOW()
                WHERE id_projeto=? AND id_avaliador=?
            ");
            $stmt->bind_param('iiiiiiiiissii',
                $notaInovacao, $notaViabilidade, $notaImpacto, $notaEquipa,
                $notaSustentabilidade, $notaEscalabilidade, $notaMercado, $notaProposta,
                $total, $observacoes, $decisao, $idProjeto, $idUsuario
            );
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO avaliacoes
                    (id_projeto, id_avaliador, nota_inovacao, nota_viabilidade, nota_impacto,
                     nota_equipa, nota_sustentabilidade, nota_escalabilidade, nota_mercado, nota_proposta,
                     pontuacao_total, observacoes, decisao)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('iiiiiiiiiiiss',
                $idProjeto, $idUsuario, $notaInovacao, $notaViabilidade,
                $notaImpacto, $notaEquipa, $notaSustentabilidade, $notaEscalabilidade,
                $notaMercado, $notaProposta, $total, $observacoes, $decisao
            );
        }
        $stmt->execute();
        $stmt->close();

        // Se decisão = aprovado/rejeitado, actualizar estado do projecto
        $novoEstadoProj = null;
        if ($decisao === 'aprovado') {
            $novoEstadoProj = 'aprovado';
        } elseif ($decisao === 'rejeitado') {
            $novoEstadoProj = 'rejeitado';
        } elseif ($decisao === 'em_revisao') {
            $novoEstadoProj = 'em_avaliacao'; // Volta para avaliação se for devolvido para revisão
        }

        if ($novoEstadoProj) {
            // Buscar estado anterior para o histórico
            $stmtEst = $mysqli->prepare("SELECT estado FROM projetos WHERE id = ?");
            $stmtEst->bind_param('i', $idProjeto);
            $stmtEst->execute();
            $projData = $stmtEst->get_result()->fetch_assoc();
            $stmtEst->close();
            $estadoAnterior = $projData ? $projData['estado'] : '';

            if ($estadoAnterior !== $novoEstadoProj) {
                $stmtUp = $mysqli->prepare("UPDATE projetos SET estado=? WHERE id=?");
                $stmtUp->bind_param('si', $novoEstadoProj, $idProjeto);
                $stmtUp->execute();
                $stmtUp->close();

                $stmtLog = $mysqli->prepare("INSERT INTO historico_estados (id_projeto, estado_anterior, estado_novo, id_usuario, motivo) VALUES (?, ?, ?, ?, ?)");
                $motivoLog = "Actualizado via avaliação formal. Decisão: " . strtoupper($decisao);
                $stmtLog->bind_param('issis', $idProjeto, $estadoAnterior, $novoEstadoProj, $idUsuario, $motivoLog);
                $stmtLog->execute();
                $stmtLog->close();
            }
        }

        $_SESSION['flash_ok'] = 'Avaliação guardada com sucesso.';

        // Notificar o dono do projeto
        $sqlDono = "SELECT criado_por FROM projetos WHERE id = ?";
        $stD = $mysqli->prepare($sqlDono);
        $stD->bind_param('i', $idProjeto);
        $stD->execute();
        $dono = $stD->get_result()->fetch_assoc();
        $stD->close();

        if ($dono) {
            $msgAval = "O teu projeto foi avaliado. Decisão: " . strtoupper($decisao);
            enviarNotificacao($dono['criado_por'], "Resultado da Avaliação", $msgAval, $decisao === 'aprovado' ? 'sucesso' : 'info');
        }
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: gerir_equipa (Adicionar/Remover)
   ════════════════════════════════════════════════ */
if (in_array($action, ['adicionar_membro', 'remover_membro'])) {
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);
    
    // Verificar se o usuário é admin ou o dono do projeto
    $check = $mysqli->prepare("SELECT criado_por FROM projetos WHERE id = ?");
    $check->bind_param('i', $idProjeto);
    $check->execute();
    $owner = $check->get_result()->fetch_assoc();
    
    if (!$owner || ($perfil === 'utilizador' && $owner['criado_por'] !== $idUsuario)) {
        $_SESSION['flash_erro'] = "Permissão negada.";
    } elseif ($action === 'adicionar_membro') {
        $emailMembro = trim($_POST['email'] ?? '');
        $papel       = $_POST['papel'] ?? 'Membro';

        $su = $mysqli->prepare("SELECT id FROM usuarios WHERE email = ?");
        $su->bind_param('s', $emailMembro);
        $su->execute();
        $userMembro = $su->get_result()->fetch_assoc();

        if (!$userMembro) {
            $_SESSION['flash_erro'] = "Utilizador com e-mail $emailMembro não encontrado.";
        } else {
            $stmt = $mysqli->prepare("INSERT IGNORE INTO membros_projeto (id_projeto, id_usuario, papel) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $idProjeto, $userMembro['id'], $papel);
            if ($stmt->execute()) {
                $_SESSION['flash_ok'] = "Membro adicionado com sucesso!";
                enviarNotificacao($userMembro['id'], "Convite de startup", "Foste adicionado ao projeto ID #$idProjeto como $papel.", 'info');
            }
        }
    } elseif ($action === 'remover_membro') {
        $idMembro = (int)($_POST['id_usuario_remover'] ?? 0);
        if ($idMembro === $owner['criado_por']) {
             $_SESSION['flash_erro'] = "O líder do projecto não pode ser removido.";
        } else {
            $stmt = $mysqli->prepare("DELETE FROM membros_projeto WHERE id_projeto = ? AND id_usuario = ?");
            $stmt->bind_param('ii', $idProjeto, $idMembro);
            $stmt->execute();
            $_SESSION['flash_ok'] = "Membro removido da equipa.";
        }
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: upload_documento
   ════════════════════════════════════════════════ */
if ($action === 'upload_documento') {
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);
    $titulo    = trim($_POST['titulo'] ?? 'Documento sem título');
    $tipo      = $_POST['tipo'] ?? 'Outro';

    if ($idProjeto && isset($_FILES['ficheiro']) && $_FILES['ficheiro']['error'] === UPLOAD_ERR_OK) {
        // ── Validações de segurança ─────────────────────────
        $maxBytes    = 10 * 1024 * 1024; // 10 MB
        $extsBranch  = ['pdf','docx','doc','xlsx','xls','pptx','ppt','zip','png','jpg','jpeg','gif','webp','txt','csv'];
        $mimesBranch = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip', 'application/x-zip-compressed',
            'image/png', 'image/jpeg', 'image/gif', 'image/webp',
            'text/plain', 'text/csv',
        ];

        $fileSize = $_FILES['ficheiro']['size'];
        $tmpPath  = $_FILES['ficheiro']['tmp_name'];
        $ext      = strtolower(pathinfo($_FILES['ficheiro']['name'], PATHINFO_EXTENSION));

        if ($fileSize > $maxBytes) {
            $_SESSION['flash_erro'] = 'Ficheiro demasiado grande. Limite máximo: 10 MB.';
        } elseif (!in_array($ext, $extsBranch)) {
            $_SESSION['flash_erro'] = 'Tipo de ficheiro não permitido. Extensões aceites: PDF, Word, Excel, PowerPoint, ZIP, imagem ou texto.';
        } else {
            // Verificar MIME real (não confia só no nome do ficheiro)
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = finfo_file($finfo, $tmpPath);
            finfo_close($finfo);

            if (!in_array($mimeReal, $mimesBranch)) {
                $_SESSION['flash_erro'] = 'O conteúdo do ficheiro não corresponde ao tipo declarado. Upload rejeitado por segurança.';
            } else {
                $novoNome = "doc_{$idProjeto}_" . time() . '_' . bin2hex(random_bytes(4)) . ".{$ext}";
                $folder   = __DIR__ . '/../../uploads/projetos/';
                if (!is_dir($folder)) mkdir($folder, 0755, true);

                if (move_uploaded_file($tmpPath, $folder . $novoNome)) {
                    $path = 'uploads/projetos/' . $novoNome;
                    $stmt = $mysqli->prepare("INSERT INTO ficheiros_projeto (id_projeto, titulo, tipo, path, id_usuario_up) VALUES (?,?,?,?,?)");
                    $stmt->bind_param('isssi', $idProjeto, $titulo, $tipo, $path, $idUsuario);
                    $stmt->execute();
                    $_SESSION['flash_ok'] = "Documento carregado! ({$ext}, " . round($fileSize/1024) . " KB)";
                } else {
                    $_SESSION['flash_erro'] = 'Falha ao mover o ficheiro para o servidor.';
                }
            }
        }
    } else {
        $_SESSION['flash_erro'] = 'Seleccione um ficheiro válido.';
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'mudar_fase') {
    $idProjeto = (int)$_POST['id_projeto'];
    $fase      = $_POST['fase'];
    if ($idProjeto > 0 && in_array($fase, ['ideacao','validacao','mvp','tracao','mercado'])) {
        $stmt = $mysqli->prepare("UPDATE projetos SET fase = ? WHERE id = ?");
        $stmt->bind_param('si', $fase, $idProjeto);
        if ($stmt->execute()) {
            // Atribuir 50 pontos por avanço de fase — prepared statement (sem interpolação)
            $stmtPts = $mysqli->prepare("UPDATE projetos SET pontos = pontos + 50 WHERE id = ?");
            $stmtPts->bind_param('i', $idProjeto);
            $stmtPts->execute();
            $stmtPts->close();
            
            $_SESSION['flash_ok'] = "Maturidade da startup atualizada para " . strtoupper($fase) . " e +50 SP atribuídos!";
            
            // NOTIFICAÇÃO AUTOMÁTICA
            $sqlDono = "SELECT criado_por, titulo FROM projetos WHERE id = ?";
            $stD = $mysqli->prepare($sqlDono);
            $stD->bind_param('i', $idProjeto);
            $stD->execute();
            $proj = $stD->get_result()->fetch_assoc();
            
            if ($proj) {
                $tituloNotif = "🚀 Evolução: " . htmlspecialchars($proj['titulo']);
                $msgNotif = "Parabéns! A tua startup avançou para a fase: " . strtoupper($fase) . ". Continua o excelente trabalho!";
                enviarNotificacao($proj['criado_por'], $tituloNotif, $msgNotif, 'sucesso');
            }
        }
        $stmt->close();
    }
    header("Location: /incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=$idProjeto");
    exit;
}

// Removido duplicado de mudar_estado que estava aqui


if ($action === 'gerar_analise_ia') {
    $idProjeto = (int)$_POST['id_projeto'];
    
    // 1. Buscar dados do projeto
    $stmt = $mysqli->prepare("SELECT titulo, descricao, problema, solucao FROM projetos WHERE id = ?");
    $stmt->bind_param('i', $idProjeto);
    $stmt->execute();
    $proj = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($proj) {
        $feedback = \App\Utils\GeminiAI::analisarProjeto(
            $proj['titulo'], 
            $proj['descricao'], 
            $proj['problema'], 
            $proj['solucao']
        );

        // 2. Guardar na BD
        $stmt = $mysqli->prepare("UPDATE projetos SET feedback_ia = ? WHERE id = ?");
        $stmt->bind_param('si', $feedback, $idProjeto);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_ok'] = "Análise da Inteligência Artificial concluída!";
    }
    header("Location: /incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=$idProjeto");
    exit;
}

if ($action === 'toggle_destaque') {
    $idProjeto = (int)$_POST['id_projeto'];
    if ($idProjeto > 0) {
        $stmt = $mysqli->prepare("UPDATE projetos SET destaque_publico = 1 - destaque_publico WHERE id = ?");
        $stmt->bind_param('i', $idProjeto);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_ok'] = "Visibilidade na Vitrine atualizada!";
    }
    // Usar $idProjeto já validado como int — safe redirect
    header("Location: /incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=" . $idProjeto);
    exit;
}

if ($action === 'atualizar_estado_tarefa') {
    $idTarefa = (int)$_POST['id_tarefa'];
    $novoStatus = $_POST['status'] ?? 'concluida';
    $evidenciaNota = trim($_POST['evidencia_nota'] ?? '');

    if ($idTarefa > 0) {
        // Garantir que a tarefa pertence ao projeto do estudante/membro
        $checkPerm = $mysqli->prepare("
            SELECT t.id, t.id_projeto 
            FROM tarefas t 
            JOIN projetos p ON p.id = t.id_projeto 
            LEFT JOIN membros_projeto mp ON mp.id_projeto = p.id AND mp.id_usuario = ?
            WHERE t.id = ? AND (p.criado_por = ? OR mp.id_usuario IS NOT NULL)
            LIMIT 1
        ");
        $checkPerm->bind_param('iii', $idUsuario, $idTarefa, $idUsuario);
        $checkPerm->execute();
        $tarefaValida = $checkPerm->get_result()->fetch_assoc();
        $checkPerm->close();

        if ($tarefaValida) {
            if ($novoStatus === 'concluida') {
                $evidenciaPath = null;
                // Processar upload de arquivo de evidência
                if (isset($_FILES['evidencia_ficheiro']) && $_FILES['evidencia_ficheiro']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['evidencia_ficheiro'];
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $novoNome = "evid_" . $idTarefa . "_" . time() . "_" . uniqid() . "." . $ext;
                    $folder = __DIR__ . "/../../uploads/evidencias/";
                    if (!is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }
                    if (move_uploaded_file($file['tmp_name'], $folder . $novoNome)) {
                        $evidenciaPath = "uploads/evidencias/" . $novoNome;
                    }
                }

                $stmt = $mysqli->prepare("
                    UPDATE tarefas 
                    SET status = 'concluida', 
                        evidencia_path = COALESCE(?, evidencia_path), 
                        evidencia_nota = ?, 
                        validada_mentor = 0 
                    WHERE id = ?
                ");
                $stmt->bind_param('ssi', $evidenciaPath, $evidenciaNota, $idTarefa);
                if ($stmt->execute()) {
                    $_SESSION['flash_ok'] = "Evidência submetida com sucesso! A aguardar validação do mentor.";
                } else {
                    $_SESSION['flash_erro'] = "Erro ao enviar evidência.";
                }
                $stmt->close();
            } else {
                // Atualizar apenas para 'em_progresso' ou 'pendente'
                $stmt = $mysqli->prepare("UPDATE tarefas SET status = ?, validada_mentor = 0 WHERE id = ?");
                $stmt->bind_param('si', $novoStatus, $idTarefa);
                if ($stmt->execute()) {
                    $_SESSION['flash_ok'] = "Estado da tarefa atualizado para " . strtoupper(str_replace('_', ' ', $novoStatus)) . ".";
                }
                $stmt->close();
            }
        } else {
            $_SESSION['flash_erro'] = "Permissão negada.";
        }
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'validar_tarefa_mentor') {
    $idTarefa = (int)$_POST['id_tarefa'];

    if ($idTarefa > 0 && in_array($perfil, ['mentor', 'admin', 'superadmin'])) {
        $chk = $mysqli->prepare("SELECT id_projeto, validada_mentor, titulo FROM tarefas WHERE id = ?");
        $chk->bind_param('i', $idTarefa);
        $chk->execute();
        $tarefa = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($tarefa) {
            if ($tarefa['validada_mentor'] == 0) {
                $stmt = $mysqli->prepare("UPDATE tarefas SET status = 'concluida', validada_mentor = 1 WHERE id = ?");
                $stmt->bind_param('i', $idTarefa);
                if ($stmt->execute()) {
                    // Atribuir 10 pontos de inovação — prepared statement (sem interpolação)
                    $idProj = (int)$tarefa['id_projeto'];
                    $stmtPts = $mysqli->prepare("UPDATE projetos SET pontos = pontos + 10 WHERE id = ?");
                    $stmtPts->bind_param('i', $idProj);
                    $stmtPts->execute();
                    $stmtPts->close();

                    // Notificar o dono do projeto
                    $sqlDono = "SELECT criado_por, titulo FROM projetos WHERE id = ?";
                    $stD = $mysqli->prepare($sqlDono);
                    $stD->bind_param('i', $idProj);
                    $stD->execute();
                    $projInfo = $stD->get_result()->fetch_assoc();
                    $stD->close();

                    if ($projInfo) {
                        $msg = "A evidência para a meta \"" . htmlspecialchars($tarefa['titulo']) . "\" foi validada pelo mentor. +10 SP atribuídos à startup!";
                        enviarNotificacao($projInfo['criado_por'], "Meta Validada! 🎯", $msg, 'sucesso');
                    }

                    $_SESSION['flash_ok'] = "Meta validada com sucesso! +10 SP atribuídos à startup.";
                } else {
                    $_SESSION['flash_erro'] = "Erro ao validar meta.";
                }
                $stmt->close();
            } else {
                $_SESSION['flash_ok'] = "Esta meta já tinha sido validada anteriormente.";
            }
        }
    } else {
        $_SESSION['flash_erro'] = "Permissão negada.";
    }
    header("Location: $redirect");
    exit;
}

if ($action === 'enviar_mensagem') {
    $idProjeto = (int)$_POST['id_projeto'];
    $mensagem  = trim($_POST['mensagem']);

    if ($idProjeto > 0 && !empty($mensagem)) {
        $stmt = $mysqli->prepare("INSERT INTO mensagens (id_projeto, id_usuario, mensagem) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $idProjeto, $idUsuario, $mensagem);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: $redirect");
    exit;
}

// Se chegou aqui sem action válida
header("Location: $redirect");
exit;

