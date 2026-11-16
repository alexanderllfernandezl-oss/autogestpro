<?php

require_once __DIR__ . '/../../config/database.php';

class Mecanico
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = (new Database())->connect();
    }

    public function listar(string $busqueda = '', bool $incluirInactivos = false): array
    {
        $sql = 'SELECT id, nombre, telefono, especialidad, activo FROM mecanicos';
        $condiciones = [];
        $parametros = [];

        if (!$incluirInactivos) $condiciones[] = 'activo = 1';
        if ($busqueda !== '') {
            $condiciones[] = '(nombre LIKE :busqueda OR telefono LIKE :busqueda OR especialidad LIKE :busqueda)';
            $parametros['busqueda'] = '%' . $busqueda . '%';
        }

        if ($condiciones) $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        $sql .= ' ORDER BY nombre ASC';
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function crear(array $datos): bool
    {
        $stmt = $this->conexion->prepare('INSERT INTO mecanicos (nombre, telefono, especialidad, activo) VALUES (:nombre, :telefono, :especialidad, 1)');
        return $stmt->execute($datos);
    }

    public function actualizar(int $id, array $datos): bool
    {
        $datos['id'] = $id;
        $stmt = $this->conexion->prepare('UPDATE mecanicos SET nombre = :nombre, telefono = :telefono, especialidad = :especialidad WHERE id = :id');
        return $stmt->execute($datos);
    }

    public function cambiarEstado(int $id, bool $activo): bool
    {
        $stmt = $this->conexion->prepare('UPDATE mecanicos SET activo = :activo WHERE id = :id');
        return $stmt->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->conexion->prepare('SELECT COUNT(*) FROM orden_mecanicos WHERE mecanico_id = :id');
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('No se puede eliminar un mecánico con órdenes en su historial. Puedes desactivarlo para conservar el registro.');
        }

        $stmt = $this->conexion->prepare('DELETE FROM mecanicos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException('Mecánico no encontrado.');
        }
    }
}
