<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $nombre = trim((string) ($_POST['nombre_completo'] ?? ''));
            $usuarioLogin = trim((string) ($_POST['usuario'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $rol = (string) ($_POST['rol'] ?? 'residente');
            $allowed = ['administrador', 'residente', 'supervisor'];
            if ($nombre === '' || $usuarioLogin === '' || $password === '') {
                $error = 'Nombre, usuario de acceso y contraseña son obligatorios.';
            } elseif (strlen($usuarioLogin) < 3 || strlen($usuarioLogin) > 80) {
                $error = 'El usuario debe tener entre 3 y 80 caracteres.';
            } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $usuarioLogin)) {
                $error = 'El usuario solo puede usar letras, números, punto, guion y guion bajo.';
            } elseif (!in_array($rol, $allowed, true)) {
                $error = 'Rol no válido.';
            } elseif (strlen($password) < 6) {
                $error = 'La contraseña debe tener al menos 6 caracteres.';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        'INSERT INTO usuarios (nombre_completo, usuario, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)'
                    );
                    $stmt->execute([$nombre, $usuarioLogin, $hash, $rol]);
                    $message = 'Usuario creado correctamente.';
                } catch (PDOException $e) {
                    if ((int) $e->errorInfo[1] === 1062) {
                        $error = 'Ese nombre de usuario ya está en uso.';
                    } else {
                        $error = 'No se pudo crear el usuario.';
                    }
                }
            }
        } elseif ($action === 'toggle_activo') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0 && $id !== (int) current_user()['id']) {
                $stmt = $pdo->prepare('UPDATE usuarios SET activo = IF(activo=1,0,1) WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Estado del usuario actualizado.';
            } elseif ($id === (int) current_user()['id']) {
                $error = 'No puedes desactivarte a ti mismo.';
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0 && $id !== (int) current_user()['id']) {
                $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Usuario eliminado.';
            } elseif ($id === (int) current_user()['id']) {
                $error = 'No puedes eliminar tu propia cuenta.';
            }
        }
    }
}

$usuarios = $pdo->query(
    'SELECT id, nombre_completo, usuario, rol, activo, creado_en FROM usuarios ORDER BY creado_en DESC'
)->fetchAll();

$pageTitle = 'Usuarios';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Administración de usuarios</h1>
<p class="muted">Solo el <strong>administrador</strong> puede crear, activar/desactivar o eliminar usuarios.</p>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="section">
    <h2>Nuevo usuario</h2>
    <p class="muted">El <strong>nombre completo</strong> identifica a la persona o unidad. El <strong>usuario</strong> es lo que se escribe al entrar (p. ej. <code>apto301</code>): suele fijarse por apartamento o rol, independiente de quién sea el arrendatario.</p>
    <form method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="create">
        <label>Nombre completo <input type="text" name="nombre_completo" required></label>
        <label>Usuario de acceso <input type="text" name="usuario" required minlength="3" maxlength="80" pattern="[a-zA-Z0-9._-]+" title="Letras, números, . _ -"></label>
        <label>Contraseña <input type="password" name="password" required minlength="6"></label>
        <label>Rol
            <select name="rol">
                <option value="residente">Residente</option>
                <option value="supervisor">Supervisor</option>
                <option value="administrador">Administrador</option>
            </select>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Crear usuario</button>
        </div>
    </form>
</section>

<section class="section">
    <h2>Listado</h2>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $u['activo'] ? 'Activo' : 'Inactivo' ?></td>
                    <td class="actions">
                        <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
                            <form method="post" class="inline-form" onsubmit="return confirm('¿Cambiar estado activo/inactivo?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="toggle_activo">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-sm"><?= (int) $u['activo'] ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <form method="post" class="inline-form" onsubmit="return confirm('¿Eliminar definitivamente este usuario?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">(tú)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
