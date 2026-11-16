<?php

require_once __DIR__ . '/../../config/database.php';

class Repuesto
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = (new Database())->connect();
    }

    public function listar(string $busqueda = '', bool $soloBajos = false): array
    {
        $sql = 'SELECT * FROM repuestos WHERE activo = 1';
        $parametros = [];
        if ($busqueda !== '') {
            $sql .= ' AND (codigo LIKE :busqueda OR nombre LIKE :busqueda OR descripcion LIKE :busqueda)';
            $parametros['busqueda'] = '%' . $busqueda . '%';
        }
        if ($soloBajos) $sql .= ' AND stock <= stock_minimo';
        $sql .= ' ORDER BY nombre ASC';
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM repuestos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    public function codigoExiste(string $codigo, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM repuestos WHERE codigo = :codigo';
        $parametros = ['codigo' => strtoupper(trim($codigo))];
        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros['id'] = $exceptoId;
        }
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($parametros);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): bool
    {
        $stmt = $this->conexion->prepare('INSERT INTO repuestos (codigo, nombre, descripcion, stock, stock_minimo, precio_referencia, activo) VALUES (:codigo, :nombre, :descripcion, :stock, :stock_minimo, :precio_referencia, 1)');
        return $stmt->execute($datos);
    }

    public function actualizar(int $id, array $datos): bool
    {
        $datos['id'] = $id;
        $stmt = $this->conexion->prepare('UPDATE repuestos SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion, stock_minimo = :stock_minimo, precio_referencia = :precio_referencia WHERE id = :id');
        return $stmt->execute([
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'stock_minimo' => $datos['stock_minimo'],
            'precio_referencia' => $datos['precio_referencia'],
            'id' => $datos['id'],
        ]);
    }

    public function cambiarEstado(int $id, bool $activo): bool
    {
        $stmt = $this->conexion->prepare('UPDATE repuestos SET activo = :activo WHERE id = :id');
        return $stmt->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    public function registrarMovimiento(int $repuestoId, int $usuarioId, string $tipo, int $cantidad, string $observacion): void
    {
        if (
            !in_array($tipo, ['entrada', 'salida', 'ajuste'], true)
            || $cantidad < 0
            || ($tipo !== 'ajuste' && $cantidad === 0)
        ) {
            throw new InvalidArgumentException('Movimiento de inventario inválido.');
        }

        $this->conexion->beginTransaction();
        try {
            $stmt = $this->conexion->prepare('SELECT stock FROM repuestos WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $repuestoId]);
            $repuesto = $stmt->fetch();
            if (!$repuesto) throw new InvalidArgumentException('Repuesto no encontrado.');

            $stockActual = (int) $repuesto['stock'];
            if ($tipo === 'entrada') $nuevoStock = $stockActual + $cantidad;
            elseif ($tipo === 'salida') $nuevoStock = $stockActual - $cantidad;
            else $nuevoStock = $cantidad;

            if ($nuevoStock < 0) throw new InvalidArgumentException('No hay existencias suficientes para realizar la salida.');
            if ($tipo === 'ajuste' && $nuevoStock === $stockActual) {
                throw new InvalidArgumentException('El stock ya tiene esa cantidad; no se registró ningún movimiento.');
            }

            $stmt = $this->conexion->prepare('UPDATE repuestos SET stock = :stock WHERE id = :id');
            $stmt->execute(['stock' => $nuevoStock, 'id' => $repuestoId]);

            $stmt = $this->conexion->prepare('INSERT INTO movimientos_inventario (repuesto_id, usuario_id, tipo, cantidad, observaciones) VALUES (:repuesto_id, :usuario_id, :tipo, :cantidad, :observaciones)');
            $stmt->execute([
                'repuesto_id' => $repuestoId,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'cantidad' => $tipo === 'ajuste' ? abs($nuevoStock - $stockActual) : $cantidad,
                'observaciones' => mb_substr($observacion, 0, 255, 'UTF-8'),
            ]);
            $this->conexion->commit();
        } catch (Throwable $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    public function movimientos(int $repuestoId): array
    {
        $stmt = $this->conexion->prepare('SELECT m.*, u.nombre AS usuario FROM movimientos_inventario m INNER JOIN usuarios u ON u.id = m.usuario_id WHERE m.repuesto_id = :id ORDER BY m.fecha_movimiento DESC LIMIT 30');
        $stmt->execute(['id' => $repuestoId]);
        return $stmt->fetchAll();
    }
}
