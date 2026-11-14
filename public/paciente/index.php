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

<section class="seccion">
    <p class="etiqueta">Portal del paciente</p>
    <h1>Hola, <?= e($primer_nombre) ?></h1>
    <p class="vacio"><?= e(ucfirst(fecha_larga(date('Y-m-d')))) ?></p>
</section>

<?php if (!$es_verificada): ?>
    <!-- Cuenta pendiente: qué tiene que hacer -->
    <section class="seccion">
        <p class="aviso aviso-pendiente" role="status">
            Tu cuenta está <strong>pendiente de verificación</strong>.
        </p>
        <div class="tarjeta-paciente">
            <h2>¿Qué tengo que hacer?</h2>
            <ol class="pasos">
                <li>Acércate a <strong>admisión</strong> con tu documento de identidad.</li>
                <li>Comprobarán que tus datos coinciden con los de tu registro.</li>
                <li>Cuando lo hagan, podrás pedir citas y ver tu historia clínica aquí.</li>
            </ol>
        </div>
    </section>
<?php else: ?>

    <!-- Próxima cita: lo más importante, en grande -->
    <section class="seccion">
        <?php if ($proxima): ?>
            <article class="proxima-cita">
                <p class="proxima-etiqueta">Tu próxima cita</p>
                <p class="proxima-fecha">
                    <?= e(fecha_relativa($proxima['fecha'])) ?>
                    <span>a las <?= e(substr($proxima['hora'], 0, 5)) ?></span>
                </p>
                <p>
                    <?= e($proxima['area']) ?> &middot;
                    <?= e($proxima['medico'] ?? 'Médico por asignar') ?>
                </p>
                <span class="estado estado-<?= e($proxima['estado']) ?>"><?= e($proxima['estado']) ?></span>
            </article>
        <?php else: ?>
            <article class="proxima-cita proxima-vacia">
                <p class="proxima-etiqueta">Tu próxima cita</p>
                <p>No tienes citas programadas.</p>
                <a class="boton" href="<?= e(url('/paciente/pedir-cita.php')) ?>">Pedir una cita</a>
            </article>
        <?php endif; ?>
    </section>

    <!-- Resumen en números -->
    <section class="seccion resumen-paciente">
        <a class="numero-paciente" href="<?= e(url('/paciente/citas.php')) ?>">
            <strong><?= $total_proximas ?></strong>
            <span>Citas próximas</span>
        </a>
        <a class="numero-paciente" href="<?= e(url('/paciente/citas.php')) ?>">
            <strong><?= $en_revision ?></strong>
            <span>Solicitudes en revisión</span>
        </a>
        <a class="numero-paciente" href="<?= e(url('/paciente/historia.php')) ?>">
            <strong><?= count($atenciones) ?></strong>
            <span>Atenciones en tu historia</span>
        </a>
    </section>

    <div class="rejilla-2">
        <!-- Última atención -->
        <section class="tarjeta-paciente">
            <h2>Tu última atención</h2>
            <?php if ($ultima_atencion): ?>
                <p class="vacio">
                    <?= e(date('d/m/Y', strtotime($ultima_atencion['fecha']))) ?> &middot;
                    <?= e($ultima_atencion['area']) ?> &middot;
                    <?= e($ultima_atencion['medico']) ?>
                </p>
                <p><strong>Diagnóstico:</strong> <?= e($ultima_atencion['diagnostico']) ?></p>
                <?php if ($ultima_atencion['tratamiento']): ?>
                    <p><strong>Tratamiento:</strong> <?= e($ultima_atencion['tratamiento']) ?></p>
                <?php endif; ?>
                <a href="<?= e(url('/paciente/historia.php')) ?>">Ver toda mi historia clínica &rarr;</a>
            <?php else: ?>
                <p class="vacio">Aún no tienes atenciones registradas.</p>
            <?php endif; ?>
        </section>

        <!-- Accesos rápidos -->
        <section class="tarjeta-paciente">
            <h2>¿Qué quieres hacer?</h2>
            <ul class="accesos">
                <li>
                    <a href="<?= e(url('/paciente/pedir-cita.php')) ?>">
                        <strong>Pedir una cita</strong>
                        <span>Elige el área y cuéntanos el motivo.</span>
                    </a>
                </li>
                <li>
                    <a href="<?= e(url('/paciente/citas.php')) ?>">
                        <strong>Ver mis citas</strong>
                        <span>Próximas, anteriores y el estado de tus solicitudes.</span>
                    </a>
                </li>
                <li>
                    <a href="<?= e(url('/paciente/historia.php')) ?>">
                        <strong>Mi historia clínica</strong>
                        <span>Diagnósticos y tratamientos de tus atenciones.</span>
                    </a>
                </li>
            </ul>
        </section>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
