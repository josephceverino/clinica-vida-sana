<?php
// Horarios de los médicos y huecos libres para reservar.
//
// Un "bloque" es una fila de horarios_medico: un día de la semana, una hora
// de inicio, una de fin y la duración de cada turno.
// Un "hueco" es un turno de ese bloque que todavía no tiene cita.
//   Ej.: lunes 08:00-09:00 con turnos de 20 min -> 08:00, 08:20, 08:40.
//        Si ya hay cita a las 08:20, los huecos libres son 08:00 y 08:40.
require_once __DIR__ . '/../core/db.php';

// Duraciones de turno que se pueden elegir (en minutos).
const DURACIONES_TURNO = [10, 15, 20, 30, 45, 60];

/**
 * Nombres de los días. La clave es la de date('N'): 1 = lunes ... 7 = domingo.
 */
function dias_semana(): array
{
    return [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
    ];
}

/**
 * Bloques de horario de un médico, ordenados por día y hora.
 */
function horarios_de_medico(int $medico_id): array
{
    $consulta = db()->prepare(
        'SELECT id, dia_semana, hora_inicio, hora_fin, duracion_min
         FROM horarios_medico
         WHERE medico_id = :medico
         ORDER BY dia_semana, hora_inicio'
    );
    $consulta->execute(['medico' => $medico_id]);

    return $consulta->fetchAll();
}

/**
 * Agrega un bloque de horario. Lo hace el admin.
 * $datos viene del formulario: dia_semana, hora_inicio, hora_fin, duracion_min.
 */
function agregar_horario(int $medico_id, array $datos): void
{
    foreach (['dia_semana', 'hora_inicio', 'hora_fin', 'duracion_min'] as $campo) {
        if (!isset($datos[$campo]) || !is_string($datos[$campo])) {
            throw new InvalidArgumentException('Completa todos los datos del horario.');
        }
    }

    $dia = (int) $datos['dia_semana'];
    $duracion = (int) $datos['duracion_min'];
    $inicio = trim($datos['hora_inicio']);
    $fin = trim($datos['hora_fin']);

    if (!isset(dias_semana()[$dia])) {
        throw new InvalidArgumentException('Elige un día de la semana.');
    }

    if (!in_array($duracion, DURACIONES_TURNO, true)) {
        throw new InvalidArgumentException('Elige una duración de turno válida.');
    }

    // Formato HH:MM de 00:00 a 23:59.
    $patron = '/^([01][0-9]|2[0-3]):[0-5][0-9]$/';
    if (!preg_match($patron, $inicio) || !preg_match($patron, $fin)) {
        throw new InvalidArgumentException('Escribe las horas en formato HH:MM.');
    }

    // "08:00" < "13:00" funciona como texto porque tienen el mismo formato.
    if ($inicio >= $fin) {
        throw new InvalidArgumentException('La hora de fin debe ser posterior a la de inicio.');
    }

    $conexion = db();

    $consulta = $conexion->prepare(
        "SELECT id FROM usuarios WHERE id = :id AND rol = 'medico' AND activo = 1"
    );
    $consulta->execute(['id' => $medico_id]);
    if (!$consulta->fetch()) {
        throw new InvalidArgumentException('El médico no existe o no está activo.');
    }

    // No puede chocar con otro bloque del mismo médico el mismo día.
    // Dos bloques chocan si uno empieza antes de que termine el otro.
    $consulta = $conexion->prepare(
        'SELECT id FROM horarios_medico
         WHERE medico_id = :medico AND dia_semana = :dia
           AND hora_inicio < :fin AND hora_fin > :inicio
         LIMIT 1'
    );
    $consulta->execute([
        'medico' => $medico_id,
        'dia'    => $dia,
        'inicio' => $inicio . ':00',
        'fin'    => $fin . ':00',
    ]);
    if ($consulta->fetch()) {
        throw new InvalidArgumentException(
            'Ese bloque se cruza con otro horario del mismo día.'
        );
    }

    $consulta = $conexion->prepare(
        'INSERT INTO horarios_medico (medico_id, dia_semana, hora_inicio, hora_fin, duracion_min)
         VALUES (:medico, :dia, :inicio, :fin, :duracion)'
    );
    $consulta->execute([
        'medico'   => $medico_id,
        'dia'      => $dia,
        'inicio'   => $inicio . ':00',
        'fin'      => $fin . ':00',
        'duracion' => $duracion,
    ]);
}

/**
 * Borra un bloque de horario de un médico. Las citas ya reservadas NO se
 * tocan: solo deja de ofrecer huecos nuevos en ese horario.
 */
