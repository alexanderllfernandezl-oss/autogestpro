<?php

require_once __DIR__ . '/../models/Mecanico.php';

class MecanicoController
{
    public function index(): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }

        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $mensaje = $_SESSION['mensaje_mecanico'] ?? null;
        unset($_SESSION['mensaje_mecanico']);
        $error = null;
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $modelo = new Mecanico();

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->procesarFormulario($modelo);
            $soloActivos = ($_GET['activos'] ?? '') === '1' && $_SESSION['rol'] === 'administrador';
            $incluirInactivos = !$soloActivos;
            $mecanicos = $modelo->listar($busqueda, $incluirInactivos);
            require __DIR__ . '/../views/mecanicos/index.php';
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $soloActivos = ($_GET['activos'] ?? '') === '1' && $_SESSION['rol'] === 'administrador';
            $incluirInactivos = !$soloActivos;
            $mecanicos = $modelo->listar($busqueda, $incluirInactivos);
            require __DIR__ . '/../views/mecanicos/index.php';
        } catch (Throwable $e) {
            http_response_code(500);
            $error = 'No se pudo completar la operación de mecánicos.';
            $mecanicos = [];
            require __DIR__ . '/../views/mecanicos/index.php';
        }
    }

    private function procesarFormulario(Mecanico $modelo): void
    {
        if ($_SESSION['rol'] !== 'administrador') {
            http_response_code(403);
            throw new RuntimeException('No autorizado');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            throw new RuntimeException('Solicitud inválida');
        }

        $accion = $_POST['accion'] ?? '';
        if ($accion === 'eliminar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Mecánico inválido.');
            $modelo->eliminar((int) $id);
            $_SESSION['mensaje_mecanico'] = 'Mecánico eliminado correctamente.';
            header('Location: ?page=mecanicos');
            exit;
        }
        if ($accion === 'activar' || $accion === 'desactivar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Mecánico inválido');
            $modelo->cambiarEstado((int) $id, $accion === 'activar');
            $_SESSION['mensaje_mecanico'] = $accion === 'activar' ? 'Mecánico activado correctamente.' : 'Mecánico desactivado correctamente.';
            header('Location: ?page=mecanicos');
            exit;
        }

        $datos = [
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'telefono' => trim((string) ($_POST['telefono'] ?? '')),
            'especialidad' => trim((string) ($_POST['especialidad'] ?? '')),
        ];

        if ($datos['nombre'] === '') throw new InvalidArgumentException('El nombre es obligatorio.');
        foreach (['nombre' => 100, 'telefono' => 20, 'especialidad' => 100] as $campo => $longitud) {
            $datos[$campo] = mb_substr($datos[$campo], 0, $longitud, 'UTF-8');
        }

        if ($accion === 'crear') {
            $modelo->crear($datos);
            $_SESSION['mensaje_mecanico'] = 'Mecánico registrado correctamente.';
        } elseif ($accion === 'editar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Mecánico inválido');
            $modelo->actualizar((int) $id, $datos);
            $_SESSION['mensaje_mecanico'] = 'Mecánico actualizado correctamente.';
        } else {
            throw new InvalidArgumentException('Acción inválida');
        }

        header('Location: ?page=mecanicos');
        exit;
    }
}
