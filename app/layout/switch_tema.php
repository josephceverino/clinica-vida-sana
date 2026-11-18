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
