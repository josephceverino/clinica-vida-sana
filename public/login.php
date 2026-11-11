<?php
// Inicio de sesión del personal (zona privada).
require_once __DIR__ . '/../app/auth.php';

// Si ya inició sesión, no tiene sentido ver el login.
if (usuario_actual() !== null) {
    redirigir('/panel/index.php');
}

$titulo_pagina = 'Acceso del personal';
$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!csrf_verificar()) {
        // El token no coincide: el formulario no vino de esta página.
        $error = 'La sesión expiró. Recarga la página e inténtalo otra vez.';
    } elseif (login_segundos_bloqueo() > 0) {
        $error = 'Demasiados intentos. Espera un minuto e inténtalo otra vez.';
    } elseif ($usuario === '' || $password === '' || strlen($usuario) > 50) {
        // Validación en el servidor (aunque el navegador también valide).
        $error = 'Escribe tu usuario y tu contraseña.';
    } else {
        $fila = verificar_credenciales($usuario, $password);

        if ($fila !== null) {
            iniciar_login($fila);
            redirigir('/panel/index.php');
        }

        // Mensaje genérico: no decimos si falló el usuario o la contraseña.
        login_registrar_fallo();
        $error = 'Usuario o contraseña incorrectos.';
    }
}

require __DIR__ . '/../app/layout/header.php';
?>

<section class="login">
    <h1>Acceso del personal</h1>
    <p>Ingresa con tu usuario para entrar al panel.</p>

    <?php if ($error !== ''): ?>
        <p class="aviso-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="formulario" method="post" action="<?= e(url('/login.php')) ?>">
        <?= csrf_campo() ?>

        <div class="campo">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" maxlength="50"
                   value="<?= e($usuario) ?>" autocomplete="username" required autofocus>
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>

        <button class="boton" type="submit">Ingresar</button>
    </form>
</section>

<?php require __DIR__ . '/../app/layout/footer.php'; ?>
