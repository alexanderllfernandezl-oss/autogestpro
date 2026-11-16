
<?php

if (PHP_SAPI !== 'cli') {
    exit("Ejecuta este archivo desde la terminal.\n");
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = (new Database())->connect();

    $cuentas = [
        ['nombre' => 'Jhonatan', 'usuario' => 'jhonatan', 'rol' => 'administrador'],
        ['nombre' => 'Juan', 'usuario' => 'juan', 'rol' => 'recepcionista'],
        ['nombre' => 'Rene', 'usuario' => 'rene', 'rol' => 'mecanico'],
    ];

    foreach ($cuentas as $cuenta) {
        $stmt = $db->prepare(
            'SELECT id FROM usuarios WHERE usuario = :usuario LIMIT 1'
        );
        $stmt->execute(['usuario' => $cuenta['usuario']]);

        if ($stmt->fetch()) {
            echo "El usuario {$cuenta['usuario']} ya existe; no se modificó.\n";
            continue;
        }

        $stmt = $db->prepare(
            'SELECT id FROM roles WHERE nombre = :rol LIMIT 1'
        );
        $stmt->execute(['rol' => $cuenta['rol']]);
        $rol = $stmt->fetch();

        if (!$rol) {
            echo "No existe el rol {$cuenta['rol']}. No se creó {$cuenta['usuario']}.\n";
            continue;
        }

        echo "\nCrear cuenta: {$cuenta['nombre']} ({$cuenta['rol']})\n";
        $password = readline('Escribe una contraseña de al menos 10 caracteres: ');

        if (strlen($password) < 10) {
            echo "Contraseña demasiado corta. No se creó esta cuenta.\n";
            continue;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare(
            'INSERT INTO usuarios
             (rol_id, nombre, usuario, password_hash, activo)
             VALUES (:rol_id, :nombre, :usuario, :hash, 1)'
        );

        $stmt->execute([
            'rol_id' => $rol['id'],
            'nombre' => $cuenta['nombre'],
            'usuario' => $cuenta['usuario'],
            'hash' => $hash,
        ]);

        echo "Cuenta {$cuenta['usuario']} creada correctamente.\n";
    }

    echo "\nProceso finalizado.\n";

} catch (PDOException $e) {
    echo "Error de base de datos. Verifica la conexión y las tablas.\n";
}
