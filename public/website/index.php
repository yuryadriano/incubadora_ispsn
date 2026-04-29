<?php
require_once __DIR__ . '/../../config/config.php';

// Cache busting
$css_version = time();

// Buscar processo de candidatura ativo
$processo = null;
$res = $mysqli->query("SELECT * FROM processos_candidatura WHERE estado='aberto' ORDER BY criado_em DESC LIMIT 1");
if ($res) $processo = $res->fetch_assoc();

// Buscar projetos em destaque
$projetos = [];
$res = $mysqli->query("
    SELECT p.id, p.titulo, p.descricao, p.area_tematica, p.fase, p.tipo, u.nome as autor
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.destaque_publico = 1
    ORDER BY p.pontos DESC
    LIMIT 6
");
if ($res) while ($row = $res->fetch_assoc()) $projetos[] = $row;

// Estatísticas reais
$stats = [];
$r = $mysqli->query("SELECT COUNT(*) n FROM projetos WHERE estado NOT IN ('rejeitado')"); $stats['startups'] = (int)$r->fetch_assoc()['n'];
$r = $mysqli->query("SELECT COUNT(*) n FROM usuarios WHERE perfil='mentor' AND activo=1"); $stats['mentores'] = (int)$r->fetch_assoc()['n'];
$r = $mysqli->query("SELECT COUNT(*) n FROM projetos WHERE estado='incubado'"); $stats['incubados'] = (int)$r->fetch_assoc()['n'];
$r = $mysqli->query("SELECT COUNT(*) n FROM usuarios WHERE activo=1"); $stats['membros'] = (int)$r->fetch_assoc()['n'];

// Buscar publicações recentes (4 para o layout ISPTEC)
$noticias = [];
$res = $mysqli->query("SELECT * FROM publicacoes_website WHERE status='publicado' ORDER BY criado_em DESC LIMIT 4");
if ($res) while ($row = $res->fetch_assoc()) $noticias[] = $row;

// Buscar galeria
$galeria = [];
$resG = $mysqli->query("SELECT * FROM galeria_website WHERE ativo=1 ORDER BY ordem ASC, criado_em DESC LIMIT 8");
if ($resG) while ($row = $resG->fetch_assoc()) $galeria[] = $row;
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= get_config('website_titulo', 'Incubadora Académica ISPSN — Onde as Ideias se Tornam Realidade') ?></title>
<meta name="description" content="<?= strip_tags(get_config('hero_subtitulo', 'A Incubadora Académica do ISPSN apoia estudantes empreendedores.')) ?>">

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
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
    <div class="nav-container">
        <a href="/incubadora_ispsn/public/website/" class="nav-logo">
            <img src="/incubadora_ispsn/assets/img/logo_sn.jpg" alt="ISPSN">
            <span>Incubadora <strong>ISPSN</strong></span>
        </a>
        <div class="nav-links">
            <a href="#hero">Início</a>
            <a href="sobre.php">Sobre Nós</a>
            <a href="#adn">Ecossistema</a>
            <a href="#mvv">Missão</a>
            <a href="#noticias">Notícias</a>
            <a href="#galeria">Galeria</a>
            <a href="/incubadora_ispsn/public/website/candidatura.php" class="nav-cta-solid"><i class="fa fa-rocket me-2"></i> Candidatar-se</a>
            <a href="/incubadora_ispsn/public/login.php" class="nav-item">Portal <i class="fa fa-arrow-right small ms-1"></i></a>
        </div>
        
        <!-- HAMBURGER (SÓ VISÍVEL EM MOBILE) -->
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
    <a href="#adn" onclick="toggleMobileMenu()">Ecossistema</a>
    <a href="#mvv" onclick="toggleMobileMenu()">Institucional</a>
    <a href="#noticias" onclick="toggleMobileMenu()">Notícias</a>
    <a href="#galeria" onclick="toggleMobileMenu()">Galeria</a>
    <a href="/incubadora_ispsn/public/website/candidatura.php" class="nav-cta-solid mt-3 text-center">Candidatar Agora</a>
    <a href="/incubadora_ispsn/public/login.php" class="text-center">Portal do Membro</a>
</div>

<!-- HERO SECTION -->
<section class="hero" id="hero">
    <div class="hero-video-bg">
        <!-- Overlay decorativo opcional -->
    </div>
    <div class="hero-overlay"></div>
    <div class="hero-content" data-aos="fade-up" data-aos-duration="1200">
        <div class="hero-badge"><i class="fa fa-sparkles me-2"></i> O Futuro Começa Aqui no ISPSN</div>
        <h1 class="hero-title">
            <?= get_config('hero_titulo', 'Onde as <span class="hero-gradient">Ideias</span> Ganham <span class="hero-gradient">Asas</span>') ?>
        </h1>
        <p class="hero-subtitle">
            <?= get_config('hero_subtitulo', 'Aceleramos o talento académico angolano através de mentoria, recursos e um ecossistema focado no sucesso empresarial.') ?>
        </p>
        <div class="hero-btns">
            <?php if ($processo): ?>
            <a href="/incubadora_ispsn/public/website/candidatura.php" class="btn-hero-primary">
                <i class="fa fa-paper-plane me-2"></i> Candidatar Agora
            </a>
            <?php endif; ?>
            <a href="#noticias" class="btn-hero-secondary">
                Ver Novidades
            </a>
        </div>
    </div>
</section>

<!-- IMPACT STATS -->
<div class="stats-overlap" data-aos="zoom-in">
    <div class="stats-container">
        <div class="stat-item"><div class="stat-number" data-target="<?= $stats['startups'] ?>">0</div><div class="stat-label">Startups</div></div>
        <div class="stat-divider"></div>
        <div class="stat-item"><div class="stat-number" data-target="<?= $stats['mentores'] ?>">0</div><div class="stat-label">Mentores</div></div>
        <div class="stat-divider"></div>
        <div class="stat-item"><div class="stat-number" data-target="<?= $stats['incubados'] ?>">0</div><div class="stat-label">Incubados</div></div>
        <div class="stat-divider"></div>
        <div class="stat-item"><div class="stat-number" data-target="<?= $stats['membros'] ?>">0</div><div class="stat-label">Empreendedores</div></div>
    </div>
</div>

<!-- ADN DA INOVAÇÃO (The Lightbulb Puzzle) -->
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

    <!-- SEÇÃO PROPÓSITO - BENTO CREATIVE (CONTEÚDO OFICIAL) -->
<section class="propósito-bento" id="mvv">
    <div class="section-container">
        <div class="bento-header" data-aos="fade-down">
            <span class="bento-tag">HONOR – LABOR – MERITUM</span>
            <h2 class="bento-title">Nossa Base & <span class="text-warning">Propósito</span></h2>
            <p class="mt-4 mx-auto opacity-75" style="max-width: 800px; font-size: 1.1rem;">
                O SOL NASCENTE não pretende ser apenas mais uma instituição de ensino superior, mas uma realidade universitária distinta. Essa distinção é sustentada pela nossa missão, visão e valores.
            </p>
        </div>

        <div class="bento-grid">
            <!-- Card Missão (Destaque) -->
            <div class="bento-card b-main" data-aos="fade-right">
                <div class="b-card-content">
                    <div class="b-icon-box"><i class="fa fa-bullseye"></i></div>
                    <h3>Missão</h3>
                    <p>Promover o desenvolvimento das competências profissionais, científicas e tecnológicas dos futuros líderes, através de um ensino superior inovador e de qualidade, fundado nos valores do humanismo e da responsabilidade individual.</p>
                </div>
            </div>

            <!-- Card Visão -->
            <div class="bento-card b-visao" data-aos="fade-up" data-aos-delay="100">
                <div class="b-icon-box"><i class="fa fa-eye"></i></div>
                <div class="b-card-content">
                    <h4>Visão</h4>
                    <p>Promover ensino de qualidade e rigor no âmbito interdisciplinar, a investigação, a formação avançada e a extensão universitária.</p>
                </div>
            </div>

            <!-- Card Valores -->
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

<!-- NOTÍCIAS (Sala de Visitas) -->
<section class="news-section" id="noticias">
    <div class="section-container">
        <div class="section-header d-flex justify-content-between align-items-end" data-aos="fade-up">
            <div>
                <span class="section-eyebrow">Sala de Visitas</span>
                <h2 class="section-title">Actualidade & Eventos</h2>
            </div>
            <a href="#" class="btn btn-outline-dark rounded-pill fw-bold px-4 mb-2">Ver Todas <i class="fa fa-arrow-right ms-2"></i></a>
        </div>
        
        <div class="news-layout">
            <?php if (empty($noticias)): ?>
                <div class="text-center py-5 opacity-50 w-100"><p>Fique atento às nossas novidades em breve.</p></div>
            <?php else: ?>
                <!-- Notícia em Destaque (Esquerda) -->
                <div class="news-featured" data-aos="fade-right">
                    <?php $feat = $noticias[0]; ?>
                    <a href="noticia.php?id=<?= $feat['id'] ?>" class="feat-img-link">
                        <img src="<?= $feat['imagem'] ?: '/incubadora_ispsn/assets/img/blog/default.jpg' ?>" alt="Destaque">
                    </a>
                    <div class="feat-badge">Em destaque</div>
                    <div class="feat-overlay">
                        <h3 class="feat-title"><?= htmlspecialchars($feat['titulo']) ?></h3>
                        <p class="feat-excerpt"><?= htmlspecialchars($feat['resumo']) ?></p>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="noticia.php?id=<?= $feat['id'] ?>" class="btn-feat-more">Ler mais</a>
                            <span class="feat-date"><?= date('d/m/Y', strtotime($feat['criado_em'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Notícias Secundárias (Direita) -->
                <div class="news-side-grid">
                    <?php for($i=1; $i < count($noticias); $i++): $n = $noticias[$i]; ?>
                    <div class="news-side-card" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                        <a href="noticia.php?id=<?= $n['id'] ?>" class="side-card-img">
                            <img src="<?= $n['imagem'] ?: '/incubadora_ispsn/assets/img/blog/default.jpg' ?>" alt="News">
                        </a>
                        <div class="side-card-body">
                            <h5 class="side-card-title"><?= htmlspecialchars($n['titulo']) ?></h5>
                            <p class="side-card-excerpt"><?= htmlspecialchars($n['resumo']) ?></p>
                            <div class="side-card-footer">
                                <a href="noticia.php?id=<?= $n['id'] ?>" class="side-card-link">LER MAIS</a>
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

<!-- GALERIA (Premium Moments) -->
<section class="gallery-section" id="galeria">
    <div class="section-container">
        <div class="section-header text-center" data-aos="fade-up">
            <span class="section-eyebrow">Momentos ISPSN</span>
            <h2 class="section-title">Nossa História em Imagens</h2>
        </div>
        
        <div class="gallery-modern-grid">
            <?php if (empty($galeria)): ?>
                <div class="col-12 text-center py-5 opacity-20"><i class="fa fa-images fa-4x mb-3"></i><p>A carregar momentos...</p></div>
            <?php else: ?>
                <?php foreach ($galeria as $i => $g): ?>
                <div class="gallery-card" data-aos="fade-up" data-aos-delay="<?= $i * 50 ?>">
                    <div class="gallery-photo">
                        <img src="<?= $g['imagem'] ?>" alt="Gallery">
                        <div class="photo-zoom"><i class="fa fa-search-plus"></i></div>
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

<!-- FOOTER - PREMIUM REDESIGN -->
<footer class="footer">
    <div class="footer-container">
        <div class="footer-main-grid">
            <!-- Coluna 1: Marca e Social -->
            <div class="footer-brand">
                <div class="f-logo">
                    <img src="/incubadora_ispsn/assets/img/logo_sn.jpg" alt="ISPSN">
                    <span>Incubadora <strong>ISPSN</strong></span>
                </div>
                <p class="f-desc">
                    Transformando o potencial académico em inovação de mercado. Somos o berço das próximas grandes empresas de Angola.
                </p>
                <div class="f-social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-x-twitter"></i></a>
                </div>
            </div>

            <!-- Coluna 2: Navegação -->
            <div class="footer-links">
                <h6>Explorar</h6>
                <ul>
                    <li><a href="#hero">Início</a></li>
                    <li><a href="#adn">Ecossistema</a></li>
                    <li><a href="#mvv">Sobre Nós</a></li>
                    <li><a href="#galeria">Galeria</a></li>
                    <li><a href="#noticias">Notícias</a></li>
                </ul>
            </div>

            <!-- Coluna 3: Institucional -->
            <div class="footer-links">
                <h6>Portal</h6>
                <ul>
                    <li><a href="/incubadora_ispsn/public/website/candidatura.php">Candidaturas</a></li>
                    <li><a href="/incubadora_ispsn/public/login.php">Área do Membro</a></li>
                    <li><a href="#">Termos de Uso</a></li>
                    <li><a href="#">Privacidade</a></li>
                </ul>
            </div>

            <!-- Coluna 4: Contactos Rápidos -->
            <div class="footer-contact">
                <h6>Contactos</h6>
                <div class="contact-item">
                    <i class="fa fa-envelope-open"></i>
                    <div>
                        <span>E-mail</span>
                        <strong><?= get_config('contacto_email', 'incubadora@ispsn.org') ?></strong>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fa fa-phone-volume"></i>
                    <div>
                        <span>Telefone</span>
                        <strong>+244 9XX XXX XXX</strong>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fa fa-location-dot"></i>
                    <div>
                        <span>Localização</span>
                        <strong>Huambo, Angola</strong>
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

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ duration: 1000, once: true, offset: 100 });

// Navbar scroll logic
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 80);
});

// Mobile menu logic
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
            const inc = Math.max(1, target / 50);
            const update = () => {
                count += inc;
                if (count < target) {
                    el.innerText = Math.ceil(count);
                    setTimeout(update, 20);
                } else el.innerText = target;
            };
            update();
            obs.unobserve(el);
        }
    });
}, { threshold: 0.5 });
counters.forEach(c => obs.observe(c));

// Smooth Scroll
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 80,
                behavior: 'smooth'
            });
        }
    });
});
</script>
</body>
</html>
