<?php
// Copia este archivo como config/config.php y pon tus datos locales.
// config.php NO se sube a GitHub (está en .gitignore).

return [
    // Ruta base de la web.
    // Desarrollo (XAMPP):  '/clinica-vida-sana/public'
    // Producción (Fedora): ''  (vacío, porque el DocumentRoot es public/)
    'base_url' => '/clinica-vida-sana/public',

    // true = muestra errores detallados (solo en desarrollo). En producción: false.
    'debug' => true,

    // Conexión a la base de datos (MariaDB)
    'db' => [
        'host'     => 'localhost',
        'nombre'   => 'clinica_vida_sana',
        'usuario'  => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],
];
