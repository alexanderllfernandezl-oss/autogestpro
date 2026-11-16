
USE if0_43456838_autogest_pro;

-- 1. Roles de acceso
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40) NOT NULL UNIQUE,
    descripcion VARCHAR(150)
);

-- 2. Usuarios del sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- 3. Clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    direccion VARCHAR(200),
    correo VARCHAR(100),
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Vehículos
CREATE TABLE vehiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    placa VARCHAR(20) NOT NULL UNIQUE,
    marca VARCHAR(60) NOT NULL,
    modelo VARCHAR(60) NOT NULL,
    anio SMALLINT UNSIGNED,
    color VARCHAR(40),
    diagnostico TEXT NULL,
    estado ENUM('registrado', 'en_revision', 'pendiente_repuesto', 'en_reparacion', 'listo', 'entregado') NOT NULL DEFAULT 'registrado',
    fecha_registro DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE vehiculo_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehiculo_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE
);

-- 5. Mecánicos
CREATE TABLE mecanicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    especialidad VARCHAR(100),
    activo TINYINT(1) NOT NULL DEFAULT 1
);

-- 6. Órdenes de trabajo
CREATE TABLE ordenes_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    vehiculo_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_prevista_retorno DATE NULL,
    diagnostico TEXT,
    observaciones TEXT,
    estado ENUM(
        'registrada',
        'diagnostico',
        'en_reparacion',
        'pendiente_repuesto',
        'lista',
        'entregada',
        'cancelada'
    ) NOT NULL DEFAULT 'registrada',
    costo_final DECIMAL(10,2) NULL,
    precio_confirmado TINYINT(1) NOT NULL DEFAULT 0,
    fecha_entrega DATETIME NULL,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CHECK (costo_final IS NULL OR costo_final >= 0),
    CHECK (precio_confirmado = 0 OR costo_final IS NOT NULL)
);

-- 7. Mecánicos asignados a las órdenes
CREATE TABLE orden_mecanicos (
    orden_id INT NOT NULL,
    mecanico_id INT NOT NULL,
    PRIMARY KEY (orden_id, mecanico_id),
    FOREIGN KEY (orden_id) REFERENCES ordenes_trabajo(id),
    FOREIGN KEY (mecanico_id) REFERENCES mecanicos(id)
);

-- 8. Tareas de reparación
CREATE TABLE tareas_orden (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    costo DECIMAL(10,2) NULL,
    estado ENUM(
        'pendiente',
        'en_proceso',
        'completada'
    ) NOT NULL DEFAULT 'pendiente',
    FOREIGN KEY (orden_id) REFERENCES ordenes_trabajo(id),
    CHECK (costo IS NULL OR costo >= 0)
);

-- Tarifas administrables para los trabajos sugeridos en las órdenes
CREATE TABLE tarifas_trabajos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(255) NOT NULL UNIQUE,
    precio DECIMAL(10,2) UNSIGNED NOT NULL,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 9. Catálogo de repuestos e inventario
CREATE TABLE repuestos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(40) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255),
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    stock_minimo INT UNSIGNED NOT NULL DEFAULT 0,
    precio_referencia DECIMAL(10,2) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CHECK (precio_referencia IS NULL OR precio_referencia >= 0)
);

-- 10. Repuestos utilizados en las órdenes
CREATE TABLE repuestos_orden (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    repuesto_id INT NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (orden_id) REFERENCES ordenes_trabajo(id),
    FOREIGN KEY (repuesto_id) REFERENCES repuestos(id),
    CHECK (cantidad > 0),
    CHECK (precio_unitario >= 0)
);

-- 12. Pagos de las órdenes
CREATE TABLE pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto DECIMAL(10,2) NOT NULL,
    metodo ENUM(
        'efectivo',
        'transferencia',
        'otro'
    ) NOT NULL DEFAULT 'efectivo',
    referencia VARCHAR(100),
    observaciones VARCHAR(255),
    FOREIGN KEY (orden_id) REFERENCES ordenes_trabajo(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CHECK (monto > 0)
);

-- 13. Historial de estados
CREATE TABLE historial_estados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    usuario_id INT NOT NULL,
    estado_anterior VARCHAR(30) NULL,
    estado_nuevo VARCHAR(30) NOT NULL,
    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones VARCHAR(255),
    FOREIGN KEY (orden_id) REFERENCES ordenes_trabajo(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- 14. Movimientos de inventario
CREATE TABLE movimientos_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repuesto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    orden_id INT NULL,
    tipo ENUM(
        'entrada',
        'salida',
        'ajuste'
    ) NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observaciones VARCHAR(255),
    FOREIGN KEY (repuesto_id) REFERENCES repuestos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (orden_id) REFERENCES ordenes_trabajo(id),
    CHECK (cantidad > 0)
);

-- Roles iniciales
INSERT INTO roles (nombre, descripcion) VALUES
('administrador', 'Administración del sistema'),
('recepcionista', 'Gestión de clientes y órdenes'),
('mecanico', 'Seguimiento técnico de reparaciones');
