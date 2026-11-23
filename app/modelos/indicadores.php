<?php
// Indicadores administrativos (panel/indicadores.php).
//
// SOLO LEE la base de datos: todas las consultas son SELECT con COUNT/SUM
// sobre citas, solicitudes_cita, atenciones y areas. No devuelve nombres
// de pacientes, documentos, diagnósticos ni ningún dato clínico: solo
// cantidades por estado, por mes y por especialidad (nombre del área).
//
// Cada función recibe la conexión ($pdo) para que las pruebas puedan usar
// una base aparte (app/pruebas/indicadores_prueba.php). Las fórmulas están
// documentadas en docs/indicadores.md.
require_once __DIR__ . '/../core/db.php';

// Rango máximo de un período personalizado (días) y meses que dibuja el
// gráfico de evolución. Mantienen las consultas y el gráfico livianos.
const INDICADORES_DIAS_MAXIMOS = 731;   // unos 24 meses
const INDICADORES_MESES_GRAFICO = 24;

/**
 * Estados de una cita, en el orden del ENUM de la tabla citas.
 */
function indicadores_estados_cita(): array
{
    return [
        'pendiente'  => 'Pendiente',
        'confirmada' => 'Confirmada',
        'atendida'   => 'Atendida',
        'cancelada'  => 'Cancelada',
        'no_asistio' => 'No asistió',
    ];
}

/**
 * Períodos rápidos: clave => texto del botón.
 */
function indicadores_periodos(): array
{
    return [
        'mes'   => 'Este mes',
        '3m'    => 'Últimos 3 meses',
        '6m'    => 'Últimos 6 meses',
        'anio'  => 'Este año',
        'todo'  => 'Todo',
    ];
}

/**
 * Convierte lo que llegó por GET en un período válido:
 *   ['clave', 'desde' => 'Y-m-d', 'hasta' => 'Y-m-d', 'error' => ?string]
 * Las fechas se validan aquí (nunca se meten sin revisar en el SQL: además
 * van como parámetros de consultas preparadas). $hoy se puede fijar en
 * las pruebas.
 */
function indicadores_periodo(PDO $pdo, array $get, ?string $hoy = null): array
{
    $hoy = $hoy ?? date('Y-m-d');
    $clave = is_string($get['periodo'] ?? null) ? $get['periodo'] : '6m';
    $inicio_mes = date('Y-m-01', strtotime($hoy));
    $fin_mes = date('Y-m-t', strtotime($hoy));
    $error = null;

    if ($clave === 'personalizado') {
        $desde = indicadores_fecha_valida($get['desde'] ?? null);
        $hasta = indicadores_fecha_valida($get['hasta'] ?? null);

        if ($desde === null || $hasta === null) {
            $error = 'Escribe las dos fechas del período (desde y hasta).';
        } elseif ($desde > $hasta) {
            $error = 'La fecha "desde" debe ser anterior o igual a la fecha "hasta".';
        } elseif ((strtotime($hasta) - strtotime($desde)) / 86400 > INDICADORES_DIAS_MAXIMOS) {
            $error = 'El período puede abarcar como máximo 24 meses.';
        } else {
            return ['clave' => $clave, 'desde' => $desde, 'hasta' => $hasta, 'error' => null];
        }
        $clave = '6m';   // con error: se muestra el período por defecto
    }

    switch ($clave) {
        case 'mes':
            return ['clave' => 'mes', 'desde' => $inicio_mes, 'hasta' => $fin_mes, 'error' => $error];
        case '3m':
            return ['clave' => '3m', 'desde' => date('Y-m-01', strtotime($inicio_mes . ' -2 months')),
                    'hasta' => $fin_mes, 'error' => $error];
        case 'anio':
            return ['clave' => 'anio', 'desde' => date('Y-01-01', strtotime($hoy)),
                    'hasta' => date('Y-12-31', strtotime($hoy)), 'error' => $error];
        case 'todo':
            // De la primera a la última fecha que existe en citas o solicitudes.
            $fila = $pdo->query(
                "SELECT LEAST(COALESCE((SELECT MIN(fecha) FROM citas), CURDATE()),
                              COALESCE((SELECT MIN(DATE(creado_en)) FROM solicitudes_cita), CURDATE())) AS desde,
                        GREATEST(COALESCE((SELECT MAX(fecha) FROM citas), CURDATE()),
                                 COALESCE((SELECT MAX(DATE(creado_en)) FROM solicitudes_cita), CURDATE())) AS hasta"
            )->fetch();
            return ['clave' => 'todo', 'desde' => $fila['desde'], 'hasta' => $fila['hasta'], 'error' => $error];
        default:
            return ['clave' => '6m', 'desde' => date('Y-m-01', strtotime($inicio_mes . ' -5 months')),
                    'hasta' => $fin_mes, 'error' => $error];
    }
}

