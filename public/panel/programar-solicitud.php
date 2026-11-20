<?php
// Programar una solicitud: admisión convierte lo que pidió el paciente en una
// cita confirmada (fecha, hora y médico). Después, la cita le aparece al
// médico en su agenda y al paciente en "Mis citas".
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/solicitudes.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

// Regla 6: solo admin y admisión.
requerir_login();
requerir_rol(['admin', 'admision']);
iniciar_sesion_segura();

$titulo_pagina = 'Programar solicitud';
$pagina_activa = 'solicitudes.php';
$error = '';
$solicitud = null;
$medicos = [];

// ID de la solicitud (viene en la dirección: ?id=5).
$id = filter_var(
    is_string($_GET['id'] ?? null) ? $_GET['id'] : '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false) {
    mostrar_error(404, true);
}

$valores = ['fecha' => '', 'hora' => '', 'medico_id' => ''];

try {
    $solicitud = obtener_solicitud($id);

    if ($solicitud === null) {
        mostrar_error(404, true);
    }

    // Médicos activos del área que pidió el paciente.
    $consulta = db()->prepare(
        "SELECT id, nombre FROM usuarios
         WHERE rol = 'medico' AND activo = 1 AND area_id = :area
         ORDER BY nombre"
    );
    $consulta->execute(['area' => $solicitud['area_id']]);
    $medicos = $consulta->fetchAll();
} catch (PDOException $excepcion) {
    error_log($excepcion->getMessage());
    $error = 'No se pudo cargar la solicitud.';
}

// Si el paciente indicó una fecha futura, se propone esa.
if ($solicitud && $solicitud['fecha_preferida'] >= date('Y-m-d')) {
    $valores['fecha'] = $solicitud['fecha_preferida'];
}

