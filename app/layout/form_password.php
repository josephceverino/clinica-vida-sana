<?php
// Formulario "Cambiar contraseña". Lo usan panel/perfil.php y
// paciente/perfil.php. Antes de incluirlo, la página define:
//   $accion_formulario -> dirección a la que se envía (ej. url('/panel/perfil.php'))
?>
<form class="formulario form-password" method="post" action="<?= e($accion_formulario) ?>">
    <?= csrf_campo() ?>

    <div class="campo">
        <label for="actual">Contraseña actual</label>
        <input type="password" id="actual" name="actual" required
               autocomplete="current-password">
    </div>

    <div class="campo">
        <label for="nueva">Nueva contraseña</label>
        <input type="password" id="nueva" name="nueva" required
               minlength="8" maxlength="72" autocomplete="new-password">
        <small>De 8 a 72 caracteres, con al menos una letra y un número.</small>
    </div>

    <div class="campo">
        <label for="confirmar">Repite la nueva contraseña</label>
        <input type="password" id="confirmar" name="confirmar" required
               minlength="8" maxlength="72" autocomplete="new-password">
    </div>

    <button class="boton" type="submit">Cambiar contraseña</button>
</form>
