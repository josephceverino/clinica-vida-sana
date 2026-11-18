// Modo claro / oscuro de toda la web (sitio público, portal y panel).
//
// Se carga en <head> SIN "defer", antes de pintar la página (ver las
// cabeceras en app/layout/). Así el tema correcto se aplica desde el primer
// momento y no se ve un "parpadeo" del tema equivocado. Va en un archivo
// aparte porque la política de seguridad (CSP) bloquea el JS incrustado.
//
// Cómo decide el tema:
// 1) Si la persona ya eligió uno con el switch, se usa ese (localStorage).
// 2) Si nunca eligió, se sigue el tema de su sistema operativo.
//
// El CSS solo mira el atributo <html data-tema="oscuro">.
// Sin JavaScript la web se ve en modo claro y el switch no aparece.
(function () {
    'use strict';

    var html = document.documentElement;
    var CLAVE = 'vidasana.tema';   // la misma clave en las tres zonas
    var sistema_oscuro = window.matchMedia('(prefers-color-scheme: dark)');
    var menos_movimiento = window.matchMedia('(prefers-reduced-motion: reduce)');

    // localStorage puede fallar (modo privado, cookies bloqueadas):
    // si falla, simplemente no se recuerda la elección.
    function leer_preferencia() {
        try {
            var valor = window.localStorage.getItem(CLAVE);
            return valor === 'claro' || valor === 'oscuro' ? valor : null;
        } catch (error) {
            return null;
        }
    }

    function guardar_preferencia(valor) {
        try {
            window.localStorage.setItem(CLAVE, valor);
        } catch (error) {
            // Sin almacenamiento: el tema cambia igual, pero no se recuerda.
        }
    }

    function tema_inicial() {
        return leer_preferencia() || (sistema_oscuro.matches ? 'oscuro' : 'claro');
    }

    // Pone el tema en <html> y actualiza todos los switches de la página.
    function aplicar_tema(tema) {
        html.setAttribute('data-tema', tema);

        var switches = document.querySelectorAll('[data-switch-tema]');
        for (var i = 0; i < switches.length; i++) {
            switches[i].setAttribute('aria-checked', tema === 'oscuro' ? 'true' : 'false');
        }
    }

    // Al cambiar con el switch, los colores pasan suavemente (--mov-medio,
    // 240 ms, en base.css). La clase "tema-cambiando" solo existe durante
    // ese momento (un poco más, 320 ms), para no retrasar los efectos
    // normales de la página (hover, foco...).
    // Con "reducir movimiento" el cambio es inmediato.
    var temporizador = null;

    function aplicar_tema_suave(tema) {
        if (!menos_movimiento.matches) {
            html.classList.add('tema-cambiando');
            // Si se pulsa dos veces seguidas, se reinicia la cuenta: así el
            // segundo cambio también termina suave y no "salta".
            window.clearTimeout(temporizador);
            temporizador = window.setTimeout(function () {
                html.classList.remove('tema-cambiando');
            }, 320);
        }
        aplicar_tema(tema);
    }

    // --- Paso 1: antes de pintar ---------------------------------------
    aplicar_tema(tema_inicial());

    // Si la persona nunca eligió y cambia el tema de su sistema, se sigue.
    sistema_oscuro.addEventListener('change', function () {
        if (leer_preferencia() === null) {
            aplicar_tema_suave(tema_inicial());
        }
    });

    // Si cambia el tema en otra pestaña de la clínica, esta se actualiza.
    window.addEventListener('storage', function (evento) {
        if (evento.key === CLAVE) {
            aplicar_tema_suave(tema_inicial());
        }
    });

    // Al volver con el botón "Atrás", el navegador puede mostrar la página
    // guardada en memoria (con el tema de antes): se revisa otra vez.
    window.addEventListener('pageshow', function () {
        aplicar_tema(tema_inicial());
    });

    // --- Paso 2: con el HTML ya cargado --------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        var switches = document.querySelectorAll('[data-switch-tema]');

        for (var i = 0; i < switches.length; i++) {
            // Con JavaScript, el switch ya sirve: se muestra.
            switches[i].hidden = false;

            switches[i].addEventListener('click', function () {
                var nuevo = html.getAttribute('data-tema') === 'oscuro' ? 'claro' : 'oscuro';
                guardar_preferencia(nuevo);
                aplicar_tema_suave(nuevo);
            });
        }

        // Marca los switches con el tema actual.
        aplicar_tema(html.getAttribute('data-tema') || tema_inicial());
    });
}());