function eliminar_horario(int $horario_id, int $medico_id): void
{
    $consulta = db()->prepare(
        'DELETE FROM horarios_medico WHERE id = :id AND medico_id = :medico'
    );
    $consulta->execute(['id' => $horario_id, 'medico' => $medico_id]);

    if ($consulta->rowCount() === 0) {
        throw new InvalidArgumentException('Ese horario no existe.');
    }
}

/**
 * Turnos que salen de un bloque: de inicio a fin, de "duración" en
 * "duración". El último turno debe terminar antes (o justo) del fin.
 * Ej.: 08:00-09:00 cada 20 min -> ['08:00', '08:20', '08:40'].
 */
function turnos_de_bloque(string $inicio, string $fin, int $duracion): array
{
    $turnos = [];
    $momento = strtotime('2000-01-01 ' . $inicio);
    $limite = strtotime('2000-01-01 ' . $fin);

    while ($momento + $duracion * 60 <= $limite) {
        $turnos[] = date('H:i', $momento);
        $momento += $duracion * 60;
    }

    return $turnos;
}

/**
 * Huecos libres de UN médico en una fecha (Y-m-d): sus turnos de ese día de
 * la semana, menos los que ya tienen cita vigente, menos los que ya pasaron.
 * Devuelve las horas como ['08:00', '08:40', ...].
 */
function huecos_libres(int $medico_id, string $fecha): array
{
    $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    if (!$dia || $dia->format('Y-m-d') !== $fecha) {
        return [];
    }

    // Todos los turnos del médico ese día de la semana.
    $consulta = db()->prepare(
        'SELECT hora_inicio, hora_fin, duracion_min
         FROM horarios_medico
         WHERE medico_id = :medico AND dia_semana = :dia
         ORDER BY hora_inicio'
    );
    $consulta->execute(['medico' => $medico_id, 'dia' => (int) $dia->format('N')]);

    $turnos = [];
    foreach ($consulta->fetchAll() as $bloque) {
        $turnos = array_merge(
            $turnos,
            turnos_de_bloque($bloque['hora_inicio'], $bloque['hora_fin'], (int) $bloque['duracion_min'])
        );
    }

    if ($turnos === []) {
        return [];
    }

    // Horas que ya están ocupadas por una cita vigente.
    $consulta = db()->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') FROM citas
         WHERE medico_id = :medico AND fecha = :fecha
           AND estado IN ('pendiente', 'confirmada')"
    );
    $consulta->execute(['medico' => $medico_id, 'fecha' => $fecha]);
    $ocupadas = $consulta->fetchAll(PDO::FETCH_COLUMN);

    $libres = array_diff($turnos, $ocupadas);

    // Si es hoy, solo los turnos que aún no empiezan.
    if ($fecha === date('Y-m-d')) {
        $ahora = date('H:i');
        $libres = array_filter($libres, fn ($hora) => $hora > $ahora);
    }

    return array_values($libres);
}

/**
 * Huecos libres de todos los médicos de un área en una fecha.
 * Devuelve: [['medico_id' => 3, 'medico' => 'Dr. ...', 'huecos' => ['08:00', ...]], ...]
 * Solo incluye médicos con al menos un hueco.
 */
function huecos_por_area(int $area_id, string $fecha): array
{
    $consulta = db()->prepare(
        "SELECT id, nombre FROM usuarios
         WHERE rol = 'medico' AND activo = 1 AND area_id = :area
         ORDER BY nombre"
    );
    $consulta->execute(['area' => $area_id]);

    $resultado = [];
    foreach ($consulta->fetchAll() as $medico) {
        $huecos = huecos_libres((int) $medico['id'], $fecha);
        if ($huecos !== []) {
            $resultado[] = [
                'medico_id' => (int) $medico['id'],
                'medico'    => $medico['nombre'],
                'huecos'    => $huecos,
            ];
        }
    }

    return $resultado;
}

// Máximo de citas futuras vigentes que un paciente puede tener reservadas a
// la vez. Evita que alguien acapare horarios que otros necesitan.
const MAX_CITAS_ACTIVAS_POR_PACIENTE = 3;

// Hasta cuántos días adelante se puede reservar.
const DIAS_RESERVA_MAXIMO = 60;

/**
 * El paciente reserva un hueco libre. La cita queda CONFIRMADA al momento
 * (el hueco es real y la cuenta ya está verificada). Devuelve el ID de la cita.
 *
 * $paciente_id y $usuario_id salen de la SESIÓN. Lo demás viene del
 * formulario y se valida aquí (regla 8).
 */
