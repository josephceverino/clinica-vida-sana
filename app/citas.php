<?php

require_once __DIR__ . '/db.php';

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
                   pacientes.dni, pacientes.nombres,
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

    $consulta = $conexion->prepare(
        'SELECT id FROM areas WHERE id = :id'
    );
    $consulta->execute(['id' => $area_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException(
            'El área seleccionada no existe.'
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