<?php
// Registro de pacientes (crea una cuenta del Portal del paciente).
require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/modelos/cuentas.php';

// Si ya inició sesión, no tiene sentido registrarse.
$sesion = usuario_actual();
if ($sesion !== null) {
    redirigir(ruta_inicio_por_rol($sesion['rol']));
}

$titulo_pagina = 'Regístrate';
$errores = [];
$tipos = tipos_documento();

// Valores del formulario (para no borrarlos si hay un error).
// Las contraseñas NUNCA se devuelven al formulario.
$valores = [
    'nombres' => '', 'apellidos' => '', 'tipo_documento' => '',
    'numero_documento' => '', 'correo' => '', 'celular' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($valores as $campo => $vacio) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    if (!csrf_verificar()) {
        $errores[] = 'La sesión expiró. Recarga la página e inténtalo otra vez.';
    } elseif (registro_bloqueado($valores['numero_documento'])) {
        $errores[] = 'Demasiados intentos. Espera un rato e inténtalo otra vez.';
    } else {
        // Cada envío cuenta para el límite, salga bien o mal.
        registrar_intento('registro', $valores['numero_documento']);

        [$errores, $datos] = validar_registro($_POST);

        if (count($errores) === 0) {
            if (registrar_cuenta_paciente($datos)) {
                mensaje_guardar(
                    'Cuenta creada. Admisión comprobará tu identidad; mientras tanto '
                    . 'ya puedes iniciar sesión.'
                );
                redirigir('/login.php');
            }

            // Mensaje genérico: no decimos si falló el correo o el documento.
            $errores[] = 'No pudimos crear la cuenta con esos datos. Si ya tienes una, '
                . 'inicia sesión. Si crees que es un error, acércate a admisión.';
        }
    }
}

require __DIR__ . '/../app/layout/publico_header.php';
?>

<section class="registro">
    <h1>Regístrate</h1>
    <p>Crea tu cuenta del Portal del paciente. Es gratis y solo toma un minuto.</p>

    <?php if (count($errores) > 0): ?>
        <div class="aviso aviso-error" role="alert">
            <?php foreach ($errores as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="formulario formulario-grid" method="post"
          action="<?= e(url('/registro.php')) ?>" novalidate>
        <?= csrf_campo() ?>

        <div class="campo">
            <label for="nombres">Nombres</label>
            <input type="text" id="nombres" name="nombres" maxlength="100"
                   placeholder="Nombres" value="<?= e($valores['nombres']) ?>"
                   autocomplete="given-name" required>
        </div>

        <div class="campo">
            <label for="correo">Correo</label>
            <input type="email" id="correo" name="correo" maxlength="120"
                   placeholder="correo@ejemplo.com" value="<?= e($valores['correo']) ?>"
                   autocomplete="email" required>
        </div>

        <div class="campo">
            <label for="apellidos">Apellidos</label>
            <input type="text" id="apellidos" name="apellidos" maxlength="100"
                   placeholder="Apellidos" value="<?= e($valores['apellidos']) ?>"
                   autocomplete="family-name" required>
        </div>

        <div class="campo">
            <label for="celular">Número de celular</label>
            <input type="tel" id="celular" name="celular" maxlength="20"
                   placeholder="Celular" value="<?= e($valores['celular']) ?>"
                   autocomplete="tel" required>
        </div>

        <div class="campo">
            <label for="tipo_documento">Tipo de documento</label>
            <select id="tipo_documento" name="tipo_documento" required>
                <option value="">Selecciona</option>
                <?php foreach ($tipos as $clave => $tipo): ?>
                    <option value="<?= e($clave) ?>"
                        <?= $valores['tipo_documento'] === $clave ? 'selected' : '' ?>>
                        <?= e($tipo['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="campo">
            <label for="password">Establece una contraseña</label>
            <input type="password" id="password" name="password"
                   placeholder="Contraseña" autocomplete="new-password" required>
        </div>

        <div class="campo">
            <label for="numero_documento">N.º de documento</label>
            <input type="text" id="numero_documento" name="numero_documento" maxlength="12"
                   placeholder="Número de documento" value="<?= e($valores['numero_documento']) ?>"
                   autocomplete="off" required>
        </div>

        <div class="campo">
            <label for="confirmar_password">Confirma tu contraseña</label>
            <input type="password" id="confirmar_password" name="confirmar_password"
                   placeholder="Repite la contraseña" autocomplete="new-password" required>
        </div>

        <p class="formulario-nota">
            La contraseña debe tener de 8 a 72 caracteres, con al menos una letra y un número.
            Tu cuenta quedará <strong>pendiente</strong> hasta que admisión compruebe tu identidad.
        </p>

        <!-- Consentimiento (Ley 29733). Obligatorio: también se valida en PHP. -->
        <label class="casilla-privacidad">
            <input type="checkbox" name="acepto_privacidad" value="1" required
                <?= isset($_POST['acepto_privacidad']) ? 'checked' : '' ?>>
            <span>
                He leído y acepto la
                <a href="<?= e(url('/privacidad.php')) ?>" target="_blank" rel="noopener">política
                de privacidad</a> y autorizo el tratamiento de mis datos personales y de salud
                para mi atención en la clínica.
            </span>
        </label>

        <button class="boton" type="submit">Crear cuenta</button>
    </form>

    <p class="login-alterno">
        ¿Ya tienes cuenta? <a href="<?= e(url('/login.php')) ?>">Inicia sesión</a>
    </p>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
