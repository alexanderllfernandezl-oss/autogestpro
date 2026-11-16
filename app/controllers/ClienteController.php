
<?php

require_once __DIR__ . '/../models/Cliente.php';

class ClienteController
{
    public function index(): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $mensaje = $_SESSION['mensaje_cliente'] ?? null;
        unset($_SESSION['mensaje_cliente']);
        $error = null;
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $incluirInactivos = ($_GET['todos'] ?? '') === '1'
            && $_SESSION['rol'] === 'administrador';

        try {
            $modelo = new Cliente();

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->procesarFormulario($modelo);
            }

            $clientes = $modelo->listar($busqueda, $incluirInactivos);
            require __DIR__ . '/../views/clientes/index.php';
        } catch (Throwable $e) {
            http_response_code(500);
            $error = 'No se pudo procesar la operación de clientes.';
            $clientes = [];
            require __DIR__ . '/../views/clientes/index.php';
        }
    }

    private function procesarFormulario(Cliente $modelo): void
    {
        $rol = $_SESSION['rol'] ?? '';
        if (!in_array($rol, ['administrador', 'recepcionista'], true)) {
            http_response_code(403);
            throw new RuntimeException('No autorizado');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            throw new RuntimeException('Token inválido');
        }

        $accion = $_POST['accion'] ?? '';

        if ($accion === 'desactivar' || $accion === 'activar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Cliente inválido');
            }
            $modelo->cambiarEstado((int) $id, $accion === 'activar');
            $_SESSION['mensaje_cliente'] = $accion === 'activar'
                ? 'Cliente activado correctamente.'
                : 'Cliente desactivado correctamente.';
            header('Location: ?page=clientes');
            exit;
        }

        $datos = [
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'telefono' => trim((string) ($_POST['telefono'] ?? '')),
            'direccion' => trim((string) ($_POST['direccion'] ?? '')),
            'correo' => trim((string) ($_POST['correo'] ?? '')),
        ];

        if ($datos['nombre'] === '') {
            throw new InvalidArgumentException('El nombre es obligatorio');
        }
        if ($datos['telefono'] === '') {
            throw new InvalidArgumentException('El teléfono es obligatorio.');
        }
        if ($datos['direccion'] === '') {
            throw new InvalidArgumentException('La dirección es obligatoria.');
        }
        if ($datos['correo'] !== '' && !filter_var($datos['correo'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo no es válido');
        }

        $datos['nombre'] = mb_substr($datos['nombre'], 0, 100, 'UTF-8');
        $datos['telefono'] = mb_substr($datos['telefono'], 0, 20, 'UTF-8');
        $datos['direccion'] = mb_substr($datos['direccion'], 0, 200, 'UTF-8');
        $datos['correo'] = mb_substr($datos['correo'], 0, 100, 'UTF-8');

        if ($accion === 'crear') {
            $modelo->crear($datos);
            $_SESSION['mensaje_cliente'] = 'Cliente registrado correctamente.';
        } elseif ($accion === 'editar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Cliente inválido');
            }
            $modelo->actualizar((int) $id, $datos);
            $_SESSION['mensaje_cliente'] = 'Cliente actualizado correctamente.';
        } else {
            throw new InvalidArgumentException('Acción inválida');
        }

        header('Location: ?page=clientes');
        exit;
    }
}
