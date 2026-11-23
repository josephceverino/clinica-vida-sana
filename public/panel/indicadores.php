<?php
// Indicadores administrativos: cifras del período sacadas de MariaDB.
// Solo lectura (consultas SELECT de app/modelos/indicadores.php) y solo
// cantidades: nunca nombres de pacientes, documentos ni diagnósticos.
// Fórmulas: docs/indicadores.md (y en la sección "¿Cómo se calcula?").
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/indicadores.php';
require_once __DIR__ . '/../../app/layout/graficos.php';

// Regla 6: primero el login y luego el rol. Solo el administrador.
requerir_login();
requerir_rol(['admin']);

$titulo_pagina = 'Indicadores';
$pagina_activa = 'indicadores.php';
$pdo = db();

// --- Período (viene por GET; se valida en el modelo) ----------------------
$periodo = indicadores_periodo($pdo, $_GET);
$desde = $periodo['desde'];
$hasta = $periodo['hasta'];

try {
    $datos = indicadores_resumen($pdo, $desde, $hasta);
    $error_datos = false;
} catch (PDOException $excepcion) {
    // Regla 9: mensaje genérico; el detalle va al log.
    error_log('Indicadores: ' . $excepcion->getMessage());
    $datos = null;
    $error_datos = true;
}

require __DIR__ . '/../../app/layout/panel_header.php';

// --- Solo presentación -----------------------------------------------------
$fecha_corta = fn (string $fecha): string => date('d/m/Y', strtotime($fecha));
$estados = indicadores_estados_cita();

/**
 * Dirección de esta página con un período rápido.
 */
function enlace_periodo(string $clave): string
{
    return url('/panel/indicadores.php') . '?periodo=' . rawurlencode($clave);
}
?>

