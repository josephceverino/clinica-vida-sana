<?php
// Solicitudes de cita.
// El paciente las crea desde su portal (public/paciente/pedir-cita.php) y
// admisión las revisa en el panel (public/panel/solicitudes.php).
require_once __DIR__ . '/../core/db.php';

// Máximo de solicitudes "nuevas" (sin atender) que un paciente puede tener a
// la vez. Evita que alguien llene el panel de admisión.
const MAX_SOLICITUDES_NUEVAS_POR_PACIENTE = 3;

/**
 * Consulta solicitudes (las ve admisión) y permite filtrar por estado.
 * Los datos del paciente salen de su ficha.
 */
function buscar_solicitudes(string $estado = ''): array
{
    $estados_validos = ['nueva', 'atendida', 'descartada'];

    if (
        $estado !== ''
        && !in_array($estado, $estados_validos, true)
    ) {
        throw new InvalidArgumentException(
            'El estado de la solicitud no es válido.'
        );
    }

    $sql = "SELECT solicitudes_cita.id,
                   CONCAT(pacientes.nombres, ' ', pacientes.apellidos) AS nombre,
                   pacientes.tipo_documento,
                   pacientes.numero_documento,
                   pacientes.telefono,
                   solicitudes_cita.fecha_preferida,
                   solicitudes_cita.mensaje,
                   solicitudes_cita.estado,
                   solicitudes_cita.creado_en,
                   solicitudes_cita.cita_id,
                   citas.fecha AS cita_fecha,
                   citas.hora AS cita_hora,
                   areas.nombre AS area
            FROM solicitudes_cita
            INNER JOIN pacientes
                ON pacientes.id = solicitudes_cita.paciente_id
            INNER JOIN areas
                ON areas.id = solicitudes_cita.area_id
            LEFT JOIN citas
                ON citas.id = solicitudes_cita.cita_id";

    $parametros = [];

    if ($estado !== '') {
        $sql .= ' WHERE solicitudes_cita.estado = :estado';
        $parametros['estado'] = $estado;
    }

    $sql .= ' ORDER BY solicitudes_cita.creado_en DESC,
                       solicitudes_cita.id DESC
              LIMIT 100';

    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}

/**
 * Solicitudes de UN paciente (para "Mis citas" en su portal).
 * $paciente_id debe salir siempre de la sesión, nunca de un formulario.
 */
function solicitudes_del_paciente(int $paciente_id): array
{
    $consulta = db()->prepare(
        'SELECT solicitudes_cita.id,
                solicitudes_cita.fecha_preferida,
                solicitudes_cita.mensaje,
                solicitudes_cita.estado,
                solicitudes_cita.creado_en,
                solicitudes_cita.cita_id,
                citas.fecha AS cita_fecha,
                citas.hora AS cita_hora,
                areas.nombre AS area
         FROM solicitudes_cita
         INNER JOIN areas
             ON areas.id = solicitudes_cita.area_id
         LEFT JOIN citas
             ON citas.id = solicitudes_cita.cita_id
         WHERE solicitudes_cita.paciente_id = :paciente
         ORDER BY solicitudes_cita.creado_en DESC,
                  solicitudes_cita.id DESC
         LIMIT 50'
    );
    $consulta->execute(['paciente' => $paciente_id]);

    return $consulta->fetchAll();
}

/**
 * Valida y guarda la solicitud de cita de un paciente. Devuelve su ID.
 *
 * $paciente_id sale de la SESIÓN (usuario_actual()['paciente_id']).
 * Lo que venga del formulario en $datos NUNCA decide de quién es la solicitud.
 */
