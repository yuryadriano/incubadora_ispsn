<?php
require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sobre Nós — Incubadora ISPSN</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="/incubadora_ispsn/public/website/assets/style.css">

<style>
    .page-hero { padding: 180px 0 100px; background: var(--dark); color: #fff; position: relative; overflow: hidden; }
    .page-hero::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 70% 30%, rgba(251, 191, 36, 0.15), transparent); }
    .page-title { font-size: 3.5rem; font-weight: 900; letter-spacing: -0.03em; margin-bottom: 20px; position: relative; z-index: 2; }
    .breadcrumb { background: transparent; padding: 0; margin: 0; position: relative; z-index: 2; }
    .breadcrumb-item a { color: #fbbf24; text-decoration: none; font-weight: 600; }
    .breadcrumb-item.active { color: rgba(255,255,255,0.6); }

    .about-section { padding: 100px 0; background: #fff; }
    .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: start; }
    
    .about-visual { position: sticky; top: 120px; }
    .about-img { width: 100%; border-radius: 40px; box-shadow: var(--shadow-lg); }
    .about-badge { 
        position: absolute; bottom: -30px; right: -30px; background: #fbbf24; color: var(--dark);
        padding: 30px; border-radius: 30px; box-shadow: 0 20px 40px rgba(251, 191, 36, 0.3);
        text-align: center; font-weight: 900; line-height: 1;
    }
    .about-badge span { display: block; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 5px; opacity: 0.7; }
    .about-badge strong { font-size: 2.5rem; }

    .about-content h2 { font-size: 2.5rem; font-weight: 900; margin-bottom: 30px; color: var(--dark); }
    .about-content p { font-size: 1.15rem; color: #475569; line-height: 1.8; margin-bottom: 30px; }

    .mvv-detail-grid { display: flex; flex-direction: column; gap: 40px; margin-top: 60px; }
    .mvv-item { display: flex; gap: 30px; }
    .mvv-icon { 
        flex-shrink: 0; width: 60px; height: 60px; background: rgba(251, 191, 36, 0.1); 
        color: #fbbf24; border-radius: 20px; display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
    }
    .mvv-info h4 { font-weight: 800; color: var(--dark); margin-bottom: 15px; }
    .mvv-info p { font-size: 1rem; margin-bottom: 10px; }
    
    .divisa-box { 
        background: #f8fafc; padding: 40px; border-radius: 30px; border-left: 8px solid #fbbf24;
        margin: 60px 0;
    }
    .divisa-box strong { color: #fbbf24; font-size: 1.2rem; letter-spacing: 0.1em; }

    @media (max-width: 992px) {
        .about-grid { grid-template-columns: 1fr; gap: 60px; }
        .about-visual { position: static; }
        .page-title { font-size: 2.8rem; }
    }
</style>
</head>
<body>

<nav class="navbar scrolled" id="navbar">
    <div class="nav-container">
        <a href="/incubadora_ispsn/public/website/" class="nav-logo">
            <img src="/incubadora_ispsn/assets/img/logo_sn_premium.png" alt="ISPSN">
        </a>
        <div class="nav-links">
            <a href="index.php">Início</a>
            <a href="sobre.php" class="active">Sobre Nós</a>
            <a href="index.php#noticias">Notícias</a>
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
    <a href="index.php" onclick="toggleMobileMenu()">Início</a>
    <a href="sobre.php" class="active" onclick="toggleMobileMenu()">Sobre Nós</a>
    <a href="index.php#noticias" onclick="toggleMobileMenu()">Notícias</a>
    <a href="/incubadora_ispsn/public/website/candidatura.php" class="nav-cta-solid mt-3 text-center" onclick="toggleMobileMenu()"><i class="fa fa-rocket me-2"></i> Candidatar-se</a>
    <a href="/incubadora_ispsn/public/login.php" class="nav-portal-mobile" onclick="toggleMobileMenu()"><i class="fa fa-user-shield me-2"></i> ACESSO AO PORTAL</a>
</div>

<header class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Sobre Nós</li>
            </ol>
        </nav>
        <h1 class="page-title">Missão, Visão e Valores</h1>
    </div>
</header>

<section class="about-section">
    <div class="container">
        <div class="about-grid">
            <div class="about-visual" data-aos="fade-right">
                <img src="/incubadora_ispsn/assets/img/blog/default.jpg" alt="ISPSN Campus" class="about-img">
                <div class="about-badge">
                    <span>Divisa Oficial</span>
                    <strong>ISPSN</strong>
                </div>
            </div>

            <div class="about-content" data-aos="fade-left">
                <h2>Onde o Conhecimento Encontra o <span class="text-warning">Propósito</span></h2>
                <p>
                    Como deriva da nossa divisa, <strong>HONOR – LABOR – MERITUM</strong>, o SOL NASCENTE não pretende ser apenas mais uma instituição de ensino superior, mas uma realidade universitária distinta. 
                </p>
                <p>
                    Assente naqueles pilares, essa distinção será sustentada, para além de outros atributos, pela nossa missão, visão e valores, que descrevemos abaixo:
                </p>

                <div class="mvv-detail-grid">
                    <div class="mvv-item">
                        <div class="mvv-icon"><i class="fa fa-bullseye"></i></div>
                        <div class="mvv-info">
                            <h4>Missão</h4>
                            <p>O ISPSN tem como missão promover o desenvolvimento das competências profissionais, científicas e tecnológicas dos futuros líderes, através de um ensino superior inovador e de qualidade, fundado nos valores do humanismo e da responsabilidade individual.</p>
                            <p class="small text-muted italic">A nossa missão incorpora três importantes valores: A cientificidade, o humanismo e a responsabilidade individual.</p>
                        </div>
                    </div>

                    <div class="mvv-item">
                        <div class="mvv-icon"><i class="fa fa-eye"></i></div>
                        <div class="mvv-info">
                            <h4>Visão</h4>
                            <p>Promover ensino de qualidade e rigor no âmbito interdisciplinar, a investigação, a formação avançada e a extensão universitária.</p>
                        </div>
                    </div>

                    <div class="mvv-item">
                        <div class="mvv-icon"><i class="fa fa-gem"></i></div>
                        <div class="mvv-info">
                            <h4>Valores</h4>
                            
                            <div class="mb-4">
                                <h6 class="fw-bold text-dark mb-2">Cientificismo</h6>
                                <p class="small">Acreditamos que o principal motor deve ser a construção do científico. Este não poderá resultar apenas da transmissão, mas da produção de conhecimento.</p>
                            </div>

                            <div class="mb-4">
                                <h6 class="fw-bold text-dark mb-2">Humanismo</h6>
                                <p class="small">Os produtos do conhecimento científico devem ser munidos dos valores do humanismo, assegurando que o resultado da atividade científica seja ajuizado pelo seu valor humano.</p>
                            </div>

                            <div>
                                <h6 class="fw-bold text-dark mb-2">Responsabilidade Individual</h6>
                                <p class="small">Pretendemos formar indivíduos que sejam capazes de, em consciência, exercerem as suas liberdades, sedimentadas pelo domínio do conhecimento.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="divisa-box" data-aos="zoom-in">
                    <strong>HONOR – LABOR – MERITUM</strong>
                    <p class="mb-0 mt-2 text-muted small">Os pilares que sustentam a excelência da nossa instituição.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Reusing the footer from index -->
<footer class="footer">
    <div class="footer-container">
        <div class="footer-main-grid">
            <div class="footer-brand">
                <div class="f-logo">
                    <div class="nav-logo" style="padding: 0;">
                        <img src="/incubadora_ispsn/assets/img/logo_sn_premium.png" alt="ISPSN" style="height: 70px;">
                    </div>
                </div>
                <p class="f-desc">Transformando o potencial académico em inovação de mercado.</p>
            </div>
            <div class="footer-links">
                <h6>Explorar</h6>
                <ul>
                    <li><a href="index.php">Início</a></li>
                    <li><a href="sobre.php">Sobre Nós</a></li>
                </ul>
            </div>
            <div class="footer-contact">
                <h6>Contactos</h6>
                <div class="contact-item">
                    <i class="fa fa-envelope-open"></i>
                    <div><span>E-mail</span><strong><?= get_config('contacto_email', 'incubadora@ispsn.org') ?></strong></div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="bottom-inner">
                <p>&copy; <?= date('Y') ?> <strong>ISPSN</strong>. Todos os direitos reservados.</p>
                <div class="bottom-links">
                    <span>Desenvolvido por <strong class="text-white">GTI - Educa futuro</strong></span>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
AOS.init({ duration: 1000, once: true });

// Mobile menu logic
const hamburger = document.getElementById('hamburger');
const navMobile = document.getElementById('navMobile');

function toggleMobileMenu() {
    hamburger.classList.toggle('active');
    navMobile.classList.toggle('open');
    document.body.style.overflow = navMobile.classList.contains('open') ? 'hidden' : 'auto';
}

hamburger.addEventListener('click', toggleMobileMenu);

// Navbar scroll logic (already scrolled on this page, but good to have)
window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 80 || true);
});
</script>
</body>
</html>
