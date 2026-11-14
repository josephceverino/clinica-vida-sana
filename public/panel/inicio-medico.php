<?php
// "Mi día": resumen del médico. Se muestra desde panel/index.php cuando entra
// un médico. También se puede abrir directo, por eso se protege igual.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/citas.php';
require_once __DIR__ . '/../../app/modelos/agenda.php';

// Regla 6: solo médicos.
requerir_rol(['medico']);

$titulo_pagina = 'Mi día';
$pagina_activa = 'index.php';

// El médico sale SIEMPRE de la sesión.
$medico_id = (int) usuario_actual()['id'];
$hoy = date('Y-m-d');
$ahora = date('H:i:s');

$numeros = resumen_medico($medico_id);
$por_mes = atenciones_por_mes($medico_id, 6);
$citas_hoy = buscar_citas_medico($medico_id, $hoy);

// Siguiente paciente: la primera cita confirmada de hoy que aún no se atiende.
// Si ya pasó su hora, igual se muestra (el paciente puede estar esperando).
$siguiente = null;
foreach ($citas_hoy as $cita) {
    if ($cita['estado'] === 'confirmada') {
        $siguiente = $cita;
        break;
    }
}

// Para el gráfico: la barra más alta ocupa el 100 % del alto.
$maximo = max(1, ...array_column($por_mes, 'total'));
$total_seis_meses = array_sum(array_column($por_mes, 'total'));

/**
 * Edad a partir de la fecha de nacimiento (o null si no se registró).
 */
function edad_de(?string $nacimiento): ?int
{
    if (!$nacimiento) {
        return null;
    }

    return (new DateTimeImmutable($nacimiento))->diff(new DateTimeImmutable('today'))->y;
}

require __DIR__ . '/../../app/layout/panel_header.php';

$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
?>

<header class="panel-titulo">
    <div>
        <h1><?= e($saludo) ?>, <?= e($usuario['nombre']) ?></h1>
        <p>Esto es lo que tienes hoy.</p>
    </div>
    <time datetime="<?= e($hoy) ?>"><?= e(ucfirst(fecha_larga($hoy))) ?></time>
</header>

<!-- Siguiente paciente: lo más importante del día -->
<?php if ($siguiente): ?>
    <section class="siguiente-paciente">
        <div>
            <p class="siguiente-etiqueta">Siguiente paciente</p>
            <p class="siguiente-nombre">
                <?= e($siguiente['nombres'] . ' ' . $siguiente['apellidos']) ?>
            </p>
            <p>
                <?= e(substr($siguiente['hora'], 0, 5)) ?>
                <?php if (substr($siguiente['hora'], 0, 8) < $ahora): ?>
                    <small>(ya es su hora)</small>
                <?php endif; ?>
                &middot; <?= e($siguiente['motivo'] ?? 'Sin motivo indicado') ?>
                <?php $edad = edad_de($siguiente['fecha_nacimiento']); ?>
                <?php if ($edad !== null): ?>
                    &middot; <?= $edad ?> años
                <?php endif; ?>
            </p>
        </div>
        <a class="boton boton-blanco"
           href="<?= e(url('/panel/atender-cita.php?id=' . (int) $siguiente['id'])) ?>">Atender</a>
    </section>
<?php else: ?>
    <section class="siguiente-paciente siguiente-libre">
        <div>
            <p class="siguiente-etiqueta">Siguiente paciente</p>
            <p class="siguiente-nombre">No tienes pacientes en espera</p>
            <p>Revisa tu agenda de la semana para ver lo que viene.</p>
        </div>
        <a class="boton" href="<?= e(url('/panel/agenda.php')) ?>">Ver mi agenda</a>
    </section>
<?php endif; ?>

<!-- Números -->
<section class="tarjetas">
    <article class="dato">
        <span class="dato-numero"><?= $numeros['hoy_total'] ?></span>
        <span class="dato-texto">Citas hoy</span>
    </article>
    <article class="dato dato-alerta">
        <span class="dato-numero"><?= $numeros['hoy_por_atender'] ?></span>
        <span class="dato-texto">Por atender hoy</span>
    </article>
    <a class="dato dato-enlace" href="<?= e(url('/panel/agenda.php')) ?>">
        <span class="dato-numero"><?= $numeros['semana_total'] ?></span>
        <span class="dato-texto">Citas en los próximos 7 días</span>
    </a>
    <article class="dato">
        <span class="dato-numero"><?= $numeros['mes_atendidas'] ?></span>
        <span class="dato-texto">
            Atenciones este mes
            (<?= $numeros['mes_pacientes'] ?> paciente<?= $numeros['mes_pacientes'] === 1 ? '' : 's' ?>)
        </span>
    </article>
