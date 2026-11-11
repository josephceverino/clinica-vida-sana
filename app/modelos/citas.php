<?php

require_once __DIR__ . '/../core/db.php';

/**
 * Consulta citas con los datos del paciente y del área.
 * Permite filtrar por una fecha exacta.
 */
function buscar_citas(string $fecha = ''): array
{
    $fecha = trim($fecha);

    if ($fecha !== '') {
        $fecha_validada = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha
        );

        if (
            !$fecha_validada
            || $fecha_validada->format('Y-m-d') !== $fecha
        ) {
            throw new InvalidArgumentException(
                'La fecha de búsqueda no es válida.'
            );
        }
    }

    $sql = 'SELECT citas.id, citas.fecha, citas.hora,
                   citas.motivo, citas.estado,
                   pacientes.tipo_documento, pacientes.numero_documento, pacientes.nombres,
                   pacientes.apellidos,
                   areas.nombre AS area
            FROM citas
            INNER JOIN pacientes
                ON pacientes.id = citas.paciente_id
            INNER JOIN areas
                ON areas.id = citas.area_id';

    $parametros = [];

    if ($fecha !== '') {
        $sql .= ' WHERE citas.fecha = :fecha';
        $parametros['fecha'] = $fecha;
    }

    $sql .= ' ORDER BY citas.fecha DESC, citas.hora ASC
              LIMIT 100';

    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}

/**
 * Registra una cita y devuelve su ID.
 * El usuario registrador debe venir de la sesión del personal.
 */
