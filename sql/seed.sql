-- =====================================================================
-- Clínica Vida Sana - Datos de prueba (TODOS FICTICIOS)
-- Importar DESPUÉS de schema.sql.
--
-- Cuentas de prueba (solo para desarrollo). Todas usan: Clinica2026!
--   Personal (entra con "usuario"):
--     admin      rol admin
--     admision1  rol admision
--     medico1    rol medico (Medicina General)
--     medico2    rol medico (Pediatría)
--   Pacientes (entran con "correo"):
--     paciente1@example.test    cuenta VERIFICADA (Ana: citas, historial y solicitudes)
--     paciente2@example.test    cuenta VERIFICADA (Carlos: sirve para comprobar
--                               que un paciente NO ve los datos de otro)
--     pendiente@example.test    cuenta PENDIENTE (espera a admisión)
-- Las contraseñas están guardadas como hash (password_hash de PHP).
-- ¡Cambiarlas antes de publicar en el servidor Fedora!
-- Los correos usan el dominio reservado .test: no existen de verdad.
-- =====================================================================

USE clinica_vida_sana;

-- Áreas ---------------------------------------------------------------
INSERT INTO areas (id, nombre, descripcion, publica) VALUES
    (1, 'Admisión',         NULL, 0),
    (2, 'Medicina General', 'Controles, chequeos y atención de molestias del día a día para toda la familia.', 1),
    (3, 'Pediatría',        'Cuidado y seguimiento del crecimiento de niños y adolescentes.', 1),
    (4, 'Laboratorio',      'Análisis clínicos con resultados claros y entregados a tiempo.', 1),
    (5, 'Sistemas',         NULL, 0);

-- Pacientes (documentos inventados, no corresponden a personas reales) --
INSERT INTO pacientes (id, tipo_documento, numero_documento, nombres, apellidos, correo, fecha_nacimiento, telefono) VALUES
    (1, 'DNI', '90000001', 'Ana María', 'Paredes Ficticia',  'paciente1@example.test', '1990-03-14', '900000001'),
    (2, 'DNI', '90000002', 'Carlos',    'Rojas Ejemplo',     NULL,                     '1985-07-02', '900000002'),
    (3, 'DNI', '90000003', 'Sofía',     'Mendoza Prueba',    NULL,                     '2016-11-21', '900000003'),
    (4, 'DNI', '90000004', 'Luis',      'Vargas Demo',       NULL,                     '1972-01-30', '900000004'),
    (5, 'DNI', '90000005', 'Elena',     'Castillo Simulada', NULL,                     '1998-09-09', '900000005'),
    (6, 'DNI', '90000006', 'Nuevo',     'Registro Demo',     'pendiente@example.test', NULL,         '900000006');

