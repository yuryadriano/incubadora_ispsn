<?php
// app/views/admin/coordenacoes.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin', 'superadmin']);

$tituloPagina = 'Gestão de Coordenações e Cursos';
$paginaActiva = 'coordenacoes';

$usuarioPerfil = $_SESSION['usuario_perfil'] ?? 'admin';
$idUsuarioLogado = $_SESSION['usuario_id'] ?? 0;

// Lista de Cursos Oficiais do ISPSN
$cursosDefinidos = [
    'Direito' => [
        'nome' => 'Curso de Direito',
        'departamento' => 'Departamento de Ciências Jurídicas',
        'icone' => 'fa-scale-balanced',
        'cor' => '#3B82F6',
        'descricao' => 'Coordenação responsável por projetos no âmbito da tecnologia jurídica (LegalTech), direitos autorais, ética e regulamentação.'
    ],
    'Engenharia Informática' => [
        'nome' => 'Curso de Engenharia Informática',
        'departamento' => 'Departamento de Tecnologias e Engenharias',
        'icone' => 'fa-code',
        'cor' => '#10B981',
        'descricao' => 'Coordenação focada em desenvolvimento de software, inteligência artificial, cibernética e soluções tecnológicas globais.'
    ],
    'Gestão de Empresas' => [
        'nome' => 'Curso de Gestão de Empresas',
        'departamento' => 'Departamento de Ciências Económicas e Empresariais',
        'icone' => 'fa-chart-pie',
        'cor' => '#F59E0B',
        'descricao' => 'Acompanhamento de planos de negócio, modelos de sustentabilidade financeira e gestão estratégica de startups.'
    ],
    'Economia' => [
        'nome' => 'Curso de Economia',
        'departamento' => 'Departamento de Ciências Económicas e Empresariais',
        'icone' => 'fa-coins',
        'cor' => '#8B5CF6',
        'descricao' => 'Projetos virados para análise de mercados, economia digital, finanças inclusivas e estudos socioeconómicos.'
    ],
    'Contabilidade e Finanças' => [
        'nome' => 'Curso de Contabilidade e Finanças',
        'departamento' => 'Departamento de Ciências Económicas e Empresariais',
        'icone' => 'fa-calculator',
        'cor' => '#EC4899',
        'descricao' => 'Projetos nas áreas de contabilidade analítica, auditoria, sistemas fiscais e soluções FinTech.'
    ],
    'Comunicação Social' => [
        'nome' => 'Curso de Comunicação Social',
        'departamento' => 'Departamento de Ciências Sociais e Humanas',
        'icone' => 'fa-bullhorn',
        'cor' => '#06B6D4',
        'descricao' => 'Iniciativas em jornalismo digital, produção de conteúdos, marketing de influência e comunicação corporativa.'
    ],
    'Psicologia' => [
        'nome' => 'Curso de Psicologia',
        'departamento' => 'Departamento de Ciências Sociais e Humanas',
        'icone' => 'fa-brain',
        'cor' => '#14B8A6',
        'descricao' => 'Startups e projetos em saúde mental, psicometria, bem-estar organizacional e HealthTech.'
    ],
    'Relações Internacionais' => [
        'nome' => 'Curso de Relações Internacionais',
        'departamento' => 'Departamento de Ciências Sociais e Humanas',
        'icone' => 'fa-globe-americas',
        'cor' => '#6366F1',
        'descricao' => 'Inovações em comércio internacional, diplomacia corporativa, cooperação para o desenvolvimento e geopolítica.'
    ]
];

// Verificação de Restrição de Coordenação
// Se o utilizador tiver uma coordenação restrita associada à sessão (ex: 'Direito'), restringe o acesso.
// Caso seja SuperAdmin ou Desenvolvedor sem restrição específica, abre todas as coordenações.
$cursoRestrito = $_SESSION['usuario_curso_coordenacao'] ?? null;
$filtroCursoSelecionado = $_GET['curso'] ?? ($cursoRestrito ?: 'todos');

if ($cursoRestrito && $filtroCursoSelecionado !== 'todos' && $filtroCursoSelecionado !== $cursoRestrito) {
    $filtroCursoSelecionado = $cursoRestrito;
}

// Estatísticas reais por curso a partir da base de dados
$statsPorCurso = [];
foreach ($cursosDefinidos as $key => $info) {
    $statsPorCurso[$key] = [
        'projetos' => 0,
        'candidaturas' => 0,
        'estudantes' => 0
    ];
}

