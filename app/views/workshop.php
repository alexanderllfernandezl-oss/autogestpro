<?php
$nombre = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$rol = htmlspecialchars($_SESSION['rol'] ?? '', ENT_QUOTES, 'UTF-8');
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
$page = $page ?? ($_GET['page'] ?? 'reportes');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page === 'usuarios' ? 'Usuarios' : 'Reportes' ?> | AUTOGEST PRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css?v=20261116-responsive1" rel="stylesheet">
</head>
<body class="ag-app">
<?php require __DIR__ . '/layouts/sidebar.php'; ?>
<main class="ag-main">
<header class="ag-topbar"><button class="ag-menu-toggle" type="button" aria-label="Abrir menú" aria-controls="agSidebar">☰</button><strong><?= $page === 'usuarios' ? 'Usuarios' : 'Reportes' ?></strong></header>
<div class="ag-shell">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><?= $page === 'usuarios' ? 'Gestión de usuarios' : 'Reportes' ?></h1>
    </div>

    <?php if (!empty($mensaje)): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if ($page === 'usuarios'): ?>
        <div class="card shadow-sm mb-4"><div class="card-body">
            <h2 class="h5">Nuevo usuario</h2>
            <form method="POST" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="accion" value="crear">
                <div class="col-md-3"><label class="form-label">Nombre</label><input name="nombre" class="form-control" required maxlength="100"></div>
                <div class="col-md-3"><label class="form-label">Nombre de usuario</label><input name="usuario" id="nuevo-nombre-usuario" class="form-control" required maxlength="50" pattern="[A-Za-z0-9._-]+" title="Use solo letras, números, punto, guion o guion bajo" autocomplete="username"></div>
                <div class="col-md-3"><label class="form-label">Contraseña</label><input name="password" type="password" class="form-control" required minlength="8" autocomplete="new-password"></div>
                <div class="col-md-3"><label class="form-label">Rol</label><select name="rol_id" class="form-select" required>
                    <option value="" selected disabled>Seleccione un rol</option>
                    <option value="1">Administrador</option>
                    <option value="2">Recepcionista</option>
                    <option value="3">Mecánico</option>
                </select></div>
                <div class="col-12"><button class="btn btn-primary">Crear usuario</button></div>
            </form>
        </div></div>
        <div class="card shadow-sm"><div class="card-body table-responsive">
            <table class="table align-middle"><thead class="table-dark"><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($usuario['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>"><input type="hidden" name="accion" value="rol"><input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                            <select name="rol_id" class="form-select form-select-sm">
                                <option value="1" <?= $usuario['rol'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                                <option value="2" <?= $usuario['rol'] === 'recepcionista' ? 'selected' : '' ?>>Recepcionista</option>
                                <option value="3" <?= $usuario['rol'] === 'mecanico' ? 'selected' : '' ?>>Mecánico</option>
                            </select>
                            <button class="btn btn-sm btn-outline-primary">Cambiar</button>
                        </form>
                    </td>
                    <td><span class="badge <?= (int) $usuario['activo'] ? 'bg-success' : 'bg-secondary' ?>"><?= (int) $usuario['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                    <td>
                        <div class="d-flex flex-column align-items-start gap-2">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                                <input type="hidden" name="accion" value="<?= (int) $usuario['activo'] ? 'desactivar' : 'activar' ?>">
                                <button class="btn btn-sm <?= (int) $usuario['activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <?= (int) $usuario['activo'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                                <input type="hidden" name="accion" value="restablecer">
                                <input type="password" name="password" class="form-control form-control-sm" minlength="8" required placeholder="Nueva contraseña">
                                <button class="btn btn-sm btn-outline-warning text-nowrap">Restablecer</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div></div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card"><div class="card-body"><span class="text-muted">Órdenes</span><h2><?= (int) $reportes['ordenes']['total'] ?></h2></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><span class="text-muted">Órdenes abiertas</span><h2><?= (int) $reportes['abiertas']['total'] ?></h2></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><span class="text-muted">Existencias</span><h2><?= (int) $reportes['inventario']['unidades'] ?></h2></div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body"><span class="text-muted">Ingresos registrados</span><h2>Bs. <?= number_format((float) $reportes['ingresos']['total'], 2) ?></h2></div></div></div>
        </div>
        <div class="card"><div class="card-body">
            <p><strong>Productos con stock bajo:</strong> <?= (int) $reportes['bajos']['total'] ?></p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="?page=reportes&amp;download=pdf" download>Descargar reporte PDF</a>
                <a class="btn btn-outline-success" href="?page=reportes&amp;download=vehiculos" download>Descargar registro de vehículos</a>
            </div>
        </div></div>
    <?php endif; ?>
</div>
</main>
<script>
document.querySelectorAll('#nuevo-nombre-usuario, input[name="usuario"]').forEach(function (campo) {
    campo.addEventListener('input', function () {
        campo.value = campo.value.toLowerCase().replace(/[^a-z0-9._-]/g, '');
    });
});
</script>
</body>
</html>
