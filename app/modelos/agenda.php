<?php
// Datos para el resumen del médico y la agenda semanal.
// Solo LEE datos; nunca los cambia. El ID del médico sale siempre de la
// sesión (o, para admin y admisión, de una lista validada en la página).
require_once __DIR__ . '/../core/db.php';

/**
 * Números del resumen de un médico.
 * Devuelve: hoy_total, hoy_por_atender, semana_total, mes_atendidas,
 *           mes_pacientes.
 */
function resumen_medico(int $medico_id): array
{
    $consulta = db()->prepare(
        "SELECT
            -- Citas de hoy (sin contar las canceladas)
            SUM(fecha = CURDATE() AND estado <> 'cancelada') AS hoy_total,
            -- De hoy, las que aún esperan ser atendidas
            SUM(fecha = CURDATE() AND estado = 'confirmada') AS hoy_por_atender,
            -- De hoy a 6 días más, vigentes
            SUM(fecha BETWEEN CURDATE() AND CURDATE() + INTERVAL 6 DAY
                AND estado IN ('pendiente', 'confirmada')) AS semana_total
         FROM citas
         WHERE medico_id = :medico"
    );
    $consulta->execute(['medico' => $medico_id]);
    $citas = $consulta->fetch();

    // Atenciones de este mes (lo que de verdad atendió) y a cuántas personas.
    $consulta = db()->prepare(
        "SELECT COUNT(*) AS mes_atendidas,
                COUNT(DISTINCT paciente_id) AS mes_pacientes
         FROM atenciones
         WHERE medico_id = :medico
           AND creado_en >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
    );
    $consulta->execute(['medico' => $medico_id]);
    $mes = $consulta->fetch();

    return [
        'hoy_total'       => (int) $citas['hoy_total'],
        'hoy_por_atender' => (int) $citas['hoy_por_atender'],
        'semana_total'    => (int) $citas['semana_total'],
        'mes_atendidas'   => (int) $mes['mes_atendidas'],
        'mes_pacientes'   => (int) $mes['mes_pacientes'],
    ];
}

/**
 * Cuántas atenciones registró el médico en cada uno de los últimos $meses
 * meses (incluido el actual). Los meses sin atenciones salen con 0, para
 * que el gráfico no tenga huecos.
 * Devuelve: [['mes' => '2026-11', 'nombre' => 'nov', 'total' => 3], ...]
 */
function atenciones_por_mes(int $medico_id, int $meses = 6): array
{
    $desde = date('Y-m-01', strtotime('first day of -' . ($meses - 1) . ' months'));

    $consulta = db()->prepare(
        "SELECT DATE_FORMAT(creado_en, '%Y-%m') AS mes, COUNT(*) AS total
         FROM atenciones
         WHERE medico_id = :medico AND creado_en >= :desde
         GROUP BY mes"
    );
    $consulta->execute(['medico' => $medico_id, 'desde' => $desde]);

    // ['2026-10' => 2, '2026-11' => 1]
    $por_mes = array_column($consulta->fetchAll(), 'total', 'mes');

    $nombres = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun',
                'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    $resultado = [];
    for ($i = $meses - 1; $i >= 0; $i--) {
        $momento = strtotime('first day of -' . $i . ' months');
        $clave = date('Y-m', $momento);
        $resultado[] = [
            'mes'    => $clave,
            'nombre' => $nombres[(int) date('n', $momento)],
            'total'  => (int) ($por_mes[$clave] ?? 0),
        ];
    }

    return $resultado;
}

/**
 * Lunes de la semana de una fecha (Y-m-d). Si la fecha no es válida,
 * devuelve el lunes de esta semana.
 */
function lunes_de_semana(string $fecha): string
{
    $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

    if (!$dia || $dia->format('Y-m-d') !== $fecha) {
        $dia = new DateTimeImmutable('today');
    }

    // 'N' = 1 (lunes) ... 7 (domingo). Se retroceden los días necesarios.
    return $dia->modify('-' . ((int) $dia->format('N') - 1) . ' days')->format('Y-m-d');
}

/**
 * Citas de la semana que empieza en $lunes (7 días), sin las canceladas.
 * Si $medico_id es null, trae las de todos los médicos (admin y admisión).
 */
function citas_de_semana(string $lunes, ?int $medico_id): array
{
    $sql = "SELECT citas.id, citas.fecha, citas.hora, citas.estado, citas.motivo,
                   CONCAT(pacientes.nombres, ' ', pacientes.apellidos) AS paciente,
                   areas.nombre AS area,
                   medico.nombre AS medico
            FROM citas
            INNER JOIN pacientes ON pacientes.id = citas.paciente_id
            INNER JOIN areas ON areas.id = citas.area_id
            LEFT JOIN usuarios AS medico ON medico.id = citas.medico_id
            WHERE citas.fecha BETWEEN :lunes AND :lunes2 + INTERVAL 6 DAY
              AND citas.estado <> 'cancelada'";

    $parametros = ['lunes' => $lunes, 'lunes2' => $lunes];

    // El filtro se agrega como texto fijo; el ID va como parámetro.
    if ($medico_id !== null) {
        $sql .= ' AND citas.medico_id = :medico';
        $parametros['medico'] = $medico_id;
    }

    $sql .= ' ORDER BY citas.fecha, citas.hora';

    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}
