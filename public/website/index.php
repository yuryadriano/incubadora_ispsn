<?php
define('NO_SESSION', true);
require_once __DIR__ . '/../../config/config.php';

// Cache curto para refletir rapidamente alterações feitas no painel Admin
header('Cache-Control: public, max-age=30, s-maxage=60, stale-while-revalidate=120');
header('Vary: Accept-Encoding');

$css_version = ASSET_VERSION;
$fallbackBlogImg = '/incubadora_ispsn/assets/img/blog/candidaturas_brevemente.png';
$fallbackGenImg  = '/incubadora_ispsn/assets/img/ilustracao_criatividade.png';

// 1. Buscar processo de candidatura aberto
$processo = null;
$resProc = $mysqli->query("SELECT * FROM processos_candidatura WHERE estado='aberto' ORDER BY criado_em DESC LIMIT 1");
if ($resProc) {
    $processo = $resProc->fetch_assoc();
}

// 2. Estatísticas reais sincronizadas em tempo real com a Base de Dados do Sistema
$stats = ['startups' => 0, 'mentores' => 0, 'incubados' => 0, 'membros' => 0];
$resStats = $mysqli->query("
    SELECT
        (SELECT COUNT(*) FROM projetos WHERE estado != 'rejeitado') AS total_startups,
        GREATEST(
            (SELECT COUNT(*) FROM mentores),
            (SELECT COUNT(*) FROM usuarios WHERE perfil = 'mentor' AND activo = 1)
        ) AS total_mentores,
        (SELECT COUNT(*) FROM projetos WHERE estado IN ('aprovado','incubado','fundo_investimento','concluido')) AS total_incubados,
        (SELECT COUNT(*) FROM usuarios WHERE perfil = 'utilizador' AND activo = 1) AS total_membros
");
if ($resStats && ($rowSt = $resStats->fetch_assoc())) {
    $stats['startups']  = (int)($rowSt['total_startups']  ?? 0);
    $stats['mentores']  = (int)($rowSt['total_mentores']  ?? 0);
    $stats['incubados'] = (int)($rowSt['total_incubados'] ?? 0);
    $stats['membros']   = (int)($rowSt['total_membros']   ?? 0);
}

// 3. Buscar Startups sincronizadas com o sistema (prioriza destaque_publico = 1, seguido das startups ativas/incubadas)
$projetos = [];
$resProj = $mysqli->query("
    SELECT p.id, p.titulo, p.descricao, p.area_tematica, p.fase, p.tipo, p.estado, p.pontos, p.destaque_publico, u.nome AS autor
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.estado != 'rejeitado'
    ORDER BY p.destaque_publico DESC,
             FIELD(p.estado, 'incubado', 'fundo_investimento', 'concluido', 'aprovado', 'em_avaliacao', 'submetido'),
             p.pontos DESC,
             p.criado_em DESC
    LIMIT 6
");
if ($resProj) {
    while ($row = $resProj->fetch_assoc()) {
        $projetos[] = $row;
    }
}

// 4. Buscar publicações recentes
$noticias = [];
$resNot = $mysqli->query("SELECT * FROM publicacoes_website WHERE status='publicado' ORDER BY criado_em DESC LIMIT 4");
if ($resNot) {
    while ($row = $resNot->fetch_assoc()) {
        $noticias[] = $row;
    }
}

// 5. Buscar galeria
$galeria = [];
$resGal = $mysqli->query("SELECT * FROM galeria_website WHERE ativo=1 ORDER BY ordem ASC, criado_em DESC LIMIT 8");
if ($resGal) {
    while ($row = $resGal->fetch_assoc()) {
        $galeria[] = $row;
    }
}

// Configurações dinâmicas geridas no painel Admin (com fallback oficial ISPSN)
$heroBadge     = get_config('hero_badge', 'O Futuro Começa Aqui no ISPSN');
$heroSubtitulo = get_config('hero_subtitulo', 'Aceleramos o talento académico angolano através de mentoria, recursos e um ecossistema focado no sucesso empresarial.');
$textoMissao   = get_config('missao', 'Promover o desenvolvimento das competências profissionais, científicas e tecnológicas dos futuros líderes, através de um ensino superior inovador e de qualidade, fundado nos valores do humanismo e da responsabilidade individual.');
$textoVisao    = get_config('visao', 'Promover ensino de qualidade e rigor no âmbito interdisciplinar, a investigação, a formação avançada e a extensão universitária.');
$emailContacto = get_config('contacto_email', 'incubadora@ispsn.org');
$telContacto   = get_config('contacto_telefone', '+244 923 000 000');
$endContacto   = get_config('contacto_endereco', 'Huambo, Angola');

$labelsFase = [
    'ideacao'   => 'Ideação',
    'validacao' => 'Validação',
    'mvp'       => 'MVP',
    'tracao'    => 'Tração',
    'mercado'   => 'Mercado',
];
$labelsEstado = [
    'submetido'          => 'Em Triagem',
    'em_avaliacao'       => 'Em Avaliação',
    'aprovado'           => 'Aprovada',
    'incubado'           => 'Incubada',
    'fundo_investimento' => 'Investimento',
    'concluido'          => 'Graduada',
];
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(get_config('site_titulo', 'Incubadora Académica ISPSN — Onde as Ideias se Tornam Realidade')) ?></title>
<meta name="description" content="<?= htmlspecialchars($heroSubtitulo) ?>">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Fonts & Icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<!-- AOS Animation Library -->
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

<link rel="stylesheet" href="/incubadora_ispsn/public/website/assets/style.css?v=<?= $css_version ?>">
<style>
.startup-showcase-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 28px;
}
.startup-card-pub {
    background: #FFFFFF;
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 28px;
    box-shadow: var(--shadow);
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.startup-card-pub:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}
.startup-badge-area {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    text-transform: uppercase;
    background: var(--primary-light);
    color: var(--primary-dark);
}
.startup-badge-fase {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    background: #F1F5F9;
    color: #334155;
}
.edital-live-banner {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    border: 1px solid rgba(251, 191, 36, 0.35);
    border-radius: 24px;
    padding: 32px 40px;
    color: #FFFFFF;
    margin-top: 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 24px;
    box-shadow: var(--shadow-lg);
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
    <div class="nav-container">
        <a href="/incubadora_ispsn/public/website/" class="nav-logo">
            <img src="/incubadora_ispsn/assets/img/logo_sn_transparent.png" alt="ISPSN" onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn.jpg';">
        </a>
        <div class="nav-links">
            <a href="#hero">Início</a>
            <a href="sobre.php">Sobre Nós</a>
            <a href="#startups">Startups</a>
            <a href="#adn">Ecossistema</a>
            <a href="#noticias">Notícias</a>
            <a href="#galeria">Galeria</a>
            <a href="/incubadora_ispsn/public/website/candidatura.php" class="nav-cta-solid"><i class="fa fa-rocket me-2"></i> Candidatar-se</a>
            <a href="/incubadora_ispsn/public/login.php" class="nav-item">Portal <i class="fa fa-arrow-right small ms-1"></i></a>
        </div>
        
        <button class="nav-hamburger" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</nav>

<!-- MENU MOBILE OVERLAY -->
<div class="nav-mobile" id="navMobile">
    <a href="#hero" onclick="toggleMobileMenu()">Início</a>
    <a href="sobre.php" onclick="toggleMobileMenu()">Sobre Nós</a>
    <a href="#startups" onclick="toggleMobileMenu()">Startups</a>
    <a href="#adn" onclick="toggleMobileMenu()">Ecossistema</a>
    <a href="#noticias" onclick="toggleMobileMenu()">Notícias</a>
    <a href="#galeria" onclick="toggleMobileMenu()">Galeria</a>
    <a href="/incubadora_ispsn/public/website/candidatura.php" class="nav-cta-solid mt-3 text-center" onclick="toggleMobileMenu()"><i class="fa fa-rocket me-2"></i> Candidatar-se</a>
    <a href="/incubadora_ispsn/public/login.php" class="nav-portal-mobile" onclick="toggleMobileMenu()"><i class="fa fa-user-shield me-2"></i> ACESSO AO PORTAL</a>
</div>

<!-- HERO SECTION -->
<section class="hero" id="hero">
    <div class="hero-video-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content" data-aos="fade-up" data-aos-duration="1200">
        <div class="hero-badge"><i class="fa fa-sparkles me-2"></i> <?= htmlspecialchars($heroBadge) ?></div>
        <h1 class="hero-title">
            Onde as <span class="hero-gradient">Ideias</span> Ganham <span class="hero-gradient">Asas</span>
        </h1>
        <p class="hero-subtitle">
            <?= htmlspecialchars($heroSubtitulo) ?>
        </p>
        <div class="hero-btns">
            <a href="/incubadora_ispsn/public/website/candidatura.php" class="btn-hero-primary">
                <i class="fa fa-paper-plane me-2"></i> <?= $processo ? 'Candidatar ao Edital Aberto' : 'Submeter Candidatura' ?>
            </a>
            <a href="#startups" class="btn-hero-secondary">
                Conhecer Startups (<?= (int)$stats['startups'] ?>)
            </a>
        </div>
    </div>
</section>

<!-- IMPACT STATS (SINCRONIZADO EM TEMPO REAL COM O SISTEMA) -->
<div class="stats-overlap" data-aos="zoom-in">
    <div class="stats-container">
        <div class="stat-item"><div class="stat-number" data-target="<?= (int)$stats['startups'] ?>"><?= (int)$stats['startups'] ?></div><div class="stat-label">Startups</div></div>
        <div class="stat-divider"></div>
        <div class="stat-item"><div class="stat-number" data-target="<?= (int)$stats['mentores'] ?>"><?= (int)$stats['mentores'] ?></div><div class="stat-label">Mentores</div></div>
        <div class="stat-divider"></div>
        <div class="stat-item"><div class="stat-number" data-target="<?= (int)$stats['incubados'] ?>"><?= (int)$stats['incubados'] ?></div><div class="stat-label">Incubados</div></div>
        <div class="stat-divider"></div>
        <div class="stat-item"><div class="stat-number" data-target="<?= (int)$stats['membros'] ?>"><?= (int)$stats['membros'] ?></div><div class="stat-label">Empreendedores</div></div>
    </div>
</div>

<!-- VITRINE DE STARTUPS & PROJETOS DO ECOSSISTEMA (SINCRONIZADA COM A BD) -->
<section id="startups" style="padding: 100px 0 60px; background: var(--bg);">
    <div class="section-container">
        <div class="section-header d-flex justify-content-between align-items-end flex-wrap gap-3" data-aos="fade-up">
            <div>
                <span class="section-eyebrow">Portfólio Académico</span>
                <h2 class="section-title">Startups &amp; Inovação no ISPSN</h2>
            </div>
            <a href="/incubadora_ispsn/public/website/candidatura.php" class="btn-nexus">
                <i class="fa fa-rocket me-2"></i> Inscrever a Minha Ideia
            </a>
        </div>

        <?php if (empty($projetos)): ?>
            <div class="text-center py-5 bg-white rounded-4 border p-5">
                <i class="fa fa-rocket fa-3x text-warning mb-3 d-block"></i>
                <h4 class="fw-bold">Seja a próxima Startup em destaque no ISPSN</h4>
                <p class="text-muted mb-4">Submeta a sua proposta de negócio ou inovação tecnológica para avaliação pela Comissão Técnica.</p>
                <a href="/incubadora_ispsn/public/website/candidatura.php" class="btn-hero-primary">Candidatar Agora</a>
            </div>
        <?php else: ?>
            <div class="startup-showcase-grid">
                <?php foreach ($projetos as $idxP => $proj):
                    $faseKey   = (string)($proj['fase'] ?? 'ideacao');
                    $estadoKey = (string)($proj['estado'] ?? 'submetido');
                    $faseTxt   = $labelsFase[$faseKey]     ?? ucfirst($faseKey);
                    $estadoTxt = $labelsEstado[$estadoKey] ?? ucfirst($estadoKey);
                    $areaTxt   = ucfirst((string)($proj['area_tematica'] ?? 'Tecnologia'));
                ?>
                    <div class="startup-card-pub" data-aos="fade-up" data-aos-delay="<?= ($idxP % 3) * 80 ?>">
                        <div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <span class="startup-badge-area">
                                    <i class="fa fa-bolt"></i> <?= htmlspecialchars($areaTxt) ?>
                                </span>
                                <span class="startup-badge-fase">
                                    Fase: <?= htmlspecialchars($faseTxt) ?> · <?= htmlspecialchars($estadoTxt) ?>
                                </span>
                            </div>
                            <h4 class="fw-bold mb-2" style="color: var(--dark); font-size: 1.25rem;">
                                <?= htmlspecialchars($proj['titulo']) ?>
                            </h4>
                            <p class="text-muted mb-4" style="font-size: 0.92rem; line-height: 1.6;">
                                <?= htmlspecialchars(mb_strimwidth((string)($proj['descricao'] ?? ''), 0, 160, '...')) ?>
                            </p>
                        </div>
                        <div class="pt-3 border-top d-flex justify-content-between align-items-center" style="border-color: var(--border) !important; font-size: 0.84rem;">
                            <span class="text-muted">
                                <i class="fa fa-user-graduate text-warning me-1"></i>
                                <strong><?= htmlspecialchars($proj['autor']) ?></strong>
                            </span>
                            <span class="fw-bold" style="color: var(--primary);">
                                <i class="fa fa-trophy me-1"></i><?= (int)($proj['pontos'] ?? 0) ?> SP
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($processo): ?>
            <div class="edital-live-banner" data-aos="fade-up">
                <div>
                    <span class="badge bg-warning text-dark fw-bold mb-2 px-3 py-1 rounded-pill">
                        <i class="fa fa-bullhorn me-1"></i> EDITAL ABERTO
                    </span>
                    <h3 class="fw-bold mb-1"><?= htmlspecialchars($processo['titulo']) ?></h3>
                    <p class="mb-0 text-white-50" style="max-width: 680px;">
                        <?= htmlspecialchars($processo['descricao'] ?: 'Estão abertas as candidaturas para novos projetos de estudantes e pré-licenciados do ISPSN.') ?>
                        <?php if (!empty($processo['data_fim'])): ?>
                            · Encerra em <strong><?= date('d/m/Y', strtotime($processo['data_fim'])) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="/incubadora_ispsn/public/website/candidatura.php" class="btn-hero-primary">
                    <i class="fa fa-paper-plane me-2"></i> Submeter Candidatura
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ADN DA INOVAÇÃO -->
<section class="adn-section" id="adn">
    <div class="section-container">
        <div class="adn-grid">
            <div class="adn-visual" data-aos="fade-right">
                <img src="/incubadora_ispsn/assets/img/ilustracao_criatividade.png" alt="Inovação ISPSN" class="adn-img-main">
                <div class="adn-floating-box" data-aos="zoom-in" data-aos-delay="400">
                    <i class="fa fa-lightbulb fa-2x mb-3"></i>
                    <h4>Pensa Grande</h4>
                    <p class="small mb-0">Nós ajudamos a encaixar as peças do teu sucesso empresarial.</p>
                </div>
            </div>
            <div class="adn-content" data-aos="fade-left">
                <span class="section-eyebrow">Nosso Conceito</span>
                <h2 class="section-title">O ADN do Sucesso Académico</h2>
                <p class="mt-4 mb-5" style="font-size:1.15rem; color:var(--text-muted)">Na Incubadora ISPSN, transformamos o conhecimento académico em valor de mercado. Nosso ecossistema foi desenhado para quem não tem medo de desafios.</p>
                <div class="adn-features">
                    <div class="adn-feat">
                        <i class="fa fa-puzzle-piece"></i>
                        <h5 class="fw-bold">Criatividade</h5>
                        <p class="small text-muted">Soluções fora da caixa para problemas reais.</p>
                    </div>
                    <div class="adn-feat">
                        <i class="fa fa-chart-line"></i>
                        <h5 class="fw-bold">Oportunidade</h5>
                        <p class="small text-muted">Acesso a mercados e investidores estratégicos.</p>
                    </div>
                    <div class="adn-feat">
                        <i class="fa fa-brain"></i>
                        <h5 class="fw-bold">Conhecimento</h5>
                        <p class="small text-muted">Mentoria técnica de alto nível académico.</p>
                    </div>
                    <div class="adn-feat">
                        <i class="fa fa-shield-halved"></i>
                        <h5 class="fw-bold">Resiliência</h5>
                        <p class="small text-muted">Suporte total em todas as fases do negócio.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SEÇÃO PROPÓSITO - BENTO CREATIVE -->
<section class="propósito-bento" id="mvv">
    <div class="section-container">
        <div class="bento-header" data-aos="fade-down">
            <span class="bento-tag">HONOR – LABOR – MERITUM</span>
            <h2 class="bento-title">Nossa Base &amp; <span class="text-warning">Propósito</span></h2>
            <p class="mt-4 opacity-75" style="font-size: 1.1rem;">
                O SOL NASCENTE não pretende ser apenas mais uma instituição de ensino superior, mas uma realidade universitária distinta. Essa distinção é sustentada pela nossa missão, visão e valores.
            </p>
        </div>

        <div class="bento-grid">
            <div class="bento-card b-main" data-aos="fade-right">
                <div class="b-card-content">
                    <div class="b-icon-box"><i class="fa fa-bullseye"></i></div>
                    <h3>Missão</h3>
                    <p><?= htmlspecialchars($textoMissao) ?></p>
                </div>
            </div>

            <div class="bento-card b-visao" data-aos="fade-up" data-aos-delay="100">
                <div class="b-icon-box"><i class="fa fa-eye"></i></div>
                <div class="b-card-content">
                    <h4>Visão</h4>
                    <p><?= htmlspecialchars($textoVisao) ?></p>
                </div>
            </div>

            <div class="bento-card b-valores" data-aos="fade-left" data-aos-delay="200">
                <div class="b-card-content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Valores Fundamentais</h4>
                        <div class="b-icon-circle"><i class="fa fa-balance-scale"></i></div>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <h6 class="fw-bold mb-2">Cientificismo</h6>
                            <p class="small opacity-75">Construção e produção de conhecimento científico real.</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold mb-2">Humanismo</h6>
                            <p class="small opacity-75">Ciência despida de preconceitos, focada no valor humano.</p>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold mb-2">Responsabilidade</h6>
                            <p class="small opacity-75">Formar indivíduos capazes de exercer liberdade com consciência.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <a href="sobre.php" class="btn-nexus">Ler História Completa <i class="fa fa-arrow-right ms-2"></i></a>
        </div>
    </div>
</section>

<!-- NOTÍCIAS -->
<section class="news-section" id="noticias">
    <div class="section-container">
        <div class="section-header d-flex justify-content-between align-items-end" data-aos="fade-up">
            <div>
                <span class="section-eyebrow">Sala de Visitas</span>
                <h2 class="section-title">Actualidade &amp; Eventos</h2>
            </div>
        </div>
        
        <div class="news-layout">
            <?php if (empty($noticias)): ?>
                <div class="text-center py-5 opacity-50 w-100"><p>Fique atento às nossas novidades em breve.</p></div>
            <?php else: ?>
                <!-- Notícia em Destaque (Esquerda) -->
                <div class="news-featured" data-aos="fade-right">
                    <?php
                    $feat    = $noticias[0];
                    $featImg = resolver_imagem_site($feat['imagem'] ?? '', $fallbackBlogImg);
                    ?>
                    <a href="noticia.php?id=<?= (int)$feat['id'] ?>" class="feat-img-link">
                        <img src="<?= htmlspecialchars($featImg) ?>" alt="Destaque" onerror="this.onerror=null;this.src='<?= $fallbackBlogImg ?>';">
                    </a>
                    <div class="feat-badge">Em destaque</div>
                    <div class="feat-overlay">
                        <h3 class="feat-title"><?= htmlspecialchars($feat['titulo']) ?></h3>
                        <p class="feat-excerpt"><?= htmlspecialchars($feat['resumo']) ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="noticia.php?id=<?= (int)$feat['id'] ?>" class="btn-feat-more">Ler mais</a>
                            <span class="feat-date"><?= date('d/m/Y', strtotime($feat['criado_em'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Notícias Secundárias (Direita) -->
                <div class="news-side-grid">
                    <?php for ($i = 1; $i < count($noticias); $i++):
                        $n    = $noticias[$i];
                        $nImg = resolver_imagem_site($n['imagem'] ?? '', $fallbackBlogImg);
                    ?>
                    <div class="news-side-card" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                        <a href="noticia.php?id=<?= (int)$n['id'] ?>" class="side-card-img">
                            <img src="<?= htmlspecialchars($nImg) ?>" alt="Notícia" onerror="this.onerror=null;this.src='<?= $fallbackBlogImg ?>';">
                        </a>
                        <div class="side-card-body">
                            <h5 class="side-card-title"><?= htmlspecialchars($n['titulo']) ?></h5>
                            <p class="side-card-excerpt"><?= htmlspecialchars($n['resumo']) ?></p>
                            <div class="side-card-footer">
                                <a href="noticia.php?id=<?= (int)$n['id'] ?>" class="side-card-link">LER MAIS</a>
                                <span class="side-card-date"><?= date('d/m/Y', strtotime($n['criado_em'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- GALERIA -->
<section class="gallery-section" id="galeria">
    <div class="section-container">
        <div class="section-header text-center" data-aos="fade-up">
            <span class="section-eyebrow">Momentos ISPSN</span>
            <h2 class="section-title">Nossa História em Imagens</h2>
        </div>
        
        <div class="gallery-modern-grid">
            <?php if (empty($galeria)): ?>
                <div class="col-12 text-center py-5 text-muted"><i class="fa fa-images fa-3x mb-3 opacity-50 d-block"></i><p>Fique atento às fotos dos nossos eventos e momentos em breve.</p></div>
            <?php else: ?>
                <?php foreach ($galeria as $i => $g): 
                    $bentoClass = '';
                    if ($i % 5 == 0) $bentoClass = 'g-wide';
                    elseif ($i % 5 == 3) $bentoClass = 'g-tall';
                    $galImg = resolver_imagem_site($g['imagem'] ?? '', $fallbackGenImg);
                ?>
                <div class="gallery-card <?= $bentoClass ?>" data-aos="fade-up" data-aos-delay="<?= $i * 50 ?>" onclick="openLightbox('<?= htmlspecialchars($galImg, ENT_QUOTES) ?>')">
                    <div class="gallery-photo">
                        <img src="<?= htmlspecialchars($galImg) ?>" alt="<?= htmlspecialchars($g['titulo'] ?? 'Galeria') ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= $fallbackGenImg ?>';">
                        <div class="photo-zoom"><i class="fa fa-expand"></i></div>
                    </div>
                    <div class="gallery-caption">
                        <h5><?= htmlspecialchars($g['titulo']) ?></h5>
                        <p><?= htmlspecialchars($g['descricao']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- LIGHTBOX MODAL -->
<div id="lightboxModal" onclick="closeLightbox()">
    <span class="lightbox-close">&times;</span>
    <img id="lightboxImg" src="" alt="Zoom">
</div>

<!-- FOOTER -->
<footer class="footer">
    <div class="footer-container">
        <div class="footer-main-grid">
            <div class="footer-brand">
                <div class="f-logo">
                    <a href="#hero" class="nav-logo" style="padding: 0; color: #fff;">
                        <img src="/incubadora_ispsn/assets/img/logo_sn_transparent.png" alt="ISPSN" onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn.jpg';">
                    </a>
                </div>
                <p class="f-desc">
                    Transformando o potencial académico em inovação de mercado. Somos o berço das próximas grandes empresas de Angola.
                </p>
                <div class="f-social-links">
                    <a href="<?= htmlspecialchars(get_config('facebook_url', '#')) ?>"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?= htmlspecialchars(get_config('linkedin_url', '#')) ?>"><i class="fab fa-linkedin-in"></i></a>
                    <a href="<?= htmlspecialchars(get_config('instagram_url', '#')) ?>"><i class="fab fa-instagram"></i></a>
                </div>
            </div>

            <div class="footer-links">
                <h6>Explorar</h6>
                <ul>
                    <li><a href="#hero">Início</a></li>
                    <li><a href="#startups">Startups</a></li>
                    <li><a href="#adn">Ecossistema</a></li>
                    <li><a href="sobre.php">Sobre Nós</a></li>
                    <li><a href="#galeria">Galeria</a></li>
                    <li><a href="#noticias">Notícias</a></li>
                </ul>
            </div>

            <div class="footer-links">
                <h6>Portal</h6>
                <ul>
                    <li><a href="/incubadora_ispsn/public/website/candidatura.php">Candidaturas</a></li>
                    <li><a href="/incubadora_ispsn/public/login.php">Área do Membro</a></li>
                </ul>
            </div>

            <div class="footer-contact">
                <h6>Contactos</h6>
                <div class="contact-item">
                    <i class="fa fa-envelope-open"></i>
                    <div>
                        <span>E-mail</span>
                        <strong><?= htmlspecialchars($emailContacto) ?></strong>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fa fa-phone-volume"></i>
                    <div>
                        <span>Telefone</span>
                        <strong><?= htmlspecialchars($telContacto) ?></strong>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fa fa-location-dot"></i>
                    <div>
                        <span>Localização</span>
                        <strong><?= htmlspecialchars($endContacto) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="bottom-inner">
                <p>&copy; <?= date('Y') ?> <strong>Incubadora Académica ISPSN</strong>. Todos os direitos reservados.</p>
                <div class="bottom-links">
                    <span>Desenvolvido por <strong class="text-white">GTI - Educa futuro</strong></span>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ duration: 1000, once: true, offset: 100 });

window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 80);
});

const hamburger = document.getElementById('hamburger');
const navMobile = document.getElementById('navMobile');

function toggleMobileMenu() {
    hamburger.classList.toggle('active');
    navMobile.classList.toggle('open');
    document.body.style.overflow = navMobile.classList.contains('open') ? 'hidden' : 'auto';
}

hamburger.addEventListener('click', toggleMobileMenu);

// Counter Animation
const counters = document.querySelectorAll('.stat-number');
const obs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const el = entry.target;
            const target = +el.dataset.target;
            let count = 0;
            const inc = Math.max(1, target / 40);
            const update = () => {
                count += inc;
                if (count < target) {
                    el.innerText = Math.ceil(count);
                    setTimeout(update, 20);
                } else {
                    el.innerText = target;
                }
            };
            update();
            obs.unobserve(el);
        }
    });
}, { threshold: 0.5 });
counters.forEach(c => obs.observe(c));

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const targetId = this.getAttribute('href');
        if (!targetId || targetId === '#') return;
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            e.preventDefault();
            window.scrollTo({
                top: targetElement.offsetTop - 80,
                behavior: 'smooth'
            });
        }
    });
});

const lightbox = document.getElementById('lightboxModal');
const lightboxImg = document.getElementById('lightboxImg');

function openLightbox(src) {
    lightboxImg.src = src;
    lightbox.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    lightbox.classList.remove('open');
    document.body.style.overflow = 'auto';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLightbox();
});
</script>
</body>
</html>