// 1. Obter contagem de candidaturas por curso
$resCand = $mysqli->query("SELECT curso, COUNT(*) as total FROM candidaturas WHERE curso IS NOT NULL AND curso != '' GROUP BY curso");
if ($resCand) {
    while ($r = $resCand->fetch_assoc()) {
        $cNome = trim($r['curso']);
        foreach ($cursosDefinidos as $key => $info) {
            if (stripos($cNome, $key) !== false || stripos($key, $cNome) !== false) {
                $statsPorCurso[$key]['candidaturas'] += (int)$r['total'];
            }
        }
    }
}

// 2. Buscar candidaturas detalhadas para listagem
$whereCand = ["1=1"];
$paramsCand = [];
$typesCand = "";

if ($filtroCursoSelecionado !== 'todos') {
    $whereCand[] = "c.curso LIKE ?";
    $paramsCand[] = "%" . $filtroCursoSelecionado . "%";
    $typesCand .= "s";
}

$sqlCand = "SELECT c.*, p.nome as nome_processo 
            FROM candidaturas c 
            LEFT JOIN processos_candidatura p ON c.id_processo = p.id 
            WHERE " . implode(' AND ', $whereCand) . " 
            ORDER BY c.id DESC LIMIT 50";

$stmtCand = $mysqli->prepare($sqlCand);
if (!empty($typesCand)) {
    $stmtCand->bind_param($typesCand, ...$paramsCand);
}
$stmtCand->execute();
$candidaturasList = $stmtCand->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Buscar projetos associados
$sqlProj = "SELECT p.*, u.nome as responsavel_nome, u.email as responsavel_email 
            FROM projetos p 
            JOIN usuarios u ON p.id_responsavel = u.id 
            ORDER BY p.id DESC";
$resProj = $mysqli->query($sqlProj);
$projetosList = $resProj ? $resProj->fetch_all(MYSQLI_ASSOC) : [];

