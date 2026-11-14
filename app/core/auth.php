<?php
// Lógica de login y permisos. La usan todos los roles (personal y pacientes).
//   - Páginas de public/panel/    -> requerir_rol(['admin', ...])
//   - Páginas de public/paciente/ -> requerir_paciente()
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/errores.php';
require_once __DIR__ . '/limites.php';

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
 * Los datos son: id, nombre, usuario, rol, paciente_id, verificado.
 */
function usuario_actual(): ?array
{
    iniciar_sesion_segura();
    return $_SESSION['usuario'] ?? null;
}

/**
 * Página de inicio de cada rol: el paciente va a su portal y el personal
 * al panel.
 */
function ruta_inicio_por_rol(string $rol): string
{
    return $rol === 'paciente' ? '/paciente/index.php' : '/panel/index.php';
}

/**
 * Busca la cuenta y comprueba su contraseña.
 * El personal entra con su "usuario" y el paciente con su "correo".
 * Devuelve los datos de la cuenta si todo es correcto, o null si no.
 */
function verificar_credenciales(string $identificador, string $password): ?array
{
    // Consulta preparada: lo que escribe la persona nunca se pega dentro del SQL.
    $consulta = db()->prepare(
        'SELECT id, nombre, usuario, password_hash, rol, paciente_id, verificado
         FROM usuarios
         WHERE (usuario = :usuario OR correo = :correo) AND activo = 1'
    );
    $consulta->execute(['usuario' => $identificador, 'correo' => $identificador]);
    $fila = $consulta->fetch();

    // Si la cuenta no existe, igual comparamos contra un hash cualquiera.
    // Así tarda lo mismo y nadie puede adivinar qué cuentas existen.
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
        'id'          => (int) $fila['id'],
        'nombre'      => $fila['nombre'],
        'usuario'     => $fila['usuario'],
        'rol'         => $fila['rol'],
        'paciente_id' => $fila['paciente_id'] === null ? null : (int) $fila['paciente_id'],
        'verificado'  => (int) $fila['verificado'],
    ];
}

/**
 * Obliga a estar logueado. Si no, manda al login.
 */
function requerir_login(): void
{
    $actual = usuario_actual();

    if ($actual === null) {
        redirigir('/login.php');
    }

    // Volvemos a mirar en la base de datos: si un admin desactivó la cuenta,
    // le cambió el rol o admisión la verificó mientras la sesión estaba
    // abierta, el cambio se aplica desde la siguiente página.
    $consulta = db()->prepare(
        'SELECT activo, rol, verificado, paciente_id FROM usuarios WHERE id = ?'
    );
    $consulta->execute([$actual['id']]);
    $fila = $consulta->fetch();

    if ($fila === false || (int) $fila['activo'] !== 1) {
        cerrar_sesion();
        redirigir('/login.php');
    }

    $_SESSION['usuario']['rol'] = $fila['rol'];
    $_SESSION['usuario']['verificado'] = (int) $fila['verificado'];
    $_SESSION['usuario']['paciente_id'] = $fila['paciente_id'] === null
        ? null
        : (int) $fila['paciente_id'];
}

/**
 * Obliga a tener uno de los roles indicados (zona del personal).
 * Uso: requerir_rol(['admin', 'admision']);
 */
function requerir_rol(array $roles): void
{
    requerir_login();

    $rol = usuario_actual()['rol'];

    if (!in_array($rol, $roles, true)) {
        // Un paciente no debe ver ni siquiera el diseño del panel.
        mostrar_error(403, $rol !== 'paciente');
    }
}

/**
 * Obliga a ser paciente (portal del paciente).
 * La cuenta puede estar pendiente de verificación.
 */
function requerir_paciente(): void
{
    requerir_login();

    if (usuario_actual()['rol'] !== 'paciente') {
        mostrar_error(403);
    }
}

/**
 * Obliga a ser paciente CON la cuenta verificada por admisión.
 * Va en las páginas que muestran datos (citas, historia clínica).
 * Si aún está pendiente, lo manda a la pantalla que se lo explica.
 */
function requerir_paciente_verificado(): void
{
    requerir_paciente();

    if (usuario_actual()['verificado'] !== 1) {
        redirigir('/paciente/index.php');
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
