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

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Mi historia clínica</h1>
            <p>Estas son las atenciones que tus médicos registraron.</p>
        </div>
    </header>

    <div class="ui-aviso">
        <?= $icono_panel('i-escudo') ?>
        <p>
            Solo las ves tú y el médico que te atiende, y <strong>cada consulta queda registrada</strong>
            (abajo puedes ver quién las consultó).
        </p>
    </div>

    <?php if (count($atenciones) === 0): ?>
        <section class="ui-tarjeta">
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-documento') ?></span>
                <strong>Todavía no tienes atenciones registradas.</strong>
                <p>Cuando un médico registre una atención, aparecerá aquí.</p>
            </div>
        </section>
    <?php else: ?>
        <!-- Línea de tiempo: de la atención más reciente a la más antigua -->
        <ol class="portal-linea">
            <?php foreach ($atenciones as $atencion): ?>
                <li class="ui-tarjeta portal-atencion">
                    <header class="portal-atencion-cabecera">
                        <time datetime="<?= e($atencion['fecha']) ?>"><?= e(date('d/m/Y', strtotime($atencion['fecha']))) ?></time>
                        <span><?= e($atencion['area']) ?></span>
                        <span><?= e($atencion['medico']) ?></span>
                    </header>

                    <dl class="datos datos-rejilla">
                        <div class="datos-ancho">
                            <dt>Motivo</dt>
                            <dd><?= e($atencion['motivo'] ?? 'No registrado') ?></dd>
                        </div>
                        <div>
                            <dt>Diagnóstico</dt>
                            <dd><?= e($atencion['diagnostico']) ?></dd>
                        </div>
                        <div>
                            <dt>Tratamiento</dt>
                            <dd><?= e($atencion['tratamiento'] ?? 'No indicado') ?></dd>
                        </div>
                        <?php if ($atencion['notas'] !== null): ?>
                            <div class="datos-ancho">
                                <dt>Notas del médico</dt>
                                <dd><?= e($atencion['notas']) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <!-- Quién consultó mi historia (Ley 29733: derecho a saber quién usa tus datos) -->
    <section class="ui-tarjeta">
        <div class="ui-tarjeta-cabecera">
            <h2>¿Quién consultó mi historia?</h2>
            <p class="ui-nota">Últimas consultas del personal de la clínica a tus datos clínicos.</p>
        </div>
        <?php if ($accesos === []): ?>
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-escudo') ?></span>
                <strong>Nadie del personal ha consultado tu historia todavía.</strong>
            </div>
        <?php else: ?>
            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable" role="table">
                    <caption class="solo-lectores">Consultas del personal a mi historia</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Fecha y hora</th>
                            <th scope="col" role="columnheader">Quién</th>
                            <th scope="col" role="columnheader">Qué hizo</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($accesos as $acceso): ?>
                            <tr role="row">
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Fecha y hora"><?= e(date('d/m/Y H:i', strtotime($acceso['creado_en']))) ?></td>
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Quién">
                                    <span class="ui-celda-valor">
                                        <?= e($acceso['usuario']) ?>
                                        <small><?= e($nombres_rol[$acceso['rol']] ?? $acceso['rol']) ?></small>
                                    </span>
                                </td>
                                <td role="cell" data-etiqueta="Qué hizo"><?= e(acciones_auditoria()[$acceso['accion']] ?? $acceso['accion']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
