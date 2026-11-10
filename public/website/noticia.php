<?php
define('NO_SESSION', true);
require_once __DIR__ . '/../../config/config.php';

// Headers de cache para conteúdo de notícias
header('Cache-Control: public, max-age=120, s-maxage=300, stale-while-revalidate=600');
header('CDN-Cache-Control: max-age=600');
header('Vary: Accept-Encoding');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

$stmt = $mysqli->prepare("
    SELECT p.*, u.nome as autor_nome 
    FROM publicacoes_website p 
    LEFT JOIN usuarios u ON u.id = p.criado_por 
    WHERE p.id = ? AND p.status = 'publicado'
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$noticia = $stmt->get_result()->fetch_assoc();

if (!$noticia) { header("Location: index.php"); exit; }

// Buscar outras notícias para a barra lateral ou sugestões
$sugestoes = [];
$stmtS = $mysqli->prepare("SELECT id, titulo, imagem, criado_em FROM publicacoes_website WHERE status='publicado' AND id != ? ORDER BY criado_em DESC LIMIT 3");
$stmtS->bind_param('i', $id);
$stmtS->execute();
$resS = $stmtS->get_result();
if ($resS) while ($row = $resS->fetch_assoc()) $sugestoes[] = $row;
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($noticia['titulo']) ?> — Incubadora ISPSN</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="/incubadora_ispsn/public/website/assets/style.css">

<style>
    .article-page { padding: 160px 0 100px; background: #fff; }
    .article-header { margin-bottom: 40px; }
    .article-category { display: inline-block; color: var(--primary); font-size: 0.8rem; font-weight: 800; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.15em; }
    .article-title { font-size: 3rem; font-weight: 900; color: var(--dark); line-height: 1.1; letter-spacing: -0.03em; margin-bottom: 20px; }
    .article-meta { display: flex; align-items: center; gap: 20px; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 40px; }
    .article-meta i { color: var(--primary); margin-right: 5px; }

    /* FULL SIDE-BY-SIDE LAYOUT */
    .article-main-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: start; margin-bottom: 50px; }
    
    .article-image-side { position: sticky; top: 140px; border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-lg); height: 600px; }
    .article-image-side img { width: 100%; height: 100%; object-fit: cover; }
    
    .article-text-side { padding-right: 20px; }
    .article-text-side h6 { font-weight: 900; font-size: 0.8rem; text-transform: uppercase; color: var(--primary); margin-bottom: 25px; letter-spacing: 0.1em; display: flex; align-items: center; gap: 10px; }
    .article-text-side h6::after { content: ''; flex: 1; height: 1px; background: var(--primary); opacity: 0.2; }
    
    .article-body { font-size: 1.15rem; line-height: 1.9; color: #334155; }
    .article-body p { margin-bottom: 30px; }

    /* Sidebar for related news remains at the bottom or separate grid */
    .related-section { margin-top: 80px; padding-top: 50px; border-top: 1px solid #f1f5f9; }
    .sidebar-title { font-weight: 900; font-size: 1.3rem; margin-bottom: 30px; text-transform: uppercase; color: var(--dark); }
    
    .related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
    .side-noticia { display: flex; gap: 15px; text-decoration: none; align-items: center; background: #f8fafc; padding: 15px; border-radius: 16px; transition: 0.3s; }
    .side-noticia:hover { transform: translateY(-5px); background: #fff; box-shadow: var(--shadow); }
    .side-noticia-img { width: 70px; height: 70px; border-radius: 12px; object-fit: cover; }
    .side-noticia-info h6 { font-weight: 700; color: var(--dark); margin-bottom: 3px; font-size: 0.95rem; line-height: 1.3; }
    .side-noticia-info span { font-size: 0.75rem; color: var(--text-muted); }

    @media (max-width: 992px) {
        .article-main-grid { grid-template-columns: 1fr; }
        .article-image-side { position: static; height: 400px; }
        .article-text-side { padding-right: 0; }
    }
</style>
</head>
<body>

<nav class="navbar scrolled" id="navbar">
    <div class="nav-container">
        <a href="/incubadora_ispsn/public/website/" class="nav-logo">
            <img src="/incubadora_ispsn/assets/img/logo_sn_transparent.png" alt="ISPSN" onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn.jpg';">
        </a>
        <div class="nav-links">
            <a href="index.php#hero">Início</a>
            <a href="sobre.php">Sobre Nós</a>
            <a href="index.php#startups">Startups</a>
            <a href="index.php#adn">Ecossistema</a>
            <a href="index.php#noticias" class="active">Notícias</a>
            <a href="index.php#galeria">Galeria</a>
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
    <a href="index.php#hero" onclick="toggleMobileMenu()">Início</a>
    <a href="sobre.php" onclick="toggleMobileMenu()">Sobre Nós</a>
    <a href="index.php#startups" onclick="toggleMobileMenu()">Startups</a>
    <a href="index.php#adn" onclick="toggleMobileMenu()">Ecossistema</a>
    <a href="index.php#noticias" class="active" onclick="toggleMobileMenu()">Notícias</a>
    <a href="index.php#galeria" onclick="toggleMobileMenu()">Galeria</a>
    <a href="/incubadora_ispsn/public/website/candidatura.php" class="nav-cta-solid mt-3 text-center" onclick="toggleMobileMenu()"><i class="fa fa-rocket me-2"></i> Candidatar-se</a>
    <a href="/incubadora_ispsn/public/login.php" class="nav-portal-mobile" onclick="toggleMobileMenu()"><i class="fa fa-user-shield me-2"></i> ACESSO AO PORTAL</a>
</div>

<article class="article-page">
    <div class="container" style="max-width:1300px">
        <header class="article-header">
            <span class="article-category"><?= htmlspecialchars($noticia['categoria']) ?></span>
            <h1 class="article-title"><?= htmlspecialchars($noticia['titulo']) ?></h1>
            <div class="article-meta">
                <span><i class="fa fa-calendar"></i> <?= date('d/m/Y', strtotime($noticia['criado_em'])) ?></span>
                <span><i class="fa fa-user"></i> <?= htmlspecialchars($noticia['autor_nome'] ?: 'Redacção ISPSN') ?></span>
            </div>
        </header>

        <div class="article-main-grid">
            <!-- Imagem à Esquerda -->
            <div class="article-image-side">
                <?php $capaNoticia = resolver_imagem_site($noticia['imagem'] ?? '', '/incubadora_ispsn/assets/img/blog/candidaturas_brevemente.png'); ?>
                <img src="<?= htmlspecialchars($capaNoticia) ?>" alt="Capa" onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/blog/candidaturas_brevemente.png';">
            </div>

            <!-- Conteúdo à Direita -->
            <div class="article-text-side">
                <h6>Detalhes da Notícia</h6>
                <div class="article-body">
                    <?= nl2br(htmlspecialchars((string)$noticia['conteudo'])) ?>
                </div>
                
                <div class="mt-5 pt-4 border-top">
                    <span class="small text-muted d-block mb-3">Partilhar esta notícia:</span>
                    <div class="d-flex gap-3">
                        <a href="#" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="fab fa-facebook-f me-2"></i> Facebook</a>
                        <a href="#" class="btn btn-sm btn-outline-info rounded-pill px-3"><i class="fab fa-linkedin-in me-2"></i> LinkedIn</a>
                        <a href="#" class="btn btn-sm btn-outline-success rounded-pill px-3"><i class="fab fa-whatsapp me-2"></i> WhatsApp</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Seção de Relacionadas abaixo (opcional, para ocupar o espaço) -->
        <section class="related-section">
            <h4 class="sidebar-title">Outras Notícias</h4>
            <div class="related-grid">
                <?php foreach ($sugestoes as $s):
                    $imgSug = resolver_imagem_site($s['imagem'] ?? '', '/incubadora_ispsn/assets/img/blog/candidaturas_brevemente.png');
                ?>
                <a href="noticia.php?id=<?= (int)$s['id'] ?>" class="side-noticia">
                    <img src="<?= htmlspecialchars($imgSug) ?>" class="side-noticia-img" onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/blog/candidaturas_brevemente.png';">
                    <div class="side-noticia-info">
                        <h6><?= htmlspecialchars($s['titulo']) ?></h6>
                        <span><?= date('d/m/Y', strtotime($s['criado_em'])) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</article>

<footer class="footer">
    <div class="footer-container">
        <div class="footer-main-grid">
            <div class="footer-brand">
                <div class="f-logo">
                    <a href="index.php#hero" class="nav-logo" style="padding: 0; color: #fff;">
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
                    <li><a href="index.php#hero">Início</a></li>
                    <li><a href="index.php#startups">Startups</a></li>
                    <li><a href="index.php#adn">Ecossistema</a></li>
                    <li><a href="sobre.php">Sobre Nós</a></li>
                    <li><a href="index.php#galeria">Galeria</a></li>
                    <li><a href="index.php#noticias">Notícias</a></li>
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
                        <strong><?= htmlspecialchars(get_config('contacto_email', 'incubadora@ispsn.org')) ?></strong>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fa fa-phone-volume"></i>
                    <div>
                        <span>Telefone</span>
                        <strong><?= htmlspecialchars(get_config('contacto_telefone', '+244 923 000 000')) ?></strong>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fa fa-location-dot"></i>
                    <div>
                        <span>Localização</span>
                        <strong><?= htmlspecialchars(get_config('contacto_endereco', 'Huambo, Angola')) ?></strong>
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
<script>
const hamburger = document.getElementById('hamburger');
const navMobile = document.getElementById('navMobile');
function toggleMobileMenu() {
    hamburger.classList.toggle('active');
    navMobile.classList.toggle('open');
    document.body.style.overflow = navMobile.classList.contains('open') ? 'hidden' : 'auto';
}
if (hamburger) hamburger.addEventListener('click', toggleMobileMenu);
</script>
</body>
</html>
