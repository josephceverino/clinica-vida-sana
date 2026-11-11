<?php

require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/solicitudes.php';

requerir_login();
requerir_rol(['admin', 'admision']);

iniciar_sesion_segura();

$titulo_pagina = 'Solicitudes de cita';
$error = '';
$solicitudes = [];

$exito = $_SESSION['solicitud_actualizada'] ?? false;
unset($_SESSION['solicitud_actualizada']);

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
            !isset($_POST['solicitud_id'], $_POST['estado'])
            || !is_string($_POST['solicitud_id'])
            || !is_string($_POST['estado'])
        ) {
            throw new InvalidArgumentException(
                'Los datos enviados no son válidos.'
            );
        }

        $id = filter_var(
            $_POST['solicitud_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($id === false) {
            throw new InvalidArgumentException(
                'La solicitud no es válida.'
            );
        }

        // El usuario se obtiene de la sesión, no del formulario.
        cambiar_estado_solicitud(
            $id,
            $_POST['estado'],
            usuario_actual()['id']
        );

        $_SESSION['solicitud_actualizada'] = true;

        header(
            'Location: ' . url('/panel/solicitudes.php'),
            true,
            303
        );
        exit;
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudo actualizar la solicitud.';
    }
}

try {
    $solicitudes = buscar_solicitudes();
} catch (PDOException $excepcion) {
    error_log($excepcion->getMessage());
    $error = 'No se pudieron consultar las solicitudes.';
}

csrf_token();

require __DIR__ . '/../../app/layout/header.php';
?>

<section class="seccion">
    <h1>Solicitudes de cita</h1>

    <p>
        <a href="<?= e(url('/panel/index.php')) ?>">
            Volver al panel
        </a>
    </p>

    <p>
        Marcar una solicitud como atendida indica que fue procesada.
        No crea automáticamente una cita.
    </p>

    <?php if ($exito): ?>
        <p role="status">Estado actualizado correctamente.</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <p>Se muestran hasta 100 solicitudes recientes.</p>

    <?php if ($solicitudes === [] && $error === ''): ?>
        <p>No hay solicitudes registradas.</p>
    <?php endif; ?>

    <?php foreach ($solicitudes as $solicitud): ?>
        <article class="punto">
            <h2>
                Solicitud <?= e($solicitud['id']) ?>:
                <?= e($solicitud['nombre']) ?>
            </h2>

            <p>
                <strong>DNI:</strong>
                <?= e($solicitud['dni']) ?><br>

                <strong>Teléfono:</strong>
                <?= e($solicitud['telefono']) ?><br>

                <strong>Área:</strong>
                <?= e($solicitud['area']) ?><br>

                <strong>Fecha preferida:</strong>
                <?= e($solicitud['fecha_preferida'] ?? 'Sin preferencia') ?><br>

                <strong>Mensaje:</strong>
                <?= e($solicitud['mensaje'] ?? 'Sin mensaje') ?><br>

                <strong>Estado:</strong>
                <?= e($solicitud['estado']) ?>
            </p>

            <?php if ($solicitud['estado'] === 'nueva'): ?>
                <form
                    method="post"
                    action="<?= e(url('/panel/solicitudes.php')) ?>"
                >
                    <?= csrf_campo() ?>

                    <input
                        type="hidden"
                        name="solicitud_id"
                        value="<?= e($solicitud['id']) ?>"
                    >

                    <button
                        class="boton"
                        type="submit"
                        name="estado"
                        value="atendida"
                    >
                        Marcar atendida
                    </button>

                    <button
                        class="boton boton-claro"
                        type="submit"
                        name="estado"
                        value="descartada"
                    >
                        Descartar
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../../app/layout/footer.php'; ?>