<?php
// Vita: endpoint JSON del asistente guiado.
//
// Recibe:   POST con JSON  {"mensaje": "¿Cómo pido una cita?", "idioma": "es"}
//           o el botón de una sugerencia  {"clave": "cancelar-cita"}
// Responde: JSON, siempre con la misma forma (ver vita_resultado() en
//           app/modelos/vita.php), o un error {"ok": false, "error": {...}}.
//
// Seguridad (excepción a la regla 4, aprobada por el equipo):
// este endpoint NO lleva token CSRF porque es de SOLO LECTURA: no inicia
// sesiones, no lee la sesión de nadie, no escribe en la base y no guarda
// el mensaje. A cambio exige:
//   - método POST y cuerpo JSON (Content-Type: application/json),
//   - cabecera Origin igual a este mismo sitio (y Sec-Fetch-Site, si
//     el navegador la envía, debe ser "same-origin"),
//   - ninguna cabecera CORS: otros sitios no pueden leer la respuesta,
//   - mensaje de 1 a 200 letras y cuerpo de 2 KB como máximo,
//   - límite de mensajes por minuto (por IP y en total).
require_once __DIR__ . '/../../app/modelos/vita.php';

// Tamaño máximo del cuerpo de la petición (en bytes).
const VITA_CUERPO_MAXIMO = 2048;

// Nunca mostrar errores de PHP dentro del JSON (ni en desarrollo): los
// detalles van solo al log del servidor (regla 9).
ini_set('display_errors', '0');

/**
 * Envía la respuesta JSON y termina.
 * JSON_HEX_TAG/AMP: "<", ">" y "&" salen escapados, por si acaso.
 */
function vita_enviar(int $codigo, array $datos): never
{
    http_response_code($codigo);
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
    );
    exit;
}

/**
 * Envía un error con mensaje genérico (sin detalles internos).
 */
function vita_error(int $codigo, string $error, string $mensaje): never
{
    vita_enviar($codigo, ['ok' => false, 'error' => ['codigo' => $error, 'mensaje' => $mensaje]]);
}

// Cualquier fallo inesperado: error JSON genérico, nunca una página HTML.
set_exception_handler(function (Throwable $error): void {
    error_log('Vita: error inesperado: ' . $error->getMessage());
    vita_error(500, 'error_interno', 'Vita no pudo responder. Inténtalo más tarde.');
});

// --- 1. Solo POST ---------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    vita_error(405, 'metodo_no_permitido', 'Usa el método POST.');
}

// --- 2. Solo desde este mismo sitio ----------------------------------------
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$origen_esperado = ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
$sitio = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';

if ($origen === '' || !hash_equals($origen_esperado, $origen)
    || ($sitio !== '' && $sitio !== 'same-origin')) {
    vita_error(403, 'origen_no_permitido', 'Esta petición no está permitida.');
}

// --- 3. Límite de mensajes por minuto ---------------------------------------
// Se cuenta antes de leer el contenido: también frena pedidos mal formados.
// Si el contador falla, NO se atiende sin control: se responde 503.
$estado_limite = vita_estado_limite(substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45));

if ($estado_limite === VITA_LIMITE_ALCANZADO) {
    header('Retry-After: 60');
    vita_error(429, 'demasiadas_solicitudes', 'Escribiste muchos mensajes seguidos. Espera un minuto.');
}

if ($estado_limite !== VITA_LIMITE_PERMITIDO) {
    header('Retry-After: 60');
    vita_error(503, 'servicio_no_disponible', 'Vita no está disponible en este momento. Inténtalo en unos minutos.');
}

// --- 4. Formato: JSON y tamaño ---------------------------------------------
$tipo = $_SERVER['CONTENT_TYPE'] ?? '';
if (preg_match('#^application/json\s*(;.*)?$#i', $tipo) !== 1) {
    vita_error(415, 'formato_no_permitido', 'Envía el mensaje en formato JSON.');
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > VITA_CUERPO_MAXIMO) {
    vita_error(413, 'peticion_muy_grande', 'El mensaje es demasiado largo.');
}

// Se lee como máximo un byte más del límite, para saber si se pasó.
$cuerpo = file_get_contents('php://input', false, null, 0, VITA_CUERPO_MAXIMO + 1);
if ($cuerpo === false || strlen($cuerpo) > VITA_CUERPO_MAXIMO) {
    vita_error(413, 'peticion_muy_grande', 'El mensaje es demasiado largo.');
}

try {
    $datos = json_decode($cuerpo, true, 4, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    vita_error(400, 'json_invalido', 'No se pudo leer el mensaje.');
}

// Debe ser un objeto JSON ({...}), no una lista ni un texto suelto.
// (Se mira el primer carácter porque PHP convierte {} y [] en lo mismo.)
if (!is_array($datos) || !str_starts_with(ltrim($cuerpo), '{')) {
    vita_error(400, 'json_invalido', 'No se pudo leer el mensaje.');
}

// --- 5. Validar los campos ---------------------------------------------------
$idioma = $datos['idioma'] ?? 'es';
if (!is_string($idioma) || !isset(vita_idiomas()[$idioma])) {
    vita_error(400, 'idioma_no_valido', 'Idioma no disponible.');
}

$tiene_mensaje = array_key_exists('mensaje', $datos);
$tiene_clave = array_key_exists('clave', $datos);

if ($tiene_mensaje === $tiene_clave) {
    // Ninguno de los dos, o los dos a la vez.
    vita_error(400, 'peticion_no_valida', 'Envía un mensaje.');
}

if ($tiene_clave) {
    $clave = $datos['clave'];
    // Las claves de las preguntas son minúsculas, números y guiones.
    if (!is_string($clave) || preg_match('/^[a-z0-9-]{1,60}$/', $clave) !== 1) {
        vita_error(400, 'peticion_no_valida', 'Pregunta no válida.');
    }
    $entrada = ['clave' => $clave];
} else {
    $mensaje = $datos['mensaje'];
    if (!is_string($mensaje)) {
        vita_error(400, 'peticion_no_valida', 'Envía un mensaje.');
    }

    // Quita caracteres de control y espacios de más.
    $mensaje = trim(preg_replace('/[\p{Cc}\s]+/u', ' ', $mensaje) ?? '');

    if ($mensaje === '') {
        vita_error(400, 'mensaje_vacio', 'Escribe tu pregunta.');
    }
    if (mb_strlen($mensaje, 'UTF-8') > VITA_LONGITUD_MAXIMA) {
        vita_error(400, 'mensaje_muy_largo',
            'Tu mensaje es muy largo. Escribe tu pregunta en ' . VITA_LONGITUD_MAXIMA . ' letras o menos.');
    }
    $entrada = ['mensaje' => $mensaje];
}

// --- 6. Responder (solo lectura) --------------------------------------------
try {
    $respuesta = vita_responder(vita_conexion(), $entrada, $idioma);
} catch (PDOException $error) {
    error_log('Vita: base de datos no disponible: ' . $error->getMessage());
    vita_error(503, 'servicio_no_disponible', 'Vita no está disponible en este momento. Inténtalo en unos minutos.');
}

vita_enviar(200, $respuesta);
