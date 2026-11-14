<?php
// "Mi perfil" del paciente: sus datos y el cambio de contraseña.
// La puede abrir aunque su cuenta siga pendiente de verificación.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/perfil.php';

// Solo pacientes (verificados o pendientes).
requerir_paciente();

$titulo_pagina = 'Mi perfil';
$pagina_activa = 'perfil.php';
$error = '';

// La cuenta sale SIEMPRE de la sesión.
$usuario_id = (int) usuario_actual()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Regla 4: token CSRF.
        if (!csrf_verificar()) {
            throw new InvalidArgumentException(
                'La sesión del formulario venció. Recarga la página.'
            );
        }

        cambiar_password(
            $usuario_id,
            is_string($_POST['actual'] ?? null) ? $_POST['actual'] : '',
            is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '',
            is_string($_POST['confirmar'] ?? null) ? $_POST['confirmar'] : ''
        );

        mensaje_guardar('Tu contraseña se cambió correctamente.');
        redirigir('/paciente/perfil.php');
    } catch (InvalidArgumentException $excepcion) {
        $error = $excepcion->getMessage();
    } catch (PDOException $excepcion) {
        // Regla 9: mensaje genérico; el detalle va al log.
        error_log($excepcion->getMessage());
        $error = 'No se pudo cambiar la contraseña. Inténtalo más tarde.';
    }
}

$cuenta = datos_de_mi_cuenta($usuario_id);
$mensaje = mensaje_tomar();
$accion_formulario = url('/paciente/perfil.php');

require __DIR__ . '/../../app/layout/paciente_header.php';
?>

<section class="seccion">
    <p class="etiqueta">Portal del paciente</p>
    <h1>Mi perfil</h1>

    <?php if ($mensaje): ?>
        <p class="aviso aviso-<?= e($mensaje['tipo']) ?>" role="status"><?= e($mensaje['texto']) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="aviso aviso-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
</section>

<div class="rejilla-2">
    <section class="tarjeta-paciente">
        <h2>Mis datos</h2>
        <dl class="atencion-datos">
            <dt>Nombre</dt>
            <dd><?= e($cuenta['nombre']) ?></dd>
            <dt>Documento</dt>
            <dd><?= e($cuenta['tipo_documento'] . ' ' . $cuenta['numero_documento']) ?></dd>
            <dt>Correo (para entrar)</dt>
            <dd><?= e($cuenta['correo']) ?></dd>
            <dt>Teléfono</dt>
            <dd><?= e($cuenta['telefono'] ?? 'No registrado') ?></dd>
            <dt>Estado de la cuenta</dt>
            <dd><?= (int) $cuenta['verificado'] === 1 ? 'Verificada' : 'Pendiente de verificación' ?></dd>
        </dl>
        <p class="vacio">Si algún dato está mal, acércate a admisión para corregirlo.</p>
    </section>

    <section>
        <h2>Cambiar contraseña</h2>
        <?php require __DIR__ . '/../../app/layout/form_password.php'; ?>
    </section>
</div>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
