-- ============================================================
--  PRADO BARBER CO. — Base de datos MySQL
--  Ejecutar este script una sola vez para crear la BD
-- ============================================================

CREATE DATABASE IF NOT EXISTS pradobarber
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pradobarber;

-- ── Tabla de servicios ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS servicios (
    id          VARCHAR(30)    NOT NULL PRIMARY KEY,
    nombre      VARCHAR(100)   NOT NULL,
    duracion    VARCHAR(20)    NOT NULL,
    precio      DECIMAL(6,2)   NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO servicios VALUES
    ('corte',       'Corte Clásico',    '30 min', 18.00),
    ('barba',       'Arreglo de Barba', '20 min', 12.00),
    ('corte-barba', 'Corte + Barba',    '50 min', 26.00),
    ('degradado',   'Degradado',        '40 min', 22.00),
    ('afeitado',    'Afeitado Navaja',  '30 min', 20.00),
    ('premium',     'Sesión Premium',   '75 min', 45.00)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ── Tabla de barberos ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS barberos (
    id          VARCHAR(20)    NOT NULL PRIMARY KEY,
    nombre      VARCHAR(100)   NOT NULL,
    especialidad VARCHAR(150)  NOT NULL,
    iniciales   VARCHAR(5)     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO barberos VALUES
    ('endika', 'Endika Prado', 'Fundador · Especialista en degradados', 'EP'),
    ('marcos', 'Marcos Vila',  'Barba & Navaja',                        'MV'),
    ('alex',   'Alex Ramos',   'Corte clásico & Fade',                  'AR')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ── Tabla de reservas ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservas (
    id              INT UNSIGNED   NOT NULL AUTO_INCREMENT PRIMARY KEY,
    servicio_id     VARCHAR(30)    NOT NULL,
    barbero_id      VARCHAR(20)    NOT NULL,
    fecha           DATE           NOT NULL,
    hora            TIME           NOT NULL,
    cliente_nombre  VARCHAR(100)   NOT NULL,
    cliente_telefono VARCHAR(30)   NOT NULL,
    cliente_email   VARCHAR(150)   NOT NULL,
    notas           TEXT,
    estado          ENUM('pendiente','aceptada','denegada') NOT NULL DEFAULT 'pendiente',
    token           VARCHAR(64)    NOT NULL DEFAULT '',
    creado_en       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (servicio_id) REFERENCES servicios(id),
    FOREIGN KEY (barbero_id)  REFERENCES barberos(id),

    UNIQUE KEY uq_barbero_fecha_hora (barbero_id, fecha, hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Migración: añadir columnas a tabla existente (ignorar si ya existen) ──
ALTER TABLE reservas
    ADD COLUMN IF NOT EXISTS estado ENUM('pendiente','aceptada','denegada') NOT NULL DEFAULT 'pendiente' AFTER notas,
    ADD COLUMN IF NOT EXISTS token  VARCHAR(64) NOT NULL DEFAULT '' AFTER estado;

-- ============================================================
--  MIGRACIÓN: añadir estado 'cancelada' a la tabla reservas
--  Ejecutar UNA sola vez en la base de datos
-- ============================================================

ALTER TABLE reservas
    MODIFY COLUMN estado
        ENUM('pendiente','aceptada','denegada','cancelada')
        NOT NULL DEFAULT 'pendiente';

-- ── Vista: próximas disponibilidades ──────────────────────
CREATE OR REPLACE VIEW proxima_disponibilidad AS
SELECT
    b.nombre           AS barbero,
    b.iniciales        AS iniciales,
    r.fecha,
    r.hora,
    s.nombre           AS servicio
FROM reservas r
JOIN barberos b  ON b.id = r.barbero_id
JOIN servicios s ON s.id = r.servicio_id
WHERE r.fecha >= CURDATE()
ORDER BY r.fecha, r.hora
LIMIT 10;

-- Añadir columnas de negociación
ALTER TABLE reservas
    ADD COLUMN IF NOT EXISTS motivo_cancelacion   TEXT NULL,
    ADD COLUMN IF NOT EXISTS propuesta_fecha      DATE NULL,
    ADD COLUMN IF NOT EXISTS propuesta_hora       TIME NULL,
    ADD COLUMN IF NOT EXISTS propuesta_token      VARCHAR(64) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS ronda_negociacion    TINYINT UNSIGNED NOT NULL DEFAULT 0;

-- Ampliar el ENUM de estado
ALTER TABLE reservas MODIFY COLUMN estado
    ENUM('pendiente','aceptada','denegada','cancelada',
        'reprogramar_barbero','reprogramar_cliente')
    NOT NULL DEFAULT 'pendiente';