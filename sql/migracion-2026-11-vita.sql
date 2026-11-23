-- =====================================================================
-- Clínica Vida Sana - Migración de noviembre 2026: Vita (etapa 1)
--
-- Crea la base de conocimientos de Vita, el asistente guiado:
--   vita_preguntas -> cada pregunta frecuente (categoría, acción, orden)
--   vita_textos    -> su texto en cada idioma, con estado de revisión
-- y carga las preguntas frecuentes en español.
--
-- NO toca ninguna tabla existente. Solo AGREGA dos tablas nuevas.
--
-- Uso: esta migración NO elige la base (no tiene "USE"), para poder
-- probarla primero en una base de pruebas. Se indica en el comando:
--     Pruebas (XAMPP):  mysql -u root clinica_vida_sana_prueba < sql/migracion-2026-11-vita.sql
--     Servidor Fedora:  sudo mariadb clinica_vida_sana < /var/www/clinica/sql/migracion-2026-11-vita.sql
-- Si se olvida el nombre de la base, MariaDB responde "No database
-- selected" y no hace nada.
--
-- La base elegida ya debe tener la tabla "usuarios" (schema.sql).
--
-- Se puede ejecutar varias veces: las tablas usan IF NOT EXISTS y los
-- datos usan INSERT IGNORE (si la fila ya existe, no se duplica ni se
-- pisa lo que el equipo haya corregido después).
--
-- Idiomas: 'es' (español) y 'quy' (quechua ayacuchano/chanka, código ISO
-- 639-3). Aquí NO se carga ningún texto en quechua: las traducciones las
-- escribe y revisa un hablante, y solo se publican después de revisarlas.
--
-- Necesita MariaDB 10.4 o más nuevo (CHECK e IF NOT EXISTS).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Preguntas (lo que no depende del idioma)
-- ---------------------------------------------------------------------
-- categoria: el ORDEN de la lista es el orden en que salen en la página
--            de ayuda (MariaDB ordena un ENUM por su posición).
-- accion:    botón que acompaña a la respuesta. Es una CLAVE, nunca una
--            dirección web: PHP la traduce con una lista fija
--            (vita_acciones() en app/modelos/vita.php). Así nadie puede
--            guardar aquí un enlace externo o peligroso. NULL = sin botón.
CREATE TABLE IF NOT EXISTS vita_preguntas (
    id        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    clave     VARCHAR(60)       NOT NULL,
    categoria ENUM('citas', 'cuenta', 'historia', 'servicios', 'contacto') NOT NULL,
    accion    ENUM('pedir_cita', 'mis_citas', 'historia', 'perfil', 'registro',
                   'login', 'servicios', 'contacto', 'privacidad') NULL,
    orden     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activa    TINYINT(1)        NOT NULL DEFAULT 1,
    creado_en DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vita_preguntas_clave (clave),
    KEY idx_vita_preguntas_lista (activa, categoria, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Textos de cada pregunta, uno por idioma
-- ---------------------------------------------------------------------
-- estado:         borrador -> revisado -> publicado. Solo "publicado" se
--                 muestra en la web.
-- palabras_clave: separadas por comas. Las usará el buscador de Vita en
--                 la etapa 2 (aquí solo se guardan).
-- revisado_por:   quién aprobó el texto. RESTRICT (y no SET NULL) porque
--                 MariaDB no deja usar en un CHECK una columna con acción
--                 SET NULL o CASCADE. En el sistema los usuarios no se
--                 borran: se desactivan.
-- Si una pregunta no tiene texto publicado en quechua, la web mostrará el
-- texto en español con un aviso (no se inventa una traducción).
CREATE TABLE IF NOT EXISTS vita_textos (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    pregunta_id    INT UNSIGNED  NOT NULL,
    idioma         ENUM('es', 'quy') NOT NULL,
    pregunta       VARCHAR(200)  NOT NULL,
    respuesta      VARCHAR(1000) NOT NULL,
    palabras_clave VARCHAR(500)  NOT NULL DEFAULT '',
    estado         ENUM('borrador', 'revisado', 'publicado') NOT NULL DEFAULT 'borrador',
    revisado_por   INT UNSIGNED  NULL,
    revisado_en    DATETIME      NULL,
    creado_en      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Un solo texto por pregunta e idioma.
    UNIQUE KEY uq_vita_textos_idioma (pregunta_id, idioma),
    KEY idx_vita_textos_publicados (idioma, estado),
    CONSTRAINT fk_vita_textos_pregunta
        FOREIGN KEY (pregunta_id) REFERENCES vita_preguntas (id)
        ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_vita_textos_revisor
        FOREIGN KEY (revisado_por) REFERENCES usuarios (id)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    -- Nada se publica sin fecha de revisión.
    CONSTRAINT ck_vita_textos_revisado
        CHECK (estado <> 'publicado' OR revisado_en IS NOT NULL),
    -- El quechua, además, necesita a la persona que lo revisó.
    CONSTRAINT ck_vita_textos_quechua
        CHECK (idioma <> 'quy' OR estado <> 'publicado' OR revisado_por IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Preguntas frecuentes en español
-- ---------------------------------------------------------------------
-- Cada respuesta describe lo que el sistema hace HOY. Si cambia una regla
-- del código, hay que actualizar su texto aquí (o desde la base):
--   60 días   -> DIAS_RESERVA_MAXIMO            (app/modelos/horarios.php)
--   3 citas   -> MAX_CITAS_ACTIVAS_POR_PACIENTE (app/modelos/horarios.php)
--   2 horas   -> HORAS_MINIMAS_PARA_CANCELAR    (app/modelos/citas.php)
--   8 a 72    -> validar_registro()             (app/modelos/cuentas.php)
-- Horarios, dirección, teléfonos y áreas NO se copian aquí: la respuesta
-- lleva a la página que ya los muestra, para no tener dos versiones.
INSERT IGNORE INTO vita_preguntas (clave, categoria, accion, orden) VALUES
    ('como-pedir-cita',     'citas',     'pedir_cita', 10),
    ('sin-horarios-libres', 'citas',     'pedir_cita', 20),
    ('limite-citas',        'citas',     'mis_citas',  30),
    ('ver-mis-citas',       'citas',     'mis_citas',  40),
    ('cancelar-cita',       'citas',     'mis_citas',  50),
    ('cita-sin-cuenta',     'cuenta',    'registro',   10),
    ('crear-cuenta',        'cuenta',    'registro',   20),
    ('cuenta-pendiente',    'cuenta',    NULL,         30),
    ('iniciar-sesion',      'cuenta',    'login',      40),
    ('cambiar-contrasena',  'cuenta',    'perfil',     50),
    ('ver-historia',        'historia',  'historia',   10),
    ('quien-ve-mis-datos',  'historia',  'privacidad', 20),
    ('servicios',           'servicios', 'servicios',  10),
    ('horarios-atencion',   'contacto',  'contacto',   10),
    ('ubicacion-contacto',  'contacto',  'contacto',   20);

-- Los textos se enlazan por la CLAVE de la pregunta (no por un id fijo),
-- así funcionan aunque los id salgan distintos en otra base.
-- Quedan "publicado" con la fecha de hoy: el equipo los revisó contra el
-- código al preparar esta migración. revisado_por queda vacío porque el
-- id de cada usuario cambia entre bases.
INSERT IGNORE INTO vita_textos
    (pregunta_id, idioma, pregunta, respuesta, palabras_clave, estado, revisado_en)
SELECT p.id, 'es', t.pregunta, t.respuesta, t.palabras_clave, 'publicado', NOW()
FROM vita_preguntas p
JOIN (
    SELECT 'como-pedir-cita' AS clave,
           '¿Cómo pido una cita?' AS pregunta,
           'Ingresa al Portal del paciente y entra a «Pedir cita». Elige el área y el día, y toca uno de los horarios libres: la cita queda confirmada al momento. Puedes reservar desde hoy y hasta 60 días adelante. Para pedir citas tu cuenta debe estar verificada por admisión.' AS respuesta,
           'cita,citas,pedir,pido,pedir cita,sacar cita,nueva cita,reservar,reservo,separar,turno,agendar,consulta' AS palabras_clave
    UNION ALL SELECT 'sin-horarios-libres',
           '¿Qué hago si no encuentro un horario libre?',
           'En «Pedir cita» también puedes enviar una solicitud a admisión: indicas el área, tu fecha preferida y el motivo (es obligatorio). Admisión la revisa y, si es posible, te programa la cita. Puedes seguir su estado en «Mis citas», en el apartado «Mis solicitudes».',
           'horario,libre,lleno,ocupado,solicitud,disponible,fecha'
    UNION ALL SELECT 'limite-citas',
           '¿Cuántas citas puedo tener reservadas a la vez?',
           'Hasta 3 citas vigentes, de hoy en adelante. Se cuentan las que reservas tú y las que te programa admisión. Así los horarios alcanzan para todos. Cuando pase el día de una de ellas, o si cancelas alguna, podrás reservar otra.',
           'cuantas,cuantas citas,limite,maximo,varias,citas'
    UNION ALL SELECT 'ver-mis-citas',
           '¿Dónde veo mis citas?',
           'En el Portal del paciente, en «Mis citas». Ahí aparecen tus próximas citas, las solicitudes que enviaste a admisión y tu historial de citas.',
           'mis citas,ver mis citas,proxima,proximas citas,mis reservas,fecha,hora,hora de mi cita,que hora es mi cita'
    UNION ALL SELECT 'cancelar-cita',
           '¿Puedo cancelar una cita?',
           'Sí. En «Mis citas», cada cita vigente tiene la opción de cancelarla, hasta 2 horas antes de la hora reservada. Ese horario vuelve a quedar libre para otra persona. Si faltan menos de 2 horas, comunícate con admisión.',
           'cancelar,cancelo,anular,eliminar,no puedo ir,no podre ir,cambiar mi cita,cambiar la cita,cita'
    UNION ALL SELECT 'cita-sin-cuenta',
           '¿Puedo pedir una cita sin crear una cuenta?',
           'No. La cuenta del Portal del paciente identifica a la persona que pide la cita y responde por sus datos. Crearla es gratis.',
           'sin cuenta,invitado,obligatorio,cuenta,pedir,necesito una cuenta,cuenta para pedir'
    UNION ALL SELECT 'crear-cuenta',
           '¿Cómo creo mi cuenta?',
           'Entra a «Regístrate» y completa tus nombres, apellidos, tipo y número de documento (DNI, carné de extranjería o pasaporte), correo, número de celular y una contraseña. También debes aceptar la política de privacidad.',
           'registro,registrarme,registrar,crear,crear cuenta,cuenta,inscribirme,creo'
    UNION ALL SELECT 'cuenta-pendiente',
           '¿Por qué mi cuenta dice «pendiente de verificación»?',
           'Toda cuenta nueva empieza pendiente. Acércate a admisión con tu documento de identidad: comprobarán que tus datos coinciden con los de tu registro. Mientras tanto ya puedes iniciar sesión; cuando tu cuenta esté verificada podrás pedir citas y ver tu historia clínica.',
           'pendiente,cuenta pendiente,verificar,verifico,verificada,verificacion,validar,activar,admision'
    UNION ALL SELECT 'iniciar-sesion',
           '¿Cómo ingreso al Portal del paciente?',
           'Toca «Portal del paciente» en la parte superior de la web y escribe el correo con el que te registraste y tu contraseña.',
           'ingresar,entrar,login,iniciar sesion,acceder,portal'
    UNION ALL SELECT 'cambiar-contrasena',
           '¿Cómo cambio mi contraseña?',
           'Con tu sesión iniciada, entra a «Mi perfil». La nueva contraseña debe tener de 8 a 72 caracteres, con al menos una letra y un número.',
           'contrasena,clave,password,cambiar,perfil,seguridad'
    UNION ALL SELECT 'ver-historia',
           '¿Dónde veo mi historia clínica?',
           'En el Portal del paciente, en «Historia clínica», una vez que tu cuenta esté verificada. Ahí aparecen las atenciones que registraron tus médicos y quién consultó tus datos clínicos.',
           'historia,historia clinica,historial,atenciones,resultados,expediente'
    UNION ALL SELECT 'quien-ve-mis-datos',
           '¿Quién puede ver mis datos?',
           'Tú, desde el Portal del paciente. Admisión y la administración del sistema ven tus datos de contacto y tus citas, pero no tu historia clínica. El médico que te atiende ve tus datos básicos y la atención de las citas que tiene contigo. Cada consulta a tu historia clínica queda registrada.',
           'privacidad,datos,quien,ver,seguridad,confidencial'
    UNION ALL SELECT 'servicios',
           '¿Qué servicios ofrece la clínica?',
           'Las áreas de atención que ofrece la clínica están publicadas en la página «Servicios». Al pedir una cita eliges una de esas áreas.',
           'servicios,especialidades,especialidad,areas,medicos,medicina,general,que ofrecen'
    UNION ALL SELECT 'horarios-atencion',
           '¿Cuál es el horario de atención?',
           'Los horarios de atención están publicados en la página «Contacto». Clínica Vida Sana es una clínica simulada de un proyecto académico: esos datos son demostrativos.',
           'horario,horario de atencion,atencion,atienden,abren,cierran,abierto,sabado,domingo,feriado,que hora atienden,que hora abren,que hora cierran,hora de atencion'
    UNION ALL SELECT 'ubicacion-contacto',
           '¿Dónde están y cómo me comunico con la clínica?',
           'En la página «Contacto» encontrarás el mapa, la dirección, el teléfono y el correo. Son datos demostrativos, porque Clínica Vida Sana es una clínica simulada.',
           'direccion,ubicacion,ubicados,ubicado,quedan,donde,mapa,telefono,correo,contacto,llegar,como llego'
) AS t ON t.clave = p.clave;
