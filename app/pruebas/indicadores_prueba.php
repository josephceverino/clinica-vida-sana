<?php
// Pruebas de los indicadores (app/modelos/indicadores.php). Solo terminal:
//
//     php app/pruebas/indicadores_prueba.php [base_de_pruebas]
//
// Usa una base APARTE (por defecto: cvs_pruebas_indicadores) que se crea
// con sql/schema.sql y se BORRA al final. Nunca toca clinica_vida_sana.
// Usuario y contraseña de MariaDB: los de config (config.php o el ejemplo).
//
// Qué comprueba:
//   1. Base vacía: todo en 0 y sin porcentajes inventados.
//   2. Datos controlados (cantidades conocidas) -> cifras exactas.
//   3. Consistencia: total = suma de estados; atenciones por área = total;
//      la evolución mensual suma lo mismo que el período.
//   4. Bordes del período (extremos incluidos, fuera de rango excluido).
//   5. Valores extremos: 5 000 citas en un mes, tiempo de respuesta.
//   6. Validación de períodos personalizados.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../modelos/indicadores.php';

$base = $argv[1] ?? 'cvs_pruebas_indicadores';
if (preg_match('/^cvs_pruebas_[a-z_]+$/', $base) !== 1) {
    exit("El nombre de la base de pruebas debe empezar con cvs_pruebas_ (por seguridad).\n");
}

$fallos = 0;
$total = 0;
function comprobar(string $nombre, bool $resultado): void
{
    global $fallos, $total;
    $total++;
    $fallos += $resultado ? 0 : 1;
    echo ($resultado ? '  OK    ' : '  FALLA ') . $nombre . PHP_EOL;
}

