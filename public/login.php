<?php
// Inicio de sesión (Portal del paciente y acceso del personal).
// Es el único login: según el rol, lleva al portal o al panel.
require_once __DIR__ . '/../app/core/auth.php';

// Si ya inició sesión, no tiene sentido ver el login.
$sesion = usuario_actual();
if ($sesion !== null) {
    redirigir(ruta_inicio_por_rol($sesion['rol']));
}

$titulo_pagina = 'Portal del paciente';
$error = '';
$identificador = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_verificar()) {
        // El token no coincide: el formulario no vino de esta página.
        $error = 'La sesión expiró. Recarga la página e inténtalo otra vez.';
    } elseif ($identificador === '' || $password === ''
        || mb_strlen($identificador) > 120 || strlen($password) > 200) {
        // Validación en el servidor (aunque el navegador también valide).
        $error = 'Escribe tu usuario o correo y tu contraseña.';
    } elseif (login_bloqueado($identificador)) {
        // Ni siquiera comprobamos la contraseña: así un ataque de fuerza
        // bruta no puede seguir probando mientras dura el bloqueo.
        $error = 'Demasiados intentos. Espera unos minutos e inténtalo otra vez.';
    } else {
        $fila = verificar_credenciales($identificador, $password);

        if ($fila !== null) {
            limpiar_intentos('login', $identificador);
            iniciar_login($fila);
            redirigir(ruta_inicio_por_rol($fila['rol']));
        }

        // Mensaje genérico: no decimos si falló la cuenta o la contraseña.
        registrar_intento('login', $identificador);
        $error = 'Usuario, correo o contraseña incorrectos.';
    }
}

// Aviso de otra página (por ejemplo, "Cuenta creada" después de registrarse).
$mensaje = mensaje_tomar();

require __DIR__ . '/../app/layout/publico_header.php';
?>

<section class="login">
    <h1>Portal del paciente</h1>
    <p>Ingresa para ver tus citas y tu historial. El personal de la clínica entra por aquí también.</p>

    <?php if ($mensaje !== null): ?>
        <p class="aviso aviso-<?= e($mensaje['tipo']) ?>" role="status"><?= e($mensaje['texto']) ?></p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="aviso-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="formulario" method="post" action="<?= e(url('/login.php')) ?>">
        <?= csrf_campo() ?>

        <div class="campo">
            <label for="identificador">Usuario o correo</label>
            <input type="text" id="identificador" name="identificador" maxlength="120"
                   value="<?= e($identificador) ?>" autocomplete="username" required autofocus>
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>

        <button class="boton" type="submit">Ingresar</button>
    </form>

    <p class="login-alterno">
        ¿No tienes cuenta? <a href="<?= e(url('/registro.php')) ?>">Regístrate</a>
    </p>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
