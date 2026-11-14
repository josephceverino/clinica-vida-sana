<?php
// Historia clínica: las atenciones del paciente que inició sesión (solo lectura).
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/atenciones.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

// Solo pacientes con la cuenta verificada por admisión: así nadie ve datos
// clínicos con una cuenta que aún no comprobó su identidad.
requerir_paciente_verificado();

$titulo_pagina = 'Historia clínica';
$pagina_activa = 'historia.php';

// El paciente sale de la SESIÓN. Nunca de la dirección ni de un formulario,
// así nadie puede pedir la historia de otra persona.
$paciente_id = (int) usuario_actual()['paciente_id'];
$atenciones = atenciones_del_paciente($paciente_id);

// Transparencia: quién del personal consultó sus datos clínicos.
$accesos = accesos_a_mi_historia($paciente_id, 10);

// Auditoría: el paciente vio su propia historia.
registrar_auditoria('ver_historia', $paciente_id);

$nombres_rol = ['admin' => 'Administración', 'admision' => 'Admisión', 'medico' => 'Médico'];

require __DIR__ . '/../../app/layout/paciente_header.php';
?>

<section class="seccion">
    <p class="etiqueta">Portal del paciente</p>
    <h1>Mi historia clínica</h1>
    <p>
        Estas son las atenciones que tus médicos registraron. Solo las ves tú y
        el médico que te atiende, y <strong>cada consulta queda registrada</strong>
        (abajo puedes ver quién las consultó).
    </p>
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

<!-- Quién consultó mi historia (Ley 29733: derecho a saber quién usa tus datos) -->
<section class="seccion">
    <h2>¿Quién consultó mi historia?</h2>
    <p>Últimas consultas del personal de la clínica a tus datos clínicos.</p>
    <?php if ($accesos === []): ?>
        <p class="vacio">Nadie del personal ha consultado tu historia todavía.</p>
    <?php else: ?>
        <div class="tabla-scroll">
            <table class="lista-paciente">
                <thead>
                    <tr>
                        <th scope="col">Fecha y hora</th>
                        <th scope="col">Quién</th>
                        <th scope="col">Qué hizo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accesos as $acceso): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime($acceso['creado_en']))) ?></td>
                            <td>
                                <?= e($acceso['usuario']) ?>
                                <small class="vacio">(<?= e($nombres_rol[$acceso['rol']] ?? $acceso['rol']) ?>)</small>
                            </td>
                            <td><?= e(acciones_auditoria()[$acceso['accion']] ?? $acceso['accion']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