function registrar_solicitud_paciente(int $paciente_id, array $datos): int
{
    if ($paciente_id < 1) {
        throw new InvalidArgumentException(
            'No se pudo identificar al paciente.'
        );
    }

    foreach (['area_id', 'fecha_preferida', 'mensaje'] as $campo) {
        if (isset($datos[$campo]) && !is_scalar($datos[$campo])) {
            throw new InvalidArgumentException(
                'Los datos enviados no son válidos.'
            );
        }
    }

    $fecha = trim((string) ($datos['fecha_preferida'] ?? ''));
    $mensaje = trim((string) ($datos['mensaje'] ?? ''));

    $area_id = filter_var(
        $datos['area_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$area_id) {
        throw new InvalidArgumentException('Selecciona un área.');
    }

    // El motivo es obligatorio: admisión lo necesita para decidir.
    if (mb_strlen($mensaje) < 5 || mb_strlen($mensaje) > 500) {
        throw new InvalidArgumentException(
            'Cuéntanos el motivo de la consulta (de 5 a 500 caracteres).'
        );
    }

    if ($fecha !== '') {
        $zona = new DateTimeZone('America/Lima');

        $fecha_validada = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $zona
        );
        $hoy = new DateTimeImmutable('today', $zona);

        if (
            !$fecha_validada
            || $fecha_validada->format('Y-m-d') !== $fecha
            || $fecha_validada < $hoy
            || $fecha_validada > $hoy->modify('+180 days')
        ) {
            throw new InvalidArgumentException(
                'La fecha preferida no es válida: debe ser hoy o una fecha '
                . 'de los próximos 6 meses.'
            );
        }
    }

    $conexion = db();

    // Solo áreas públicas, aunque se manipule el formulario.
    $consulta = $conexion->prepare(
        'SELECT id FROM areas WHERE id = :id AND publica = 1'
    );
    $consulta->execute(['id' => $area_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException(
            'El área seleccionada no está disponible.'
        );
    }

    // Transacción: bloqueamos la ficha del paciente mientras contamos y
    // guardamos, para que dos envíos a la vez no se salten el límite.
    $conexion->beginTransaction();

    try {
        $consulta = $conexion->prepare(
            'SELECT id FROM pacientes WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $paciente_id]);

        if (!$consulta->fetch()) {
            throw new InvalidArgumentException(
                'No se pudo identificar al paciente.'
            );
        }

        $consulta = $conexion->prepare(
            "SELECT COUNT(*) FROM solicitudes_cita
             WHERE paciente_id = :id AND estado = 'nueva'"
        );
        $consulta->execute(['id' => $paciente_id]);

        if ((int) $consulta->fetchColumn() >= MAX_SOLICITUDES_NUEVAS_POR_PACIENTE) {
            throw new InvalidArgumentException(
                'Ya tienes ' . MAX_SOLICITUDES_NUEVAS_POR_PACIENTE
                . ' solicitudes pendientes. Espera a que admisión las atienda.'
            );
        }

        $consulta = $conexion->prepare(
            "INSERT INTO solicitudes_cita
                (paciente_id, area_id, fecha_preferida, mensaje, estado)
             VALUES
                (:paciente, :area, :fecha, :mensaje, 'nueva')"
        );
        $consulta->execute([
            'paciente' => $paciente_id,
            'area'     => $area_id,
            'fecha'    => $fecha === '' ? null : $fecha,
            'mensaje'  => $mensaje,
        ]);

        $nueva_id = (int) $conexion->lastInsertId();
        $conexion->commit();

        return $nueva_id;
    } catch (Throwable $error) {
        $conexion->rollBack();
        throw $error;
    }
}

/**
 * Una solicitud con los datos que admisión necesita para programarla.
 * Devuelve null si no existe.
 */
function obtener_solicitud(int $solicitud_id): ?array
{
    $consulta = db()->prepare(
        "SELECT solicitudes_cita.id,
                solicitudes_cita.paciente_id,
                solicitudes_cita.area_id,
                solicitudes_cita.fecha_preferida,
                solicitudes_cita.mensaje,
                solicitudes_cita.estado,
                solicitudes_cita.creado_en,
                CONCAT(pacientes.nombres, ' ', pacientes.apellidos) AS nombre,
                pacientes.tipo_documento,
                pacientes.numero_documento,
                pacientes.telefono,
                areas.nombre AS area
         FROM solicitudes_cita
         INNER JOIN pacientes ON pacientes.id = solicitudes_cita.paciente_id
         INNER JOIN areas ON areas.id = solicitudes_cita.area_id
         WHERE solicitudes_cita.id = :id"
    );
    $consulta->execute(['id' => $solicitud_id]);
    $fila = $consulta->fetch();

    return $fila ?: null;
}

/**
 * Convierte una solicitud NUEVA en una cita CONFIRMADA con fecha, hora y
 * médico. Devuelve el ID de la cita creada.
 *
 * Todo pasa en una sola transacción: o se crea la cita Y la solicitud queda
 * "atendida" (enlazada a esa cita), o no cambia nada.
 *
 * $datos viene del formulario: fecha (Y-m-d), hora (H:i) y medico_id.
 * $usuario_id sale de la SESIÓN (quien programa la cita).
 */
function convertir_solicitud_en_cita(
    int $solicitud_id,
    array $datos,
    int $usuario_id
): int {
    if ($solicitud_id < 1) {
        throw new InvalidArgumentException('La solicitud no es válida.');
    }

    foreach (['fecha', 'hora', 'medico_id'] as $campo) {
        if (!isset($datos[$campo]) || !is_string($datos[$campo])) {
            throw new InvalidArgumentException(
                'Indica la fecha, la hora y el médico.'
            );
        }
    }

    $fecha = trim($datos['fecha']);
    $hora = trim($datos['hora']);
    $medico_id = filter_var(
        $datos['medico_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$medico_id) {
        throw new InvalidArgumentException('Selecciona un médico.');
    }

    // La fecha y la hora deben ser válidas y futuras.
    $zona = new DateTimeZone('America/Lima');
    $texto = $fecha . ' ' . $hora;
    $fecha_hora = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $texto, $zona);

    if (
        !$fecha_hora
        || $fecha_hora->format('Y-m-d H:i') !== $texto
        || $fecha_hora <= new DateTimeImmutable('now', $zona)
    ) {
        throw new InvalidArgumentException(
            'Indica una fecha y hora válidas y futuras.'
        );
    }

    $conexion = db();

    // Solo admin y admisión programan citas.
    $consulta = $conexion->prepare(
        "SELECT id FROM usuarios
         WHERE id = :id AND activo = 1
           AND rol IN ('admin', 'admision')"
    );
    $consulta->execute(['id' => $usuario_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException('El usuario no está autorizado.');
    }

    $conexion->beginTransaction();

    try {
        // 1. La solicitud debe seguir "nueva". FOR UPDATE la bloquea: si dos
        //    personas de admisión la programan a la vez, solo una lo logra.
        $consulta = $conexion->prepare(
            'SELECT id, paciente_id, area_id, mensaje, estado
             FROM solicitudes_cita WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $solicitud_id]);
        $solicitud = $consulta->fetch();

        if (!$solicitud || $solicitud['estado'] !== 'nueva') {
            throw new InvalidArgumentException(
                'La solicitud no existe o ya fue procesada.'
            );
        }

        // 2. El médico debe estar activo y ser del área que pidió el paciente.
        $consulta = $conexion->prepare(
            "SELECT id FROM usuarios
             WHERE id = :id AND rol = 'medico' AND activo = 1
               AND area_id = :area
             FOR UPDATE"
        );
        $consulta->execute([
            'id'   => $medico_id,
            'area' => $solicitud['area_id'],
        ]);

        if (!$consulta->fetch()) {
            throw new InvalidArgumentException(
                'El médico no existe o no atiende en esa área.'
            );
        }

        // 3. Ni el médico ni el paciente pueden tener otra cita a esa hora.
        $consulta = $conexion->prepare(
            "SELECT
                SUM(medico_id = :medico)     AS choca_medico,
                SUM(paciente_id = :paciente) AS choca_paciente
             FROM citas
             WHERE fecha = :fecha AND hora = :hora
               AND estado IN ('pendiente', 'confirmada')"
        );
        $consulta->execute([
            'medico'   => $medico_id,
            'paciente' => $solicitud['paciente_id'],
            'fecha'    => $fecha,
            'hora'     => $hora . ':00',
        ]);
        $choques = $consulta->fetch();

        if ((int) $choques['choca_medico'] > 0) {
            throw new InvalidArgumentException(
                'El médico ya tiene una cita a esa hora. Elige otro horario.'
            );
        }

        if ((int) $choques['choca_paciente'] > 0) {
            throw new InvalidArgumentException(
                'El paciente ya tiene una cita a esa hora.'
            );
        }

        // 4. Crear la cita ya confirmada (tiene médico, fecha y hora).
        //    El motivo es el mensaje del paciente (recortado a 255 letras).
        $consulta = $conexion->prepare(
            "INSERT INTO citas
                (paciente_id, area_id, medico_id, fecha, hora,
                 motivo, estado, registrado_por)
             VALUES
                (:paciente, :area, :medico, :fecha, :hora,
                 :motivo, 'confirmada', :usuario)"
        );
        $consulta->execute([
            'paciente' => $solicitud['paciente_id'],
            'area'     => $solicitud['area_id'],
            'medico'   => $medico_id,
            'fecha'    => $fecha,
            'hora'     => $hora . ':00',
            'motivo'   => mb_substr((string) $solicitud['mensaje'], 0, 255),
            'usuario'  => $usuario_id,
        ]);
        $cita_id = (int) $conexion->lastInsertId();

        // 5. Marcar la solicitud como atendida y enlazarla a la cita.
        $consulta = $conexion->prepare(
            "UPDATE solicitudes_cita
             SET estado = 'atendida', cita_id = :cita
             WHERE id = :id"
        );
        $consulta->execute([
            'cita' => $cita_id,
            'id'   => $solicitud_id,
        ]);

        $conexion->commit();

        return $cita_id;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $error;
    }
}

/**
 * Descarta una solicitud nueva (por ejemplo, si está repetida).
 * Para aceptarla se usa convertir_solicitud_en_cita(): así ninguna
 * solicitud queda "atendida" sin su cita.
 * El usuario debe obtenerse de la sesión.
 */
function cambiar_estado_solicitud(
    int $solicitud_id,
    string $estado,
    int $usuario_id
): void {
    if ($solicitud_id < 1) {
        throw new InvalidArgumentException(
            'La solicitud no es válida.'
        );
    }

    if ($estado !== 'descartada') {
        throw new InvalidArgumentException(
            'Para aceptar una solicitud, prográmala como cita.'
        );
    }

    $conexion = db();

    $consulta = $conexion->prepare(
        "SELECT id FROM usuarios
         WHERE id = :id AND activo = 1
           AND rol IN ('admin', 'admision')"
    );
    $consulta->execute(['id' => $usuario_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException(
            'El usuario no está autorizado.'
        );
    }

    $consulta = $conexion->prepare(
        "UPDATE solicitudes_cita
         SET estado = :estado
         WHERE id = :id AND estado = 'nueva'"
    );
    $consulta->execute([
        'estado' => $estado,
        'id' => $solicitud_id,
    ]);

    if ($consulta->rowCount() === 0) {
        throw new InvalidArgumentException(
            'La solicitud no existe o ya fue procesada.'
        );
    }
}
