<?php
// Lógica de login y permisos.
// Todas las páginas de public/panel/ empiezan con requerir_login().
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

// Intentos fallidos permitidos antes de bloquear, y minutos de bloqueo.
const LOGIN_MAX_INTENTOS = 5;
const LOGIN_SEGUNDOS_BLOQUEO = 60;

/**
 * Redirige a otra página de la web y detiene el script.
 * Uso: redirigir('/login.php');
 */
function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

/**
 * Devuelve los datos del usuario que inició sesión, o null si no hay nadie.
 * Los datos son: id, nombre, usuario, rol.
 */
function usuario_actual(): ?array
{
    iniciar_sesion_segura();
    return $_SESSION['usuario'] ?? null;
}

/**
 * Busca al usuario y comprueba su contraseña.
 * Devuelve los datos del usuario si todo es correcto, o null si no.
 */
function verificar_credenciales(string $usuario, string $password): ?array
{
    // Consulta preparada: el usuario nunca se pega dentro del SQL.
    $consulta = db()->prepare(
        'SELECT id, nombre, usuario, password_hash, rol
         FROM usuarios
         WHERE usuario = ? AND activo = 1'
    );
    $consulta->execute([$usuario]);
    $fila = $consulta->fetch();

    // Si el usuario no existe, igual comparamos contra un hash cualquiera.
    // Así tarda lo mismo y nadie puede adivinar qué usuarios existen.
    $hash = $fila['password_hash']
        ?? '$2y$10$0PMYbo0/EJ4MdkUz3h/QJ.LtO9/VpKf7QhRBhad0GOZqftrkWpMs6';
    $correcta = password_verify($password, $hash);

    if ($fila === false || !$correcta) {
        return null;
    }

    return $fila;
}

/**
 * Guarda al usuario en la sesión. Se llama solo cuando el login fue correcto.
 */
function iniciar_login(array $fila): void
{
    iniciar_sesion_segura();

    // Cambia el ID de la sesión para evitar que alguien reutilice uno viejo.
    session_regenerate_id(true);

    // Guardamos solo lo necesario (nunca el hash de la contraseña).
    $_SESSION['usuario'] = [
        'id'      => (int) $fila['id'],
        'nombre'  => $fila['nombre'],
        'usuario' => $fila['usuario'],
        'rol'     => $fila['rol'],
    ];

    // Limpia el conteo de intentos fallidos.
    unset($_SESSION['intentos_fallidos'], $_SESSION['bloqueado_hasta']);
}

/**
 * Devuelve cuántos segundos faltan para poder volver a intentar (0 = libre).
 */
function login_segundos_bloqueo(): int
{
    iniciar_sesion_segura();

    $hasta = $_SESSION['bloqueado_hasta'] ?? 0;
    return max(0, $hasta - time());
}

/**
 * Cuenta un intento fallido y bloquea si se pasó del máximo.
 */
function login_registrar_fallo(): void
{
    iniciar_sesion_segura();

    $_SESSION['intentos_fallidos'] = ($_SESSION['intentos_fallidos'] ?? 0) + 1;

    if ($_SESSION['intentos_fallidos'] >= LOGIN_MAX_INTENTOS) {
        $_SESSION['bloqueado_hasta'] = time() + LOGIN_SEGUNDOS_BLOQUEO;
        $_SESSION['intentos_fallidos'] = 0;
    }
}

/**
 * Obliga a estar logueado. Si no, manda al login.
 * Va al inicio de cada página de public/panel/.
 */
function requerir_login(): void
{
    $actual = usuario_actual();

    if ($actual === null) {
        redirigir('/login.php');
    }

    // Volvemos a mirar en la base de datos: si un admin desactivó a este
    // usuario (o le cambió el rol) mientras tenía la sesión abierta,
    // el cambio se aplica desde la siguiente página.
    $consulta = db()->prepare('SELECT activo, rol FROM usuarios WHERE id = ?');
    $consulta->execute([$actual['id']]);
    $fila = $consulta->fetch();

    if ($fila === false || (int) $fila['activo'] !== 1) {
        cerrar_sesion();
        redirigir('/login.php');
    }

    $_SESSION['usuario']['rol'] = $fila['rol'];
}

/**
 * Obliga a tener uno de los roles indicados.
 * Uso: requerir_rol(['admin', 'admision']);
 */
function requerir_rol(array $roles): void
{
    requerir_login();

    if (!in_array(usuario_actual()['rol'], $roles, true)) {
        http_response_code(403);
        exit('No tienes permiso para ver esta página.');
    }
}

/**
 * Cierra la sesión por completo (datos y cookie).
 */
function cerrar_sesion(): void
{
    iniciar_sesion_segura();

    $_SESSION = [];

    // Borra también la cookie de sesión del navegador.
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $cookie['path'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_destroy();
}
