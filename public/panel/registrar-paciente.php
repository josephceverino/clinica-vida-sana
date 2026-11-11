<?php

require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/pacientes.php';

requerir_login();
requerir_rol(['admin', 'admision']);

$titulo_pagina = 'Registrar paciente';
$error = '';

$valores = [
    'dni' => '',
    'nombres' => '',
    'apellidos' => '',
    'fecha_nacimiento' => '',
    'telefono' => '',
];

iniciar_sesion_segura();

$registrado = $_SESSION['paciente_registrado'] ?? false;
unset($_SESSION['paciente_registrado']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $registrado = false;

    try {
        foreach ($valores as $campo => $valor) {
            if (
                isset($_POST[$campo])
                && !is_string($_POST[$campo])
            ) {
                throw new InvalidArgumentException(
                    'Los datos enviados no son válidos.'
                );
            }

            $valores[$campo] = trim($_POST[$campo] ?? '');
        }

        if (
            !isset($_POST['csrf_token'])
            || !is_string($_POST['csrf_token'])
            || !csrf_verificar()
        ) {
            throw new InvalidArgumentException(
                'La sesión del formulario venció. Recarga la página.'
            );
        }

        registrar_paciente($valores);

        $_SESSION['paciente_registrado'] = true;
        unset($_SESSION['csrf_token']);

        header(
            'Location: ' . url('/panel/registrar-paciente.php'),
            true,
            303
        );
        exit;
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudo registrar el paciente. Inténtalo más tarde.';
    }
}

$hoy = (new DateTimeImmutable(
    'today',
    new DateTimeZone('America/Lima')
))->format('Y-m-d');

csrf_token();

// Sección del menú lateral que se marca como activa.
$pagina_activa = 'pacientes.php';
require __DIR__ . '/../../app/layout/panel_header.php';
?>

<section class="seccion">
    <h1>Registrar paciente</h1>

    <p>
        <a href="<?= e(url('/panel/pacientes.php')) ?>">
            Volver a pacientes
        </a>
    </p>

    <p>Proyecto académico: utiliza únicamente datos ficticios.</p>

    <?php if ($registrado): ?>
        <p role="status">Paciente registrado correctamente.</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form
        method="post"
        action="<?= e(url('/panel/registrar-paciente.php')) ?>"
        class="punto"
    >
        <?= csrf_campo() ?>

        <p>Los campos con * son obligatorios.</p>

        <p>
            <label for="dni">DNI *</label><br>
            <input
                id="dni"
                name="dni"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{8}"
                minlength="8"
                maxlength="8"
                value="<?= e($valores['dni']) ?>"
                required
            >
        </p>

        <p>
            <label for="nombres">Nombres *</label><br>
            <input
                id="nombres"
                name="nombres"
                type="text"
                maxlength="100"
                value="<?= e($valores['nombres']) ?>"
                required
            >
        </p>

        <p>
            <label for="apellidos">Apellidos *</label><br>
            <input
                id="apellidos"
                name="apellidos"
                type="text"
                maxlength="100"
                value="<?= e($valores['apellidos']) ?>"
                required
            >
        </p>

        <p>
            <label for="fecha_nacimiento">
                Fecha de nacimiento *
            </label><br>
            <input
                id="fecha_nacimiento"
                name="fecha_nacimiento"
                type="date"
                max="<?= e($hoy) ?>"
                value="<?= e($valores['fecha_nacimiento']) ?>"
                required
            >
        </p>

        <p>
            <label for="telefono">Teléfono (opcional)</label><br>
            <input
                id="telefono"
                name="telefono"
                type="tel"
                minlength="6"
                maxlength="20"
                value="<?= e($valores['telefono']) ?>"
            >
        </p>

        <button class="boton" type="submit">
            Registrar paciente
        </button>
    </form>
</section>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>