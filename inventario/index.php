<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

$pdo = db();
$message = '';
$error = '';
$canEdit = can_manage_inventory();

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE insumos SET activo = 0 WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Insumo dado de baja (soft delete).';
            }
        }
    }
}

$insumos = $pdo->query(
    'SELECT id, codigo, nombre, categoria, unidad_medida, cantidad_stock, stock_minimo, ubicacion, activo
     FROM insumos WHERE activo = 1 ORDER BY nombre ASC'
)->fetchAll();

$pageTitle = 'Inventario';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Inventario de insumos</h1>
<p class="muted">
    <?php if ($canEdit): ?>
        Como administrador o supervisor puedes crear y editar insumos. Los residentes solo consultan.
    <?php else: ?>
        Consulta de insumos (solo lectura).
    <?php endif; ?>
</p>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($canEdit): ?>
    <p><a class="btn btn-primary" href="<?= htmlspecialchars(url('inventario/formulario.php'), ENT_QUOTES, 'UTF-8') ?>">Nuevo insumo</a></p>
<?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead>
        <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Unidad</th>
            <th>Stock</th>
            <th>Mínimo</th>
            <th>Ubicación</th>
            <?php if ($canEdit): ?><th>Acciones</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($insumos as $row): ?>
            <?php
            $low = (float) $row['cantidad_stock'] <= (float) $row['stock_minimo'];
            ?>
            <tr class="<?= $low ? 'row-warning' : '' ?>">
                <td><?= htmlspecialchars($row['codigo'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['categoria'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['unidad_medida'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $row['cantidad_stock'], ENT_QUOTES, 'UTF-8') ?><?= $low ? ' ⚠' : '' ?></td>
                <td><?= htmlspecialchars((string) $row['stock_minimo'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['ubicacion'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <?php if ($canEdit): ?>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?= htmlspecialchars(url('inventario/formulario.php?id=' . (int) $row['id']), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                        <form method="post" class="inline-form" onsubmit="return confirm('¿Dar de baja este insumo?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Baja</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (count($insumos) === 0): ?>
            <tr><td colspan="<?= $canEdit ? 8 : 7 ?>">No hay insumos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
