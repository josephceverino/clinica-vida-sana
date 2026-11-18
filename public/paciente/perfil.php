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

<div class="ui-pagina ui-animada">

    <header class="ui-cabecera">
        <div>
            <h1>Mi perfil</h1>
            <p>Tus datos y tu contraseña.</p>
        </div>
    </header>

    <?php if ($mensaje): ?>
        <div class="ui-aviso <?= $mensaje['tipo'] === 'error' ? 'ui-aviso-error' : 'ui-aviso-exito' ?>" role="status">
            <?= $icono_panel($mensaje['tipo'] === 'error' ? 'i-alerta' : 'i-visto') ?>
            <p><?= e($mensaje['texto']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="ui-aviso ui-aviso-error" role="alert">
            <?= $icono_panel('i-alerta') ?>
            <p><?= e($error) ?></p>
        </div>
    <?php endif; ?>

    <div class="ui-columnas">
        <section class="ui-tarjeta">
            <h2>Mis datos</h2>
            <dl class="datos datos-rejilla">
                <div class="datos-ancho">
                    <dt>Nombre</dt>
                    <dd><?= e($cuenta['nombre']) ?></dd>
                </div>
                <div>
                    <dt>Documento</dt>
                    <dd><?= e($cuenta['tipo_documento'] . ' ' . $cuenta['numero_documento']) ?></dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd><?= e($cuenta['telefono'] ?? 'No registrado') ?></dd>
                </div>
                <div class="datos-ancho">
                    <dt>Correo (para entrar)</dt>
                    <dd><?= e($cuenta['correo']) ?></dd>
                </div>
                <div class="datos-ancho">
                    <dt>Estado de la cuenta</dt>
                    <dd>
                        <?php if ((int) $cuenta['verificado'] === 1): ?>
                            <span class="estado estado-confirmada">Verificada</span>
                        <?php else: ?>
                            <span class="estado estado-pendiente">Pendiente de verificación</span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <p class="ui-nota ui-nota-separada">Si algún dato está mal, acércate a admisión para corregirlo.</p>
        </section>

        <section class="ui-tarjeta">
            <h2>Cambiar contraseña</h2>
            <?php // Formulario compartido con el panel del personal (no se cambia su HTML). ?>
            <?php require __DIR__ . '/../../app/layout/form_password.php'; ?>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../../app/layout/paciente_footer.php'; ?>