function reservar_hueco(int $paciente_id, int $usuario_id, array $datos): int
{
    foreach (['area_id', 'fecha', 'hueco', 'motivo'] as $campo) {
        if (!isset($datos[$campo]) || !is_string($datos[$campo])) {
            throw new InvalidArgumentException('Elige un horario y escribe el motivo.');
        }
    }

    $area_id = (int) $datos['area_id'];
    $fecha = trim($datos['fecha']);
    $motivo = trim($datos['motivo']);

    // El hueco llega como "medico_id|hora", ej. "3|08:40".
    if (!preg_match('/^([0-9]+)\|([0-2][0-9]:[0-5][0-9])$/', $datos['hueco'], $partes)) {
        throw new InvalidArgumentException('Elige un horario de la lista.');
    }
    $medico_id = (int) $partes[1];
    $hora = $partes[2];

    if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 255) {
        throw new InvalidArgumentException('Cuéntanos el motivo de la consulta (de 5 a 255 caracteres).');
    }

    $hoy = date('Y-m-d');
    $limite = date('Y-m-d', strtotime('+' . DIAS_RESERVA_MAXIMO . ' days'));
    if ($fecha < $hoy || $fecha > $limite) {
        throw new InvalidArgumentException('Elige una fecha de hoy a ' . DIAS_RESERVA_MAXIMO . ' días.');
    }

    $conexion = db();
    $conexion->beginTransaction();

    try {
        // 1. Bloquear al médico (debe ser del área elegida). Mientras dure
        //    la transacción, nadie más puede reservar con él: así dos
        //    pacientes no se quedan con el mismo hueco.
        $consulta = $conexion->prepare(
            "SELECT id FROM usuarios
             WHERE id = :id AND rol = 'medico' AND activo = 1 AND area_id = :area
             FOR UPDATE"
        );
        $consulta->execute(['id' => $medico_id, 'area' => $area_id]);
        if (!$consulta->fetch()) {
            throw new InvalidArgumentException('Ese médico no atiende en el área elegida.');
        }

        // 2. El hueco sigue libre (se vuelve a calcular, ya con el bloqueo).
        if (!in_array($hora, huecos_libres($medico_id, $fecha), true)) {
            throw new InvalidArgumentException(
                'Ese horario acaba de ser reservado o ya no está disponible. Elige otro.'
            );
        }

        // 3. Bloquear la ficha del paciente y revisar sus citas.
        $consulta = $conexion->prepare('SELECT id FROM pacientes WHERE id = :id FOR UPDATE');
        $consulta->execute(['id' => $paciente_id]);
        if (!$consulta->fetch()) {
            throw new InvalidArgumentException('No se pudo identificar al paciente.');
        }

        $consulta = $conexion->prepare(
            "SELECT
                SUM(fecha = :fecha AND hora = :hora) AS misma_hora,
                COUNT(*) AS activas
             FROM citas
             WHERE paciente_id = :paciente
               AND estado IN ('pendiente', 'confirmada')
               AND fecha >= CURDATE()"
        );
        $consulta->execute([
            'fecha'    => $fecha,
            'hora'     => $hora . ':00',
            'paciente' => $paciente_id,
        ]);
        $citas = $consulta->fetch();

        if ((int) $citas['misma_hora'] > 0) {
            throw new InvalidArgumentException('Ya tienes otra cita a esa misma hora.');
        }

        if ((int) $citas['activas'] >= MAX_CITAS_ACTIVAS_POR_PACIENTE) {
            throw new InvalidArgumentException(
                'Ya tienes ' . MAX_CITAS_ACTIVAS_POR_PACIENTE . ' citas reservadas. '
                . 'Espera a que pase alguna o cancela una para reservar otra.'
            );
        }

        // 4. Crear la cita confirmada.
        $consulta = $conexion->prepare(
            "INSERT INTO citas
                (paciente_id, area_id, medico_id, fecha, hora, motivo, estado, registrado_por)
             VALUES
                (:paciente, :area, :medico, :fecha, :hora, :motivo, 'confirmada', :usuario)"
        );
        $consulta->execute([
            'paciente' => $paciente_id,
            'area'     => $area_id,
            'medico'   => $medico_id,
            'fecha'    => $fecha,
            'hora'     => $hora . ':00',
            'motivo'   => $motivo,
            'usuario'  => $usuario_id,
        ]);
        $cita_id = (int) $conexion->lastInsertId();

        $conexion->commit();

        return $cita_id;
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        throw $error;
    }
}
