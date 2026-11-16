<?php

require_once __DIR__ . '/../../config/database.php';

class WorkshopController
{
    public function module(string $page): void
    {
        if (!in_array($_SESSION['rol'] ?? '', ['administrador', 'recepcionista'], true) && $page !== 'usuarios') {
            header('Location: ?page=home');
            exit;
        }
        if ($page === 'usuarios' && ($_SESSION['rol'] ?? '') !== 'administrador') {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }

        $conexion = (new Database())->connect();
        try {
            $columna = $conexion->query("SHOW COLUMNS FROM mecanicos LIKE 'usuario_id'");
            if (!$columna->fetch()) {
                $conexion->exec('ALTER TABLE mecanicos ADD COLUMN usuario_id INT NULL');
                $conexion->exec('CREATE INDEX idx_mecanicos_usuario_id ON mecanicos(usuario_id)');
            }
        } catch (Throwable $e) {
            // The assignment still works by mechanic name if the migration is unavailable.
        }
        try {
            $mecanicosUsuarios=$conexion->query(
                "SELECT u.id,u.nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE r.nombre='mecanico' AND u.activo=1"
            )->fetchAll();
            foreach ($mecanicosUsuarios as $mecanicoUsuario) {
                $this->sincronizarMecanico($conexion,(int)$mecanicoUsuario['id'],(string)$mecanicoUsuario['nombre']);
            }
        } catch (Throwable $e) {}
        $mensaje = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo 'Solicitud inválida.';
                exit;
            }
            try {
                if ($page === 'usuarios' && ($_POST['accion'] ?? '') === 'crear') {
                    $nombre = trim($_POST['nombre'] ?? '');
                    $usuario = strtolower(trim((string) ($_POST['usuario'] ?? '')));
                    $usuario = preg_replace('/[^a-z0-9._-]/', '', $usuario);
                    $password = $_POST['password'] ?? '';
                    $rolId = filter_input(INPUT_POST, 'rol_id', FILTER_VALIDATE_INT);
                    if ($nombre === '' || $usuario === '' || strlen($password) < 8 || !$rolId) {
                        throw new InvalidArgumentException('Complete los datos. La contraseña debe tener al menos 8 caracteres.');
                    }
                    $rolValido = $conexion->prepare(
                        'SELECT nombre FROM roles WHERE id = ? AND nombre IN (?, ?, ?)'
                    );
                    $rolValido->execute([$rolId, 'administrador', 'recepcionista', 'mecanico']);
                    $nombreRol = $rolValido->fetchColumn();
                    if (!$nombreRol) {
                        throw new InvalidArgumentException('Seleccione un rol válido.');
                    }
                    $existe = $conexion->prepare('SELECT COUNT(*) FROM usuarios WHERE LOWER(usuario) = LOWER(?)');
                    $existe->execute([$usuario]);
                    if ((int) $existe->fetchColumn() > 0) {
                        throw new InvalidArgumentException('Ya existe una cuenta con ese nombre de usuario.');
                    }
                    $stmt = $conexion->prepare('INSERT INTO usuarios (rol_id, nombre, usuario, password_hash) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$rolId, $nombre, $usuario, password_hash($password, PASSWORD_BCRYPT)]);
                    if ($nombreRol === 'mecanico') {
                        $usuarioId=(int)$conexion->lastInsertId();
                        $this->sincronizarMecanico($conexion,$usuarioId,$nombre);
                    }
                    $mensaje = 'Usuario creado correctamente.';
                } elseif ($page === 'usuarios') {
                    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    if (!$id) {
                        throw new InvalidArgumentException('Usuario inválido.');
                    }
                    if ($_POST['accion'] === 'desactivar') {
                        $conexion->prepare('UPDATE usuarios SET activo=0 WHERE id=?')->execute([$id]);
                    } elseif ($_POST['accion'] === 'activar') {
                        $conexion->prepare('UPDATE usuarios SET activo=1 WHERE id=?')->execute([$id]);
                    } elseif ($_POST['accion'] === 'rol') {
                        $rolId = filter_input(INPUT_POST, 'rol_id', FILTER_VALIDATE_INT);
                        if (!$rolId) {
                            throw new InvalidArgumentException('Rol inválido.');
                        }
                        $rolNombre=$conexion->prepare('SELECT nombre FROM roles WHERE id=?');
                        $rolNombre->execute([$rolId]);
                        $nombreRol=(string)$rolNombre->fetchColumn();
                        if (!in_array($nombreRol,['administrador','recepcionista','mecanico'],true)) {
                            throw new InvalidArgumentException('Seleccione un rol válido.');
                        }
                        $conexion->prepare('UPDATE usuarios SET rol_id=? WHERE id=?')->execute([$rolId, $id]);
                        $usuario=$conexion->prepare('SELECT nombre FROM usuarios WHERE id=?');
                        $usuario->execute([$id]);
                        $nombreUsuario=(string)$usuario->fetchColumn();
                        if ($nombreRol==='mecanico') $this->sincronizarMecanico($conexion,$id,$nombreUsuario);
                        else $conexion->prepare('UPDATE mecanicos SET usuario_id=NULL WHERE usuario_id=?')->execute([$id]);
                    } elseif ($_POST['accion'] === 'restablecer') {
                        $password=(string)($_POST['password']??'');
                        if (strlen($password)<8) {
                            throw new InvalidArgumentException('La nueva contraseña debe tener al menos 8 caracteres.');
                        }
                        $conexion->prepare('UPDATE usuarios SET password_hash=? WHERE id=?')
                            ->execute([password_hash($password,PASSWORD_BCRYPT),$id]);
                        $mensaje='Contraseña restablecida correctamente.';
                    }
                    $mensaje = 'Usuario actualizado correctamente.';
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        if ($page === 'usuarios') {
            $usuarios = $conexion->query(
                'SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id=u.rol_id ORDER BY u.nombre'
            )->fetchAll();
            $roles = $conexion->query('SELECT * FROM roles ORDER BY nombre')->fetchAll();
        } else {
            $usuarios = [];
            $roles = [];
        }

        $reportes = [
            'ordenes' => $conexion->query('SELECT COUNT(*) AS total FROM ordenes_trabajo')->fetch(),
            'abiertas' => $conexion->query("SELECT COUNT(*) AS total FROM ordenes_trabajo WHERE estado NOT IN ('entregada','cancelada')")->fetch(),
            'inventario' => $conexion->query('SELECT COUNT(*) AS total, COALESCE(SUM(stock),0) AS unidades FROM repuestos WHERE activo=1')->fetch(),
            'bajos' => $conexion->query('SELECT COUNT(*) AS total FROM repuestos WHERE activo=1 AND stock<=stock_minimo')->fetch(),
            'ingresos' => $conexion->query('SELECT COALESCE(SUM(monto),0) AS total FROM pagos')->fetch(),
        ];

        if ($page === 'reportes') {
            $download = (string) ($_GET['download'] ?? '');
            if ($download === 'pdf') {
                $this->downloadReportPdf($reportes);
            }
            if ($download === 'vehiculos') {
                $this->downloadVehiculosPdf($conexion);
            }
        }

        require __DIR__ . '/../views/workshop.php';
    }

    private function sincronizarMecanico(PDO $conexion, int $usuarioId, string $nombre): void
    {
        $existente=$conexion->prepare('SELECT id FROM mecanicos WHERE nombre=? LIMIT 1');
        $existente->execute([$nombre]);
        $mecanicoId=(int)$existente->fetchColumn();
        if ($mecanicoId>0) {
            $conexion->prepare('UPDATE mecanicos SET usuario_id=? WHERE id=?')->execute([$usuarioId,$mecanicoId]);
        } else {
            $crear=$conexion->prepare('INSERT INTO mecanicos (nombre,telefono,especialidad,activo,usuario_id) VALUES (?,NULL,NULL,1,?)');
            $crear->execute([$nombre,$usuarioId]);
        }
    }

    private function downloadReportPdf(array $reportes): void
    {
        $lines = [
            'AUTOGEST PRO - Taller Automotriz Almaraz',
            'Reporte general',
            'Fecha: ' . date('d/m/Y H:i'),
            '',
            'Ordenes totales: ' . (int) $reportes['ordenes']['total'],
            'Ordenes abiertas: ' . (int) $reportes['abiertas']['total'],
            'Existencias registradas: ' . (int) $reportes['inventario']['unidades'],
            'Ingresos registrados: Bs. ' . number_format((float) $reportes['ingresos']['total'], 2, '.', ''),
            'Productos con stock bajo: ' . (int) $reportes['bajos']['total'],
        ];

        $this->emitPdfDocument($lines, 'reporte-autogest-pro-' . date('Y-m-d') . '.pdf');
    }

    private function downloadVehiculosPdf(PDO $conexion): void
    {
        $stmt = $conexion->query(
            'SELECT v.id, c.nombre AS propietario, v.placa, v.marca, v.modelo, v.anio, v.color, v.diagnostico, v.estado, v.fecha_registro
             FROM vehiculos v
             LEFT JOIN clientes c ON c.id = v.cliente_id
             ORDER BY v.fecha_registro DESC, v.placa ASC'
        );

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $lines = [
            'AUTOGEST PRO - Registro de vehículos',
            'Fecha: ' . date('d/m/Y H:i'),
            '',
        ];

        if ($filas === []) {
            $lines[] = 'No hay vehículos registrados.';
        } else {
            foreach ($filas as $fila) {
                $diagnostico = trim((string) ($fila['diagnostico'] ?? ''));
                $diagnostico = preg_replace('/\s+/', ' ', $diagnostico) ?: 'Sin diagnóstico';

                $lines[] = 'ID: ' . (int) $fila['id'];
                $lines[] = 'Propietario: ' . (string) ($fila['propietario'] ?? '');
                $lines[] = 'Placa: ' . (string) ($fila['placa'] ?? '');
                $lines[] = 'Marca/Modelo: ' . (string) ($fila['marca'] ?? '') . ' ' . (string) ($fila['modelo'] ?? '');
                $lines[] = 'Año: ' . (string) ($fila['anio'] ?? '');
                $lines[] = 'Color: ' . (string) ($fila['color'] ?? '');
                $lines[] = 'Estado: ' . (string) ($fila['estado'] ?? '');
                $lines[] = 'Fecha: ' . (string) ($fila['fecha_registro'] ?? '');
                $lines[] = 'Diagnóstico: ' . $diagnostico;
                $lines[] = '----------------------------------------';
            }
        }

        $this->emitPdfDocument($lines, 'registro-vehiculos-' . date('Y-m-d') . '.pdf');
    }

    private function emitPdfDocument(array $lines, string $filename): void
    {
        $stream = "BT\n/F1 16 Tf\n50 770 Td\n";
        foreach ($lines as $index => $line) {
            if ($index === 1) {
                $stream .= "/F1 12 Tf\n";
            }
            $line = preg_replace('/[^\\x20-\\x7E]/', '', $line ?? '');
            $stream .= '(' . $this->escapePdfText($line) . ") Tj\n";
            $stream .= "0 -24 Td\n";
        }
        $stream .= "ET\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($index = 1; $index <= 5; $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        if (ob_get_level()) {
            ob_clean();
        }
        echo $pdf;
        exit;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
