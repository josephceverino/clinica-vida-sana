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
