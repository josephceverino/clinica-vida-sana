<?php
// Páginas de error amigables (403, 404 y 500).
// Uso:  mostrar_error(404);         -> con el diseño de la web pública
//       mostrar_error(403, true);   -> con el diseño del panel
require_once __DIR__ . '/helpers.php';

/**
 * Muestra una página de error con el código HTTP correcto y detiene el script.
 * Los mensajes son genéricos a propósito (regla 9): nunca detalles técnicos.
 */
function mostrar_error(int $codigo, bool $en_panel = false): void
{
    $textos = [
        403 => ['Acceso no permitido', 'No tienes permiso para ver esta página.'],
        404 => ['Página no encontrada', 'La página que buscas no existe o fue movida.'],
        500 => ['Algo salió mal', 'No pudimos completar tu solicitud. Inténtalo más tarde.'],
    ];

    // Si piden un código que no tenemos, usamos el 500.
    if (!isset($textos[$codigo])) {
        $codigo = 500;
    }

    [$titulo, $mensaje] = $textos[$codigo];
    http_response_code($codigo);

    $titulo_pagina = $titulo;
    $pagina_activa = '';   // En el panel, que no marque ninguna sección del menú

    if ($en_panel) {
        require __DIR__ . '/layout/panel_header.php';
        $enlace = url('/panel/index.php');
        $texto_enlace = 'Volver al panel';
    } else {
        require __DIR__ . '/layout/header.php';
        $enlace = url('/');
        $texto_enlace = 'Volver al inicio';
    }
    ?>
    <section class="error-pagina">
        <p class="error-codigo"><?= (int) $codigo ?></p>
        <h1><?= e($titulo) ?></h1>
        <p><?= e($mensaje) ?></p>
        <a class="boton" href="<?= e($enlace) ?>"><?= e($texto_enlace) ?></a>
    </section>
    <?php
    require __DIR__ . ($en_panel ? '/layout/panel_footer.php' : '/layout/footer.php');
    exit;
}
