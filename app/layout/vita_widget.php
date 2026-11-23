<?php
// Vita: botón flotante y panel de chat del asistente guiado.
// Lo incluyen publico_footer.php (web pública, cuando $vita_visible es true:
// no se muestra en Login ni en Registro) y paciente_footer.php (portal).
//
// Sin JavaScript el widget queda oculto (atributo "hidden"): la ayuda
// sigue disponible en la página "Ayuda" del pie. vita.js lo activa.
// Va FUERA de <main> y del pie: así no altera el botón de WhatsApp, que
// solo se aparta de los controles de esas zonas (ver publico.js).

/**
 * Dibuja a Vita, la mascota. Su silueta forma la cruz del logo (cabeza al
 * centro, "auriculares" a los lados y la base de la antena arriba) y de la
 * antena brota la hoja. Es un robot asistente: sin bata ni estetoscopio,
 * para no confundirse con un médico. Es decorativo (aria-hidden): el texto
 * del botón o del encabezado ya dice quién es.
 */
if (!function_exists('vita_mascota')) {
function vita_mascota(string $clase): string
{
    return '<svg class="vita-mascota ' . e($clase) . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
        // Hoja que brota de la antena (como la hoja del logo)
        . '<path class="vm-tallo" d="M32 9.5 C32.6 7 34 5.2 36 4"/>'
        . '<path class="vm-hoja" d="M33.2 8.6 C33.4 3.6 37.8 0.6 44.2 1.2 C44 6.6 39.4 9.8 33.2 8.6 Z"/>'
        . '<path class="vm-nervio" d="M34.6 7.6 C37.4 5.6 40 4 42.6 2.8"/>'
        // Brazos de la cruz: base de la antena y "auriculares"
        . '<rect class="vm-cruz" x="25" y="8" width="14" height="10" rx="3.5"/>'
        . '<rect class="vm-cruz" x="2.5" y="26" width="12" height="17" rx="4.5"/>'
        . '<rect class="vm-cruz" x="49.5" y="26" width="12" height="17" rx="4.5"/>'
        // Cabeza, visor, ojos y sonrisa
        . '<rect class="vm-cabeza" x="10.5" y="14" width="43" height="40" rx="14"/>'
        . '<rect class="vm-visor" x="16" y="22.5" width="32" height="20.5" rx="10.25"/>'
        . '<rect class="vm-ojo" x="23" y="28.5" width="5" height="7.5" rx="2.5"/>'
        . '<rect class="vm-ojo" x="36" y="28.5" width="5" height="7.5" rx="2.5"/>'
        . '<path class="vm-boca" d="M27.6 38.4 Q32 41.2 36.4 38.4"/>'
        . '<circle class="vm-luz" cx="32" cy="48.6" r="1.7"/>'
        . '</svg>';
}
}
?>
<div class="vita" data-vita hidden
     data-vita-endpoint="<?= e(url('/vita/responder.php')) ?>"
     data-vita-ayuda="<?= e(url('/ayuda.php')) ?>">

    <!-- Botón flotante (abajo a la izquierda) -->
    <button class="vita-lanzador" type="button" data-vita-lanzador
            aria-expanded="false" aria-controls="vita-panel">
        <span class="vita-lanzador-avatar"><?= vita_mascota('vita-mascota-lanzador') ?></span>
        <span class="vita-lanzador-texto">
            <span class="vita-lanzador-pregunta">¿Dudas?</span>
            Pregúntale a Vita
        </span>
    </button>

    <!-- Panel de chat (diálogo NO modal: se puede seguir usando la página).
         tabindex="-1": en el celular, al abrir, el foco va al panel (y no al
         campo de texto) para que el teclado no aparezca sin pedirlo. -->
    <section class="vita-panel" id="vita-panel" role="dialog" aria-modal="false" tabindex="-1"
             aria-labelledby="vita-titulo" aria-describedby="vita-descripcion" hidden data-vita-panel>

        <header class="vita-cabecera">
            <span class="vita-cabecera-avatar"><?= vita_mascota('vita-mascota-cabecera') ?></span>
            <div class="vita-cabecera-texto">
                <h2 id="vita-titulo">Vita</h2>
                <p id="vita-descripcion">Asistente virtual de Clínica Vida Sana</p>
            </div>
            <button class="vita-cerrar" type="button" data-vita-cerrar aria-label="Cerrar Vita">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M6 6 L18 18 M18 6 L6 18"/>
                </svg>
            </button>
        </header>

        <!-- Una sola línea: el saludo de Vita ya explica en qué ayuda -->
        <p class="vita-nota">
            <strong>No doy consejos médicos</strong> ni guardo lo que escribes.
        </p>

        <!-- Conversación: role="log" anuncia los mensajes nuevos a los lectores de pantalla -->
        <div class="vita-mensajes" role="log" aria-live="polite" aria-relevant="additions"
             aria-label="Conversación con Vita" tabindex="0" data-vita-mensajes></div>

        <!-- Saludo y primeras sugerencias LOCALES: vita.js los muestra al abrir
             sin llamar al servidor (no gasta el límite de mensajes ni necesita
             la base de datos). Las claves y textos son los de las preguntas
             publicadas (VITA_SUGERENCIAS_INICIALES en app/modelos/vita.php y
             sql/migracion-2026-11-vita.sql): si cambian allí, cambiarlos aquí. -->
        <p hidden data-vita-saludo>¡Hola! Soy Vita, el asistente de Clínica Vida Sana. Puedo ayudarte con citas, tu cuenta, servicios, horarios y contacto.</p>
        <ul hidden data-vita-iniciales>
            <li data-clave="como-pedir-cita">¿Cómo pido una cita?</li>
            <li data-clave="horarios-atencion">¿Cuál es el horario de atención?</li>
            <li data-clave="ubicacion-contacto">¿Dónde están y cómo me comunico con la clínica?</li>
        </ul>

        <!-- Preguntas sugeridas (las arma vita.js con lo que devuelve el servidor) -->
        <div class="vita-sugerencias" role="group" aria-label="Preguntas sugeridas" data-vita-sugerencias hidden></div>

        <form class="vita-formulario" data-vita-formulario novalidate>
            <label class="solo-lectores" for="vita-entrada">Escribe tu pregunta para Vita</label>
            <input class="vita-entrada" id="vita-entrada" type="text" maxlength="200"
                   autocomplete="off" enterkeyhint="send" placeholder="Escribe tu pregunta…"
                   aria-describedby="vita-estado" data-vita-entrada>
            <button class="vita-enviar" type="submit" data-vita-enviar>
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M4 12 L20 4 L14 20 L11.5 13 Z"/>
                </svg>
                <span class="solo-lectores">Enviar</span>
            </button>
        </form>

        <!-- Avisos breves: campo vacío, espera por el límite, etc. -->
        <p class="vita-estado" id="vita-estado" role="status" data-vita-estado></p>

        <p class="vita-pie">
            <a href="<?= e(url('/ayuda.php')) ?>">Ver todas las preguntas frecuentes</a>
        </p>
    </section>
</div>
