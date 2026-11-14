<?php
// Registro de auditoría: anota quién hizo qué, sobre qué paciente, cuándo y
// desde qué IP. Sirve para responder "¿quién vio la historia clínica de
// esta persona?" (Ley 29733: la historia clínica es un dato sensible).
//
// Uso:  registrar_auditoria('ver_historia', $paciente_id);
require_once __DIR__ . '/auth.php';   // usuario_actual(), db() e ip_cliente()

/**
 * Acciones que se registran, con el texto que se muestra en pantalla.
 * Solo se aceptan estas: así nadie inventa acciones sin querer.
 */
function acciones_auditoria(): array
{
    return [
        'iniciar_sesion'     => 'Inició sesión',
        'ver_historia'       => 'Vio la historia clínica',
        'ver_atencion'       => 'Abrió la atención de una cita',
        'registrar_atencion' => 'Registró una atención',
        'cambiar_password'   => 'Cambió su contraseña',
        'verificar_cuenta'   => 'Verificó una cuenta de paciente',
        'rechazar_cuenta'    => 'Rechazó una cuenta de paciente',
        'programar_cita'     => 'Programó una cita desde una solicitud',
    ];
}

/**
 * Guarda una fila de auditoría. Quien la hace sale de la SESIÓN.
 * $usuario_id solo se pasa cuando aún no hay sesión (justo al iniciarla).
 *
 * Si falla, NO detiene la página (se anota en el log del servidor): que se
 * caiga la auditoría no debe impedir atender a un paciente.
 */
function registrar_auditoria(
    string $accion,
    ?int $paciente_id = null,
    string $detalle = '',
    ?int $usuario_id = null
): void {
    if (!isset(acciones_auditoria()[$accion])) {
        error_log('Auditoría: acción desconocida "' . $accion . '"');
        return;
    }

    if ($usuario_id === null) {
        $usuario_id = usuario_actual()['id'] ?? null;
    }

    try {
        $consulta = db()->prepare(
            'INSERT INTO auditoria (usuario_id, accion, paciente_id, detalle, ip)
             VALUES (:usuario, :accion, :paciente, :detalle, :ip)'
        );
        $consulta->execute([
            'usuario'  => $usuario_id,
            'accion'   => $accion,
            'paciente' => $paciente_id,
            'detalle'  => $detalle === '' ? null : mb_substr($detalle, 0, 255),
            'ip'       => ip_cliente(),
        ]);
    } catch (PDOException $error) {
        error_log('Auditoría: no se pudo registrar: ' . $error->getMessage());
    }
}

/**
 * Busca en la auditoría (para la página del admin).
 * Filtros opcionales: $accion (una de acciones_auditoria()) y $paciente_id.
 * Devuelve como máximo las 200 filas más recientes.
 */
function buscar_auditoria(string $accion = '', ?int $paciente_id = null): array
{
    $sql = "SELECT auditoria.id, auditoria.accion, auditoria.detalle,
                   auditoria.ip, auditoria.creado_en,
                   usuarios.nombre AS usuario, usuarios.rol,
                   CONCAT(pacientes.nombres, ' ', pacientes.apellidos) AS paciente
            FROM auditoria
            LEFT JOIN usuarios ON usuarios.id = auditoria.usuario_id
            LEFT JOIN pacientes ON pacientes.id = auditoria.paciente_id
            WHERE 1 = 1";
    $parametros = [];

    // Los filtros se agregan como texto fijo; los valores van como parámetros.
    if ($accion !== '' && isset(acciones_auditoria()[$accion])) {
        $sql .= ' AND auditoria.accion = :accion';
        $parametros['accion'] = $accion;
    }

    if ($paciente_id !== null) {
        $sql .= ' AND auditoria.paciente_id = :paciente';
        $parametros['paciente'] = $paciente_id;
    }

    $sql .= ' ORDER BY auditoria.creado_en DESC, auditoria.id DESC LIMIT 200';

    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}

/**
 * Quién del PERSONAL consultó los datos clínicos de un paciente (para que
 * el propio paciente lo vea en su historia clínica). No incluye sus propias
 * consultas: solo las del personal.
 */
function accesos_a_mi_historia(int $paciente_id, int $limite = 10): array
{
    $consulta = db()->prepare(
        "SELECT auditoria.accion, auditoria.creado_en,
                usuarios.nombre AS usuario, usuarios.rol
         FROM auditoria
         INNER JOIN usuarios ON usuarios.id = auditoria.usuario_id
         WHERE auditoria.paciente_id = :paciente
           AND usuarios.rol <> 'paciente'
           AND auditoria.accion IN ('ver_atencion', 'registrar_atencion', 'ver_historia')
         ORDER BY auditoria.creado_en DESC, auditoria.id DESC
         LIMIT " . max(1, min(50, $limite))
    );
    $consulta->execute(['paciente' => $paciente_id]);

    return $consulta->fetchAll();
}