<div class="resumen-admin indicadores ui-animada">

    <header class="resumen-cabecera">
        <div>
            <h1>Indicadores</h1>
            <p>
                Citas, solicitudes y atenciones del
                <time datetime="<?= e($desde) ?>"><?= e($fecha_corta($desde)) ?></time> al
                <time datetime="<?= e($hasta) ?>"><?= e($fecha_corta($hasta)) ?></time>
            </p>
        </div>
        <span class="resumen-pastilla">Administrador</span>
    </header>

    <!-- Aviso: de dónde salen los datos y que son ficticios -->
    <div class="ind-origen" role="note">
        <?= $icono_panel('i-info') ?>
        <p>
            <strong>Datos de demostración.</strong> Clínica Vida Sana es una clínica simulada: todos los
            registros son ficticios (los de <code>sql/seed.sql</code> y los creados al probar la web).
            Las cifras se calculan en este momento desde MariaDB (<?= e(date('d/m/Y H:i')) ?>) y no
            muestran nombres, documentos ni diagnósticos.
        </p>
    </div>

    <!-- Filtros: períodos rápidos y un rango personalizado (formulario GET) -->
    <section class="resumen-caja ind-filtros" aria-label="Período">
        <nav class="ind-periodos" aria-label="Períodos rápidos">
            <?php foreach (indicadores_periodos() as $clave => $texto): ?>
                <a class="ind-periodo<?= $periodo['clave'] === $clave ? ' activo' : '' ?>"
                   href="<?= e(enlace_periodo($clave)) ?>"
                   <?= $periodo['clave'] === $clave ? 'aria-current="true"' : '' ?>><?= e($texto) ?></a>
            <?php endforeach; ?>
        </nav>

        <form class="ind-rango" method="get" action="<?= e(url('/panel/indicadores.php')) ?>">
            <input type="hidden" name="periodo" value="personalizado">
            <div class="ui-campo">
                <label for="ind-desde">Desde</label>
                <input class="ui-entrada" type="date" id="ind-desde" name="desde" required
                       value="<?= e($desde) ?>">
            </div>
            <div class="ui-campo">
                <label for="ind-hasta">Hasta</label>
                <input class="ui-entrada" type="date" id="ind-hasta" name="hasta" required
                       value="<?= e($hasta) ?>">
            </div>
            <button class="ui-boton" type="submit">
                <?= $icono_panel('i-buscar') ?>
                Aplicar
            </button>
        </form>

        <?php if ($periodo['error'] !== null): ?>
            <div class="ui-aviso ui-aviso-error" role="alert">
                <?= $icono_panel('i-alerta') ?>
                <p><?= e($periodo['error']) ?> Se muestran los últimos 6 meses.</p>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($error_datos): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p>No se pudieron calcular los indicadores. Inténtalo más tarde.</p>
        </div>
    <?php else: ?>
        <?php
        $citas = $datos['citas'];
        $total_citas = $citas['total'];
        $por_atender = $citas['estados']['pendiente'] + $citas['estados']['confirmada'];
        $sol = $datos['solicitudes'];
        ?>

        <!-- Tarjetas: cifras principales del período -->
        <section class="resumen-indicadores" aria-label="Cifras del período">
            <article class="resumen-indicador">
                <h2>Citas del período</h2>
                <p class="resumen-numero"><?= e(grafico_numero($total_citas)) ?></p>
                <p class="resumen-nota">Todas, en cualquier estado</p>
            </article>
            <article class="resumen-indicador">
                <h2>Atendidas</h2>
                <p class="resumen-numero"><?= e(grafico_numero($citas['estados']['atendida'])) ?></p>
                <p class="resumen-nota"><?= e(grafico_porcentaje(indicadores_porcentaje($citas['estados']['atendida'], $total_citas))) ?> de las citas</p>
            </article>
            <article class="resumen-indicador">
                <h2>Canceladas</h2>
                <p class="resumen-numero"><?= e(grafico_numero($citas['estados']['cancelada'])) ?></p>
                <p class="resumen-nota"><?= e(grafico_porcentaje(indicadores_porcentaje($citas['estados']['cancelada'], $total_citas))) ?> de las citas</p>
            </article>
            <article class="resumen-indicador">
                <h2>Pendientes</h2>
                <p class="resumen-numero"><?= e(grafico_numero($por_atender)) ?></p>
                <p class="resumen-nota">Pendientes + confirmadas</p>
            </article>
            <a class="resumen-indicador resumen-indicador-enlace" href="<?= e(url('/panel/solicitudes.php')) ?>">
                <h2>Solicitudes en revisión</h2>
                <p class="resumen-numero"><?= e(grafico_numero($sol['en_revision'])) ?></p>
                <p class="resumen-nota">Enviadas en el período</p>
            </a>
            <article class="resumen-indicador">
                <h2>Solicitudes resueltas</h2>
                <p class="resumen-numero"><?= e(grafico_numero($sol['resueltas'])) ?></p>
                <p class="resumen-nota">Programadas: <?= e(grafico_numero($sol['programadas'])) ?> &middot; Descartadas: <?= e(grafico_numero($sol['descartadas'])) ?></p>
            </article>
        </section>

        <?php if ($datos['sin_cerrar'] > 0): ?>
            <div class="ui-aviso ui-aviso-advertencia" role="status">
                <?= $icono_panel('i-reloj') ?>
                <p>
                    <strong><?= e(grafico_numero($datos['sin_cerrar'])) ?></strong>
                    <?= $datos['sin_cerrar'] === 1 ? 'cita ya pasó y sigue' : 'citas ya pasaron y siguen' ?>
                    pendiente o confirmada: falta marcarla<?= $datos['sin_cerrar'] === 1 ? '' : 's' ?> como atendida o «no asistió».
                    <a href="<?= e(url('/panel/citas.php')) ?>">Ir a citas</a>
                </p>
            </div>
        <?php endif; ?>

        <!-- Evolución mensual -->
        <section class="resumen-caja" aria-labelledby="ind-evolucion-titulo">
            <div class="resumen-caja-cabecera">
                <h2 id="ind-evolucion-titulo">Evolución mensual de citas y atenciones</h2>
                <ul class="ind-leyenda" aria-label="Leyenda">
                    <li><span class="ind-muestra ind-serie-1" aria-hidden="true"></span>Citas programadas</li>
                    <li><span class="ind-muestra ind-serie-2" aria-hidden="true"></span>Atenciones registradas</li>
                </ul>
            </div>
            <?php
            $hay_mensual = array_sum(array_map(fn ($m) => $m['citas'] + $m['atenciones'], $datos['mensual'])) > 0;
            ?>
            <?php if (!$hay_mensual): ?>
                <p class="resumen-vacio ind-vacio">No hay citas en este período.</p>
            <?php else: ?>
                <div class="ind-desplazable" tabindex="0" aria-label="Gráfico de evolución mensual (se puede desplazar)">
                    <?= grafico_columnas_mensuales($datos['mensual']) ?>
                </div>
                <?php if (count($datos['mensual']) === INDICADORES_MESES_GRAFICO): ?>
                    <p class="ui-nota">El gráfico muestra como máximo los últimos <?= INDICADORES_MESES_GRAFICO ?> meses del período.</p>
                <?php endif; ?>
                <details class="ind-tabla-datos">
                    <summary>Ver los datos en una tabla</summary>
                    <div class="tabla-scroll">
                        <table class="ui-tabla">
                            <caption class="solo-lectores">Citas programadas y atenciones registradas por mes</caption>
                            <thead>
                                <tr><th scope="col">Mes</th><th scope="col">Citas programadas</th><th scope="col">Atenciones registradas</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($datos['mensual'] as $mes => $valores): ?>
                                    <tr>
                                        <th scope="row"><?= e($mes) ?></th>
                                        <td><?= e(grafico_numero($valores['citas'])) ?></td>
                                        <td><?= e(grafico_numero($valores['atenciones'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </section>

        <div class="resumen-columnas ind-columnas">
            <!-- Distribución de estados -->
            <section class="resumen-caja" aria-labelledby="ind-estados-titulo">
                <h2 id="ind-estados-titulo">Distribución de estados de las citas</h2>
                <?php if ($total_citas === 0): ?>
                    <p class="resumen-vacio ind-vacio">No hay citas en este período.</p>
                <?php else: ?>
                    <?php
                    $por_estado = [];
                    foreach ($estados as $clave => $texto) {
                        $por_estado[$texto] = $citas['estados'][$clave];
                    }
                    ?>
                    <?= grafico_barras_horizontales($por_estado, $total_citas, 'ind-serie-1') ?>
                    <p class="ui-nota">
                        Porcentaje sobre <?= e(grafico_numero($total_citas)) ?> citas. Por el redondeo a un
                        decimal, la suma puede no dar exactamente 100 %.
                    </p>
                <?php endif; ?>
            </section>

            <!-- Atenciones por especialidad -->
            <section class="resumen-caja" aria-labelledby="ind-areas-titulo">
                <h2 id="ind-areas-titulo">Atenciones por especialidad</h2>
                <?php if ($datos['atenciones'] === 0): ?>
                    <p class="resumen-vacio ind-vacio">No hay atenciones registradas en este período.</p>
                <?php else: ?>
                    <?= grafico_barras_horizontales($datos['por_area'], $datos['atenciones'], 'ind-serie-2') ?>
                    <p class="ui-nota">
                        <?= e(grafico_numero($datos['atenciones'])) ?> atenciones registradas en la historia clínica
                        &middot; <?= e(grafico_numero($citas['estados']['atendida'])) ?> citas marcadas como atendidas.
                        <?php if ($datos['atenciones'] !== $citas['estados']['atendida']): ?>
                            <strong>Las dos cifras no coinciden:</strong> revisa las citas atendidas sin atención registrada.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </section>
        </div>

        <!-- Fórmulas: transparencia para quien lee las cifras -->
        <details class="resumen-caja ind-formulas">
            <summary><h2>¿Cómo se calcula cada cifra?</h2></summary>
            <dl>
                <div><dt>Período</dt><dd>De citas y atenciones: la <em>fecha de la cita</em> (<code>citas.fecha</code>). De solicitudes: el día en que el paciente la envió (<code>solicitudes_cita.creado_en</code>). Ambos extremos se incluyen.</dd></div>
                <div><dt>Citas del período</dt><dd><code>COUNT(*)</code> de <code>citas</code> del período, en cualquier estado.</dd></div>
                <div><dt>Atendidas / Canceladas</dt><dd>Citas con <code>estado = 'atendida'</code> / <code>'cancelada'</code>. Porcentaje = estado ÷ citas del período × 100, con un decimal.</dd></div>
                <div><dt>Pendientes</dt><dd>Citas con estado <code>'pendiente'</code> o <code>'confirmada'</code> (aún sin atender). Las que ya pasaron se avisan aparte como «sin cerrar».</dd></div>
                <div><dt>Solicitudes en revisión</dt><dd><code>solicitudes_cita</code> del período con <code>estado = 'nueva'</code>.</dd></div>
                <div><dt>Solicitudes resueltas</dt><dd>Del período, <code>'atendida'</code> (se programó una cita) + <code>'descartada'</code>.</dd></div>
                <div><dt>Atenciones por especialidad</dt><dd><code>atenciones</code> unidas a su cita (<code>cita_id</code>) y al área de esa cita, contadas por área.</dd></div>
                <div><dt>Evolución mensual</dt><dd>Por mes de <code>citas.fecha</code>: total de citas y cuántas tienen una atención registrada.</dd></div>
            </dl>
        </details>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
