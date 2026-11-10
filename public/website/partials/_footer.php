<?php
/**
 * Rodapé Uniforme da Incubadora Académica ISPSN (Estrutura e Design idênticos a incubadora.ipil.ao)
 */
$fEmail    = get_config('contacto_email', 'incubadora@ispsn.org');
$fTelefone = get_config('contacto_telefone', '+244 923 000 000');
$fEndereco = get_config('contacto_endereco', 'Huambo, Angola');
$fDesc     = get_config('site_descricao', 'Transformando o potencial académico em inovação de mercado. Promovemos tecnologia, investigação científica e empreendedorismo em Angola.');
$fFb       = get_config('facebook_url', '#');
$fIg       = get_config('instagram_url', '#');
$fIn       = get_config('linkedin_url', '#');
$fWaDigits = preg_replace('/\D+/', '', $fTelefone) ?: '244923000000';
?>
<footer class="bg-[#0A0A0A] text-white">
    <div class="h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-10">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
            <!-- Coluna 1: Identidade & Redes -->
            <div class="sm:col-span-2 lg:col-span-1">
                <a href="/incubadora_ispsn/public/website/index.php" class="flex items-center gap-3 mb-4">
                    <div class="relative">
                        <div class="absolute inset-0 bg-amber-400 rounded-full blur-md opacity-25"></div>
                        <img src="/incubadora_ispsn/assets/img/logo_sn_bulb_dark.png"
                             alt="Logo Incubadora de Empresas Sol Nascente"
                             class="relative w-11 h-11 object-contain"
                             onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn_transparent.png';">
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white leading-tight">Incubadora de Empresas</p>
                        <p class="text-xs text-amber-400 font-semibold tracking-wide">Sol Nascente · ISPSN</p>
                    </div>
                </a>
                <p class="text-gray-400 text-sm leading-relaxed mb-6 max-w-xs">
                    <?= htmlspecialchars($fDesc) ?>
                </p>
                <div class="flex items-center gap-2">
                    <a href="<?= htmlspecialchars($fFb) ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook"
                       class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-orange-400 hover:border-orange-500/40 hover:bg-orange-500/10 transition-all duration-300">
                        <i class="fa-brands fa-facebook-f text-xs"></i>
                    </a>
                    <a href="<?= htmlspecialchars($fIg) ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram"
                       class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-orange-400 hover:border-orange-500/40 hover:bg-orange-500/10 transition-all duration-300">
                        <i class="fa-brands fa-instagram text-xs"></i>
                    </a>
                    <a href="<?= htmlspecialchars($fIn) ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"
                       class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-orange-400 hover:border-orange-500/40 hover:bg-orange-500/10 transition-all duration-300">
                        <i class="fa-brands fa-linkedin-in text-xs"></i>
                    </a>
                    <a href="https://wa.me/<?= htmlspecialchars($fWaDigits) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"
                       class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-gray-400 hover:text-orange-400 hover:border-orange-500/40 hover:bg-orange-500/10 transition-all duration-300">
                        <i class="fa-brands fa-whatsapp text-xs"></i>
                    </a>
                </div>
            </div>

            <!-- Coluna 2: Navegação -->
            <div>
                <h3 class="text-xs font-semibold tracking-widest text-gray-500 uppercase mb-5">Navegação</h3>
                <ul class="space-y-3">
                    <?php
                    $footerNav = [
                        ['label' => 'Início',        'href' => '/incubadora_ispsn/public/website/index.php'],
                        ['label' => 'Sobre Nós',     'href' => '/incubadora_ispsn/public/website/sobre.php'],
                        ['label' => 'Projetos',      'href' => '/incubadora_ispsn/public/website/projetos.php'],
                        ['label' => 'Notícias',      'href' => '/incubadora_ispsn/public/website/noticias.php'],
                        ['label' => 'Startups',      'href' => '/incubadora_ispsn/public/website/startups.php'],
                        ['label' => 'Candidatar-se', 'href' => '/incubadora_ispsn/public/website/candidatura.php'],
                        ['label' => 'Portal',        'href' => '/incubadora_ispsn/public/login.php'],
                    ];
                    foreach ($footerNav as $fn):
                    ?>
                        <li>
                            <a href="<?= $fn['href'] ?>" class="group flex items-center gap-1.5 text-sm text-gray-400 hover:text-orange-400 transition-colors duration-300">
                                <span class="w-0 group-hover:w-3 h-px bg-orange-500 transition-all duration-300 rounded-full"></span>
                                <span><?= $fn['label'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Coluna 3: Apoios & Ecossistema ISPSN -->
            <div>
                <h3 class="text-xs font-semibold tracking-widest text-gray-500 uppercase mb-5">Apoios &amp; Ecossistema</h3>
                <ul class="space-y-3">
                    <li class="flex items-start gap-2">
                        <span class="w-1 h-1 rounded-full bg-orange-500 shrink-0 mt-2"></span>
                        <span class="text-sm text-gray-400 leading-snug">ISPSN — Instituto Superior Politécnico Sol Nascente</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1 h-1 rounded-full bg-orange-500 shrink-0 mt-2"></span>
                        <span class="text-sm text-gray-400 leading-snug">Divisa Oficial — HONOR · LABOR · MERITUM</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1 h-1 rounded-full bg-orange-500 shrink-0 mt-2"></span>
                        <span class="text-sm text-gray-400 leading-snug">Investigação Científica &amp; Extensão Universitária</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-1 h-1 rounded-full bg-orange-500 shrink-0 mt-2"></span>
                        <span class="text-sm text-gray-400 leading-snug">GTI — Gabinete de Tecnologias de Informação</span>
                    </li>
                </ul>
            </div>

            <!-- Coluna 4: Contacto -->
            <div>
                <h3 class="text-xs font-semibold tracking-widest text-gray-500 uppercase mb-5">Contacto</h3>
                <div class="space-y-3 mb-6">
                    <a href="mailto:<?= htmlspecialchars($fEmail) ?>" class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center shrink-0 group-hover:border-orange-500/40 group-hover:bg-orange-500/10 transition-all duration-300">
                            <i class="fa-regular fa-envelope text-xs text-gray-400 group-hover:text-orange-400 transition-colors duration-300"></i>
                        </div>
                        <span class="text-sm text-gray-400 group-hover:text-orange-400 transition-colors duration-300"><?= htmlspecialchars($fEmail) ?></span>
                    </a>
                    <a href="tel:<?= htmlspecialchars($fTelefone) ?>" class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center shrink-0 group-hover:border-orange-500/40 group-hover:bg-orange-500/10 transition-all duration-300">
                            <i class="fa-solid fa-phone text-xs text-gray-400 group-hover:text-orange-400 transition-colors duration-300"></i>
                        </div>
                        <span class="text-sm text-gray-400 group-hover:text-orange-400 transition-colors duration-300"><?= htmlspecialchars($fTelefone) ?></span>
                    </a>
                    <div class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-location-dot text-xs text-gray-400"></i>
                        </div>
                        <span class="text-sm text-gray-400"><?= htmlspecialchars($fEndereco) ?></span>
                    </div>
                </div>
                <a href="/incubadora_ispsn/public/website/candidatura.php"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-linear-to-r from-orange-500 to-amber-500 text-white text-xs font-semibold rounded-xl shadow-md shadow-orange-500/20 hover:shadow-orange-500/40 hover:scale-105 transition-all duration-300">
                    <span>Submeter Candidatura</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>
        </div>

        <div class="h-px bg-linear-to-r from-transparent via-white/10 to-transparent mb-6"></div>
        <div class="flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-gray-500">
            <p>&copy; <?= date('Y') ?> Incubadora Académica do ISPSN — Todos os direitos reservados.</p>
            <div class="flex items-center gap-4">
                <span>Desenvolvido por <strong class="text-orange-400">GTI - Educa futuro</strong></span>
                <span class="w-px h-3 bg-white/20"></span>
                <a href="/incubadora_ispsn/public/login.php" class="hover:text-orange-400 transition-colors duration-300">Acesso ao Sistema</a>
            </div>
        </div>
    </div>
</footer>

<script>
(function() {
    const header = document.getElementById('siteHeader');
    const btn = document.getElementById('mobileMenuBtn');
    const nav = document.getElementById('mobileMenuNav');
    const icon = document.getElementById('mobileMenuIcon');

    window.addEventListener('scroll', function() {
        if (!header) return;
        if (window.scrollY > 20) {
            header.classList.add('bg-black/98', 'shadow-lg', 'shadow-black/40', 'backdrop-blur-md');
            header.classList.remove('bg-black/90');
        } else {
            header.classList.remove('bg-black/98', 'shadow-lg', 'shadow-black/40', 'backdrop-blur-md');
            header.classList.add('bg-black/90');
        }
    }, { passive: true });

    if (btn && nav && icon) {
        btn.addEventListener('click', function() {
            const isHidden = nav.classList.contains('hidden');
            if (isHidden) {
                nav.classList.remove('hidden');
                icon.className = 'fa-solid fa-xmark text-base';
            } else {
                nav.classList.add('hidden');
                icon.className = 'fa-solid fa-bars text-base';
            }
        });
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                nav.classList.add('hidden');
                icon.className = 'fa-solid fa-bars text-base';
            }
        });
    }
})();
</script>

