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
                   areas.nombre AS area
            FROM solicitudes_cita
            INNER JOIN pacientes
                ON pacientes.id = solicitudes_cita.paciente_id
            INNER JOIN areas
                ON areas.id = solicitudes_cita.area_id";

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
                areas.nombre AS area
         FROM solicitudes_cita
         INNER JOIN areas
             ON areas.id = solicitudes_cita.area_id
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
 * Cambia el estado de una solicitud nueva.
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

    if (!in_array($estado, ['atendida', 'descartada'], true)) {
        throw new InvalidArgumentException(
            'Selecciona atendida o descartada.'
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
