<?php
declare(strict_types=1);

// Crear o editar items del catalogo admin o supervisor
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

if (!can_manage_inventory()) {
    http_response_code(403);
    echo 'Solo administrador o supervisor pueden gestionar el catálogo del salón.';
    exit;
}

$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = null;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM insumos WHERE id = ? AND activo = 1 LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo 'Ítem no encontrado.';
        exit;
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $codigo = trim((string) ($_POST['codigo'] ?? '')) ?: null;
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? '')) ?: null;
        $categoria = trim((string) ($_POST['categoria'] ?? '')) ?: null;
        $precio = trim((string) ($_POST['precio_unitario'] ?? ''));
        $cantidad = max(0, (int) ($_POST['cantidad_stock'] ?? 0));
        $estadoOperativo = (string) ($_POST['estado_operativo'] ?? 'disponible');
        $postId = (int) ($_POST['id'] ?? 0);

        if ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } else {
            $allowedEstados = ['disponible', 'danado', 'reparacion'];
            if (!in_array($estadoOperativo, $allowedEstados, true)) {
                $estadoOperativo = 'disponible';
            }
            $precioVal = $precio === '' ? null : $precio;
            try {
                if ($postId > 0) {
                    try {
                        $stmt = $pdo->prepare(
                            'UPDATE insumos
                             SET codigo=?, nombre=?, descripcion=?, categoria=?, cantidad_stock=?, precio_unitario=?, estado_operativo=?
                             WHERE id=? AND activo=1'
                        );
                        $stmt->execute([
                            $codigo, $nombre, $descripcion, $categoria, $cantidad, $precioVal, $estadoOperativo, $postId,
                        ]);
                    } catch (PDOException $e) {
                        // Si falta la columna estado se guarda sin ese campo
                        $stmt = $pdo->prepare(
                            'UPDATE insumos SET codigo=?, nombre=?, descripcion=?, categoria=?, cantidad_stock=?, precio_unitario=? WHERE id=? AND activo=1'
                        );
                        $stmt->execute([
                            $codigo, $nombre, $descripcion, $categoria, $cantidad, $precioVal, $postId,
                        ]);
                    }
                    $message = 'Ítem actualizado.';
                    $id = $postId;
                    $stmt = $pdo->prepare('SELECT * FROM insumos WHERE id = ? LIMIT 1');
                    $stmt->execute([$id]);
                    $row = $stmt->fetch();
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            'INSERT INTO insumos (codigo, nombre, descripcion, categoria, unidad_medida, cantidad_stock, stock_minimo, precio_unitario, ubicacion, estado_operativo, activo)
                             VALUES (?,?,?,?, \'servicio\', ?, 0, ?, NULL, ?, 1)'
                        );
                        $stmt->execute([
                            $codigo, $nombre, $descripcion, $categoria, $cantidad, $precioVal, $estadoOperativo,
                        ]);
                    } catch (PDOException $e) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO insumos (codigo, nombre, descripcion, categoria, unidad_medida, cantidad_stock, stock_minimo, precio_unitario, ubicacion, activo)
                             VALUES (?,?,?,?, \'servicio\', ?, 0, ?, NULL, 1)'
                        );
                        $stmt->execute([
                            $codigo, $nombre, $descripcion, $categoria, $cantidad, $precioVal,
                        ]);
                    }
                    header('Location: ' . url('inventario/index.php'));
                    exit;
                }
            } catch (PDOException $e) {
                if ((int) $e->errorInfo[1] === 1062) {
                    $error = 'Ya existe otro ítem con ese código.';
                } else {
                    $error = 'No se pudo guardar.';
                }
            }
        }
    }
}

$pageTitle = $id ? 'Editar ítem del catálogo' : 'Nuevo ítem del catálogo';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1><?= $id ? 'Editar ítem' : 'Nuevo ítem' ?></h1>
<p class="muted">Servicios, paquetes o extras para eventos en el salón.</p>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($id): ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php endif; ?>

    <label>Código interno (opcional) <input type="text" name="codigo" value="<?= htmlspecialchars($row['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Nombre <input type="text" name="nombre" required value="<?= htmlspecialchars($row['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
    <label class="full">Descripción <textarea name="descripcion" rows="3" placeholder="Qué incluye, duración, condiciones…"><?= htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></label>
    <label>Categoría <input type="text" name="categoria" placeholder="Ej. catering, sonido, decoración" value="<?= htmlspecialchars($row['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Cantidad disponible <input type="number" name="cantidad_stock" min="0" step="1" value="<?= htmlspecialchars((string) (isset($row['cantidad_stock']) ? (int) round((float) $row['cantidad_stock']) : 0), ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Precio de referencia (opcional) <input type="number" step="0.01" min="0" name="precio_unitario" value="<?= htmlspecialchars((string) ($row['precio_unitario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
    <?php
    $estadoVal = (string) ($row['estado_operativo'] ?? 'disponible');
    ?>
    <label>Estado del insumo
        <select name="estado_operativo">
            <option value="disponible" <?= $estadoVal === 'disponible' ? 'selected' : '' ?>>Disponible</option>
            <option value="danado" <?= $estadoVal === 'danado' ? 'selected' : '' ?>>Dañado</option>
            <option value="reparacion" <?= $estadoVal === 'reparacion' ? 'selected' : '' ?>>En reparación</option>
        </select>
    </label>

    <div class="form-actions full">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a class="btn btn-outline" href="<?= htmlspecialchars(url('inventario/index.php'), ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
