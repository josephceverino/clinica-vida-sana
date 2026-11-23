<?php
// Vita: base de conocimientos (preguntas frecuentes) y su buscador.
//
// Etapa 1: lee las preguntas publicadas para la página de ayuda.
// Etapa 2: busca la pregunta que mejor responde a lo que escribe la persona
//          (lo usa public/vita/responder.php).
//
// Vita SOLO LEE la base de datos: no guarda mensajes ni datos de nadie.
// No usa inteligencia artificial: compara palabras con reglas fijas.
// Las tablas se crean con sql/migracion-2026-11-vita.sql.
require_once __DIR__ . '/../core/db.php';

// Largo máximo de un mensaje (en letras).
const VITA_LONGITUD_MAXIMA = 200;

// Puntaje mínimo para dar una respuesta. Una palabra clave que solo tiene
// una pregunta ya vale 3 (ver vita_buscar()).
const VITA_PUNTAJE_MINIMO = 3;

// Ventaja mínima sobre la segunda mejor pregunta. Si dos preguntas quedan
// casi empatadas, la consulta es ambigua y se ofrecen sugerencias.
const VITA_VENTAJA_MINIMA = 1.5;

// Control de solicitudes excesivas (ver vita_limite_superado()).
const VITA_LIMITE_POR_IP_MINUTO = 20;   // de una misma IP
const VITA_LIMITE_GLOBAL_MINUTO = 600;  // de todas las IP juntas

// Preguntas que se sugieren cuando Vita no entiende nada.
const VITA_SUGERENCIAS_INICIALES = ['como-pedir-cita', 'horarios-atencion', 'ubicacion-contacto'];

/**
 * Idiomas que conoce Vita: código => nombre.
 * 'quy' = quechua ayacuchano/chanka (ISO 639-3). Sus textos solo se
 * muestran cuando un hablante los revisó y quedaron "publicado".
 */
function vita_idiomas(): array
{
    return [
        'es'  => 'Español',
        'quy' => 'Quechua ayacuchano',
    ];
}

/**
 * Categorías, en el mismo orden que el ENUM de vita_preguntas.
 * id => título que se ve en la página.
 */
function vita_categorias(): array
{
    return [
        'citas'     => 'Citas',
        'cuenta'    => 'Tu cuenta',
        'historia'  => 'Historia clínica y privacidad',
        'servicios' => 'Servicios',
        'contacto'  => 'Horarios, ubicación y contacto',
    ];
}

/**
 * Acciones autorizadas: la base solo guarda la CLAVE (ej. 'pedir_cita') y
 * aquí se traduce a una página real de la web. Si una clave no está en
 * esta lista, no se dibuja ningún botón. Nunca se usa una URL que venga
 * de la base de datos ni de quien escribe.
 *
 * Las páginas del portal ya se protegen solas: sin sesión llevan al login
 * y con la cuenta pendiente llevan al inicio del portal.
 */
function vita_acciones(): array
{
    return [
        'pedir_cita' => ['texto' => 'Pedir cita',          'ruta' => '/paciente/pedir-cita.php'],
        'mis_citas'  => ['texto' => 'Ver mis citas',       'ruta' => '/paciente/citas.php'],
        'historia'   => ['texto' => 'Ver historia clínica', 'ruta' => '/paciente/historia.php'],
        'perfil'     => ['texto' => 'Ir a mi perfil',      'ruta' => '/paciente/perfil.php'],
        'registro'   => ['texto' => 'Crear mi cuenta',     'ruta' => '/registro.php'],
        'login'      => ['texto' => 'Ir al portal',        'ruta' => '/login.php'],
        'servicios'  => ['texto' => 'Ver servicios',       'ruta' => '/servicios.php'],
        'contacto'   => ['texto' => 'Ver contacto',        'ruta' => '/contacto.php'],
        'privacidad' => ['texto' => 'Leer la política de privacidad', 'ruta' => '/privacidad.php'],
    ];
}

/**
 * Devuelve la acción autorizada de una clave, o null si no existe.
 */
function vita_accion(?string $clave): ?array
{
    if ($clave === null) {
        return null;
    }

    return vita_acciones()[$clave] ?? null;
}

// =====================================================================
// 1. Lectura de las preguntas publicadas
// =====================================================================

