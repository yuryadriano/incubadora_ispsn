<?php
// app/views/mentor/sessoes.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['mentor', 'admin', 'superadmin']);

$idUsuario = (int)$_SESSION['usuario_id'];

// Buscar ID do mentor
$stmt = $mysqli->prepare("SELECT id FROM mentores WHERE id_usuario = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$idMentor = $stmt->get_result()->fetch_assoc()['id'] ?? 0;
$stmt->close();

$sessoes = [];
$totalMinutos = 0;
$minhasMentorias = [];
$topicosFrequentes = [];

if ($idMentor > 0) {
    // Buscar sessões com detalhes
    $stmt = $mysqli->prepare("
        SELECT s.*, p.titulo as projeto_nome, p.tipo as projeto_tipo, p.id as id_projeto
        FROM sessoes_mentoria s
        JOIN mentorias m ON m.id = s.id_mentoria
        JOIN projetos p ON p.id = m.id_projeto
        WHERE m.id_mentor = ?
        ORDER BY s.data_sessao DESC, s.criado_em DESC
    ");
    $stmt->bind_param('i', $idMentor);
    $stmt->execute();
    $sessoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($sessoes as $s) {
        $totalMinutos += $s['duracao_min'];
        // Extrair palavras-chave dos tópicos (simulação simples)
        $words = explode(',', $s['topicos']);
        foreach($words as $w) {
            $w = trim($w);
            if(strlen($w) > 3) $topicosFrequentes[$w] = ($topicosFrequentes[$w] ?? 0) + 1;
        }
    }
    arsort($topicosFrequentes);
    $topicosFrequentes = array_slice($topicosFrequentes, 0, 8);

    // Buscar mentorias activas para o modal
    $stmt = $mysqli->prepare("
        SELECT m.id, p.titulo 
        FROM mentorias m
        JOIN projetos p ON p.id = m.id_projeto
        WHERE m.id_mentor = ? AND m.estado = 'activa'
    ");
    $stmt->bind_param('i', $idMentor);
    $stmt->execute();
    $minhasMentorias = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$tituloPagina = 'Linha do Tempo de Mentoria';
$paginaActiva = 'sessoes';
require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
    .timeline-container {
        position: relative;
        padding-left: 60px;
        margin-top: 30px;
    }
    .timeline-line {
        position: absolute;
        left: 28px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(to bottom, var(--primary), var(--border));
        border-radius: 3px;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 40px;
    }
    .timeline-dot {
        position: absolute;
        left: -44px;
        top: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: white;
        border: 4px solid var(--primary);
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--primary), transparent 90%);
    }
    .timeline-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .timeline-card:hover {
        transform: translateX(10px);
        border-color: var(--primary);
        box-shadow: var(--shadow-lg);
    }
    .session-date {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
        display: block;
    }
    .session-project {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .session-badge {
        font-size: 0.65rem;
        padding: 2px 10px;
        border-radius: 20px;
        background: var(--surface-2);
        color: var(--primary-dark);
        font-weight: 700;
    }
    .session-topics {
        background: #f8fafc;
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-bottom: 16px;
        border-left: 4px solid var(--primary);
    }
    .next-steps-label {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--success);
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 6px;
    }
    .session-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }
    .rating-stars { color: #fbbf24; font-size: 0.9rem; }
    
    .growth-pulse {
        display: flex;
        gap: 12px;
        overflow-x: auto;
        padding-bottom: 10px;
        margin-bottom: 5px;
    }
    .pulse-tag {
        background: white;
        border: 1px solid var(--border);
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        transition: all 0.2s;
    }
    .pulse-tag:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
</style>

<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa-solid fa-bolt-lightning me-2" style="color:var(--primary)"></i>
            Evolução & Histórico de Sessões
        </div>
        <div class="page-header-sub">
            Registo da sua jornada transformando ideias em negócios reais.
        </div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalSessao" style="border-radius: 50px; padding: 12px 24px;">
        <i class="fa-solid fa-plus"></i> Registar Nova Sessão
    </button>
</div>

<!-- CARDS DE MÉTRICAS -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="kpi-card" style="--kpi-color:var(--primary); background: linear-gradient(135deg, white, #fffbeb);">
            <div class="kpi-icon"><i class="fa-solid fa-fire"></i></div>
            <div class="kpi-value"><?= count($sessoes) ?></div>
            <div class="kpi-label">Sessões de Impacto</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="--kpi-color:var(--success); background: linear-gradient(135deg, white, #f0fdf4);">
            <div class="kpi-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="kpi-value"><?= round($totalMinutos / 60, 1) ?>h</div>
            <div class="kpi-label">Horas Investidas</div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-custom" style="padding: 20px; min-height: 140px; display: flex; flex-direction: column; justify-content: center;">
            <div class="small fw-bold text-muted mb-3 uppercase" style="letter-spacing: 1px;">Radar de Tópicos Frequentes</div>
            <div class="growth-pulse">
                <?php if(empty($topicosFrequentes)): ?>
                    <div class="p-3 border rounded w-100 text-center bg-light">
                        <span class="text-muted small italic"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Aguardando dados para o radar. Comece a registar sessões para ver os temas recorrentes.</span>
                    </div>
                <?php else: ?>
                    <?php foreach($topicosFrequentes as $topic => $count): ?>
                        <div class="pulse-tag">
                            <i class="fa-solid fa-hashtag text-primary"></i> <?= htmlspecialchars($topic) ?> 
                            <span class="badge bg-light text-dark ms-1"><?= $count ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <small class="text-muted mt-2" style="font-size: 0.65rem">Mapeamento automático de temas abordados nas suas mentorias.</small>
        </div>
    </div>
</div>

<!-- TIMELINE -->
<div class="timeline-container">
    <div class="timeline-line"></div>
    
    <?php if (empty($sessoes)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-seedling"></i></div>
            <div class="empty-state-title">A jornada começa aqui</div>
            <div class="empty-state-text">Registe a sua primeira sessão de mentoria para começar a traçar o caminho do sucesso.</div>
        </div>
    <?php else: ?>
        <?php foreach ($sessoes as $s): ?>
        <div class="timeline-item">
            <div class="timeline-dot">
                <i class="fa-solid fa-check" style="font-size: 0.8rem; color: var(--primary)"></i>
            </div>
            <div class="timeline-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="session-date"><?= date('l, d \d\e F \d\e Y', strtotime($s['data_sessao'])) ?></span>
                        <div class="session-project">
                            <i class="fa-solid fa-rocket text-primary" style="font-size: 0.9rem"></i>
                            <?= htmlspecialchars($s['projeto_nome']) ?>
                            <span class="session-badge"><?= $s['duracao_min'] ?> MIN</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="rating-stars">
                            <?php for($i=0; $i<$s['avaliacao_equipa']; $i++) echo '★'; ?>
                        </div>
                        <small class="text-muted" style="font-size: 0.6rem">AVALIAÇÃO DE IMPACTO</small>
                    </div>
                </div>

                <div class="session-topics">
                    <strong>Pauta & Discussão:</strong><br>
                    <?= nl2br(htmlspecialchars($s['topicos'])) ?>
                </div>

                <?php if($s['proximos_passos']): ?>
                <div class="next-steps">
                    <div class="next-steps-label"><i class="fa-solid fa-bullseye"></i> Próximos Passos</div>
                    <p class="mb-0 small text-primary fw-600"><?= htmlspecialchars($s['proximos_passos']) ?></p>
                </div>
                <?php endif; ?>

                <div class="session-meta">
                    <div class="d-flex gap-2">
                        <a href="/incubadora_ispsn/app/views/mentor/projeto_detalhe.php?id=<?= $s['id_projeto'] ?>" class="pulse-tag" style="text-decoration:none">
                            <i class="fa-solid fa-eye"></i> Perfil da Startup
                        </a>
                    </div>
                    <div class="small text-muted">
                        <i class="fa-solid fa-user-tie me-1"></i> Mentor Responsável
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- MODAL REGISTAR SESSÃO (Mesmo código anterior, mas com design ajustado) -->
<div class="modal fade" id="modalSessao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom" style="border-radius: 30px;">
            <form method="POST" action="/incubadora_ispsn/app/controllers/mentor_action.php">
                <input type="hidden" name="action" value="registar_sessao">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/mentor/sessoes.php">
                
                <div class="modal-header-custom" style="background: linear-gradient(to right, #fffbeb, white); border-radius: 30px 30px 0 0;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Registo de Mentoria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Startup Alvo *</label>
                        <select name="id_mentoria" class="form-control-custom" required style="border-radius: 12px; padding: 12px;">
                            <option value="">— Seleccione o Projecto —</option>
                            <?php foreach ($minhasMentorias as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Quando aconteceu? *</label>
                            <input type="date" name="data_sessao" class="form-control-custom" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Duração (Minutos) *</label>
                            <input type="number" name="duracao" class="form-control-custom" required min="15" step="15" value="60">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">O que discutiram? (Tópicos) *</label>
                        <textarea name="topicos" class="form-control-custom" rows="3" required placeholder="Ex: Ajuste de MVP, Plano de Marketing..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">O que a equipa deve fazer agora?</label>
                        <textarea name="proximos_passos" class="form-control-custom" rows="2" placeholder="Acções concretas..."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Energia da Equipa (1-5)</label>
                            <select name="aval_equipa" class="form-control-custom">
                                <option value="5">🔥 Excelente</option>
                                <option value="4" selected>✨ Bom</option>
                                <option value="3">⚡ Regular</option>
                                <option value="2">❄️ Baixa</option>
                                <option value="1">💀 Crítica</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Seu Sentimento (1-5)</label>
                            <select name="aval_mentor" class="form-control-custom">
                                <option value="5">🌟 Muito Satisfeito</option>
                                <option value="4" selected>🙂 Satisfeito</option>
                                <option value="3">😐 Neutro</option>
                                <option value="2">😕 Preocupado</option>
                                <option value="1">🆘 Frustrado</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-custom" style="border-radius: 0 0 30px 30px;">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Adiar</button>
                    <button type="submit" class="btn-primary-custom" style="border-radius: 50px;">Finalizar Registo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