// --- Crear la base de pruebas desde schema.sql ------------------------------
$datos_db = config('db');
$admin = new PDO('mysql:host=' . $datos_db['host'] . ';charset=utf8mb4', $datos_db['usuario'], $datos_db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$admin->exec('DROP DATABASE IF EXISTS `' . $base . '`');
$esquema = str_replace('clinica_vida_sana', $base, file_get_contents(__DIR__ . '/../../sql/schema.sql'));
// Se quitan los comentarios "--" y se ejecuta sentencia por sentencia.
$esquema = preg_replace('/^\s*--.*$/m', '', $esquema);
foreach (array_filter(array_map('trim', explode(';', $esquema))) as $sentencia) {
    $admin->exec($sentencia);
}

$pdo = new PDO('mysql:host=' . $datos_db['host'] . ';dbname=' . $base . ';charset=utf8mb4',
    $datos_db['usuario'], $datos_db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
$pdo->exec("SET time_zone = '-05:00'");

try {
    echo "1. Base vacía\n";
    $r = indicadores_resumen($pdo, '2026-01-01', '2026-12-31');
    comprobar('0 citas', $r['citas']['total'] === 0);
    comprobar('los 5 estados existen y valen 0', $r['citas']['estados'] === [
        'pendiente' => 0, 'confirmada' => 0, 'atendida' => 0, 'cancelada' => 0, 'no_asistio' => 0]);
    comprobar('0 solicitudes', $r['solicitudes']['total'] === 0 && $r['solicitudes']['resueltas'] === 0);
    comprobar('0 atenciones', $r['atenciones'] === 0);
    comprobar('12 meses en cero', count($r['mensual']) === 12 && array_sum(array_column($r['mensual'], 'citas')) === 0);
    comprobar('porcentaje con total 0 es null (no 0 %)', indicadores_porcentaje(0, 0) === null);

    echo "2. Datos controlados\n";
    $pdo->exec("INSERT INTO areas (id, nombre, publica) VALUES
        (1, 'Admisión', 0), (2, 'Medicina General', 1), (3, 'Pediatría', 1), (4, 'Laboratorio', 1)");
    $pdo->exec("INSERT INTO pacientes (id, numero_documento, nombres, apellidos) VALUES
        (1, '00000001', 'Prueba', 'Uno'), (2, '00000002', 'Prueba', 'Dos')");
    $pdo->exec("INSERT INTO usuarios (id, nombre, usuario, password_hash, rol, area_id) VALUES
        (1, 'Médico prueba', 'medico_prueba', 'x', 'medico', 2)");

    // Citas: [fecha, área, estado, ¿con atención?]
    $citas = [
        ['2026-03-01', 2, 'atendida', true],     // borde inferior del período
        ['2026-03-15', 2, 'atendida', true],
        ['2026-03-20', 3, 'atendida', true],
        ['2026-03-20', 3, 'cancelada', false],
        ['2026-04-02', 2, 'pendiente', false],   // pasada y pendiente: "sin cerrar"
        ['2026-04-10', 4, 'no_asistio', false],
        ['2026-04-30', 3, 'confirmada', false],  // borde superior del período
        ['2026-02-28', 2, 'atendida', true],     // FUERA (día antes)
        ['2026-05-01', 2, 'cancelada', false],   // FUERA (día después)
    ];
    $insertar = $pdo->prepare("INSERT INTO citas (paciente_id, area_id, medico_id, fecha, hora, estado)
                               VALUES (1, :area, 1, :fecha, :hora, :estado)");
    $atencion = $pdo->prepare("INSERT INTO atenciones (cita_id, paciente_id, medico_id, diagnostico)
                               VALUES (:cita, 1, 1, 'Dato de prueba')");
    foreach ($citas as $i => [$fecha, $area, $estado, $con_atencion]) {
        $insertar->execute(['area' => $area, 'fecha' => $fecha, 'hora' => sprintf('08:%02d:00', $i), 'estado' => $estado]);
        if ($con_atencion) {
            $atencion->execute(['cita' => (int) $pdo->lastInsertId()]);
        }
    }
    // Solicitudes: [creada, estado]
    $solicitud = $pdo->prepare("INSERT INTO solicitudes_cita (paciente_id, area_id, estado, creado_en)
                                VALUES (2, 2, :estado, :creado)");
    foreach ([['2026-03-01 00:00:00', 'nueva'], ['2026-04-30 23:59:59', 'nueva'], ['2026-03-10 10:00:00', 'atendida'],
              ['2026-03-11 10:00:00', 'descartada'], ['2026-05-01 00:00:00', 'nueva'], ['2026-02-28 23:59:59', 'atendida']]
             as [$creado, $estado]) {
        $solicitud->execute(['estado' => $estado, 'creado' => $creado]);
    }

    $r = indicadores_resumen($pdo, '2026-03-01', '2026-04-30');
    comprobar('7 citas en el período (bordes incluidos, fuera excluidas)', $r['citas']['total'] === 7);
    comprobar('3 atendidas, 1 cancelada, 1 pendiente, 1 confirmada, 1 no asistió', $r['citas']['estados'] === [
        'pendiente' => 1, 'confirmada' => 1, 'atendida' => 3, 'cancelada' => 1, 'no_asistio' => 1]);
    comprobar('total = suma de estados', $r['citas']['total'] === array_sum($r['citas']['estados']));
    comprobar('2 citas pasadas sin cerrar (pendiente del 02/04 y confirmada del 30/04)', $r['sin_cerrar'] === 2);
    comprobar('solicitudes: 2 en revisión (bordes 00:00 y 23:59 incluidos)', $r['solicitudes']['en_revision'] === 2);
    comprobar('solicitudes: 2 resueltas (1 programada + 1 descartada)', $r['solicitudes']['resueltas'] === 2
        && $r['solicitudes']['programadas'] === 1 && $r['solicitudes']['descartadas'] === 1);
    comprobar('atenciones por área: Medicina General 2, Pediatría 1, Laboratorio 0', $r['por_area'] === [
        'Medicina General' => 2, 'Pediatría' => 1, 'Laboratorio' => 0]);
    comprobar('áreas internas sin atenciones no aparecen (Admisión)', !isset($r['por_area']['Admisión']));
    comprobar('atenciones = citas atendidas', $r['atenciones'] === $r['citas']['estados']['atendida']);
    comprobar('mensual: marzo 4 citas / 3 atenciones, abril 3 / 0', $r['mensual'] === [
        '2026-03' => ['citas' => 4, 'atenciones' => 3], '2026-04' => ['citas' => 3, 'atenciones' => 0]]);
    comprobar('mensual suma lo mismo que el período', array_sum(array_column($r['mensual'], 'citas')) === $r['citas']['total']);
    comprobar('porcentaje: 3 de 7 = 42,9', indicadores_porcentaje(3, 7) === 42.9);

    echo "3. Comparación con SQL independiente\n";
    $sql = fn (string $q): int => (int) $pdo->query($q)->fetchColumn();
    comprobar('total citas = COUNT directo', $r['citas']['total'] === $sql(
        "SELECT COUNT(*) FROM citas WHERE fecha >= '2026-03-01' AND fecha <= '2026-04-30'"));
    comprobar('atenciones = COUNT directo', $r['atenciones'] === $sql(
        "SELECT COUNT(*) FROM atenciones a JOIN citas c ON c.id = a.cita_id WHERE c.fecha BETWEEN '2026-03-01' AND '2026-04-30'"));
    comprobar('solicitudes = COUNT directo', $r['solicitudes']['total'] === $sql(
        "SELECT COUNT(*) FROM solicitudes_cita WHERE DATE(creado_en) BETWEEN '2026-03-01' AND '2026-04-30'"));

    echo "4. Período 'todo' y períodos personalizados\n";
    $todo = indicadores_periodo($pdo, ['periodo' => 'todo'], '2026-11-22');
    comprobar("'todo' va de la primera a la última fecha", $todo['desde'] === '2026-02-28' && $todo['hasta'] >= '2026-05-01');
    $p = indicadores_periodo($pdo, ['periodo' => 'personalizado', 'desde' => '2026-04-30', 'hasta' => '2026-03-01'], '2026-11-22');
    comprobar('desde > hasta da error y vuelve a 6 meses', $p['error'] !== null && $p['clave'] === '6m');
    $p = indicadores_periodo($pdo, ['periodo' => 'personalizado', 'desde' => '2026-02-30', 'hasta' => '2026-03-01'], '2026-11-22');
    comprobar('fecha imposible (30 de febrero) da error', $p['error'] !== null);
    $p = indicadores_periodo($pdo, ['periodo' => 'personalizado', 'desde' => "2026-01-01' OR 1=1 --", 'hasta' => '2026-03-01'], '2026-11-22');
    comprobar('texto con SQL no pasa la validación', $p['error'] !== null);
    $p = indicadores_periodo($pdo, ['periodo' => 'personalizado', 'desde' => '2020-01-01', 'hasta' => '2026-01-01'], '2026-11-22');
    comprobar('más de 24 meses da error', $p['error'] !== null);
    $p = indicadores_periodo($pdo, ['periodo' => ['x']], '2026-11-22');
    comprobar('período como lista (truco) no rompe: 6 meses', $p['clave'] === '6m' && $p['desde'] === '2026-06-01' && $p['hasta'] === '2026-11-30');
    $p = indicadores_periodo($pdo, ['periodo' => 'mes'], '2026-02-10');
    comprobar("'mes' de febrero termina el 28", $p['desde'] === '2026-02-01' && $p['hasta'] === '2026-02-28');

    echo "5. Valores extremos\n";
    $pdo->beginTransaction();
    $insertar = $pdo->prepare("INSERT INTO citas (paciente_id, area_id, fecha, hora, estado) VALUES (2, 3, :fecha, :hora, 'atendida')");
    for ($i = 0; $i < 5000; $i++) {
        $insertar->execute(['fecha' => sprintf('2026-06-%02d', 1 + $i % 30), 'hora' => sprintf('%02d:%02d:%02d', intdiv($i, 3600) % 24, intdiv($i, 60) % 60, $i % 60)]);
    }
    $pdo->commit();
    $inicio = microtime(true);
    $r = indicadores_resumen($pdo, '2026-01-01', '2026-12-31');
    $ms = (microtime(true) - $inicio) * 1000;
    comprobar('5 000 citas en junio se cuentan', $r['mensual']['2026-06']['citas'] === 5000);
    comprobar('citas atendidas sin atención registrada se notan (atenciones ≠ atendidas)', $r['atenciones'] !== $r['citas']['estados']['atendida']);
    comprobar('las 6 consultas tardan menos de 500 ms (' . round($ms) . ' ms)', $ms < 500);
    comprobar('memoria usada menor a 8 MB (' . round(memory_get_peak_usage() / 1048576, 1) . ' MB)', memory_get_peak_usage() < 8 * 1048576);
    require_once __DIR__ . '/../layout/graficos.php';
    $svg = grafico_columnas_mensuales($r['mensual']);
    comprobar('el gráfico se dibuja con 5 000 en un mes (eje redondo hasta 5 000)', str_contains($svg, '>5,000<'));
    $svg24 = grafico_columnas_mensuales(indicadores_evolucion_mensual($pdo, '2024-01-01', '2026-12-31'));
    comprobar('un período de 36 meses dibuja solo los últimos 24', substr_count($svg24, 'ind-eje-anio') === 24);
    comprobar('barras con todo en 0 no dibujan rectángulos de color', !str_contains(grafico_barras_horizontales(['A' => 0, 'B' => 0]), 'ind-serie'));
} finally {
    $admin->exec('DROP DATABASE IF EXISTS `' . $base . '`');
    echo "(base de pruebas {$base} borrada)\n";
}

echo PHP_EOL . ($fallos === 0 ? "Todo bien: {$total} comprobaciones." : "FALLARON {$fallos} de {$total}.") . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
