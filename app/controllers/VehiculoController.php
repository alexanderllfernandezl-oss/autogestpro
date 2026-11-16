<?php

require_once __DIR__ . '/../models/Vehiculo.php';
require_once __DIR__ . '/../models/Orden.php';

class VehiculoController
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

        $mensaje = $_SESSION['mensaje_vehiculo'] ?? null;
        unset($_SESSION['mensaje_vehiculo']);
        $error = null;
        $busqueda = trim((string) ($_GET['q'] ?? ''));

        try {
            $modelo = new Vehiculo();
            $modeloOrden = new Orden();
            $tarifasTrabajos = $modeloOrden->tarifasTareas();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (($_POST['accion'] ?? '') === 'actualizar_tarifas') {
                    $this->procesarTarifas($modeloOrden);
                }
                $this->procesarFormulario($modelo);
            }

            $incluirPrecios = in_array($_SESSION['rol'] ?? '', ['administrador', 'recepcionista'], true);
            $vehiculos = $modelo->listar($busqueda, $incluirPrecios);
            $propietarios = $modelo->listarPropietarios();
            require __DIR__ . '/../views/vehiculos/index.php';
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $incluirPrecios = in_array($_SESSION['rol'] ?? '', ['administrador', 'recepcionista'], true);
            $vehiculos = $modelo->listar($busqueda, $incluirPrecios);
            $propietarios = $modelo->listarPropietarios();
            $tarifasTrabajos = isset($modeloOrden) ? $modeloOrden->tarifasTareas() : [];
            require __DIR__ . '/../views/vehiculos/index.php';
        } catch (Throwable $e) {
            http_response_code(500);
            $error = 'No se pudo completar la operación de vehículos.';
            $vehiculos = [];
            $propietarios = [];
            $tarifasTrabajos = [];
            require __DIR__ . '/../views/vehiculos/index.php';
        }
    }

    private function procesarTarifas(Orden $modelo): void
    {
        if (($_SESSION['rol'] ?? '') !== 'administrador') {
            http_response_code(403);
            throw new InvalidArgumentException('Solo el administrador puede modificar las tarifas.');
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            throw new InvalidArgumentException('Solicitud inválida');
        }

        $preciosEnviados = $_POST['precios'] ?? null;
        if (!is_array($preciosEnviados)) {
            throw new InvalidArgumentException('No se recibieron tarifas para guardar.');
        }

        $precios = [];
        foreach (Orden::preciosReferencialesTareas() as $tarea => $precioActual) {
            if (!array_key_exists($tarea, $preciosEnviados) || !is_scalar($preciosEnviados[$tarea])) {
                throw new InvalidArgumentException('Completa todas las tarifas del catálogo.');
            }
            $precio = filter_var($preciosEnviados[$tarea], FILTER_VALIDATE_FLOAT);
            if ($precio === false || $precio < 0) {
                throw new InvalidArgumentException('Las tarifas deben ser números iguales o mayores que cero.');
            }
            $precios[$tarea] = (float) $precio;
        }

        $modelo->guardarTarifasTareas($precios);
        $_SESSION['mensaje_vehiculo'] = 'Tarifas de trabajos sugeridos actualizadas correctamente.';
        header('Location: ?page=vehiculos#tarifas-trabajos');
        exit;
    }

    private function procesarFormulario(Vehiculo $modelo): void
    {
        $rol = $_SESSION['rol'] ?? '';
        if (!in_array($rol, ['administrador', 'recepcionista'], true)) {
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
            if (!$id) {
                throw new InvalidArgumentException('Vehículo inválido');
            }

            try {
                $imagenes = $modelo->imagenes((int) $id);
                $modelo->eliminar((int) $id);
                foreach ($imagenes as $imagen) {
                    $archivo = __DIR__ . '/../../public/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $imagen['ruta']);
                    if (is_file($archivo) && !unlink($archivo)) {
                        error_log('No se pudo borrar el archivo de imagen: ' . $archivo);
                    }
                }
                $_SESSION['mensaje_vehiculo'] = 'Vehículo eliminado correctamente.';
            } catch (PDOException $e) {
                throw new InvalidArgumentException('No se puede eliminar un vehículo con órdenes registradas.');
            }

            header('Location: ?page=vehiculos');
            exit;
        }

        $propietario = mb_substr(
            trim((string) ($_POST['propietario'] ?? '')),
            0,
            100,
            'UTF-8'
        );
        $telefonoPropietario = trim((string) ($_POST['telefono_propietario'] ?? ''));
        $direccionPropietario = trim((string) ($_POST['direccion_propietario'] ?? ''));
        if (!in_array($accion, ['crear', 'editar'], true)) {
            throw new InvalidArgumentException('Acción inválida');
        }

        $datos = [
            'placa' => strtoupper(trim((string) ($_POST['placa'] ?? ''))),
            'marca' => trim((string) ($_POST['marca'] ?? '')),
            'modelo' => trim((string) ($_POST['modelo'] ?? '')),
            'anio' => filter_var($_POST['anio'] ?? null, FILTER_VALIDATE_INT),
            'color' => trim((string) ($_POST['color'] ?? '')),
            'diagnostico' => mb_substr(trim((string) ($_POST['diagnostico'] ?? '')), 0, 3000, 'UTF-8'),
            'estado' => trim((string) ($_POST['estado'] ?? 'registrado')),
            'fecha_registro' => null,
        ];

        $estados = ['registrado', 'en_revision', 'pendiente_repuesto', 'en_reparacion', 'listo', 'entregado'];
        if (!in_array($datos['estado'], $estados, true)) {
            $datos['estado'] = 'registrado';
        }

        if ($propietario === '') throw new InvalidArgumentException('El nombre del propietario es obligatorio.');
        if ($datos['placa'] === '') throw new InvalidArgumentException('La placa es obligatoria.');
        if (!preg_match('/^[A-Z0-9 ]+$/', $datos['placa'])) {
            throw new InvalidArgumentException('La placa solo puede contener letras, números y espacios.');
        }
        if ($datos['marca'] === '') throw new InvalidArgumentException('La marca es obligatoria.');
        if ($datos['modelo'] === '') throw new InvalidArgumentException('El modelo es obligatorio.');
        if ($datos['anio'] === false || $datos['anio'] < 1900 || $datos['anio'] > 2100) {
            $datos['anio'] = null;
        }

        if ($accion === 'crear') {
            $fechaSeleccionada = trim((string) ($_POST['fecha_registro_fecha'] ?? ''));
            $horaSeleccionada = trim((string) ($_POST['fecha_registro_hora'] ?? ''));
            if ($fechaSeleccionada === '' || $horaSeleccionada === '') {
                throw new InvalidArgumentException('Debes seleccionar la fecha y la hora.');
            }
            $fecha = DateTime::createFromFormat('!Y-m-d H:i', $fechaSeleccionada . ' ' . $horaSeleccionada);
            if (!$fecha || $fecha->format('Y-m-d H:i') !== $fechaSeleccionada . ' ' . $horaSeleccionada) {
                throw new InvalidArgumentException('La fecha y hora seleccionadas no son válidas.');
            }
            if ($fecha->format('Y-m-d') < date('Y-m-d')) {
                throw new InvalidArgumentException('La fecha de registro no puede ser anterior a hoy.');
            }
            $datos['fecha_registro'] = $fecha->format('Y-m-d H:i:s');
        }

        $datos['placa'] = mb_substr($datos['placa'], 0, 20, 'UTF-8');
        $datos['marca'] = mb_substr($datos['marca'], 0, 60, 'UTF-8');
        $datos['modelo'] = mb_substr($datos['modelo'], 0, 60, 'UTF-8');
        $datos['color'] = mb_substr($datos['color'], 0, 40, 'UTF-8');
        if ($accion === 'crear') {
            if ($modelo->placaExiste($datos['placa'])) throw new InvalidArgumentException('Ya existe un vehículo con esa placa.');
            $datos['cliente_id'] = $modelo->buscarOCrearPropietario($propietario, $telefonoPropietario, $direccionPropietario);
            $vehiculoId = $modelo->crear($datos);
            $this->guardarImagenes($modelo, $vehiculoId);
            $_SESSION['mensaje_vehiculo'] = 'Vehículo registrado correctamente.';
        } elseif ($accion === 'editar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) throw new InvalidArgumentException('Vehículo inválido');
            if ($modelo->placaExiste($datos['placa'], (int) $id)) throw new InvalidArgumentException('Ya existe otro vehículo con esa placa.');
            $datos['cliente_id'] = $modelo->buscarOCrearPropietario($propietario, $telefonoPropietario, $direccionPropietario);
            $modelo->actualizar((int) $id, $datos);
            $this->guardarImagenes($modelo, (int) $id);
            $_SESSION['mensaje_vehiculo'] = 'Vehículo actualizado correctamente.';
        }

        header('Location: ?page=vehiculos');
        exit;
    }

    private function guardarImagenes(Vehiculo $modelo, int $vehiculoId): void
    {
        if (!isset($_FILES['imagenes']) || !is_array($_FILES['imagenes']['name'])) {
            return;
        }

        $archivos = [];
        foreach ($_FILES['imagenes']['name'] as $indice => $nombre) {
            if (!isset($_FILES['imagenes']['error'][$indice]) || (int) $_FILES['imagenes']['error'][$indice] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $archivos[] = $indice;
        }

        if (count($archivos) === 0) {
            return;
        }
        if (count($archivos) > 8) {
            throw new InvalidArgumentException('Solo puedes seleccionar hasta 8 imágenes.');
        }
        if ($modelo->contarImagenes($vehiculoId) + count($archivos) > 8) {
            throw new InvalidArgumentException('Este vehículo ya alcanzó el máximo de 8 imágenes.');
        }

        $directorio = __DIR__ . '/../../public/uploads/vehiculos';
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
        }

        foreach ($archivos as $indice) {
            $error = (int) $_FILES['imagenes']['error'][$indice];
            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Una imagen no se pudo cargar. Inténtalo nuevamente.');
            }

            $temporal = $_FILES['imagenes']['tmp_name'][$indice];
            $nombreOriginal = basename((string) $_FILES['imagenes']['name'][$indice]);
            $tamano = (int) $_FILES['imagenes']['size'][$indice];
            if ($tamano <= 0 || $tamano > 10 * 1024 * 1024) {
                throw new InvalidArgumentException('Cada imagen debe pesar entre 1 byte y 10 MB.');
            }

            $informacion = @getimagesize($temporal);
            if ($informacion === false) {
                throw new InvalidArgumentException('Uno de los archivos no es una imagen válida.');
            }

            $tiposPermitidos = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_GIF => 'gif',
            ];
            $tipo = (int) ($informacion[2] ?? 0);
            if (!isset($tiposPermitidos[$tipo])) {
                throw new InvalidArgumentException('Solo se permiten imágenes JPG, PNG, WEBP o GIF.');
            }

            $extension = $tiposPermitidos[$tipo];
            $nombreSeguro = 'vehiculo_' . $vehiculoId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $destino = $directorio . '/' . $nombreSeguro;
            if (!move_uploaded_file($temporal, $destino)) {
                throw new RuntimeException('No se pudo guardar una de las imágenes.');
            }

            $rutaRelativa = 'uploads/vehiculos/' . $nombreSeguro;
            if (!$modelo->guardarImagen($vehiculoId, $nombreOriginal, $rutaRelativa)) {
                @unlink($destino);
                throw new RuntimeException('No se pudo registrar una de las imágenes.');
            }
        }
    }
}
