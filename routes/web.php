
<?php
require_once __DIR__ . '/../app/controllers/ClienteController.php';
require_once __DIR__ . '/../app/controllers/HomeController.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/VehiculoController.php';
require_once __DIR__ . '/../app/controllers/MecanicoController.php';
require_once __DIR__ . '/../app/controllers/RepuestoController.php';
require_once __DIR__ . '/../app/controllers/OrdenController.php';
require_once __DIR__ . '/../app/controllers/WorkshopController.php';

$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'login':
        if (isset($_SESSION['usuario_id'])) {
            header('Location: ?page=home');
            exit;
        }

        (new AuthController())->login();
        break;

    case 'logout':
        (new AuthController())->logout();
        break;

    case 'home':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if (($_SESSION['rol'] ?? '') === 'mecanico') {
            header('Location: ?page=ordenes');
            exit;
        }

        (new HomeController())->index();
        break;
    case 'clientes':
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ?page=login');
        exit;
    }
    if (($_SESSION['rol'] ?? '') === 'mecanico') {
        http_response_code(403);
        echo 'Acceso denegado.';
        exit;
    }

    (new ClienteController())->index();
    break;

    case 'vehiculos':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if (($_SESSION['rol'] ?? '') === 'mecanico') {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }

        (new VehiculoController())->index();
        break;

    case 'mecanicos':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if (($_SESSION['rol'] ?? '') === 'mecanico') {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }

        (new MecanicoController())->index();
        break;

    case 'ordenes':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }

        (new OrdenController())->index();
        break;

    case 'repuestos':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if (($_SESSION['rol'] ?? '') === 'mecanico') {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }

        (new RepuestoController())->index();
        break;

    case 'reportes':
    case 'usuarios':
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        (new WorkshopController())->module($page);
        break;

    default:
        http_response_code(404);
        echo 'Página no encontrada.';
        break;
}