-- Usuarios (hash de 'Clinica2026!') -------------------------------------
-- Personal: usuario obligatorio, sin correo ni ficha.
INSERT INTO usuarios (id, nombre, usuario, correo, password_hash, rol, area_id, paciente_id, activo, verificado) VALUES
    (1, 'Administrador Demo',    'admin',     NULL, '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'admin',    5, NULL, 1, 1),
    (2, 'Lucía Admisión Demo',   'admision1', NULL, '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'admision', 1, NULL, 1, 1),
    (3, 'Dr. Mateo Médico Demo', 'medico1',   NULL, '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'medico',   2, NULL, 1, 1),
    (4, 'Dra. Camila Pediatra Demo', 'medico2', NULL, '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'medico', 3, NULL, 1, 1);

-- Pacientes con cuenta: correo obligatorio, enlazados a su ficha.
-- paciente1 fue verificada por admision1 (id 2); la otra espera verificación.
INSERT INTO usuarios (id, nombre, usuario, correo, password_hash, rol, area_id, paciente_id, activo, verificado, verificado_por, verificado_en) VALUES
    (5, 'Ana María Paredes Ficticia', NULL, 'paciente1@example.test', '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'paciente', NULL, 1, 1, 1, 2, '2026-10-15 10:00:00'),
    (6, 'Nuevo Registro Demo',        NULL, 'pendiente@example.test', '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'paciente', NULL, 6, 1, 0, NULL, NULL),
    (7, 'Carlos Rojas Ejemplo',       NULL, 'paciente2@example.test', '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'paciente', NULL, 2, 1, 1, 2, '2026-10-16 11:00:00');

-- Citas (fechas fijas de ejemplo; se pueden editar desde el panel) -----
-- Medicina General -> medico1 (id 3). Pediatría -> medico2 (id 4).
-- Laboratorio no tiene médico asignado todavía.
INSERT INTO citas (id, paciente_id, area_id, medico_id, fecha, hora, motivo, estado, registrado_por) VALUES
    (1, 1, 2, 3,    '2026-11-12', '09:00:00', 'Control general',         'confirmada', 2),
    (2, 2, 2, 3,    '2026-11-12', '10:30:00', 'Dolor de cabeza',         'pendiente',  2),
    (3, 3, 3, 4,    '2026-11-12', '11:00:00', 'Control de crecimiento',  'confirmada', 2),
    (4, 4, 4, NULL, '2026-11-13', '08:30:00', 'Análisis de sangre',      'pendiente',  2),
    (5, 5, 2, 3,    '2026-11-10', '15:00:00', 'Resfrío',                 'atendida',   2),
    (6, 2, 3, NULL, '2026-11-09', '16:00:00', 'Consulta cancelada',      'cancelada',  2),
    (7, 1, 2, 3,    '2026-10-20', '09:00:00', 'Chequeo anual',           'atendida',   2),
    -- Confirmada para HOY a las 08:00: sirve para probar "Atender" con medico1.
    (8, 2, 2, 3,    '2026-11-12', '08:00:00', 'Seguimiento del dolor de cabeza', 'confirmada', 2),
    -- Atendidas antes, con su historia clínica (ver "atenciones" más abajo).
    (9, 1, 2, 3,    '2026-09-15', '10:00:00', 'Dolor de espalda',        'atendida',   2),
    (10, 2, 2, 3,   '2026-10-05', '11:30:00', 'Control de presión',      'atendida',   2);

-- Historia clínica: atenciones de las citas ya atendidas -----------------
INSERT INTO atenciones (cita_id, paciente_id, medico_id, motivo, diagnostico, tratamiento, notas) VALUES
    (5, 5, 3, 'Resfrío',        'Resfrío común sin complicaciones.',
        'Reposo, abundante líquido y paracetamol si hay malestar.',
        'Volver a consulta si la fiebre dura más de 3 días.'),
    (7, 1, 3, 'Chequeo anual',  'Paciente en buen estado general.',
        'Mantener alimentación balanceada y actividad física regular.',
        'Se recomienda control de rutina en 12 meses.'),
    (9, 1, 3, 'Dolor de espalda', 'Contractura muscular lumbar leve.',
        'Reposo relativo y calor local durante 3 días.',
        'Evitar cargar peso. Volver si el dolor continúa.'),
    (10, 2, 3, 'Control de presión', 'Presión arterial dentro de lo normal.',
        'Sin medicación. Reducir el consumo de sal.',
        NULL);

-- Solicitudes de cita hechas desde el portal (de pacientes con cuenta) ----
INSERT INTO solicitudes_cita (paciente_id, area_id, fecha_preferida, mensaje, estado) VALUES
    (1, 4, '2026-11-20', 'Quisiera un análisis de sangre de rutina.', 'nueva'),
    (2, 2, '2026-11-18', 'Dolor de cabeza que no se me quita.',       'nueva'),
    (1, 2, '2026-10-20', 'Chequeo anual.',                            'atendida'),
    (1, 2, '2026-10-01', 'Pedí la misma cita dos veces.',             'descartada');
