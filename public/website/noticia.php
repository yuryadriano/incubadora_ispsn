<?php
require_once __DIR__ . '/../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit; }

$res = $mysqli->query("
    SELECT p.*, u.nome as autor_nome 
    FROM publicacoes_website p 
    LEFT JOIN usuarios u ON u.id = p.criado_por 
    WHERE p.id = $id AND p.status = 'publicado'
");
$noticia = $res->fetch_assoc();

if (!$noticia) { header("Location: index.php"); exit; }

// Buscar outras notícias para a barra lateral ou sugestões
$sugestoes = [];
$resS = $mysqli->query("SELECT * FROM publicacoes_website WHERE status='publicado' AND id != $id ORDER BY criado_em DESC LIMIT 3");
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
            <img src="/incubadora_ispsn/assets/img/logo_sn.jpg" alt="ISPSN">
            <span>Incubadora <strong>ISPSN</strong></span>
        </a>
        <div class="nav-links">
            <a href="index.php">Início</a>
            <a href="sobre.php">Sobre Nós</a>
            <a href="index.php#noticias">Notícias</a>
            <a href="candidatura.php" class="nav-cta-solid">Candidatar-se</a>
        </div>
    </div>
</nav>

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
                <img src="<?= $noticia['imagem'] ?: '/incubadora_ispsn/assets/img/blog/default.jpg' ?>" alt="Capa">
            </div>

            <!-- Conteúdo à Direita -->
            <div class="article-text-side">
                <h6>Detalhes da Notícia</h6>
                <div class="article-body">
                    <?= nl2br($noticia['conteudo']) ?>
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
                <?php foreach ($sugestoes as $s): ?>
                <a href="noticia.php?id=<?= $s['id'] ?>" class="side-noticia">
                    <img src="<?= $s['imagem'] ?: '/incubadora_ispsn/assets/img/blog/default.jpg' ?>" class="side-noticia-img">
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
        <div class="footer-bottom text-center">
            <p class="small mb-0">&copy; <?= date('Y') ?> Incubadora Académica ISPSN.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