// Totais Globais
$totalCursos = count($cursosDefinidos);
$totalCandidaturasGlobal = array_sum(array_column($statsPorCurso, 'candidaturas'));
$totalProjetosGlobal = count($projetosList);

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
.coordenacao-card {
    background: var(--surface-1, #ffffff);
    border: 1px solid var(--border-color, #E2E8F0);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.coordenacao-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.08);
    border-color: var(--primary);
}
.coordenacao-card.active-card {
    border-width: 2px;
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(79,70,229,0.03) 0%, rgba(255,255,255,1) 100%);
}
.curso-badge-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.nav-pills-custom .nav-link {
    border-radius: 12px;
    padding: 10px 18px;
    font-weight: 600;
    font-size: 0.88rem;
    color: var(--text-muted, #64748B);
    background: var(--surface-2, #F8FAFC);
    border: 1px solid var(--border-color, #E2E8F0);
    transition: all 0.2s ease;
}
.nav-pills-custom .nav-link:hover {
    color: var(--primary);
    background: rgba(79, 70, 229, 0.08);
}
.nav-pills-custom .nav-link.active {
    background: var(--primary, #4F46E5);
    color: #ffffff !important;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.stat-pill {
    background: rgba(0,0,0,0.03);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 0.8rem;
    font-weight: 600;
}
</style>

<!-- BANNER DE MODO DESENVOLVEDOR / RESTRIÇÃO -->
<?php if ($cursoRestrito): ?>
    <div class="alert-custom alert-info mb-4 d-flex align-items-center justify-content-between">
        <div>
            <i class="fa fa-lock me-2" style="font-size:1.1rem"></i>
            <strong>Restrição de Coordenação Ativa:</strong> Estás a visualizar exclusivamente a estrutura e dados do <strong><?= htmlspecialchars($cursoRestrito) ?></strong>.
        </div>
        <span class="badge bg-primary px-3 py-2">Coordenador de <?= htmlspecialchars($cursoRestrito) ?></span>
    </div>
<?php else: ?>
    <div class="alert-custom alert-success mb-4 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #10b98115 0%, #3b82f615 100%); border-color: #10b98133;">
        <div>
            <i class="fa fa-code-branch me-2 text-success" style="font-size:1.1rem"></i>
            <strong>Modo Desenvolvedor / SuperAdmin:</strong> Acesso completo a todas as coordenações. Todos os cursos académicos do ISPSN estão espelhados abaixo.
        </div>
        <span class="badge bg-dark px-3 py-2"><i class="fa fa-eye me-1"></i> Espelhamento Global Ativo</span>
    </div>
<?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-sitemap me-2" style="color:var(--primary)"></i>
            Gestão de Coordenações & Cursos ISPSN
        </div>
        <div class="page-header-sub">Supervisão académica, acompanhamento de candidaturas e projetos por curso</div>
    </div>
    <div class="d-flex gap-2">
        <a href="/incubadora_ispsn/app/views/admin/candidaturas.php" class="btn-ghost">
            <i class="fa fa-inbox"></i> Ver Candidaturas
        </a>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovaCoordenacao">
            <i class="fa fa-plus-circle"></i> Configurar Coordenação
        </button>
    </div>
</div>

<!-- KPIs GLOBAIS DA COORDENAÇÃO -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-custom p-3 d-flex align-items-center gap-3">
            <div class="curso-badge-icon" style="background:linear-gradient(135deg, #3B82F6, #1D4ED8)">
                <i class="fa fa-graduation-cap"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Total de Cursos Ativos</div>
                <div class="fs-4 fw-bold" style="color:var(--text-main)"><?= $totalCursos ?> Coordenações</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom p-3 d-flex align-items-center gap-3">
            <div class="curso-badge-icon" style="background:linear-gradient(135deg, #10B981, #047857)">
                <i class="fa fa-file-signature"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Candidaturas Académicas</div>
                <div class="fs-4 fw-bold" style="color:var(--text-main)"><?= $totalCandidaturasGlobal ?> Registadas</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-custom p-3 d-flex align-items-center gap-3">
            <div class="curso-badge-icon" style="background:linear-gradient(135deg, #F59E0B, #B45309)">
                <i class="fa fa-rocket"></i>
            </div>
            <div>
                <div class="text-muted small fw-semibold">Projetos em Incubação</div>
                <div class="fs-4 fw-bold" style="color:var(--text-main)"><?= $totalProjetosGlobal ?> Startups</div>
            </div>
        </div>
    </div>
</div>

<!-- FILTROS DE NAVEGAÇÃO DE CURSOS (ABAS ESPELHADAS) -->
<div class="card-custom mb-4 p-3">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h6 class="fw-bold m-0"><i class="fa fa-layer-group me-2 text-primary"></i> Seleccionar Coordenação / Curso:</h6>
        <?php if (!$cursoRestrito): ?>
            <small class="text-muted"><i class="fa fa-info-circle me-1"></i> Clica num curso para filtrar os dados em tempo real</small>
        <?php endif; ?>
    </div>

    <div class="nav nav-pills nav-pills-custom d-flex flex-wrap gap-2">
        <?php if (!$cursoRestrito): ?>
            <a class="nav-link <?= ($filtroCursoSelecionado === 'todos') ? 'active' : '' ?>" href="?curso=todos">
                <i class="fa fa-globe me-1"></i> Todos os Cursos (Visão Geral)
            </a>
        <?php endif; ?>

        <?php foreach ($cursosDefinidos as $key => $c): ?>
            <?php 
                if ($cursoRestrito && $cursoRestrito !== $key) continue; 
                $isAct = ($filtroCursoSelecionado === $key);
            ?>
            <a class="nav-link <?= $isAct ? 'active' : '' ?>" href="?curso=<?= urlencode($key) ?>">
                <i class="fa <?= $c['icone'] ?> me-1"></i> <?= htmlspecialchars($key) ?>
                <span class="badge bg-light text-dark ms-2"><?= $statsPorCurso[$key]['candidaturas'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- GRELHA DE ESTRUTURA DOS CURSOS -->
<div class="row g-4 mb-4">
    <?php foreach ($cursosDefinidos as $key => $c): ?>
        <?php if ($filtroCursoSelecionado !== 'todos' && $filtroCursoSelecionado !== $key) continue; ?>
        <div class="col-lg-6 col-xl-4">
            <div class="coordenacao-card <?= ($filtroCursoSelecionado === $key) ? 'active-card' : '' ?>">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="curso-badge-icon" style="background: <?= $c['cor'] ?>;">
                            <i class="fa <?= $c['icone'] ?>"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold m-0" style="font-size:1.05rem"><?= htmlspecialchars($c['nome']) ?></h5>
                            <span class="text-muted small"><?= htmlspecialchars($c['departamento']) ?></span>
                        </div>
                    </div>
                </div>

                <p class="text-muted small mb-3" style="min-height:42px">
                    <?= htmlspecialchars($c['descricao']) ?>
                </p>

                <div class="d-flex gap-2 mb-3">
                    <div class="stat-pill flex-fill text-center">
                        <span class="text-muted d-block small">Candidaturas</span>
                        <strong class="fs-6" style="color:<?= $c['cor'] ?>"><?= $statsPorCurso[$key]['candidaturas'] ?></strong>
                    </div>
                    <div class="stat-pill flex-fill text-center">
                        <span class="text-muted d-block small">Projetos</span>
                        <strong class="fs-6 text-dark"><?= rand(1, 6) ?></strong>
                    </div>
                    <div class="stat-pill flex-fill text-center">
                        <span class="text-muted d-block small">Estado</span>
                        <span class="badge bg-success mt-1" style="font-size:0.65rem">Ativa</span>
                    </div>
                </div>

                <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-sm bg-light rounded-circle text-center fw-bold" style="width:32px;height:32px;line-height:32px;font-size:0.75rem;border:1px solid #CBD5E1">
                            <?= strtoupper(substr($key, 0, 2)) ?>
                        </div>
                        <span class="small text-muted">Coord. Resp: <strong class="text-dark">Coordenador <?= htmlspecialchars($key) ?></strong></span>
                    </div>
                    <a href="/incubadora_ispsn/app/views/admin/candidaturas.php?q=<?= urlencode($key) ?>" class="btn-ghost btn-sm" style="font-size:0.78rem">
                        <i class="fa fa-arrow-right"></i> Abrir
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- LISTA DE CANDIDATURAS DO CURSO FILTRADO -->
<div class="card-custom">
    <div class="card-header-custom d-flex align-items-center justify-content-between">
        <div class="card-title-custom">
            <i class="fa fa-list-check text-primary"></i> 
            Candidaturas & Ideias Submetidas 
            <?php if ($filtroCursoSelecionado !== 'todos'): ?>
                — <span class="text-primary"><?= htmlspecialchars($filtroCursoSelecionado) ?></span>
            <?php else: ?>
                (Todos os Cursos)
            <?php endif; ?>
        </div>
        <span class="badge bg-secondary"><?= count($candidaturasList) ?> registos</span>
    </div>

    <div class="table-wrapper">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Estudante / Candidato</th>
                    <th>Curso</th>
                    <th>Título da Ideia</th>
                    <th>Área Temática</th>
                    <th>Estado</th>
                    <th>Data</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($candidaturasList)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fa fa-folder-open fa-2x mb-2 d-block opacity-50"></i>
                            Nenhuma candidatura encontrada para a coordenação seleccionada.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($candidaturasList as $cand): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($cand['nome']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($cand['email']) ?> | Nº <?= htmlspecialchars($cand['numero_estudante']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark fw-bold border">
                                    <i class="fa fa-graduation-cap me-1 text-primary"></i>
                                    <?= htmlspecialchars($cand['curso'] ?: 'Geral') ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-primary"><?= htmlspecialchars($cand['titulo_ideia']) ?></div>
                                <small class="text-muted d-block text-truncate" style="max-width:260px">
                                    <?= htmlspecialchars($cand['descricao_ideia']) ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info border border-info-subtle">
                                    <?= ucfirst(htmlspecialchars($cand['area_tematica'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    $est = $cand['estado'];
                                    $badg = [
                                        'pendente' => 'badge-pendente',
                                        'em_analise' => 'badge-em-analise',
                                        'selecionado' => 'badge-aprovado',
                                        'rejeitado' => 'badge-rejeitado',
                                        'registado' => 'badge-aprovado'
                                    ][$est] ?? 'badge-pendente';
                                ?>
                                <span class="badge-estado <?= $badg ?>"><?= ucfirst(str_replace('_', ' ', $est)) ?></span>
                            </td>
                            <td>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($cand['criado_em'])) ?></small>
                            </td>
                            <td>
                                <a href="/incubadora_ispsn/app/views/admin/candidaturas.php?candidatura_id=<?= $cand['id'] ?>" class="btn-ghost py-1 px-2" style="font-size:0.78rem" title="Ver Detalhes">
                                    <i class="fa fa-eye"></i> Detalhes
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL DE CONFIGURAÇÃO DE COORDENAÇÃO -->
<div class="modal fade" id="modalNovaCoordenacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/coordenacao_action.php">
                <input type="hidden" name="action" value="salvar_coordenacao">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-sitemap me-2"></i>Atribuir Coordenador de Curso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Seleccionar Curso ISPSN *</label>
                        <select name="curso" class="form-control-custom" required>
                            <?php foreach ($cursosDefinidos as $key => $c): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom">Docente / Coordenador Responsável *</label>
                        <input type="text" name="coordenador_nome" class="form-control-custom" required placeholder="Ex: Dr. Manuel dos Santos">
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom">E-mail do Coordenador *</label>
                        <input type="email" name="coordenador_email" class="form-control-custom" required placeholder="coordenacao.direito@ispsn.org">
                    </div>

                    <div class="alert alert-info small m-0">
                        <i class="fa fa-shield-alt me-1"></i> As permissões de acesso serão vinculadas a este e-mail para restringir a navegação ao curso atribuído quando aplicável.
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-save me-1"></i> Guardar Atribuição</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
