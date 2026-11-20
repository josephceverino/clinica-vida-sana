// =====================================================================
// publico.js - Web pública (JavaScript puro, se carga con "defer").
//   1. Menú del celular: el botón "Menú" lo abre y lo cierra.
//   2. Aparecer al bajar: las secciones .aparecer entran suavemente.
//      (En Inicio lo hace portada.js, que se carga antes: aquí no se repite.)
//   3. Formularios con data-cargando: el botón muestra "Ingresando…" o
//      "Creando cuenta…" mientras el servidor responde.
//   4. Botón de WhatsApp de demostración.
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

    document.querySelectorAll('.pub-notificacion-cerrar').forEach(function (cerrar) {
        var aviso = cerrar.closest('.pub-notificacion');
        cerrar.hidden = false;
        cerrar.addEventListener('click', function () {
            var campo = document.querySelector('main .pub-campo-flotante input');
            if (document.activeElement === cerrar && campo) {
                campo.focus({ preventScroll: true });
            }
            aviso.inert = true;
            aviso.classList.add('pub-notificacion-cerrando');
            var animaciones = aviso.getAnimations();
            Promise.allSettled(animaciones.map(function (animacion) {
                return animacion.finished;
            })).then(function () {
                aviso.remove();
            });
        });
    });

    var whatsapp = document.querySelector('.pub-whatsapp');
    var dialogo_whatsapp = document.getElementById('pub-whatsapp-demo');
    if (whatsapp && dialogo_whatsapp && typeof dialogo_whatsapp.showModal === 'function') {
        whatsapp.hidden = false;
        whatsapp.addEventListener('click', function () { dialogo_whatsapp.showModal(); });
        dialogo_whatsapp.querySelector('[data-whatsapp-cerrar]').addEventListener('click', function () {
            dialogo_whatsapp.close();
        });
    }

    if (whatsapp && !whatsapp.hidden) {
        // Deja libres los controles cuando pasan por debajo del botón flotante.
        var controles_publicos = document.querySelectorAll(
            'main a[href], main button, main input:not([type="hidden"]), main select, main textarea, main iframe, ' +
            '.pub-pie a, [data-whatsapp-proteger], .pub-lista-datos strong, .pub-horario strong'
        );
        var despeje = 0;
        var cuadro_pendiente = false;
        var formulario_publico = document.querySelector('main .pub-formulario');
        var ancho_movil = window.matchMedia('(max-width: 640px)');
        var ventana_visual = window.visualViewport;
        var selector_campo = 'input:not([type="hidden"]), select, textarea';

        function campo_enfocado() {
            return formulario_publico.contains(document.activeElement)
                && document.activeElement.matches(selector_campo);
        }

        function despejar_controles() {
            cuadro_pendiente = false;
            var boton_rect = whatsapp.getBoundingClientRect();
            var base = whatsapp.offsetTop + despeje;
            var destino = base;
            var altura = boton_rect.height;
            var controles = Array.from(controles_publicos, function (control) {
                return control.getBoundingClientRect();
            }).filter(function (rect) {
                return rect.height > 0 && rect.right > boton_rect.left - 6 && rect.left < boton_rect.right + 6;
            }).sort(function (a, b) { return b.bottom - a.bottom; });

            controles.forEach(function (rect) {
                if (rect.bottom + 6 > destino && rect.top - 6 < destino + altura) {
                    destino = rect.top - altura - 6;
                }
            });

            despeje = Math.max(0, Math.floor(base - Math.max(100, destino)));
            whatsapp.style.setProperty('--pub-wa-despeje', despeje + 'px');

            if (formulario_publico) {
                var movil = ancho_movil.matches;
                var arriba = base - despeje;
                var abajo = arriba + altura;
                var visible_arriba = ventana_visual ? ventana_visual.offsetTop : 0;
                var visible_abajo = visible_arriba + (ventana_visual ? ventana_visual.height : window.innerHeight);
                var interfiere = controles.some(function (rect) {
                    return rect.bottom > visible_arriba && rect.top < visible_abajo
                        && rect.bottom > arriba && rect.top < abajo;
                });

                // El límite del desplazamiento puede dejar el botón encima de un campo.
                whatsapp.toggleAttribute('inert', movil && (
                    campo_enfocado() || interfiere || arriba < visible_arriba || abajo > visible_abajo
                ));
            }
        }

        function programar_despeje() {
            if (!cuadro_pendiente) {
                cuadro_pendiente = true;
                window.requestAnimationFrame(despejar_controles);
            }
        }

        window.addEventListener('scroll', programar_despeje, { passive: true });
        window.addEventListener('resize', programar_despeje);
        window.addEventListener('load', programar_despeje);
        if (formulario_publico) {
            formulario_publico.addEventListener('focusin', function () {
                if (ancho_movil.matches && campo_enfocado()) {
                    whatsapp.setAttribute('inert', '');
                }
                programar_despeje();
            });
            formulario_publico.addEventListener('focusout', programar_despeje);
            if (ventana_visual) {
                ventana_visual.addEventListener('resize', programar_despeje);
                ventana_visual.addEventListener('scroll', programar_despeje);
            }
        }
        programar_despeje();
    }
}());
