<?php

require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/citas.php';

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
        'SELECT id, dni, nombres, apellidos
         FROM pacientes
         ORDER BY apellidos, nombres'
    )->fetchAll();

    $areas = db()->query(
        'SELECT id, nombre FROM areas ORDER BY nombre'
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

require __DIR__ . '/../../app/layout/header.php';
?>

<section class="seccion">
    <h1>Registrar cita</h1>

    <p>
        <a href="<?= e(url('/panel/citas.php')) ?>">
            Volver a citas
        </a>
    </p>

    <?php if ($registrada): ?>
        <p role="status">Cita registrada correctamente.</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($disponible): ?>
        <form
            method="post"
            action="<?= e(url('/panel/registrar-cita.php')) ?>"
            class="punto"
        >
            <?= csrf_campo() ?>

            <p>Los campos con * son obligatorios.</p>

            <p>
                <label for="paciente_id">Paciente *</label><br>
                <select id="paciente_id" name="paciente_id" required>
                    <option value="">Selecciona un paciente</option>

                    <?php foreach ($pacientes as $paciente): ?>
                        <option
                            value="<?= e($paciente['id']) ?>"
                            <?= (string) $paciente['id']
                                === $valores['paciente_id']
                                ? 'selected' : '' ?>
                        >
                            <?= e($paciente['dni']) ?> —
                            <?= e($paciente['nombres']) ?>
                            <?= e($paciente['apellidos']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="area_id">Área *</label><br>
                <select id="area_id" name="area_id" required>
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
            </p>

            <p>
                <label for="fecha">Fecha *</label><br>
                <input
                    id="fecha"
                    name="fecha"
                    type="date"
                    min="<?= e($hoy) ?>"
                    value="<?= e($valores['fecha']) ?>"
                    required
                >
            </p>

            <p>
                <label for="hora">Hora *</label><br>
                <input
                    id="hora"
                    name="hora"
                    type="time"
                    step="60"
                    value="<?= e($valores['hora']) ?>"
                    required
                >
            </p>

            <p>
                <label for="motivo">Motivo (opcional)</label><br>
                <textarea
                    id="motivo"
                    name="motivo"
                    maxlength="255"
                    rows="3"
                ><?= e($valores['motivo']) ?></textarea>
            </p>

            <p>
                La cita quedará pendiente.
                La asignación de médico se gestiona por separado.
            </p>

            <button class="boton" type="submit">
                Registrar cita
            </button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../app/layout/footer.php'; ?>