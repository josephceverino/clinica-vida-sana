// =====================================================================
// publico.js - Web pública (JavaScript puro, se carga con "defer").
//   1. Menú del celular: el botón "Menú" lo abre y lo cierra.
//   2. Aparecer al bajar: las secciones .aparecer entran suavemente.
//      (En Inicio lo hace portada.js, que se carga antes: aquí no se repite.)
//   3. Formularios con data-cargando: el botón muestra "Ingresando…" o
//      "Creando cuenta…" mientras el servidor responde.
// Sin JavaScript todo sigue funcionando: el menú se ve completo y nada se oculta.
// =====================================================================
(function () {
    'use strict';

    var reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.documentElement.classList.add('pub-js');

    // -----------------------------------------------------------------
    // 1. Menú del celular
    // -----------------------------------------------------------------
    var cabecera = document.querySelector('[data-cabecera]');
    var boton = document.querySelector('[data-boton-menu]');
    var menu = document.getElementById('pub-menu');

    if (cabecera && boton && menu) {
        boton.hidden = false;

        function abrir_menu(abrir) {
            cabecera.classList.toggle('pub-menu-abierto', abrir);
            boton.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        }

        boton.addEventListener('click', function () {
            abrir_menu(boton.getAttribute('aria-expanded') !== 'true');
        });

        // Escape cierra el menú y devuelve el foco al botón.
        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && cabecera.classList.contains('pub-menu-abierto')) {
                abrir_menu(false);
                boton.focus();
            }
        });

        // Si la ventana pasa a tamaño escritorio, el menú vuelve a la normalidad.
        window.matchMedia('(min-width: 861px)').addEventListener('change', function (cambio) {
            if (cambio.matches) {
                abrir_menu(false);
            }
        });
    }

    // -----------------------------------------------------------------
    // 2. Aparecer al bajar (en todas las páginas públicas, también inicio)
    // -----------------------------------------------------------------
    var elementos = document.querySelectorAll('.aparecer');
    if (elementos.length > 0) {
        if (reducirMovimiento || !('IntersectionObserver' in window)) {
            elementos.forEach(function (el) { el.classList.add('visible'); });
        } else {
            // Lo que ya se ve al abrir la página queda visible desde el
            // principio: no se oculta para volver a aparecer (eso sería un
            // parpadeo y retrasaría la lectura). Solo se anima lo de abajo.
            elementos.forEach(function (el) {
                if (el.getBoundingClientRect().top < window.innerHeight) {
                    el.classList.add('visible');
                }
            });

            var observador = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (entrada) {
                    if (entrada.isIntersecting) {
                        entrada.target.classList.add('visible');
                        observador.unobserve(entrada.target);
                    }
                });
            }, { threshold: 0.12 });

            document.body.classList.add('animar');
            elementos.forEach(function (el) {
                if (!el.classList.contains('visible')) { observador.observe(el); }
            });
        }
    }

    // -----------------------------------------------------------------
    // 3. "Cargando" al enviar (solo formularios con data-cargando)
    // -----------------------------------------------------------------
    document.querySelectorAll('form[data-cargando]').forEach(function (formulario) {
        var enviar = formulario.querySelector('button[type="submit"]');
        if (!enviar) {
            return;
        }
        var texto = enviar.textContent;
        var enviando = false;

        formulario.addEventListener('submit', function (evento) {
            if (enviando) {           // segundo clic: se ignora
                evento.preventDefault();
                return;
            }
            enviando = true;
            enviar.setAttribute('aria-busy', 'true');
            if (enviar.dataset.textoCargando) {
                enviar.textContent = enviar.dataset.textoCargando;
            }
            // Se desactiva un instante después, para no estorbar el envío.
            window.setTimeout(function () { enviar.disabled = true; }, 0);
        });

        // Al volver con "Atrás", el botón vuelve a la normalidad.
        window.addEventListener('pageshow', function () {
            enviando = false;
            enviar.removeAttribute('aria-busy');
            enviar.disabled = false;
            enviar.textContent = texto;
        });
    });
}());