</section>

<div class="panel-columnas">

    <!-- Agenda de hoy -->
    <section class="caja">
        <h2>Mi agenda de hoy</h2>
        <?php if ($citas_hoy === []): ?>
            <p class="vacio">No tienes citas hoy.</p>
        <?php else: ?>
            <ul class="agenda">
                <?php foreach ($citas_hoy as $cita): ?>
                    <li>
                        <div class="agenda-hora">
                            <strong><?= e(substr($cita['hora'], 0, 5)) ?></strong>
                        </div>
                        <div class="agenda-detalle">
                            <strong><?= e($cita['nombres'] . ' ' . $cita['apellidos']) ?></strong>
                            <small><?= e($cita['motivo'] ?? 'Sin motivo') ?></small>
                        </div>
                        <?php if ($cita['estado'] === 'confirmada'): ?>
                            <a class="boton-tabla"
                               href="<?= e(url('/panel/atender-cita.php?id=' . (int) $cita['id'])) ?>">Atender</a>
                        <?php elseif ($cita['estado'] === 'atendida'): ?>
                            <a class="boton-tabla boton-tabla-claro"
                               href="<?= e(url('/panel/atender-cita.php?id=' . (int) $cita['id'])) ?>">Ver</a>
                        <?php else: ?>
                            <span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- Gráfico: atenciones por mes -->
    <section class="caja">
        <h2>Mis atenciones por mes</h2>
        <p class="vacio"><?= $total_seis_meses ?> en los últimos 6 meses</p>

        <!-- Gráfico de barras en SVG, dibujado por PHP (sin librerías).
             Se usa SVG y no style="height:..." porque la política de
             seguridad (CSP) de la web bloquea los estilos escritos en el HTML.
             Al pasar el ratón por una barra se ve su número. -->
        <svg class="grafico-barras" viewBox="0 0 300 170" aria-hidden="true">
            <?php
            $alto_maximo = 120;   // alto disponible para las barras
            $base = 140;          // línea donde se apoyan las barras
            $ancho_barra = 26;
            $paso = 300 / count($por_mes);
            ?>
            <line class="grafico-base" x1="0" y1="<?= $base ?>" x2="300" y2="<?= $base ?>"></line>
            <?php foreach ($por_mes as $i => $mes): ?>
                <?php
                $alto = $mes['total'] / $maximo * $alto_maximo;
                $x = $i * $paso + ($paso - $ancho_barra) / 2;
                $y = $base - $alto;
                $centro = $x + $ancho_barra / 2;
                // Barra con esquinas redondeadas solo arriba (radio 4).
                $r = min(4, $alto);
                $trazo = sprintf(
                    'M%.1f,%d V%.1f Q%.1f,%.1f %.1f,%.1f H%.1f Q%.1f,%.1f %.1f,%.1f V%d Z',
                    $x, $base, $y + $r, $x, $y, $x + $r, $y,
                    $x + $ancho_barra - $r, $x + $ancho_barra, $y, $x + $ancho_barra, $y + $r, $base
                );
                ?>
                <g class="barra-grupo">
                    <title><?= e($mes['nombre'] . ': ' . $mes['total'] . ' atenciones') ?></title>
                    <!-- Zona invisible más grande que la barra: más fácil de señalar -->
                    <rect class="barra-zona" x="<?= round($i * $paso, 1) ?>" y="0"
                          width="<?= round($paso, 1) ?>" height="<?= $base ?>"></rect>
                    <?php if ($mes['total'] > 0): ?>
                        <path class="barra" d="<?= $trazo ?>"></path>
                    <?php endif; ?>
                    <text class="barra-valor" x="<?= round($centro, 1) ?>"
                          y="<?= round($y - 6, 1) ?>"><?= $mes['total'] ?></text>
                    <text class="barra-mes" x="<?= round($centro, 1) ?>"
                          y="<?= $base + 20 ?>"><?= e($mes['nombre']) ?></text>
                </g>
            <?php endforeach; ?>
        </svg>

        <!-- Los mismos datos en una tabla, para lectores de pantalla -->
        <table class="solo-lectores">
            <caption>Atenciones por mes</caption>
            <?php foreach ($por_mes as $mes): ?>
                <tr><th scope="row"><?= e($mes['nombre']) ?></th><td><?= $mes['total'] ?></td></tr>
            <?php endforeach; ?>
        </table>
    </section>

</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
