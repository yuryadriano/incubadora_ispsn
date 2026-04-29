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

// 2. Buscar mentorias activas
$mentorias = [];
$tarefasPendentes = [];
$avaliacoesRecentes = [];

if ($idMentor > 0) {
    // Mentorias
    $stmt = $mysqli->prepare("
        SELECT m.id as id_mentoria, m.estado, m.data_inicio, 
               p.id as id_projeto, p.titulo as projeto_nome, p.estado as projeto_estado,
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
            <div class="kpi-icon"><i class="fa fa-comments"></i></div>
            <div class="kpi-value"><?= $totalSessoes ?></div>
            <div class="kpi-label">Total de Sessões</div>
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

    <div class="row g-4">
        <!-- LISTA DE STARTUPS -->
        <div class="col-lg-8">
            <div class="card-custom h-100">
                <div class="card-header-custom">
                    <div class="card-title-custom">
                        <i class="fa fa-building-rocket"></i> Minhas Startups Mentoradas
                    </div>
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
                                    <th>Estado</th>
                                    <th>Sessões</th>
                                    <th class="text-end">Acções</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mentorias as $m): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600; color:var(--primary)"><?= htmlspecialchars($m['projeto_nome']) ?></div>
                                        <small class="text-muted">Início: <?= $m['data_inicio'] ? date('d/m/Y', strtotime($m['data_inicio'])) : '—' ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-estado badge-<?= $m['estado'] ?>">
                                            <?= ucfirst($m['estado']) ?>
                                        </span>
                                    </td>
                                    <td><span class="fw-bold"><?= $m['total_sessoes'] ?></span></td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <button class="btn-ghost" title="Nova Tarefa" data-bs-toggle="modal" data-bs-target="#modalTarefa" onclick="configModal(<?= $m['id_projeto'] ?>, '<?= addslashes($m['projeto_nome']) ?>')" style="color:var(--primary)">
                                                <i class="fa-solid fa-tasks"></i>
                                            </button>
                                            <button class="btn-ghost" title="Agendar Reunião" data-bs-toggle="modal" data-bs-target="#modalReuniao" onclick="configModal(<?= $m['id_projeto'] ?>, '<?= addslashes($m['projeto_nome']) ?>')" style="color:#d97706">
                                                <i class="fa-solid fa-calendar-plus"></i>
                                            </button>
                                            <button class="btn-primary-custom" style="padding:6px 10px; font-size:0.75rem"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalSessao"
                                                    onclick="configModal(<?= $m['id_mentoria'] ?>, '<?= addslashes($m['projeto_nome']) ?>')">
                                                <i class="fa-solid fa-plus"></i> Sessão
                                            </button>
                                            <a href="/incubadora_ispsn/app/views/mentor/projeto_detalhe.php?id=<?= $m['id_projeto'] ?>" class="btn-ghost" style="padding:6px 10px; font-size:0.75rem">
                                                <i class="fa-solid fa-eye"></i>
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

        <!-- TAREFAS PENDENTES -->
        <div class="col-lg-4">
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-clipboard-list"></i> Próximas Tarefas</div>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($tarefasPendentes)): ?>
                        <p class="text-muted text-center py-4 small">Nenhuma tarefa pendente.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($tarefasPendentes as $t): ?>
                            <div class="p-2 border rounded bg-white">
                                <div class="d-flex justify-content-between align-items-start">
                                    <small class="fw-bold text-primary"><?= htmlspecialchars($t['projeto_nome']) ?></small>
                                    <span class="badge bg-light text-dark" style="font-size:0.6rem"><?= $t['prioridade'] ?></span>
                                </div>
                                <div class="small fw-600 mt-1"><?= htmlspecialchars($t['titulo']) ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-danger" style="font-size:0.7rem">
                                        <i class="fa fa-clock me-1"></i> <?= $t['data_limite'] ? date('d/m/Y', strtotime($t['data_limite'])) : 'Sem prazo' ?>
                                    </small>
                                    <a href="/incubadora_ispsn/app/views/mentor/projeto_detalhe.php?id=<?= $t['id_projeto'] ?>#tab-tarefas" class="btn btn-sm btn-link p-0 text-decoration-none" style="font-size:0.7rem">Ver</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- REUNIÕES AGENDADAS -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-calendar-days"></i> Agenda de Reuniões</div>
                </div>
                <div class="card-body-custom">
                    <?php if (empty($proximasReunioes)): ?>
                        <p class="text-muted text-center py-4 small">Nenhuma reunião marcada.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($proximasReunioes as $re): ?>
                            <div class="p-2 border rounded border-primary bg-light">
                                <div class="d-flex justify-content-between">
                                    <small class="fw-bold text-primary"><?= htmlspecialchars($re['projeto_nome']) ?></small>
                                    <small class="text-muted"><?= date('H:i', strtotime($re['data_reuniao'])) ?></small>
                                </div>
                                <div class="small fw-600"><?= htmlspecialchars($re['titulo']) ?></div>
                                <div class="mt-2 small text-muted">
                                    <i class="fa fa-calendar me-1"></i> <?= date('d/m/Y', strtotime($re['data_reuniao'])) ?>
                                </div>
                                <?php if ($re['link_reuniao']): ?>
                                    <a href="<?= $re['link_reuniao'] ?>" target="_blank" class="btn-primary-custom w-100 mt-2 py-1" style="font-size:0.65rem">
                                        <i class="fa fa-video me-1"></i> Entrar na Reunião
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
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
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