/**
 * Lista de preguntas activas con su texto PUBLICADO, en orden de página.
 * Cada fila: clave, categoria, accion, pregunta, respuesta, idioma,
 * traducida, y además el texto en español para el buscador
 * (pregunta_es, palabras_clave).
 *
 * Con $idioma = 'quy' usa el texto en quechua SOLO si está publicado; si
 * no, deja el de español con 'traducida' => false. Así nunca aparece una
 * traducción sin revisar.
 *
 * Si algo falla, LANZA PDOException: quien llama decide qué mostrar.
 */
function vita_leer_publicadas(PDO $pdo, string $idioma = 'es'): array
{
    if (!isset(vita_idiomas()[$idioma])) {
        $idioma = 'es';
    }

    // Siempre se parte del español publicado: es la versión de respaldo.
    // ORDER BY categoria sigue el orden del ENUM (el de vita_categorias()).
    $consulta = $pdo->prepare(
        "SELECT p.id, p.clave, p.categoria, p.accion,
                t.pregunta, t.respuesta, t.palabras_clave
         FROM vita_preguntas p
         JOIN vita_textos t ON t.pregunta_id = p.id
         WHERE p.activa = 1 AND t.idioma = 'es' AND t.estado = 'publicado'
         ORDER BY p.categoria, p.orden, p.id"
    );
    $consulta->execute();
    $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);

    // Traducciones publicadas del idioma pedido (si no es español).
    $traducciones = [];
    if ($idioma !== 'es') {
        $consulta = $pdo->prepare(
            "SELECT pregunta_id, pregunta, respuesta
             FROM vita_textos
             WHERE idioma = :idioma AND estado = 'publicado'"
        );
        $consulta->execute(['idioma' => $idioma]);
        foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $traducciones[(int) $fila['pregunta_id']] = $fila;
        }
    }

    $categorias = vita_categorias();
    $resultado = [];

    foreach ($filas as $fila) {
        // Una categoría desconocida (agregada a la base y no aquí) no se usa.
        if (!isset($categorias[$fila['categoria']])) {
            continue;
        }

        $traduccion = $traducciones[(int) $fila['id']] ?? null;

        $resultado[] = [
            'clave'          => $fila['clave'],
            'categoria'      => $fila['categoria'],
            'accion'         => $fila['accion'],
            'pregunta'       => $traduccion['pregunta'] ?? $fila['pregunta'],
            'respuesta'      => $traduccion['respuesta'] ?? $fila['respuesta'],
            'idioma'         => $traduccion !== null ? $idioma : 'es',
            'traducida'      => $idioma === 'es' || $traduccion !== null,
            'pregunta_es'    => $fila['pregunta'],
            'palabras_clave' => $fila['palabras_clave'],
        ];
    }

    return $resultado;
}

/**
 * Preguntas publicadas agrupadas por categoría (para public/ayuda.php):
 *   ['citas' => [ ['clave' => ..., 'pregunta' => ..., ...], ... ], ...]
 *
 * Si las tablas aún no existen (falta la migración) o la consulta falla,
 * devuelve una lista vacía: la página muestra "pronto" en vez de romperse.
 */
function vita_preguntas_publicadas(string $idioma = 'es'): array
{
    try {
        $filas = vita_leer_publicadas(db(), $idioma);
    } catch (PDOException $error) {
        error_log('Vita: no se pudieron leer las preguntas: ' . $error->getMessage());
        return [];
    }

    $resultado = [];
    foreach ($filas as $fila) {
        $resultado[$fila['categoria']][] = $fila;
    }

    return $resultado;
}

/**
 * Conexión propia de Vita para el endpoint JSON.
 * No usa db() porque db(), si falla, muestra una página HTML de error; el
 * endpoint necesita responder JSON. Esta función LANZA PDOException y el
 * endpoint la convierte en un error JSON controlado. Se conecta de nuevo
 * en cada petición: cuando la base vuelve, Vita vuelve sola.
 */
function vita_conexion(): PDO
{
    $datos = config('db');

    $dsn = 'mysql:host=' . $datos['host']
         . ';dbname=' . $datos['nombre']
         . ';charset=' . $datos['charset'];

    return new PDO($dsn, $datos['usuario'], $datos['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 3,   // segundos para conectar
    ]);
}

// =====================================================================
// 2. Preparar el texto
// =====================================================================

