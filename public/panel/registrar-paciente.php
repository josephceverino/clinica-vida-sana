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

<div class="ui-pagina">

    <a class="ui-migas" href="<?= e(url('/panel/pacientes.php')) ?>">
        <?= $icono_panel('i-volver') ?>
        Volver a pacientes
    </a>

    <header class="ui-cabecera">
        <div>
            <h1>Registrar paciente</h1>
            <p>Crea la ficha de un paciente nuevo.</p>
        </div>
    </header>

    <?php if ($registrado): ?>
        <div class="ui-aviso ui-aviso-exito" role="status">
            <?= $icono_panel('i-visto') ?>
            <p>Paciente registrado correctamente.</p>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <div class="ui-aviso">
        <?= $icono_panel('i-info') ?>
        <p>Proyecto académico: utiliza únicamente datos ficticios.</p>
    </div>

    <!-- data-cargando: al enviar, panel.js muestra "Registrando…" y evita el doble envío -->
    <form
        class="ui-tarjeta"
        method="post"
        action="<?= e(url('/panel/registrar-paciente.php')) ?>"
        data-cargando
    >
        <?= csrf_campo() ?>

        <div class="ui-tarjeta-cabecera">
            <h2>Datos del paciente</h2>
            <p class="ui-nota">Los campos con <span class="ui-obligatorio">*</span> son obligatorios.</p>
        </div>

        <div class="ui-rejilla">
            <div class="ui-campo">
                <label for="dni">DNI <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                <input
                    class="ui-entrada"
                    id="dni"
                    name="dni"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{8}"
                    minlength="8"
                    maxlength="8"
                    autocomplete="off"
                    aria-describedby="ayuda-dni"
                    value="<?= e($valores['dni']) ?>"
                    required
                >
                <span class="ui-ayuda" id="ayuda-dni">8 dígitos, solo números.</span>
            </div>

            <div class="ui-campo">
                <label for="telefono">Teléfono (opcional)</label>
                <input
                    class="ui-entrada"
                    id="telefono"
                    name="telefono"
                    type="tel"
                    minlength="6"
                    maxlength="20"
                    autocomplete="off"
                    aria-describedby="ayuda-telefono"
                    value="<?= e($valores['telefono']) ?>"
                >
                <span class="ui-ayuda" id="ayuda-telefono">Entre 6 y 20 caracteres.</span>
            </div>

            <div class="ui-campo">
                <label for="nombres">Nombres <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                <input
                    class="ui-entrada"
                    id="nombres"
                    name="nombres"
                    type="text"
                    maxlength="100"
                    autocomplete="off"
                    value="<?= e($valores['nombres']) ?>"
                    required
                >
            </div>

            <div class="ui-campo">
                <label for="apellidos">Apellidos <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                <input
                    class="ui-entrada"
                    id="apellidos"
                    name="apellidos"
                    type="text"
                    maxlength="100"
                    autocomplete="off"
                    value="<?= e($valores['apellidos']) ?>"
                    required
                >
            </div>

            <div class="ui-campo">
                <label for="fecha_nacimiento">Fecha de nacimiento <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                <input
                    class="ui-entrada"
                    id="fecha_nacimiento"
                    name="fecha_nacimiento"
                    type="date"
                    max="<?= e($hoy) ?>"
                    value="<?= e($valores['fecha_nacimiento']) ?>"
                    required
                >
            </div>
        </div>

        <div class="ui-acciones">
            <button class="ui-boton" type="submit" data-texto-cargando="Registrando…">
                Registrar paciente
            </button>
            <a class="ui-boton ui-boton-secundario" href="<?= e(url('/panel/pacientes.php')) ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
