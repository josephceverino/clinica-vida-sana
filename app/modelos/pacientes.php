<?php

require_once __DIR__ . '/../core/db.php';

/**
 * Busca por número de documento, nombres o apellidos.
 */
function buscar_pacientes(string $busqueda = ''): array
{
    $conexion = db();
    $busqueda = trim($busqueda);

    if ($busqueda === '') {
        return $conexion->query(
            'SELECT id, tipo_documento, numero_documento, nombres,
                    apellidos, fecha_nacimiento, telefono
             FROM pacientes
             ORDER BY id DESC
             LIMIT 50'
        )->fetchAll();
    }

    $consulta = $conexion->prepare(
        'SELECT id, tipo_documento, numero_documento, nombres,
                apellidos, fecha_nacimiento, telefono
         FROM pacientes
         WHERE numero_documento LIKE :documento
            OR nombres LIKE :nombres
            OR apellidos LIKE :apellidos
         ORDER BY apellidos, nombres
         LIMIT 50'
    );

    $texto = '%' . $busqueda . '%';

    $consulta->execute([
        'documento' => $texto,
        'nombres' => $texto,
        'apellidos' => $texto,
    ]);

    return $consulta->fetchAll();
}

/**
 * Valida y registra un paciente. Devuelve su ID.
 */
function registrar_paciente(array $datos): int
{
    $dni = trim($datos['dni'] ?? '');
    $nombres = trim($datos['nombres'] ?? '');
    $apellidos = trim($datos['apellidos'] ?? '');
    $fecha = trim($datos['fecha_nacimiento'] ?? '');
    $telefono = trim($datos['telefono'] ?? '');

    if (!preg_match('/^[0-9]{8}$/', $dni)) {
        throw new InvalidArgumentException(
            'El DNI debe tener 8 dígitos.'
        );
    }

    if (
        $nombres === '' || $apellidos === ''
        || mb_strlen($nombres) > 100
        || mb_strlen($apellidos) > 100
    ) {
        throw new InvalidArgumentException(
            'Nombres y apellidos: obligatorios, máximo 100 caracteres.'
        );
    }

    $fecha_validada = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $fecha
    );

    if (
        !$fecha_validada
        || $fecha_validada->format('Y-m-d') !== $fecha
        || $fecha > date('Y-m-d')
    ) {
        throw new InvalidArgumentException(
            'La fecha de nacimiento no es válida.'
        );
    }

    if (
        $telefono !== ''
        && !preg_match('/^[0-9+() -]{6,20}$/', $telefono)
    ) {
        throw new InvalidArgumentException(
            'El teléfono debe tener entre 6 y 20 caracteres válidos.'
        );
    }

    $conexion = db();

    // Por ahora admisión registra solo con DNI (tipo_documento = 'DNI').
    // El registro del portal permitirá también CE y pasaporte.
    $consulta = $conexion->prepare(
        "INSERT INTO pacientes
            (tipo_documento, numero_documento, nombres, apellidos,
             fecha_nacimiento, telefono)
         VALUES
            ('DNI', :dni, :nombres, :apellidos, :fecha, :telefono)"
    );

    try {
        $consulta->execute([
            'dni' => $dni,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'fecha' => $fecha,
            'telefono' => $telefono === '' ? null : $telefono,
        ]);
    } catch (PDOException $error) {
        if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
            throw new InvalidArgumentException(
                'Ya existe un paciente con ese DNI.'
            );
        }

        throw $error;
    }

    return (int) $conexion->lastInsertId();
}