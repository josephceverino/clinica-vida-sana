<?php
// "Mi perfil": datos de la propia cuenta y cambio de contraseña.
// Sirve para todos los roles (personal y pacientes).
// El ID de la cuenta sale SIEMPRE de la sesión, nunca de un formulario.
require_once __DIR__ . '/../core/db.php';

/**
 * Datos de la cuenta para mostrarlos en "Mi perfil".
 * Si es paciente, trae también su ficha (documento, teléfono...).
 */
function datos_de_mi_cuenta(int $usuario_id): ?array
{
    $consulta = db()->prepare(
        'SELECT usuarios.id, usuarios.nombre, usuarios.usuario, usuarios.correo,
                usuarios.rol, usuarios.verificado, usuarios.creado_en,
                areas.nombre AS area,
                pacientes.tipo_documento, pacientes.numero_documento,
                pacientes.telefono, pacientes.fecha_nacimiento
         FROM usuarios
         LEFT JOIN areas ON areas.id = usuarios.area_id
         LEFT JOIN pacientes ON pacientes.id = usuarios.paciente_id
         WHERE usuarios.id = :id'
    );
    $consulta->execute(['id' => $usuario_id]);
    $fila = $consulta->fetch();

    return $fila ?: null;
}

/**
 * Cambia la contraseña de la cuenta que inició sesión.
 * Pide la contraseña ACTUAL: así, si alguien deja la sesión abierta en
 * una computadora, otra persona no puede cambiarle la contraseña.
 * Lanza InvalidArgumentException con un mensaje para el usuario si algo falla.
 */
function cambiar_password(
    int $usuario_id,
    string $actual,
    string $nueva,
    string $confirmar
): void {
    // Mismas reglas que el registro de pacientes (app/modelos/cuentas.php).
    if (strlen($nueva) < 8 || strlen($nueva) > 72) {
        throw new InvalidArgumentException(
            'La nueva contraseña debe tener entre 8 y 72 caracteres.'
        );
    }

    if (!preg_match('/[A-Za-z]/', $nueva) || !preg_match('/[0-9]/', $nueva)) {
        throw new InvalidArgumentException(
            'La nueva contraseña debe incluir al menos una letra y un número.'
        );
    }

    if ($nueva !== $confirmar) {
        throw new InvalidArgumentException('Las contraseñas nuevas no coinciden.');
    }

    $consulta = db()->prepare(
        'SELECT password_hash FROM usuarios WHERE id = :id AND activo = 1'
    );
    $consulta->execute(['id' => $usuario_id]);
    $hash_actual = $consulta->fetchColumn();

    // Regla 2: se compara con password_verify(), nunca el texto.
    if ($hash_actual === false || !password_verify($actual, $hash_actual)) {
        throw new InvalidArgumentException('La contraseña actual no es correcta.');
    }

    if (password_verify($nueva, $hash_actual)) {
        throw new InvalidArgumentException(
            'La nueva contraseña debe ser distinta de la actual.'
        );
    }

    $consulta = db()->prepare(
        'UPDATE usuarios SET password_hash = :hash WHERE id = :id'
    );
    $consulta->execute([
        'hash' => password_hash($nueva, PASSWORD_DEFAULT),
        'id'   => $usuario_id,
    ]);

    // Nueva sesión (nuevo ID de cookie) tras un cambio de seguridad.
    session_regenerate_id(true);
}