/**
 * Devuelve la fecha si es una fecha real en formato AAAA-MM-DD; si no, null.
 */
function indicadores_fecha_valida($valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : null;
}

/**
 * Citas del período por estado. Período = fecha de la cita (citas.fecha).
 * Devuelve los 5 estados (con 0 si no hay) y el total.
 */
function indicadores_citas_por_estado(PDO $pdo, string $desde, string $hasta): array
{
    $consulta = $pdo->prepare(
        "SELECT estado, COUNT(*) AS total
         FROM citas
         WHERE fecha BETWEEN :desde AND :hasta
         GROUP BY estado"
    );
    $consulta->execute(['desde' => $desde, 'hasta' => $hasta]);

    $estados = array_fill_keys(array_keys(indicadores_estados_cita()), 0);
    foreach ($consulta->fetchAll() as $fila) {
        // Un estado que no conocemos (agregado al ENUM después) igual se cuenta.
        $estados[$fila['estado']] = (int) $fila['total'];
    }

    return ['estados' => $estados, 'total' => array_sum($estados)];
}

/**
 * Citas que ya pasaron (fecha anterior a hoy) y siguen pendientes o
 * confirmadas: nadie marcó si se atendieron o no. Sirve para que admisión
 * las cierre. Solo cuenta dentro del período.
 */
function indicadores_citas_sin_cerrar(PDO $pdo, string $desde, string $hasta): int
{
    $consulta = $pdo->prepare(
        "SELECT COUNT(*)
         FROM citas
         WHERE fecha BETWEEN :desde AND :hasta
           AND fecha < CURDATE()
           AND estado IN ('pendiente', 'confirmada')"
    );
    $consulta->execute(['desde' => $desde, 'hasta' => $hasta]);
    return (int) $consulta->fetchColumn();
}

/**
 * Solicitudes del período por estado. Período = día en que el paciente la
 * envió (DATE(solicitudes_cita.creado_en)).
 *   en_revision = nueva;  resueltas = atendida + descartada.
 */
function indicadores_solicitudes(PDO $pdo, string $desde, string $hasta): array
{
    $consulta = $pdo->prepare(
        "SELECT estado, COUNT(*) AS total
         FROM solicitudes_cita
         WHERE creado_en >= :desde AND creado_en < DATE_ADD(:hasta, INTERVAL 1 DAY)
         GROUP BY estado"
    );
    $consulta->execute(['desde' => $desde, 'hasta' => $hasta]);

    $estados = ['nueva' => 0, 'atendida' => 0, 'descartada' => 0];
    foreach ($consulta->fetchAll() as $fila) {
        $estados[$fila['estado']] = (int) $fila['total'];
    }

    return [
        'en_revision' => $estados['nueva'],
        'programadas' => $estados['atendida'],
        'descartadas' => $estados['descartada'],
        'resueltas'   => $estados['atendida'] + $estados['descartada'],
        'total'       => array_sum($estados),
    ];
}

