<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

if (!can_manage_inventory()) {
    http_response_code(403);
    echo 'Solo administrador o supervisor pueden gestionar insumos.';
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
        echo 'Insumo no encontrado.';
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
        $unidad = trim((string) ($_POST['unidad_medida'] ?? 'unidad')) ?: 'unidad';
        $stock = (int) ($_POST['cantidad_stock'] ?? 0);
        $minimo = (int) ($_POST['stock_minimo'] ?? 0);
        $precio = trim((string) ($_POST['precio_unitario'] ?? ''));
        $ubicacion = trim((string) ($_POST['ubicacion'] ?? '')) ?: null;
        $postId = (int) ($_POST['id'] ?? 0);

        if ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } elseif ($stock < 0 || $minimo < 0) {
            $error = 'El stock debe ser un número positivo.';
        } else {
            $precioVal = $precio === '' ? null : $precio;
            try {
                if ($postId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE insumos SET codigo=?, nombre=?, descripcion=?, categoria=?, unidad_medida=?,
                         cantidad_stock=?, stock_minimo=?, precio_unitario=?, ubicacion=? WHERE id=? AND activo=1'
                    );
                    $stmt->execute([
                        $codigo, $nombre, $descripcion, $categoria, $unidad,
                        $stock, $minimo, $precioVal, $ubicacion, $postId,
                    ]);
                    $message = 'Insumo actualizado.';
                    $id = $postId;
                    $stmt = $pdo->prepare('SELECT * FROM insumos WHERE id = ? LIMIT 1');
                    $stmt->execute([$id]);
                    $row = $stmt->fetch();
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO insumos (codigo, nombre, descripcion, categoria, unidad_medida, cantidad_stock, stock_minimo, precio_unitario, ubicacion, activo)
                         VALUES (?,?,?,?,?,?,?,?,?,1)'
                    );
                    $stmt->execute([
                        $codigo, $nombre, $descripcion, $categoria, $unidad,
                        $stock, $minimo, $precioVal, $ubicacion,
                    ]);
                    header('Location: ' . url('inventario/index.php'));
                    exit;
                }
            } catch (PDOException $e) {
                if ((int) $e->errorInfo[1] === 1062) {
                    $error = 'Ya existe otro insumo con ese código.';
                } else {
                    $error = 'No se pudo guardar.';
                }
            }
        }
    }
}

$pageTitle = $id ? 'Editar insumo' : 'Nuevo insumo';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1><?= $id ? 'Editar insumo' : 'Nuevo insumo' ?></h1>

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

    <label>Código (opcional) <input type="text" name="codigo" value="<?= htmlspecialchars($row['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Nombre <input type="text" name="nombre" required value="<?= htmlspecialchars($row['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
    <label class="full">Descripción <textarea name="descripcion" rows="3"><?= htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></label>
    <label>Categoría <input type="text" name="categoria" value="<?= htmlspecialchars($row['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Unidad de medida <input type="text" name="unidad_medida" value="<?= htmlspecialchars($row['unidad_medida'] ?? 'unidad', ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Cantidad en stock <input type="number" step="1" min="0" name="cantidad_stock" value="<?= htmlspecialchars((string) ((int) ($row['cantidad_stock'] ?? '0')), ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Stock mínimo (alerta) <input type="number" step="1" min="0" name="stock_minimo" value="<?= htmlspecialchars((string) ((int) ($row['stock_minimo'] ?? '0')), ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Precio unitario (opcional) <input type="number" step="0.0001" name="precio_unitario" value="<?= htmlspecialchars((string) ($row['precio_unitario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Ubicación <input type="text" name="ubicacion" value="<?= htmlspecialchars($row['ubicacion'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>

    <div class="form-actions full">
        <button type="submit" class="btn btn-primary">Guardar</button>
        <a class="btn btn-outline" href="<?= htmlspecialchars(url('inventario/index.php'), ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
