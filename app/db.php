<?php
// Conexión a la base de datos con PDO.
// Uso en cualquier página:
//     require_once __DIR__ . '/../app/db.php';
//     $pdo = db();
require_once __DIR__ . '/helpers.php';

/**
 * Devuelve la conexión PDO. Se crea una sola vez y se reutiliza.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $datos = config('db');

    $dsn = 'mysql:host=' . $datos['host']
         . ';dbname=' . $datos['nombre']
         . ';charset=' . $datos['charset'];

    try {
        $pdo = new PDO($dsn, $datos['usuario'], $datos['password'], [
            // Si algo falla, PDO lanza una excepción (así no pasa desapercibido).
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Los resultados vienen como arreglo con nombre de columna.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Consultas preparadas reales (más seguras contra inyección SQL).
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $error) {
        // Mensaje genérico al usuario; el detalle solo en desarrollo.
        http_response_code(500);
        if (config('debug', false)) {
            exit('Error de conexión: ' . e($error->getMessage()));
        }
        exit('No pudimos conectar con el sistema. Inténtalo más tarde.');
    }

    return $pdo;
}
