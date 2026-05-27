<?php
declare(strict_types=1);

// Pagina de acceso sin menu lateral
require_once __DIR__ . '/includes/auth.php';

if (current_user() !== null) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Sesión de formulario inválida. Intenta de nuevo.';
    } else {
        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($usuario === '' || $password === '') {
            $error = 'Completa usuario y contraseña.';
        } elseif (!login_user($usuario, $password)) {
            $error = 'Credenciales incorrectas o usuario inactivo.';
        } else {
            redirect('index.php');
        }
    }
}

$pageTitle = 'Iniciar sesión — Vivimos Todos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/01-app-base.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1>Vivimos Todos</h1>
    <p class="muted">Ingreso al sistema</p>
    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" action="" class="form-stack">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <label>
            Usuario
            <input type="text" name="usuario" required autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label>
            Contraseña
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
</div>
</body>
</html>
