<?php
// Agenda semanal: calendario de lunes a domingo con las citas.
// - Médico: ve solo SUS citas (sale de la sesión).
// - Admin y admisión: eligen un médico de la lista, o ven todos.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/agenda.php';

// Regla 6: solo personal.
requerir_rol(['admin', 'admision', 'medico']);

$titulo_pagina = 'Agenda semanal';
$pagina_activa = 'agenda.php';
$yo = usuario_actual();
$es_medico = $yo['rol'] === 'medico';

// --- Semana a mostrar (?semana=2026-11-09). Siempre se ajusta al lunes. ---
$lunes = lunes_de_semana(is_string($_GET['semana'] ?? null) ? $_GET['semana'] : date('Y-m-d'));
$anterior = date('Y-m-d', strtotime($lunes . ' -7 days'));
$siguiente = date('Y-m-d', strtotime($lunes . ' +7 days'));
$domingo = date('Y-m-d', strtotime($lunes . ' +6 days'));

// --- Qué médico se muestra ----------------------------------------------
$medicos = [];
$medico_id = null;          // null = todos
$filtro_medico = '';        // lo que va en la dirección (?medico=3)

if ($es_medico) {
    // El médico solo puede ver su agenda: se ignora cualquier ?medico=.
    $medico_id = (int) $yo['id'];
} else {
    $medicos = db()->query(
        "SELECT usuarios.id, usuarios.nombre, areas.nombre AS area
         FROM usuarios
         LEFT JOIN areas ON areas.id = usuarios.area_id
         WHERE usuarios.rol = 'medico' AND usuarios.activo = 1
         ORDER BY usuarios.nombre"
    )->fetchAll();

    // Solo se acepta un ID que esté en la lista de médicos.
    $pedido = is_string($_GET['medico'] ?? null) ? $_GET['medico'] : '';
    foreach ($medicos as $medico) {
        if ((string) $medico['id'] === $pedido) {
            $medico_id = (int) $medico['id'];
            $filtro_medico = $pedido;
        }
    }
}

// --- Citas agrupadas por día -----------------------------------------------
$dias = [];
for ($i = 0; $i < 7; $i++) {
    $dias[date('Y-m-d', strtotime($lunes . ' +' . $i . ' days'))] = [];
}
foreach (citas_de_semana($lunes, $medico_id) as $cita) {
    $dias[$cita['fecha']][] = $cita;
}

$total_semana = array_sum(array_map('count', $dias));

/**
 * Dirección de esta misma página para otra semana (mantiene el médico elegido).
 */
function enlace_semana(string $semana, string $medico): string
{
    $ruta = '/panel/agenda.php?semana=' . $semana;
    if ($medico !== '') {
        $ruta .= '&medico=' . $medico;
    }
    return url($ruta);
}

/**
 * A dónde lleva una cita al hacer clic, según el rol.
 * Médico: atenderla o ver su atención. Admin/admisión: gestionarla.
 */
function enlace_cita(array $cita, bool $es_medico): ?string
{
    if (!$es_medico) {
        return url('/panel/gestionar-cita.php?id=' . (int) $cita['id']);
    }

    if (in_array($cita['estado'], ['confirmada', 'atendida'], true)) {
        return url('/panel/atender-cita.php?id=' . (int) $cita['id']);
    }

    return null;   // pendiente: aún no se puede atender
}

$nombres_dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$hoy = date('Y-m-d');

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<header class="panel-titulo">
    <div>
        <h1><?= $es_medico ? 'Mi agenda' : 'Agenda semanal' ?></h1>
        <p>
            Del <?= e(fecha_larga($lunes)) ?> al <?= e(fecha_larga($domingo)) ?>
            &middot; <?= $total_semana ?> cita<?= $total_semana === 1 ? '' : 's' ?>
        </p>
    </div>
</header>

<!-- Controles: semana anterior / esta semana / siguiente, y filtro de médico -->
<div class="agenda-controles">
    <nav class="agenda-navegacion" aria-label="Cambiar de semana">
        <a class="boton-tabla" href="<?= e(enlace_semana($anterior, $filtro_medico)) ?>">&larr; Anterior</a>
        <a class="boton-tabla" href="<?= e(enlace_semana(date('Y-m-d'), $filtro_medico)) ?>">Esta semana</a>
        <a class="boton-tabla" href="<?= e(enlace_semana($siguiente, $filtro_medico)) ?>">Siguiente &rarr;</a>
    </nav>

    <?php if (!$es_medico): ?>
        <!-- GET: solo filtra lo que se ve, no cambia datos (no necesita CSRF) -->
        <form class="agenda-filtro" method="get" action="<?= e(url('/panel/agenda.php')) ?>">
            <input type="hidden" name="semana" value="<?= e($lunes) ?>">
            <label for="medico">Médico</label>
            <select id="medico" name="medico">
                <option value="">Todos</option>
                <?php foreach ($medicos as $medico): ?>
                    <option value="<?= (int) $medico['id'] ?>"
                        <?= $filtro_medico === (string) $medico['id'] ? 'selected' : '' ?>>
                        <?= e($medico['nombre'] . ' (' . ($medico['area'] ?? 'sin área') . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="boton-tabla" type="submit">Ver</button>
        </form>
    <?php endif; ?>
</div>

<!-- Calendario: una columna por día -->
<div class="calendario">
    <?php $i = 0; ?>
    <?php foreach ($dias as $fecha => $citas): ?>
        <section class="calendario-dia <?= $fecha === $hoy ? 'es-hoy' : '' ?> <?= $fecha < $hoy ? 'es-pasado' : '' ?>">
            <header class="calendario-cabecera">
                <span><?= e($nombres_dias[$i++]) ?></span>
                <strong><?= e(date('j', strtotime($fecha))) ?></strong>
            </header>

            <?php if ($citas === []): ?>
                <p class="calendario-vacio">Libre</p>
            <?php endif; ?>

            <?php foreach ($citas as $cita): ?>
                <?php $enlace = enlace_cita($cita, $es_medico); ?>
                <?php if ($enlace): ?>
                    <a class="calendario-cita cita-<?= e($cita['estado']) ?>" href="<?= e($enlace) ?>">
                <?php else: ?>
                    <div class="calendario-cita cita-<?= e($cita['estado']) ?>">
                <?php endif; ?>
                    <strong><?= e(substr($cita['hora'], 0, 5)) ?></strong>
                    <span><?= e($cita['paciente']) ?></span>
                    <small>
                        <?= $es_medico ? e($cita['area']) : e($cita['medico'] ?? 'Sin médico') ?>
                        &middot; <?= e($cita['estado']) ?>
                    </small>
                <?php if ($enlace): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

<!-- Qué significa cada color (el estado también va escrito en cada cita) -->
<p class="calendario-leyenda">
    <span class="leyenda cita-pendiente">Pendiente</span>
    <span class="leyenda cita-confirmada">Confirmada</span>
    <span class="leyenda cita-atendida">Atendida</span>
</p>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
