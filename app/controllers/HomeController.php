
<?php

require_once __DIR__ . '/../../config/database.php';

class HomeController
{
    public function index(): void
    {
        $errorResumen = null;
        $resumen = [
            'clientes' => 0,
            'vehiculos' => 0,
            'ordenes_mes' => 0,
            'ordenes_abiertas' => 0,
            'ordenes_listas' => 0,
            'ingresos_mes' => 0,
            'repuestos_bajos' => 0
        ];

        try {
            $conexion = (new Database())->connect();
            if ($_SESSION['rol'] === 'mecanico') {
                $usuarioId=(int)$_SESSION['usuario_id'];
                $nombre=(string)$_SESSION['nombre'];
                $filtroAsignacion=' JOIN orden_mecanicos om ON om.orden_id=o.id JOIN mecanicos m ON m.id=om.mecanico_id WHERE (m.usuario_id=:usuario_id OR (m.usuario_id IS NULL AND m.nombre=:nombre))';
                $consultas = [
                    'clientes' => 'SELECT COUNT(DISTINCT v.cliente_id) FROM vehiculos v JOIN ordenes_trabajo o ON o.vehiculo_id=v.id'.$filtroAsignacion,
                    'vehiculos' => 'SELECT COUNT(DISTINCT o.vehiculo_id) FROM ordenes_trabajo o'.$filtroAsignacion,
                    'ordenes_mes' => 'SELECT COUNT(DISTINCT o.id) FROM ordenes_trabajo o'.$filtroAsignacion." AND o.fecha_ingreso >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')",
                    'ordenes_abiertas' => 'SELECT COUNT(DISTINCT o.id) FROM ordenes_trabajo o'.$filtroAsignacion." AND o.estado NOT IN ('entregada', 'cancelada')",
                    'ordenes_listas' => 'SELECT COUNT(DISTINCT o.id) FROM ordenes_trabajo o'.$filtroAsignacion." AND o.estado IN ('lista', 'listo')",
                    'ingresos_mes' => 'SELECT 0',
                    'repuestos_bajos' => 'SELECT 0'
                ];
            } else {
                $consultas = [
                    'clientes' => 'SELECT COUNT(*) FROM clientes WHERE activo = 1',
                    'vehiculos' => 'SELECT COUNT(*) FROM vehiculos',
                    'ordenes_mes' => "SELECT COUNT(*) FROM ordenes_trabajo WHERE fecha_ingreso >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')",
                    'ordenes_abiertas' => "SELECT COUNT(*) FROM ordenes_trabajo WHERE estado NOT IN ('entregada', 'cancelada')",
                    'ordenes_listas' => "SELECT COUNT(*) FROM ordenes_trabajo WHERE estado IN ('lista', 'listo')",
                    'ingresos_mes' => 'SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE fecha_pago >= :inicio AND fecha_pago < :fin',
                    'repuestos_bajos' => 'SELECT COUNT(*) FROM repuestos WHERE activo = 1 AND stock <= stock_minimo'
                ];
            }

            foreach ($consultas as $campo => $sql) {
                if ($_SESSION['rol'] === 'mecanico') {
                    $stmt=$conexion->prepare($sql);
                    $stmt->execute(['usuario_id'=>(int)$_SESSION['usuario_id'],'nombre'=>(string)$_SESSION['nombre']]);
                    $resumen[$campo]=(float)$stmt->fetchColumn();
                } elseif ($campo === 'ingresos_mes') {
                    $inicioMes = new DateTimeImmutable('first day of this month midnight');
                    $stmt = $conexion->prepare($sql);
                    $stmt->execute([
                        'inicio' => $inicioMes->format('Y-m-d H:i:s'),
                        'fin' => $inicioMes->modify('first day of next month')->format('Y-m-d H:i:s'),
                    ]);
                    $resumen[$campo] = (float) $stmt->fetchColumn();
                } else {
                    $resumen[$campo]=(float)$conexion->query($sql)->fetchColumn();
                }
            }
        } catch (Throwable $e) {
            error_log('No se pudo cargar el resumen del taller: ' . $e->getMessage());
            $errorResumen = 'No se pudo actualizar el resumen. Puedes seguir usando los módulos del sistema.';
        }

        require __DIR__ . '/../views/home.php';
    }
}
