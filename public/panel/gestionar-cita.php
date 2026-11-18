<?php

require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/citas.php';
require_once __DIR__ . '/../../app/core/auditoria.php';

requerir_login();
requerir_rol(['admin', 'admision']);
iniciar_sesion_segura();

$titulo_pagina = 'Gestionar cita';
$error = '';
$cita = null;
$medicos = [];
$puede_editar = false;
$id = false;

if (isset($_GET['id']) && is_string($_GET['id'])) {
    $id = filter_var(
        $_GET['id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
}

if ($id === false) {
    http_response_code(400);
    exit('Selecciona una cita válida.');
}

$exito = $_SESSION['gestion_cita_mensaje'] ?? '';
unset($_SESSION['gestion_cita_mensaje']);

$ruta = '/panel/gestionar-cita.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exito = '';

    try {
        if (
            !isset($_POST['csrf_token'])
            || !is_string($_POST['csrf_token'])
            || !csrf_verificar()
        ) {
            throw new InvalidArgumentException(
                'La sesión del formulario venció. Recarga la página.'
            );
        }

        if (
            !isset($_POST['accion'])
            || !is_string($_POST['accion'])
        ) {
            throw new InvalidArgumentException(
                'La acción no es válida.'
            );
        }

        $usuario_id = usuario_actual()['id'];

        if ($_POST['accion'] === 'asignar_medico') {
            if (
                !isset($_POST['medico_id'])
                || !is_string($_POST['medico_id'])
            ) {
                throw new InvalidArgumentException(
                    'Selecciona un médico válido.'
                );
            }

            $medico_id = filter_var(
                $_POST['medico_id'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($medico_id === false) {
                throw new InvalidArgumentException(
                    'Selecciona un médico válido.'
                );
            }

            asignar_medico_cita($id, $medico_id, $usuario_id);
            $mensaje = 'Médico asignado correctamente.';
        } elseif ($_POST['accion'] === 'cambiar_estado') {
            if (
                !isset($_POST['estado'])
                || !is_string($_POST['estado'])
            ) {
                throw new InvalidArgumentException(
                    'Selecciona un estado válido.'
                );
            }

            cambiar_estado_cita(
                $id,
                $_POST['estado'],
                $usuario_id
            );

            if ($_POST['estado'] === 'no_asistio') {
                // Auditoría: queda quién marcó la inasistencia.
                $consulta = db()->prepare('SELECT paciente_id FROM citas WHERE id = ?');
                $consulta->execute([$id]);
                registrar_auditoria('marcar_no_asistio', (int) $consulta->fetchColumn(), 'Cita ' . $id);
            }

            $mensaje = 'Estado actualizado correctamente.';
        } else {
            throw new InvalidArgumentException(
                'La acción no es válida.'
            );
        }

        $_SESSION['gestion_cita_mensaje'] = $mensaje;

        header('Location: ' . url($ruta), true, 303);
        exit;
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudo actualizar la cita.';
    }
}

try {
    $consulta = db()->prepare(
        'SELECT citas.id, citas.area_id, citas.medico_id,
                citas.fecha, citas.hora, citas.estado,
                pacientes.nombres, pacientes.apellidos,
                areas.nombre AS area,
                usuarios.nombre AS medico
         FROM citas
         INNER JOIN pacientes ON pacientes.id = citas.paciente_id
         INNER JOIN areas ON areas.id = citas.area_id
         LEFT JOIN usuarios ON usuarios.id = citas.medico_id
         WHERE citas.id = :id'
    );
    $consulta->execute(['id' => $id]);
    $cita = $consulta->fetch();

    if (!$cita) {
        http_response_code(404);
        $error = 'La cita no existe.';
    } else {
        $consulta = db()->prepare(
            "SELECT id, nombre FROM usuarios
             WHERE rol = 'medico'
               AND activo = 1
               AND area_id = :area
             ORDER BY nombre"
        );
        $consulta->execute(['area' => $cita['area_id']]);
        $medicos = $consulta->fetchAll();

        $puede_editar = in_array(
            $cita['estado'],
            ['pendiente', 'confirmada'],
            true
        );
    }
} catch (PDOException $excepcion) {
    error_log($excepcion->getMessage());
    $error = 'No se pudieron cargar los datos de la cita.';
    $puede_editar = false;
}

csrf_token();

// Sección del menú lateral que se marca como activa.
$pagina_activa = 'citas.php';
require __DIR__ . '/../../app/layout/panel_header.php';
?>

<div class="ui-pagina">

    <a class="ui-migas" href="<?= e(url(
        '/panel/citas.php'
        . ($cita ? '?fecha=' . $cita['fecha'] : '')
    )) ?>">
        <?= $icono_panel('i-volver') ?>
        Volver a citas
    </a>

    <header class="ui-cabecera">
        <div>
            <h1>Gestionar cita</h1>
            <?php if ($cita): ?>
                <p>Cita <?= e($cita['id']) ?> &middot; <?= e($cita['nombres']) ?> <?= e($cita['apellidos']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($cita): ?>
            <span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span>
        <?php endif; ?>
    </header>

    <?php if ($exito !== ''): ?>
        <div class="ui-aviso ui-aviso-exito" role="status">
            <?= $icono_panel('i-visto') ?>
            <p><?= e($exito) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($cita): ?>
        <div class="ui-columnas">

            <!-- Izquierda: los datos de la cita -->
            <section class="ui-tarjeta">
                <div class="ui-tarjeta-cabecera">
                    <h2>Cita <?= e($cita['id']) ?></h2>
                </div>
                <dl class="datos datos-rejilla">
                    <div class="datos-ancho">
                        <dt>Paciente</dt>
                        <dd><?= e($cita['nombres']) ?> <?= e($cita['apellidos']) ?></dd>
                    </div>
                    <div>
                        <dt>Área</dt>
                        <dd><?= e($cita['area']) ?></dd>
                    </div>
                    <div>
                        <dt>Estado</dt>
                        <dd><span class="estado estado-<?= e($cita['estado']) ?>"><?= e(texto_estado($cita['estado'])) ?></span></dd>
                    </div>
                    <div>
                        <dt>Fecha</dt>
                        <?php // Se muestra como 16/11/2026; la base la guarda como 2026-11-16. ?>
                        <dd><time datetime="<?= e($cita['fecha']) ?>"><?= e(date('d/m/Y', strtotime($cita['fecha']))) ?></time></dd>
                    </div>
                    <div>
                        <dt>Hora</dt>
                        <dd><?= e(substr((string) $cita['hora'], 0, 5)) ?></dd>
                    </div>
                    <div class="datos-ancho">
                        <dt>Médico asignado</dt>
                        <dd><?= e($cita['medico'] ?? 'Sin médico asignado') ?></dd>
                    </div>
                </dl>
            </section>

            <!-- Derecha: lo que se puede hacer con la cita -->
            <div class="ui-columnas-pila">
                <?php if ($puede_editar): ?>
                    <section class="ui-tarjeta">
                        <h2>Asignar médico</h2>

                        <?php if ($medicos === []): ?>
                            <p class="ui-nota">No hay médicos activos en esta área.</p>
                        <?php else: ?>
                            <form method="post" action="<?= e(url($ruta)) ?>" data-cargando>
                                <?= csrf_campo() ?>

                                <input
                                    type="hidden"
                                    name="accion"
                                    value="asignar_medico"
                                >

                                <div class="ui-buscador">
                                    <div class="ui-campo">
                                        <label for="medico_id">Médico</label>
                                        <select
                                            class="ui-entrada"
                                            id="medico_id"
                                            name="medico_id"
                                            required
                                        >
                                            <option value="">
                                                Selecciona un médico
                                            </option>

                                            <?php foreach ($medicos as $medico): ?>
                                                <option
                                                    value="<?= e($medico['id']) ?>"
                                                    <?= (int) $medico['id']
                                                        === (int) $cita['medico_id']
                                                        ? 'selected' : '' ?>
                                                >
                                                    <?= e($medico['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <button class="ui-boton" type="submit" data-texto-cargando="Guardando…">
                                        Guardar médico
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>

                    <section class="ui-tarjeta">
                        <h2>Cambiar estado</h2>

                        <form method="post" action="<?= e(url($ruta)) ?>" data-cargando>
                            <?= csrf_campo() ?>

                            <input
                                type="hidden"
                                name="accion"
                                value="cambiar_estado"
                            >

                            <div class="ui-acciones ui-acciones-simple">
                                <?php if ($cita['estado'] === 'pendiente'): ?>
                                    <button
                                        class="ui-boton"
                                        name="estado"
                                        value="confirmada"
                                        type="submit"
                                        data-texto-cargando="Confirmando…"
                                    >
                                        Confirmar
                                    </button>
                                <?php endif; ?>

                                <?php // Solo si estaba confirmada y su hora ya pasó. ?>
                                <?php if (
                                    $cita['estado'] === 'confirmada'
                                    && strtotime($cita['fecha'] . ' ' . $cita['hora']) <= time()
                                ): ?>
                                    <button
                                        class="ui-boton ui-boton-secundario"
                                        name="estado"
                                        value="no_asistio"
                                        type="submit"
                                        data-texto-cargando="Guardando…"
                                    >
                                        No asistió
                                    </button>
                                <?php endif; ?>

                                <button
                                    class="ui-boton ui-boton-peligro"
                                    name="estado"
                                    value="cancelada"
                                    type="submit"
                                    data-texto-cargando="Cancelando…"
                                >
                                    Cancelar cita
                                </button>
                            </div>
                        </form>

                        <p class="ui-nota ui-nota-separada">
                            La cita pasa a <strong>atendida</strong> cuando el médico
                            registra la atención, no desde aquí. Si ya pasó su hora y el
                            paciente no llegó, márcala como <strong>no asistió</strong>.
                        </p>
                    </section>
                <?php elseif (in_array(
                    $cita['estado'],
                    ['atendida', 'cancelada', 'no_asistio'],
                    true
                )): ?>
                    <section class="ui-tarjeta">
                        <div class="ui-vacio">
                            <span class="ui-vacio-icono"><?= $icono_panel('i-escudo') ?></span>
                            <strong>Esta cita ya no admite cambios.</strong>
                            <p>Su estado es «<?= e(texto_estado($cita['estado'])) ?>».</p>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
