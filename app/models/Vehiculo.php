<?php

require_once __DIR__ . '/../../config/database.php';

class Vehiculo
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = (new Database())->connect();
    }

    public function listar(string $busqueda = '', bool $incluirPrecios = false): array
    {
        $precioOrdenes = $incluirPrecios
            ? ', (SELECT COALESCE(SUM(o.costo_final), 0) FROM ordenes_trabajo o WHERE o.vehiculo_id = v.id) AS total_ordenes'
            : '';
        $sql = 'SELECT v.*, c.nombre AS propietario' . $precioOrdenes . '
                FROM vehiculos v
                INNER JOIN clientes c ON c.id = v.cliente_id';
        $parametros = [];

        if ($busqueda !== '') {
            $sql .= ' WHERE (v.placa LIKE :busqueda OR v.marca LIKE :busqueda OR v.modelo LIKE :busqueda OR c.nombre LIKE :busqueda)';
            $parametros['busqueda'] = '%' . $busqueda . '%';
        }

        $sql .= ' ORDER BY v.placa ASC';
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($parametros);
        $vehiculos = $stmt->fetchAll();
        foreach ($vehiculos as &$vehiculo) {
            $stmtImagenes = $this->conexion->prepare(
                'SELECT id, nombre_archivo, ruta FROM vehiculo_imagenes
                 WHERE vehiculo_id = :vehiculo_id ORDER BY id ASC'
            );
            $stmtImagenes->execute(['vehiculo_id' => $vehiculo['id']]);
            $vehiculo['imagenes'] = $stmtImagenes->fetchAll();
        }
        unset($vehiculo);
        return $vehiculos;
    }

    public function listarPropietarios(): array
    {
        return $this->conexion->query(
            'SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre ASC'
        )->fetchAll();
    }

    public function buscarOCrearPropietario(string $nombre, string $telefono, string $direccion): int
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre del propietario es obligatorio.');
        }

        $stmt = $this->conexion->prepare(
            'SELECT id FROM clientes WHERE activo = 1 AND LOWER(nombre) = LOWER(:nombre) ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute(['nombre' => $nombre]);
        $cliente = $stmt->fetch();

        if ($cliente) {
            return (int) $cliente['id'];
        }

        if (trim($telefono) === '' || trim($direccion) === '') {
            throw new InvalidArgumentException('Para registrar un propietario nuevo, ingresa su teléfono y dirección.');
        }

        $stmt = $this->conexion->prepare(
            'INSERT INTO clientes (nombre, telefono, direccion, activo) VALUES (:nombre, :telefono, :direccion, 1)'
        );
        $stmt->execute([
            'nombre' => mb_substr($nombre, 0, 100, 'UTF-8'),
            'telefono' => mb_substr(trim($telefono), 0, 20, 'UTF-8'),
            'direccion' => mb_substr(trim($direccion), 0, 200, 'UTF-8'),
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT v.*, c.nombre AS propietario FROM vehiculos v INNER JOIN clientes c ON c.id = v.cliente_id WHERE v.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $vehiculo = $stmt->fetch();
        return $vehiculo ?: null;
    }

    public function placaExiste(string $placa, ?int $exceptoId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM vehiculos WHERE placa = :placa';
        $parametros = ['placa' => strtoupper(trim($placa))];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> :id';
            $parametros['id'] = $exceptoId;
        }

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($parametros);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function crear(array $datos): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO vehiculos (cliente_id, placa, marca, modelo, anio, color, diagnostico, estado, fecha_registro)
             VALUES (:cliente_id, :placa, :marca, :modelo, :anio, :color, :diagnostico, :estado, :fecha_registro)'
        );
        $stmt->execute($datos);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $datos): bool
    {
        $datos['id'] = $id;
        $stmt = $this->conexion->prepare(
            'UPDATE vehiculos SET cliente_id = :cliente_id, placa = :placa, marca = :marca,
             modelo = :modelo, anio = :anio, color = :color, diagnostico = :diagnostico,
             estado = :estado WHERE id = :id'
        );
        unset($datos['fecha_registro']);
        return $stmt->execute($datos);
    }

    public function guardarImagen(int $vehiculoId, string $nombreArchivo, string $ruta): bool
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO vehiculo_imagenes (vehiculo_id, nombre_archivo, ruta)
             VALUES (:vehiculo_id, :nombre_archivo, :ruta)'
        );
        return $stmt->execute([
            'vehiculo_id' => $vehiculoId,
            'nombre_archivo' => mb_substr($nombreArchivo, 0, 255, 'UTF-8'),
            'ruta' => mb_substr($ruta, 0, 255, 'UTF-8'),
        ]);
    }

    public function contarImagenes(int $vehiculoId): int
    {
        $stmt = $this->conexion->prepare(
            'SELECT COUNT(*) FROM vehiculo_imagenes WHERE vehiculo_id = :vehiculo_id'
        );
        $stmt->execute(['vehiculo_id' => $vehiculoId]);
        return (int) $stmt->fetchColumn();
    }

    public function imagenes(int $vehiculoId): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id, nombre_archivo, ruta FROM vehiculo_imagenes
             WHERE vehiculo_id = :vehiculo_id ORDER BY id ASC'
        );
        $stmt->execute(['vehiculo_id' => $vehiculoId]);
        return $stmt->fetchAll();
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->conexion->prepare('DELETE FROM vehiculos WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
