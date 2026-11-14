<?php
// "Mi perfil" del personal (admin, admisión y médico): sus datos y el
// cambio de contraseña.
require_once __DIR__ . '/../../app/core/auth.php';
require_once __DIR__ . '/../../app/modelos/perfil.php';

// Regla 6: solo personal.
requerir_rol(['admin', 'admision', 'medico']);

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
        redirigir('/panel/perfil.php');
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
$accion_formulario = url('/panel/perfil.php');

// Nombre del rol para mostrarlo bonito.
$nombres_rol = [
    'admin'    => 'Administrador',
    'admision' => 'Admisión',
    'medico'   => 'Médico',
];

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<header class="panel-titulo">
    <div>
        <h1>Mi perfil</h1>
        <p>Tus datos de acceso y tu contraseña.</p>
    </div>
</header>

<?php if ($mensaje): ?>
    <p role="status"><?= e($mensaje['texto']) ?></p>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p role="alert"><?= e($error) ?></p>
<?php endif; ?>

<div class="panel-columnas">
    <section class="caja">
        <h2>Mis datos</h2>
        <dl class="datos">
            <dt>Nombre</dt>
            <dd><?= e($cuenta['nombre']) ?></dd>
            <dt>Usuario (para entrar)</dt>
            <dd><?= e($cuenta['usuario']) ?></dd>
            <dt>Rol</dt>
            <dd><?= e($nombres_rol[$cuenta['rol']] ?? $cuenta['rol']) ?></dd>
            <dt>Área</dt>
            <dd><?= e($cuenta['area'] ?? 'Sin área') ?></dd>
            <dt>Cuenta creada el</dt>
            <dd><?= e(date('d/m/Y', strtotime($cuenta['creado_en']))) ?></dd>
        </dl>
        <p class="vacio">Para cambiar tu nombre, usuario o área, pídeselo al administrador.</p>
    </section>

    <section class="caja">
        <h2>Cambiar contraseña</h2>
        <?php require __DIR__ . '/../../app/layout/form_password.php'; ?>
    </section>
</div>

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
