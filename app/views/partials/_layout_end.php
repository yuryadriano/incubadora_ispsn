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
// ─── Sidebar Toggle ───────────────────────
function toggleSidebar() {
    const sb   = document.getElementById('sidebar');
    const ov   = document.getElementById('sidebarOverlay');
    const main = document.getElementById('mainContent');
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
        const badge = document.getElementById('notif-badge');
        if (data.unread > 0) {
            badge.textContent = data.unread;
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    } catch (e) { console.warn("Erro ao buscar notificações"); }
}

async function loadNotifications() {
    const list = document.getElementById('notif-list');
    try {
        const res = await fetch('/incubadora_ispsn/app/controllers/notificacoes_controller.php?action=list');
        const data = await res.json();
        
        if (!data.notificacoes || data.notificacoes.length === 0) {
            list.innerHTML = '<li class="p-4 text-center text-muted small">Sem notificações recentes</li>';
            return;
        }

        list.innerHTML = data.notificacoes.map(n => `
            <li class="p-3 border-bottom hover-surface" style="cursor:default">
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
    } catch (e) { 
        list.innerHTML = '<li class="p-4 text-center text-danger small">Erro ao carregar</li>';
    }
}

async function marcarLidas() {
    await fetch('/incubadora_ispsn/app/controllers/notificacoes_controller.php?action=read_all');
    checkNotifications();
    loadNotifications();
}

// Iniciar Polling (cada 15 seg)
setInterval(checkNotifications, 15000);
checkNotifications();

// Carregar ao abrir o dropdown
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('notifDropdown')?.addEventListener('show.bs.dropdown', loadNotifications);
});
</script>

<?php if (isset($extraJs)): ?>
<!-- Extra JS injectado pela página -->
<?= $extraJs ?>
<?php endif; ?>

</body>
</html>
