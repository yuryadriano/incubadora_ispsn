<?php
// app/views/dashboard/mentor.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['mentor', 'admin', 'superadmin']);

$idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
$nome = $_SESSION['usuario_nome'] ?? 'Mentor';

// Lógica de salvar perfil de mentor (caso idMentor == 0)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'completar_perfil') {
    $esp  = trim($_POST['especialidade'] ?? '');
    $bio  = trim($_POST['bio'] ?? '');
    $link = trim($_POST['linkedin'] ?? '');

    if (!empty($esp)) {
        $stmt = $mysqli->prepare("INSERT INTO mentores (id_usuario, especialidade, bio, linkedin) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $idUsuario, $esp, $bio, $link);
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = "Perfil de mentor configurado com sucesso!";
        } else {
            $_SESSION['flash_erro'] = "Erro ao configurar perfil: " . $mysqli->error;
        }
        $stmt->close();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// 1. Buscar dados do mentor
$stmt = $mysqli->prepare("SELECT id, especialidade, bio, linkedin FROM mentores WHERE id_usuario = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$mentorInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

$idMentor = $mentorInfo['id'] ?? 0;

// 2. Buscar mentorias activas e estatísticas
$mentorias = [];
$tarefasPendentes = [];
$avaliacoesRecentes = [];
$totalHoras = 0;
$saudeProjetos = [];

if ($idMentor > 0) {
    // Mentorias
    $stmt = $mysqli->prepare("
        SELECT m.id as id_mentoria, m.estado, m.data_inicio, 
               p.id as id_projeto, p.titulo as projeto_nome, p.estado as projeto_estado, p.fase as projeto_fase,
               (SELECT COUNT(*) FROM sessoes_mentoria WHERE id_mentoria = m.id) as total_sessoes
         FROM mentorias m
         JOIN projetos p ON p.id = m.id_projeto
         WHERE m.id_mentor = ?
         ORDER BY m.estado ASC, m.criado_em DESC
    ");
    $stmt->bind_param('i', $idMentor);
    $stmt->execute();
    $mentorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Carregar saúde de todos os projetos em lote para evitar N+1 queries
    if (!empty($mentorias)) {
        $idsProjeto = array_column($mentorias, 'id_projeto');
        $placeholders = implode(',', array_fill(0, count($idsProjeto), '?'));
        $types = str_repeat('i', count($idsProjeto));
        
        $stmtSaude = $mysqli->prepare("
            SELECT id_projeto,
                   SUM(CASE WHEN status != 'concluida' AND data_limite < CURDATE() THEN 1 ELSE 0 END) as atrasadas,
                   SUM(CASE WHEN status != 'concluida' THEN 1 ELSE 0 END) as pendentes
            FROM tarefas
            WHERE id_projeto IN ($placeholders)
            GROUP BY id_projeto
        ");
        $stmtSaude->bind_param($types, ...$idsProjeto);
        $stmtSaude->execute();
        $resSaude = $stmtSaude->get_result();
        while ($row = $resSaude->fetch_assoc()) {
            $pid = (int)$row['id_projeto'];
            if ($row['atrasadas'] > 0) {
                $saudeProjetos[$pid] = 'critical';
            } elseif ($row['pendentes'] > 0) {
                $saudeProjetos[$pid] = 'warning';
            } else {
                $saudeProjetos[$pid] = 'good';
            }
        }
        $stmtSaude->close();
    }

    // Horas totais de consultoria
    $stmt = $mysqli->prepare("
        SELECT SUM(s.duracao_min) as total_min
        FROM sessoes_mentoria s
        JOIN mentorias m ON m.id = s.id_mentoria
        WHERE m.id_mentor = ?
    ");
    $stmt->bind_param('i', $idMentor);
    $stmt->execute();
    $horasData = $stmt->get_result()->fetch_assoc();
    $totalHoras = $horasData['total_min'] ? round($horasData['total_min'] / 60, 1) : 0;
    $stmt->close();

    // Tarefas Pendentes
    $stmt = $mysqli->prepare("
        SELECT t.*, p.titulo as projeto_nome
        FROM tarefas t
        JOIN projetos p ON p.id = t.id_projeto
        JOIN mentorias m ON m.id_projeto = p.id
        WHERE m.id_mentor = ? AND t.status = 'pendente'
        ORDER BY t.data_limite ASC LIMIT 10
    ");
    $stmt->bind_param('i', $idMentor);
    $stmt->execute();
    $tarefasPendentes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Próximas Reuniões
    $proximasReunioes = [];
    $stmt = $mysqli->prepare("
        SELECT r.*, p.titulo as projeto_nome
        FROM reunioes r
        JOIN projetos p ON p.id = r.id_projeto
        WHERE r.id_mentor = ? AND r.data_reuniao >= NOW() AND r.status = 'agendada'
        ORDER BY r.data_reuniao ASC
    ");
    $stmt->bind_param('i', $idMentor);
    $stmt->execute();
    $proximasReunioes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Metas pendentes de validação
    $metasAvaliar = [];
    $stmt = $mysqli->prepare("
        SELECT mp.*, mpd.titulo as meta_titulo, mpd.evidencia_tipo, mpd.evidencia_desc,
               p.titulo as projeto_nome
        FROM metas_projeto mp
        JOIN projetos p ON p.id = mp.id_projeto
        JOIN mentorias m ON m.id_projeto = p.id
        JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
        WHERE m.id_mentor = ? AND m.estado = 'activa' AND mp.estado = 'em_avaliacao'
        ORDER BY mp.evidencia_em ASC
    ");
    $stmt->bind_param('i', $idMentor);
    $stmt->execute();
    $metasAvaliar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// 3. Contadores rápidos
$activasCount = count(array_filter($mentorias, fn($m) => $m['estado'] === 'activa'));
$totalSessoes = array_sum(array_column($mentorias, 'total_sessoes'));
$totalTarefas = count($tarefasPendentes);

$tituloPagina = 'Painel do Mentor';
$paginaActiva = 'dashboard';

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

// Lógica de salvar sessão (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'registar_sessao') {
    $idMentoria = (int)$_POST['id_mentoria'];
    $dataSessao = $_POST['data_sessao'];
    $duracao    = (int)$_POST['duracao_min'];
    $topicos    = $_POST['topicos'];
    $passos     = $_POST['proximos_passos'];
    $avalEquipa = (int)$_POST['avaliacao_equipa'];

    if ($idMentoria > 0 && !empty($dataSessao)) {
        $stmt = $mysqli->prepare("
            INSERT INTO sessoes_mentoria (id_mentoria, data_sessao, duracao_min, topicos, proximos_passos, avaliacao_equipa)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isissi', $idMentoria, $dataSessao, $duracao, $topicos, $passos, $avalEquipa);
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = "Sessão registada com sucesso!";
        } else {
            $_SESSION['flash_erro'] = "Erro ao registar sessão: " . $mysqli->error;
        }
        $stmt->close();
    } else {
        $_SESSION['flash_erro'] = "Dados inválidos para o registo da sessão.";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH MESSAGES -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- TÍTULO -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-handshake me-2" style="color:var(--primary)"></i>
            Painel do Mentor
        </div>
        <div class="page-header-sub">
            Olá, <?= htmlspecialchars(explode(' ', $nome)[0]) ?>. Bem-vindo ao centro de acompanhamento de startups.
        </div>
    </div>
</div>

<?php if ($idMentor == 0): ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-user-pen"></i> Completar Perfil de Mentor</div>
                </div>
                <div class="card-body-custom">
                    <p class="text-muted mb-4">
                        Detectamos que a sua conta está marcada como "Mentor", mas ainda não completou os seus dados profissionais. 
                        Preencha o formulário abaixo para começar a mentorar projetos.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="completar_perfil">
                        <div class="mb-3">
                            <label class="form-label-custom">Sua Especialidade *</label>
                            <input type="text" name="especialidade" class="form-control-custom" required placeholder="Ex: Gestão de Projetos, Marketing Digital, Desenvolvimento Web...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Breve Bio</label>
                            <textarea name="bio" class="form-control-custom" rows="3" placeholder="Conte-nos um pouco sobre a sua experiência..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Link LinkedIn</label>
                            <input type="url" name="linkedin" class="form-control-custom" placeholder="https://linkedin.com/in/seuperfil">
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn-primary-custom">Salvar e Activar Perfil</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- KPI CARDS -->
    <div class="kpi-grid mb-4">
        <div class="kpi-card" style="--kpi-color:var(--primary)">
            <div class="kpi-icon"><i class="fa fa-rocket"></i></div>
            <div class="kpi-value"><?= $activasCount ?></div>
            <div class="kpi-label">Mentorias Activas</div>
        </div>
        <div class="kpi-card" style="--kpi-color:#8B5CF6">
            <div class="kpi-icon"><i class="fa fa-hourglass-half"></i></div>
            <div class="kpi-value"><?= $totalHoras ?> <span style="font-size:1rem; font-weight:600">horas</span></div>
            <div class="kpi-label">Horas de Consultoria</div>
        </div>
        <div class="kpi-card" style="--kpi-color:var(--warning)">
            <div class="kpi-icon"><i class="fa fa-list-check"></i></div>
            <div class="kpi-value"><?= $totalTarefas ?></div>
            <div class="kpi-label">Tarefas em Aberto</div>
            <div class="small text-muted" style="font-size:0.7rem">Nas suas startups</div>
        </div>
        <div class="kpi-card" style="--kpi-color:var(--success)">
            <div class="kpi-icon"><i class="fa fa-calendar-check"></i></div>
            <div class="kpi-value"><?= count($proximasReunioes) ?></div>
            <div class="kpi-label">Reuniões Agendadas</div>
        </div>
    </div>

    <style>
        /* ── Mentor Dashboard Local Styles ─────── */
        .mentor-dash-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }
        @media (max-width: 1100px) { .mentor-dash-grid { grid-template-columns: 1fr; } }

        /* Health pill */
        .health-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 11px; border-radius: 20px;
            font-size: 0.73rem; font-weight: 700;
        }
        .health-dot {
            width: 7px; height: 7px; border-radius: 50%;
            animation: pulse-dot 1.8s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.45; transform: scale(0.7); }
        }
        .health-good    { background: rgba(16,185,129,.12); color: #059669; }
        .health-good    .health-dot { background: #10B981; }
        .health-warning { background: rgba(245,158,11,.12); color: #B45309; }
        .health-warning .health-dot { background: #F59E0B; }
        .health-critical{ background: rgba(239,68,68,.12);  color: #B91C1C; }
        .health-critical .health-dot { background: #EF4444; }

        /* Metas urgency banner */
        .metas-banner {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 20px;
            background: linear-gradient(135deg, #FFFBEB, #FEF3C7);
            border-bottom: 1px solid #FDE68A;
        }
        .metas-badge-count {
            background: #D97706; color: #fff;
            font-size: 0.72rem; font-weight: 800;
            min-width: 24px; height: 24px;
            border-radius: 12px; display: inline-flex;
            align-items: center; justify-content: center;
            padding: 0 7px;
        }
        .meta-item {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            transition: background var(--transition);
        }
        .meta-item:last-child { border-bottom: none; }
        .meta-item:hover { background: var(--table-hover); }

        /* Task item */
        .task-item {
            display: flex; gap: 12px; align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .task-item:last-child { border-bottom: none; }
        .task-dot {
            width: 8px; height: 8px; border-radius: 50%;
            margin-top: 6px; flex-shrink: 0;
        }
        .task-dot.alta   { background: #EF4444; }
        .task-dot.media  { background: #F59E0B; }
        .task-dot.baixa  { background: #10B981; }

        /* Meeting item */
        .meeting-item {
            display: flex; gap: 14px; align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .meeting-item:last-child { border-bottom: none; }
        .meeting-date-badge {
            flex-shrink: 0;
            width: 44px; min-height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            font-weight: 800;
            line-height: 1.1;
        }
        .meeting-date-badge .day   { font-size: 1.25rem; }
        .meeting-date-badge .month { font-size: 0.62rem; text-transform: uppercase; opacity: .85; }
    </style>

    <div class="mentor-dash-grid">
        <!-- ── COL PRINCIPAL ───────────────── -->
        <div style="min-width:0;">

            <!-- METAS PENDENTES DE VALIDAÇÃO -->
            <?php if (!empty($metasAvaliar)): ?>
            <div class="card-custom mb-4">
                <div class="metas-banner">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width:36px;height:36px;border-radius:9px;background:rgba(217,119,6,.15);color:#D97706;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                            <i class="fa fa-circle-exclamation"></i>
                        </div>
                        <div>
                            <div style="font-size:0.88rem;font-weight:700;color:#92400E;">Evidências a Validar</div>
                            <div style="font-size:0.75rem;color:#A16207;">Startups aguardam o seu feedback</div>
                        </div>
                    </div>
                    <span class="metas-badge-count"><?= count($metasAvaliar) ?></span>
                </div>
                <?php foreach ($metasAvaliar as $mv): ?>
                <div class="meta-item">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; gap:8px; align-items:center; margin-bottom:4px;">
                                <span style="font-size:0.78rem; font-weight:700; color:var(--primary);"><?= htmlspecialchars($mv['projeto_nome']) ?></span>
                                <span style="font-size:0.7rem; color:var(--text-muted);">&middot; <?= date('d/m/Y', strtotime($mv['evidencia_em'])) ?></span>
                            </div>
                            <div style="font-size:0.88rem; font-weight:700; color:var(--text-primary); margin-bottom:3px;"><?= htmlspecialchars($mv['meta_titulo']) ?></div>
                            <div style="font-size:0.8rem; color:var(--text-secondary); line-height:1.45;"><?= htmlspecialchars(mb_strimwidth($mv['evidencia_texto'] ?? '', 0, 160, '...')) ?></div>
                            <?php if ($mv['evidencia_link'] || $mv['evidencia_path']): ?>
                            <div style="display:flex; gap:8px; margin-top:8px;">
                                <?php if ($mv['evidencia_link']): ?>
                                    <a href="<?= htmlspecialchars($mv['evidencia_link']) ?>" target="_blank" class="btn-ghost" style="padding:4px 10px; font-size:0.72rem;"><i class="fa fa-link me-1"></i>Ver Link</a>
                                <?php endif; ?>
                                <?php if ($mv['evidencia_path']): ?>
                                    <a href="/incubadora_ispsn/<?= htmlspecialchars($mv['evidencia_path']) ?>" target="_blank" class="btn-ghost" style="padding:4px 10px; font-size:0.72rem;"><i class="fa fa-file me-1"></i>Ficheiro</a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0;">
                            <button onclick="validarMeta(<?= $mv['id'] ?>, 'aprovar')" class="btn-primary-custom" style="padding:6px 14px; font-size:0.75rem; background:#059669; white-space:nowrap;">
                                <i class="fa fa-check"></i> Aprovar
                            </button>
                            <button onclick="validarMeta(<?= $mv['id'] ?>, 'reprovar')" class="btn-ghost" style="padding:6px 14px; font-size:0.75rem; color:#EF4444; border-color:#FECACA; white-space:nowrap;">
                                <i class="fa fa-rotate-left"></i> Devolver
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- STARTUPS MENTORADAS -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom">
                        <i class="fa fa-rocket"></i> Startups Mentoradas
                    </div>
                    <span style="font-size:0.78rem; color:var(--text-muted); font-weight:500;"><?= count($mentorias) ?> projecto<?= count($mentorias) != 1 ? 's' : '' ?></span>
                </div>
                <div class="table-wrapper">
                    <?php if (empty($mentorias)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fa fa-seedling"></i></div>
                            <div class="empty-state-title">Nenhuma startup atribuída</div>
                            <div class="empty-state-text">Aguarde até que um administrador lhe atribua projectos para mentoria.</div>
                        </div>
                    <?php else: ?>
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Startup / Projecto</th>
                                    <th>Fase</th>
                                    <th class="text-center">Saúde</th>
                                    <th class="text-center">Sessões</th>
                                    <th class="text-end">Acções</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mentorias as $m):
                                    $saude = $saudeProjetos[$m['id_projeto']] ?? 'good';
                                    $fasesLabels = [
                                        'ideacao'   => ['emoji' => '💡', 'label' => 'Ideação'],
                                        'validacao' => ['emoji' => '🔬', 'label' => 'Validação'],
                                        'mvp'       => ['emoji' => '📦', 'label' => 'MVP'],
                                        'tracao'    => ['emoji' => '📈', 'label' => 'Tração'],
                                        'mercado'   => ['emoji' => '🛒', 'label' => 'Mercado'],
                                    ];
                                    $fase = $fasesLabels[$m['projeto_fase'] ?? 'ideacao'] ?? ['emoji' => '💡', 'label' => 'Ideação'];
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($m['projeto_nome']) ?></div>
                                        <?php if ($m['data_inicio']): ?>
                                        <small class="text-muted"><i class="fa fa-calendar-alt me-1"></i><?= date('d/m/Y', strtotime($m['data_inicio'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size:0.88rem;"><?= $fase['emoji'] ?></span>
                                        <span style="font-size:0.82rem; font-weight:600; color:var(--text-secondary);"><?= $fase['label'] ?></span>
                                        <br>
                                        <span class="badge-estado badge-<?= $m['estado'] ?>"><?= ucfirst(str_replace('_', ' ', $m['estado'])) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="health-pill health-<?= $saude ?>">
                                            <span class="health-dot"></span>
                                            <?= ['good' => 'Em Dia', 'warning' => 'Atenção', 'critical' => 'Crítico'][$saude] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span style="font-size:1.1rem; font-weight:800; color:var(--text-primary);"><?= $m['total_sessoes'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div style="display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                                            <button class="btn-ghost" title="Nova Tarefa" data-bs-toggle="modal" data-bs-target="#modalTarefa"
                                                    onclick="configModal(<?= $m['id_projeto'] ?>, '<?= addslashes($m['projeto_nome']) ?>')"
                                                    style="padding:5px 9px; color:var(--info);">
                                                <i class="fa fa-tasks"></i>
                                            </button>
                                            <button class="btn-ghost" title="Agendar Reunião" data-bs-toggle="modal" data-bs-target="#modalReuniao"
                                                    onclick="configModal(<?= $m['id_projeto'] ?>, '<?= addslashes($m['projeto_nome']) ?>')"
                                                    style="padding:5px 9px; color:var(--warning);">
                                                <i class="fa fa-calendar-plus"></i>
                                            </button>
                                            <button class="btn-primary-custom" style="padding:5px 10px; font-size:0.75rem;"
                                                    data-bs-toggle="modal" data-bs-target="#modalSessao"
                                                    onclick="configModal(<?= $m['id_mentoria'] ?>, '<?= addslashes($m['projeto_nome']) ?>')">
                                                <i class="fa fa-plus"></i> Sessão
                                            </button>
                                            <a href="/incubadora_ispsn/app/views/mentor/projeto_detalhe.php?id=<?= $m['id_projeto'] ?>"
                                               class="btn-ghost" title="Ver Detalhes" style="padding:5px 9px;">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── COL LATERAL ────────────────── -->
        <div style="display:flex; flex-direction:column; gap:24px;">

            <!-- PRÓXIMAS TAREFAS -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-clipboard-list"></i> Próximas Tarefas</div>
                    <?php if (!empty($tarefasPendentes)): ?>
                    <span class="metas-badge-count" style="background:var(--warning);"><?= count($tarefasPendentes) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body-custom" style="padding:16px 20px;">
                    <?php if (empty($tarefasPendentes)): ?>
                        <div style="text-align:center; padding:24px 0;">
                            <i class="fa fa-check-circle" style="font-size:1.8rem; color:var(--success); margin-bottom:8px; display:block;"></i>
                            <p style="font-size:0.82rem; color:var(--text-muted); margin:0;">Nenhuma tarefa pendente!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tarefasPendentes as $t):
                            $prio = $t['prioridade'] ?? 'media';
                            $diasRestantes = $t['data_limite'] ? (int)((strtotime($t['data_limite']) - time()) / 86400) : null;
                            $prazoClass = ($diasRestantes !== null && $diasRestantes < 0) ? 'text-danger' : (($diasRestantes !== null && $diasRestantes <= 3) ? 'text-warning' : 'text-muted');
                        ?>
                        <div class="task-item">
                            <span class="task-dot <?= $prio ?>"></span>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:0.82rem; font-weight:600; color:var(--text-primary); margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($t['titulo']) ?></div>
                                <div style="font-size:0.75rem; color:var(--primary); font-weight:600; margin-bottom:3px;"><?= htmlspecialchars($t['projeto_nome']) ?></div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <small class="<?= $prazoClass ?>" style="font-size:0.7rem;">
                                        <i class="fa fa-clock me-1"></i>
                                        <?php if ($diasRestantes === null): ?>Sem prazo
                                        <?php elseif ($diasRestantes < 0): ?>Atrasada <?= abs($diasRestantes) ?>d
                                        <?php elseif ($diasRestantes === 0): ?>Hoje!
                                        <?php else: ?>+<?= $diasRestantes ?>d<?php endif; ?>
                                    </small>
                                    <a href="/incubadora_ispsn/app/views/mentor/projeto_detalhe.php?id=<?= $t['id_projeto'] ?>" style="font-size:0.7rem; color:var(--primary); font-weight:600; text-decoration:none;">Ver &rarr;</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- AGENDA DE REUNIÕES -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-calendar-days"></i> Próximas Reuniões</div>
                    <?php if (!empty($proximasReunioes)): ?>
                    <span class="metas-badge-count" style="background:var(--info);"><?= count($proximasReunioes) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body-custom" style="padding:16px 20px;">
                    <?php if (empty($proximasReunioes)): ?>
                        <div style="text-align:center; padding:24px 0;">
                            <i class="fa fa-calendar-xmark" style="font-size:1.8rem; color:var(--border); margin-bottom:8px; display:block;"></i>
                            <p style="font-size:0.82rem; color:var(--text-muted); margin:0;">Nenhuma reunião marcada.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($proximasReunioes as $re): ?>
                        <div class="meeting-item">
                            <div class="meeting-date-badge">
                                <span class="day"><?= date('d', strtotime($re['data_reuniao'])) ?></span>
                                <span class="month"><?= date('M', strtotime($re['data_reuniao'])) ?></span>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:0.85rem; font-weight:700; color:var(--text-primary); margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($re['titulo']) ?></div>
                                <div style="font-size:0.75rem; color:var(--primary); font-weight:600; margin-bottom:4px;"><?= htmlspecialchars($re['projeto_nome']) ?></div>
                                <div style="font-size:0.72rem; color:var(--text-muted);"><i class="fa fa-clock me-1"></i><?= date('H:i', strtotime($re['data_reuniao'])) ?></div>
                                <?php if ($re['link_reuniao']): ?>
                                <a href="<?= htmlspecialchars($re['link_reuniao']) ?>" target="_blank" class="btn-primary-custom mt-2" style="padding:5px 12px; font-size:0.7rem; width:100%; justify-content:center;">
                                    <i class="fa fa-video"></i> Entrar
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PERFIL DO MENTOR -->
            <div class="card-custom">
                <div class="card-body-custom" style="padding:20px; text-align:center;">
                    <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;font-size:1.4rem;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                        <?= mb_strtoupper(mb_substr(explode(' ', $nome)[0], 0, 1)) ?><?= mb_strtoupper(mb_substr(explode(' ', $nome)[1] ?? '', 0, 1)) ?>
                    </div>
                    <div style="font-weight:800; font-size:0.95rem; color:var(--text-primary); margin-bottom:2px;"><?= htmlspecialchars($nome) ?></div>
                    <div style="font-size:0.78rem; color:var(--text-secondary); margin-bottom:12px;"><?= htmlspecialchars($mentorInfo['especialidade'] ?? 'Mentor') ?></div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                        <div style="background:var(--surface-2);border-radius:9px;padding:10px;">
                            <div style="font-size:1.4rem;font-weight:800;color:var(--primary);"><?= $activasCount ?></div>
                            <div style="font-size:0.7rem;color:var(--text-muted);font-weight:500;">Activas</div>
                        </div>
                        <div style="background:var(--surface-2);border-radius:9px;padding:10px;">
                            <div style="font-size:1.4rem;font-weight:800;color:#8B5CF6;"><?= $totalHoras ?>h</div>
                            <div style="font-size:0.7rem;color:var(--text-muted);font-weight:500;">Consultoria</div>
                        </div>
                    </div>
                    <?php if ($mentorInfo['linkedin'] ?? ''): ?>
                    <a href="<?= htmlspecialchars($mentorInfo['linkedin']) ?>" target="_blank" class="btn-ghost" style="width:100%; justify-content:center; font-size:0.78rem;">
                        <i class="fa-brands fa-linkedin"></i> LinkedIn
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

<?php endif; ?>

<!-- MODAL REGISTAR SESSÃO -->
<div class="modal fade" id="modalSessao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="POST">
                <input type="hidden" name="action" value="registar_sessao">
                <input type="hidden" name="id_mentoria" id="modal_id_mentoria">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold">
                        <i class="fa fa-comment-medical me-2"></i> 
                        Registar Sessão: <span id="modal_projeto_nome" style="color:var(--primary)"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body-custom">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Data da Sessão *</label>
                            <input type="date" name="data_sessao" class="form-control-custom" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Duração (minutos) *</label>
                            <input type="number" name="duracao_min" class="form-control-custom" required value="60" min="15" step="15">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom">Tópicos Discutidos *</label>
                        <textarea name="topicos" class="form-control-custom" rows="3" required placeholder="Resuma os principais pontos abordados..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom">Próximos Passos / TPC</label>
                        <textarea name="proximos_passos" class="form-control-custom" rows="2" placeholder="O que a equipa deve fazer até à próxima sessão?"></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label-custom">Avaliação do Desempenho da Equipa (1-5)</label>
                        <div class="d-flex gap-4 mt-2">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <label class="d-flex align-items-center gap-2" style="cursor:pointer">
                                    <input type="radio" name="avaliacao_equipa" value="<?= $i ?>" <?= $i==4?'checked':'' ?>>
                                    <span style="font-weight:600"><?= $i ?></span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">
                        <i class="fa fa-check"></i> Guardar Registo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function configModal(id, nome) {
    document.getElementById('modal_id_mentoria').value = id;
    document.getElementById('modal_id_projeto_tarefa').value = id; // Para tarefa usamos ID do projeto
    document.getElementById('modal_id_projeto_reuniao').value = id;
    
    document.querySelectorAll('.projeto_nome_placeholder').forEach(el => el.innerText = nome);
}
</script>

<!-- MODAL: NOVA TAREFA -->
<div class="modal fade" id="modalTarefa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="POST" action="/incubadora_ispsn/app/controllers/mentor_action.php">
                <input type="hidden" name="action" value="criar_tarefa">
                <input type="hidden" name="id_projeto" id="modal_id_projeto_tarefa">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-tasks me-2"></i>Nova Tarefa para <span class="projeto_nome_placeholder"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Título da Tarefa *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Finalizar Business Plan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Descrição / Instruções</label>
                        <textarea name="descricao" class="form-control-custom" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label-custom">Prazo (Data Limite) *</label>
                            <input type="date" name="data_limite" class="form-control-custom" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Prioridade</label>
                            <select name="prioridade" class="form-control-custom">
                                <option value="baixa">Baixa</option>
                                <option value="media" selected>Média</option>
                                <option value="alta">Alta 🔥</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Criar Tarefa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: AGENDAR REUNIÃO -->
<div class="modal fade" id="modalReuniao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="POST" action="/incubadora_ispsn/app/controllers/mentor_action.php">
                <input type="hidden" name="action" value="agendar_reuniao">
                <input type="hidden" name="id_projeto" id="modal_id_projeto_reuniao">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-calendar-plus me-2"></i>Agendar Reunião: <span class="projeto_nome_placeholder"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Assunto / Pauta *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Revisão de Protótipo">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-7">
                            <label class="form-label-custom">Data e Hora *</label>
                            <input type="datetime-local" name="data_reuniao" class="form-control-custom" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label-custom">Tipo</label>
                            <select class="form-control-custom" onchange="toggleReuniaoType(this.value)">
                                <option value="virtual">Virtual (Link)</option>
                                <option value="presencial">Presencial</option>
                            </select>
                        </div>
                    </div>
                    <div id="div_virtual">
                        <label class="form-label-custom">Link da Reunião (Google Meet/Teams/Zoom)</label>
                        <input type="url" name="link_reuniao" class="form-control-custom" placeholder="https://meet.google.com/...">
                    </div>
                    <div id="div_presencial" style="display:none">
                        <label class="form-label-custom">Local da Reunião</label>
                        <input type="text" name="local" class="form-control-custom" placeholder="Ex: Sala de Reuniões ISPSN">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Agendar Agora</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleReuniaoType(val) {
    document.getElementById('div_virtual').style.display = val === 'virtual' ? 'block' : 'none';
    document.getElementById('div_presencial').style.display = val === 'presencial' ? 'block' : 'none';
}
function validarMeta(id, decisao) {
    document.getElementById('validarMetaId').value = id;
    document.getElementById('validarDecisao').value = decisao;
    document.getElementById('validarTitulo').textContent = decisao === 'aprovar' ? '✅ Validar Evidência' : '🔄 Devolver Evidência';
    document.getElementById('validarBtn').textContent = decisao === 'aprovar' ? 'Aprovar' : 'Devolver com Feedback';
    document.getElementById('validarBtn').className = decisao === 'aprovar' ? 'btn btn-success fw-bold rounded-3 px-4' : 'btn btn-danger fw-bold rounded-3 px-4';
    new bootstrap.Modal(document.getElementById('modalValidar')).show();
}
</script>

<!-- Modal de Validação de Metas -->
<div class="modal fade" id="modalValidar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg" style="border-radius: 14px;">
            <form method="post" action="/incubadora_ispsn/app/controllers/metas_action.php">
                <input type="hidden" name="action" value="validar_evidencia">
                <input type="hidden" name="id_meta_projeto" id="validarMetaId">
                <input type="hidden" name="decisao" id="validarDecisao">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/mentor.php">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="validarTitulo">Validar Evidência</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Nota (1-5)</label>
                        <select name="nota_mentor" class="form-control rounded-3" style="border-radius: 8px;">
                            <option value="1">1 — Insuficiente</option>
                            <option value="2">2 — Fraco</option>
                            <option value="3" selected>3 — Suficiente</option>
                            <option value="4">4 — Bom</option>
                            <option value="5">5 — Excelente</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Feedback</label>
                        <textarea name="feedback_mentor" class="form-control rounded-3" rows="3" required placeholder="Escreva o seu feedback sobre a evidência..." style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold rounded-3 px-4" id="validarBtn" style="border-radius: 8px;">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
