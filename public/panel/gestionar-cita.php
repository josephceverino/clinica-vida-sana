<?php

require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/citas.php';

requerir_login();
requerir_rol(['admin', 'admision']);
iniciar_sesion_segura();

$titulo_pagina = 'Gestionar cita';
$error = '';
$cita = null;

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

$exito = $_SESSION['cita_actualizada'] ?? false;
unset($_SESSION['cita_actualizada']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exito = false;

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
            usuario_actual()['id']
        );

        $_SESSION['cita_actualizada'] = true;

        header(
            'Location: ' . url('/panel/gestionar-cita.php?id=' . $id),
            true,
            303
        );
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
        'SELECT citas.id, citas.fecha, citas.hora, citas.estado,
                pacientes.nombres, pacientes.apellidos,
                areas.nombre AS area
         FROM citas
         INNER JOIN pacientes ON pacientes.id = citas.paciente_id
         INNER JOIN areas ON areas.id = citas.area_id
         WHERE citas.id = :id'
    );
    $consulta->execute(['id' => $id]);
    $cita = $consulta->fetch();

    if (!$cita) {
        http_response_code(404);
        $error = 'La cita no existe.';
    }
} catch (PDOException $excepcion) {
    error_log($excepcion->getMessage());
    $error = 'No se pudo consultar la cita.';
}

csrf_token();

require __DIR__ . '/../../app/layout/header.php';
?>

<section class="seccion">
    <h1>Gestionar cita</h1>

    <p>
        <a href="<?= e(url('/panel/citas.php')) ?>">
            Volver a citas
        </a>
    </p>

    <?php if ($exito): ?>
        <p role="status">Estado actualizado correctamente.</p>
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
                <?= e($cita['estado']) ?>
            </p>

            <?php if (in_array(
                $cita['estado'],
                ['pendiente', 'confirmada'],
                true
            )): ?>
                <form
                    method="post"
                    action="<?= e(url(
                        '/panel/gestionar-cita.php?id=' . $id
                    )) ?>"
                >
                    <?= csrf_campo() ?>

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
            <?php else: ?>
                <p>Esta cita ya no admite cambios de estado.</p>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../app/layout/footer.php'; ?>