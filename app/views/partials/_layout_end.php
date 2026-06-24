<?php
// app/views/partials/_layout_end.php
// Fecha o layout base (scripts + fechos HTML)
?>
    </div><!-- /page-content -->
</main><!-- /main-content -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// ─── CSRF: Injetar token em todos os formulários POST (automático) ────
(function() {
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
    function injectCsrf(form) {
        if (!form.querySelector('input[name="_csrf_token"]')) {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = '_csrf_token';
            inp.value = CSRF_TOKEN;
            form.prepend(inp);
        }
    }
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(injectCsrf);
    });
    // Capturar forms criados dinamicamente via JS (modais, wizards)
    document.addEventListener('submit', (e) => {
        if (e.target.tagName === 'FORM') injectCsrf(e.target);
    }, true);
})();

// ─── Dark Mode Toggle ─────────────────────
function toggleDarkMode() {
    const isDark = document.body.classList.toggle('dark-mode');
    document.documentElement.classList.toggle('dark-mode', isDark);
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateDarkModeIcon(isDark);
}

function updateDarkModeIcon(isDark) {
    const icon = document.getElementById('darkModeIcon');
    if (icon) {
        if (isDark) {
            icon.className = 'fa fa-sun';
        } else {
            icon.className = 'fa fa-moon';
        }
    }
}

// Alinhar ícone ao carregar
document.addEventListener('DOMContentLoaded', () => {
    const isDark = document.body.classList.contains('dark-mode');
    updateDarkModeIcon(isDark);
});

// ─── Sidebar Toggle ───────────────────────
function toggleSidebar() {
    const sb   = document.getElementById('sidebar');
    const ov   = document.getElementById('sidebarOverlay');
    sb.classList.toggle('open');
    ov.classList.toggle('show');
}

// ─── Fechar ao redimensionar para desktop ─
window.addEventListener('resize', () => {
    if (window.innerWidth > 992) {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('show');
    }
});

// ─── Notificações Polling ─────────────────
async function checkNotifications() {
    try {
        const res = await fetch('/incubadora_ispsn/app/controllers/notificacoes_controller.php?action=check');
        const data = await res.json();

        const badgeSidebar = document.getElementById('notif-badge');
        if (badgeSidebar) {
            data.unread > 0 ? badgeSidebar.classList.remove('d-none') : badgeSidebar.classList.add('d-none');
        }

        const badgeTopbar = document.getElementById('notif-badge-topbar');
        if (badgeTopbar) {
            if (data.unread > 0) {
                badgeTopbar.textContent = data.unread;
                badgeTopbar.classList.remove('d-none');
            } else {
                badgeTopbar.classList.add('d-none');
            }
        }
    } catch (e) { console.warn("Erro ao buscar notificações"); }
}

async function loadNotifications() {
    const listSidebar = document.getElementById('notif-list');
    const listTopbar  = document.getElementById('notif-list-topbar');
    try {
        const res  = await fetch('/incubadora_ispsn/app/controllers/notificacoes_controller.php?action=list');
        const data = await res.json();

        const html = (!data.notificacoes || data.notificacoes.length === 0)
            ? '<li class="p-4 text-center text-muted small">Sem notificações recentes</li>'
            : data.notificacoes.map(n => `
                <li class="p-3 border-bottom" style="cursor:default">
                    <div class="d-flex gap-2">
                        <div class="mt-1"><i class="fa fa-circle text-${n.tipo === 'info' ? 'primary' : (n.tipo === 'sucesso' ? 'success' : (n.tipo === 'aviso' ? 'warning' : 'danger'))}" style="font-size:0.6rem"></i></div>
                        <div>
                            <div class="fw-bold" style="font-size:0.85rem">${n.titulo}</div>
                            <div class="text-muted" style="font-size:0.75rem">${n.mensagem}</div>
                            <div class="text-muted mt-1" style="font-size:0.65rem">${new Date(n.criado_em).toLocaleString()}</div>
                        </div>
                    </div>
                </li>
            `).join('');

        if (listSidebar) listSidebar.innerHTML = html;
        if (listTopbar)  listTopbar.innerHTML  = html;
    } catch (e) {
        const errHtml = '<li class="p-4 text-center text-danger small">Erro ao carregar</li>';
        if (listSidebar) listSidebar.innerHTML = errHtml;
        if (listTopbar)  listTopbar.innerHTML  = errHtml;
    }
}

async function marcarLidas() {
    await fetch('/incubadora_ispsn/app/controllers/notificacoes_controller.php?action=read_all');
    checkNotifications();
    loadNotifications();
}

// Verificar badges ao carregar
checkNotifications();

// Carregar lista ao abrir os dropdowns
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('notifDropdown')?.addEventListener('show.bs.dropdown', loadNotifications);
    document.getElementById('btnNotif')?.addEventListener('show.bs.dropdown', loadNotifications);
});
</script>

<?php if (isset($extraJs)): ?>
<!-- Extra JS injectado pela página -->
<?= $extraJs ?>
<?php endif; ?>

</body>
</html>
