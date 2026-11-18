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
}());
