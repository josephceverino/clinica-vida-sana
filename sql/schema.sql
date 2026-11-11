-- =====================================================================
-- Clínica Vida Sana - Estructura de la base de datos
-- Motor: InnoDB | Charset: utf8mb4 | Compatible con MariaDB/MySQL
-- Orden de importación: primero este archivo, después seed.sql
--
-- Incluye el portal del paciente: cuentas de paciente (usuarios con rol
-- 'paciente' enlazados a su ficha), historia clínica (atenciones) y el
-- registro de intentos de acceso (límite de intentos en la base de datos).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS clinica_vida_sana
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE clinica_vida_sana;

-- Se borran en orden inverso a las dependencias (claves foráneas).
-- (Útil para volver a importar en desarrollo. ¡Cuidado en producción!)
DROP TABLE IF EXISTS intentos_acceso;
DROP TABLE IF EXISTS atenciones;
DROP TABLE IF EXISTS solicitudes_cita;
DROP TABLE IF EXISTS citas;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS pacientes;
DROP TABLE IF EXISTS areas;

-- ---------------------------------------------------------------------
-- areas: departamentos o especialidades de la clínica
-- ---------------------------------------------------------------------
-- publica = 1: se muestra en la web pública y atiende pacientes.
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
-- pacientes: ficha de las personas atendidas (datos ficticios)
-- El documento es único junto con su tipo (un DNI y un CE pueden coincidir
-- en número sin ser la misma persona).
-- fecha_nacimiento es opcional: el registro por la web no la pide.
-- ---------------------------------------------------------------------
CREATE TABLE pacientes (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo_documento   ENUM('DNI', 'CE', 'PASAPORTE') NOT NULL DEFAULT 'DNI',
    numero_documento VARCHAR(12)  NOT NULL,
    nombres          VARCHAR(100) NOT NULL,
    apellidos        VARCHAR(100) NOT NULL,
    correo           VARCHAR(120) NULL,
    fecha_nacimiento DATE         NULL,
    telefono         VARCHAR(20)  NULL,
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pacientes_documento (tipo_documento, numero_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- usuarios: todas las cuentas que inician sesión (personal y pacientes)
-- La contraseña NUNCA se guarda en texto plano: solo el hash.
--
-- Personal (admin, admision, medico): entra con 'usuario'.
-- Paciente: entra con su 'correo' y está enlazado a su ficha (paciente_id).
--
-- activo    = 0: cuenta desactivada, no puede entrar.
-- verificado = 0: cuenta de paciente pendiente; admisión debe comprobar su
--                 identidad antes de que pueda ver su historial.
-- verificado_por / verificado_en: quién de admisión la verificó y cuándo.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(100) NOT NULL,
    usuario       VARCHAR(50)  NULL,
    correo        VARCHAR(120) NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('admin', 'admision', 'medico', 'paciente') NOT NULL,
    area_id       INT UNSIGNED NULL,
    paciente_id   INT UNSIGNED NULL,
    activo        TINYINT(1)   NOT NULL DEFAULT 1,
    verificado    TINYINT(1)   NOT NULL DEFAULT 1,
    verificado_por INT UNSIGNED NULL,
    verificado_en  DATETIME     NULL,
    creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_usuario (usuario),
    UNIQUE KEY uq_usuarios_correo (correo),
    UNIQUE KEY uq_usuarios_paciente (paciente_id),
    CONSTRAINT fk_usuarios_area
        FOREIGN KEY (area_id) REFERENCES areas (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    -- RESTRICT en ambos: el id de un paciente nunca cambia, y MariaDB no
    -- permite usar en un CHECK una columna con ON UPDATE CASCADE.
    CONSTRAINT fk_usuarios_paciente
        FOREIGN KEY (paciente_id) REFERENCES pacientes (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    -- Quién verificó la cuenta (si esa persona se borra, queda el dato vacío).
    CONSTRAINT fk_usuarios_verificador
        FOREIGN KEY (verificado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE RESTRICT,
    -- Una cuenta de paciente siempre tiene ficha; el personal nunca.
    CONSTRAINT ck_usuarios_paciente
        CHECK ((rol = 'paciente' AND paciente_id IS NOT NULL)
            OR (rol <> 'paciente' AND paciente_id IS NULL)),
    -- El personal entra con 'usuario'; el paciente, con 'correo'.
    CONSTRAINT ck_usuarios_acceso
        CHECK ((rol = 'paciente' AND correo IS NOT NULL)
            OR (rol <> 'paciente' AND usuario IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- citas: atenciones programadas
-- ---------------------------------------------------------------------
CREATE TABLE citas (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    paciente_id    INT UNSIGNED NOT NULL,
    area_id        INT UNSIGNED NOT NULL,
    medico_id      INT UNSIGNED NULL,
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
        ON DELETE SET NULL ON UPDATE CASCADE,
    -- Médico asignado a la cita (puede quedar sin asignar).
    CONSTRAINT fk_citas_medico
        FOREIGN KEY (medico_id) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- solicitudes_cita: citas pedidas por los pacientes (admisión confirma)
-- paciente_id enlaza la solicitud con la cuenta que la hizo. Queda NULL
-- mientras exista el formulario público antiguo (se retira en el portal).
-- ---------------------------------------------------------------------
CREATE TABLE solicitudes_cita (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    paciente_id     INT UNSIGNED NULL,
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
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_solicitudes_paciente
        FOREIGN KEY (paciente_id) REFERENCES pacientes (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- atenciones: historia clínica (lo que el médico registra al atender)
-- Una atención por cita. Solo la ve el paciente dueño (con cuenta
-- verificada) y el personal autorizado. Los datos son ficticios.
-- ---------------------------------------------------------------------
CREATE TABLE atenciones (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    cita_id     INT UNSIGNED  NOT NULL,
    paciente_id INT UNSIGNED  NOT NULL,
    medico_id   INT UNSIGNED  NOT NULL,
    motivo      VARCHAR(255)  NULL,
    diagnostico VARCHAR(500)  NOT NULL,
    tratamiento VARCHAR(500)  NULL,
    notas       VARCHAR(1000) NULL,
    creado_en   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_atenciones_cita (cita_id),
    KEY idx_atenciones_paciente (paciente_id, creado_en),
    CONSTRAINT fk_atenciones_cita
        FOREIGN KEY (cita_id) REFERENCES citas (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_atenciones_paciente
        FOREIGN KEY (paciente_id) REFERENCES pacientes (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_atenciones_medico
        FOREIGN KEY (medico_id) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- intentos_acceso: registro de intentos de login y de registro
-- Sirve para limitar los intentos por IP y por cuenta. Está en la base de
-- datos (no en la sesión) para que borrar la cookie no salte el límite.
-- Los registros viejos se pueden borrar sin problema.
-- ---------------------------------------------------------------------
CREATE TABLE intentos_acceso (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo      ENUM('login', 'registro') NOT NULL,
    ip        VARCHAR(45)  NOT NULL,
    clave     VARCHAR(120) NOT NULL,
    creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intentos_ip (tipo, ip, creado_en),
    KEY idx_intentos_clave (tipo, clave, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