// Si el área tiene un solo médico, ya queda elegido.
if (count($medicos) === 1) {
    $valores['medico_id'] = (string) $medicos[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $solicitud) {
    foreach ($valores as $campo => $vacio) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
    }

    try {
        // Regla 4: token CSRF.
        if (!csrf_verificar()) {
            throw new InvalidArgumentException(
                'La sesión del formulario venció. Recarga la página.'
            );
        }

        // Quién programa la cita sale de la SESIÓN, no del formulario.
        $cita_id = convertir_solicitud_en_cita($id, $valores, usuario_actual()['id']);
        registrar_auditoria(
            'programar_cita',
            (int) $solicitud['paciente_id'],
            'Solicitud ' . $id . ' → cita ' . $cita_id
        );

        // Se muestra la cita recién creada, con su mensaje de éxito.
        $_SESSION['gestion_cita_mensaje'] = 'Cita programada y confirmada. '
            . 'Ya aparece en la agenda del médico y en el portal del paciente.';
        redirigir('/panel/gestionar-cita.php?id=' . $cita_id);
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        // Regla 9: mensaje genérico; el detalle va al log.
        error_log($excepcion->getMessage());
        $error = 'No se pudo programar la cita.';
    }
}

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<div class="ui-pagina ui-animada">

    <a class="ui-migas" href="<?= e(url('/panel/solicitudes.php')) ?>">
        <?= $icono_panel('i-volver') ?>
        Volver a solicitudes
    </a>

    <header class="ui-cabecera">
        <div>
            <h1>Programar solicitud</h1>
            <p>Elige fecha, hora y médico. La cita queda confirmada.</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($solicitud): ?>
        <div class="ui-columnas">
            <!-- Lo que pidió el paciente -->
            <section class="ui-tarjeta">
                <h2>Lo que pidió el paciente</h2>
                <dl class="datos datos-rejilla">
                    <div class="datos-ancho">
                        <dt>Paciente</dt>
                        <dd><?= e($solicitud['nombre']) ?></dd>
                    </div>
                    <div>
                        <dt>Documento</dt>
                        <dd><?= e($solicitud['tipo_documento'] . ' ' . $solicitud['numero_documento']) ?></dd>
                    </div>
                    <div>
                        <dt>Teléfono</dt>
                        <dd><?= e($solicitud['telefono'] ?? 'Sin teléfono') ?></dd>
                    </div>
                    <div>
                        <dt>Área</dt>
                        <dd><?= e($solicitud['area']) ?></dd>
                    </div>
                    <div>
                        <dt>Fecha preferida</dt>
                        <dd><?= $solicitud['fecha_preferida']
                            ? e(date('d/m/Y', strtotime($solicitud['fecha_preferida'])))
                            : 'Sin preferencia' ?></dd>
                    </div>
                    <div class="datos-ancho">
                        <dt>Motivo</dt>
                        <dd><?= e($solicitud['mensaje'] ?? '') ?></dd>
                    </div>
                    <div class="datos-ancho">
                        <dt>Enviada el</dt>
                        <dd><?= e(date('d/m/Y H:i', strtotime($solicitud['creado_en']))) ?></dd>
                    </div>
                </dl>
            </section>

            <!-- Formulario para programarla -->
            <section class="ui-tarjeta">
                <h2>Datos de la cita</h2>

                <?php if ($solicitud['estado'] !== 'nueva'): ?>
                    <div class="ui-vacio">
                        <span class="ui-vacio-icono"><?= $icono_panel('i-visto') ?></span>
                        <strong>Esta solicitud ya fue procesada (<?= e($solicitud['estado']) ?>).</strong>
                        <p>No se puede programar de nuevo.</p>
                        <a class="ui-boton ui-boton-secundario" href="<?= e(url('/panel/solicitudes.php')) ?>">Ver solicitudes</a>
                    </div>
                <?php elseif ($medicos === []): ?>
                    <div class="ui-aviso ui-aviso-error" role="alert">
                        <?= $icono_panel('i-alerta') ?>
                        <p>
                            No hay médicos activos en <?= e($solicitud['area']) ?>.
                            Crea uno en "Usuarios del personal" antes de programar.
                        </p>
                    </div>
                <?php else: ?>
                    <!-- data-cargando: al enviar, el botón muestra "Programando…" y evita el doble envío -->
                    <form method="post"
                          action="<?= e(url('/panel/programar-solicitud.php?id=' . $id)) ?>" data-cargando>
                        <?= csrf_campo() ?>

                        <div class="ui-rejilla">
                            <div class="ui-campo ui-campo-ancho">
                                <label for="medico_id">Médico <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                                <select class="ui-entrada" id="medico_id" name="medico_id" required
                                        aria-describedby="ayuda-medico">
                                    <option value="">Selecciona un médico</option>
                                    <?php foreach ($medicos as $medico): ?>
                                        <option value="<?= (int) $medico['id'] ?>"
                                            <?= $valores['medico_id'] === (string) $medico['id'] ? 'selected' : '' ?>>
                                            <?= e($medico['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="ui-ayuda" id="ayuda-medico">Médicos activos de <?= e($solicitud['area']) ?>.</span>
                            </div>

                            <div class="ui-campo">
                                <label for="fecha">Fecha <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                                <input class="ui-entrada" type="date" id="fecha" name="fecha" required
                                       min="<?= e(date('Y-m-d')) ?>"
                                       aria-describedby="ayuda-fecha"
                                       value="<?= e($valores['fecha']) ?>">
                                <span class="ui-ayuda" id="ayuda-fecha">
                                    <?= e($solicitud['fecha_preferida']
                                        ? 'El paciente prefiere el ' . date('d/m/Y', strtotime($solicitud['fecha_preferida'])) . '.'
                                        : 'Desde hoy en adelante.') ?>
                                </span>
                            </div>

                            <div class="ui-campo">
                                <label for="hora">Hora <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                                <input class="ui-entrada" type="time" id="hora" name="hora" required
                                       min="07:00" max="20:00" step="900"
                                       aria-describedby="ayuda-hora"
                                       value="<?= e($valores['hora']) ?>">
                                <span class="ui-ayuda" id="ayuda-hora">Entre 07:00 y 20:00, cada 15 minutos.</span>
                            </div>
                        </div>

                        <div class="ui-acciones">
                            <button class="ui-boton" type="submit" data-texto-cargando="Programando…">
                                Programar y confirmar cita
                            </button>
                            <a class="ui-boton ui-boton-secundario" href="<?= e(url('/panel/solicitudes.php')) ?>">Cancelar</a>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
