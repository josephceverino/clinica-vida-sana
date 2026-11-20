<?php
// Switch de modo claro / oscuro. Se usa igual en las tres zonas, con:
//   require __DIR__ . '/switch_tema.php';
// Opcional: $switch_tema_texto = true muestra el texto "Modo oscuro" al lado
// (en el menú lateral del panel). Sin él, solo se ve el switch.
//
// Lo hace funcionar public/assets/js/tema.js. Nace con "hidden": sin
// JavaScript no serviría, así que no se muestra.
//
// Accesibilidad: role="switch" + aria-checked dicen a los lectores de
// pantalla si el modo oscuro está encendido ("true") o apagado ("false").
$switch_tema_texto = $switch_tema_texto ?? false;
$icono_switch = fn (string $id): string =>
    '<svg class="switch-tema-icono" aria-hidden="true" focusable="false"><use href="'
    . e(recurso('/assets/img/iconos.svg') . '#' . $id) . '"></use></svg>';
?>
<button type="button" class="switch-tema<?= $switch_tema_texto ? ' switch-tema-con-texto' : '' ?>"
        role="switch" aria-checked="false" data-switch-tema hidden
        <?= $switch_tema_texto ? '' : 'aria-label="Modo oscuro" title="Modo oscuro"' ?>>
    <span class="switch-tema-pista" aria-hidden="true">
        <svg class="switch-tema-nubes" viewBox="0 0 24 20" focusable="false">
            <path class="switch-tema-nube-lejana" d="M9 10a3 3 0 0 1 3-3h1a4 4 0 0 1 7.8 1H22a2 2 0 0 1 0 4H9z" />
            <path d="M2 17a3 3 0 0 1 3-3h1a4.5 4.5 0 0 1 8.6.3 3.1 3.1 0 0 1 4.7 2.7 2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" />
        </svg>
        <svg class="switch-tema-estrellas" viewBox="0 0 24 22" focusable="false">
            <path d="m8 3 1.3 3.7L13 8 9.3 9.3 8 13l-1.3-3.7L3 8l3.7-1.3zM17 12l.9 2.1L20 15l-2.1.9L17 18l-.9-2.1L14 15l2.1-.9z" />
            <circle cx="18" cy="5" r="1" />
            <circle cx="7" cy="17" r="1" />
        </svg>
        <span class="switch-tema-perilla">
            <?= $icono_switch('i-sol') ?>
            <?= $icono_switch('i-luna') ?>
        </span>
    </span>
    <?php if ($switch_tema_texto): ?>
        <span class="switch-tema-texto">Modo oscuro</span>
    <?php endif; ?>
</button>
<?php $switch_tema_texto = false; // para que el siguiente switch empiece sin texto ?>
