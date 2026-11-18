<?php
// Inicio del Portal del paciente.
// - Cuenta pendiente: le explica qué hacer para que la verifiquen.
// - Cuenta verificada: su próxima cita, un resumen en números, su última
//   atención y accesos rápidos.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/citas.php';
require_once __DIR__ . '/../../app/modelos/solicitudes.php';
require_once __DIR__ . '/../../app/modelos/atenciones.php';

// Solo pacientes. La cuenta puede estar pendiente de verificación.
requerir_paciente();

$titulo_pagina = 'Inicio';
$pagina_activa = 'index.php';

$yo = usuario_actual();
$es_verificada = (int) ($yo['verificado'] ?? 0) === 1;

$proxima = null;
$total_proximas = 0;
$en_revision = 0;
$atenciones = [];

// Los datos solo se cargan si la cuenta está verificada (si no, no puede
// verlos). El paciente sale SIEMPRE de la sesión.
if ($es_verificada) {
    $paciente_id = (int) $yo['paciente_id'];
    $ahora = date('Y-m-d H:i:s');

    // Próximas citas vigentes (su fecha y hora aún no pasan), de la más
    // cercana a la más lejana. Se compara "2026-11-14 08:30:00" como texto:
    // con este formato, el orden alfabético es el mismo que el del tiempo.
    $proximas = array_filter(
        citas_del_paciente($paciente_id),
        fn ($cita) => $cita['fecha'] . ' ' . $cita['hora'] >= $ahora
            && in_array($cita['estado'], ['pendiente', 'confirmada'], true)
    );
    usort($proximas, fn ($a, $b) => [$a['fecha'], $a['hora']] <=> [$b['fecha'], $b['hora']]);
    $total_proximas = count($proximas);
    $proxima = $proximas[0] ?? null;

    // Solicitudes que admisión aún no revisa.
    foreach (solicitudes_del_paciente($paciente_id) as $solicitud) {
        if ($solicitud['estado'] === 'nueva') {
            $en_revision++;
        }
    }

    // Historia clínica (vienen de la más reciente a la más antigua).
    $atenciones = atenciones_del_paciente($paciente_id);
}

$ultima_atencion = $atenciones[0] ?? null;

require __DIR__ . '/../../app/layout/paciente_header.php';

$primer_nombre = explode(' ', trim($usuario['nombre']))[0];
?>

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Hola, <?= e($primer_nombre) ?></h1>
            <p><?= e(ucfirst(fecha_larga(date('Y-m-d')))) ?></p>
        </div>
    </header>

    <?php if (!$es_verificada): ?>
        <!-- Cuenta pendiente: qué tiene que hacer -->
        <div class="ui-aviso ui-aviso-advertencia" role="status">
            <?= $icono_panel('i-reloj') ?>
            <p>Tu cuenta está <strong>pendiente de verificación</strong>.</p>
        </div>

        <section class="ui-tarjeta">
            <h2>¿Qué tengo que hacer?</h2>
            <ol class="portal-pasos">
                <li>Acércate a <strong>admisión</strong> con tu documento de identidad.</li>
                <li>Comprobarán que tus datos coinciden con los de tu registro.</li>
                <li>Cuando lo hagan, podrás pedir citas y ver tu historia clínica aquí.</li>
            </ol>
        </section>
    <?php else: ?>

        <!-- Próxima cita: lo más importante, en grande -->
        <?php if ($proxima): ?>
            <section class="ui-destacado" aria-label="Tu próxima cita">
                <div>
                    <p class="ui-destacado-etiqueta">Tu próxima cita</p>
                    <p class="ui-destacado-titulo">
                        <?= e(fecha_relativa($proxima['fecha'])) ?>
                        <span>a las <?= e(substr($proxima['hora'], 0, 5)) ?></span>
                    </p>
                    <p class="ui-destacado-detalle">
                        <?= e($proxima['area']) ?> &middot;
                        <?= e($proxima['medico'] ?? 'Médico por asignar') ?>
                    </p>
                </div>
                <span class="estado estado-<?= e($proxima['estado']) ?>"><?= e($proxima['estado']) ?></span>
            </section>
        <?php else: ?>
            <section class="ui-tarjeta">
                <div class="ui-vacio">
                    <span class="ui-vacio-icono"><?= $icono_panel('i-calendario') ?></span>
                    <strong>Tu próxima cita</strong>
                    <p>No tienes citas programadas.</p>
                    <a class="ui-boton" href="<?= e(url('/paciente/pedir-cita.php')) ?>">
                        <?= $icono_panel('i-agregar') ?>
                        Pedir una cita
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <!-- Resumen en números -->
        <section class="portal-numeros" aria-label="Resumen">
            <a class="ui-tarjeta portal-numero" href="<?= e(url('/paciente/citas.php')) ?>">
                <strong><?= $total_proximas ?></strong>
                <span>Citas próximas</span>
            </a>
            <a class="ui-tarjeta portal-numero" href="<?= e(url('/paciente/citas.php')) ?>">
                <strong><?= $en_revision ?></strong>
                <span>Solicitudes en revisión</span>
            </a>
            <a class="ui-tarjeta portal-numero" href="<?= e(url('/paciente/historia.php')) ?>">
                <strong><?= count($atenciones) ?></strong>
                <span>Atenciones en tu historia</span>
            </a>
        </section>

        <div class="ui-columnas portal-columnas">
            <!-- Última atención -->
            <section class="ui-tarjeta">
                <h2>Tu última atención</h2>
                <?php if ($ultima_atencion): ?>
                    <p class="ui-nota">
                        <?= e(date('d/m/Y', strtotime($ultima_atencion['fecha']))) ?> &middot;
                        <?= e($ultima_atencion['area']) ?> &middot;
                        <?= e($ultima_atencion['medico']) ?>
                    </p>
                    <dl class="datos portal-datos">
                        <div>
                            <dt>Diagnóstico</dt>
                            <dd><?= e($ultima_atencion['diagnostico']) ?></dd>
                        </div>
                        <?php if ($ultima_atencion['tratamiento']): ?>
                            <div>
                                <dt>Tratamiento</dt>
                                <dd><?= e($ultima_atencion['tratamiento']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                    <a class="ui-enlace" href="<?= e(url('/paciente/historia.php')) ?>">Ver toda mi historia clínica <span aria-hidden="true">&rarr;</span></a>
                <?php else: ?>
                    <p class="ui-nota">Aún no tienes atenciones registradas.</p>
                <?php endif; ?>
            </section>

            <!-- Accesos rápidos -->
            <section class="ui-tarjeta">
                <h2>¿Qué quieres hacer?</h2>
                <ul class="portal-accesos">
                    <li>
                        <a href="<?= e(url('/paciente/pedir-cita.php')) ?>">
                            <span class="portal-accesos-icono"><?= $icono_panel('i-agregar') ?></span>
                            <span>
                                <strong>Pedir una cita</strong>
                                <small>Elige el área y cuéntanos el motivo.</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= e(url('/paciente/citas.php')) ?>">
                            <span class="portal-accesos-icono"><?= $icono_panel('i-calendario') ?></span>
                            <span>
                                <strong>Ver mis citas</strong>
                                <small>Próximas, anteriores y el estado de tus solicitudes.</small>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= e(url('/paciente/historia.php')) ?>">
                            <span class="portal-accesos-icono"><?= $icono_panel('i-documento') ?></span>
                            <span>
                                <strong>Mi historia clínica</strong>
                                <small>Diagnósticos y tratamientos de tus atenciones.</small>
                            </span>
                        </a>
                    </li>
                </ul>
            </section>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
