<?php

require_once __DIR__ . '/../../config/database.php';

class Cliente
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = (new Database())->connect();
    }

    public function listar(string $busqueda = '', bool $incluirInactivos = false): array
    {
        $sql = 'SELECT id, nombre, telefono, direccion, correo, activo, creado_en FROM clientes';
        $condiciones = [];
        $parametros = [];

        if (!$incluirInactivos) {
            $condiciones[] = 'activo = 1';
        }

        if ($busqueda !== '') {
            $condiciones[] = '(nombre LIKE :busqueda OR telefono LIKE :busqueda OR correo LIKE :busqueda OR direccion LIKE :busqueda)';
            $parametros['busqueda'] = '%' . $busqueda . '%';
        }

        if ($condiciones) {
            $sql .= ' WHERE ' . implode(' AND ', $condiciones);
        }

        $sql .= ' ORDER BY nombre ASC';
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM clientes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $cliente = $stmt->fetch();
        return $cliente ?: null;
    }

    public function crear(array $datos): bool
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO clientes (nombre, telefono, direccion, correo, activo) VALUES (:nombre, :telefono, :direccion, :correo, 1)'
        );
        return $stmt->execute($datos);
    }

    public function actualizar(int $id, array $datos): bool
    {
        $datos['id'] = $id;
        $stmt = $this->conexion->prepare(
            'UPDATE clientes SET nombre = :nombre, telefono = :telefono, direccion = :direccion, correo = :correo WHERE id = :id'
        );
        return $stmt->execute($datos);
    }

    public function cambiarEstado(int $id, bool $activo): bool
    {
        $stmt = $this->conexion->prepare('UPDATE clientes SET activo = :activo WHERE id = :id');
        return $stmt->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }
}
