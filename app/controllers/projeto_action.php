<?php
// app/controllers/projeto_action.php
// Controlador central de acções sobre projectos
// Chamado via POST de qualquer ecrã de projecto

require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../utils/GeminiAI.php';
obrigarLogin();

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
        // id_responsavel = o próprio criador por defeito
        // Todos os projetos novos iniciam como 'submetido'
        $stmt = $mysqli->prepare("
            INSERT INTO projetos
                (titulo, tipo, descricao, problema, solucao, area_tematica, estado, id_responsavel, criado_por)
            VALUES (?, ?, ?, ?, ?, ?, 'submetido', ?, ?)
        ");
        $stmt->bind_param('ssssssii',
            $titulo, $tipo, $descricao, $problema, $solucao,
            $area_tematica, $idUsuario, $idUsuario
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
        $stmt = $mysqli->prepare("UPDATE projetos SET estado=?, motivo_rejeicao=? WHERE id=?");
        $stmt->bind_param('ssi', $novoEstado, $motivo, $idProjeto);
        $stmt->execute();

        // Notificar o criador do projecto
        $rp = $mysqli->prepare("SELECT criado_por, titulo FROM projetos WHERE id=?");
        $rp->bind_param('i', $idProjeto);
        $rp->execute();
        $proj = $rp->get_result()->fetch_assoc();
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
            
            // Notificar o criador do projecto
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
    $idProjeto      = (int)($_POST['id_projeto'] ?? 0);
    $notaInovacao   = min(10, max(0, (int)($_POST['nota_inovacao']    ?? 0)));
    $notaViabilidade= min(10, max(0, (int)($_POST['nota_viabilidade'] ?? 0)));
    $notaImpacto    = min(10, max(0, (int)($_POST['nota_impacto']     ?? 0)));
    $notaEquipa     = min(10, max(0, (int)($_POST['nota_equipa']      ?? 0)));
    $observacoes    = trim($_POST['observacoes'] ?? '');
    $decisao        = $_POST['decisao'] ?? 'pendente';
    $total          = (int)round(($notaInovacao + $notaViabilidade + $notaImpacto + $notaEquipa) / 4);

    $decisoesValidas = ['pendente','aprovado','rejeitado','em_revisao'];
    if (!in_array($decisao, $decisoesValidas)) $decisao = 'pendente';

    if ($idProjeto) {
        // Verificar se já avaliou — se sim, actualiza; se não, insere
        $check = $mysqli->prepare("SELECT id FROM avaliacoes WHERE id_projeto=? AND id_avaliador=?");
        $check->bind_param('ii', $idProjeto, $idUsuario);
        $check->execute();
        $jaExiste = $check->get_result()->fetch_assoc();

        if ($jaExiste) {
            $stmt = $mysqli->prepare("
                UPDATE avaliacoes SET
                    nota_inovacao=?, nota_viabilidade=?, nota_impacto=?, nota_equipa=?,
                    pontuacao_total=?, observacoes=?, decisao=?, avaliado_em=NOW()
                WHERE id_projeto=? AND id_avaliador=?
            ");
            $stmt->bind_param('iiiiiissii',
                $notaInovacao, $notaViabilidade, $notaImpacto, $notaEquipa,
                $total, $observacoes, $decisao, $idProjeto, $idUsuario
            );
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO avaliacoes
                    (id_projeto, id_avaliador, nota_inovacao, nota_viabilidade, nota_impacto,
                     nota_equipa, pontuacao_total, observacoes, decisao)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('iiiiiiiss',
                $idProjeto, $idUsuario, $notaInovacao, $notaViabilidade,
                $notaImpacto, $notaEquipa, $total, $observacoes, $decisao
            );
        }
        $stmt->execute();

        // Se decisão = aprovado/rejeitado, actualizar estado do projecto conforme o novo fluxo
        if ($decisao === 'aprovado') {
            $mysqli->query("UPDATE projetos SET estado='aprovado' WHERE id=$idProjeto");
        } elseif ($decisao === 'rejeitado') {
            $mysqli->query("UPDATE projetos SET estado='rejeitado' WHERE id=$idProjeto");
        }

        $_SESSION['flash_ok'] = 'Avaliação guardada com sucesso.';

        // Notificar o dono do projeto
        $sqlDono = "SELECT criado_por FROM projetos WHERE id = ?";
        $stD = $mysqli->prepare($sqlDono);
        $stD->bind_param('i', $idProjeto);
        $stD->execute();
        $dono = $stD->get_result()->fetch_assoc();
        if ($dono) {
            $msgAval = "O teu projeto foi avaliado. Resultado: " . strtoupper($decisao);
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
        $ext = pathinfo($_FILES['ficheiro']['name'], PATHINFO_EXTENSION);
        $novoNome = "doc_" . $idProjeto . "_" . time() . "_" . uniqid() . "." . $ext;
        $folder = __DIR__ . "/../../uploads/projetos/";
        if (!is_dir($folder)) mkdir($folder, 0777, true);

        if (move_uploaded_file($_FILES['ficheiro']['tmp_name'], $folder . $novoNome)) {
            $path = "uploads/projetos/" . $novoNome;
            $stmt = $mysqli->prepare("INSERT INTO ficheiros_projeto (id_projeto, titulo, tipo, path, id_usuario_up) VALUES (?,?,?,?,?)");
            $stmt->bind_param('isssi', $idProjeto, $titulo, $tipo, $path, $idUsuario);
            $stmt->execute();
            $_SESSION['flash_ok'] = "Documento carregado com sucesso!";
        } else {
            $_SESSION['flash_erro'] = "Falha ao mover o ficheiro para o servidor.";
        }
    } else {
         $_SESSION['flash_erro'] = "Seleccione um ficheiro válido.";
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
            // Atribuir 50 pontos por avanço de fase
            $mysqli->query("UPDATE projetos SET pontos = pontos + 50 WHERE id = $idProjeto");
            
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
    header("Location: /incubadora_ispsn/app/views/admin/projeto_detalhe.php?id=$idProjeto");
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
