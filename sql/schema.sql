-- =====================================================================
-- Clínica Vida Sana - Estructura de la base de datos
-- BORRADOR (por Claude, para que Alexander lo revise y ajuste).
-- Motor: InnoDB | Charset: utf8mb4 | Compatible con MariaDB/MySQL
-- Orden de importación: primero este archivo, después seed.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS clinica_vida_sana
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE clinica_vida_sana;

-- Se borran en orden inverso para respetar las claves foráneas.
-- (Útil para volver a importar en desarrollo. ¡Cuidado en producción!)
DROP TABLE IF EXISTS solicitudes_cita;
DROP TABLE IF EXISTS citas;
DROP TABLE IF EXISTS pacientes;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS areas;

-- ---------------------------------------------------------------------
-- areas: departamentos o especialidades de la clínica
-- ---------------------------------------------------------------------
-- publica = 1: se muestra en la web pública (Servicios, formulario de cita).
-- publica = 0: área interna (ej. Admisión, Sistemas).
CREATE TABLE areas (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(80)  NOT NULL,
    descripcion VARCHAR(255) NULL,
    publica     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_areas_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- usuarios: personal que entra al panel (con login)
-- La contraseña NUNCA se guarda en texto plano: solo el hash.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(100) NOT NULL,
    usuario       VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('admin', 'admision', 'medico') NOT NULL,
    area_id       INT UNSIGNED NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_usuario (usuario),
    CONSTRAINT fk_usuarios_area
        FOREIGN KEY (area_id) REFERENCES areas (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- pacientes: personas atendidas (todos los datos son ficticios)
-- ---------------------------------------------------------------------
CREATE TABLE pacientes (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dni              CHAR(8)      NOT NULL,
    nombres          VARCHAR(100) NOT NULL,
    apellidos        VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE         NOT NULL,
    telefono         VARCHAR(20)  NULL,
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pacientes_dni (dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- citas: atenciones programadas
-- ---------------------------------------------------------------------
CREATE TABLE citas (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    paciente_id    INT UNSIGNED NOT NULL,
    area_id        INT UNSIGNED NOT NULL,
    fecha          DATE         NOT NULL,
    hora           TIME         NOT NULL,
    motivo         VARCHAR(255) NULL,
    estado         ENUM('pendiente', 'confirmada', 'atendida', 'cancelada')
                   NOT NULL DEFAULT 'pendiente',
    registrado_por INT UNSIGNED NULL,
    creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_citas_fecha (fecha, hora),
    CONSTRAINT fk_citas_paciente
        FOREIGN KEY (paciente_id) REFERENCES pacientes (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_citas_area
        FOREIGN KEY (area_id) REFERENCES areas (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_citas_registrado_por
        FOREIGN KEY (registrado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- solicitudes_cita: lo que llega desde el formulario público (sin login)
-- ---------------------------------------------------------------------
CREATE TABLE solicitudes_cita (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(100) NOT NULL,
    dni             CHAR(8)      NOT NULL,
    telefono        VARCHAR(20)  NOT NULL,
    area_id         INT UNSIGNED NOT NULL,
    fecha_preferida DATE         NULL,
    mensaje         VARCHAR(500) NULL,
    estado          ENUM('nueva', 'atendida', 'descartada')
                    NOT NULL DEFAULT 'nueva',
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_solicitudes_estado (estado),
    CONSTRAINT fk_solicitudes_area
        FOREIGN KEY (area_id) REFERENCES areas (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Médico asignado a cada cita.
ALTER TABLE citas
    ADD COLUMN medico_id INT UNSIGNED NULL AFTER area_id,
    ADD CONSTRAINT fk_citas_medico
        FOREIGN KEY (medico_id)
        REFERENCES usuarios (id)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
