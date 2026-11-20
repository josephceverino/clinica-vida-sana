<?php
// "Mis citas": las citas y solicitudes del paciente que inició sesión.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/citas.php';
require_once __DIR__ . '/../../app/modelos/solicitudes.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

// Solo pacientes con la cuenta verificada por admisión.
requerir_paciente_verificado();

$titulo_pagina = 'Mis citas';
$pagina_activa = 'citas.php';

// Importante: el paciente sale de la SESIÓN. Nunca de la dirección ni de un
// formulario, así nadie puede pedir las citas de otra persona.
$paciente_id = (int) usuario_actual()['paciente_id'];

// --- Cancelar una cita (POST) ----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Regla 4: token CSRF.
        if (!csrf_verificar()) {
            throw new InvalidArgumentException('La sesión del formulario venció. Recarga la página.');
        }

        $cita_id = (int) ($_POST['cita_id'] ?? 0);

        // Solo puede cancelar SUS citas: el paciente sale de la sesión.
        cancelar_cita_paciente($cita_id, $paciente_id);
        registrar_auditoria('cancelar_cita', $paciente_id, 'Cita ' . $cita_id);

        mensaje_guardar('Tu cita fue cancelada. Ese horario quedó libre para otra persona.');
    } catch (InvalidArgumentException $excepcion) {
        mensaje_guardar($excepcion->getMessage(), 'error');
    } catch (PDOException $excepcion) {
        // Regla 9: mensaje genérico; el detalle va al log.
        error_log($excepcion->getMessage());
        mensaje_guardar('No se pudo cancelar la cita. Inténtalo más tarde.', 'error');
    }

    redirigir('/paciente/citas.php');
}

$citas = citas_del_paciente($paciente_id);
$solicitudes = solicitudes_del_paciente($paciente_id);
$mensaje = mensaje_tomar();

// Separamos las citas que vienen de las que ya pasaron o se cancelaron.
$hoy = date('Y-m-d');
$proximas = [];
$anteriores = [];
foreach ($citas as $cita) {
    $vigente = in_array($cita['estado'], ['pendiente', 'confirmada'], true);
    if ($vigente && $cita['fecha'] >= $hoy) {
        $proximas[] = $cita;
    } else {
        $anteriores[] = $cita;
    }
}
// Las próximas se muestran de la más cercana a la más lejana.
$proximas = array_reverse($proximas);

