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
   ACÇÃO: atribuir_avaliador
   Avaliador pega autonomamente no caso (máximo 3 avaliadores por projeto)
════════════════════════════════════════════════ */
if ($action === 'atribuir_avaliador') {
    obrigarPerfil(['admin', 'superadmin', 'mentor']);
    $idProjeto = (int)($_POST['id_projeto'] ?? 0);

    if ($idProjeto <= 0) {
        $_SESSION['flash_erro'] = 'Projecto inválido.';
        header('Location: ' . $redirect);
        exit;
    }

    // Verificar se o utilizador é o autor do próprio projecto (conflito de interesses)
    $stmtChk = $mysqli->prepare("SELECT criado_por FROM projetos WHERE id = ?");
    $stmtChk->bind_param('i', $idProjeto);
    $stmtChk->execute();
    $pData = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();

    if ($pData && (int)$pData['criado_por'] === $idUsuario) {
        $_SESSION['flash_erro'] = 'Não pode avaliar o seu próprio projecto (conflito de interesses).';
        header('Location: ' . $redirect);
        exit;
    }

    // Transação e Lock de Linha (FOR UPDATE) contra Concorrência
    $mysqli->begin_transaction();
    try {
        $stmtCount = $mysqli->prepare("SELECT COUNT(*) n FROM avaliacoes_atribuicao WHERE projeto_id = ? FOR UPDATE");
        $stmtCount->bind_param('i', $idProjeto);
        $stmtCount->execute();
        $count = (int)$stmtCount->get_result()->fetch_assoc()['n'];
        $stmtCount->close();

        if ($count >= 3) {
            $mysqli->rollback();
            $_SESSION['flash_erro'] = 'Este projecto já atingiu o limite de 3 avaliadores atribuídos.';
        } else {
            $stmtIns = $mysqli->prepare("INSERT IGNORE INTO avaliacoes_atribuicao (projeto_id, avaliador_id, estado) VALUES (?, ?, 'atribuido')");
            $stmtIns->bind_param('ii', $idProjeto, $idUsuario);
            $stmtIns->execute();
            $inserted = $stmtIns->affected_rows;
            $stmtIns->close();

            if ($inserted > 0) {
                $novoCount = $count + 1;
                $novoEstadoAval = ($novoCount == 3) ? 'em_avaliacao' : 'em_avaliacao';
                $stmtUp = $mysqli->prepare("UPDATE projetos SET estado_avaliacao = ?, estado = IF(estado = 'submetido', 'em_avaliacao', estado) WHERE id = ?");
                $stmtUp->bind_param('si', $novoEstadoAval, $idProjeto);
                $stmtUp->execute();
                $stmtUp->close();

                $mysqli->commit();
                $_SESSION['flash_ok'] = 'Projecto atribuído com sucesso à sua fila de avaliação!';
            } else {
                $mysqli->rollback();
                $_SESSION['flash_aviso'] = 'Já se encontra atribuído a este projecto.';
            }
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['flash_erro'] = 'Erro ao atribuir projecto: ' . $e->getMessage();
    }

    header('Location: ' . $redirect);
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: libertar_atribuicao
   Admin/Superadmin cancela atribuição inativa
════════════════════════════════════════════════ */
if ($action === 'libertar_atribuicao') {
    obrigarPerfil(['admin', 'superadmin']);
    $idAtribuicao = (int)($_POST['id_atribuicao'] ?? 0);

    if ($idAtribuicao > 0) {
        $stmtDel = $mysqli->prepare("DELETE FROM avaliacoes_atribuicao WHERE id = ? AND estado = 'atribuido'");
        $stmtDel->bind_param('i', $idAtribuicao);
        $stmtDel->execute();
        $stmtDel->close();
        $_SESSION['flash_ok'] = 'Atribuição libertada com sucesso. A vaga voltou à fila aberta.';
    }

    header('Location: ' . $redirect);
    exit;
}

/* ════════════════════════════════════════════════
   FUNÇÃO: consolidarAvaliacao
   Consolida automaticamente a decisão do projeto após as 3 avaliações concluídas
════════════════════════════════════════════════ */
function consolidarAvaliacao($idProjeto, $mysqli) {
    // Buscar todas as avaliações concluídas
    $stmt = $mysqli->prepare("
        SELECT a.* 
        FROM avaliacoes a
        JOIN avaliacoes_atribuicao aa ON (aa.id = a.atribuicao_id OR (aa.projeto_id = a.id_projeto AND aa.avaliador_id = a.id_avaliador))
        WHERE a.id_projeto = ? AND aa.estado = 'concluido'
    ");
    $stmt->bind_param('i', $idProjeto);
    $stmt->execute();
    $avaliacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($avaliacoes) < 3) {
        return false; // Ainda não se atingiram as 3 avaliações completas
    }

    $notasFinais = [];
    $vetos = 0;

    foreach ($avaliacoes as $av) {
        $mediaIndiv = (
            (int)$av['nota_inovacao'] + (int)$av['nota_viabilidade'] + (int)$av['nota_impacto'] + (int)$av['nota_equipa'] +
            (int)$av['nota_sustentabilidade'] + (int)$av['nota_escalabilidade'] + (int)$av['nota_mercado'] + (int)$av['nota_proposta']
        ) / 8.0;
        $notasFinais[] = $mediaIndiv;

        if ((int)$av['nota_inovacao'] < 5 || (int)$av['nota_sustentabilidade'] < 4) {
            $vetos++;
        }
    }

    $mediaConsolidada = round(array_sum($notasFinais) / count($notasFinais), 2);

    if ($vetos >= 2) {
        $decisao = 'rejeitado'; // Maioria vetou
    } elseif ($mediaConsolidada >= 7.0 && $vetos == 0) {
        $decisao = 'aprovado';
    } elseif ($mediaConsolidada >= 4.0) {
        $decisao = 'em_revisao';
    } else {
        $decisao = 'rejeitado';
    }

    // Buscar estado anterior e autor do projeto
    $stmtEst = $mysqli->prepare("SELECT estado, criado_por, titulo FROM projetos WHERE id = ?");
    $stmtEst->bind_param('i', $idProjeto);
    $stmtEst->execute();
    $projData = $stmtEst->get_result()->fetch_assoc();
    $stmtEst->close();

    $estadoAnterior = $projData ? $projData['estado'] : '';
    $idAutor = $projData ? (int)$projData['criado_por'] : 0;
    $tituloProj = $projData ? $projData['titulo'] : 'Projecto';

    // Atualizar o projeto com o estado final e a nota consolidada
    $stmtUp = $mysqli->prepare("UPDATE projetos SET estado = ?, media_final = ?, estado_avaliacao = 'avaliado' WHERE id = ?");
    $stmtUp->bind_param('sdi', $decisao, $mediaConsolidada, $idProjeto);
    $stmtUp->execute();
    $stmtUp->close();

    // Registar no histórico
    $stmtLog = $mysqli->prepare("INSERT INTO historico_estados (id_projeto, estado_anterior, estado_novo, id_usuario, motivo) VALUES (?, ?, ?, 1, ?)");
    $motivoLog = "Consolidação automática de 3 avaliações. Média Consolidada: " . number_format($mediaConsolidada, 2) . "/10. Vetos: $vetos. Decisão: " . strtoupper($decisao);
    $stmtLog->bind_param('isss', $idProjeto, $estadoAnterior, $decisao, $motivoLog);
    $stmtLog->execute();
    $stmtLog->close();

    // Notificar o estudante por e-mail e sistema
    if ($idAutor > 0) {
        $msgNotif = "A avaliação do seu projecto '{$tituloProj}' foi concluída e consolidada.\n\nResultado Final: " . strtoupper(str_replace('_', ' ', $decisao)) . "\nMédia Consolidada: " . number_format($mediaConsolidada, 2) . "/10.\n\nAceda ao painel para consultar a evolução do seu projecto.";
        $tipoNotif = ($decisao === 'aprovado') ? 'sucesso' : (($decisao === 'em_revisao') ? 'warning' : 'erro');
        enviarNotificacao($idAutor, "Avaliação Concluída: " . ucfirst($decisao), $msgNotif, $tipoNotif);
    }

    return true;
}

/* ════════════════════════════════════════════════
   ACÇÃO: avaliar
   Grava a avaliação individual e desencadeia consolidação ao atingir 3 submissões
════════════════════════════════════════════════ */
if ($action === 'avaliar') {
    obrigarPerfil(['admin','superadmin','mentor']);
    
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

    if ($idProjeto <= 0) {
        $_SESSION['flash_erro'] = 'Projecto inválido.';
        header('Location: ' . $redirect);
        exit;
    }

    // Buscar atribuição do avaliador
    $stmtAtrib = $mysqli->prepare("SELECT id FROM avaliacoes_atribuicao WHERE projeto_id = ? AND avaliador_id = ?");
    $stmtAtrib->bind_param('ii', $idProjeto, $idUsuario);
    $stmtAtrib->execute();
    $atribRow = $stmtAtrib->get_result()->fetch_assoc();
    $stmtAtrib->close();

    $atribuicaoId = $atribRow ? (int)$atribRow['id'] : null;

    // Se ainda não estava atribuído mas há vaga (<3), atribuir automaticamente ao submeter
    if (!$atribuicaoId) {
        $stmtC = $mysqli->prepare("SELECT COUNT(*) n FROM avaliacoes_atribuicao WHERE projeto_id = ?");
        $stmtC->bind_param('i', $idProjeto);
        $stmtC->execute();
        $cntAtrib = (int)$stmtC->get_result()->fetch_assoc()['n'];
        $stmtC->close();

        if ($cntAtrib >= 3) {
            $_SESSION['flash_erro'] = 'Este projecto já possui 3 avaliadores atribuídos.';
            header('Location: ' . $redirect);
            exit;
        }

        $stmtInsA = $mysqli->prepare("INSERT INTO avaliacoes_atribuicao (projeto_id, avaliador_id, estado) VALUES (?, ?, 'atribuido')");
        $stmtInsA->bind_param('ii', $idProjeto, $idUsuario);
        $stmtInsA->execute();
        $atribuicaoId = $stmtInsA->insert_id;
        $stmtInsA->close();
    }

    // Média individual
    $totalFloat = (
        $notaInovacao + $notaViabilidade + $notaImpacto + $notaEquipa +
        $notaSustentabilidade + $notaEscalabilidade + $notaMercado + $notaProposta
    ) / 8.0;
    $total = (int)round($totalFloat);

    $decisaoIndiv = ($notaInovacao < 5 || $notaSustentabilidade < 4) ? 'em_revisao' : (($totalFloat >= 7.0) ? 'aprovado' : 'rejeitado');

    // Verificar se já tem registo na tabela avaliacoes
    $check = $mysqli->prepare("SELECT id FROM avaliacoes WHERE id_projeto=? AND id_avaliador=?");
    $check->bind_param('ii', $idProjeto, $idUsuario);
    $check->execute();
    $jaExiste = $check->get_result()->fetch_assoc();
    $check->close();

    if ($jaExiste) {
        $stmt = $mysqli->prepare("
            UPDATE avaliacoes SET
                atribuicao_id=?, nota_inovacao=?, nota_viabilidade=?, nota_impacto=?, nota_equipa=?,
                nota_sustentabilidade=?, nota_escalabilidade=?, nota_mercado=?, nota_proposta=?,
                pontuacao_total=?, observacoes=?, decisao=?, avaliado_em=NOW()
            WHERE id_projeto=? AND id_avaliador=?
        ");
        $stmt->bind_param('iiiiiiiiiissii',
            $atribuicaoId, $notaInovacao, $notaViabilidade, $notaImpacto, $notaEquipa,
            $notaSustentabilidade, $notaEscalabilidade, $notaMercado, $notaProposta,
            $total, $observacoes, $decisaoIndiv, $idProjeto, $idUsuario
        );
    } else {
        $stmt = $mysqli->prepare("
            INSERT INTO avaliacoes
                (atribuicao_id, id_projeto, id_avaliador, nota_inovacao, nota_viabilidade, nota_impacto,
                 nota_equipa, nota_sustentabilidade, nota_escalabilidade, nota_mercado, nota_proposta,
                 pontuacao_total, observacoes, decisao)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param('iiiiiiiiiiiiss',
            $atribuicaoId, $idProjeto, $idUsuario, $notaInovacao, $notaViabilidade,
            $notaImpacto, $notaEquipa, $notaSustentabilidade, $notaEscalabilidade,
            $notaMercado, $notaProposta, $total, $observacoes, $decisaoIndiv
        );
    }
    $stmt->execute();
    $stmt->close();

    // Marcar a atribuição como concluída
    if ($atribuicaoId) {
        $stmtConcl = $mysqli->prepare("UPDATE avaliacoes_atribuicao SET estado = 'concluido', data_conclusao = NOW() WHERE id = ?");
        $stmtConcl->bind_param('i', $atribuicaoId);
        $stmtConcl->execute();
        $stmtConcl->close();
    }

    // Verificar se as 3 avaliações foram concluídas para acionar o motor de consolidação
    $stmtComp = $mysqli->prepare("SELECT COUNT(*) n FROM avaliacoes_atribuicao WHERE projeto_id = ? AND estado = 'concluido'");
    $stmtComp->bind_param('i', $idProjeto);
    $stmtComp->execute();
    $numConcluidas = (int)$stmtComp->get_result()->fetch_assoc()['n'];
    $stmtComp->close();

    if ($numConcluidas >= 3) {
        consolidarAvaliacao($idProjeto, $mysqli);
        $_SESSION['flash_ok'] = 'A sua avaliação foi registada! Como foi a 3.ª avaliação concluída, o projecto foi automaticamente consolidado e a decisão final foi emitida.';
    } else {
        $faltam = 3 - $numConcluidas;
        $_SESSION['flash_ok'] = "A sua avaliação foi registada com sucesso! Faltam {$faltam} avaliação(ões) para o sistema consolidar a decisão final.";
    }

    header('Location: ' . $redirect);
    exit;
}

        $_SESSION['flash_ok'] = 'Avaliação guardada com sucesso.';

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
    $redirectPost = $_POST['redirect'] ?? '';
    
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
            $stD->close();
            
            if ($proj) {
                $tituloNotif = "🚀 Evolução: " . htmlspecialchars($proj['titulo']);
                $msgNotif = "Parabéns! A tua startup avançou para a fase: " . strtoupper($fase) . ". Continua o excelente trabalho!";
                enviarNotificacao($proj['criado_por'], $tituloNotif, $msgNotif, 'sucesso');
            }

            // Inicializar automaticamente as metas da nova fase
            $stmtMetas = $mysqli->prepare("SELECT id FROM metas_padrao WHERE fase = ? AND activo = 1 ORDER BY numero");
            $stmtMetas->bind_param('s', $fase);
            $stmtMetas->execute();
            $metasNovas = $stmtMetas->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtMetas->close();
            
            $stmtInsert = $mysqli->prepare("INSERT IGNORE INTO metas_projeto (id_projeto, id_meta_padrao, estado) VALUES (?, ?, 'inactiva')");
            foreach ($metasNovas as $mn) {
                $stmtInsert->bind_param('ii', $idProjeto, $mn['id']);
                $stmtInsert->execute();
            }
            $stmtInsert->close();
        }
        $stmt->close();
    }
    
    $finalRedirect = (!empty($redirectPost) && str_starts_with($redirectPost, '/incubadora_ispsn/')) ? $redirectPost : "/incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=$idProjeto";
    header("Location: $finalRedirect");
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

