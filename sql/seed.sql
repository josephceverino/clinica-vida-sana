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
--     medico3    rol medico (Laboratorio)
--   Pacientes (entran con "correo"):
--     paciente1@example.test    cuenta VERIFICADA (Ana: citas, historial y solicitudes)
--     paciente2@example.test    cuenta VERIFICADA (Carlos: sirve para comprobar
--                               que un paciente NO ve los datos de otro)
--     pendiente@example.test    cuenta PENDIENTE (espera a admisión)
-- Las contraseñas están guardadas como hash (password_hash de PHP).
-- ¡Cambiarlas antes de publicar en el servidor Fedora!
-- Los correos usan el dominio reservado .test: no existen de verdad.
--
-- Las fechas de citas, atenciones y solicitudes se calculan a partir del
-- día en que se importa (CURDATE()). Así siempre hay citas "de hoy" para
-- la demo, sin editar el archivo.
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
    (4, 'Dra. Camila Pediatra Demo', 'medico2', NULL, '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'medico', 3, NULL, 1, 1),
    -- Responsable de Laboratorio: sin él, lo que se pide a Laboratorio no
    -- llegaría a nadie. Usa el rol "medico" para ver y atender sus citas.
    (8, 'Lic. Rosa Laboratorio Demo', 'medico3', NULL, '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'medico', 4, NULL, 1, 1);

-- Pacientes con cuenta: correo obligatorio, enlazados a su ficha.
-- paciente1 fue verificada por admision1 (id 2); la otra espera verificación.
-- Todos aceptaron la política de privacidad al registrarse.
INSERT INTO usuarios (id, nombre, usuario, correo, password_hash, rol, area_id, paciente_id, activo, verificado, verificado_por, verificado_en, acepto_privacidad_en) VALUES
    (5, 'Ana María Paredes Ficticia', NULL, 'paciente1@example.test', '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'paciente', NULL, 1, 1, 1, 2, '2026-10-15 10:00:00', '2026-10-14 18:30:00'),
    (6, 'Nuevo Registro Demo',        NULL, 'pendiente@example.test', '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'paciente', NULL, 6, 1, 0, NULL, NULL, '2026-11-10 09:15:00'),
    (7, 'Carlos Rojas Ejemplo',       NULL, 'paciente2@example.test', '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6', 'paciente', NULL, 2, 1, 1, 2, '2026-10-16 11:00:00', '2026-10-15 20:05:00');

-- Fecha de referencia: el día en que se importa este archivo.
-- Todas las fechas de abajo se cuentan desde aquí (hoy, hoy + 2 días...).
SET @hoy = CURDATE();

-- Citas -----------------------------------------------------------------
-- Medicina General -> medico1 (id 3). Pediatría -> medico2 (id 4).
-- Laboratorio -> medico3 (id 8).
-- Toda cita confirmada tiene médico: si no, nadie podría atenderla.
INSERT INTO citas (id, paciente_id, area_id, medico_id, fecha, hora, motivo, estado, registrado_por) VALUES
    -- HOY: sirven para probar "Atender" y el resumen del día.
    (1, 1, 2, 3, @hoy, '09:00:00', 'Control general',                 'confirmada', 2),
    (2, 2, 2, 3, @hoy, '10:30:00', 'Dolor de cabeza',                 'pendiente',  2),
    (3, 3, 3, 4, @hoy, '11:00:00', 'Control de crecimiento',          'confirmada', 2),
    (8, 2, 2, 3, @hoy, '08:00:00', 'Seguimiento del dolor de cabeza', 'confirmada', 2),
    -- PRÓXIMOS DÍAS: llenan el calendario semanal.
    (4,  4, 4, 8, DATE_ADD(@hoy, INTERVAL 1 DAY), '08:30:00', 'Análisis de sangre',     'confirmada', 2),
    (11, 1, 2, 3, DATE_ADD(@hoy, INTERVAL 2 DAY), '10:00:00', 'Revisión de resultados', 'confirmada', 2),
    (12, 5, 2, 3, DATE_ADD(@hoy, INTERVAL 3 DAY), '16:00:00', 'Dolor de garganta',      'pendiente',  2),
    (13, 3, 3, 4, DATE_ADD(@hoy, INTERVAL 5 DAY), '09:30:00', 'Vacunación',             'confirmada', 2),
    -- PASADAS: atendidas (con historia clínica) y una cancelada.
    (5,  5, 2, 3,    DATE_SUB(@hoy, INTERVAL 3 DAY),  '15:00:00', 'Resfrío',            'atendida',  2),
    (6,  2, 3, NULL, DATE_SUB(@hoy, INTERVAL 4 DAY),  '16:00:00', 'Consulta cancelada', 'cancelada', 2),
    (7,  1, 2, 3,    DATE_SUB(@hoy, INTERVAL 24 DAY), '09:00:00', 'Chequeo anual',      'atendida',  2),
    (9,  1, 2, 3,    DATE_SUB(@hoy, INTERVAL 59 DAY), '10:00:00', 'Dolor de espalda',   'atendida',  2),
    (10, 2, 2, 3,    DATE_SUB(@hoy, INTERVAL 39 DAY), '11:30:00', 'Control de presión', 'atendida',  2),
    -- Más atenciones de meses anteriores: llenan el gráfico "Mis atenciones
    -- por mes" del médico (medico1).
    (14, 4, 2, 3, DATE_SUB(@hoy, INTERVAL 75 DAY),  '09:00:00', 'Dolor de rodilla',     'atendida', 2),
    (15, 5, 2, 3, DATE_SUB(@hoy, INTERVAL 82 DAY),  '10:30:00', 'Chequeo general',      'atendida', 2),
    (16, 2, 2, 3, DATE_SUB(@hoy, INTERVAL 105 DAY), '15:00:00', 'Gastritis',            'atendida', 2),
    (17, 4, 2, 3, DATE_SUB(@hoy, INTERVAL 110 DAY), '11:00:00', 'Control de colesterol','atendida', 2),
    (18, 1, 2, 3, DATE_SUB(@hoy, INTERVAL 140 DAY), '09:30:00', 'Alergia estacional',   'atendida', 2);

-- Historia clínica: atenciones de las citas ya atendidas -----------------
-- creado_en = el día de la cita, un rato después de la hora.
INSERT INTO atenciones (cita_id, paciente_id, medico_id, motivo, diagnostico, tratamiento, notas, creado_en) VALUES
    (5, 5, 3, 'Resfrío',        'Resfrío común sin complicaciones.',
        'Reposo, abundante líquido y paracetamol si hay malestar.',
        'Volver a consulta si la fiebre dura más de 3 días.',
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 3 DAY), '15:25:00')),
    (7, 1, 3, 'Chequeo anual',  'Paciente en buen estado general.',
        'Mantener alimentación balanceada y actividad física regular.',
        'Se recomienda control de rutina en 12 meses.',
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 24 DAY), '09:30:00')),
    (9, 1, 3, 'Dolor de espalda', 'Contractura muscular lumbar leve.',
        'Reposo relativo y calor local durante 3 días.',
        'Evitar cargar peso. Volver si el dolor continúa.',
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 59 DAY), '10:20:00')),
    (10, 2, 3, 'Control de presión', 'Presión arterial dentro de lo normal.',
        'Sin medicación. Reducir el consumo de sal.',
        NULL,
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 39 DAY), '11:50:00')),
    (14, 4, 3, 'Dolor de rodilla', 'Esguince leve de rodilla derecha.',
        'Reposo, hielo local y antiinflamatorio por 5 días.', NULL,
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 75 DAY), '09:25:00')),
    (15, 5, 3, 'Chequeo general', 'Sin hallazgos de importancia.',
        'Continuar con hábitos saludables.', NULL,
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 82 DAY), '10:50:00')),
    (16, 2, 3, 'Gastritis', 'Gastritis leve.',
        'Dieta blanda y protector gástrico por 14 días.', 'Evitar café y picantes.',
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 105 DAY), '15:20:00')),
    (17, 4, 3, 'Control de colesterol', 'Colesterol ligeramente elevado.',
        'Dieta baja en grasas y caminata diaria.', 'Repetir análisis en 3 meses.',
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 110 DAY), '11:20:00')),
    (18, 1, 3, 'Alergia estacional', 'Rinitis alérgica.',
        'Antihistamínico por 7 días.', NULL,
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 140 DAY), '09:50:00'));

