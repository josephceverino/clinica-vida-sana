<?php

require_once __DIR__ . '/db.php';

/**
 * Consulta solicitudes y permite filtrar por estado.
 */
function buscar_solicitudes(string $estado = ''): array
{
    $estados_validos = ['nueva', 'atendida', 'descartada'];

    if (
        $estado !== ''
        && !in_array($estado, $estados_validos, true)
    ) {
        throw new InvalidArgumentException(
            'El estado de la solicitud no es válido.'
        );
    }

    $sql = 'SELECT solicitudes_cita.id,
                   solicitudes_cita.nombre,
                   solicitudes_cita.dni,
                   solicitudes_cita.telefono,
                   solicitudes_cita.fecha_preferida,
                   solicitudes_cita.mensaje,
                   solicitudes_cita.estado,
                   solicitudes_cita.creado_en,
                   areas.nombre AS area
            FROM solicitudes_cita
            INNER JOIN areas
                ON areas.id = solicitudes_cita.area_id';

    $parametros = [];

    if ($estado !== '') {
        $sql .= ' WHERE solicitudes_cita.estado = :estado';
        $parametros['estado'] = $estado;
    }

    $sql .= ' ORDER BY solicitudes_cita.creado_en DESC,
                       solicitudes_cita.id DESC
              LIMIT 100';

    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}

/**
 * Valida y guarda una solicitud para un área pública.
 */
function registrar_solicitud(array $datos): int
{
    $campos = [
        'nombre', 'dni', 'telefono', 'area_id',
        'fecha_preferida', 'mensaje',
    ];

    foreach ($campos as $campo) {
        if (
            isset($datos[$campo])
            && !is_scalar($datos[$campo])
        ) {
            throw new InvalidArgumentException(
                'Los datos enviados no son válidos.'
            );
        }
    }

    $nombre = trim((string) ($datos['nombre'] ?? ''));
    $dni = trim((string) ($datos['dni'] ?? ''));
    $telefono = trim((string) ($datos['telefono'] ?? ''));
    $fecha = trim((string) ($datos['fecha_preferida'] ?? ''));
    $mensaje = trim((string) ($datos['mensaje'] ?? ''));

    $area_id = filter_var(
        $datos['area_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($nombre === '' || mb_strlen($nombre) > 100) {
        throw new InvalidArgumentException(
            'El nombre es obligatorio y admite hasta 100 caracteres.'
        );
    }

    if (!preg_match('/^[0-9]{8}$/', $dni)) {
        throw new InvalidArgumentException(
            'El DNI debe tener 8 dígitos.'
        );
    }

    if (!preg_match('/^[0-9+() -]{6,20}$/', $telefono)) {
        throw new InvalidArgumentException(
            'Indica un teléfono válido de entre 6 y 20 caracteres.'
        );
    }

    if (!$area_id) {
        throw new InvalidArgumentException(
            'Selecciona un área válida.'
        );
    }

    if (mb_strlen($mensaje) > 500) {
        throw new InvalidArgumentException(
            'El mensaje admite hasta 500 caracteres.'
        );
    }

    if ($fecha !== '') {
        $zona = new DateTimeZone('America/Lima');

        $fecha_validada = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha,
            $zona
        );

        if (
            !$fecha_validada
            || $fecha_validada->format('Y-m-d') !== $fecha
            || $fecha_validada < new DateTimeImmutable('today', $zona)
        ) {
            throw new InvalidArgumentException(
                'La fecha preferida no es válida o ya pasó.'
            );
        }
    }

    $conexion = db();

    // Rechaza áreas internas aunque se manipule el formulario.
    $consulta = $conexion->prepare(
        'SELECT id FROM areas
         WHERE id = :id AND publica = 1'
    );
    $consulta->execute(['id' => $area_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException(
            'El área seleccionada no está disponible para solicitudes.'
        );
    }

    $consulta = $conexion->prepare(
        "INSERT INTO solicitudes_cita
            (nombre, dni, telefono, area_id,
             fecha_preferida, mensaje, estado)
         VALUES
            (:nombre, :dni, :telefono, :area,
             :fecha, :mensaje, 'nueva')"
    );

    $consulta->execute([
        'nombre' => $nombre,
        'dni' => $dni,
        'telefono' => $telefono,
        'area' => $area_id,
        'fecha' => $fecha === '' ? null : $fecha,
        'mensaje' => $mensaje === '' ? null : $mensaje,
    ]);

    return (int) $conexion->lastInsertId();
}

/**
 * Cambia el estado de una solicitud nueva.
 * El usuario debe obtenerse de la sesión.
 */
function cambiar_estado_solicitud(
    int $solicitud_id,
    string $estado,
    int $usuario_id
): void {
    if ($solicitud_id < 1) {
        throw new InvalidArgumentException(
            'La solicitud no es válida.'
        );
    }

    if (!in_array($estado, ['atendida', 'descartada'], true)) {
        throw new InvalidArgumentException(
            'Selecciona atendida o descartada.'
        );
    }

    $conexion = db();

    $consulta = $conexion->prepare(
        "SELECT id FROM usuarios
         WHERE id = :id AND activo = 1
           AND rol IN ('admin', 'admision')"
    );
    $consulta->execute(['id' => $usuario_id]);

    if (!$consulta->fetch()) {
        throw new InvalidArgumentException(
            'El usuario no está autorizado.'
        );
    }

    $consulta = $conexion->prepare(
        "UPDATE solicitudes_cita
         SET estado = :estado
         WHERE id = :id AND estado = 'nueva'"
    );
    $consulta->execute([
        'estado' => $estado,
        'id' => $solicitud_id,
    ]);

    if ($consulta->rowCount() === 0) {
        throw new InvalidArgumentException(
            'La solicitud no existe o ya fue procesada.'
        );
    }
}