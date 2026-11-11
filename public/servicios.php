<?php
// Servicios y especialidades (zona pública).
// Las áreas se leen de la base de datos, así que si se agrega una nueva
// en la tabla "areas", aparece aquí sin tocar este archivo.
require_once __DIR__ . '/../app/core/db.php';

$titulo_pagina = 'Servicios';

// Solo las áreas marcadas como públicas. No hay datos del usuario en esta
// consulta, pero igual usamos prepare() por costumbre (regla de seguridad 1).
$consulta = db()->prepare(
    'SELECT nombre, descripcion FROM areas WHERE publica = 1 ORDER BY nombre'
);
$consulta->execute();
$areas = $consulta->fetchAll();

require __DIR__ . '/../app/layout/publico_header.php';
?>

<section class="seccion">
    <p class="etiqueta">Servicios</p>
    <h1>Cuidamos cada etapa de tu salud</h1>
    <p class="portada-bajada">
        Estas son las áreas donde te atendemos. Si no sabes a cuál ir,
        pide tu cita y te orientamos.
    </p>
</section>

<section class="seccion">
    <?php if (count($areas) === 0): ?>
        <p>Pronto publicaremos nuestros servicios.</p>
    <?php else: ?>
        <div class="rejilla-3">
            <?php foreach ($areas as $area): ?>
                <article class="servicio">
                    <h3><?= e($area['nombre']) ?></h3>
                    <p><?= e($area['descripcion']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="llamado">
    <h2>¿Listo para tu consulta?</h2>
    <p>Ingresa al Portal del paciente, pide tu cita y admisión la confirmará.</p>
    <a class="boton boton-claro" href="<?= e(url('/login.php')) ?>">Portal del paciente</a>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
