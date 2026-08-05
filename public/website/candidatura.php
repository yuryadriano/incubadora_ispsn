<?php
require_once __DIR__ . '/../../config/config.php';

// Cache apenas em GET (formulário de candidatura)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Cache-Control: public, max-age=60, s-maxage=120, stale-while-revalidate=300, stale-if-error=3600');
    header('CDN-Cache-Control: public, max-age=300, stale-while-revalidate=300, stale-if-error=86400');
    header('Vary: Accept-Encoding');
}

// Verificar se há processo aberto
$processo = null;
$res = $mysqli->query("SELECT * FROM processos_candidatura WHERE estado='aberto' ORDER BY criado_em DESC LIMIT 1");
if ($res) $processo = $res->fetch_assoc();

$erro = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $processo) {
    $tipo_candidato   = limpar($_POST['tipo_candidato'] ?? 'estudante');
    $nome             = limpar($_POST['nome'] ?? '');
    $email            = strtolower(limpar($_POST['email'] ?? ''));
    $telefone         = limpar($_POST['telefone'] ?? '');
    $numero_estudante = $tipo_candidato === 'estudante' ? limpar($_POST['numero_estudante'] ?? '') : '';
    $curso            = limpar($_POST['curso'] ?? '');
    $ano_estudo       = limpar($_POST['ano_estudo'] ?? '');
    $tipo_projeto     = limpar($_POST['tipo_projeto'] ?? 'startup_tecnologica');
    $titulo_ideia     = limpar($_POST['titulo_ideia'] ?? '');
    $descricao_ideia  = limpar($_POST['descricao_ideia'] ?? '');
    $problema         = limpar($_POST['problema'] ?? '');
    $solucao          = limpar($_POST['solucao'] ?? '');
    $publico_alvo     = limpar($_POST['publico_alvo'] ?? '');
    $modelo_negocio  = limpar($_POST['modelo_negocio'] ?? '');
    $diferencial     = limpar($_POST['diferencial'] ?? '');
    $area_tematica    = limpar($_POST['area_tematica'] ?? 'tecnologia');
    $id_processo      = (int)$processo['id'];
    $ip               = $_SERVER['REMOTE_ADDR'] ?? '';

    // Validações
    if (strlen($nome) < 3) $erro = 'Nome muito curto.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Email inválido.';
    elseif ($tipo_candidato === 'estudante' && strlen($numero_estudante) < 5) $erro = 'Número de estudante inválido.';
    elseif ($tipo_candidato === 'estudante' && !preg_match('/@ispsn\.org$/i', $email)) $erro = 'Por favor, use o seu e-mail institucional (@ispsn.org).';
    elseif (strlen($telefone) < 9) $erro = 'Telefone inválido.';
    elseif (strlen($titulo_ideia) < 5) $erro = 'Título da ideia muito curto.';
    elseif (strlen($descricao_ideia) < 30) $erro = 'A descrição da ideia deve ter pelo menos 30 caracteres.';
    else {
        $pitch_path = '';

        if (empty($erro)) {
            // Verificar candidatura duplicada (mesmo email ou nº estudante se preenchido neste processo)
            if ($tipo_candidato === 'estudante') {
                $chk = $mysqli->prepare("SELECT id FROM candidaturas WHERE id_processo=? AND (email=? OR (numero_estudante != '' AND numero_estudante=?)) LIMIT 1");
                $chk->bind_param('iss', $id_processo, $email, $numero_estudante);
            } else {
                $chk = $mysqli->prepare("SELECT id FROM candidaturas WHERE id_processo=? AND email=? LIMIT 1");
                $chk->bind_param('is', $id_processo, $email);
            }
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                $erro = 'Já existe uma candidatura registada com este email ou número de estudante para este processo.';
            } else {
                // Assegurar colunas na BD
                @$mysqli->query("ALTER TABLE candidaturas ADD COLUMN IF NOT EXISTS tipo_projeto ENUM('startup_tecnologica','negocio_tradicional','individual','equipa','impacto_social') DEFAULT 'startup_tecnologica'");
                @$mysqli->query("ALTER TABLE candidaturas ADD COLUMN IF NOT EXISTS publico_alvo TEXT DEFAULT NULL");
                @$mysqli->query("ALTER TABLE candidaturas ADD COLUMN IF NOT EXISTS modelo_negocio TEXT DEFAULT NULL");
                @$mysqli->query("ALTER TABLE candidaturas ADD COLUMN IF NOT EXISTS diferencial TEXT DEFAULT NULL");

                $stmt = $mysqli->prepare("
                    INSERT INTO candidaturas 
                    (id_processo, nome, email, telefone, numero_estudante, curso, ano_estudo,
                     titulo_ideia, descricao_ideia, problema, solucao, area_tematica, pitch_path, ip_submissao, tipo_candidato,
                     tipo_projeto, publico_alvo, modelo_negocio, diferencial)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->bind_param('issssssssssssssssss',
                    $id_processo, $nome, $email, $telefone, $numero_estudante,
                    $curso, $ano_estudo, $titulo_ideia, $descricao_ideia,
                    $problema, $solucao, $area_tematica, $pitch_path, $ip, $tipo_candidato,
                    $tipo_projeto, $publico_alvo, $modelo_negocio, $diferencial
                );
                if ($stmt->execute()) {
                    // Notificar admins internamente
                    $admins = $mysqli->query("SELECT id FROM usuarios WHERE perfil IN ('admin','superadmin') AND activo=1");
                    if ($admins) {
                        $sn = $mysqli->prepare("INSERT INTO notificacoes (id_usuario,titulo,mensagem,tipo) VALUES (?,?,?,'info')");
                        $tit = "Nova Candidatura: $titulo_ideia";
                        $msg = "Nova candidatura recebida de $nome ($email) como " . ($tipo_candidato === 'pre_licenciado' ? 'Pré-licenciado' : 'Estudante ISPSN') . ". Aceda ao painel para rever.";
                        while ($a = $admins->fetch_assoc()) {
                            $sn->bind_param('iss', $a['id'], $tit, $msg);
                            $sn->execute();
                        }
                    }
                    $sucesso = true;
                } else {
                    $erro = 'Erro ao registar candidatura. Tente novamente.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Candidatura — Incubadora Académica ISPSN</title>
<meta name="description" content="Candidata a tua ideia à Incubadora Académica ISPSN e transforma o teu projeto numa startup de sucesso.">
<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="/incubadora_ispsn/public/website/assets/style.css">
<style>
.cand-page { min-height: 100vh; background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); padding: 90px 24px 60px; font-family: 'Inter', sans-serif; }
.cand-wrapper { max-width: 820px; margin: 0 auto; }
.cand-header { text-align: center; margin-bottom: 36px; }
.cand-header h1 { font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: 8px; letter-spacing: -0.02em; }
.cand-header p { color: rgba(255,255,255,0.6); font-size: 1rem; }
.cand-card { background: rgba(30, 41, 59, 0.65); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(16px); border-radius: 24px; padding: 36px; margin-bottom: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
.cand-card h3 { color: #fff; font-size: 1.15rem; font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 14px; }
.cand-card h3 i { color: var(--primary); }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
.form-group label { font-size: 0.76rem; font-weight: 700; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 0.06em; }
.form-group input,
.form-group select,
.form-group textarea { width: 100%; padding: 13px 16px; background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 12px; color: #fff; font-size: 0.95rem; font-family: 'Inter', sans-serif; outline: none; transition: all 0.25s ease; }
.form-group input::placeholder,
.form-group textarea::placeholder { color: rgba(255,255,255,0.3); }
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { border-color: var(--primary); background: rgba(217,119,6,0.08); box-shadow: 0 0 0 4px rgba(217,119,6,0.15); }
.form-group select option { background: #1E293B; color: #fff; }
.form-group textarea { resize: vertical; min-height: 105px; }
.char-count { font-size: 0.72rem; color: rgba(255,255,255,0.35); text-align: right; }

/* GRID SELETOR INTERATIVO DE TIPO DE PROJETO */
.tipo-proj-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 24px; }
.tipo-proj-card { background: rgba(255,255,255,0.04); border: 1.5px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 16px 12px; text-align: center; cursor: pointer; transition: all 0.3s cubic-bezier(0.4,0,0.2,1); position: relative; }
.tipo-proj-card:hover { border-color: rgba(217,119,6,0.5); transform: translateY(-3px); background: rgba(217,119,6,0.06); }
.tipo-proj-card.active { border-color: var(--primary); background: linear-gradient(135deg, rgba(217,119,6,0.2) 0%, rgba(217,119,6,0.05) 100%); box-shadow: 0 0 20px rgba(217,119,6,0.25); }
.tipo-proj-card .icon { font-size: 1.6rem; margin-bottom: 8px; }
.tipo-proj-card .title { font-size: 0.8rem; font-weight: 700; color: #fff; line-height: 1.2; }
.tipo-proj-card .desc { font-size: 0.68rem; color: rgba(255,255,255,0.45); margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

/* STEPPER INTERATIVO MODERNO */
.wizard-stepper { display: flex; align-items: center; justify-content: space-between; position: relative; margin-bottom: 36px; padding: 0 20px; }
.wizard-step { display: flex; flex-direction: column; align-items: center; gap: 8px; z-index: 2; cursor: pointer; }
.wizard-step .step-circle { width: 46px; height: 46px; border-radius: 50%; background: rgba(255,255,255,0.06); border: 2px solid rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5); font-weight: 700; font-size: 1rem; transition: all 0.3s ease; }
.wizard-step.active .step-circle { background: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 0 20px rgba(217,119,6,0.5); transform: scale(1.1); }
.wizard-step.done .step-circle { background: #10B981; border-color: #10B981; color: #fff; }
.wizard-step .step-label-text { font-size: 0.75rem; font-weight: 600; color: rgba(255,255,255,0.5); transition: all 0.3s; }
.wizard-step.active .step-label-text { color: #fff; font-weight: 800; }
.stepper-bar-bg { position: absolute; top: 23px; left: 60px; right: 60px; height: 3px; background: rgba(255,255,255,0.08); z-index: 1; border-radius: 2px; }
.stepper-bar-progress { height: 100%; background: linear-gradient(90deg, var(--primary) 0%, #10B981 100%); width: 0%; border-radius: 2px; transition: width 0.4s ease; }

.form-section { display: none; }
.form-section.active { display: block; animation: fadeIn 0.4s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.btn-next, .btn-prev, .btn-submit { display: inline-flex; align-items: center; gap: 10px; padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; border: none; transition: all 0.3s ease; font-family: 'Inter', sans-serif; }
.btn-next, .btn-submit { background: linear-gradient(135deg, var(--primary) 0%, #B45309 100%); color: #fff; box-shadow: 0 4px 15px rgba(217,119,6,0.3); }
.btn-next:hover, .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(217,119,6,0.4); }
.btn-prev { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.8); }
.btn-prev:hover { background: rgba(255,255,255,0.15); color: #fff; }
.form-nav { display: flex; align-items: center; justify-content: space-between; margin-top: 28px; flex-wrap: wrap; gap: 12px; }
.alert-erro { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
.closed-box { text-align: center; padding: 60px 24px; }
.closed-box i { font-size: 3rem; color: rgba(255,255,255,0.2); margin-bottom: 20px; display: block; }
.closed-box h2 { color: #fff; margin-bottom: 12px; }
.closed-box p { color: rgba(255,255,255,0.5); }
.success-box { text-align: center; padding: 60px 24px; }
.success-box i { font-size: 4.5rem; color: #22C55E; margin-bottom: 20px; display: block; animation: popIn 0.5s cubic-bezier(0.175,0.885,0.32,1.275); }
@keyframes popIn { from { transform: scale(0); } to { transform: scale(1); } }
.success-box h2 { color: #fff; font-size: 2.2rem; font-weight: 800; margin-bottom: 12px; }
.success-box p { color: rgba(255,255,255,0.6); max-width: 520px; margin: 0 auto 24px; line-height: 1.7; }
.success-box a { display: inline-flex; align-items: center; gap: 8px; color: var(--primary); text-decoration: none; font-weight: 600; }

@media(max-width:600px) { .form-row { grid-template-columns: 1fr; } .cand-card { padding: 24px; } .tipo-proj-grid { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>
<nav class="navbar scrolled" id="navbar">
    <div class="nav-container">
        <a href="/incubadora_ispsn/public/website/" class="nav-logo">
            <img src="/incubadora_ispsn/assets/img/logo_sn_transparent.png" alt="ISPSN">
        </a>
        <div class="nav-links">
            <a href="/incubadora_ispsn/public/website/">← Voltar ao Site</a>
            <a href="/incubadora_ispsn/public/login.php" class="nav-cta-solid"><i class="fa fa-right-to-bracket"></i> Portal</a>
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
    <a href="/incubadora_ispsn/public/website/" onclick="toggleMobileMenu()">← Voltar ao Site</a>
    <a href="/incubadora_ispsn/public/login.php" class="nav-portal-mobile" onclick="toggleMobileMenu()"><i class="fa fa-user-shield me-2"></i> ACESSO AO PORTAL</a>
</div>

<div class="cand-page">
    <div class="cand-wrapper">

        <?php if ($sucesso): ?>
        <div class="success-box">
            <i class="fa fa-circle-check"></i>
            <h2>Candidatura Registada!</h2>
            <p>A tua candidatura foi recebida com sucesso. A equipa da Incubadora ISPSN irá analisá-la e entraremos em contacto via <strong>WhatsApp ou email</strong> nos próximos dias úteis.</p>
            <p style="color:rgba(255,255,255,0.4);font-size:0.85rem;margin-bottom:32px;">Guarda o teu número de estudante — será necessário para criar a conta no portal caso sejas selecionado.</p>
            <a href="/incubadora_ispsn/public/website/">← Voltar ao site</a>
        </div>

        <?php elseif (!$processo): ?>
        <div class="closed-box">
            <i class="fa fa-calendar-check" style="color: var(--primary);"></i>
            <h2 style="font-size: 1.8rem; margin-top: 15px;">Candidaturas a partir do dia 5 de Agosto</h2>
            <p style="font-size: 1.1rem; max-width: 500px; margin: 15px auto 25px; line-height: 1.6;">O processo de candidatura está actualmente fechado. As inscrições estarão abertas oficialmente a partir do dia <strong>5 de Agosto</strong>. Prepare o seu projecto!</p>
            <br>
            <a href="/incubadora_ispsn/public/website/" style="color:var(--primary);text-decoration:none;font-weight:600;"><i class="fa fa-arrow-left me-2"></i> Voltar ao site</a>
        </div>

        <?php else: ?>

        <div class="cand-header">
            <div class="hero-badge" style="margin-bottom:20px;">
                <span class="pulse-dot"></span>
                Inscrições Abertas — <?= htmlspecialchars($processo['nome']) ?>
            </div>
            <h1>Candidata a Tua Ideia</h1>
            <p>Preenche o formulário abaixo. Não precisas de conta no sistema.</p>
        </div>

        <?php if ($erro): ?>
        <div class="alert-erro"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <?php if ($erro): ?>
        <div class="alert-erro"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <!-- STEPPER INTERATIVO MODERNO -->
        <div class="wizard-stepper">
            <div class="stepper-bar-bg"><div class="stepper-bar-progress" id="stepperProgress" style="width:0%"></div></div>
            <div class="wizard-step active" id="stepIndicator1" onclick="currentStep > 1 && prevStep(currentStep)">
                <div class="step-circle" id="circle1">1</div>
                <span class="step-label-text">Dados Pessoais</span>
            </div>
            <div class="wizard-step" id="stepIndicator2">
                <div class="step-circle" id="circle2">2</div>
                <span class="step-label-text">O Teu Pitch</span>
            </div>
            <div class="wizard-step" id="stepIndicator3">
                <div class="step-circle" id="circle3">3</div>
                <span class="step-label-text">Confirmação</span>
            </div>
        </div>

        <form method="post" id="candidaturaForm" novalidate>

            <!-- PASSO 1: Dados Pessoais -->
            <div class="form-section active" id="section1">
                <div class="cand-card">
                    <h3><i class="fa fa-user"></i> Dados Pessoais</h3>
                    
                    <div class="form-group">
                        <label>Tipo de Candidato *</label>
                        <select name="tipo_candidato" id="tipo_candidato" onchange="toggleTipoCandidato(this.value)">
                            <option value="estudante">Estudante ISPSN</option>
                            <option value="pre_licenciado">Pré-licenciado</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" placeholder="O teu nome completo" required minlength="3">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label id="lbl_numero_estudante">Número de Estudante *</label>
                            <input type="text" name="numero_estudante" id="numero_estudante" placeholder="Ex: 122400024" required>
                        </div>
                        <div class="form-group">
                            <label id="lbl_email">Email Institucional *</label>
                            <input type="email" name="email" id="email" placeholder="número@ispsn.org" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Telefone / WhatsApp *</label>
                            <input type="tel" name="telefone" placeholder="9XXXXXXXX" required>
                        </div>
                        <div class="form-group">
                            <label>Ano de Estudo</label>
                            <select name="ano_estudo">
                                <option value="">Selecionar...</option>
                                <option>1º Ano</option><option>2º Ano</option>
                                <option>3º Ano</option><option>4º Ano</option>
                                <option>5º Ano</option><option>Pós-Graduação</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Curso</label>
                        <input type="text" name="curso" placeholder="Ex: Engenharia Informática">
                    </div>
                </div>
                <div class="form-nav">
                    <span></span>
                    <button type="button" class="btn-next" onclick="nextStep(1)">
                        Próximo <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- PASSO 2: O Teu Pitch / A Tua Ideia -->
            <div class="form-section" id="section2">
                <div class="cand-card">
                    <h3><i class="fa fa-rocket me-2" style="color:var(--primary)"></i> O Teu Pitch / A Tua Ideia</h3>
                    <p style="color:rgba(255,255,255,0.6);font-size:0.85rem;margin-bottom:20px;">
                        Seleciona a natureza do teu projeto e responde às perguntas do Pitch. Quanto mais claro fores, melhor será a avaliação da tua candidatura.
                    </p>

                    <div class="form-group">
                        <label>Tipo de Projeto / Entidade *</label>
                        <input type="hidden" name="tipo_projeto" id="input_tipo_projeto" value="startup_tecnologica">
                        <div class="tipo-proj-grid">
                            <div class="tipo-proj-card active" onclick="selectTipoProjeto('startup_tecnologica', this)">
                                <div class="icon">🚀</div>
                                <div class="title">Startup Tech</div>
                                <div class="desc">Software, App, Plataforma Digital</div>
                            </div>
                            <div class="tipo-proj-card" onclick="selectTipoProjeto('negocio_tradicional', this)">
                                <div class="icon">🏢</div>
                                <div class="title">Empresa / Negócio</div>
                                <div class="desc">Comércio, Serviços, Indústria</div>
                            </div>
                            <div class="tipo-proj-card" onclick="selectTipoProjeto('individual', this)">
                                <div class="icon">👤</div>
                                <div class="title">Individual</div>
                                <div class="desc">Empreendedor Autónomo</div>
                            </div>
                            <div class="tipo-proj-card" onclick="selectTipoProjeto('equipa', this)">
                                <div class="icon">👥</div>
                                <div class="title">Equipa / Grupo</div>
                                <div class="desc">Estudantes Cofundadores</div>
                            </div>
                            <div class="tipo-proj-card" onclick="selectTipoProjeto('impacto_social', this)">
                                <div class="icon">🌍</div>
                                <div class="title">Impacto Social</div>
                                <div class="desc">Projeto Comunitário</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Título da Ideia / Startup *</label>
                            <input type="text" name="titulo_ideia" placeholder="Nome do teu projeto" required minlength="5">
                        </div>
                        <div class="form-group">
                            <label>Área Temática *</label>
                            <select name="area_tematica" required>
                                <option value="tecnologia">💻 Tecnologia</option>
                                <option value="saude">❤️ Saúde</option>
                                <option value="educacao">📚 Educação</option>
                                <option value="agro">🌱 Agro-negócio</option>
                                <option value="financas">📈 Finanças</option>
                                <option value="outro">🔮 Outro</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Descrição Geral da Ideia * (mín. 30 caracteres)</label>
                        <textarea name="descricao_ideia" id="desc" placeholder="Resumo executivo do teu projeto..." required oninput="updateCount('desc','descCount',30)"></textarea>
                        <div class="char-count"><span id="descCount">0</span> caracteres</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Que Problema Resolve?</label>
                            <textarea name="problema" placeholder="Qual é a dor ou necessidade real no mercado que a tua ideia endereça?"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Qual é a Solução Proposta?</label>
                            <textarea name="solucao" placeholder="Como o teu produto/serviço resolve este problema de forma eficaz?"></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Público-Alvo / Clientes</label>
                            <textarea name="publico_alvo" placeholder="Quem são os clientes diretos, utilizadores ou beneficiários do projeto?"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Modelo de Negócio / Receita</label>
                            <textarea name="modelo_negocio" placeholder="Como o projeto pretende gerar sustentabilidade financeira ou vendas?"></textarea>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Diferencial Competitivo</label>
                        <textarea name="diferencial" placeholder="O que torna a tua solução única ou diferente das alternativas existentes?"></textarea>
                    </div>

                </div>
                <div class="form-nav">
                    <button type="button" class="btn-prev" onclick="prevStep(2)">
                        <i class="fa fa-arrow-left"></i> Anterior
                    </button>
                    <button type="button" class="btn-next" onclick="nextStep(2)">
                        Próximo <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- PASSO 3: Confirmação -->
            <div class="form-section" id="section3">
                <div class="cand-card">
                    <h3><i class="fa fa-clipboard-check"></i> Confirmação</h3>
                    <div id="resumo" style="color:rgba(255,255,255,0.7);font-size:0.9rem;line-height:1.8;"></div>
                    <div style="margin-top:24px;padding:16px;background:rgba(217,119,6,0.1);border:1px solid rgba(217,119,6,0.3);border-radius:10px;font-size:0.85rem;color:rgba(255,255,255,0.6);">
                        <i class="fa fa-info-circle" style="color:var(--primary)"></i>
                        Ao submeter, confirmas que os dados são verdadeiros e que és estudante ativo do ISPSN. O teu número de estudante será verificado se fores selecionado.
                    </div>
                </div>
                <div class="form-nav">
                    <button type="button" class="btn-prev" onclick="prevStep(3)">
                        <i class="fa fa-arrow-left"></i> Anterior
                    </button>
                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <i class="fa fa-paper-plane"></i> Submeter Candidatura
                    </button>
                </div>
            </div>

        </form>
        <?php endif; ?>
    </div>
</div>

<script>
let currentStep = 1;
const totalSteps = 3;

function updateProgress(step) {
    const pct = ((step - 1) / (totalSteps - 1)) * 100;
    const progressEl = document.getElementById('stepperProgress');
    if (progressEl) progressEl.style.width = pct + '%';
    
    for (let i = 1; i <= totalSteps; i++) {
        const ind = document.getElementById('stepIndicator' + i);
        const circle = document.getElementById('circle' + i);
        if (!ind || !circle) continue;
        
        if (i === step) {
            ind.className = 'wizard-step active';
            circle.innerHTML = i;
        } else if (i < step) {
            ind.className = 'wizard-step done';
            circle.innerHTML = '<i class="fa fa-check"></i>';
        } else {
            ind.className = 'wizard-step';
            circle.innerHTML = i;
        }
    }
}

function selectTipoProjeto(val, cardEl) {
    document.getElementById('input_tipo_projeto').value = val;
    document.querySelectorAll('.tipo-proj-card').forEach(c => c.classList.remove('active'));
    if (cardEl) cardEl.classList.add('active');
}

function toggleTipoCandidato(value) {
    const numEstudanteInput = document.getElementById('numero_estudante');
    const lblNumeroEstudante = document.getElementById('lbl_numero_estudante');
    const emailInput = document.getElementById('email');
    const lblEmail = document.getElementById('lbl_email');
    
    if (value === 'pre_licenciado') {
        numEstudanteInput.disabled = true;
        numEstudanteInput.required = false;
        numEstudanteInput.value = '';
        numEstudanteInput.placeholder = 'N/A - Pré-licenciado';
        lblNumeroEstudante.innerHTML = 'Número de Estudante (Inativo)';
        
        emailInput.placeholder = 'exemplo@email.com';
        lblEmail.innerHTML = 'Email Pessoal *';
    } else {
        numEstudanteInput.disabled = false;
        numEstudanteInput.required = true;
        numEstudanteInput.placeholder = 'Ex: 122400024';
        lblNumeroEstudante.innerHTML = 'Número de Estudante *';
        
        emailInput.placeholder = 'número@ispsn.org';
        lblEmail.innerHTML = 'Email Institucional *';
    }
}

function validateStep(step) {
    const section = document.getElementById('section' + step);
    const inputs = section.querySelectorAll('input[required], select[required], textarea[required]');
    for (const input of inputs) {
        if (input.disabled) continue;
        if (!input.value.trim()) {
            input.focus();
            input.style.borderColor = '#EF4444';
            setTimeout(() => input.style.borderColor = '', 2000);
            alert('Por favor, preenche todos os campos obrigatórios (*).');
            return false;
        }
    }
    // Email domain check
    if (step === 1) {
        const tipo = document.getElementById('tipo_candidato').value;
        const email = section.querySelector('[name="email"]').value;
        if (tipo === 'estudante') {
            if (!email.endsWith('@ispsn.org')) {
                alert('Por favor, usa o teu email institucional (@ispsn.org).');
                return false;
            }
        }
    }
    if (step === 2) {
        const desc = document.querySelector('[name="descricao_ideia"]').value;
        if (desc.length < 30) { alert('A descrição deve ter pelo menos 30 caracteres.'); return false; }
    }
    return true;
}

function nextStep(from) {
    if (!validateStep(from)) return;
    document.getElementById('section' + from).classList.remove('active');
    const next = from + 1;
    document.getElementById('section' + next).classList.add('active');
    currentStep = next;
    updateProgress(next);
    if (next === 3) buildResumo();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevStep(from) {
    document.getElementById('section' + from).classList.remove('active');
    const prev = from - 1;
    document.getElementById('section' + prev).classList.add('active');
    currentStep = prev;
    updateProgress(prev);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function buildResumo() {
    const f = document.getElementById('candidaturaForm');
    const g = (n) => {
        const el = f.querySelector('[name="' + n + '"]');
        if (el && el.disabled) return 'N/A';
        return el ? el.value || '—' : '—';
    };
    const tipoText = g('tipo_candidato') === 'pre_licenciado' ? 'Pré-licenciado' : 'Estudante ISPSN';
    
    const tiposProjMap = {
        'startup_tecnologica': '🚀 Startup Tecnológica',
        'negocio_tradicional': '🏢 Empresa Registada / Negócio Tradicional',
        'individual': '👤 Projeto Individual',
        'equipa': '👥 Equipa / Grupo de Estudantes',
        'impacto_social': '🌍 Projeto de Impacto Social'
    };
    const projText = tiposProjMap[g('tipo_projeto')] || g('tipo_projeto');

    document.getElementById('resumo').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Tipo Candidato</strong><br>${tipoText}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Nome</strong><br>${g('nome')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Nº Estudante</strong><br>${g('numero_estudante')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Email</strong><br>${g('email')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Telefone</strong><br>${g('telefone')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Tipo Projeto</strong><br>${projText}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Área</strong><br>${g('area_tematica')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Curso</strong><br>${g('curso') || '—'}</div>
        </div>
        <div style="margin-top:16px;"><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Título da Ideia / Startup</strong><br><strong>${g('titulo_ideia')}</strong></div>
        <div style="margin-top:10px;"><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Descrição Geral</strong><br>${g('descricao_ideia')}</div>
        <div style="margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Problema</strong><br>${g('problema') || '—'}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Solução</strong><br>${g('solucao') || '—'}</div>
        </div>
        <div style="margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Público-Alvo</strong><br>${g('publico_alvo') || '—'}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Modelo de Negócio</strong><br>${g('modelo_negocio') || '—'}</div>
        </div>
    `;
}

function updateCount(inputId, countId, min) {
    const val = document.getElementById(inputId).value.length;
    const el = document.getElementById(countId);
    el.textContent = val;
    el.style.color = val >= min ? '#22C55E' : 'rgba(255,255,255,0.35)';
}

// Mobile menu logic
const hamburger = document.getElementById('hamburger');
const navMobile = document.getElementById('navMobile');

function toggleMobileMenu() {
    hamburger.classList.toggle('active');
    navMobile.classList.toggle('open');
    document.body.style.overflow = navMobile.classList.contains('open') ? 'hidden' : 'auto';
}

if (hamburger) hamburger.addEventListener('click', toggleMobileMenu);

document.getElementById('candidaturaForm')?.addEventListener('submit', () => {
    document.getElementById('btnSubmit').innerHTML = '<i class="fa fa-spinner fa-spin"></i> A enviar...';
    document.getElementById('btnSubmit').disabled = true;
});
</script>
</body>
</html>
