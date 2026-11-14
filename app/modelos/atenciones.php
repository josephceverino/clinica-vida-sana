<?php
// Historia clínica: las atenciones que el médico registra al atender una cita.
//
// Quién puede qué:
//   - ESCRIBIR: solo el médico asignado a esa cita (registrar_atencion).
//   - LEER: el paciente dueño (atenciones_del_paciente) y el médico que la
//     escribió (cita_del_medico). Admisión y admin NO leen datos clínicos.
require_once __DIR__ . '/../core/db.php';

/**
 * Atenciones de UN paciente, de la más nueva a la más antigua
 * (para "Historia clínica" en su portal).
 * $paciente_id debe salir siempre de la sesión, nunca de un formulario.
 */
function atenciones_del_paciente(int $paciente_id): array
{
    $consulta = db()->prepare(
        'SELECT atenciones.id, atenciones.motivo, atenciones.diagnostico,
                atenciones.tratamiento, atenciones.notas,
                citas.fecha, citas.hora,
                areas.nombre AS area,
                medico.nombre AS medico
         FROM atenciones
         INNER JOIN citas
             ON citas.id = atenciones.cita_id
         INNER JOIN areas
             ON areas.id = citas.area_id
         INNER JOIN usuarios AS medico
             ON medico.id = atenciones.medico_id
         WHERE atenciones.paciente_id = :paciente
         ORDER BY citas.fecha DESC, citas.hora DESC
         LIMIT 100'
    );
    $consulta->execute(['paciente' => $paciente_id]);

    return $consulta->fetchAll();
}

/**
 * Una cita, SOLO si está asignada a ese médico. Trae los datos básicos del
 * paciente y, si ya fue atendida, su atención.
 * Devuelve null si la cita no existe o es de otro médico (no se distingue,
 * para no revelar qué citas existen).
 */
function cita_del_medico(int $cita_id, int $medico_id): ?array
{
    $consulta = db()->prepare(
        'SELECT citas.id, citas.fecha, citas.hora, citas.motivo,
                citas.estado, citas.paciente_id,
                areas.nombre AS area,
                pacientes.nombres, pacientes.apellidos,
                pacientes.tipo_documento, pacientes.numero_documento,
                pacientes.fecha_nacimiento,
                atenciones.diagnostico, atenciones.tratamiento,
                atenciones.notas, atenciones.creado_en AS atendida_en
         FROM citas
         INNER JOIN areas
             ON areas.id = citas.area_id
         INNER JOIN pacientes
             ON pacientes.id = citas.paciente_id
         LEFT JOIN atenciones
             ON atenciones.cita_id = citas.id
         WHERE citas.id = :cita AND citas.medico_id = :medico'
    );
    $consulta->execute(['cita' => $cita_id, 'medico' => $medico_id]);
    $cita = $consulta->fetch();

    return $cita === false ? null : $cita;
}

/**
 * ¿Ya llegó la fecha y hora de la cita? (hora de Lima)
 */
function cita_ya_llego(string $fecha, string $hora): bool
{
    $zona = new DateTimeZone('America/Lima');

    return new DateTimeImmutable($fecha . ' ' . $hora, $zona)
        <= new DateTimeImmutable('now', $zona);
}

/**
 * Registra la atención de una cita y la marca como atendida. Devuelve el ID.
 *
 * Reglas (se comprueban aquí, no en la página):
 *   - $medico_id sale de la SESIÓN, y esa cuenta debe ser un médico activo.
 *   - La cita debe estar asignada a ESE médico.
 *   - La cita debe estar confirmada y su hora debe haber llegado.
 *   - El paciente se toma de la CITA, nunca del formulario.
 *   - Guardar la atención y marcar la cita se hace todo junto (transacción).
 */
function registrar_atencion(int $cita_id, int $medico_id, array $datos): int
{
    if ($cita_id < 1 || $medico_id < 1) {
        throw new InvalidArgumentException('La cita no es válida.');
    }

    foreach (['diagnostico', 'tratamiento', 'notas'] as $campo) {
        if (isset($datos[$campo]) && !is_scalar($datos[$campo])) {
            throw new InvalidArgumentException(
                'Los datos enviados no son válidos.'
            );
        }
    }

    $diagnostico = trim((string) ($datos['diagnostico'] ?? ''));
    $tratamiento = trim((string) ($datos['tratamiento'] ?? ''));
    $notas       = trim((string) ($datos['notas'] ?? ''));

    if (mb_strlen($diagnostico) < 5 || mb_strlen($diagnostico) > 500) {
        throw new InvalidArgumentException(
            'El diagnóstico es obligatorio (de 5 a 500 caracteres).'
        );
    }
    if (mb_strlen($tratamiento) > 500) {
        throw new InvalidArgumentException(
            'El tratamiento admite hasta 500 caracteres.'
        );
    }
    if (mb_strlen($notas) > 1000) {
        throw new InvalidArgumentException(
            'Las notas admiten hasta 1000 caracteres.'
        );
    }

    $conexion = db();

    // Quien escribe debe ser un médico activo.
    $consulta = $conexion->prepare(
        "SELECT id FROM usuarios
         WHERE id = :id AND activo = 1 AND rol = 'medico'"
    );
    $consulta->execute(['id' => $medico_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException('El usuario no está autorizado.');
    }

    $conexion->beginTransaction();

    try {
        // Bloquea la cita mientras se comprueba y se guarda.
        $consulta = $conexion->prepare(
            'SELECT paciente_id, fecha, hora, motivo, estado
             FROM citas
             WHERE id = :cita AND medico_id = :medico
             FOR UPDATE'
        );
        $consulta->execute(['cita' => $cita_id, 'medico' => $medico_id]);
        $cita = $consulta->fetch();

        if (!$cita) {
            throw new InvalidArgumentException(
                'La cita no existe o no está asignada a ti.'
            );
        }

        if ($cita['estado'] === 'atendida') {
            throw new InvalidArgumentException('Esta cita ya fue atendida.');
        }

        if ($cita['estado'] !== 'confirmada') {
            throw new InvalidArgumentException(
                'Solo se puede atender una cita confirmada.'
            );
        }

        if (!cita_ya_llego($cita['fecha'], $cita['hora'])) {
            throw new InvalidArgumentException(
                'Todavía no es la fecha y hora de la cita.'
            );
        }

        $insertar = $conexion->prepare(
            'INSERT INTO atenciones
                (cita_id, paciente_id, medico_id, motivo,
                 diagnostico, tratamiento, notas)
             VALUES
                (:cita, :paciente, :medico, :motivo,
                 :diagnostico, :tratamiento, :notas)'
        );
        $insertar->execute([
            'cita'        => $cita_id,
            'paciente'    => (int) $cita['paciente_id'],
            'medico'      => $medico_id,
            'motivo'      => $cita['motivo'],
            'diagnostico' => $diagnostico,
            'tratamiento' => $tratamiento === '' ? null : $tratamiento,
            'notas'       => $notas === '' ? null : $notas,
        ]);
        $atencion_id = (int) $conexion->lastInsertId();

        $actualizar = $conexion->prepare(
            "UPDATE citas SET estado = 'atendida'
             WHERE id = :cita AND estado = 'confirmada'"
        );
        $actualizar->execute(['cita' => $cita_id]);

        if ($actualizar->rowCount() !== 1) {
            throw new InvalidArgumentException(
                'No se pudo marcar la cita como atendida.'
            );
        }

        $conexion->commit();

        return $atencion_id;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $error;
    }
}
