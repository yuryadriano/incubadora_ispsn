<?php
/**
 * Cabeçalho Uniforme da Incubadora Académica ISPSN (Estrutura e Design idênticos a incubadora.ipil.ao)
 * Variável esperada: $activePage ('inicio', 'sobre', 'projetos', 'noticias', 'startups', 'candidatura')
 */
$activePage = $activePage ?? 'inicio';
$navItems = [
    ['id' => 'inicio',   'href' => '/incubadora_ispsn/public/website/index.php',    'label' => 'Início',   'icon' => 'fa-house'],
    ['id' => 'sobre',    'href' => '/incubadora_ispsn/public/website/sobre.php',    'label' => 'Sobre',    'icon' => 'fa-circle-info'],
    ['id' => 'projetos', 'href' => '/incubadora_ispsn/public/website/projetos.php', 'label' => 'Projetos', 'icon' => 'fa-layer-group'],
    ['id' => 'noticias', 'href' => '/incubadora_ispsn/public/website/noticias.php', 'label' => 'Notícias', 'icon' => 'fa-newspaper'],
];
?>
<header id="siteHeader" class="sticky top-0 z-50 transition-all duration-500 bg-black/90">
    <div class="max-w-7xl mx-auto flex items-center justify-between px-4 sm:px-6 lg:px-8 h-18 sm:h-20">
        <!-- Logo + Nome Institucional -->
        <a href="/incubadora_ispsn/public/website/index.php" class="flex items-center gap-3 group shrink-0">
            <div class="relative">
                <div class="absolute inset-0 bg-orange-500 rounded-full blur-md opacity-0 group-hover:opacity-40 transition-opacity duration-300"></div>
                <img src="/incubadora_ispsn/assets/img/logo_sn_transparent.png"
                     alt="Logo Incubadora ISPSN"
                     class="relative w-10 h-10 sm:w-11 sm:h-11 object-contain"
                     onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn.jpg';">
            </div>
            <div class="hidden sm:block">
                <p class="text-white font-bold text-sm leading-tight">Incubadora Académica</p>
                <p class="text-orange-400 text-xs font-medium tracking-wide">do ISPSN</p>
            </div>
        </a>

        <!-- Navegação Desktop -->
        <nav class="hidden md:flex items-center gap-1 lg:gap-2">
            <?php foreach ($navItems as $item):
                $isActive = ($activePage === $item['id']);
            ?>
                <a href="<?= $item['href'] ?>"
                   class="relative flex items-center gap-1.5 text-sm font-medium transition-colors duration-300 px-3 py-2 rounded-lg <?= $isActive ? 'text-orange-400 bg-orange-500/10' : 'text-gray-300 hover:text-white hover:bg-white/5' ?>">
                    <span class="<?= $isActive ? 'text-orange-400' : 'text-gray-400' ?>">
                        <i class="fa-solid <?= $item['icon'] ?> text-xs"></i>
                    </span>
                    <span><?= $item['label'] ?></span>
                    <span class="absolute -bottom-0.5 left-3 right-3 h-px bg-linear-to-r from-orange-500 to-amber-400 rounded-full transition-opacity duration-300 <?= $isActive ? 'opacity-100' : 'opacity-0' ?>"></span>
                </a>
            <?php endforeach; ?>

            <!-- Botão Startups (estilo IPIL) -->
            <a href="/incubadora_ispsn/public/website/startups.php"
               class="ml-2 relative overflow-hidden flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-xl border border-orange-500 <?= $activePage === 'startups' ? 'bg-orange-500 text-white' : 'text-orange-400 hover:text-white' ?> group transition-colors duration-300">
                <span class="absolute inset-0 bg-linear-to-r from-orange-500 to-amber-500 <?= $activePage === 'startups' ? 'translate-y-0' : 'translate-y-full group-hover:translate-y-0' ?> transition-transform duration-400 ease-out"></span>
                <i class="fa-solid fa-arrow-trend-up relative z-10 text-xs"></i>
                <span class="relative z-10">Startups</span>
            </a>

            <!-- Botão Candidatar-se (ISPSN) -->
            <a href="/incubadora_ispsn/public/website/candidatura.php"
               class="ml-1 flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-xl bg-linear-to-r from-orange-500 to-amber-500 text-white shadow-md shadow-orange-500/20 hover:shadow-orange-500/40 hover:scale-105 transition-all duration-300">
                <i class="fa-solid fa-rocket text-xs"></i>
                <span>Candidatar-se</span>
            </a>

            <!-- Botão Portal (Acesso ao Sistema) -->
            <a href="/incubadora_ispsn/public/login.php"
               class="ml-1 flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded-xl bg-white/5 border border-white/10 text-gray-300 hover:text-white hover:border-orange-500/40 hover:bg-orange-500/10 transition-all duration-300">
                <span>Portal</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </nav>

        <!-- Botão Hambúrguer Mobile -->
        <button id="mobileMenuBtn"
                type="button"
                aria-label="Abrir menu"
                class="md:hidden flex items-center justify-center w-10 h-10 rounded-xl bg-white/5 border border-white/10 text-white hover:bg-white/10 transition-colors duration-200 cursor-pointer">
            <i id="mobileMenuIcon" class="fa-solid fa-bars text-base"></i>
        </button>
    </div>

    <!-- Menu Mobile Acordeão Escuro (Estilo IPIL) -->
    <nav id="mobileMenuNav" class="hidden md:hidden overflow-hidden border-t border-white/10 bg-black/98 backdrop-blur-md animate-slideDown">
        <div class="px-4 py-4 space-y-1">
            <?php foreach ($navItems as $item):
                $isActive = ($activePage === $item['id']);
            ?>
                <a href="<?= $item['href'] ?>"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 <?= $isActive ? 'bg-orange-500/15 text-orange-400 border border-orange-500/20' : 'text-gray-300 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid <?= $item['icon'] ?> w-4 text-center"></i>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>

            <div class="pt-2 space-y-2">
                <a href="/incubadora_ispsn/public/website/startups.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold border border-orange-500/40 text-orange-400 hover:bg-orange-500/15 transition-all">
                    <i class="fa-solid fa-arrow-trend-up w-4 text-center"></i>
                    <span>Startups Incubadas</span>
                </a>
                <a href="/incubadora_ispsn/public/website/candidatura.php"
                   class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold bg-linear-to-r from-orange-500 to-amber-500 text-white shadow-md shadow-orange-500/20">
                    <i class="fa-solid fa-rocket"></i>
                    <span>Candidatar-se</span>
                </a>
                <a href="/incubadora_ispsn/public/login.php"
                   class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-semibold bg-white/5 border border-white/10 text-gray-300 hover:text-white">
                    <i class="fa-solid fa-user-shield"></i>
                    <span>Acesso ao Portal</span>
                </a>
            </div>
        </div>
    </nav>
</header>

