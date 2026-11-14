<?php
// Programar una solicitud: admisión convierte lo que pidió el paciente en una
// cita confirmada (fecha, hora y médico). Después, la cita le aparece al
// médico en su agenda y al paciente en "Mis citas".
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/solicitudes.php';

// Regla 6: solo admin y admisión.
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

<header class="panel-titulo">
    <div>
        <h1>Programar solicitud</h1>
        <p>Elige fecha, hora y médico. La cita queda confirmada.</p>
    </div>
</header>

<p><a href="<?= e(url('/panel/solicitudes.php')) ?>">&larr; Volver a solicitudes</a></p>

<?php if ($error !== ''): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($solicitud): ?>
    <div class="panel-columnas">
        <!-- Lo que pidió el paciente -->
        <section class="caja">
            <h2>Lo que pidió el paciente</h2>
            <dl class="datos">
                <dt>Paciente</dt>
                <dd><?= e($solicitud['nombre']) ?></dd>
                <dt>Documento</dt>
                <dd><?= e($solicitud['tipo_documento'] . ' ' . $solicitud['numero_documento']) ?></dd>
                <dt>Teléfono</dt>
                <dd><?= e($solicitud['telefono'] ?? 'Sin teléfono') ?></dd>
                <dt>Área</dt>
                <dd><?= e($solicitud['area']) ?></dd>
                <dt>Fecha preferida</dt>
                <dd><?= $solicitud['fecha_preferida']
                    ? e(date('d/m/Y', strtotime($solicitud['fecha_preferida'])))
                    : 'Sin preferencia' ?></dd>
                <dt>Motivo</dt>
                <dd><?= e($solicitud['mensaje'] ?? '') ?></dd>
                <dt>Enviada el</dt>
                <dd><?= e(date('d/m/Y H:i', strtotime($solicitud['creado_en']))) ?></dd>
            </dl>
        </section>

        <!-- Formulario para programarla -->
        <section class="caja">
            <h2>Datos de la cita</h2>

            <?php if ($solicitud['estado'] !== 'nueva'): ?>
                <p class="vacio">Esta solicitud ya fue procesada (<?= e($solicitud['estado']) ?>).</p>
            <?php elseif ($medicos === []): ?>
                <p role="alert">
                    No hay médicos activos en <?= e($solicitud['area']) ?>.
                    Crea uno en "Usuarios del personal" antes de programar.
                </p>
            <?php else: ?>
                <form class="form-panel" method="post"
                      action="<?= e(url('/panel/programar-solicitud.php?id=' . $id)) ?>">
                    <?= csrf_campo() ?>

                    <div class="campo">
                        <label for="medico_id">Médico</label>
                        <select id="medico_id" name="medico_id" required>
                            <option value="">Selecciona un médico</option>
                            <?php foreach ($medicos as $medico): ?>
                                <option value="<?= (int) $medico['id'] ?>"
                                    <?= $valores['medico_id'] === (string) $medico['id'] ? 'selected' : '' ?>>
                                    <?= e($medico['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="fecha">Fecha</label>
                        <input type="date" id="fecha" name="fecha" required
                               min="<?= e(date('Y-m-d')) ?>"
                               value="<?= e($valores['fecha']) ?>">
                    </div>

                    <div class="campo">
                        <label for="hora">Hora</label>
                        <input type="time" id="hora" name="hora" required
                               min="07:00" max="20:00" step="900"
                               value="<?= e($valores['hora']) ?>">
                    </div>

                    <button class="boton" type="submit">Programar y confirmar cita</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
