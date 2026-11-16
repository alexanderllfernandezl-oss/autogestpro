
<?php

require_once __DIR__ . '/../../config/database.php';

class Usuario
{
    private PDO $conexion;

    public function __construct()
    {
        $database = new Database();
        $this->conexion = $database->connect();
    }

    public function buscarPorUsuario(string $nombreUsuario): ?array
    {
        $sql = "SELECT
                    u.id,
                    u.nombre,
                    u.usuario,
                    u.password_hash,
                    u.activo,
                    r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                WHERE u.usuario = :usuario
                LIMIT 1";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute(['usuario' => $nombreUsuario]);

        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }
}
