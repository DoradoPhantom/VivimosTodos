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
                // Baja logica no borra la fila para conservar historial
                $stmt = $pdo->prepare('UPDATE insumos SET activo = 0 WHERE id = ?');
                $stmt->execute([$id]);
                $message = 'Ítem dado de baja.';
            }
        }
    }
}

$insumos = $pdo->query(
    'SELECT id, codigo, nombre, categoria, cantidad_stock, precio_unitario, activo, estado_operativo
     FROM insumos WHERE activo = 1 ORDER BY nombre ASC'
)->fetchAll();

$pageTitle = 'Catálogo del salón';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Catálogo del salón</h1>
<p class="muted">
    <?php if ($canEdit): ?>
        Servicios, paquetes y extras para eventos. Puedes crear y editar ítems; el resto de roles solo consulta.
    <?php else: ?>
        Consulta del catálogo (solo lectura).
    <?php endif; ?>
</p>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($canEdit): ?>
    <p><a class="btn btn-primary" href="<?= htmlspecialchars(url('inventario/formulario.php'), ENT_QUOTES, 'UTF-8') ?>">Nuevo ítem</a></p>
<?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead>
        <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Categoría</th>
            <th>Cantidad</th>
            <th>Estado</th>
            <th>Precio ref.</th>
            <?php if ($canEdit): ?><th>Acciones</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($insumos as $row): ?>
            <?php
            $precio = $row['precio_unitario'];
            $precioCell = ($precio !== null && $precio !== '')
                ? htmlspecialchars(number_format((float) $precio, 2, ',', '.'), ENT_QUOTES, 'UTF-8')
                : '—';
            ?>
            <tr>
                <td><?= htmlspecialchars($row['codigo'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($row['categoria'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) round((float) ($row['cantidad_stock'] ?? 0)) ?></td>
                <?php
                $estadoOp = (string) ($row['estado_operativo'] ?? 'disponible');
                $estadoTxt = match ($estadoOp) {
                    'danado' => 'Dañado',
                    'reparacion' => 'En reparación',
                    default => 'Disponible',
                };
                ?>
                <td><span class="chip"><?= htmlspecialchars($estadoTxt, ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><?= $precioCell ?></td>
                <?php if ($canEdit): ?>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?= htmlspecialchars(url('inventario/formulario.php?id=' . (int) $row['id']), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                        <form method="post" class="inline-form" onsubmit="return confirm('¿Dar de baja este ítem del catálogo?');">
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
            <tr><td colspan="<?= $canEdit ? 7 : 6 ?>">No hay ítems en el catálogo.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
