<?php
declare(strict_types=1);

// Crear y gestionar usuarios solo administrador; supervisor solo lectura
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

$user = current_user();
$isAdmin = ($user['rol'] ?? '') === 'administrador';

if (!$isAdmin && ($user['rol'] ?? '') !== 'supervisor') {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        $error = 'No tienes permisos para modificar usuarios.';
    } elseif (!csrf_verify($_POST['_csrf'] ?? null)) {
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
<h1>Usuarios</h1>
<p class="muted">Listado de todos los usuarios del sistema. Solo el <strong>administrador</strong> puede crear, activar/desactivar o eliminar usuarios.</p>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($isAdmin): ?>
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
<?php endif; ?>

<section class="section">
    <h2>Listado</h2>
    <div class="reservas-toolbar">
        <div class="reservas-filtros">
            <div class="filtro-group">
                <label class="filtro-label" for="searchUser">Buscar</label>
                <input type="text" id="searchUser" class="filtro-input" placeholder="Nombre o usuario…" style="min-width:180px">
            </div>
            <div class="filtro-group">
                <label class="filtro-label" for="filterRol">Rol</label>
                <select id="filterRol" class="filtro-select">
                    <option value="">Todos</option>
                    <option value="administrador">Administrador</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="residente">Residente</option>
                </select>
            </div>
            <div class="filtro-group">
                <label class="filtro-label" for="filterEstado">Estado</label>
                <select id="filterEstado" class="filtro-select">
                    <option value="">Todos</option>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                </select>
            </div>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table" id="tablaUsuarios">
            <thead>
            <tr>
                <th data-sort="nombre" class="sortable">Nombre <span class="sort-icon"></span></th>
                <th data-sort="usuario" class="sortable">Usuario <span class="sort-icon"></span></th>
                <th data-sort="rol" class="sortable">Rol <span class="sort-icon"></span></th>
                <th data-sort="estado" class="sortable">Estado <span class="sort-icon"></span></th>
                <?php if ($isAdmin): ?><th>Acciones</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr data-nombre="<?= htmlspecialchars($u['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                    data-usuario="<?= htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8') ?>"
                    data-rol="<?= htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8') ?>"
                    data-estado="<?= (int) $u['activo'] ? 'Activo' : 'Inactivo' ?>">
                    <td><?= htmlspecialchars($u['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($u['rol'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $u['activo'] ? 'Activo' : 'Inactivo' ?></td>
                    <?php if ($isAdmin): ?>
                    <td class="actions">
                        <?php if ((int) $u['id'] !== (int) $user['id']): ?>
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
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
(function () {
    var table = document.getElementById('tablaUsuarios');
    if (!table) return;
    var tbody = table.querySelector('tbody');
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

    var searchInput = document.getElementById('searchUser');
    var filterRol = document.getElementById('filterRol');
    var filterEstado = document.getElementById('filterEstado');

    var sortKey = 'nombre';
    var sortDir = 1;

    function filterRows() {
        var q = (searchInput.value || '').toLowerCase().trim();
        var rol = filterRol.value;
        var estado = filterEstado.value;
        return rows.filter(function (row) {
            var nombre = (row.getAttribute('data-nombre') || '').toLowerCase();
            var usuario = (row.getAttribute('data-usuario') || '').toLowerCase();
            var r = row.getAttribute('data-rol') || '';
            var e = row.getAttribute('data-estado') || '';
            if (q && nombre.indexOf(q) === -1 && usuario.indexOf(q) === -1) return false;
            if (rol && r !== rol) return false;
            if (estado && e.toLowerCase() !== estado) return false;
            return true;
        });
    }

    function sortRows(arr) {
        arr.sort(function (a, b) {
            var va = (a.getAttribute('data-' + sortKey) || '').toLowerCase();
            var vb = (b.getAttribute('data-' + sortKey) || '').toLowerCase();
            if (va < vb) return -1 * sortDir;
            if (va > vb) return 1 * sortDir;
            return 0;
        });
    }

    function render() {
        var filtered = filterRows();
        sortRows(filtered);
        rows.forEach(function (row) { row.style.display = 'none'; });
        filtered.forEach(function (row) { row.style.display = ''; tbody.appendChild(row); });
    }

    function updateSortIcons(key) {
        table.querySelectorAll('.sortable .sort-icon').forEach(function (icon) {
            icon.textContent = '';
        });
        var th = table.querySelector('th[data-sort="' + key + '"]');
        if (th) {
            var icon = th.querySelector('.sort-icon');
            if (icon) icon.textContent = sortDir === 1 ? ' ▲' : ' ▼';
        }
    }

    searchInput.addEventListener('input', render);
    filterRol.addEventListener('change', render);
    filterEstado.addEventListener('change', render);

    table.querySelectorAll('th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            var key = th.getAttribute('data-sort');
            if (key === sortKey) {
                sortDir *= -1;
            } else {
                sortKey = key;
                sortDir = 1;
            }
            updateSortIcons(key);
            render();
        });
    });

    updateSortIcons('nombre');
})();
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
