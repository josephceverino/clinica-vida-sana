<?php
// Atender una cita: el MÉDICO registra el diagnóstico, el tratamiento y las
// notas. Al guardar, la cita pasa a "atendida".
// Solo entra el médico, y solo a sus propias citas.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/atenciones.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

// Regla 6: panel protegido. Solo médicos (ni admin ni admisión leen ni
// escriben datos clínicos).
requerir_rol(['medico']);

$titulo_pagina = 'Atender cita';
$pagina_activa = 'citas.php';
$yo = usuario_actual();

// El id de la cita viene de la dirección; se valida como número entero.
$cita_id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

// Si la cita no existe o es de otro médico, el mensaje es el mismo (404):
// así nadie puede averiguar qué citas existen.
$cita = $cita_id === false ? null : cita_del_medico($cita_id, $yo['id']);
if ($cita === null) {
    mostrar_error(404, true);
}

$error = '';
$valores = ['diagnostico' => '', 'tratamiento' => '', 'notas' => ''];

// Auditoría: el médico está viendo datos del paciente (y su atención, si
// ya existe). Solo al ABRIR la página (GET); el guardado se anota aparte.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    registrar_auditoria('ver_atencion', (int) $cita['paciente_id'], 'Cita ' . (int) $cita['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valores as $campo => $vacio) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
    }

    try {
        // Regla 4: todo formulario POST lleva token CSRF.
        if (!csrf_verificar()) {
            throw new InvalidArgumentException(
                'La sesión del formulario venció. Recarga la página.'
            );
        }

        // El médico sale de la SESIÓN y el paciente de la CITA. Nada de eso
        // se lee del formulario.
        registrar_atencion($cita['id'], $yo['id'], $valores);
        registrar_auditoria('registrar_atencion', (int) $cita['paciente_id'], 'Cita ' . (int) $cita['id']);

        mensaje_guardar('Atención registrada. La cita quedó como atendida.');
        redirigir('/panel/atender-cita.php?id=' . (int) $cita['id']);
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        // Regla 9: al usuario un mensaje genérico; el detalle va al log.
        error_log($excepcion->getMessage());
        $error = 'No se pudo guardar la atención. Inténtalo más tarde.';
    } catch (Exception $excepcion) {
        // Cualquier otro fallo (ej. una fecha mal guardada): mensaje genérico
        // en vez de una pantalla de error. Lo escrito sigue en el formulario.
        error_log($excepcion->getMessage());
        $error = 'No se pudo guardar la atención. Inténtalo más tarde.';
    }

    // Si no se guardó, se vuelve a leer la cita: quizá cambió mientras el
    // médico escribía (la atendió en otra pestaña o admisión la canceló).
    // Así la pantalla muestra el estado real y no un formulario que ya no sirve.
    $cita = cita_del_medico($cita['id'], $yo['id']) ?? $cita;
}

$mensaje = mensaje_tomar();
// Está atendida si tiene atención guardada (el diagnóstico es obligatorio).
$ya_atendida = $cita['diagnostico'] !== null;
$puede_atender = $cita['estado'] === 'confirmada'
    && cita_ya_llego($cita['fecha'], $cita['hora']);

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<header class="panel-titulo">
    <div>
        <h1>Atender cita</h1>
        <p><a href="<?= e(url('/panel/citas.php?fecha=' . $cita['fecha'])) ?>">&larr; Volver a mis citas</a></p>
    </div>
    <span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span>
</header>

<?php if ($mensaje !== null): ?>
    <p class="aviso aviso-<?= e($mensaje['tipo']) ?>" role="status"><?= e($mensaje['texto']) ?></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <!-- El error se muestra siempre, aunque la cita ya no admita el formulario. -->
    <p class="aviso aviso-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>

<section class="caja">
    <h2>Paciente</h2>
    <dl class="datos">
        <dt>Nombre</dt>
        <dd><?= e($cita['nombres']) ?> <?= e($cita['apellidos']) ?></dd>
        <dt>Documento</dt>
        <dd><?= e($cita['tipo_documento']) ?> <?= e($cita['numero_documento']) ?></dd>
        <dt>Fecha de nacimiento</dt>
        <dd><?= e($cita['fecha_nacimiento'] !== null
            ? date('d/m/Y', strtotime($cita['fecha_nacimiento'])) : 'No registrada') ?></dd>
        <dt>Cita</dt>
        <dd><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?> a las <?= e(substr($cita['hora'], 0, 5)) ?> &middot; <?= e($cita['area']) ?></dd>
        <dt>Motivo</dt>
        <dd><?= e($cita['motivo'] ?? 'Sin motivo registrado') ?></dd>
    </dl>
</section>

<?php if ($ya_atendida): ?>
    <!-- La cita ya fue atendida: se muestra la atención (solo lectura). -->
    <section class="caja">
        <h2>Atención registrada</h2>
        <dl class="datos">
            <dt>Diagnóstico</dt>
            <dd><?= e($cita['diagnostico']) ?></dd>
            <dt>Tratamiento</dt>
            <dd><?= e($cita['tratamiento'] ?? 'No indicado') ?></dd>
            <dt>Notas</dt>
            <dd><?= e($cita['notas'] ?? 'Sin notas') ?></dd>
            <dt>Registrada el</dt>
            <dd><?= e(date('d/m/Y H:i', strtotime($cita['atendida_en']))) ?></dd>
        </dl>
    </section>

<?php elseif ($puede_atender): ?>
    <section class="caja">
        <h2>Registrar la atención</h2>

        <form method="post" action="<?= e(url('/panel/atender-cita.php?id=' . (int) $cita['id'])) ?>">
            <?= csrf_campo() ?>

            <p>
                <label for="diagnostico">Diagnóstico *</label><br>
                <textarea id="diagnostico" name="diagnostico" rows="3" minlength="5"
                          maxlength="500" required><?= e($valores['diagnostico']) ?></textarea>
            </p>

            <p>
                <label for="tratamiento">Tratamiento</label><br>
                <textarea id="tratamiento" name="tratamiento" rows="3"
                          maxlength="500"><?= e($valores['tratamiento']) ?></textarea>
            </p>

            <p>
                <label for="notas">Notas</label><br>
                <textarea id="notas" name="notas" rows="4"
                          maxlength="1000"><?= e($valores['notas']) ?></textarea>
            </p>

            <button class="boton" type="submit">Guardar atención</button>
        </form>
    </section>

<?php else: ?>
    <!-- Todavía no se puede atender: se explica por qué. -->
    <section class="caja">
        <h2>Esta cita todavía no se puede atender</h2>
        <p class="vacio">
            <?php if ($cita['estado'] === 'pendiente'): ?>
                Admisión aún no la confirmó.
            <?php elseif ($cita['estado'] === 'cancelada'): ?>
                La cita fue cancelada.
            <?php elseif ($cita['estado'] === 'confirmada'): ?>
                Aún no llega la fecha y la hora de la cita.
            <?php else: ?>
                No hay una atención registrada para esta cita.
            <?php endif; ?>
        </p>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
