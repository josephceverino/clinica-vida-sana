<?php
// Límite de intentos de login y de registro.
//
// Los intentos se guardan en la tabla "intentos_acceso" (base de datos), no
// en la sesión. Así, borrar la cookie o abrir otro navegador NO reinicia el
// contador: el límite se cuenta por IP y por cuenta.
require_once __DIR__ . '/db.php';

// Login: intentos FALLIDOS permitidos dentro de la ventana.
const LIMITE_LOGIN_POR_CUENTA = 5;    // contra una misma cuenta
const LIMITE_LOGIN_POR_IP     = 20;   // desde una misma IP (todas las cuentas)
const VENTANA_LOGIN_SEG       = 600;  // 10 minutos

// Registro: envíos del formulario permitidos dentro de la ventana.
const LIMITE_REGISTRO_POR_IP        = 10;    // desde una misma IP
const LIMITE_REGISTRO_POR_DOCUMENTO = 5;     // para un mismo documento
const VENTANA_REGISTRO_SEG          = 3600;  // 1 hora

/**
 * IP de quien hace la petición.
 * Solo usamos REMOTE_ADDR: las cabeceras como X-Forwarded-For las puede
 * escribir el atacante y así se saltaría el límite.
 */
function ip_cliente(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/**
 * Normaliza lo que se usa como "clave" (usuario, correo o documento), para
 * que "Admin" y "admin " cuenten como lo mismo.
 */
function clave_intento(string $texto): string
{
    return mb_substr(mb_strtolower(trim($texto)), 0, 120);
}

/**
 * Cuenta los intentos recientes de un tipo, por IP o por clave.
 * $campo solo puede ser 'ip' o 'clave' (lo decide el código, nunca el usuario).
 */
function contar_intentos(string $tipo, string $campo, string $valor, int $ventana_seg): int
{
    if (!in_array($campo, ['ip', 'clave'], true)) {
        throw new InvalidArgumentException('Campo no permitido.');
    }

    $consulta = db()->prepare(
        "SELECT COUNT(*) FROM intentos_acceso
         WHERE tipo = :tipo AND $campo = :valor
           AND creado_en > DATE_SUB(NOW(), INTERVAL :ventana SECOND)"
    );
    $consulta->execute([
        'tipo'    => $tipo,
        'valor'   => $valor,
        'ventana' => $ventana_seg,
    ]);

    return (int) $consulta->fetchColumn();
}

/**
 * Guarda un intento. De vez en cuando borra los muy viejos (más de 1 día)
 * para que la tabla no crezca sin fin.
 */
function registrar_intento(string $tipo, string $clave): void
{
    $insertar = db()->prepare(
        'INSERT INTO intentos_acceso (tipo, ip, clave) VALUES (?, ?, ?)'
    );
    $insertar->execute([$tipo, ip_cliente(), clave_intento($clave)]);

    // Limpieza ocasional (1 de cada 50 intentos).
    if (random_int(1, 50) === 1) {
        db()->exec(
            'DELETE FROM intentos_acceso WHERE creado_en < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        );
    }
}

/**
 * Borra los intentos fallidos de una cuenta (se llama cuando el login sale bien).
 */
function limpiar_intentos(string $tipo, string $clave): void
{
    $borrar = db()->prepare('DELETE FROM intentos_acceso WHERE tipo = ? AND clave = ?');
    $borrar->execute([$tipo, clave_intento($clave)]);
}

/**
 * ¿Está bloqueado el login para esta IP o esta cuenta?
 */
function login_bloqueado(string $identificador): bool
{
    return contar_intentos('login', 'ip', ip_cliente(), VENTANA_LOGIN_SEG) >= LIMITE_LOGIN_POR_IP
        || contar_intentos('login', 'clave', clave_intento($identificador), VENTANA_LOGIN_SEG) >= LIMITE_LOGIN_POR_CUENTA;
}

/**
 * ¿Está bloqueado el registro para esta IP o este documento?
 */
function registro_bloqueado(string $documento): bool
{
    return contar_intentos('registro', 'ip', ip_cliente(), VENTANA_REGISTRO_SEG) >= LIMITE_REGISTRO_POR_IP
        || contar_intentos('registro', 'clave', clave_intento($documento), VENTANA_REGISTRO_SEG) >= LIMITE_REGISTRO_POR_DOCUMENTO;
}