/**
 * Normaliza un texto para comparar: minúsculas, sin tildes ni signos y
 * con un solo espacio entre palabras.
 *   "¿Cómo   PIDO una Cita?"  ->  "como pido una cita"
 * Se hace con una tabla propia (sin la extensión intl), igual que
 * fecha_larga(), para no depender de cómo esté instalado PHP.
 */
function vita_normalizar(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ñ' => 'n',
    ]);
    // Todo lo que no sea letra o número pasa a ser un espacio.
    $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto);

    return trim($texto);
}

/**
 * Palabras que no ayudan a distinguir una pregunta de otra.
 */
function vita_palabras_vacias(): array
{
    static $lista = null;

    if ($lista === null) {
        $lista = array_flip([
            'a', 'al', 'algo', 'como', 'con', 'de', 'del', 'el', 'en', 'es', 'esta',
            'estan', 'favor', 'hay', 'la', 'las', 'le', 'lo', 'los', 'me', 'mi', 'mis',
            'necesito', 'o', 'para', 'pero', 'por', 'puedo', 'que', 'quiero', 'se',
            'si', 'su', 'sus', 'te', 'tengo', 'tu', 'tus', 'un', 'una', 'unos', 'unas',
            'y', 'ya', 'yo', 'hacer', 'hago', 'saber', 'quisiera', 'ustedes', 'usted',
            'vita', 'clinica', 'vida', 'sana',
        ]);
    }

    return $lista;
}

/**
 * Raíz muy simple: quita la "s" final del plural ("citas" -> "cita").
 * Se aplica igual al mensaje y a la base, así que basta con ser constante.
 */
function vita_raiz(string $palabra): string
{
    if (strlen($palabra) > 3 && str_ends_with($palabra, 's')) {
        return substr($palabra, 0, -1);
    }

    return $palabra;
}

/**
 * Palabras útiles de un texto YA normalizado (sin repetir).
 */
function vita_palabras(string $normalizado): array
{
    $vacias = vita_palabras_vacias();
    $palabras = [];

    foreach (explode(' ', $normalizado) as $palabra) {
        if ($palabra === '' || isset($vacias[$palabra])) {
            continue;
        }
        $palabras[vita_raiz($palabra)] = true;
    }

    return array_keys($palabras);
}

// Palabras que deben acompañar a "creo" para que cuente como "crear".
const VITA_CREO_ACOMPANANTES = ['cuenta', 'cuentas', 'usuario', 'registro'];

/**
 * Palabras útiles del MENSAJE de la persona (no de la base).
 *
 * Igual que vita_palabras(), con una sola regla extra para "creo":
 *   - "¿Cómo creo mi cuenta?" -> "creo" = crear: SÍ cuenta.
 *   - "creo que olvidé...", "creo q...", "creo en la clínica" ->
 *     "creo" = pensar o creer: NO cuenta.
 * "creo" solo cuenta si NO le sigue "que"/"q" y si en las 3 palabras
 * siguientes aparece una de VITA_CREO_ACOMPANANTES. Sola nunca basta.
 * Se aplica solo al mensaje: las palabras clave de la base no cambian.
 */
function vita_palabras_mensaje(string $normalizado): array
{
    $lista = explode(' ', $normalizado);
    $creo_vale = false;

    foreach ($lista as $posicion => $palabra) {
        if ($palabra !== 'creo') {
            continue;
        }
        $siguiente = $lista[$posicion + 1] ?? '';
        $cerca = array_slice($lista, $posicion + 1, 3);
        if ($siguiente !== 'que' && $siguiente !== 'q'
            && array_intersect($cerca, VITA_CREO_ACOMPANANTES) !== []) {
            $creo_vale = true;
        }
    }

    $palabras = vita_palabras($normalizado);
    if (!$creo_vale) {
        $palabras = array_values(array_diff($palabras, ['creo']));
    }

    return $palabras;
}

/**
 * ¿Dos palabras son "casi iguales"? Tolera errores simples de escritura:
 *   - 1 letra distinta en palabras de 5 a 7 letras ("cancelr" ~ "cancelar"),
 *   - 2 letras en palabras de 8 o más,
 *   - o empiezan igual en al menos 6 letras ("verificar" ~ "verificacion").
 */
