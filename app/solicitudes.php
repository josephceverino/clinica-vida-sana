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