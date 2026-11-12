<?php
// Pedir una cita: crea una solicitud ligada a la cuenta del paciente.
// Admisión la revisa en el panel (Solicitudes) y confirma la cita.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/solicitudes.php';

// Solo pacientes con la cuenta verificada por admisión.
requerir_paciente_verificado();

$titulo_pagina = 'Pedir cita';
$pagina_activa = 'pedir-cita.php';
$error = '';

// Valores del formulario (para no borrarlos si hay un error).
$valores = ['area_id' => '', 'fecha_preferida' => '', 'mensaje' => ''];

// Áreas que atienden pacientes (no Admisión ni Sistemas).
$areas = db()->query(
    'SELECT id, nombre FROM areas WHERE publica = 1 ORDER BY nombre'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valores as $campo => $vacio) {
        $valores[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
    }

    try {
        // Regla 4: todo formulario POST lleva token CSRF.
        if (!csrf_verificar()) {
            throw new InvalidArgumentException(
                'La sesión del formulario venció. Recarga la página.'
            );
        }

        // El paciente sale de la SESIÓN. Aunque alguien agregue un campo
        // "paciente_id" al formulario, aquí se ignora por completo.
        registrar_solicitud_paciente(
            (int) usuario_actual()['paciente_id'],
            $valores
        );

        mensaje_guardar('Solicitud enviada. Admisión la revisará y te confirmará la cita.');
        redirigir('/paciente/citas.php');
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        // Regla 9: al usuario un mensaje genérico; el detalle va al log.
        error_log($excepcion->getMessage());
        $error = 'No se pudo enviar la solicitud. Inténtalo más tarde.';
    }
}

require __DIR__ . '/../../app/layout/paciente_header.php';
?>

<section class="login">
    <h1>Pedir una cita</h1>
    <p>Cuéntanos qué necesitas. Admisión revisará tu solicitud y te confirmará la fecha y la hora.</p>

    <?php if ($error !== ''): ?>
        <p class="aviso aviso-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="formulario" method="post" action="<?= e(url('/paciente/pedir-cita.php')) ?>">
        <?= csrf_campo() ?>

        <div class="campo">
            <label for="area_id">Área</label>
            <select id="area_id" name="area_id" required>
                <option value="">Selecciona un área</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?= (int) $area['id'] ?>"
                        <?= $valores['area_id'] === (string) $area['id'] ? 'selected' : '' ?>>
                        <?= e($area['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="fecha_preferida">Fecha preferida (opcional)</label>
            <input type="date" id="fecha_preferida" name="fecha_preferida"
                   min="<?= e(date('Y-m-d')) ?>"
                   value="<?= e($valores['fecha_preferida']) ?>">
        </div>

        <div class="campo">
            <label for="mensaje">Motivo de la consulta</label>
            <textarea id="mensaje" name="mensaje" rows="4" minlength="5" maxlength="500"
                      required><?= e($valores['mensaje']) ?></textarea>
        </div>

        <button class="boton" type="submit">Enviar solicitud</button>
    </form>

    <p class="login-alterno"><a href="<?= e(url('/paciente/citas.php')) ?>">Volver a mis citas</a></p>
</section>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
