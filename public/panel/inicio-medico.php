<?php
// "Mi día": resumen del médico. Se muestra desde panel/index.php cuando entra
// un médico. También se puede abrir directo, por eso se protege igual.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/citas.php';
require_once __DIR__ . '/../../app/modelos/agenda.php';

// Regla 6: solo médicos.
requerir_login();
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

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1><?= e($saludo) ?>, <?= e($usuario['nombre']) ?></h1>
            <p>Esto es lo que tienes hoy.</p>
        </div>
        <span class="ui-insignia"><time datetime="<?= e($hoy) ?>"><?= e(ucfirst(fecha_larga($hoy))) ?></time></span>
    </header>

    <!-- Siguiente paciente: lo más importante del día -->
    <?php if ($siguiente): ?>
        <section class="ui-destacado" aria-label="Siguiente paciente">
            <div>
                <p class="ui-destacado-etiqueta">Siguiente paciente</p>
                <p class="ui-destacado-titulo">
                    <?= e($siguiente['nombres'] . ' ' . $siguiente['apellidos']) ?>
                </p>
                <p class="ui-destacado-detalle">
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
            <a class="ui-boton ui-boton-blanco"
               href="<?= e(url('/panel/atender-cita.php?id=' . (int) $siguiente['id'])) ?>">Atender</a>
        </section>
    <?php else: ?>
        <section class="ui-destacado ui-destacado-tranquilo" aria-label="Siguiente paciente">
            <div>
                <p class="ui-destacado-etiqueta">Siguiente paciente</p>
                <p class="ui-destacado-titulo">No tienes pacientes en espera</p>
                <p class="ui-destacado-detalle">Revisa tu agenda de la semana para ver lo que viene.</p>
            </div>
            <a class="ui-boton" href="<?= e(url('/panel/agenda.php')) ?>">
                <?= $icono_panel('i-reloj') ?>
                Ver mi agenda
            </a>
        </section>
    <?php endif; ?>

    <!-- Números (mismas tarjetas que el Resumen de administración) -->
    <section class="resumen-indicadores" aria-label="Resumen del día">
        <article class="resumen-indicador">
            <h2>Citas hoy</h2>
            <p class="resumen-numero"><?= $numeros['hoy_total'] ?></p>
            <p class="resumen-nota">Agenda del día</p>
        </article>
        <article class="resumen-indicador">
            <h2>Por atender hoy</h2>
            <p class="resumen-numero"><?= $numeros['hoy_por_atender'] ?></p>
        </article>
        <a class="resumen-indicador resumen-indicador-enlace" href="<?= e(url('/panel/agenda.php')) ?>">
            <h2>Citas en los próximos 7 días</h2>
            <p class="resumen-numero"><?= $numeros['semana_total'] ?></p>
            <p class="resumen-nota">Ver mi agenda</p>
        </a>
        <article class="resumen-indicador">
            <h2>Atenciones este mes</h2>
            <p class="resumen-numero"><?= $numeros['mes_atendidas'] ?></p>
            <p class="resumen-nota"><?= $numeros['mes_pacientes'] ?> paciente<?= $numeros['mes_pacientes'] === 1 ? '' : 's' ?></p>
        </article>
    </section>

    <div class="ui-columnas ui-columnas-anchas">

        <!-- Agenda de hoy -->
        <section class="ui-tarjeta">
            <div class="ui-tarjeta-cabecera">
                <h2>Mi agenda de hoy</h2>
                <?php if ($citas_hoy !== []): ?>
                    <span class="ui-insignia"><?= count($citas_hoy) ?> cita<?= count($citas_hoy) === 1 ? '' : 's' ?></span>
                <?php endif; ?>
            </div>
            <?php if ($citas_hoy === []): ?>
                <div class="ui-vacio">
                    <span class="ui-vacio-icono"><?= $icono_panel('i-calendario') ?></span>
                    <strong>No tienes citas hoy.</strong>
                    <p>Las próximas aparecen en tu agenda semanal.</p>
                </div>
            <?php else: ?>
                <div class="ui-tabla-contenedor">
                    <table class="ui-tabla ui-tabla-apilable" role="table">
                        <caption class="solo-lectores">Mi agenda de hoy</caption>
                        <thead role="rowgroup">
                            <tr role="row">
                                <th scope="col" role="columnheader">Hora</th>
                                <th scope="col" role="columnheader">Paciente</th>
                                <th scope="col" role="columnheader"><span class="solo-lectores">Acción</span></th>
                            </tr>
                        </thead>
                        <tbody role="rowgroup">
                            <?php foreach ($citas_hoy as $cita): ?>
                                <tr role="row">
                                    <td class="ui-celda-fuerte" role="cell" data-etiqueta="Hora"><?= e(substr($cita['hora'], 0, 5)) ?></td>
                                    <td class="ui-celda-fuerte" role="cell" data-etiqueta="Paciente">
                                        <span class="ui-celda-valor">
                                            <?= e($cita['nombres'] . ' ' . $cita['apellidos']) ?>
                                            <small><?= e($cita['motivo'] ?? 'Sin motivo') ?></small>
                                        </span>
                                    </td>
                                    <td role="cell" data-etiqueta="Acción">
                                        <?php if ($cita['estado'] === 'confirmada'): ?>
                                            <a class="ui-boton ui-boton-chico"
                                               href="<?= e(url('/panel/atender-cita.php?id=' . (int) $cita['id'])) ?>">Atender</a>
                                        <?php elseif ($cita['estado'] === 'atendida'): ?>
                                            <a class="ui-boton ui-boton-secundario ui-boton-chico"
                                               href="<?= e(url('/panel/atender-cita.php?id=' . (int) $cita['id'])) ?>">Ver</a>
                                        <?php else: ?>
                                            <span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Gráfico: atenciones por mes -->
        <section class="ui-tarjeta">
            <div class="ui-tarjeta-cabecera">
                <h2>Mis atenciones por mes</h2>
            </div>
            <p class="ui-nota"><?= $total_seis_meses ?> en los últimos 6 meses</p>

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
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
