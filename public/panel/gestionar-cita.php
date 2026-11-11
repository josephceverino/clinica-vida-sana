<?php

require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/citas.php';

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

<section class="seccion">
    <h1>Gestionar cita</h1>

    <p>
        <a href="<?= e(url(
            '/panel/citas.php'
            . ($cita ? '?fecha=' . $cita['fecha'] : '')
        )) ?>">
            Volver a citas
        </a>
    </p>

    <?php if ($exito !== ''): ?>
        <p role="status"><?= e($exito) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($cita): ?>
        <article class="punto">
            <h2>Cita <?= e($cita['id']) ?></h2>

            <p>
                <strong>Paciente:</strong>
                <?= e($cita['nombres']) ?>
                <?= e($cita['apellidos']) ?><br>

                <strong>Área:</strong>
                <?= e($cita['area']) ?><br>

                <strong>Fecha:</strong>
                <?= e($cita['fecha']) ?><br>

                <strong>Hora:</strong>
                <?= e($cita['hora']) ?><br>

                <strong>Estado:</strong>
                <?= e($cita['estado']) ?><br>

                <strong>Médico asignado:</strong>
                <?= e($cita['medico'] ?? 'Sin médico asignado') ?>
            </p>

            <?php if ($puede_editar): ?>
                <h3>Asignar médico</h3>

                <?php if ($medicos === []): ?>
                    <p>No hay médicos activos en esta área.</p>
                <?php else: ?>
                    <form method="post" action="<?= e(url($ruta)) ?>">
                        <?= csrf_campo() ?>

                        <input
                            type="hidden"
                            name="accion"
                            value="asignar_medico"
                        >

                        <p>
                            <label for="medico_id">Médico</label><br>

                            <select
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
                        </p>

                        <button class="boton" type="submit">
                            Guardar médico
                        </button>
                    </form>
                <?php endif; ?>

                <h3>Cambiar estado</h3>

                <form method="post" action="<?= e(url($ruta)) ?>">
                    <?= csrf_campo() ?>

                    <input
                        type="hidden"
                        name="accion"
                        value="cambiar_estado"
                    >

                    <?php if ($cita['estado'] === 'pendiente'): ?>
                        <button
                            class="boton"
                            name="estado"
                            value="confirmada"
                            type="submit"
                        >
                            Confirmar
                        </button>
                    <?php else: ?>
                        <button
                            class="boton"
                            name="estado"
                            value="atendida"
                            type="submit"
                        >
                            Marcar atendida
                        </button>
                    <?php endif; ?>

                    <button
                        class="boton boton-claro"
                        name="estado"
                        value="cancelada"
                        type="submit"
                    >
                        Cancelar cita
                    </button>
                </form>

                <p>
                    Una cita solo puede marcarse como atendida
                    cuando haya llegado su fecha y hora.
                </p>
            <?php elseif (in_array(
                $cita['estado'],
                ['atendida', 'cancelada'],
                true
            )): ?>
                <p>Esta cita ya no admite cambios.</p>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>