require __DIR__ . '/../../app/layout/paciente_header.php';
?>

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Mis citas</h1>
            <p>Tus próximas citas, tus solicitudes y tu historial.</p>
        </div>
        <a class="ui-boton" href="<?= e(url('/paciente/pedir-cita.php')) ?>">
            <?= $icono_panel('i-agregar') ?>
            Pedir una cita
        </a>
    </header>

    <?php if ($mensaje !== null): ?>
        <div class="ui-aviso <?= $mensaje['tipo'] === 'error' ? 'ui-aviso-error' : 'ui-aviso-exito' ?>"
             role="<?= $mensaje['tipo'] === 'error' ? 'alert' : 'status' ?>">
            <?= $icono_panel($mensaje['tipo'] === 'error' ? 'i-alerta' : 'i-visto') ?>
            <p><?= e($mensaje['texto']) ?></p>
        </div>
    <?php endif; ?>

    <!-- Próximas citas -->
    <section class="ui-tarjeta">
        <div class="ui-tarjeta-cabecera">
            <h2>Próximas citas</h2>
            <?php if (count($proximas) > 0): ?>
                <span class="ui-insignia"><?= count($proximas) ?></span>
            <?php endif; ?>
        </div>
        <?php if (count($proximas) === 0): ?>
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-calendario') ?></span>
                <strong>No tienes citas próximas.</strong>
                <p>Reserva un horario libre en «Pedir una cita».</p>
            </div>
        <?php else: ?>
            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable" role="table">
                    <caption class="solo-lectores">Próximas citas</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Fecha</th>
                            <th scope="col" role="columnheader">Hora</th>
                            <th scope="col" role="columnheader">Área</th>
                            <th scope="col" role="columnheader">Médico</th>
                            <th scope="col" role="columnheader">Estado</th>
                            <th scope="col" role="columnheader"><span class="solo-lectores">Acción</span></th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($proximas as $cita): ?>
                            <tr role="row">
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Fecha"><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?></td>
                                <td role="cell" data-etiqueta="Hora"><?= e(substr($cita['hora'], 0, 5)) ?></td>
                                <td role="cell" data-etiqueta="Área"><?= e($cita['area']) ?></td>
                                <td role="cell" data-etiqueta="Médico"><?= e($cita['medico'] ?? 'Por asignar') ?></td>
                                <td role="cell" data-etiqueta="Estado"><span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span></td>
                                <td role="cell" data-etiqueta="Acción">
                                    <?php if (puede_cancelar_paciente($cita)): ?>
                                        <form method="post" action="<?= e(url('/paciente/citas.php')) ?>" data-cargando>
                                            <?= csrf_campo() ?>
                                            <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
                                            <button class="ui-boton ui-boton-peligro ui-boton-chico" type="submit"
                                                    data-texto-cargando="Cancelando…">Cancelar</button>
                                        </form>
                                    <?php else: ?>
                                        <small class="ui-nota">Para cambios, llama a admisión</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Mis solicitudes -->
    <section class="ui-tarjeta">
        <div class="ui-tarjeta-cabecera">
            <h2>Mis solicitudes</h2>
            <p class="ui-nota">Son las citas que pediste. Admisión las revisa y te confirma.</p>
        </div>
        <?php if (count($solicitudes) === 0): ?>
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-documento') ?></span>
                <strong>No has pedido ninguna cita todavía.</strong>
            </div>
        <?php else: ?>
            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable ui-tabla-apilable-ancha" role="table">
                    <caption class="solo-lectores">Mis solicitudes</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Pedida el</th>
                            <th scope="col" role="columnheader">Área</th>
                            <th scope="col" role="columnheader">Fecha preferida</th>
                            <th scope="col" role="columnheader">Motivo</th>
                            <th scope="col" role="columnheader">Estado</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($solicitudes as $solicitud): ?>
                            <tr role="row">
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Pedida el"><?= e(date('d/m/Y', strtotime($solicitud['creado_en']))) ?></td>
                                <td role="cell" data-etiqueta="Área"><?= e($solicitud['area']) ?></td>
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Fecha preferida"><?= e($solicitud['fecha_preferida'] !== null
                                    ? date('d/m/Y', strtotime($solicitud['fecha_preferida']))
                                    : 'Sin preferencia') ?></td>
                                <td role="cell" data-etiqueta="Motivo"><?= e($solicitud['mensaje']) ?></td>
                                <td role="cell" data-etiqueta="Estado">
                                    <span class="ui-celda-valor">
                                        <?php [$clase, $texto] = etiqueta_solicitud($solicitud); ?>
                                        <?php if ($solicitud['estado'] === 'nueva'): ?>
                                            <span class="estado estado-nueva">en revisión</span>
                                        <?php else: ?>
                                            <span class="estado estado-<?= e($clase) ?>"><?= e($texto) ?></span>
                                        <?php endif; ?>
                                        <?php if ($solicitud['cita_id']): ?>
                                            <!-- Admisión la convirtió en cita: le decimos de cuándo era. -->
                                            <small>
                                                Cita del <?= e(date('d/m/Y', strtotime($solicitud['cita_fecha']))) ?>
                                                a las <?= e(substr($solicitud['cita_hora'], 0, 5)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Historial -->
    <?php if (count($anteriores) > 0): ?>
        <section class="ui-tarjeta">
            <h2>Historial de citas</h2>
            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable" role="table">
                    <caption class="solo-lectores">Historial de citas</caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Fecha</th>
                            <th scope="col" role="columnheader">Área</th>
                            <th scope="col" role="columnheader">Motivo</th>
                            <th scope="col" role="columnheader">Estado</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($anteriores as $cita): ?>
                            <tr role="row">
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Fecha"><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?></td>
                                <td role="cell" data-etiqueta="Área"><?= e($cita['area']) ?></td>
                                <td role="cell" data-etiqueta="Motivo"><?= e($cita['motivo']) ?></td>
                                <td role="cell" data-etiqueta="Estado"><span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
