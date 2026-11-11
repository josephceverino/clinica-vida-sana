<?php

require_once __DIR__ . '/../app/solicitudes.php';

iniciar_sesion_segura();

$titulo_pagina = 'Solicitar cita';
$error = '';
$areas = [];
$disponible = true;

$valores = [
    'nombre' => '',
    'dni' => '',
    'telefono' => '',
    'area_id' => '',
    'fecha_preferida' => '',
    'mensaje' => '',
];

$exito = $_SESSION['solicitud_enviada'] ?? false;
unset($_SESSION['solicitud_enviada']);

try {
    // Solo muestra áreas disponibles para el público.
    $areas = db()->query(
        'SELECT id, nombre FROM areas
         WHERE publica = 1
         ORDER BY nombre'
    )->fetchAll();

    if ($areas === []) {
        $error = 'Actualmente no hay áreas disponibles para solicitudes.';
        $disponible = false;
    }
} catch (PDOException $excepcion) {
    error_log($excepcion->getMessage());
    $error = 'El servicio no está disponible. Inténtalo más tarde.';
    $disponible = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $disponible) {
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

        registrar_solicitud($valores);

        $_SESSION['solicitud_enviada'] = true;
        unset($_SESSION['csrf_token']);

        // Evita repetir el envío al actualizar la página.
        header('Location: ' . url('/solicitar-cita.php'), true, 303);
        exit;
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        error_log($excepcion->getMessage());
        $error = 'No se pudo guardar la solicitud. Inténtalo más tarde.';
    }
}

$hoy = (new DateTimeImmutable(
    'today',
    new DateTimeZone('America/Lima')
))->format('Y-m-d');

csrf_token();

require __DIR__ . '/../app/layout/header.php';
?>

<section class="seccion">
    <h1>Solicitar cita</h1>

    <p>
        Completa tus datos para solicitar una atención.
        El personal revisará tu solicitud y se comunicará contigo.
        Este envío no confirma una cita.
    </p>

    <p>
        <strong>Proyecto académico:</strong>
        utiliza únicamente datos ficticios.
    </p>

    <?php if ($exito): ?>
        <div role="status" class="punto">
            <strong>Tu solicitud se guardó correctamente.</strong>
            <p>Queda pendiente de revisión por el personal.</p>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div role="alert" class="punto">
            <strong><?= e($error) ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($disponible): ?>
        <form
            method="post"
            action="<?= e(url('/solicitar-cita.php')) ?>"
            class="punto"
        >
            <?= csrf_campo() ?>

            <p>Los campos con * son obligatorios.</p>

            <p>
                <label for="nombre">Nombres y apellidos *</label><br>
                <input
                    id="nombre"
                    name="nombre"
                    type="text"
                    maxlength="100"
                    autocomplete="name"
                    value="<?= e($valores['nombre']) ?>"
                    required
                >
            </p>

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
                <br><small>Escribe 8 dígitos.</small>
            </p>

            <p>
                <label for="telefono">Teléfono *</label><br>
                <input
                    id="telefono"
                    name="telefono"
                    type="tel"
                    minlength="6"
                    maxlength="20"
                    autocomplete="tel"
                    value="<?= e($valores['telefono']) ?>"
                    required
                >
            </p>

            <p>
                <label for="area_id">Área solicitada *</label><br>
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
                <label for="fecha_preferida">
                    Fecha preferida (opcional)
                </label><br>
                <input
                    id="fecha_preferida"
                    name="fecha_preferida"
                    type="date"
                    min="<?= e($hoy) ?>"
                    value="<?= e($valores['fecha_preferida']) ?>"
                >
            </p>

            <p>
                <label for="mensaje">Mensaje (opcional)</label><br>
                <textarea
                    id="mensaje"
                    name="mensaje"
                    maxlength="500"
                    rows="4"
                ><?= e($valores['mensaje']) ?></textarea>
                <br><small>Máximo 500 caracteres.</small>
            </p>

            <button class="boton" type="submit">
                Enviar solicitud
            </button>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../app/layout/footer.php'; ?>