function vita_parecidas(string $a, string $b): bool
{
    $corta = min(strlen($a), strlen($b));
    if ($corta < 5) {
        return false;   // en palabras cortas un error cambia el sentido
    }

    $tolerancia = $corta >= 8 ? 2 : 1;
    if (levenshtein($a, $b) <= $tolerancia) {
        return true;
    }

    $prefijo = 0;
    while ($prefijo < $corta && $a[$prefijo] === $b[$prefijo]) {
        $prefijo++;
    }

    return $prefijo >= 6;
}

/**
 * ¿El texto normalizado contiene alguna de estas palabras o frases?
 * Una entrada que termina en "*" compara solo el comienzo de la palabra
 * ("sangr*" encuentra "sangrado" y "sangrando").
 */
function vita_contiene(string $normalizado, array $lista): bool
{
    $con_espacios = ' ' . $normalizado . ' ';
    $palabras = explode(' ', $normalizado);

    foreach ($lista as $entrada) {
        if (str_ends_with($entrada, '*')) {
            $inicio = substr($entrada, 0, -1);
            foreach ($palabras as $palabra) {
                if (str_starts_with($palabra, $inicio)) {
                    return true;
                }
            }
        } elseif (str_contains($con_espacios, ' ' . $entrada . ' ')) {
            return true;
        }
    }

    return false;
}

// =====================================================================
// 3. Filtros fijos (se revisan ANTES de buscar)
// =====================================================================

/**
 * Posible emergencia. Lista prudente: ante la duda, se orienta a buscar
 * atención urgente (nunca se intenta un diagnóstico).
 */
function vita_es_emergencia(string $normalizado): bool
{
    return vita_contiene($normalizado, [
        'emergencia*', 'urgencia*', 'urgente*', 'infarto*', 'convulsi*', 'desmay*',
        'inconscien*', 'sangrado*', 'sangrando', 'hemorragi*', 'envenen*', 'intoxic*',
        'sobredosis', 'suicid*', 'asfixi*', 'ahogando', 'accidente*', 'quemadura*',
        'no puedo respirar', 'no puede respirar', 'no respira', 'dolor de pecho',
        'dolor en el pecho', 'matarme', 'quitarme la vida', 'hacerme dano',
    ]) || vita_es_dolor_de_pecho($normalizado);
}

// Cuántas posiciones puede haber entre la palabra de dolor y "pecho"
// (en cualquier orden) para contarlo como dolor de pecho. Con 6 entran
// frases como "tengo un dolor punzante en el lado izquierdo del pecho".
const VITA_DISTANCIA_PECHO = 6;

/**
 * Dolor u opresión en el pecho dicho con otras palabras:
 *   "me duele mucho el pecho", "el pecho me duele",
 *   "tengo un dolor fuerte en el pecho", "siento opresión en el pecho".
 * Regla fija y prudente: si aparece "pecho" cerca de una palabra de dolor
 * o de presión, se trata como posible emergencia. NO es un diagnóstico ni
 * un triaje: solo decide si se muestra el mensaje fijo de atención urgente.
 * "Pecho" sin palabra de dolor ("tengo el pecho congestionado") no entra.
 */
