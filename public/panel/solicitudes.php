<?php
// Solicitudes de cita que envían los pacientes desde su portal.
// Admisión decide: "Programar cita" (la convierte en cita con médico) o
// "Descartar" (por ejemplo, si está repetida).
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/solicitudes.php';

// Regla 6: solo admin y admisión.
requerir_rol(['admin', 'admision']);
iniciar_sesion_segura();

$titulo_pagina = 'Solicitudes de cita';
$pagina_activa = 'solicitudes.php';
$error = '';
$solicitudes = [];

// Pestañas: estado de la base => texto que se muestra.
// '' (vacío) significa "todas".
$filtros = [
    'nueva'      => 'Nuevas',
    'atendida'   => 'Programadas',
    'descartada' => 'Descartadas',
    ''           => 'Todas',
];

$filtro = is_string($_GET['estado'] ?? null) ? $_GET['estado'] : 'nueva';
if (!array_key_exists($filtro, $filtros)) {
    $filtro = 'nueva';
}

// --- Descartar una solicitud (POST) ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Regla 4: token CSRF.
        if (!csrf_verificar()) {
            throw new InvalidArgumentException(
                'La sesión del formulario venció. Recarga la página.'
            );
        }

        $id = filter_var(
            is_string($_POST['solicitud_id'] ?? null) ? $_POST['solicitud_id'] : '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            throw new InvalidArgumentException('La solicitud no es válida.');
        }

        // Solo se puede descartar. Para aceptarla se usa "Programar cita".
        cambiar_estado_solicitud($id, 'descartada', usuario_actual()['id']);

        mensaje_guardar('Solicitud descartada.');
        redirigir('/panel/solicitudes.php?estado=' . $filtro);
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudo actualizar la solicitud.';
    }
}

try {
    $solicitudes = buscar_solicitudes($filtro);
} catch (PDOException $excepcion) {
    error_log($excepcion->getMessage());
    $error = 'No se pudieron consultar las solicitudes.';
}

$mensaje = mensaje_tomar();

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<header class="panel-titulo">
    <div>
        <h1>Solicitudes de cita</h1>
        <p>Lo que piden los pacientes desde su portal. Prográmalas como cita o descártalas.</p>
    </div>
</header>

<?php if ($mensaje): ?>
    <p role="status"><?= e($mensaje['texto']) ?></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<nav class="pestanas" aria-label="Filtro de solicitudes">
    <?php foreach ($filtros as $clave => $texto): ?>
        <a href="<?= e(url('/panel/solicitudes.php?estado=' . $clave)) ?>"
           class="<?= $clave === $filtro ? 'activo' : '' ?>"><?= e($texto) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($solicitudes === [] && $error === ''): ?>
    <section class="caja">
        <p class="vacio">No hay solicitudes en esta lista.</p>
    </section>
<?php else: ?>
    <div class="caja tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Área</th>
                    <th>Fecha preferida</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($solicitudes as $solicitud): ?>
                    <tr>
                        <td>
                            <strong><?= e($solicitud['nombre']) ?></strong><br>
                            <small>
                                <?= e($solicitud['tipo_documento'] . ' ' . $solicitud['numero_documento']) ?>
                                &middot; Tel. <?= e($solicitud['telefono'] ?? '—') ?>
                            </small>
                        </td>
                        <td><?= e($solicitud['area']) ?></td>
                        <td>
                            <?= $solicitud['fecha_preferida']
                                ? e(date('d/m/Y', strtotime($solicitud['fecha_preferida'])))
                                : '<span class="vacio">Sin preferencia</span>' ?>
                        </td>
                        <td><?= e($solicitud['mensaje'] ?? '') ?></td>
                        <td>
                            <?php if ($solicitud['estado'] === 'atendida'): ?>
                                <span class="estado estado-confirmada">programada</span>
                            <?php else: ?>
                                <span class="estado estado-<?= e($solicitud['estado']) ?>">
                                    <?= e($solicitud['estado']) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($solicitud['estado'] === 'nueva'): ?>
                                <div class="acciones-fila">
                                    <a class="boton-tabla"
                                       href="<?= e(url('/panel/programar-solicitud.php?id=' . $solicitud['id'])) ?>">
                                        Programar cita
                                    </a>
                                    <form method="post"
                                          action="<?= e(url('/panel/solicitudes.php?estado=' . $filtro)) ?>">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="solicitud_id"
                                               value="<?= (int) $solicitud['id'] ?>">
                                        <button class="boton-tabla boton-tabla-claro" type="submit">
                                            Descartar
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($solicitud['cita_id']): ?>
                                <a href="<?= e(url('/panel/gestionar-cita.php?id=' . $solicitud['cita_id'])) ?>">
                                    Ver cita del
                                    <?= e(date('d/m', strtotime($solicitud['cita_fecha']))) ?>
                                    a las <?= e(substr($solicitud['cita_hora'], 0, 5)) ?>
                                </a>
                            <?php else: ?>
                                <span class="vacio">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
