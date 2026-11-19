<?php
// Inicio de sesión (Portal del paciente y acceso del personal).
// Es el único login: según el rol, lleva al portal o al panel.
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/auditoria.php';

// Si ya inició sesión, no tiene sentido ver el login.
$sesion = usuario_actual();
if ($sesion !== null) {
    redirigir(ruta_inicio_por_rol($sesion['rol']));
}

$titulo_pagina = 'Portal del paciente';
$error = '';
$identificador = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim(campo_texto($_POST, 'identificador'));
    $password = campo_texto($_POST, 'password');

    if (!csrf_verificar()) {
        // El token no coincide: el formulario no vino de esta página.
        $error = 'La sesión expiró. Recarga la página e inténtalo otra vez.';
    } elseif ($identificador === '' || $password === ''
        || mb_strlen($identificador) > 120 || strlen($password) > 200) {
        // Validación en el servidor (aunque el navegador también valide).
        $error = 'Escribe tu usuario o correo y tu contraseña.';
    } else {
        // El intento se anota ANTES de comprobar la contraseña. Si se anotara
        // después, 30 intentos enviados a la vez pasarían todos el control
        // antes de que se guarde ninguno (lo probamos: pasaban 10, no 5).
        registrar_intento('login', $identificador);

        if (login_bloqueado($identificador)) {
            // Ni siquiera comprobamos la contraseña: así un ataque de fuerza
            // bruta no puede seguir probando mientras dura el bloqueo.
            $error = 'Demasiados intentos. Espera unos minutos e inténtalo otra vez.';
        } else {
            $fila = verificar_credenciales($identificador, $password);

            if ($fila !== null) {
                // Entró bien: se borran sus intentos (también el de ahora).
                limpiar_intentos('login', $identificador);
                iniciar_login($fila);
                registrar_auditoria('iniciar_sesion');
                redirigir(ruta_inicio_por_rol($fila['rol']));
            }

            // Mensaje genérico: no decimos si falló la cuenta o la contraseña.
            $error = 'Usuario, correo o contraseña incorrectos.';
        }
    }
}

// Aviso de otra página (por ejemplo, "Cuenta creada" después de registrarse).
$mensaje = mensaje_tomar();

require __DIR__ . '/../app/layout/publico_header.php';
?>

<!-- Pantalla partida: marca a la izquierda, formulario a la derecha -->
<section class="pub-acceso aparecer">
    <div class="pub-acceso-marca" aria-hidden="true">
        <img src="<?= e(url('/assets/img/slide-citas.svg')) ?>" alt="" width="320" height="240">
    </div>

    <div class="pub-acceso-formulario">
        <h1>Portal del paciente</h1>
        <p class="pub-bajada">Ingresa para ver tus citas y tu historial. El personal de la clínica entra por aquí también.</p>

        <?php if ($mensaje !== null): ?>
            <div class="pub-aviso <?= $mensaje['tipo'] === 'error' ? 'pub-aviso-error' : 'pub-aviso-exito' ?>" role="status">
                <?= $icono_publico($mensaje['tipo'] === 'error' ? 'i-alerta' : 'i-visto') ?>
                <p><?= e($mensaje['texto']) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="pub-aviso pub-aviso-error" role="alert">
                <?= $icono_publico('i-alerta') ?>
                <p><?= e($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- data-cargando: al enviar, el botón muestra "Ingresando…" y evita el doble envío -->
        <form class="pub-formulario" method="post" action="<?= e(url('/login.php')) ?>" data-cargando>
            <?= csrf_campo() ?>

            <div class="pub-campo">
                <label for="identificador">Usuario o correo</label>
                <input type="text" id="identificador" name="identificador" maxlength="120"
                       value="<?= e($identificador) ?>" autocomplete="username" required autofocus>
            </div>

            <div class="pub-campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button class="pub-boton pub-boton-ancho" type="submit" data-texto-cargando="Ingresando…">Ingresar</button>
        </form>

        <p class="pub-alterno">
            ¿No tienes cuenta? <a href="<?= e(url('/registro.php')) ?>">Regístrate</a>
        </p>
    </div>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
