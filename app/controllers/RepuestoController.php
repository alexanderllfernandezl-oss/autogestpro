<?php

require_once __DIR__ . '/../models/Repuesto.php';

class RepuestoController
{
    public function index(): void
    {
        if (!isset($_SESSION['usuario_id'])) { header('Location: ?page=login'); exit; }
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $mensaje = $_SESSION['mensaje_repuesto'] ?? null;
        unset($_SESSION['mensaje_repuesto']);
        $error = null;
        $busqueda = trim((string) ($_GET['q'] ?? ''));
        $soloBajos = ($_GET['bajos'] ?? '') === '1';
        $modelo = new Repuesto();

        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') $this->procesarFormulario($modelo);
            $repuestos = $modelo->listar($busqueda, $soloBajos);
            require __DIR__ . '/../views/repuestos/index.php';
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $repuestos = $modelo->listar($busqueda, $soloBajos);
            require __DIR__ . '/../views/repuestos/index.php';
        } catch (Throwable $e) {
            http_response_code(500);
            $error = 'No se pudo completar la operación de repuestos.';
            $repuestos = [];
            require __DIR__ . '/../views/repuestos/index.php';
        }
    }

    private function procesarFormulario(Repuesto $modelo): void
    {
        $rol = $_SESSION['rol'] ?? '';
        if (!in_array($rol, ['administrador', 'recepcionista'], true)) { http_response_code(403); throw new RuntimeException('No autorizado'); }

        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) { http_response_code(403); throw new RuntimeException('Solicitud inválida'); }

        $accion = $_POST['accion'] ?? '';
        if ($accion === 'movimiento') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $tipo = (string) ($_POST['tipo'] ?? '');
            $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
            if (!$id || $cantidad === false || $cantidad === null) throw new InvalidArgumentException('Datos de movimiento incompletos.');
            if ($cantidad < 0 || ($tipo !== 'ajuste' && $cantidad === 0)) throw new InvalidArgumentException('La cantidad del movimiento no es válida.');
            $observacion = trim((string) ($_POST['observacion'] ?? ''));
            $modelo->registrarMovimiento((int) $id, (int) $_SESSION['usuario_id'], $tipo, (int) $cantidad, $observacion);
            $_SESSION['mensaje_repuesto'] = 'Movimiento de inventario registrado.';
            header('Location: ?page=repuestos'); exit;
        }

        $stockEnviado = $_POST['stock'] ?? '';
        $stockMinimoEnviado = $_POST['stock_minimo'] ?? '';
        $datos = [
            'codigo' => strtoupper(trim((string) ($_POST['codigo'] ?? ''))),
            'nombre' => trim((string) ($_POST['nombre'] ?? '')),
            'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
            'stock' => $stockEnviado === '' ? 0 : filter_var($stockEnviado, FILTER_VALIDATE_INT),
            'stock_minimo' => $stockMinimoEnviado === '' ? 0 : filter_var($stockMinimoEnviado, FILTER_VALIDATE_INT),
            'precio_referencia' => isset($_POST['precio_referencia']) && $_POST['precio_referencia'] !== '' ? filter_var($_POST['precio_referencia'], FILTER_VALIDATE_FLOAT) : null,
        ];

        if ($datos['stock'] === false || $datos['stock_minimo'] === false) {
            throw new InvalidArgumentException('El stock debe ser un número entero válido.');
        }
        if ($datos['codigo'] === '') throw new InvalidArgumentException('El código es obligatorio.');
        if ($datos['nombre'] === '') throw new InvalidArgumentException('El nombre es obligatorio.');
        if ($datos['stock'] < 0 || $datos['stock_minimo'] < 0) throw new InvalidArgumentException('El stock no puede ser negativo.');
        if ($datos['precio_referencia'] === false) throw new InvalidArgumentException('El precio no es válido.');
        if ($datos['precio_referencia'] !== null && $datos['precio_referencia'] < 0) throw new InvalidArgumentException('El precio no puede ser negativo.');
        foreach ([['codigo', 40], ['nombre', 120], ['descripcion', 255]] as $campo) $datos[$campo[0]] = mb_substr($datos[$campo[0]], 0, $campo[1], 'UTF-8');

        if ($accion === 'crear') {
            if ($modelo->codigoExiste($datos['codigo'])) throw new InvalidArgumentException('Ya existe un repuesto con ese código.');
            $modelo->crear($datos);
            $_SESSION['mensaje_repuesto'] = 'Repuesto registrado correctamente.';
        } elseif ($accion === 'editar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Repuesto inválido');
            if ($modelo->codigoExiste($datos['codigo'], (int) $id)) throw new InvalidArgumentException('Ya existe otro repuesto con ese código.');
            unset($datos['stock']);
            $modelo->actualizar((int) $id, $datos);
            $_SESSION['mensaje_repuesto'] = 'Repuesto actualizado correctamente.';
        } else throw new InvalidArgumentException('Acción inválida');

        header('Location: ?page=repuestos'); exit;
    }
}
