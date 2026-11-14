-- =====================================================================
-- Clínica Vida Sana - Migración de noviembre 2026
--
-- Actualiza una base YA EXISTENTE a la estructura actual de schema.sql
-- SIN borrar sus datos. Sirve para el servidor Fedora cuando la base se
-- importó con una versión anterior del código.
--
-- Uso (en el servidor):
--     sudo mariadb < /var/www/clinica/sql/migracion-2026-11.sql
--
-- Se puede ejecutar varias veces: cada paso comprueba si ya está hecho
-- (IF NOT EXISTS / IF EXISTS) y, si lo está, no hace nada.
--
-- Cubre bases creadas desde la versión "portal del paciente" (cuando
-- usuarios ya tenía 'correo', 'paciente_id' y 'verificado'). Si la base es
-- más vieja aún (pacientes con columna 'dni'), no se migra: se vuelve a
-- importar schema.sql y seed.sql (ver docs/despliegue.md).
--
-- Necesita MariaDB 10.5 o más nuevo (Fedora trae una versión más nueva).
-- =====================================================================

USE clinica_vida_sana;

-- ---------------------------------------------------------------------
-- 1. usuarios: quién verificó la cuenta y cuándo aceptó la privacidad
-- ---------------------------------------------------------------------
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS verificado_por INT UNSIGNED NULL AFTER verificado,
    ADD COLUMN IF NOT EXISTS verificado_en DATETIME NULL AFTER verificado_por,
    ADD COLUMN IF NOT EXISTS acepto_privacidad_en DATETIME NULL AFTER verificado_en;

ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuarios_verificador
        FOREIGN KEY IF NOT EXISTS (verificado_por) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE RESTRICT;

-- ---------------------------------------------------------------------
-- 2. citas: nuevo estado 'no_asistio' (el paciente no llegó)
-- ---------------------------------------------------------------------
-- MODIFY deja la columna igual que en schema.sql; repetirlo no cambia nada.
ALTER TABLE citas
    MODIFY estado ENUM('pendiente', 'confirmada', 'atendida', 'cancelada', 'no_asistio')
        NOT NULL DEFAULT 'pendiente';

-- ---------------------------------------------------------------------
-- 3. solicitudes_cita: ahora siempre vienen de una cuenta de paciente
-- ---------------------------------------------------------------------
-- Nombre, DNI y teléfono ya no se guardan aquí: se leen de la ficha.
ALTER TABLE solicitudes_cita
    DROP COLUMN IF EXISTS nombre,
    DROP COLUMN IF EXISTS dni,
    DROP COLUMN IF EXISTS telefono;

-- Una solicitud sin paciente no se puede atender (no se sabe de quién es).
-- Eran solicitudes del formulario público antiguo; se borran.
DELETE FROM solicitudes_cita WHERE paciente_id IS NULL;

ALTER TABLE solicitudes_cita
    MODIFY paciente_id INT UNSIGNED NOT NULL,
    ADD COLUMN IF NOT EXISTS cita_id INT UNSIGNED NULL AFTER estado;

ALTER TABLE solicitudes_cita
    ADD CONSTRAINT fk_solicitudes_cita
        FOREIGN KEY IF NOT EXISTS (cita_id) REFERENCES citas (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
-- 4. Tablas nuevas (iguales que en schema.sql)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED  NULL,
    accion      VARCHAR(40)   NOT NULL,
    paciente_id INT UNSIGNED  NULL,
    detalle     VARCHAR(255)  NULL,
    ip          VARCHAR(45)   NOT NULL,
    creado_en   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auditoria_fecha (creado_en),
    KEY idx_auditoria_paciente (paciente_id, creado_en),
    KEY idx_auditoria_accion (accion, creado_en),
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_auditoria_paciente
        FOREIGN KEY (paciente_id) REFERENCES pacientes (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS horarios_medico (
    id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    medico_id    INT UNSIGNED     NOT NULL,
    dia_semana   TINYINT UNSIGNED NOT NULL,
    hora_inicio  TIME             NOT NULL,
    hora_fin     TIME             NOT NULL,
    duracion_min SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    PRIMARY KEY (id),
    KEY idx_horarios_medico (medico_id, dia_semana),
    CONSTRAINT fk_horarios_medico
        FOREIGN KEY (medico_id) REFERENCES usuarios (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_horarios_dia CHECK (dia_semana BETWEEN 1 AND 7),
    CONSTRAINT ck_horarios_horas CHECK (hora_inicio < hora_fin),
    CONSTRAINT ck_horarios_duracion CHECK (duracion_min BETWEEN 10 AND 120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nota: horarios_medico queda vacía. Sin horarios no hay huecos para
-- reservar: el administrador los crea desde el panel (Horarios).
