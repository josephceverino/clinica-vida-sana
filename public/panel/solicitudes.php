<?php
// Solicitudes de cita que envían los pacientes desde su portal.
// Admisión decide: "Programar cita" (la convierte en cita con médico) o
// "Descartar" (por ejemplo, si está repetida).
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/solicitudes.php';

// Regla 6: solo admin y admisión.
requerir_login();
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

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Solicitudes de cita</h1>
            <p>Lo que piden los pacientes desde su portal. Prográmalas como cita o descártalas.</p>
        </div>
    </header>

    <?php if ($mensaje): ?>
        <div class="ui-aviso ui-aviso-exito" role="status">
            <?= $icono_panel('i-visto') ?>
            <p><?= e($mensaje['texto']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <nav class="ui-pestanas" aria-label="Filtro de solicitudes">
        <?php foreach ($filtros as $clave => $texto): ?>
            <a href="<?= e(url('/panel/solicitudes.php?estado=' . $clave)) ?>"
               <?= $clave === $filtro ? 'aria-current="page"' : '' ?>><?= e($texto) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php // Con error y sin datos, basta el aviso rojo de arriba (sin tarjeta vacía). ?>
    <?php if ($solicitudes !== [] || $error === ''): ?>
    <section class="ui-tarjeta">
        <?php if ($solicitudes === []): ?>
            <div class="ui-vacio">
                <span class="ui-vacio-icono"><?= $icono_panel('i-documento') ?></span>
                <strong>No hay solicitudes en esta lista.</strong>
                <p>
                    <?= $filtro === 'nueva'
                        ? 'Cuando un paciente pida una cita desde su portal, aparecerá aquí.'
                        : 'Prueba con otra pestaña.' ?>
                </p>
            </div>
        <?php elseif ($solicitudes !== []): ?>
            <div class="ui-tarjeta-cabecera">
                <h2><?= e($filtros[$filtro]) ?></h2>
                <span class="ui-insignia"><?= count($solicitudes) ?> solicitud<?= count($solicitudes) === 1 ? '' : 'es' ?></span>
            </div>

            <div class="ui-tabla-contenedor">
                <table class="ui-tabla ui-tabla-apilable ui-tabla-apilable-ancha" role="table">
                    <caption class="solo-lectores">Solicitudes de cita: <?= e($filtros[$filtro]) ?></caption>
                    <thead role="rowgroup">
                        <tr role="row">
                            <th scope="col" role="columnheader">Paciente</th>
                            <th scope="col" role="columnheader">Área</th>
                            <th scope="col" role="columnheader">Fecha preferida</th>
                            <th scope="col" role="columnheader">Motivo</th>
                            <th scope="col" role="columnheader">Estado</th>
                            <th scope="col" role="columnheader">Acción</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        <?php foreach ($solicitudes as $solicitud): ?>
                            <tr role="row">
                                <td class="ui-celda-fuerte" role="cell" data-etiqueta="Paciente">
                                    <span class="ui-celda-valor">
                                        <?= e($solicitud['nombre']) ?>
                                        <small>
                                            <?= e($solicitud['tipo_documento'] . ' ' . $solicitud['numero_documento']) ?>
                                            &middot; Tel. <?= e($solicitud['telefono'] ?? '—') ?>
                                        </small>
                                    </span>
                                </td>
                                <td role="cell" data-etiqueta="Área"><?= e($solicitud['area']) ?></td>
                                <td class="ui-celda-suave" role="cell" data-etiqueta="Fecha preferida">
                                    <?= $solicitud['fecha_preferida']
                                        ? e(date('d/m/Y', strtotime($solicitud['fecha_preferida'])))
                                        : 'Sin preferencia' ?>
                                </td>
                                <td role="cell" data-etiqueta="Motivo"><?= e($solicitud['mensaje'] ?? '') ?></td>
                                <td role="cell" data-etiqueta="Estado">
                                    <?php [$clase, $texto] = etiqueta_solicitud($solicitud); ?>
                                    <span class="estado estado-<?= e($clase) ?>"><?= e($texto) ?></span>
                                </td>
                                <td role="cell" data-etiqueta="Acción">
                                    <?php if ($solicitud['estado'] === 'nueva'): ?>
                                        <div class="ui-acciones-fila">
                                            <a class="ui-boton ui-boton-chico"
                                               href="<?= e(url('/panel/programar-solicitud.php?id=' . $solicitud['id'])) ?>">
                                                Programar cita
                                            </a>
                                            <!-- data-cargando: al enviar, el botón muestra "Descartando…" -->
                                            <form method="post"
                                                  action="<?= e(url('/panel/solicitudes.php?estado=' . $filtro)) ?>"
                                                  data-cargando>
                                                <?= csrf_campo() ?>
                                                <input type="hidden" name="solicitud_id"
                                                       value="<?= (int) $solicitud['id'] ?>">
                                                <button class="ui-boton ui-boton-peligro ui-boton-chico" type="submit"
                                                        data-texto-cargando="Descartando…">
                                                    Descartar
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($solicitud['cita_id']): ?>
                                        <a class="ui-enlace" href="<?= e(url('/panel/gestionar-cita.php?id=' . $solicitud['cita_id'])) ?>">
                                            Ver cita del
                                            <?= e(date('d/m', strtotime($solicitud['cita_fecha']))) ?>
                                            a las <?= e(substr($solicitud['cita_hora'], 0, 5)) ?>
                                        </a>
                                    <?php else: ?>
                                        <span aria-hidden="true">—</span><span class="solo-lectores">Sin acciones</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
