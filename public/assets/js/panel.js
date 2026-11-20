// Menú lateral del panel: contraer en escritorio y abrir/cerrar en el celular.
//
// Se carga en <head> SIN "defer" (ver app/layout/panel_header.php):
// 1) Apenas se lee, marca <html> con las clases necesarias, antes de que la
//    página se pinte. Así el menú contraído no "salta" al cargar.
// 2) Cuando el HTML terminó de cargar (DOMContentLoaded), conecta los botones.
//
// Sin JavaScript el panel sigue funcionando: los botones nacen con "hidden"
// y el menú se ve completo.
(function () {
    'use strict';

    var html = document.documentElement;
    var CLAVE = 'vidasana.panel.lateral';   // dónde se guarda la preferencia
    var celular = window.matchMedia('(max-width: 900px)');

    // localStorage puede fallar (modo privado, cookies bloqueadas):
    // si falla, simplemente no se recuerda la preferencia.
    function leer_preferencia() {
        try {
            return window.localStorage.getItem(CLAVE);
        } catch (error) {
            return null;
        }
    }

    function guardar_preferencia(valor) {
        try {
            window.localStorage.setItem(CLAVE, valor);
        } catch (error) {
            // Sin almacenamiento: no pasa nada.
        }
    }

    // --- Paso 1: antes de pintar ---------------------------------------
    html.classList.add('js');
    if (leer_preferencia() === 'contraida') {
        html.classList.add('lateral-contraida');
    }

    // --- Paso 2: con el HTML ya cargado --------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        var lateral = document.getElementById('panel-lateral');
        var boton_contraer = document.querySelector('[data-contraer-lateral]');
        var boton_abrir = document.querySelector('[data-abrir-menu]');
        var boton_cerrar = document.querySelector('[data-cerrar-menu]');
        var velo = document.querySelector('[data-velo-menu]');
        // Lo que queda "detrás" del menú abierto en el celular.
        var fondo = [
            document.querySelector('.panel-principal'),
            document.querySelector('.panel-barra-movil')
        ];

        if (!lateral || !boton_contraer || !boton_abrir || !boton_cerrar || !velo) {
            return;
        }

        // Con JavaScript, los botones ya sirven: se muestran.
        boton_contraer.hidden = false;
        boton_abrir.hidden = false;
        boton_cerrar.hidden = false;

        // ---------- Escritorio: contraer / expandir ----------
        function pintar_boton_contraer() {
            var contraida = html.classList.contains('lateral-contraida');
            var texto = contraida ? 'Expandir menú' : 'Contraer menú';
            boton_contraer.setAttribute('aria-expanded', contraida ? 'false' : 'true');
            boton_contraer.setAttribute('aria-label', texto);
            boton_contraer.title = texto;
        }

        boton_contraer.addEventListener('click', function () {
            var contraida = html.classList.toggle('lateral-contraida');
            guardar_preferencia(contraida ? 'contraida' : 'expandida');
            pintar_boton_contraer();
        });

        pintar_boton_contraer();

        // ---------- Celular: abrir / cerrar el menú ----------
        function abrir_menu() {
            html.classList.add('menu-abierto');
            velo.hidden = false;
            boton_abrir.setAttribute('aria-expanded', 'true');
            // "inert": lo de atrás no se puede usar ni recibir el foco.
            fondo.forEach(function (elemento) {
                if (elemento) { elemento.inert = true; }
            });
            boton_cerrar.focus();
        }

        function cerrar_menu(devolver_foco) {
            if (!html.classList.contains('menu-abierto')) {
                return;
            }
            html.classList.remove('menu-abierto');
            velo.hidden = true;
            boton_abrir.setAttribute('aria-expanded', 'false');
            fondo.forEach(function (elemento) {
                if (elemento) { elemento.inert = false; }
            });
            if (devolver_foco) {
                boton_abrir.focus();
            }
        }

        boton_abrir.addEventListener('click', abrir_menu);
        boton_cerrar.addEventListener('click', function () { cerrar_menu(true); });
        velo.addEventListener('click', function () { cerrar_menu(true); });

        // La tecla Escape cierra el menú.
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                cerrar_menu(true);
            }
        });

        // Si la ventana pasa a tamaño escritorio con el menú abierto, se cierra.
        celular.addEventListener('change', function () {
            if (!celular.matches) {
                cerrar_menu(false);
            }
        });

        // Las animaciones se activan después de la primera pintura:
        // al cargar la página el menú aparece ya en su sitio, sin moverse.
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                html.classList.add('lateral-animada');
            });
        });
    });

    // --- Formularios que tardan: indicador "cargando" ------------------
    // Solo en formularios con el atributo data-cargando. El evento "submit"
    // llega cuando el navegador ya validó los campos. El botón muestra un
    // círculo que gira y su texto cambia (ej. "Registrando…"); así se ve que
    // la página trabaja y no se envía dos veces. El mensaje de éxito lo
    // sigue mostrando PHP cuando de verdad terminó.
    document.addEventListener('DOMContentLoaded', function () {
        var formularios = document.querySelectorAll('form[data-cargando]');

        formularios.forEach(function (formulario) {
            // Un formulario puede tener varios botones (ej. Confirmar y
            // Cancelar cita). Se guarda el texto original de cada uno.
            var botones = Array.prototype.slice.call(
                formulario.querySelectorAll('button[type="submit"]')
            );
            if (botones.length === 0) {
                return;
            }
            var textos = botones.map(function (boton) { return boton.textContent; });
            var enviando = false;

            formulario.addEventListener('submit', function (evento) {
                // Ya se está enviando: se ignora el segundo clic.
                if (enviando) {
                    evento.preventDefault();
                    return;
                }
                enviando = true;

                // El botón pulsado (evento.submitter) muestra el círculo y su
                // texto de carga; su name/value ya va en el envío.
                var pulsado = evento.submitter || botones[0];
                pulsado.setAttribute('aria-busy', 'true');
                if (pulsado.dataset.textoCargando) {
                    pulsado.textContent = pulsado.dataset.textoCargando;
                }
                // Se desactivan un instante después, para no estorbar el envío.
                window.setTimeout(function () {
                    botones.forEach(function (boton) { boton.disabled = true; });
                }, 0);
            });

            // Si el usuario vuelve con "Atrás", los botones vuelven a la normalidad.
            window.addEventListener('pageshow', function () {
                enviando = false;
                botones.forEach(function (boton, i) {
                    boton.removeAttribute('aria-busy');
                    boton.disabled = false;
                    boton.textContent = textos[i];
                });
            });
        });
    });
}());
