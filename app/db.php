<?php

require_once __DIR__ . '/helpers.php';

/**
 * Abre la conexión y la reutiliza durante la petición.
 */
function db(): PDO
{
    static $conexion = null;

    if ($conexion === null) {
        $datos = config('db');

        $dsn = 'mysql:host=' . $datos['host']
            . ';dbname=' . $datos['nombre']
            . ';charset=' . $datos['charset'];

        $conexion = new PDO(
            $dsn,
            $datos['usuario'],
            $datos['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $conexion;
}