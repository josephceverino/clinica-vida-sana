<?php

require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/citas.php';

requerir_login();
requerir_rol(['admin', 'admision', 'medico']);

$titulo_pagina = 'Citas';
$usuario = usuario_actual();
$error = '';
$citas = [];

$puede_gestionar = in_array(
    $usuario['rol'],
    ['admin', 'admision'],
    true
);

$zona = new DateTimeZone('America/Lima');
$fecha = (new DateTimeImmutable('today', $zona))->format('Y-m-d');

if (isset($_GET['fecha'])) {
    if (!is_string($_GET['fecha'])) {
        $error = 'La fecha no es válida.';
    } else {
        $fecha = trim($_GET['fecha']);
    }
}

if ($error === '') {
    try {
        $fecha_validada = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $zona
        );

        if (
            !$fecha_validada
            || $fecha_validada->format('Y-m-d') !== $fecha
        ) {
            throw new InvalidArgumentException(
                'Selecciona una fecha válida.'
            );
        }

        if ($usuario['rol'] === 'medico') {
            // El médico solo consulta las citas de su propia sesión.
            $citas = buscar_citas_medico($usuario['id'], $fecha);
        } else {
            $citas = buscar_citas($fecha);
        }
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudieron consultar las citas.';
    }
}

// Sección del menú lateral que se marca como activa.
$pagina_activa = 'citas.php';
require __DIR__ . '/../../app/layout/panel_header.php';
?>

<section class="seccion">
    <h1>Citas</h1>
    <?php if ($puede_gestionar): ?>
    <p>
        <a
            class="boton"
            href="<?= e(url('/panel/registrar-cita.php')) ?>"
        >
            Registrar cita
        </a>
    </p>
<?php endif; ?>

    <p>
        <a href="<?= e(url('/panel/index.php')) ?>">
            Volver al panel
        </a>
    </p>

    <?php if ($usuario['rol'] === 'medico'): ?>
        <p>Se muestran únicamente tus citas asignadas.</p>
    <?php endif; ?>

    <form method="get" action="<?= e(url('/panel/citas.php')) ?>">
        <label for="fecha">Fecha de las citas</label>

        <p>
            <input
                id="fecha"
                name="fecha"
                type="date"
                value="<?= e($fecha) ?>"
                required
            >

            <button class="boton" type="submit">
                Consultar
            </button>
        </p>
    </form>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= e($error) ?></p>

    <?php elseif ($citas === []): ?>
        <p>No hay citas para la fecha seleccionada.</p>

    <?php else: ?>
        <p>Resultados mostrados: <?= e(count($citas)) ?>.</p>

        <?php if ($puede_gestionar): ?>
            <p>Se muestran hasta 100 citas por consulta.</p>
        <?php endif; ?>

        <?php foreach ($citas as $cita): ?>
            <article class="punto">
                <h2>
                    Cita <?= e($cita['id']) ?>:
                    <?= e($cita['hora']) ?>
                </h2>

                <p>
                    <strong>Paciente:</strong>
                    <?= e($cita['nombres']) ?>
                    <?= e($cita['apellidos']) ?><br>

                    <strong>DNI:</strong>
                    <?= e($cita['dni']) ?><br>

                    <strong>Área:</strong>
                    <?= e($cita['area']) ?><br>

                    <strong>Motivo:</strong>
                    <?= e($cita['motivo'] ?? 'Sin motivo registrado') ?><br>

                    <strong>Estado:</strong>
                    <?= e($cita['estado']) ?>
                </p>

                <?php if ($puede_gestionar): ?>
                    <p>
                        <a
                            class="boton"
                            href="<?= e(url(
                                '/panel/gestionar-cita.php?id=' . $cita['id']
                            )) ?>"
                        >
                            Gestionar cita
                        </a>
                    </p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>