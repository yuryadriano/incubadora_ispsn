<?php
require_once __DIR__ . '/../../config/config.php';

// Impedir cache público de páginas com token CSRF / sessão
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('CDN-Cache-Control: no-store');
header('Pragma: no-cache');

$css_version = ASSET_VERSION;
$activePage  = 'candidatura';

// Verificar se há processo aberto
$processo = null;
$res = $mysqli->query("SELECT * FROM processos_candidatura WHERE estado='aberto' ORDER BY criado_em DESC LIMIT 1");
if ($res) $processo = $res->fetch_assoc();

$erro = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
}

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
    $modelo_negocio   = limpar($_POST['modelo_negocio'] ?? '');
    $diferencial      = limpar($_POST['diferencial'] ?? '');
    $area_tematica    = limpar($_POST['area_tematica'] ?? 'tecnologia');
    $id_processo      = (int)$processo['id'];
    $remoteAddr       = $_SERVER['REMOTE_ADDR'] ?? '';
    $ip               = $remoteAddr;
    if ($remoteAddr !== '' && !filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $hops = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
            foreach ($hops as $hop) {
                if (filter_var($hop, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $hop;
                    break;
                }
            }
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var(trim($_SERVER['HTTP_X_REAL_IP']), FILTER_VALIDATE_IP)) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        }
    }

    // Rate limit por IP: máximo 5 submissões na última hora
    $stmtRl = $mysqli->prepare("SELECT COUNT(*) AS total FROM candidaturas WHERE ip_submissao = ? AND criado_em >= (NOW() - INTERVAL 1 HOUR)");
    $stmtRl->bind_param('s', $ip);
    $stmtRl->execute();
    $totalRecentes = (int)($stmtRl->get_result()->fetch_assoc()['total'] ?? 0);
    $stmtRl->close();

    // Validações
    if ($totalRecentes >= 5) {
        http_response_code(429);
        $erro = 'Limite de submissões excedido (máximo de 5 candidaturas por hora). Por favor, aguarde antes de tentar novamente.';
    } elseif (strlen($nome) < 3) $erro = 'Nome muito curto.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Email inválido.';
    elseif ($tipo_candidato === 'estudante' && strlen($numero_estudante) < 5) $erro = 'Número de estudante inválido.';
    elseif ($tipo_candidato === 'estudante' && !preg_match('/@ispsn\.org$/i', $email)) $erro = 'Por favor, use o seu e-mail institucional (@ispsn.org).';
    elseif (strlen($telefone) < 9) $erro = 'Telefone inválido.';
    elseif (strlen($titulo_ideia) < 5) $erro = 'Título da ideia muito curto.';
    elseif (strlen($descricao_ideia) < 30) $erro = 'A descrição da ideia deve ter pelo menos 30 caracteres.';
    else {
        $pitch_path = '';

        if (empty($erro)) {
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
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatura — Incubadora Académica do ISPSN</title>
    <meta name="description" content="Candidata a tua ideia à Incubadora Académica ISPSN e transforma o teu projeto numa startup de sucesso.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/incubadora_ispsn/public/website/assets/style.css?v=<?= $css_version ?>">
    <style>
        .form-section { display: none; }
        .form-section.active { display: block; animation: slideDown 0.3s ease-out; }
        .tipo-proj-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: 12px; }
        .tipo-proj-card {
            background: #ffffff;
            border: 1.5px solid #e5e7eb;
            border-radius: 16px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .tipo-proj-card:hover { border-color: #fb923c; background: #fff7ed; transform: translateY(-2px); }
        .tipo-proj-card.active {
            border-color: #f97316;
            background: #fff7ed;
            box-shadow: 0 4px 14px rgba(249, 115, 22, 0.15);
        }
        .tipo-proj-card .icon { font-size: 1.5rem; margin-bottom: 6px; }
        .tipo-proj-card .title { font-size: 0.8rem; font-weight: 700; color: #111827; }
        .tipo-proj-card .desc { font-size: 0.68rem; color: #6b7280; margin-top: 3px; }
        .input-ipil {
            width: 100%;
            padding: 12px 16px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.875rem;
            color: #1f2937;
            outline: none;
            transition: all 0.2s ease;
        }
        .input-ipil:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
        }
        .input-ipil:disabled {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-poppins">
<div class="flex flex-col min-h-screen bg-gray-50">

    <?php require __DIR__ . '/partials/_navbar.php'; ?>

    <main class="flex-1">
        <!-- 1. HERO ESCURO EM 2 COLUNAS (Padrão IPIL Candidatura) -->
        <div class="relative bg-[#0A0A0A] overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-orange-500/6 rounded-full blur-3xl"></div>
            </div>

            <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-20">
                <div class="grid lg:grid-cols-2 gap-10 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-orange-500/30 bg-orange-500/10 mb-5">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                            <span class="text-orange-400 text-xs font-medium tracking-wide">
                                <?= $processo ? 'Candidaturas abertas — ' . htmlspecialchars($processo['titulo'] ?? $processo['nome'] ?? date('Y')) : 'Programa de Incubação ISPSN' ?>
                            </span>
                        </div>

                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-white leading-tight mb-5">
                            Candidatura ao <span class="text-orange-400">Programa de Incubação</span>
                        </h1>

                        <p class="text-gray-400 text-sm sm:text-base leading-relaxed mb-7">
                            Tem uma ideia inovadora? Junte-se ao programa da Incubadora Académica do ISPSN e receba mentoria, infraestrutura e apoio especializado para transformar o seu projeto num negócio de sucesso.
                        </p>

                        <div class="flex flex-wrap gap-3">
                            <div class="inline-flex items-center gap-2 px-3 py-2 bg-white/5 border border-white/10 rounded-xl">
                                <i class="fa-solid fa-bolt text-xs text-orange-400"></i>
                                <span class="text-white text-xs font-medium">Mentoria Gratuita</span>
                            </div>
                            <div class="inline-flex items-center gap-2 px-3 py-2 bg-white/5 border border-white/10 rounded-xl">
                                <i class="fa-solid fa-shield-halved text-xs text-orange-400"></i>
                                <span class="text-white text-xs font-medium">Acompanhamento Técnico</span>
                            </div>
                            <div class="inline-flex items-center gap-2 px-3 py-2 bg-white/5 border border-white/10 rounded-xl">
                                <i class="fa-solid fa-award text-xs text-orange-400"></i>
                                <span class="text-white text-xs font-medium">Rede de Investidores</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/5 border border-white/10 rounded-2xl p-6 backdrop-blur-sm">
                        <p class="text-xs font-semibold text-orange-400 uppercase tracking-widest mb-5">O que oferecemos</p>
                        <?php
                        $ofertas = [
                            ['icon' => 'fa-users',          't' => 'Mentoria com especialistas',     's' => 'Sessões de orientação individuais e em equipa'],
                            ['icon' => 'fa-building',       't' => 'Espaço de trabalho no ISPSN',    's' => 'Salas de incubação e reuniões equipadas'],
                            ['icon' => 'fa-arrow-trend-up', 't' => 'Banca & Demo Day',               's' => 'Apresentação a avaliadores e investidores'],
                            ['icon' => 'fa-bullseye',       't' => 'Evolução em 5 Fases',            's' => 'Ideação, Validação, MVP, Tração e Mercado'],
                            ['icon' => 'fa-file-lines',     't' => 'Certificação & Suporte',         's' => 'Apoio na estruturação empresarial'],
                        ];
                        foreach ($ofertas as $idx => $of):
                        ?>
                            <div class="flex items-center gap-3.5 <?= $idx < 4 ? 'pb-4 mb-4 border-b border-white/8' : '' ?>">
                                <div class="w-9 h-9 bg-orange-500/15 border border-orange-500/20 rounded-xl flex items-center justify-center shrink-0">
                                    <i class="fa-solid <?= $of['icon'] ?> text-xs text-orange-400"></i>
                                </div>
                                <div>
                                    <p class="text-white text-sm font-semibold"><?= $of['t'] ?></p>
                                    <p class="text-gray-500 text-xs"><?= $of['s'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. FAIXA BRANCA: PROCESSO DE CANDIDATURA (4 Etapas Numeradas) -->
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                <p class="text-center text-xs font-semibold text-gray-400 uppercase tracking-widest mb-8">Processo de candidatura</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <?php
                    $passosCand = [
                        ['icon' => 'fa-file-lines', 't' => 'Preencha o formulário', 's' => 'Completo e claro'],
                        ['icon' => 'fa-clock',      't' => 'Análise pela equipa',   's' => 'Comissão Técnica'],
                        ['icon' => 'fa-phone',      't' => 'Contacto / Entrevista', 's' => 'Via email ou WhatsApp'],
                        ['icon' => 'fa-rocket',     't' => 'Início da incubação',   's' => 'Bem-vindo ao ISPSN!'],
                    ];
                    foreach ($passosCand as $iP => $pc):
                    ?>
                        <div class="text-center group">
                            <div class="relative w-14 h-14 mx-auto mb-3">
                                <div class="absolute inset-0 rounded-full bg-orange-50 border border-orange-100 group-hover:bg-orange-500 group-hover:border-orange-500 transition-all duration-300"></div>
                                <span class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-[#0A0A0A] text-white text-xs font-bold flex items-center justify-center border-2 border-white z-10">
                                    <?= $iP + 1 ?>
                                </span>
                                <div class="relative h-full flex items-center justify-center">
                                    <i class="fa-solid <?= $pc['icon'] ?> text-orange-500 group-hover:text-white transition-colors duration-300"></i>
                                </div>
                            </div>
                            <p class="text-xs font-bold text-black mb-0.5"><?= $pc['t'] ?></p>
                            <p class="text-xs text-gray-400"><?= $pc['s'] ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 3. ÁREA DO FORMULÁRIO (Estilo Claro e Limpo IPIL + Campos Oficiais ISPSN) -->
        <div id="formAnchor" class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">

            <?php if ($sucesso): ?>
                <div class="bg-white border border-gray-100 rounded-2xl p-8 sm:p-10 shadow-sm text-center">
                    <div class="relative inline-block mb-6">
                        <div class="relative w-20 h-20 bg-linear-to-br from-orange-500 to-amber-400 rounded-full flex items-center justify-center shadow-xl shadow-orange-300/50 mx-auto">
                            <i class="fa-solid fa-check text-3xl text-white"></i>
                        </div>
                    </div>
                    <h2 class="text-3xl font-bold text-black mb-3">
                        Candidatura <span class="text-orange-500">Enviada!</span>
                    </h2>
                    <p class="text-gray-500 text-sm sm:text-base leading-relaxed mb-6 max-w-lg mx-auto">
                        A tua candidatura foi recebida com sucesso. A equipa da Incubadora Académica do ISPSN irá analisá-la e entraremos em contacto via <strong>WhatsApp ou email</strong> nos próximos dias úteis.
                    </p>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-5 mb-8 text-left max-w-md mx-auto">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Nota importante</p>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            Guarda o teu número de estudante e e-mail — serão utilizados para ativar a tua conta no Portal da Incubadora assim que a candidatura for aprovada.
                        </p>
                    </div>
                    <a href="/incubadora_ispsn/public/website/index.php"
                       class="inline-flex items-center gap-2 px-8 py-3.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl shadow-md shadow-orange-200 transition-all duration-300 text-sm">
                        <span>Voltar ao Início</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>

            <?php elseif (!$processo): ?>
                <div class="bg-white border border-gray-100 rounded-2xl p-8 sm:p-12 shadow-sm text-center">
                    <div class="w-16 h-16 bg-orange-50 border border-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <i class="fa-regular fa-calendar-check text-2xl text-orange-500"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-black mb-3">Candidaturas Brevemente</h2>
                    <p class="text-gray-500 text-sm sm:text-base max-w-md mx-auto leading-relaxed mb-8">
                        O processo de candidatura encontra-se temporariamente encerrado. Prepare o seu projeto para a abertura do próximo edital da Incubadora Académica do ISPSN!
                    </p>
                    <a href="/incubadora_ispsn/public/website/index.php"
                       class="inline-flex items-center gap-2 px-7 py-3 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl shadow-md shadow-orange-200 transition-all text-sm">
                        <i class="fa-solid fa-arrow-left text-xs"></i>
                        <span>Voltar ao Início</span>
                    </a>
                </div>

            <?php else: ?>

                <!-- Cabeçalho de Progresso Estilo IPIL -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <p class="text-xs text-gray-400 font-medium mb-1">Etapa <span id="stepNumTxt">1</span> de 3</p>
                            <h2 id="stepTitleTxt" class="text-xl sm:text-2xl font-bold text-black">Dados Pessoais</h2>
                        </div>
                        <div class="w-12 h-12 bg-orange-50 border border-orange-100 rounded-2xl flex items-center justify-center">
                            <i id="stepIconEl" class="fa-solid fa-user text-orange-500"></i>
                        </div>
                    </div>
                    <div class="w-full h-1 bg-gray-200 rounded-full overflow-hidden">
                        <div id="stepperProgress" class="h-full bg-linear-to-r from-orange-500 to-amber-400 rounded-full transition-all duration-500" style="width: 33.33%"></div>
                    </div>
                </div>

                <!-- Pills de Etapas -->
                <div class="flex gap-2 mb-8 overflow-x-auto pb-1">
                    <div id="pillStep1" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium whitespace-nowrap border transition-all duration-300 shrink-0 bg-orange-500 border-orange-500 text-white shadow-md shadow-orange-200">
                        <i class="fa-solid fa-user text-[11px]"></i>
                        <span>1. Dados Pessoais</span>
                    </div>
                    <div id="pillStep2" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium whitespace-nowrap border transition-all duration-300 shrink-0 bg-white border-gray-100 text-gray-400">
                        <i class="fa-solid fa-lightbulb text-[11px]"></i>
                        <span>2. O Teu Pitch</span>
                    </div>
                    <div id="pillStep3" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium whitespace-nowrap border transition-all duration-300 shrink-0 bg-white border-gray-100 text-gray-400">
                        <i class="fa-solid fa-clipboard-check text-[11px]"></i>
                        <span>3. Confirmação</span>
                    </div>
                </div>

                <?php if ($erro): ?>
                    <div class="flex items-center gap-3 px-5 py-3.5 bg-red-50 border border-red-200 rounded-xl mb-5">
                        <i class="fa-solid fa-circle-exclamation text-red-500 shrink-0"></i>
                        <p class="text-xs font-semibold text-red-700"><?= htmlspecialchars($erro) ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" id="candidaturaForm" novalidate class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                    <?= csrf_field() ?>

                    <div class="p-6 sm:p-8">
                        <!-- PASSO 1: Dados Pessoais -->
                        <div class="form-section active" id="section1">
                            <div class="grid sm:grid-cols-2 gap-5">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Tipo de Candidato <span class="text-orange-500">*</span>
                                    </label>
                                    <select name="tipo_candidato" id="tipo_candidato" onchange="toggleTipoCandidato(this.value)" class="input-ipil">
                                        <option value="estudante">Estudante ISPSN</option>
                                        <option value="pre_licenciado">Pré-licenciado</option>
                                    </select>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Nome Completo <span class="text-orange-500">*</span>
                                    </label>
                                    <input type="text" name="nome" placeholder="Ex: João Manuel dos Santos" required minlength="3" class="input-ipil">
                                </div>

                                <div>
                                    <label id="lbl_numero_estudante" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Número de Estudante <span class="text-orange-500">*</span>
                                    </label>
                                    <input type="text" name="numero_estudante" id="numero_estudante" placeholder="Ex: 122400024" required class="input-ipil">
                                </div>

                                <div>
                                    <label id="lbl_email" class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Email Institucional <span class="text-orange-500">*</span>
                                    </label>
                                    <input type="email" name="email" id="email" placeholder="numero@ispsn.org" required class="input-ipil">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Telefone / WhatsApp <span class="text-orange-500">*</span>
                                    </label>
                                    <input type="tel" name="telefone" placeholder="9XXXXXXXX" required class="input-ipil">
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Ano de Estudo
                                    </label>
                                    <select name="ano_estudo" class="input-ipil">
                                        <option value="">Selecionar...</option>
                                        <option>1º Ano</option><option>2º Ano</option>
                                        <option>3º Ano</option><option>4º Ano</option>
                                        <option>5º Ano</option><option>Pós-Graduação</option>
                                    </select>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Curso
                                    </label>
                                    <input type="text" name="curso" placeholder="Ex: Engenharia Informática" class="input-ipil">
                                </div>
                            </div>

                            <div class="mt-8 pt-5 border-t border-gray-100 flex items-center justify-between">
                                <span class="text-xs text-gray-400">Campos com <span class="text-orange-500">*</span> são obrigatórios</span>
                                <button type="button" onclick="nextStep(1)"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-orange-200 transition-all cursor-pointer">
                                    <span>Continuar</span>
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- PASSO 2: O Teu Pitch / A Tua Ideia -->
                        <div class="form-section" id="section2">
                            <div class="space-y-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-2">
                                        Tipo de Projeto / Entidade <span class="text-orange-500">*</span>
                                    </label>
                                    <input type="hidden" name="tipo_projeto" id="input_tipo_projeto" value="startup_tecnologica">
                                    <div class="tipo-proj-grid">
                                        <div class="tipo-proj-card active" onclick="selectTipoProjeto('startup_tecnologica', this)">
                                            <div class="icon">🚀</div>
                                            <div class="title">Startup Tech</div>
                                            <div class="desc">Software, App, Plataforma</div>
                                        </div>
                                        <div class="tipo-proj-card" onclick="selectTipoProjeto('negocio_tradicional', this)">
                                            <div class="icon">🏢</div>
                                            <div class="title">Empresa / Negócio</div>
                                            <div class="desc">Comércio, Serviços</div>
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

                                <div class="grid sm:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                            Título da Ideia / Startup <span class="text-orange-500">*</span>
                                        </label>
                                        <input type="text" name="titulo_ideia" placeholder="Nome do teu projeto" required minlength="5" class="input-ipil">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                            Área Temática <span class="text-orange-500">*</span>
                                        </label>
                                        <select name="area_tematica" required class="input-ipil">
                                            <option value="tecnologia">💻 Tecnologia</option>
                                            <option value="saude">❤️ Saúde</option>
                                            <option value="educacao">📚 Educação</option>
                                            <option value="agro">🌱 Agro-negócio</option>
                                            <option value="financas">📈 Finanças</option>
                                            <option value="outro">🔮 Outro</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">
                                        Descrição Geral da Ideia <span class="text-orange-500">*</span> <span class="text-gray-400 font-normal">(mín. 30 caracteres)</span>
                                    </label>
                                    <textarea name="descricao_ideia" id="desc" rows="3" placeholder="Resumo executivo do teu projeto..." required oninput="updateCount('desc','descCount',30)" class="input-ipil"></textarea>
                                    <div class="text-right text-xs text-gray-400 mt-1"><span id="descCount">0</span> caracteres</div>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Que Problema Resolve?</label>
                                        <textarea name="problema" rows="3" placeholder="Qual é a necessidade real no mercado que a tua ideia resolve?" class="input-ipil"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Qual é a Solução Proposta?</label>
                                        <textarea name="solucao" rows="3" placeholder="Como o teu produto/serviço resolve este problema?" class="input-ipil"></textarea>
                                    </div>
                                </div>

                                <div class="grid sm:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Público-Alvo / Clientes</label>
                                        <textarea name="publico_alvo" rows="2" placeholder="Quem são os clientes ou beneficiários do projeto?" class="input-ipil"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Modelo de Negócio / Receita</label>
                                        <textarea name="modelo_negocio" rows="2" placeholder="Como o projeto pretende gerar sustentabilidade financeira?" class="input-ipil"></textarea>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Diferencial Competitivo</label>
                                    <textarea name="diferencial" rows="2" placeholder="O que torna a tua solução única face às alternativas?" class="input-ipil"></textarea>
                                </div>
                            </div>

                            <div class="mt-8 pt-5 border-t border-gray-100 flex items-center justify-between">
                                <button type="button" onclick="prevStep(2)"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:border-orange-200 hover:text-orange-600 transition-all cursor-pointer">
                                    <i class="fa-solid fa-chevron-left text-xs"></i>
                                    <span>Anterior</span>
                                </button>
                                <button type="button" onclick="nextStep(2)"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-orange-200 transition-all cursor-pointer">
                                    <span>Continuar</span>
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <!-- PASSO 3: Confirmação -->
                        <div class="form-section" id="section3">
                            <div id="resumo" class="bg-gray-50 border border-gray-100 rounded-2xl p-5 text-sm text-gray-700 space-y-4"></div>

                            <div class="mt-5 p-4 bg-orange-50 border-l-4 border-orange-500 rounded-r-xl text-xs text-gray-700 leading-relaxed">
                                <i class="fa-solid fa-circle-info text-orange-500 mr-1"></i>
                                Ao submeter, confirmas que os dados fornecidos são verdadeiros. A tua candidatura será analisada pela Comissão Técnica da Incubadora Académica do ISPSN.
                            </div>

                            <div class="mt-8 pt-5 border-t border-gray-100 flex items-center justify-between">
                                <button type="button" onclick="prevStep(3)"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:border-orange-200 hover:text-orange-600 transition-all cursor-pointer">
                                    <i class="fa-solid fa-chevron-left text-xs"></i>
                                    <span>Anterior</span>
                                </button>
                                <button type="submit" id="btnSubmit"
                                        class="inline-flex items-center gap-2 px-7 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-orange-200 transition-all cursor-pointer">
                                    <i class="fa-solid fa-rocket text-xs"></i>
                                    <span>Submeter Candidatura</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <p class="text-center text-xs text-gray-400 mt-6 leading-relaxed">
                    Os seus dados são tratados com total confidencialidade pela Incubadora Académica do ISPSN.
                </p>
            <?php endif; ?>

        </div>
    </main>

    <?php require __DIR__ . '/partials/_footer.php'; ?>
</div>

<script>
let currentStep = 1;
const totalSteps = 3;
const stepMeta = {
    1: { title: 'Dados Pessoais', icon: 'fa-solid fa-user' },
    2: { title: 'O Teu Pitch / A Tua Ideia', icon: 'fa-solid fa-lightbulb' },
    3: { title: 'Confirmação da Candidatura', icon: 'fa-solid fa-clipboard-check' }
};

function updateProgress(step) {
    const pct = (step / totalSteps) * 100;
    const progressEl = document.getElementById('stepperProgress');
    if (progressEl) progressEl.style.width = pct + '%';

    document.getElementById('stepNumTxt').textContent = step;
    document.getElementById('stepTitleTxt').textContent = stepMeta[step].title;
    document.getElementById('stepIconEl').className = stepMeta[step].icon + ' text-orange-500';

    for (let i = 1; i <= totalSteps; i++) {
        const pill = document.getElementById('pillStep' + i);
        if (!pill) continue;
        if (i === step) {
            pill.className = 'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium whitespace-nowrap border transition-all duration-300 shrink-0 bg-orange-500 border-orange-500 text-white shadow-md shadow-orange-200';
        } else if (i < step) {
            pill.className = 'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium whitespace-nowrap border transition-all duration-300 shrink-0 bg-green-50 border-green-200 text-green-700';
        } else {
            pill.className = 'flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium whitespace-nowrap border transition-all duration-300 shrink-0 bg-white border-gray-100 text-gray-400';
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
        lblEmail.innerHTML = 'Email Pessoal <span class="text-orange-500">*</span>';
    } else {
        numEstudanteInput.disabled = false;
        numEstudanteInput.required = true;
        numEstudanteInput.placeholder = 'Ex: 122400024';
        lblNumeroEstudante.innerHTML = 'Número de Estudante <span class="text-orange-500">*</span>';
        emailInput.placeholder = 'numero@ispsn.org';
        lblEmail.innerHTML = 'Email Institucional <span class="text-orange-500">*</span>';
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
    if (step === 1) {
        const tipo = document.getElementById('tipo_candidato').value;
        const email = section.querySelector('[name="email"]').value.trim().toLowerCase();
        if (tipo === 'estudante' && !email.endsWith('@ispsn.org')) {
            alert('Por favor, usa o teu email institucional (@ispsn.org).');
            return false;
        }
    }
    if (step === 2) {
        const desc = document.querySelector('[name="descricao_ideia"]').value;
        if (desc.length < 30) {
            alert('A descrição deve ter pelo menos 30 caracteres.');
            return false;
        }
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
    document.getElementById('formAnchor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function prevStep(from) {
    document.getElementById('section' + from).classList.remove('active');
    const prev = from - 1;
    document.getElementById('section' + prev).classList.add('active');
    currentStep = prev;
    updateProgress(prev);
    document.getElementById('formAnchor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
        'negocio_tradicional': '🏢 Empresa / Negócio',
        'individual': '👤 Projeto Individual',
        'equipa': '👥 Equipa / Grupo de Estudantes',
        'impacto_social': '🌍 Projeto de Impacto Social'
    };
    const projText = tiposProjMap[g('tipo_projeto')] || g('tipo_projeto');

    document.getElementById('resumo').innerHTML = `
        <div class="grid sm:grid-cols-2 gap-4">
            <div><span class="text-xs text-gray-400 uppercase font-semibold block">Tipo Candidato</span><strong>${tipoText}</strong></div>
            <div><span class="text-xs text-gray-400 uppercase font-semibold block">Nome</span><strong>${g('nome')}</strong></div>
            <div><span class="text-xs text-gray-400 uppercase font-semibold block">Nº Estudante</span><strong>${g('numero_estudante')}</strong></div>
            <div><span class="text-xs text-gray-400 uppercase font-semibold block">Email</span><strong>${g('email')}</strong></div>
            <div><span class="text-xs text-gray-400 uppercase font-semibold block">Telefone</span><strong>${g('telefone')}</strong></div>
            <div><span class="text-xs text-gray-400 uppercase font-semibold block">Tipo Projeto</span><strong>${projText}</strong></div>
            <div><span class="text-xs text-gray-400 uppercase font-semibold block">Área Temática</span><strong>${g('area_tematica')}</strong></div>
            <div><span class="text-xs text-gray-400 uppercase font-semibold block">Curso</span><strong>${g('curso')}</strong></div>
        </div>
        <div class="pt-3 border-t border-gray-200">
            <span class="text-xs text-gray-400 uppercase font-semibold block">Título da Ideia / Startup</span>
            <strong class="text-base text-orange-600">${g('titulo_ideia')}</strong>
        </div>
        <div>
            <span class="text-xs text-gray-400 uppercase font-semibold block">Descrição Geral</span>
            <p class="text-gray-700 mt-1">${g('descricao_ideia')}</p>
        </div>
    `;
}

function updateCount(inputId, countId, min) {
    const val = document.getElementById(inputId).value.length;
    const el = document.getElementById(countId);
    el.textContent = val;
    el.style.color = val >= min ? '#16a34a' : '#9ca3af';
}

document.getElementById('candidaturaForm')?.addEventListener('submit', () => {
    const btn = document.getElementById('btnSubmit');
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> A enviar...';
        btn.disabled = true;
    }
});
</script>
</body>
</html>
