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
</div>
</div>
</body>
</html>
