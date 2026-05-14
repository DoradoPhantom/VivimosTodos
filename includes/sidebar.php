<?php
declare(strict_types=1);

/** Barra lateral: enlaces según módulos reales del proyecto. */
$user = current_user();
if ($user === null) {
    return;
}
$isResident = is_resident();

$sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$navActive = 'inicio';
if (str_contains($sn, '/inventario/')) {
    $navActive = 'inventario';
} elseif (str_contains($sn, '/reservas/')) {
    $navActive = 'reservas';
} elseif (str_contains($sn, '/admin/')) {
    $navActive = 'usuarios';
} elseif (preg_match('#/index\.php$#', $sn) && !preg_match('#/(inventario|reservas|admin)/#', $sn)) {
    $navActive = 'inicio';
}

$initials = '';
$parts = preg_split('/\s+/', trim((string) ($user['nombre_completo'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
$sub = static function (string $s, int $start, int $len): string {
    if (function_exists('mb_substr')) {
        return mb_substr($s, $start, $len, 'UTF-8');
    }
    return substr($s, $start, $len);
};
$lenFn = static function (string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
};
if ($parts !== false && count($parts) > 0) {
    $initials = strtoupper($sub($parts[0], 0, 1));
    if (isset($parts[1])) {
        $initials .= strtoupper($sub($parts[1], 0, 1));
    } elseif ($lenFn($parts[0]) > 1) {
        $initials = strtoupper($sub($parts[0], 0, 2));
    }
}
if ($initials === '') {
    $initials = '?';
}
?>
<aside class="app-sidebar" aria-label="Navegación principal">
    <div class="app-sidebar-brand">
        <span class="app-sidebar-logo" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="4" y="14" width="4" height="6" rx="1" fill="currentColor" opacity="0.9"/>
                <rect x="10" y="8" width="4" height="12" rx="1" fill="currentColor"/>
                <rect x="16" y="4" width="4" height="16" rx="1" fill="currentColor" opacity="0.85"/>
            </svg>
        </span>
        <div class="app-sidebar-titles">
            <span class="app-sidebar-title">VivimosTodos</span>
            <span class="app-sidebar-sub">Salón social</span>
        </div>
    </div>

    <nav class="app-sidebar-nav">
        <p class="app-sidebar-section-label">Módulos</p>
        <ul class="app-sidebar-list">
            <li>
                <?php if ($isResident): ?>
                    <a class="app-sidebar-link<?= $navActive === 'inicio' ? ' is-active' : '' ?>" href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="app-sidebar-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </span>
                        Inicio
                    </a>
                <?php endif; ?>
            </li>
            <?php if (can_manage_reservations()): ?>
                <li>
                    <a class="app-sidebar-link<?= str_contains($sn, '/dashboard/') ? ' is-active' : '' ?>" href="<?= htmlspecialchars(url('dashboard/index.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="app-sidebar-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                        </span>
                        Dashboard
                    </a>
                </li>
            <?php endif; ?>
            <li>
                <a class="app-sidebar-link<?= $navActive === 'inventario' ? ' is-active' : '' ?>" href="<?= htmlspecialchars(url('inventario/index.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="app-sidebar-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    </span>
                    Catálogo
                </a>
            </li>
            <li>
                <a class="app-sidebar-link<?= $navActive === 'reservas' ? ' is-active' : '' ?>" href="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="app-sidebar-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </span>
                    Reservas
                </a>
            </li>
        </ul>

        <?php if (can_manage_reservations()): ?>
            <p class="app-sidebar-section-label">Administración</p>
            <ul class="app-sidebar-list">
                <?php if (can_manage_users()): ?>
                    <li>
                        <a class="app-sidebar-link<?= $navActive === 'usuarios' ? ' is-active' : '' ?>" href="<?= htmlspecialchars(url('admin/usuarios.php'), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="app-sidebar-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </span>
                            Usuarios
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a class="app-sidebar-link<?= str_contains($sn, '/informes/') ? ' is-active' : '' ?>" href="<?= htmlspecialchars(url('informes/index.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="app-sidebar-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg>
                        </span>
                        Informes
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </nav>

    <div class="app-sidebar-footer">
        <div class="app-sidebar-user">
            <span class="app-sidebar-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
            <div class="app-sidebar-user-text">
                <span class="app-sidebar-user-name"><?= htmlspecialchars((string) ($user['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="app-sidebar-user-role"><?= htmlspecialchars((string) ($user['rol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
        <button type="button" class="app-sidebar-theme" id="vivimos-theme-toggle" onclick="vivimosToggleTheme()" aria-pressed="false">
            Modo oscuro
        </button>
    </div>
</aside>
