// =====================================================================
// vita.js - Vita, el asistente guiado de la web pública.
// JavaScript puro, se carga con "defer". HTML: app/layout/vita_widget.php
//
//   - Abre y cierra el panel (botón, botón X y tecla Escape).
//   - Envía las preguntas al endpoint JSON (public/vita/responder.php).
//   - Muestra respuestas, enlaces de acción y preguntas sugeridas.
//   - Estados: cargando, error, sin respuesta y límite de mensajes.
//
// Seguridad: TODO texto que llega del servidor se pone con textContent
// (nunca innerHTML). Los enlaces solo se crean si la dirección es una ruta
// de este mismo sitio (la lista autorizada la decide el servidor).
// Privacidad: la conversación no se guarda en ningún lado (ni localStorage).
// =====================================================================
(function () {
    'use strict';

    var raiz = document.querySelector('[data-vita]');
    if (!raiz || !window.fetch || !window.JSON) {
        return;   // sin soporte: Vita queda oculto y la ayuda sigue en el pie
    }

    var endpoint = raiz.getAttribute('data-vita-endpoint');
    var pagina_ayuda = raiz.getAttribute('data-vita-ayuda');
    var lanzador = raiz.querySelector('[data-vita-lanzador]');
    var panel = raiz.querySelector('[data-vita-panel]');
    var cerrar = raiz.querySelector('[data-vita-cerrar]');
    var mensajes = raiz.querySelector('[data-vita-mensajes]');
    var sugerencias = raiz.querySelector('[data-vita-sugerencias]');
    var formulario = raiz.querySelector('[data-vita-formulario]');
    var entrada = raiz.querySelector('[data-vita-entrada]');
    var enviar = raiz.querySelector('[data-vita-enviar]');
    var estado = raiz.querySelector('[data-vita-estado]');
    var avatar_modelo = raiz.querySelector('.vita-mascota-cabecera');

    var LARGO_MAXIMO = 200;
    var ESPERA_MAXIMA_MS = 12000;

    var iniciado = false;      // ¿ya se mostró el saludo inicial?
    var ocupado = false;       // ¿hay una pregunta en camino?
    var bloqueado_hasta = 0;   // límite de mensajes: hora hasta la que se espera
    var temporizador_bloqueo = null;

    raiz.hidden = false;
    document.body.classList.add('vita-activa');

    // ¿Mouse o touchpad (escritorio)? Ahí, al abrir, el foco va directo al
    // campo de texto. En pantallas táctiles NO: el teclado virtual
    // aparecería sin que la persona lo pida; el foco va al panel y el
    // teclado sale solo cuando toca el campo.
    var puntero_fino = window.matchMedia('(hover: hover) and (pointer: fine)');

    function enfocar_al_abrir() {
        if (puntero_fino.matches && !entrada.disabled) {
            entrada.focus();
        } else {
            panel.focus();
        }
    }

    // -----------------------------------------------------------------
    // Zona visible (teclado virtual del celular)
    // Con el teclado abierto, la parte visible de la pantalla se achica.
    // Se le pasa al CSS cuánto mide lo visible y cuánto tapa el teclado,
    // para que la hoja de Vita quede encima del teclado y la cabecera, el
    // botón de cerrar y el campo de texto sigan a la vista.
    // -----------------------------------------------------------------
    var vista = window.visualViewport || null;
    var barra_inferior = document.querySelector('[data-vita-barra-inferior]');

    function ajustar_a_lo_visible() {
        if (panel.hidden) {
            return;
        }
        var alto_visible = vista ? vista.height : window.innerHeight;
        var arriba = vista ? vista.offsetTop : 0;
        var teclado = Math.max(0, Math.round(window.innerHeight - alto_visible - arriba));

        panel.style.setProperty('--vita-visible', Math.round(alto_visible) + 'px');
        panel.style.setProperty('--vita-teclado', teclado + 'px');

        // Barra de navegación fija abajo (portal del paciente en el celular):
        // la hoja de Vita se apoya encima para no taparla. Con el teclado
        // abierto la barra queda detrás del teclado y no se cuenta. En la
        // web pública no hay barra: vale 0.
        var alto_barra = 0;
        if (barra_inferior && teclado <= 80 && getComputedStyle(barra_inferior).position === 'fixed') {
            alto_barra = Math.round(barra_inferior.getBoundingClientRect().height);
        }
        panel.style.setProperty('--vita-barra', alto_barra + 'px');
        panel.classList.toggle('vita-teclado-abierto', teclado > 80);
        // Poco alto: se ocultan nota, sugerencias y pie (ver vita.css).
        panel.classList.toggle('vita-compacto', alto_visible < 560);
    }

    if (vista) {
        vista.addEventListener('resize', ajustar_a_lo_visible);
        vista.addEventListener('scroll', ajustar_a_lo_visible);
    }
    window.addEventListener('resize', ajustar_a_lo_visible);

    // -----------------------------------------------------------------
    // Abrir y cerrar
    // -----------------------------------------------------------------
    function abrir() {
        panel.hidden = false;
        lanzador.setAttribute('aria-expanded', 'true');
        ajustar_a_lo_visible();
        enfocar_al_abrir();
        if (!iniciado) {
            iniciado = true;
            mostrar_saludo_local();   // sin llamar al servidor
        }
    }

    function cerrar_panel() {
        panel.hidden = true;
        lanzador.setAttribute('aria-expanded', 'false');
        lanzador.focus();
    }

    lanzador.addEventListener('click', function () {
        if (panel.hidden) {
            abrir();
        } else {
            cerrar_panel();
        }
    });

    cerrar.addEventListener('click', cerrar_panel);

    panel.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            evento.stopPropagation();
            cerrar_panel();
        }
    });

    // -----------------------------------------------------------------
    // Dibujar mensajes (solo con createElement y textContent)
    // -----------------------------------------------------------------
    function crear(etiqueta, clase, texto) {
        var elemento = document.createElement(etiqueta);
        if (clase) {
            elemento.className = clase;
        }
        if (texto !== undefined && texto !== null) {
            elemento.textContent = String(texto);
        }
        return elemento;
    }

    function bajar_al_final() {
        mensajes.scrollTop = mensajes.scrollHeight;
    }

    function mensaje_usuario(texto) {
        var fila = crear('div', 'vita-msg vita-msg-usuario');
        var burbuja = crear('div', 'vita-burbuja');
        burbuja.appendChild(crear('span', 'solo-lectores', 'Tú: '));
        burbuja.appendChild(crear('p', null, texto));
        fila.appendChild(burbuja);
        mensajes.appendChild(fila);
        bajar_al_final();
    }

    // Burbuja de Vita. Devuelve la burbuja para poder agregarle cosas.
    function mensaje_vita(clase_extra) {
        var fila = crear('div', 'vita-msg vita-msg-bot');
        var avatar = crear('span', 'vita-msg-avatar');
        if (avatar_modelo) {
            avatar.appendChild(avatar_modelo.cloneNode(true));
        }
        var burbuja = crear('div', 'vita-burbuja' + (clase_extra ? ' ' + clase_extra : ''));
        burbuja.appendChild(crear('span', 'solo-lectores', 'Vita: '));
        fila.appendChild(avatar);
        fila.appendChild(burbuja);
        mensajes.appendChild(fila);
        return burbuja;
    }

    // ¿La dirección es una ruta de ESTE sitio? (nada de "javascript:",
    // "//otro-sitio" ni direcciones completas a otros dominios)
    function ruta_segura(direccion) {
        if (typeof direccion !== 'string' || direccion.charAt(0) !== '/' || direccion.charAt(1) === '/') {
            return null;
        }
        try {
            var destino = new URL(direccion, window.location.origin);
            return destino.origin === window.location.origin ? destino.pathname : null;
        } catch (error) {
            return null;
        }
    }

    function agregar_accion(burbuja, accion) {
        if (!accion || typeof accion.texto !== 'string') {
            return;
        }
        var ruta = ruta_segura(accion.url);
        if (ruta === null) {
            return;
        }
        var enlace = crear('a', 'vita-accion', accion.texto);
        enlace.href = ruta;
        burbuja.appendChild(enlace);
    }

    // Respuesta correcta del servidor ({ok: true, tipo, respuesta, ...})
    function mostrar_respuesta(datos) {
        var clase = '';
        if (datos.tipo === 'emergencia') {
            clase = 'vita-burbuja-urgente';
        } else if (datos.tipo === 'consulta_medica' || datos.tipo === 'datos_personales') {
            clase = 'vita-burbuja-aviso';
        }

        var burbuja = mensaje_vita(clase);
        if (typeof datos.pregunta === 'string' && datos.pregunta !== '') {
            burbuja.appendChild(crear('p', 'vita-respuesta-titulo', datos.pregunta));
        }
        burbuja.appendChild(crear('p', null, datos.respuesta));
        if (typeof datos.aviso === 'string' && datos.aviso !== '') {
            burbuja.appendChild(crear('p', 'vita-respuesta-aviso', datos.aviso));
        }
        agregar_accion(burbuja, datos.accion);
        bajar_al_final();

        mostrar_sugerencias(Array.isArray(datos.sugerencias) ? datos.sugerencias : []);
    }

    // Saludo inicial: sale del HTML de la página (vita_widget.php), no del
    // servidor. Así abrir Vita no gasta el límite de mensajes y funciona
    // aunque la base de datos no esté disponible.
    function mostrar_saludo_local() {
        var saludo = raiz.querySelector('[data-vita-saludo]');
        var burbuja = mensaje_vita('');
        burbuja.appendChild(crear('p', null, saludo ? saludo.textContent.trim() : '¡Hola! Soy Vita.'));
        bajar_al_final();

        var iniciales = Array.prototype.map.call(
            raiz.querySelectorAll('[data-vita-iniciales] li'),
            function (item) {
                return { clave: item.getAttribute('data-clave'), pregunta: item.textContent.trim() };
            }
        );
        mostrar_sugerencias(iniciales);
    }

    // Error (sin conexión, base caída, límite...): botón "Reintentar" (si
    // tiene sentido) y siempre un enlace visible a la página de Ayuda.
    function mostrar_error(texto, reintento) {
        var burbuja = mensaje_vita('vita-burbuja-aviso');
        burbuja.appendChild(crear('p', null, texto));

        var acciones = crear('div', 'vita-error-acciones');
        if (reintento) {
            var boton = crear('button', 'vita-reintentar', 'Reintentar');
            boton.type = 'button';
            boton.addEventListener('click', function () {
                acciones.remove();
                reintento();
            });
            acciones.appendChild(boton);
        }
        var ruta_ayuda = ruta_segura(pagina_ayuda);
        if (ruta_ayuda !== null) {
            var enlace = crear('a', 'vita-enlace-ayuda', 'Ver preguntas frecuentes');
            enlace.href = ruta_ayuda;
            acciones.appendChild(enlace);
        }
        if (acciones.children.length > 0) {
            burbuja.appendChild(acciones);
        }
        bajar_al_final();
    }

    function mostrar_sugerencias(lista) {
        while (sugerencias.firstChild) {
            sugerencias.removeChild(sugerencias.firstChild);
        }
        lista.slice(0, 3).forEach(function (item) {
            if (!item || typeof item.clave !== 'string' || typeof item.pregunta !== 'string') {
                return;
            }
            var boton = crear('button', 'vita-sugerencia', item.pregunta);
            boton.type = 'button';
            boton.disabled = ocupado || esta_bloqueado();
            boton.addEventListener('click', function () {
                preguntar({ clave: item.clave }, item.pregunta);
            });
            sugerencias.appendChild(boton);
        });
        sugerencias.hidden = sugerencias.children.length === 0;
    }

    // "Vita está escribiendo…"
    var escribiendo = null;

    function mostrar_escribiendo() {
        escribiendo = crear('div', 'vita-msg vita-msg-bot');
        var burbuja = crear('div', 'vita-burbuja vita-escribiendo');
        burbuja.appendChild(crear('span', 'solo-lectores', 'Vita está buscando una respuesta…'));
        for (var i = 0; i < 3; i++) {
            var punto = crear('span');
            punto.setAttribute('aria-hidden', 'true');
            burbuja.appendChild(punto);
        }
        escribiendo.appendChild(burbuja);
        mensajes.appendChild(escribiendo);
        bajar_al_final();
    }

    function quitar_escribiendo() {
        if (escribiendo) {
            escribiendo.remove();
            escribiendo = null;
        }
    }

    // -----------------------------------------------------------------
    // Estado del formulario
    // -----------------------------------------------------------------
    function esta_bloqueado() {
        return Date.now() < bloqueado_hasta;
    }

    function actualizar_controles() {
        var apagado = ocupado || esta_bloqueado();
        enviar.disabled = apagado;
        entrada.disabled = esta_bloqueado();
        Array.prototype.forEach.call(sugerencias.querySelectorAll('button'), function (boton) {
            boton.disabled = apagado;
        });
        mensajes.setAttribute('aria-busy', ocupado ? 'true' : 'false');
    }

    function avisar(texto) {
        estado.textContent = texto;
    }

    // Límite de mensajes: se espera los segundos que pida el servidor.
    function bloquear(segundos) {
        bloqueado_hasta = Date.now() + segundos * 1000;
        actualizar_controles();
        avisar('Podrás escribir otra vez en ' + segundos + ' segundos.');
        window.clearTimeout(temporizador_bloqueo);
        temporizador_bloqueo = window.setTimeout(function () {
            bloqueado_hasta = 0;
            actualizar_controles();
            avisar('Ya puedes escribir otra vez.');
            // Solo con mouse: en el celular no se abre el teclado sin pedirlo.
            if (!panel.hidden && puntero_fino.matches) {
                entrada.focus();
            }
        }, segundos * 1000);
    }

    // -----------------------------------------------------------------
    // Hablar con el servidor
    // -----------------------------------------------------------------
    // datos: {mensaje: '...'} o {clave: '...'}. texto_visible: lo que se
    // muestra como mensaje de la persona (null para el saludo inicial).
    function preguntar(datos, texto_visible) {
        if (ocupado || esta_bloqueado()) {
            return;
        }

        if (texto_visible) {
            mensaje_usuario(texto_visible);
        }
        avisar('');
        ocupado = true;
        actualizar_controles();
        mostrar_escribiendo();

        var control = typeof AbortController === 'function' ? new AbortController() : null;
        var reloj = window.setTimeout(function () {
            if (control) {
                control.abort();
            }
        }, ESPERA_MAXIMA_MS);

        var cuerpo = { idioma: 'es' };
        if (datos.clave) {
            cuerpo.clave = datos.clave;
        } else {
            cuerpo.mensaje = datos.mensaje;
        }

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(cuerpo),
            credentials: 'same-origin',
            signal: control ? control.signal : undefined,
        }).then(function (respuesta) {
            var espera = parseInt(respuesta.headers.get('Retry-After') || '60', 10);
            return respuesta.json().then(function (json) {
                return { codigo: respuesta.status, json: json, espera: espera };
            }, function () {
                return { codigo: respuesta.status, json: null, espera: espera };
            });
        }).then(function (resultado) {
            terminar();
            var json = resultado.json;

            if (resultado.codigo === 200 && json && json.ok === true && typeof json.respuesta === 'string') {
                mostrar_respuesta(json);
                return;
            }

            if (resultado.codigo === 429) {
                mostrar_error('Escribiste muchos mensajes seguidos. Espera un momento y vuelve a intentarlo.');
                bloquear(Math.min(Math.max(resultado.espera || 60, 5), 120));
                return;
            }

            // Errores de validación (400): el servidor explica qué pasó.
            if (resultado.codigo === 400 && json && json.error && typeof json.error.mensaje === 'string') {
                mostrar_error(json.error.mensaje);
                return;
            }

            // 503, 500 u otra cosa: no disponible por ahora.
            mostrar_error('Vita no está disponible en este momento. Puedes ver las preguntas '
                + 'frecuentes en la página de Ayuda o volver a intentarlo.', function () {
                preguntar(datos, null);
            });
        }).catch(function () {
            terminar();
            mostrar_error('No hay conexión con Vita. Revisa tu conexión e inténtalo otra vez.', function () {
                preguntar(datos, null);
            });
        });

        function terminar() {
            window.clearTimeout(reloj);
            quitar_escribiendo();
            ocupado = false;
            actualizar_controles();
        }
    }

    formulario.addEventListener('submit', function (evento) {
        evento.preventDefault();
        var texto = entrada.value.replace(/\s+/g, ' ').trim();

        if (texto === '') {
            avisar('Escribe tu pregunta antes de enviar.');
            entrada.focus();
            return;
        }
        if (texto.length > LARGO_MAXIMO) {
            avisar('Tu pregunta es muy larga: usa ' + LARGO_MAXIMO + ' letras o menos.');
            return;
        }
        if (ocupado || esta_bloqueado()) {
            return;
        }

        entrada.value = '';
        preguntar({ mensaje: texto }, texto);
    });
}());
