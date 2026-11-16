<?php
$rolMenu = $_SESSION['rol'] ?? '';
$paginaMenu = $_GET['page'] ?? 'home';
$nombreMenu = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$rolMenuTexto = htmlspecialchars(match ($rolMenu) {
    'administrador' => 'Administrador',
    'recepcionista' => 'Recepcionista',
    'mecanico' => 'Mecánico',
    default => $rolMenu,
}, ENT_QUOTES, 'UTF-8');
$csrfMenu = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');

$menuItems = [
    'home' => ['Inicio', '⌂', ['administrador', 'recepcionista']],
    'ordenes' => [$rolMenu === 'mecanico' ? 'Mis órdenes' : 'Órdenes de trabajo', '▣', ['administrador', 'recepcionista', 'mecanico']],
    'repuestos' => ['Inventario', '⬡', ['administrador', 'recepcionista']],
    'clientes' => ['Clientes', '♙', ['administrador', 'recepcionista']],
    'vehiculos' => ['Vehículos', '◇', ['administrador', 'recepcionista']],
    'mecanicos' => ['Mecánicos', '♟', ['administrador', 'recepcionista']],
    'reportes' => ['Reportes', '▥', ['administrador', 'recepcionista']],
    'usuarios' => ['Usuarios', '♟', ['administrador']],
];
$brandHref = $rolMenu === 'mecanico' ? '?page=ordenes' : '?page=home';
?>
<aside class="ag-sidebar" id="agSidebar" aria-label="Menú principal">
    <a class="ag-sidebar-brand" href="<?= $brandHref ?>">
        <img class="ag-brand-mark" src="../img/logo2.png?v=2" alt="AUTOGEST PRO">
        <span>AUTOGEST <b>PRO</b></span>
    </a>
    <button class="ag-sidebar-close" type="button" aria-label="Cerrar menú">×</button>
    <nav class="ag-sidebar-nav" aria-label="Navegación principal">
        <?php foreach ($menuItems as $clave => [$titulo, $icono, $roles]): ?>
            <?php if (!in_array($rolMenu, $roles, true)) continue; ?>
            <a class="<?= $paginaMenu === $clave ? 'active' : '' ?>" href="?page=<?= $clave ?>" aria-current="<?= $paginaMenu === $clave ? 'page' : 'false' ?>">
                <span><?= $icono ?></span><?= $titulo ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="ag-sidebar-footer">
        <div class="small">
            <strong><?= $nombreMenu ?></strong><br>
            <span><?= $rolMenuTexto ?></span>
        </div>
        <form method="POST" action="?page=logout">
            <input type="hidden" name="csrf_token" value="<?= $csrfMenu ?>">
            <button class="btn btn-link p-0">Cerrar sesión</button>
        </form>
    </div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector('.ag-menu-toggle');
    const sidebar = document.getElementById('agSidebar');
    if (!toggle || !sidebar) return;
    const closeButton = sidebar.querySelector('.ag-sidebar-close');

    function closeMobileMenu() {
        sidebar.classList.remove('open');
        document.body.classList.remove('ag-mobile-menu-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menú');
    }

    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir menú');

    closeButton.addEventListener('click', closeMobileMenu);

    toggle.addEventListener('click', function () {
        if (window.innerWidth <= 992) {
            sidebar.classList.toggle('open');
            const isOpen = sidebar.classList.contains('open');
            document.body.classList.toggle('ag-mobile-menu-open', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggle.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
        } else {
            return;
        }
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 992) {
                closeMobileMenu();
            }
        });
    });

    if (window.innerWidth > 992) {
        document.body.classList.remove('ag-sidebar-collapsed');
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && window.innerWidth <= 992) {
            closeMobileMenu();
        }
    });

    document.addEventListener('click', function (event) {
        if (
            window.innerWidth <= 992 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(event.target) &&
            !toggle.contains(event.target)
        ) {
            closeMobileMenu();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            closeMobileMenu();
        }
    });
});
</script>
