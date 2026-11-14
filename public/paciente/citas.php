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

<section class="seccion">
    <p class="etiqueta">Portal del paciente</p>
    <h1>Mis citas</h1>

    <?php if ($mensaje !== null): ?>
        <p class="aviso aviso-<?= e($mensaje['tipo']) ?>" role="status"><?= e($mensaje['texto']) ?></p>
    <?php endif; ?>

    <p><a class="boton" href="<?= e(url('/paciente/pedir-cita.php')) ?>">Pedir una cita</a></p>
</section>

<!-- Próximas citas -->
<section class="seccion">
    <h2>Próximas citas</h2>
    <?php if (count($proximas) === 0): ?>
        <p class="vacio">No tienes citas próximas.</p>
    <?php else: ?>
        <div class="tabla-scroll">
            <table class="lista-paciente">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Hora</th>
                        <th scope="col">Área</th>
                        <th scope="col">Médico</th>
                        <th scope="col">Estado</th>
                        <th scope="col"><span class="solo-lectores">Acción</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proximas as $cita): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?></td>
                            <td><?= e(substr($cita['hora'], 0, 5)) ?></td>
                            <td><?= e($cita['area']) ?></td>
                            <td><?= e($cita['medico'] ?? 'Por asignar') ?></td>
                            <td><span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span></td>
                            <td>
                                <?php if (puede_cancelar_paciente($cita)): ?>
                                    <form method="post" action="<?= e(url('/paciente/citas.php')) ?>">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
                                        <button class="boton-cancelar" type="submit">Cancelar</button>
                                    </form>
                                <?php else: ?>
                                    <small class="vacio">Para cambios, llama a admisión</small>
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
<section class="seccion">
    <h2>Mis solicitudes</h2>
    <p>Son las citas que pediste. Admisión las revisa y te confirma.</p>
    <?php if (count($solicitudes) === 0): ?>
        <p class="vacio">No has pedido ninguna cita todavía.</p>
    <?php else: ?>
        <div class="tabla-scroll">
            <table class="lista-paciente">
                <thead>
                    <tr>
                        <th scope="col">Pedida el</th>
                        <th scope="col">Área</th>
                        <th scope="col">Fecha preferida</th>
                        <th scope="col">Motivo</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $solicitud): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime($solicitud['creado_en']))) ?></td>
                            <td><?= e($solicitud['area']) ?></td>
                            <td><?= e($solicitud['fecha_preferida'] !== null
                                ? date('d/m/Y', strtotime($solicitud['fecha_preferida']))
                                : 'Sin preferencia') ?></td>
                            <td><?= e($solicitud['mensaje']) ?></td>
                            <td>
                                <?php if ($solicitud['estado'] === 'atendida' && $solicitud['cita_id']): ?>
                                    <!-- Admisión la convirtió en cita: le decimos cuándo es. -->
                                    <span class="estado estado-confirmada">programada</span><br>
                                    <small>
                                        Cita el <?= e(date('d/m/Y', strtotime($solicitud['cita_fecha']))) ?>
                                        a las <?= e(substr($solicitud['cita_hora'], 0, 5)) ?>
                                    </small>
                                <?php elseif ($solicitud['estado'] === 'nueva'): ?>
                                    <span class="estado estado-nueva">en revisión</span>
                                <?php else: ?>
                                    <span class="estado estado-<?= e($solicitud['estado']) ?>"><?= e($solicitud['estado']) ?></span>
                                <?php endif; ?>
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
    <section class="seccion">
        <h2>Historial de citas</h2>
        <div class="tabla-scroll">
            <table class="lista-paciente">
                <thead>
                    <tr>
                        <th scope="col">Fecha</th>
                        <th scope="col">Área</th>
                        <th scope="col">Motivo</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($anteriores as $cita): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?></td>
                            <td><?= e($cita['area']) ?></td>
                            <td><?= e($cita['motivo']) ?></td>
                            <td><span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
