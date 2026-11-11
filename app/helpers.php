<?php
// Funciones pequeñas que usamos en muchas páginas.

/**
 * Lee la configuración. Si no existe config.php, usa config.example.php
 * para que la web al menos abra en un equipo recién clonado.
 */
function config(string $clave, $por_defecto = null)
{
    static $config = null;

    if ($config === null) {
        $archivo = __DIR__ . '/../config/config.php';
        if (!file_exists($archivo)) {
            $archivo = __DIR__ . '/../config/config.example.php';
        }
        $config = require $archivo;
    }

    return $config[$clave] ?? $por_defecto;
}

/**
 * Escapa texto antes de imprimirlo en HTML (evita XSS).
 * Uso: <?= e($nombre) ?>
 */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/**
 * Arma una ruta de la web. Uso: <?= e(url('/assets/css/base.css')) ?>
 */
function url(string $ruta = '/'): string
{
    return rtrim(config('base_url', ''), '/') . '/' . ltrim($ruta, '/');
}

/**
 * Inicia la sesión con cookies seguras (HttpOnly y SameSite=Lax).
 * Si la sesión ya está iniciada, no hace nada.
 */
function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // En producción (HTTPS) la cookie solo viaja por conexión segura.
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/**
 * Guarda un mensaje para mostrarlo en la siguiente página
 * (por ejemplo, "Usuario creado" después de redirigir).
 * $tipo puede ser 'ok' o 'error'.
 */
function mensaje_guardar(string $texto, string $tipo = 'ok'): void
{
    iniciar_sesion_segura();
    $_SESSION['mensaje'] = ['texto' => $texto, 'tipo' => $tipo];
}

/**
 * Devuelve el mensaje guardado (y lo borra, para que salga una sola vez).
 * Si no hay mensaje devuelve null.
 */
function mensaje_tomar(): ?array
{
    iniciar_sesion_segura();
    $mensaje = $_SESSION['mensaje'] ?? null;
    unset($_SESSION['mensaje']);
    return $mensaje;
}

/**
 * Devuelve el token CSRF de la sesión (lo crea si no existe).
 */
function csrf_token(): string
{
    iniciar_sesion_segura();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Imprime el campo oculto que va dentro de cada formulario POST.
 * Uso: <?= csrf_campo() ?>
 */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Comprueba que el token enviado por POST sea el de la sesión.
 * Devuelve true si es válido.
 */
function csrf_verificar(): bool
{
    iniciar_sesion_segura();

    $enviado = $_POST['csrf_token'] ?? '';
    $guardado = $_SESSION['csrf_token'] ?? '';

    return $guardado !== '' && hash_equals($guardado, $enviado);
}
