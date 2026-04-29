<?php
require_once __DIR__ . '/../config/config.php';

// Buscar apenas projetos marcados para destaque público
$sql = "SELECT p.*, u.nome as autor FROM projetos p JOIN usuarios u ON u.id = p.criado_por WHERE p.destaque_publico = 1 ORDER BY p.criado_em DESC";
$res = $mysqli->query($sql);
$projetos = $res->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitrine de Inovação | Incubadora ISPSN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #2563eb; --secondary: #8b5cf6; --dark: #0f172a; --bg: #f8fafc; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--bg); color: var(--dark); overflow-x: hidden; }
        
        /* HERO SECTION */
        .hero { background: linear-gradient(135deg, var(--dark) 0%, #1e293b 100%); padding: 120px 0 80px; color: white; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -50%; right: -10%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(37,99,235,0.2) 0%, transparent 70%); }
        .hero h1 { font-size: 3.5rem; font-weight: 800; margin-bottom: 20px; background: linear-gradient(to right, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        /* PROJECT CARDS */
        .card-vitrine { border: none; border-radius: 24px; overflow: hidden; background: white; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); height: 100%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card-vitrine:hover { transform: translateY(-10px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .card-img-top { height: 180px; background: linear-gradient(135deg, #e0e7ff 0%, #f5f3ff 100%); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--primary); }
        .badge-fase { font-weight: 700; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; padding: 6px 12px; border-radius: 100px; }
        .fase-ideacao { background: #fee2e2; color: #ef4444; }
        .fase-mvp { background: #fef3c7; color: #d97706; }
        .fase-mercado { background: #dcfce7; color: #10b981; }

        .btn-invest { background: var(--dark); color: white; border-radius: 12px; padding: 12px 24px; font-weight: 600; transition: all 0.3s; border: none; width: 100%; }
        .btn-invest:hover { background: var(--primary); color: white; }
    </style>
</head>
<body>

    <!-- NAVBAR SIMPLES -->
    <nav class="navbar navbar-expand-lg navbar-dark position-absolute w-100 py-4" style="z-index:100">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="#"><i class="fa fa-gravity me-2"></i>ISPSN <span class="text-primary">INNOVATION</span></a>
            <div class="ms-auto">
                <a href="login.php" class="btn btn-outline-light px-4 rounded-pill border-0">Entrar no Sistema</a>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="container text-center">
            <span class="badge bg-primary px-3 py-2 rounded-pill mb-4">STARTUPS DE SUCESSO</span>
            <h1>O Futuro de Angola <br> nasce no ISPSN.</h1>
            <p class="lead text-muted mx-auto" style="max-width: 600px;">Explore as ideias transformadoras da nossa incubadora académica e ligue-se ao próximo unicórnio.</p>
        </div>
    </section>

    <!-- LISTA DE PROJETOS -->
    <section class="py-5 mt-n5" style="z-index:5; position:relative">
        <div class="container">
            <div class="row g-4">
                <?php if(empty($projetos)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="card p-5 border-0 shadow-sm rounded-4">
                            <i class="fa fa-rocket fa-4x text-muted mb-3" style="opacity:0.2"></i>
                            <h3 class="text-muted">A preparar o lançamento...</h3>
                            <p class="text-muted">Estamos a selecionar as melhores startups para breve.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach($projetos as $p): 
                        $faseClass = 'fase-'.($p['fase'] ?? 'ideacao');
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card-vitrine card">
                            <div class="card-img-top">
                                <i class="fa fa-<?= ($p['area_tematica'] == 'tecnologia' ? 'microchip' : ($p['area_tematica'] == 'saude' ? 'heart-pulse' : 'lightbulb')) ?>"></i>
                            </div>
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="badge-fase <?= $faseClass ?>"><?= $p['fase'] ?? 'Ideação' ?></span>
                                    <small class="text-muted"><i class="fa fa-tag me-1"></i> <?= strtoupper($p['tipo']) ?></small>
                                </div>
                                <h4 class="fw-bold mb-2"><?= htmlspecialchars($p['titulo']) ?></h4>
                                <p class="text-muted small mb-4" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; height: 3.6rem;">
                                    <?= htmlspecialchars($p['descricao']) ?>
                                </p>
                                <button class="btn-invest"><i class="fa fa-handshake me-2"></i> Demonstrar Interesse</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="py-5 text-center text-muted">
        <hr class="container mb-5">
        <p>&copy; <?= date('Y') ?> Incubadora Académica ISPSN. Todos os direitos reservados.</p>
    </footer>

</body>
</html>