-- Solicitudes de cita hechas desde el portal (de pacientes con cuenta) ----
-- Las "nuevas" esperan a admisión. La "atendida" se convirtió en la cita 7
-- (por eso tiene cita_id). La "descartada" no generó cita.
INSERT INTO solicitudes_cita (paciente_id, area_id, fecha_preferida, mensaje, estado, cita_id, creado_en) VALUES
    (1, 4, DATE_ADD(@hoy, INTERVAL 7 DAY),  'Quisiera un análisis de sangre de rutina.', 'nueva',      NULL,
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 1 DAY), '18:10:00')),
    (2, 2, DATE_ADD(@hoy, INTERVAL 5 DAY),  'Dolor de cabeza que no se me quita.',       'nueva',      NULL,
        TIMESTAMP(@hoy, '07:45:00')),
    (1, 2, DATE_SUB(@hoy, INTERVAL 24 DAY), 'Chequeo anual.',                            'atendida',   7,
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 30 DAY), '12:00:00')),
    (1, 2, DATE_SUB(@hoy, INTERVAL 40 DAY), 'Pedí la misma cita dos veces.',             'descartada', NULL,
        TIMESTAMP(DATE_SUB(@hoy, INTERVAL 45 DAY), '12:05:00'));

-- Horarios de los médicos ------------------------------------------------
-- dia_semana: 1 = lunes ... 7 = domingo. De aquí salen los huecos libres
-- que el paciente ve al pedir cita.
INSERT INTO horarios_medico (medico_id, dia_semana, hora_inicio, hora_fin, duracion_min) VALUES
    -- medico1 (Medicina General): lunes a viernes por la mañana, turnos de 20 min,
    -- y lunes y miércoles también por la tarde.
    (3, 1, '08:00', '13:00', 20), (3, 2, '08:00', '13:00', 20), (3, 3, '08:00', '13:00', 20),
    (3, 4, '08:00', '13:00', 20), (3, 5, '08:00', '13:00', 20),
    (3, 1, '15:00', '18:00', 20), (3, 3, '15:00', '18:00', 20),
    -- medico2 (Pediatría): lunes a viernes de 09:00 a 13:00, turnos de 30 min.
    (4, 1, '09:00', '13:00', 30), (4, 2, '09:00', '13:00', 30), (4, 3, '09:00', '13:00', 30),
    (4, 4, '09:00', '13:00', 30), (4, 5, '09:00', '13:00', 30),
    -- medico3 (Laboratorio): lunes a sábado de 07:00 a 11:00, turnos de 15 min.
    (8, 1, '07:00', '11:00', 15), (8, 2, '07:00', '11:00', 15), (8, 3, '07:00', '11:00', 15),
    (8, 4, '07:00', '11:00', 15), (8, 5, '07:00', '11:00', 15), (8, 6, '07:00', '11:00', 15);
