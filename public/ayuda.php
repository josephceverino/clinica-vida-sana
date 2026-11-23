<?php
// Ayuda: preguntas frecuentes (zona pública).
// Es la base de conocimientos de Vita, el asistente guiado, pero esta
// página NO necesita JavaScript: todo lo arma PHP con las preguntas
// PUBLICADAS en la base (ver app/modelos/vita.php).
// Solo lee: no hay formularios ni se guarda nada de quien la visita.
require_once __DIR__ . '/../app/modelos/vita.php';

$titulo_pagina = 'Ayuda';

// Por ahora la página solo se muestra en español. El quechua se activará
// cuando haya textos revisados por un hablante (no se inventan).
$idioma = 'es';
$preguntas = vita_preguntas_publicadas($idioma);
$categorias = vita_categorias();

require __DIR__ . '/../app/layout/publico_header.php';
?>

<!-- Encabezado -->
<section class="pub-encabezado aparecer">
    <p class="pub-etiqueta">Ayuda</p>
    <h1>Preguntas frecuentes</h1>
    <p class="pub-bajada">
        Respuestas cortas sobre citas, tu cuenta, servicios y contacto.
        Cada una te lleva a la página donde puedes hacerlo.
    </p>
</section>

<?php if (count($preguntas) === 0): ?>
    <section class="pub-seccion">
        <p class="pub-bajada">Pronto publicaremos las preguntas frecuentes.</p>
    </section>
<?php else: ?>
    <!-- Mismo diseño que la política de privacidad: índice + texto -->
    <section class="pub-seccion pub-legal">
        <nav class="pub-legal-indice" aria-label="Temas de ayuda">
            <p>Temas</p>
            <ol>
                <?php foreach ($preguntas as $categoria => $lista): ?>
                    <li><a href="#tema-<?= e($categoria) ?>"><?= e($categorias[$categoria]) ?></a></li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <article class="pub-legal-texto pub-ayuda" lang="<?= e($idioma) ?>">
            <!-- Aviso fijo: la ayuda no reemplaza a un médico -->
            <div class="pub-aviso">
                <?= $icono_publico('i-info') ?>
                <p>Esta ayuda solo explica cómo usar la web. <strong>No da diagnósticos
                ni consejos médicos</strong>: para eso, pide una cita. Si es una emergencia,
                acude de inmediato al servicio de emergencias más cercano.</p>
            </div>

            <?php foreach ($preguntas as $categoria => $lista): ?>
                <h2 id="tema-<?= e($categoria) ?>"><?= e($categorias[$categoria]) ?></h2>

                <?php foreach ($lista as $item): ?>
                    <?php $accion = vita_accion($item['accion']); ?>
                    <section class="pub-ayuda-item" id="<?= e($item['clave']) ?>"
                             aria-labelledby="pregunta-<?= e($item['clave']) ?>"
                             <?= $item['idioma'] !== $idioma ? 'lang="' . e($item['idioma']) . '"' : '' ?>>
                        <h3 id="pregunta-<?= e($item['clave']) ?>"><?= e($item['pregunta']) ?></h3>
                        <?php if (!$item['traducida']): ?>
                            <p class="pub-ayuda-nota">Esta respuesta aún no está disponible en quechua.</p>
                        <?php endif; ?>
                        <p><?= e($item['respuesta']) ?></p>
                        <?php if ($accion !== null): ?>
                            <p class="pub-ayuda-accion">
                                <a class="pub-boton pub-boton-chico pub-boton-secundario"
                                   href="<?= e(url($accion['ruta'])) ?>"><?= e($accion['texto']) ?></a>
                            </p>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </article>
    </section>
<?php endif; ?>

<!-- Llamado final -->
<section class="pub-llamado aparecer">
    <div>
        <h2>¿No encontraste tu respuesta?</h2>
        <p>Escríbenos o llámanos: los datos están en la página de contacto.</p>
    </div>
    <a class="pub-boton pub-boton-blanco" href="<?= e(url('/contacto.php')) ?>">Ir a contacto</a>
</section>

<?php require __DIR__ . '/../app/layout/publico_footer.php'; ?>
