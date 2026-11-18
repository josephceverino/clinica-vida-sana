<?php

require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/citas.php';

requerir_login();
requerir_rol(['admin', 'admision']);
iniciar_sesion_segura();

$titulo_pagina = 'Registrar cita';
$error = '';
$disponible = true;
$pacientes = [];
$areas = [];

$valores = [
    'paciente_id' => '',
    'area_id' => '',
    'fecha' => '',
    'hora' => '',
    'motivo' => '',
];

$registrada = $_SESSION['cita_registrada'] ?? false;
unset($_SESSION['cita_registrada']);

try {
    $pacientes = db()->query(
        'SELECT id, tipo_documento, numero_documento, nombres, apellidos
         FROM pacientes
         ORDER BY apellidos, nombres'
    )->fetchAll();

    $areas = db()->query(
        'SELECT id, nombre FROM areas WHERE publica = 1 ORDER BY nombre'
    )->fetchAll();
} catch (PDOException $excepcion) {
    error_log($excepcion->getMessage());
    $error = 'No se pudieron cargar los datos del formulario.';
    $disponible = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $disponible) {
    $registrada = false;

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

        registrar_cita($valores, usuario_actual()['id']);

        $_SESSION['cita_registrada'] = true;
        unset($_SESSION['csrf_token']);

        header(
            'Location: ' . url('/panel/registrar-cita.php'),
            true,
            303
        );
        exit;
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudo registrar la cita.';
    }
}

$hoy = (new DateTimeImmutable(
    'today',
    new DateTimeZone('America/Lima')
))->format('Y-m-d');

csrf_token();

// Sección del menú lateral que se marca como activa.
$pagina_activa = 'citas.php';
require __DIR__ . '/../../app/layout/panel_header.php';
?>

<div class="ui-pagina">

    <a class="ui-migas" href="<?= e(url('/panel/citas.php')) ?>">
        <?= $icono_panel('i-volver') ?>
        Volver a citas
    </a>

    <header class="ui-cabecera">
        <div>
            <h1>Registrar cita</h1>
            <p>Reserva una cita para un paciente registrado.</p>
        </div>
    </header>

    <?php if ($registrada): ?>
        <div class="ui-aviso ui-aviso-exito" role="status">
            <?= $icono_panel('i-visto') ?>
            <p>Cita registrada correctamente.</p>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($disponible): ?>
        <!-- data-cargando: al enviar, panel.js muestra "Registrando…" y evita el doble envío -->
        <form
            class="ui-tarjeta"
            method="post"
            action="<?= e(url('/panel/registrar-cita.php')) ?>"
            data-cargando
        >
            <?= csrf_campo() ?>

            <div class="ui-tarjeta-cabecera">
                <h2>Datos de la cita</h2>
                <p class="ui-nota">Los campos con <span class="ui-obligatorio">*</span> son obligatorios.</p>
            </div>

            <div class="ui-rejilla">
                <div class="ui-campo ui-campo-ancho">
                    <label for="paciente_id">Paciente <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                    <select class="ui-entrada" id="paciente_id" name="paciente_id" required>
                        <option value="">Selecciona un paciente</option>

                        <?php foreach ($pacientes as $paciente): ?>
                            <option
                                value="<?= e($paciente['id']) ?>"
                                <?= (string) $paciente['id']
                                    === $valores['paciente_id']
                                    ? 'selected' : '' ?>
                            >
                                <?= e($paciente['tipo_documento']) ?> <?= e($paciente['numero_documento']) ?> —
                                <?= e($paciente['nombres']) ?>
                                <?= e($paciente['apellidos']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ui-campo ui-campo-ancho">
                    <label for="area_id">Área <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                    <select class="ui-entrada" id="area_id" name="area_id" required>
                        <option value="">Selecciona un área</option>

                        <?php foreach ($areas as $area): ?>
                            <option
                                value="<?= e($area['id']) ?>"
                                <?= (string) $area['id'] === $valores['area_id']
                                    ? 'selected' : '' ?>
                            >
                                <?= e($area['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ui-campo">
                    <label for="fecha">Fecha <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                    <input
                        class="ui-entrada"
                        id="fecha"
                        name="fecha"
                        type="date"
                        min="<?= e($hoy) ?>"
                        aria-describedby="ayuda-fecha"
                        value="<?= e($valores['fecha']) ?>"
                        required
                    >
                    <span class="ui-ayuda" id="ayuda-fecha">Desde hoy en adelante.</span>
                </div>

                <div class="ui-campo">
                    <label for="hora">Hora <span class="ui-obligatorio" aria-hidden="true">*</span></label>
                    <input
                        class="ui-entrada"
                        id="hora"
                        name="hora"
                        type="time"
                        step="60"
                        value="<?= e($valores['hora']) ?>"
                        required
                    >
                </div>

                <div class="ui-campo ui-campo-ancho">
                    <label for="motivo">Motivo (opcional)</label>
                    <textarea
                        class="ui-entrada"
                        id="motivo"
                        name="motivo"
                        maxlength="255"
                        rows="3"
                        aria-describedby="ayuda-motivo"
                    ><?= e($valores['motivo']) ?></textarea>
                    <span class="ui-ayuda" id="ayuda-motivo">Hasta 255 caracteres.</span>
                </div>
            </div>

            <div class="ui-aviso">
                <?= $icono_panel('i-info') ?>
                <p>
                    La cita quedará pendiente.
                    La asignación de médico se gestiona por separado.
                </p>
            </div>

            <div class="ui-acciones">
                <button class="ui-boton" type="submit" data-texto-cargando="Registrando…">
                    Registrar cita
                </button>
                <a class="ui-boton ui-boton-secundario" href="<?= e(url('/panel/citas.php')) ?>">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
