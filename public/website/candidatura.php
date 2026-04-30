<?php
require_once __DIR__ . '/../../config/config.php';

// Verificar se há processo aberto
$processo = null;
$res = $mysqli->query("SELECT * FROM processos_candidatura WHERE estado='aberto' ORDER BY criado_em DESC LIMIT 1");
if ($res) $processo = $res->fetch_assoc();

$erro = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $processo) {
    $nome             = limpar($_POST['nome'] ?? '');
    $email            = strtolower(limpar($_POST['email'] ?? ''));
    $telefone         = limpar($_POST['telefone'] ?? '');
    $numero_estudante = limpar($_POST['numero_estudante'] ?? '');
    $curso            = limpar($_POST['curso'] ?? '');
    $ano_estudo       = limpar($_POST['ano_estudo'] ?? '');
    $titulo_ideia     = limpar($_POST['titulo_ideia'] ?? '');
    $descricao_ideia  = limpar($_POST['descricao_ideia'] ?? '');
    $problema         = limpar($_POST['problema'] ?? '');
    $solucao          = limpar($_POST['solucao'] ?? '');
    $area_tematica    = limpar($_POST['area_tematica'] ?? 'tecnologia');
    $id_processo      = (int)$processo['id'];
    $ip               = $_SERVER['REMOTE_ADDR'] ?? '';

    // Validações
    if (strlen($nome) < 3) $erro = 'Nome muito curto.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Email inválido.';
    elseif (strlen($numero_estudante) < 5) $erro = 'Número de estudante inválido.';
    elseif (strlen($telefone) < 9) $erro = 'Telefone inválido.';
    elseif (strlen($titulo_ideia) < 5) $erro = 'Título da ideia muito curto.';
    elseif (strlen($descricao_ideia) < 30) $erro = 'A descrição da ideia deve ter pelo menos 30 caracteres.';
    else {
        // Verificar candidatura duplicada (mesmo email ou nº estudante neste processo)
        $chk = $mysqli->prepare("SELECT id FROM candidaturas WHERE id_processo=? AND (email=? OR numero_estudante=?) LIMIT 1");
        $chk->bind_param('iss', $id_processo, $email, $numero_estudante);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $erro = 'Já existe uma candidatura registada com este email ou número de estudante para este processo.';
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO candidaturas 
                (id_processo, nome, email, telefone, numero_estudante, curso, ano_estudo,
                 titulo_ideia, descricao_ideia, problema, solucao, area_tematica, ip_submissao)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('issssssssssss',
                $id_processo, $nome, $email, $telefone, $numero_estudante,
                $curso, $ano_estudo, $titulo_ideia, $descricao_ideia,
                $problema, $solucao, $area_tematica, $ip
            );
            if ($stmt->execute()) {
                // Notificar admins internamente
                $admins = $mysqli->query("SELECT id FROM usuarios WHERE perfil IN ('admin','superadmin') AND activo=1");
                if ($admins) {
                    $sn = $mysqli->prepare("INSERT INTO notificacoes (id_usuario,titulo,mensagem,tipo) VALUES (?,?,?,'info')");
                    $tit = "Nova Candidatura: $titulo_ideia";
                    $msg = "Nova candidatura recebida de $nome ($email). Aceda ao painel para rever.";
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
.cand-page { min-height: 100vh; background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); padding: 100px 24px 60px; }
.cand-wrapper { max-width: 780px; margin: 0 auto; }
.cand-header { text-align: center; margin-bottom: 48px; }
.cand-header h1 { font-size: clamp(2rem,4vw,3rem); font-weight: 900; color: #fff; margin-bottom: 12px; }
.cand-header p { color: rgba(255,255,255,0.6); font-size: 1rem; }
.cand-card { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(12px); border-radius: 24px; padding: 40px; margin-bottom: 20px; }
.cand-card h3 { color: #fff; font-size: 1rem; font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
.cand-card h3 i { color: var(--primary); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.form-group label { font-size: 0.78rem; font-weight: 600; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.05em; }
.form-group input,
.form-group select,
.form-group textarea { width: 100%; padding: 12px 16px; background: rgba(255,255,255,0.07); border: 1.5px solid rgba(255,255,255,0.12); border-radius: 10px; color: #fff; font-size: 0.95rem; font-family: 'Inter', sans-serif; outline: none; transition: all 0.2s; }
.form-group input::placeholder,
.form-group textarea::placeholder { color: rgba(255,255,255,0.25); }
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus { border-color: var(--primary); background: rgba(217,119,6,0.08); }
.form-group select option { background: #1E293B; color: #fff; }
.form-group textarea { resize: vertical; min-height: 100px; }
.char-count { font-size: 0.72rem; color: rgba(255,255,255,0.35); text-align: right; }
.steps-indicator { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 40px; }
.step-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.2); transition: all 0.3s; cursor: pointer; }
.step-dot.active { background: var(--primary); transform: scale(1.3); }
.step-dot.done { background: #10B981; }
.form-section { display: none; }
.form-section.active { display: block; }
.btn-next, .btn-prev, .btn-submit { display: inline-flex; align-items: center; gap: 10px; padding: 14px 28px; border-radius: 10px; font-weight: 700; font-size: 0.95rem; cursor: pointer; border: none; transition: all 0.3s; font-family: 'Inter', sans-serif; }
.btn-next, .btn-submit { background: var(--primary); color: #fff; }
.btn-next:hover, .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); }
.btn-prev { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.7); }
.btn-prev:hover { background: rgba(255,255,255,0.15); }
.form-nav { display: flex; align-items: center; justify-content: space-between; margin-top: 24px; flex-wrap: wrap; gap: 12px; }
.alert-erro { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #FCA5A5; padding: 14px 18px; border-radius: 10px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
.closed-box { text-align: center; padding: 60px 24px; }
.closed-box i { font-size: 3rem; color: rgba(255,255,255,0.2); margin-bottom: 20px; display: block; }
.closed-box h2 { color: #fff; margin-bottom: 12px; }
.closed-box p { color: rgba(255,255,255,0.5); }
.success-box { text-align: center; padding: 60px 24px; }
.success-box i { font-size: 4rem; color: #22C55E; margin-bottom: 20px; display: block; animation: popIn 0.5s cubic-bezier(0.175,0.885,0.32,1.275); }
@keyframes popIn { from { transform: scale(0); } to { transform: scale(1); } }
.success-box h2 { color: #fff; font-size: 2rem; margin-bottom: 12px; }
.success-box p { color: rgba(255,255,255,0.6); max-width: 500px; margin: 0 auto 24px; line-height: 1.7; }
.success-box a { display: inline-flex; align-items: center; gap: 8px; color: var(--primary); text-decoration: none; font-weight: 600; }
.progress-bar { height: 3px; background: rgba(255,255,255,0.08); border-radius: 2px; margin-bottom: 32px; }
.progress-fill { height: 100%; background: var(--primary); border-radius: 2px; transition: width 0.4s ease; }
.step-label { text-align: center; font-size: 0.8rem; color: rgba(255,255,255,0.4); margin-bottom: 24px; }
.step-label strong { color: rgba(255,255,255,0.8); }
@media(max-width:600px) { .form-row { grid-template-columns: 1fr; } .cand-card { padding: 24px; } }
</style>
</head>
<body>
<nav class="navbar scrolled" id="navbar">
    <div class="nav-container">
        <a href="/incubadora_ispsn/public/website/" class="nav-logo">
            <img src="/incubadora_ispsn/assets/img/logo_icon.png" alt="Icon">
            <div class="logo-text-wrapper">
                <span class="t1">Incubadora de</span>
                <span class="t2">EMPRESAS</span>
                <span class="t3">SOL NASCENTE</span>
            </div>
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
            <i class="fa fa-lock"></i>
            <h2>Inscrições Encerradas</h2>
            <p>O processo de candidatura está actualmente fechado. Fique atento às próximas edições — siga-nos para ser notificado quando abrir nova edição.</p>
            <br>
            <a href="/incubadora_ispsn/public/website/" style="color:var(--primary);text-decoration:none;font-weight:600;">← Voltar ao site</a>
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

        <div class="progress-bar"><div class="progress-fill" id="progressFill" style="width:33%"></div></div>
        <div class="step-label">Passo <strong id="stepLabel">1</strong> de 3</div>

        <div class="steps-indicator">
            <div class="step-dot active" id="dot1"></div>
            <div class="step-dot" id="dot2"></div>
            <div class="step-dot" id="dot3"></div>
        </div>

        <form method="post" id="candidaturaForm" novalidate>

            <!-- PASSO 1: Dados Pessoais -->
            <div class="form-section active" id="section1">
                <div class="cand-card">
                    <h3><i class="fa fa-user"></i> Dados Pessoais</h3>
                    <div class="form-group">
                        <label>Nome Completo *</label>
                        <input type="text" name="nome" placeholder="O teu nome completo" required minlength="3">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Número de Estudante *</label>
                            <input type="text" name="numero_estudante" placeholder="Ex: 122400024" required>
                        </div>
                        <div class="form-group">
                            <label>Email Institucional *</label>
                            <input type="email" name="email" placeholder="número@ispsn.org" required>
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

            <!-- PASSO 2: A Ideia -->
            <div class="form-section" id="section2">
                <div class="cand-card">
                    <h3><i class="fa fa-lightbulb"></i> A Tua Ideia</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Título da Ideia / Startup *</label>
                            <input type="text" name="titulo_ideia" placeholder="Nome do teu projeto" required>
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
                        <textarea name="descricao_ideia" id="desc" placeholder="Descreve a tua ideia de forma clara e objetiva..." required oninput="updateCount('desc','descCount',30)"></textarea>
                        <div class="char-count"><span id="descCount">0</span> caracteres</div>
                    </div>
                    <div class="form-group">
                        <label>Que Problema Resolve?</label>
                        <textarea name="problema" placeholder="Qual é o problema que a tua solução endereça?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Qual é a Solução Proposta?</label>
                        <textarea name="solucao" placeholder="Como a tua ideia resolve este problema?"></textarea>
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
    document.getElementById('progressFill').style.width = ((step / totalSteps) * 100) + '%';
    document.getElementById('stepLabel').textContent = step;
    for (let i = 1; i <= totalSteps; i++) {
        const dot = document.getElementById('dot' + i);
        dot.className = 'step-dot' + (i === step ? ' active' : (i < step ? ' done' : ''));
    }
}

function validateStep(step) {
    const section = document.getElementById('section' + step);
    const inputs = section.querySelectorAll('input[required], select[required], textarea[required]');
    for (const input of inputs) {
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
        const email = section.querySelector('[name="email"]').value;
        if (!email.endsWith('@ispsn.org')) {
            alert('Por favor, usa o teu email institucional (@ispsn.org).');
            return false;
        }
        const desc_step = section.querySelector('[name="descricao_ideia"]');
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
    const g = (n) => (f.querySelector('[name="' + n + '"]') || {}).value || '—';
    document.getElementById('resumo').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Nome</strong><br>${g('nome')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Nº Estudante</strong><br>${g('numero_estudante')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Email</strong><br>${g('email')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Telefone</strong><br>${g('telefone')}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Curso</strong><br>${g('curso') || '—'}</div>
            <div><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Área</strong><br>${g('area_tematica')}</div>
        </div>
        <div style="margin-top:16px;"><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Ideia</strong><br>${g('titulo_ideia')}</div>
        <div style="margin-top:10px;"><strong style="color:rgba(255,255,255,0.4);font-size:0.72rem;text-transform:uppercase">Descrição</strong><br>${g('descricao_ideia').substring(0,200)}...</div>
    `;
}

function updateCount(inputId, countId, min) {
    const val = document.getElementById(inputId).value.length;
    const el = document.getElementById(countId);
    el.textContent = val;
    el.style.color = val >= min ? '#22C55E' : 'rgba(255,255,255,0.35)';
}

buildResumo();
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
