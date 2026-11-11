<?php

require_once __DIR__ . '/db.php';

/**
 * Consulta citas con los datos del paciente y del área.
 * Permite filtrar por una fecha exacta.
 */
function buscar_citas(string $fecha = ''): array
{
    $fecha = trim($fecha);

    if ($fecha !== '') {
        $fecha_validada = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $fecha
        );

        if (
            !$fecha_validada
            || $fecha_validada->format('Y-m-d') !== $fecha
        ) {
            throw new InvalidArgumentException(
                'La fecha de búsqueda no es válida.'
            );
        }
    }

    $sql = 'SELECT citas.id, citas.fecha, citas.hora,
                   citas.motivo, citas.estado,
                   pacientes.dni, pacientes.nombres,
                   pacientes.apellidos,
                   areas.nombre AS area
            FROM citas
            INNER JOIN pacientes
                ON pacientes.id = citas.paciente_id
            INNER JOIN areas
                ON areas.id = citas.area_id';

    $parametros = [];

    if ($fecha !== '') {
        $sql .= ' WHERE citas.fecha = :fecha';
        $parametros['fecha'] = $fecha;
    }

    $sql .= ' ORDER BY citas.fecha DESC, citas.hora ASC
              LIMIT 100';

    $consulta = db()->prepare($sql);
    $consulta->execute($parametros);

    return $consulta->fetchAll();
}