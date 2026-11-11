-- =====================================================================
-- Clínica Vida Sana - Datos de prueba (TODOS FICTICIOS)
-- BORRADOR (por Claude, para que Alexander lo revise y ajuste).
-- Importar DESPUÉS de schema.sql.
--
-- Usuarios de prueba (solo para desarrollo):
--   admin     / Clinica2026!   (rol admin)
--   admision1 / Clinica2026!   (rol admision)
--   medico1   / Clinica2026!   (rol medico)
-- Las contraseñas están guardadas como hash (password_hash de PHP).
-- ¡Cambiarlas antes de publicar en el servidor Fedora!
-- =====================================================================

USE clinica_vida_sana;

-- Áreas ---------------------------------------------------------------
INSERT INTO areas (id, nombre, descripcion, publica) VALUES
    (1, 'Admisión',         NULL, 0),
    (2, 'Medicina General', 'Controles, chequeos y atención de molestias del día a día para toda la familia.', 1),
    (3, 'Pediatría',        'Cuidado y seguimiento del crecimiento de niños y adolescentes.', 1),
    (4, 'Laboratorio',      'Análisis clínicos con resultados claros y entregados a tiempo.', 1),
    (5, 'Sistemas',         NULL, 0);

-- Usuarios (hash de 'Clinica2026!') -------------------------------------
INSERT INTO usuarios (id, nombre, usuario, password_hash, rol, area_id, activo) VALUES
    (1, 'Administrador Demo',  'admin',     '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'admin',    5, 1),
    (2, 'Lucía Admisión Demo', 'admision1', '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'admision', 1, 1),
    (3, 'Dr. Mateo Médico Demo', 'medico1', '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'medico',   2, 1);

-- Pacientes (DNI inventados, no corresponden a personas reales) ---------
INSERT INTO pacientes (id, dni, nombres, apellidos, fecha_nacimiento, telefono) VALUES
    (1, '90000001', 'Ana María',  'Paredes Ficticia',  '1990-03-14', '900000001'),
    (2, '90000002', 'Carlos',     'Rojas Ejemplo',     '1985-07-02', '900000002'),
    (3, '90000003', 'Sofía',      'Mendoza Prueba',    '2016-11-21', '900000003'),
    (4, '90000004', 'Luis',       'Vargas Demo',       '1972-01-30', '900000004'),
    (5, '90000005', 'Elena',      'Castillo Simulada', '1998-09-09', '900000005');

-- Citas (fechas fijas de ejemplo; se pueden editar desde el panel) -----
INSERT INTO citas (paciente_id, area_id, fecha, hora, motivo, estado, registrado_por) VALUES
    (1, 2, '2026-11-12', '09:00:00', 'Control general',         'confirmada', 2),
    (2, 2, '2026-11-12', '10:30:00', 'Dolor de cabeza',         'pendiente',  2),
    (3, 3, '2026-11-12', '11:00:00', 'Control de crecimiento',  'confirmada', 2),
    (4, 4, '2026-11-13', '08:30:00', 'Análisis de sangre',      'pendiente',  2),
    (5, 2, '2026-11-10', '15:00:00', 'Resfrío',                 'atendida',   2),
    (2, 3, '2026-11-09', '16:00:00', 'Consulta cancelada',      'cancelada',  2);

-- Solicitudes llegadas desde la web pública ------------------------------
INSERT INTO solicitudes_cita (nombre, dni, telefono, area_id, fecha_preferida, mensaje, estado) VALUES
    ('Marta Solicitante Demo', '90000101', '900000101', 2, '2026-11-15', 'Quisiera una consulta por la mañana.', 'nueva'),
    ('Pedro Ejemplo Web',      '90000102', '900000102', 3, '2026-11-16', 'Cita para mi hijo.',                   'nueva'),
    ('Rosa Prueba Web',        '90000103', '900000103', 4, NULL,         'Necesito un examen de laboratorio.',   'atendida');