function vita_es_dolor_de_pecho(string $normalizado): bool
{
    $de_dolor = [
        'dolor', 'dolores', 'duele', 'duelen', 'doliendo', 'adolorido', 'adolorida',
        'opresion', 'presion', 'aprieta', 'apretado', 'apretada', 'punzada', 'punzante',
    ];

    $palabras = explode(' ', $normalizado);
    $posiciones_pecho = array_keys($palabras, 'pecho', true);

    if ($posiciones_pecho === []) {
        return false;
    }

    foreach ($palabras as $posicion => $palabra) {
        if (!in_array($palabra, $de_dolor, true)) {
            continue;
        }
        foreach ($posiciones_pecho as $posicion_pecho) {
            if (abs($posicion - $posicion_pecho) <= VITA_DISTANCIA_PECHO) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Consulta médica: síntomas, medicamentos o pedido de diagnóstico.
 * Vita no responde estas preguntas: deriva a una cita con un médico.
 * (No se incluye "medicina": es parte de "Medicina General", un área.)
 */
function vita_es_consulta_medica(string $normalizado): bool
{
    return vita_contiene($normalizado, [
        'sintoma*', 'dolor*', 'duele*', 'fiebre*', 'tos', 'gripe*', 'diagnost*',
        'medicamento*', 'pastilla*', 'dosis', 'receta*', 'enferm*', 'infecci*',
        'alergi*', 'vomit*', 'mareo*', 'mareado*', 'antibiotico*', 'paracetamol',
        'ibuprofeno', 'embaraz*', 'diabet*', 'cancer*', 'tumor*', 'presion alta',
        'presion arterial', 'que me tomo', 'que tomo', 'que debo tomar', 'es grave',
        'tratamiento*', 'curar*', 'remedio*', 'virus',
    ]);
}

/**
 * ¿El mensaje trae algo que parece un dato personal? (número de documento
 * o de teléfono, o un correo). Se mira el texto ORIGINAL.
 */
function vita_tiene_datos_personales(string $mensaje): bool
{
    // 7 o más dígitos seguidos, aunque tengan espacios, puntos o guiones
    // entre ellos (DNI, carné, celular: "987 654 321").
    if (preg_match('/\d(?:[\s.\-]?\d){6,}/u', $mensaje) === 1) {
        return true;
    }

    // Algo con forma de correo.
    return preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/u', $mensaje) === 1;
}

/**
 * ¿Es solo un saludo o un agradecimiento?
 */
function vita_es_saludo(string $normalizado): bool
{
    $saludos = [
        'hola', 'holi', 'buenas', 'buenos', 'dias', 'tardes', 'noches', 'buen',
        'dia', 'gracias', 'muchas', 'ok', 'vale', 'chau', 'adios', 'saludos', 'hey',
        'vita',
    ];

    $palabras = explode(' ', $normalizado);

    return $normalizado !== '' && array_diff($palabras, $saludos) === [];
}

// =====================================================================
// 4. Búsqueda ponderada
// =====================================================================

/**
 * Prepara el "índice" de cada pregunta: sus palabras clave sueltas, sus
 * frases clave (de varias palabras) y las palabras de la pregunta.
 * También calcula cuántas preguntas usan cada palabra clave: una palabra
 * que aparece en una sola pregunta vale más que una muy repetida ("cita").
 */
function vita_indice(array $preguntas): array
{
    $indice = [];
    $uso = [];   // palabra clave => en cuántas preguntas aparece

    foreach ($preguntas as $fila) {
        $sueltas = [];
        $frases = [];

        foreach (explode(',', $fila['palabras_clave']) as $clave) {
            $clave = vita_normalizar($clave);
            if ($clave === '') {
                continue;
            }
            if (str_contains($clave, ' ')) {
                $frases[$clave] = true;
            }
            foreach (vita_palabras($clave) as $palabra) {
                $sueltas[$palabra] = true;
            }
        }

        foreach (array_keys($sueltas) as $palabra) {
            $uso[$palabra] = ($uso[$palabra] ?? 0) + 1;
        }

        $indice[$fila['clave']] = [
            'sueltas'  => array_keys($sueltas),
            'frases'   => array_keys($frases),
            'pregunta' => vita_palabras(vita_normalizar($fila['pregunta_es'])),
        ];
    }

    return [$indice, $uso];
}

/**
 * Peso de una palabra clave según en cuántas preguntas aparece.
 */
function vita_peso(int $usos): int
{
    if ($usos <= 1) {
        return 3;
    }

    return $usos === 2 ? 2 : 1;
}

/**
 * Puntúa cada pregunta frente al mensaje. Devuelve [clave => puntaje],
 * de mayor a menor, solo con las que sumaron algo.
 *
 * Por cada palabra del mensaje se toma lo MEJOR que encuentra en cada
 * pregunta (no se suma dos veces la misma palabra):
 *   - igual a una palabra clave .............. su peso (3, 2 o 1)
 *   - igual a una palabra de la pregunta ..... 1
 *   - parecida a una palabra de la pregunta .. 0,5
 * Y cada frase clave completa ("no puedo ir") suma 4 más.
 *
 * Errores de escritura: SOLO se buscan palabras "parecidas" cuando la
 * palabra escrita NO existe en el vocabulario de Vita (es decir, cuando
 * probablemente está mal escrita: "cancelr", "dirrecion"). Así una
 * palabra bien escrita, como "cuenta", nunca se confunde con "cuántas".
 * Una palabra corregida vale lo mismo que la palabra clave correcta.
 */
function vita_puntuar(string $normalizado, array $preguntas): array
{
    [$indice, $uso] = vita_indice($preguntas);
    $palabras = vita_palabras_mensaje($normalizado);
    $con_espacios = ' ' . $normalizado . ' ';
    $puntajes = [];

    // Todas las palabras que Vita conoce (claves y preguntas).
    $vocabulario = [];
    foreach ($indice as $datos) {
        foreach (array_merge($datos['sueltas'], $datos['pregunta']) as $conocida) {
            $vocabulario[$conocida] = true;
        }
    }

    foreach ($indice as $clave => $datos) {
        $puntaje = 0.0;

        foreach ($palabras as $palabra) {
            $mejor = 0.0;
            $corregir = !isset($vocabulario[$palabra]);

            foreach ($datos['sueltas'] as $clave_suelta) {
                if ($palabra === $clave_suelta
                    || ($corregir && vita_parecidas($palabra, $clave_suelta))) {
                    $mejor = max($mejor, vita_peso($uso[$clave_suelta]));
                }
            }

            if ($mejor === 0.0) {
                foreach ($datos['pregunta'] as $de_pregunta) {
                    if ($palabra === $de_pregunta) {
                        $mejor = max($mejor, 1.0);
                    } elseif ($corregir && vita_parecidas($palabra, $de_pregunta)) {
                        $mejor = max($mejor, 0.5);
                    }
                }
            }

            $puntaje += $mejor;
        }

        foreach ($datos['frases'] as $frase) {
            if (str_contains($con_espacios, ' ' . $frase . ' ')) {
                $puntaje += 4;
            }
        }

        if ($puntaje > 0) {
            $puntajes[$clave] = $puntaje;
        }
    }

    arsort($puntajes);

    return $puntajes;
}

// =====================================================================
// 5. Armar la respuesta (la usa public/vita/responder.php)
// =====================================================================

/**
 * Respuesta de Vita a un mensaje libre o a una clave de pregunta.
 * $entrada es ['mensaje' => '...'] o ['clave' => 'cancelar-cita'] (ya
 * validados por el endpoint). Devuelve el arreglo que se envía como JSON.
 *
 * Orden de revisión: emergencia -> datos personales -> consulta médica ->
 * saludo -> búsqueda. Lo más delicado va primero.
 *
 * Solo LEE la base. LANZA PDOException si la base falla.
 */
function vita_responder(PDO $pdo, array $entrada, string $idioma = 'es'): array
{
    $preguntas = vita_leer_publicadas($pdo, $idioma);
    $por_clave = array_column($preguntas, null, 'clave');

    // Botón de una pregunta frecuente (la clave viene de una sugerencia).
    if (isset($entrada['clave'])) {
        $fila = $por_clave[$entrada['clave']] ?? null;
        if ($fila === null) {
            return vita_no_entendi($por_clave, [], $idioma);
        }
        return vita_respuesta_pregunta($fila, $idioma);
    }

    $mensaje = $entrada['mensaje'];
    $normalizado = vita_normalizar($mensaje);

    if (vita_es_emergencia($normalizado)) {
        return vita_resultado('emergencia', $idioma,
            'Si tú u otra persona está en peligro, busca atención médica urgente: '
            . 'acude de inmediato al servicio de emergencias más cercano o llama a la '
            . 'central de emergencias de tu localidad. Vita no puede atender emergencias.'
        );
    }

    if (vita_tiene_datos_personales($mensaje)) {
        return vita_resultado('datos_personales', $idioma,
            'Por tu seguridad, no escribas datos personales como tu número de documento, '
            . 'teléfono o correo. Vita no los necesita para ayudarte y no guarda lo que escribes.',
            null,
            vita_sugerencias($por_clave, VITA_SUGERENCIAS_INICIALES)
        );
    }

    if (vita_es_consulta_medica($normalizado)) {
        return vita_resultado('consulta_medica', $idioma,
            'Vita no puede dar diagnósticos, recomendar medicamentos ni dar consejos '
            . 'médicos. Para eso, pide una cita y un médico te atenderá.',
            vita_accion_json('pedir_cita')
        );
    }

    if (vita_es_saludo($normalizado)) {
        return vita_resultado('saludo', $idioma,
            '¡Hola! Soy Vita, el asistente de Clínica Vida Sana. Puedo ayudarte con '
            . 'citas, tu cuenta, servicios, horarios y contacto.',
            null,
            vita_sugerencias($por_clave, VITA_SUGERENCIAS_INICIALES)
        );
    }

    $puntajes = vita_puntuar($normalizado, $preguntas);
    $claves = array_keys($puntajes);
    $primero = $puntajes[$claves[0] ?? ''] ?? 0;
    $segundo = $puntajes[$claves[1] ?? ''] ?? 0;

    // Coincidencia clara: buen puntaje y sin empate con la segunda.
    if ($primero >= VITA_PUNTAJE_MINIMO && $primero - $segundo >= VITA_VENTAJA_MINIMA) {
        return vita_respuesta_pregunta($por_clave[$claves[0]], $idioma);
    }

    // Débil o ambigua: no se adivina. Se sugieren las más cercanas.
    return vita_no_entendi($por_clave, array_slice($claves, 0, 3), $idioma);
}

/**
 * Respuesta con una pregunta frecuente concreta.
 */
function vita_respuesta_pregunta(array $fila, string $idioma): array
{
    $resultado = vita_resultado('respuesta', $idioma, $fila['respuesta'],
        vita_accion_json($fila['accion']));
    $resultado['pregunta'] = $fila['pregunta'];
    $resultado['idioma'] = $fila['idioma'];

    return $resultado;
}

/**
 * Respuesta segura cuando no se entendió (o fue ambiguo).
 */
function vita_no_entendi(array $por_clave, array $cercanas, string $idioma): array
{
    $claves = $cercanas !== [] ? $cercanas : VITA_SUGERENCIAS_INICIALES;
    $sugerencias = vita_sugerencias($por_clave, $claves);

    $texto = $cercanas !== []
        ? 'No tengo claro qué necesitas. ¿Te refieres a alguna de estas preguntas?'
        : 'No encontré una respuesta para eso. Puedo ayudarte con citas, tu cuenta, '
          . 'servicios, horarios y contacto. Estas son algunas preguntas frecuentes:';

    return vita_resultado('sin_respuesta', $idioma, $texto, null, $sugerencias);
}

/**
 * Hasta 3 sugerencias [{clave, pregunta}] (solo preguntas publicadas).
 */
function vita_sugerencias(array $por_clave, array $claves): array
{
    $lista = [];

    foreach ($claves as $clave) {
        if (isset($por_clave[$clave])) {
            $lista[] = ['clave' => $clave, 'pregunta' => $por_clave[$clave]['pregunta']];
        }
        if (count($lista) === 3) {
            break;
        }
    }

    return $lista;
}

/**
 * Acción para el JSON: clave, texto y URL armada desde la lista autorizada.
 * La URL usa url(), así sirve igual en XAMPP y en Fedora.
 */
function vita_accion_json(?string $clave): ?array
{
    $accion = vita_accion($clave);
    if ($accion === null) {
        return null;
    }

    return ['clave' => $clave, 'texto' => $accion['texto'], 'url' => url($accion['ruta'])];
}

/**
 * Forma común de TODAS las respuestas correctas de Vita.
 * Los textos fijos de este archivo están en español; si se pidió otro
 * idioma, se avisa que todavía no hay traducción revisada.
 */
function vita_resultado(
    string $tipo,
    string $idioma,
    string $respuesta,
    ?array $accion = null,
    array $sugerencias = []
): array {
    return [
        'ok'          => true,
        'tipo'        => $tipo,
        'idioma'      => 'es',
        'respuesta'   => $respuesta,
        'pregunta'    => null,
        'accion'      => $accion,
        'sugerencias' => $sugerencias,
        'aviso'       => $idioma === 'es'
            ? null
            : 'Las respuestas en quechua aún no están disponibles. Te respondemos en español.',
    ];
}

// =====================================================================
// 6. Control de solicitudes excesivas
// =====================================================================

// Resultados posibles del control de solicitudes.
const VITA_LIMITE_PERMITIDO = 'permitido';   // puede seguir
const VITA_LIMITE_ALCANZADO = 'alcanzado';   // pasó el límite: esperar
const VITA_LIMITE_ERROR     = 'error';       // no se pudo contar: NO se atiende

/**
 * Carpeta donde se cuentan los pedidos.
 * Por defecto, "vita-limite" dentro de la carpeta temporal del sistema.
 * Se puede cambiar en config.php con 'vita_limite_carpeta' (ver
 * docs/despliegue.md, sección de Vita, para Fedora y SELinux).
 */
function vita_carpeta_limite(): string
{
    $carpeta = config('vita_limite_carpeta');

    if (!is_string($carpeta) || $carpeta === '') {
        $carpeta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vita-limite';
    }

    return rtrim($carpeta, '/\\');
}

/**
 * ¿Esta IP (y todo el sitio) está dentro del límite de mensajes por minuto?
 * Devuelve una de las constantes VITA_LIMITE_*.
 *
 * No usa la base de datos (Vita no escribe en ella) ni sesiones. Guarda
 * solo HORAS de pedidos en archivos pequeños, uno por IP. El nombre del
 * archivo es un hash de la IP (la IP no se escribe tal cual) y nunca se
 * guarda el mensaje. Los archivos viejos se borran solos.
 *
 * Si la carpeta o los archivos no se pueden usar, devuelve
 * VITA_LIMITE_ERROR: el endpoint responde "no disponible" en vez de
 * atender sin control (así nadie puede saturar el servidor aprovechando
 * una falla). El detalle queda en el log del servidor.
 */
function vita_estado_limite(string $ip): string
{
    $carpeta = vita_carpeta_limite();

    if (!is_dir($carpeta) && !@mkdir($carpeta, 0700, true) && !is_dir($carpeta)) {
        error_log('Vita: no se pudo crear la carpeta del límite de solicitudes.');
        return VITA_LIMITE_ERROR;
    }

    // Limpieza ocasional (1 de cada 100 pedidos): archivos de más de 10 min.
    if (random_int(1, 100) === 1) {
        foreach (glob($carpeta . DIRECTORY_SEPARATOR . '*.txt') ?: [] as $archivo) {
            if (@filemtime($archivo) < time() - 600) {
                @unlink($archivo);
            }
        }
    }

    $por_ip = vita_contar_pedido($carpeta, hash('sha256', 'vita-ip|' . $ip), VITA_LIMITE_POR_IP_MINUTO);
    if ($por_ip !== VITA_LIMITE_PERMITIDO) {
        return $por_ip;
    }

    return vita_contar_pedido($carpeta, 'global', VITA_LIMITE_GLOBAL_MINUTO);
}

/**
 * Anota un pedido en el archivo $nombre si todavía no pasó $limite en el
 * último minuto. Devuelve una de las constantes VITA_LIMITE_*.
 * flock() evita que dos pedidos a la vez se pisen el archivo. Cualquier
 * paso que falle (abrir, bloquear, escribir) devuelve VITA_LIMITE_ERROR.
 */
function vita_contar_pedido(string $carpeta, string $nombre, int $limite): string
{
    $archivo = @fopen($carpeta . DIRECTORY_SEPARATOR . $nombre . '.txt', 'c+');
    if ($archivo === false) {
        error_log('Vita: no se pudo abrir el archivo del límite de solicitudes.');
        return VITA_LIMITE_ERROR;
    }

    if (!flock($archivo, LOCK_EX)) {
        fclose($archivo);
        error_log('Vita: no se pudo bloquear el archivo del límite de solicitudes.');
        return VITA_LIMITE_ERROR;
    }

    $ahora = time();
    $contenido = stream_get_contents($archivo);
    $marcas = array_filter(
        array_map('intval', explode(',', (string) $contenido)),
        fn (int $momento): bool => $momento > $ahora - 60
    );

    $alcanzado = count($marcas) >= $limite;
    if (!$alcanzado) {
        $marcas[] = $ahora;
    }

    $texto = implode(',', $marcas);
    $escrito = ftruncate($archivo, 0) && rewind($archivo)
        && @fwrite($archivo, $texto) === strlen($texto) && fflush($archivo);

    flock($archivo, LOCK_UN);
    fclose($archivo);

    if (!$escrito) {
        error_log('Vita: no se pudo escribir el archivo del límite de solicitudes.');
        return VITA_LIMITE_ERROR;
    }

    return $alcanzado ? VITA_LIMITE_ALCANZADO : VITA_LIMITE_PERMITIDO;
}