function registrar_cita(array $datos, int $registrado_por): int
{
    $paciente_id = filter_var(
        $datos['paciente_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $area_id = filter_var(
        $datos['area_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $fecha = trim($datos['fecha'] ?? '');
    $hora = trim($datos['hora'] ?? '');
    $motivo = trim($datos['motivo'] ?? '');

    if (!$paciente_id || !$area_id) {
        throw new InvalidArgumentException(
            'Selecciona un paciente y un área válidos.'
        );
    }

    $zona = new DateTimeZone('America/Lima');
    $texto_fecha = $fecha . ' ' . $hora;
    $fecha_hora = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i',
        $texto_fecha,
        $zona
    );

    if (
        !$fecha_hora
        || $fecha_hora->format('Y-m-d H:i') !== $texto_fecha
        || $fecha_hora <= new DateTimeImmutable('now', $zona)
    ) {
        throw new InvalidArgumentException(
            'Indica una fecha y hora válidas y futuras.'
        );
    }

    if (mb_strlen($motivo) > 255) {
        throw new InvalidArgumentException(
            'El motivo admite hasta 255 caracteres.'
        );
    }

    $conexion = db();

    $consulta = $conexion->prepare(
        "SELECT id FROM usuarios
         WHERE id = :id AND activo = 1
           AND rol IN ('admin', 'admision')"
    );
    $consulta->execute(['id' => $registrado_por]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException(
            'El usuario no está autorizado para registrar citas.'
        );
    }

    // Solo las áreas públicas atienden pacientes (no Admisión ni Sistemas).
    $consulta = $conexion->prepare(
        'SELECT id FROM areas WHERE id = :id AND publica = 1'
    );
    $consulta->execute(['id' => $area_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException(
            'El área seleccionada no existe o no atiende pacientes.'
        );
    }

    $conexion->beginTransaction();

    try {
        // Bloquea el paciente mientras se comprueba y guarda su cita.
        $consulta = $conexion->prepare(
            'SELECT id FROM pacientes WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $paciente_id]);

        if (!$consulta->fetch()) {
            throw new InvalidArgumentException(
                'El paciente seleccionado no existe.'
            );
        }

        $consulta = $conexion->prepare(
            "SELECT id FROM citas
             WHERE paciente_id = :paciente
               AND fecha = :fecha AND hora = :hora
               AND estado <> 'cancelada'
             LIMIT 1"
        );
        $consulta->execute([
            'paciente' => $paciente_id,
            'fecha' => $fecha,
            'hora' => $hora . ':00',
        ]);

        if ($consulta->fetch()) {
            throw new InvalidArgumentException(
                'El paciente ya tiene una cita en ese horario.'
            );
        }

        $consulta = $conexion->prepare(
            "INSERT INTO citas
                (paciente_id, area_id, fecha, hora,
                 motivo, estado, registrado_por)
             VALUES
                (:paciente, :area, :fecha, :hora,
                 :motivo, 'pendiente', :usuario)"
        );
        $consulta->execute([
            'paciente' => $paciente_id,
            'area' => $area_id,
            'fecha' => $fecha,
            'hora' => $hora . ':00',
            'motivo' => $motivo === '' ? null : $motivo,
            'usuario' => $registrado_por,
        ]);

        $id = (int) $conexion->lastInsertId();
        $conexion->commit();

        return $id;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $error;
    }
}

/**
 * Cambia el estado de una cita.
 * El usuario debe obtenerse de la sesión del personal.
 */
function cambiar_estado_cita(
    int $cita_id,
    string $nuevo_estado,
    int $usuario_id
): void {
    if ($cita_id < 1) {
        throw new InvalidArgumentException(
            'La cita no es válida.'
        );
    }

    // Estados de origen permitidos para cada destino.
    $transiciones = [
        'confirmada' => ['pendiente'],
        'atendida' => ['confirmada'],
        'cancelada' => ['pendiente', 'confirmada'],
    ];

    if (!isset($transiciones[$nuevo_estado])) {
        throw new InvalidArgumentException(
            'El estado solicitado no es válido.'
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

    $conexion->beginTransaction();

    try {
        // Bloquea la cita mientras se comprueba y actualiza.
        $consulta = $conexion->prepare(
            'SELECT estado FROM citas
             WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $cita_id]);
        $cita = $consulta->fetch();

        if (!$cita) {
            throw new InvalidArgumentException(
                'La cita no existe.'
            );
        }

        if (
            !in_array(
                $cita['estado'],
                $transiciones[$nuevo_estado],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'No se permite ese cambio desde el estado actual.'
            );
        }

        // No permite marcar como atendida una cita futura.
        if ($nuevo_estado === 'atendida') {
            $consulta = $conexion->prepare(
                'SELECT fecha, hora FROM citas WHERE id = :id'
            );
            $consulta->execute(['id' => $cita_id]);
            $horario = $consulta->fetch();

            $zona = new DateTimeZone('America/Lima');
            $fecha_cita = new DateTimeImmutable(
                $horario['fecha'] . ' ' . $horario['hora'],
                $zona
            );

            if ($fecha_cita > new DateTimeImmutable('now', $zona)) {
                throw new InvalidArgumentException(
                    'Todavía no es la fecha y hora de atención.'
                );
            }
        }

        $consulta = $conexion->prepare(
            'UPDATE citas SET estado = :estado WHERE id = :id'
        );
        $consulta->execute([
            'estado' => $nuevo_estado,
            'id' => $cita_id,
        ]);

        $conexion->commit();
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $error;
    }
}

/**
 * Asigna un médico a una cita pendiente o confirmada.
 * El usuario que realiza la acción debe venir de la sesión.
 */
function asignar_medico_cita(
    int $cita_id,
    int $medico_id,
    int $usuario_id
): void {
    if ($cita_id < 1 || $medico_id < 1) {
        throw new InvalidArgumentException(
            'Selecciona una cita y un médico válidos.'
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

    $conexion->beginTransaction();

    try {
        // Evita asignaciones simultáneas al mismo médico.
        $consulta = $conexion->prepare(
            "SELECT id, area_id FROM usuarios
             WHERE id = :id AND rol = 'medico' AND activo = 1
             FOR UPDATE"
        );
        $consulta->execute(['id' => $medico_id]);
        $medico = $consulta->fetch();

        if (!$medico) {
            throw new InvalidArgumentException(
                'El médico no existe o no está activo.'
            );
        }

        $consulta = $conexion->prepare(
            'SELECT id, area_id, fecha, hora, estado
             FROM citas WHERE id = :id FOR UPDATE'
        );
        $consulta->execute(['id' => $cita_id]);
        $cita = $consulta->fetch();

        if (!$cita) {
            throw new InvalidArgumentException(
                'La cita no existe.'
            );
        }

        if (
            !in_array(
                $cita['estado'],
                ['pendiente', 'confirmada'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Solo puedes asignar médicos a citas pendientes o confirmadas.'
            );
        }

        if ((int) $medico['area_id'] !== (int) $cita['area_id']) {
            throw new InvalidArgumentException(
                'El médico no pertenece al área de la cita.'
            );
        }

        $consulta = $conexion->prepare(
            "SELECT id FROM citas
             WHERE medico_id = :medico
               AND fecha = :fecha
               AND hora = :hora
               AND id <> :cita
               AND estado IN ('pendiente', 'confirmada')
             LIMIT 1 FOR UPDATE"
        );
        $consulta->execute([
            'medico' => $medico_id,
            'fecha' => $cita['fecha'],
            'hora' => $cita['hora'],
            'cita' => $cita_id,
        ]);

        if ($consulta->fetch()) {
            throw new InvalidArgumentException(
                'El médico ya tiene una cita en ese horario.'
            );
        }

        $consulta = $conexion->prepare(
            'UPDATE citas SET medico_id = :medico WHERE id = :cita'
        );
        $consulta->execute([
            'medico' => $medico_id,
            'cita' => $cita_id,
        ]);

        $conexion->commit();
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $error;
    }
}

/**
 * Consulta las citas de un médico en una fecha.
 * El ID del médico debe obtenerse de la sesión iniciada.
 */
function buscar_citas_medico(
    int $medico_id,
    string $fecha
): array {
    $fecha_validada = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha
    );

    if (
        !$fecha_validada
        || $fecha_validada->format('Y-m-d') !== $fecha
    ) {
        throw new InvalidArgumentException(
            'La fecha no es válida.'
        );
    }

    $conexion = db();

    $consulta = $conexion->prepare(
        "SELECT id FROM usuarios
         WHERE id = :id AND rol = 'medico' AND activo = 1"
    );
    $consulta->execute(['id' => $medico_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException(
            'El médico no existe o no está activo.'
        );
    }

    $consulta = $conexion->prepare(
        'SELECT citas.id, citas.fecha, citas.hora,
                citas.motivo, citas.estado,
                pacientes.tipo_documento, pacientes.numero_documento, pacientes.nombres,
                pacientes.apellidos,
                pacientes.fecha_nacimiento,
                areas.nombre AS area
         FROM citas
         INNER JOIN pacientes ON pacientes.id = citas.paciente_id
         INNER JOIN areas ON areas.id = citas.area_id
         WHERE citas.medico_id = :medico
           AND citas.fecha = :fecha
         ORDER BY citas.hora, citas.id'
    );

    $consulta->execute([
        'medico' => $medico_id,
        'fecha' => $fecha,
    ]);

    return $consulta->fetchAll();
}