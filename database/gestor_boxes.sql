CREATE DATABASE IF NOT EXISTS gestor_boxes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestor_boxes;

CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    identificador_institucional VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(120) NOT NULL,
    correo_institucional VARCHAR(120) NOT NULL UNIQUE,
    clave_hash VARCHAR(255) NOT NULL,
    rol ENUM('estudiante', 'bibliotecario') NOT NULL DEFAULT 'estudiante',
    estado ENUM('habilitado', 'bloqueado') NOT NULL DEFAULT 'habilitado'
) ENGINE=InnoDB;

CREATE TABLE box (
    id_box INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    ubicacion VARCHAR(100) NOT NULL,
    estado ENUM('habilitado', 'fuera_de_servicio') NOT NULL DEFAULT 'habilitado'
) ENGINE=InnoDB;

CREATE TABLE reserva (
    id_reserva INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_box INT NOT NULL,
    fecha_hora_inicio DATETIME NOT NULL,
    fecha_hora_fin DATETIME NOT NULL,
    estado ENUM('pendiente', 'confirmada', 'cancelada', 'inasistencia', 'finalizada') NOT NULL DEFAULT 'pendiente',
    fecha_confirmacion DATETIME NULL,
    fecha_creacion DATETIME NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    FOREIGN KEY (id_box) REFERENCES box(id_box),
    INDEX idx_box_fechas (id_box, fecha_hora_inicio, fecha_hora_fin)
) ENGINE=InnoDB;

CREATE TABLE sancion (
    id_sancion INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_termino DATETIME NOT NULL,
    estado ENUM('vigente', 'cumplida', 'anulada') NOT NULL DEFAULT 'vigente',
    FOREIGN KEY (id_reserva) REFERENCES reserva(id_reserva)
) ENGINE=InnoDB;

CREATE TABLE historial_acciones (
    id_historial INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_reserva INT NULL,
    accion VARCHAR(100) NOT NULL,
    fecha_hora DATETIME NOT NULL,
    detalle TEXT NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario),
    FOREIGN KEY (id_reserva) REFERENCES reserva(id_reserva) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE sistema_reloj (
    id INT PRIMARY KEY DEFAULT 1,
    fecha_hora_simulada DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE correo_simulado (
    id_correo INT AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT NULL,
    destinatario VARCHAR(120) NOT NULL,
    asunto VARCHAR(200) NOT NULL,
    cuerpo TEXT NOT NULL,
    fecha_envio DATETIME NOT NULL,
    estado_envio ENUM('enviado', 'fallido_reintento') NOT NULL DEFAULT 'enviado',
    leido TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO sistema_reloj (id, fecha_hora_simulada) VALUES (1, '2026-11-23 09:00:00');

INSERT INTO box (codigo, ubicacion, estado) VALUES
('BOX-01', 'Piso 1 - Ala Norte (Capacidad 4)', 'habilitado'),
('BOX-02', 'Piso 1 - Ala Norte (Capacidad 4)', 'habilitado'),
('BOX-03', 'Piso 1 - Ala Norte (Capacidad 6)', 'habilitado'),
('BOX-04', 'Piso 1 - Ala Sur (Capacidad 4)', 'habilitado'),
('BOX-05', 'Piso 1 - Ala Sur (Capacidad 4)', 'habilitado'),
('BOX-06', 'Piso 2 - Zona Silenciosa (Capacidad 2)', 'habilitado'),
('BOX-07', 'Piso 2 - Zona Silenciosa (Capacidad 2)', 'habilitado'),
('BOX-08', 'Piso 2 - Zona Colaborativa (Capacidad 6)', 'habilitado'),
('BOX-09', 'Piso 2 - Zona Colaborativa (Capacidad 6)', 'habilitado');

INSERT INTO usuario (identificador_institucional, nombre, correo_institucional, clave_hash, rol, estado) VALUES
('20.456.789-1', 'María Jara Ibarra', 'm.jara@alumnos.santotomas.cl', '2f391a57f855eabc7da288f6a86d4226a280dd7ab5282d306996c340ccf1e9d7', 'estudiante', 'habilitado'),
('21.123.456-7', 'Leandro Rojas Pérez', 'l.rojas@alumnos.santotomas.cl', '2f391a57f855eabc7da288f6a86d4226a280dd7ab5282d306996c340ccf1e9d7', 'estudiante', 'habilitado'),
('20.987.654-3', 'Sebastián Silva Soto', 's.silva@alumnos.santotomas.cl', '2f391a57f855eabc7da288f6a86d4226a280dd7ab5282d306996c340ccf1e9d7', 'estudiante', 'habilitado'),
('15.333.222-K', 'Rosa Lorca Andrade', 'r.lorca@santotomas.cl', '2f391a57f855eabc7da288f6a86d4226a280dd7ab5282d306996c340ccf1e9d7', 'bibliotecario', 'habilitado');
