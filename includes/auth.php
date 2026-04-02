<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_PATH . ($path !== '' ? '/' . $path : '');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if ((current_user()['rol'] ?? '') !== 'administrador') {
        http_response_code(403);
        echo 'Acceso denegado: solo administradores.';
        exit;
    }
}

function can_manage_inventory(): bool
{
    $u = current_user();
    if ($u === null) {
        return false;
    }
    return in_array($u['rol'], ['administrador', 'supervisor'], true);
}

function login_user(string $usuario, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, nombre_completo, usuario, password_hash, rol, activo FROM usuarios WHERE usuario = ? LIMIT 1'
    );
    $stmt->execute([$usuario]);
    $row = $stmt->fetch();
    if (!$row || !(int) $row['activo']) {
        return false;
    }
    if (!password_verify($password, $row['password_hash'])) {
        return false;
    }
    unset($row['password_hash']);
    $_SESSION['user'] = $row;
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool
{
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}
