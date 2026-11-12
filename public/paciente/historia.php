<?php
// Historia clínica: las atenciones del paciente que inició sesión (solo lectura).
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/atenciones.php';

// Solo pacientes con la cuenta verificada por admisión: así nadie ve datos
// clínicos con una cuenta que aún no comprobó su identidad.
requerir_paciente_verificado();

$titulo_pagina = 'Historia clínica';
$pagina_activa = 'historia.php';

// El paciente sale de la SESIÓN. Nunca de la dirección ni de un formulario,
// así nadie puede pedir la historia de otra persona.
$atenciones = atenciones_del_paciente((int) usuario_actual()['paciente_id']);

require __DIR__ . '/../../app/layout/paciente_header.php';
?>

<section class="seccion">
    <p class="etiqueta">Portal del paciente</p>
    <h1>Mi historia clínica</h1>
    <p>Estas son las atenciones que tus médicos registraron. Solo tú puedes verlas.</p>
</section>

<?php if (count($atenciones) === 0): ?>
    <section class="seccion">
        <p class="vacio">Todavía no tienes atenciones registradas.</p>
    </section>
<?php else: ?>
    <?php foreach ($atenciones as $atencion): ?>
        <article class="atencion">
            <header class="atencion-cabecera">
                <strong><?= e(date('d/m/Y', strtotime($atencion['fecha']))) ?></strong>
                <span><?= e($atencion['area']) ?></span>
                <span><?= e($atencion['medico']) ?></span>
            </header>

            <dl class="atencion-datos">
                <dt>Motivo</dt>
                <dd><?= e($atencion['motivo'] ?? 'No registrado') ?></dd>

                <dt>Diagnóstico</dt>
                <dd><?= e($atencion['diagnostico']) ?></dd>

                <dt>Tratamiento</dt>
                <dd><?= e($atencion['tratamiento'] ?? 'No indicado') ?></dd>

                <?php if ($atencion['notas'] !== null): ?>
                    <dt>Notas del médico</dt>
                    <dd><?= e($atencion['notas']) ?></dd>
                <?php endif; ?>
            </dl>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