/**
 * Atenciones registradas (historia clínica) por especialidad.
 * Período = fecha de la cita atendida. Solo devuelve el NOMBRE del área y
 * la cantidad: nada del paciente ni del diagnóstico.
 * Incluye las áreas públicas sin atenciones (con 0) para que se vea el hueco.
 */
function indicadores_atenciones_por_area(PDO $pdo, string $desde, string $hasta): array
{
    $consulta = $pdo->prepare(
        "SELECT ar.nombre, COUNT(at.id) AS total
         FROM areas ar
         LEFT JOIN citas c
                ON c.area_id = ar.id AND c.fecha BETWEEN :desde AND :hasta
         LEFT JOIN atenciones at
                ON at.cita_id = c.id
         WHERE ar.publica = 1 OR at.id IS NOT NULL
         GROUP BY ar.id, ar.nombre
         ORDER BY total DESC, ar.nombre"
    );
    $consulta->execute(['desde' => $desde, 'hasta' => $hasta]);

    $filas = [];
    foreach ($consulta->fetchAll() as $fila) {
        $filas[$fila['nombre']] = (int) $fila['total'];
    }

    return $filas;
}

/**
 * Evolución mensual: citas programadas (todas, por citas.fecha) y
 * atenciones registradas (por la fecha de su cita), mes a mes.
 * Devuelve ['AAAA-MM' => ['citas' => n, 'atenciones' => n], ...] con todos
 * los meses del período (0 si no hubo nada), como máximo los últimos
 * INDICADORES_MESES_GRAFICO meses.
 */
function indicadores_evolucion_mensual(PDO $pdo, string $desde, string $hasta): array
{
    $meses = [];
    $mes = new DateTimeImmutable(substr($desde, 0, 7) . '-01');
    $ultimo = new DateTimeImmutable(substr($hasta, 0, 7) . '-01');
    while ($mes <= $ultimo) {
        $meses[$mes->format('Y-m')] = ['citas' => 0, 'atenciones' => 0];
        $mes = $mes->modify('+1 month');
    }
    $meses = array_slice($meses, -INDICADORES_MESES_GRAFICO, null, true);

    $consulta = $pdo->prepare(
        "SELECT DATE_FORMAT(c.fecha, '%Y-%m') AS mes,
                COUNT(*)       AS citas,
                COUNT(at.id)   AS atenciones
         FROM citas c
         LEFT JOIN atenciones at ON at.cita_id = c.id
         WHERE c.fecha BETWEEN :desde AND :hasta
         GROUP BY mes
         ORDER BY mes"
    );
    $consulta->execute(['desde' => $desde, 'hasta' => $hasta]);

    foreach ($consulta->fetchAll() as $fila) {
        if (isset($meses[$fila['mes']])) {
            $meses[$fila['mes']] = ['citas' => (int) $fila['citas'], 'atenciones' => (int) $fila['atenciones']];
        }
    }

    return $meses;
}

/**
 * Porcentaje con un decimal, o null si el total es 0 (no se inventa un 0 %).
 */
function indicadores_porcentaje(int $parte, int $total): ?float
{
    return $total > 0 ? round($parte * 100 / $total, 1) : null;
}

/**
 * Todos los indicadores del período juntos (lo que usa la página).
 */
function indicadores_resumen(PDO $pdo, string $desde, string $hasta): array
{
    $citas = indicadores_citas_por_estado($pdo, $desde, $hasta);
    $por_area = indicadores_atenciones_por_area($pdo, $desde, $hasta);

    return [
        'citas'       => $citas,
        'sin_cerrar'  => indicadores_citas_sin_cerrar($pdo, $desde, $hasta),
        'solicitudes' => indicadores_solicitudes($pdo, $desde, $hasta),
        'por_area'    => $por_area,
        'atenciones'  => array_sum($por_area),
        'mensual'     => indicadores_evolucion_mensual($pdo, $desde, $hasta),
    ];
}
