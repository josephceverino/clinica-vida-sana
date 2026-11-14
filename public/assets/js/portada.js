// =====================================================================
// portada.js - Movimiento de la página de inicio (JavaScript puro).
//   1. Carrusel: avanza solo, con flechas, puntos, teclado y deslizando
//      el dedo en el celular.
//   2. Aviso emergente: sale una vez cada 7 días por visitante.
//   3. Aparecer al bajar: las secciones entran suavemente al verse.
//
// Si la persona activó "reducir movimiento" en su sistema, el carrusel
// no avanza solo y no hay animaciones (accesibilidad).
// =====================================================================

// ¿La persona prefiere menos movimiento?
const reducirMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---------------------------------------------------------------------
// 1. Carrusel
// ---------------------------------------------------------------------
function iniciarCarrusel(carrusel) {
    const pista = carrusel.querySelector('[data-pista]');
    const diapositivas = Array.from(pista.children);
    const puntos = Array.from(carrusel.querySelectorAll('[data-ir]'));
    const SEGUNDOS = 6;          // tiempo de cada diapositiva
    let actual = 0;
    let temporizador = null;

    // Con JavaScript activo, las diapositivas se ponen una al lado de otra.
    carrusel.classList.add('carrusel-activo');
    carrusel.querySelectorAll('[hidden]').forEach((el) => { el.hidden = false; });

    // Muestra la diapositiva número "n" (da la vuelta al llegar al final).
    function mostrar(n) {
        actual = (n + diapositivas.length) % diapositivas.length;
        pista.style.transform = 'translateX(-' + (actual * 100) + '%)';

        diapositivas.forEach((diapositiva, i) => {
            const visible = i === actual;
            // Las ocultas no se leen ni se alcanzan con Tab.
            diapositiva.setAttribute('aria-hidden', visible ? 'false' : 'true');
            diapositiva.querySelectorAll('a, button').forEach((enlace) => {
                enlace.tabIndex = visible ? 0 : -1;
            });
        });

        puntos.forEach((punto, i) => {
            punto.classList.toggle('activo', i === actual);
            punto.setAttribute('aria-current', i === actual ? 'true' : 'false');
        });
    }

    function siguiente() { mostrar(actual + 1); }
    function anterior() { mostrar(actual - 1); }

    // Avance automático (salvo "reducir movimiento").
    function arrancar() {
        if (reducirMovimiento || temporizador) return;
        temporizador = setInterval(siguiente, SEGUNDOS * 1000);
    }

    function detener() {
        clearInterval(temporizador);
        temporizador = null;
    }

    // Botones. Al tocarlos, se reinicia la cuenta para no saltar enseguida.
    carrusel.querySelector('[data-siguiente]').addEventListener('click', () => {
        siguiente(); detener(); arrancar();
    });
    carrusel.querySelector('[data-anterior]').addEventListener('click', () => {
        anterior(); detener(); arrancar();
    });
    puntos.forEach((punto) => {
        punto.addEventListener('click', () => {
            mostrar(Number(punto.dataset.ir)); detener(); arrancar();
        });
    });

    // Se pausa mientras el ratón o el foco del teclado están encima,
    // para que la persona pueda leer tranquila.
    carrusel.addEventListener('mouseenter', detener);
    carrusel.addEventListener('mouseleave', arrancar);
    carrusel.addEventListener('focusin', detener);
    carrusel.addEventListener('focusout', arrancar);

    // Flechas del teclado cuando el foco está dentro del carrusel.
    carrusel.addEventListener('keydown', (evento) => {
        if (evento.key === 'ArrowRight') siguiente();
        if (evento.key === 'ArrowLeft') anterior();
    });

    // Deslizar con el dedo (celular): más de 50 px cuenta como deslizar.
    let inicioX = null;
    carrusel.addEventListener('touchstart', (evento) => {
        inicioX = evento.touches[0].clientX;
        detener();
    }, { passive: true });
    carrusel.addEventListener('touchend', (evento) => {
        if (inicioX === null) return;
        const distancia = evento.changedTouches[0].clientX - inicioX;
        if (distancia < -50) siguiente();
        if (distancia > 50) anterior();
        inicioX = null;
        arrancar();
    });

    // Si la pestaña no se ve, no tiene sentido seguir moviéndolo.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { detener(); } else { arrancar(); }
    });

    mostrar(0);
    arrancar();
}

// ---------------------------------------------------------------------
// 2. Aviso emergente (una vez cada 7 días)
// ---------------------------------------------------------------------
function iniciarAviso(aviso) {
    const CLAVE = 'vidasana_aviso_visto';
    const DIAS = 7;

    // localStorage guarda cuándo lo vio por última vez. Puede fallar
    // (navegación privada, almacenamiento bloqueado): en ese caso se
    // muestra igual, sin romper la página.
    let vistoEn = 0;
    try {
        vistoEn = Number(localStorage.getItem(CLAVE)) || 0;
    } catch (error) {
        vistoEn = 0;
    }

    const yaLoVio = Date.now() - vistoEn < DIAS * 24 * 60 * 60 * 1000;
    if (yaLoVio || typeof aviso.showModal !== 'function') return;

    function cerrar() {
        aviso.close();
    }

    // Al cerrarse (X, "Ahora no", Esc o clic fuera) se anota la fecha.
    aviso.addEventListener('close', () => {
        try {
            localStorage.setItem(CLAVE, String(Date.now()));
        } catch (error) {
            // Sin almacenamiento: volverá a salir en la próxima visita.
        }
    });

    aviso.querySelectorAll('[data-cerrar]').forEach((boton) => {
        boton.addEventListener('click', cerrar);
    });

    // Clic en el fondo oscuro (fuera de la ventana) también cierra.
    aviso.addEventListener('click', (evento) => {
        if (evento.target === aviso) cerrar();
    });

    // Se espera un poco para no tapar la página apenas se abre.
    setTimeout(() => aviso.showModal(), 1500);
}

// ---------------------------------------------------------------------
// 3. Secciones que aparecen al bajar
// ---------------------------------------------------------------------
function iniciarAparecer() {
    const elementos = document.querySelectorAll('.aparecer');

    // Sin animaciones (o navegador antiguo): se muestran tal cual.
    if (reducirMovimiento || !('IntersectionObserver' in window)) {
        elementos.forEach((el) => el.classList.add('visible'));
        return;
    }

    // Avisa cuando un elemento entra en pantalla; entonces se le pone
    // la clase "visible" (el CSS hace la animación) y se deja de vigilar.
    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach((entrada) => {
            if (entrada.isIntersecting) {
                entrada.target.classList.add('visible');
                observador.unobserve(entrada.target);
            }
        });
    }, { threshold: 0.15 });

    document.body.classList.add('animar');
    elementos.forEach((el) => observador.observe(el));
}

// --- Arranque -----------------------------------------------------------
const carrusel = document.querySelector('[data-carrusel]');
if (carrusel) iniciarCarrusel(carrusel);

const aviso = document.querySelector('[data-aviso]');
if (aviso) iniciarAviso(aviso);

iniciarAparecer();
