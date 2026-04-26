</main>
<?php if (current_user() !== null): ?>
<script>
(function () {
    var THEME_KEY = 'vivimos-theme';
    function syncThemeToggle() {
        var btn = document.getElementById('vivimos-theme-toggle');
        if (!btn) return;
        var dark = document.documentElement.classList.contains('theme-dark');
        btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        btn.textContent = dark ? 'Modo claro' : 'Modo oscuro';
    }
    window.vivimosToggleTheme = function () {
        var nextDark = !document.documentElement.classList.contains('theme-dark');
        try {
            localStorage.setItem(THEME_KEY, nextDark ? 'dark' : 'light');
        } catch (e) {}
        document.documentElement.classList.toggle('theme-dark', nextDark);
        syncThemeToggle();
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncThemeToggle);
    } else {
        syncThemeToggle();
    }
})();
</script>
<?php endif; ?>
<?php
$__u = current_user();
if ($__u !== null && can_manage_reservations()):
?>
<script>
function toggleNotifPanel() {
    var panel = document.getElementById('notif-panel');
    if (!panel) return;
    panel.hidden = !panel.hidden;
}
document.addEventListener('mousedown', function (ev) {
    var wrap = document.querySelector('.nav-notif-wrap');
    if (!wrap) return;
    if (!wrap.contains(ev.target)) {
        var panel = document.getElementById('notif-panel');
        if (panel) panel.hidden = true;
    }
});
document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    var panel = document.getElementById('notif-panel');
    if (panel && !panel.hidden) panel.hidden = true;
});
</script>
<?php endif; ?>

<?php
$__u2 = current_user();
if ($__u2 !== null && can_manage_reservations()):
?>
<script>
function openReservaReviewFromNotif(btn) {
    var modal = document.getElementById('reserva-review-modal');
    if (!modal) return;
    if (modal.parentElement !== document.body) document.body.appendChild(modal);

    var rid = btn.getAttribute('data-reserva-id') || '';
    var nombre = btn.getAttribute('data-reserva-nombre') || '';
    var fecha = btn.getAttribute('data-reserva-fecha') || '';

    var sub = document.getElementById('reserva-review-sub');
    if (sub) {
        sub.textContent = nombre + (fecha ? (' · ' + fecha.replace('T',' ').slice(0,16)) : '');
    }

    var a = document.getElementById('review-id-approve');
    var r = document.getElementById('review-id-reject');
    if (r) r.value = rid;

    var reason = document.getElementById('review-reason');
    if (reason) reason.value = '';

    modal.hidden = false;
    document.body.classList.add('modal-open');
}

function reviewApprove() {
    var rid = document.getElementById('review-id-reject');
    if (!rid || !rid.value) return;
    if (!confirm('¿Autorizar esta reserva?')) return;
    var form = document.createElement('form');
    form.method = 'post';
    form.action = '<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>';

    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_csrf';
    csrf.value = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>';
    form.appendChild(csrf);

    var action = document.createElement('input');
    action.type = 'hidden';
    action.name = 'action';
    action.value = 'aprobar';
    form.appendChild(action);

    var id = document.createElement('input');
    id.type = 'hidden';
    id.name = 'id';
    id.value = rid.value;
    form.appendChild(id);

    document.body.appendChild(form);
    form.submit();
}

function reviewValidateReject() {
    var input = document.getElementById('review-reason');
    if (!input || !input.value.trim()) {
        alert('Escribe el motivo del rechazo.');
        return false;
    }
    return confirm('¿Rechazar esta reserva?');
}

document.addEventListener('click', function (ev) {
    var modal = document.getElementById('reserva-review-modal');
    if (!modal || modal.hidden) return;
    var t = ev.target;
    if (t && t.matches && t.matches('#reserva-review-modal [data-modal-close]')) {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }
});

document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    var modal = document.getElementById('reserva-review-modal');
    if (modal && !modal.hidden) {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
    }
});
</script>
<?php endif; ?>
</div>
</div>
</body>
</html>
