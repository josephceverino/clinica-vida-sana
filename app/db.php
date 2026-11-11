<?php
// Conexión a la base de datos con PDO.
// Uso en cualquier página:
//     require_once __DIR__ . '/../app/db.php';
//     $pdo = db();
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/errores.php';

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
        // El detalle solo se ve en desarrollo (regla 9).
        if (config('debug', false)) {
            http_response_code(500);
            exit('Error de conexión: ' . e($error->getMessage()));
        }
        // En producción: página de error genérica, sin detalles técnicos.
        error_log('Error de conexión a la base de datos: ' . $error->getMessage());
        mostrar_error(500);
    }

    return $pdo;
}
