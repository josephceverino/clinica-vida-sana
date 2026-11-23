-- =====================================================================
-- Clínica Vida Sana - Actualización de noviembre 2026: Vita (palabras clave)
--
-- Para bases que YA tienen aplicada migracion-2026-11-vita.sql.
-- (Esa migración usa INSERT IGNORE: volver a importarla NO cambia las
-- filas que ya existen. Por eso hace falta esta actualización.)
--
-- Qué hace: agrega palabras o frases clave a TRES preguntas.
--   horarios-atencion -> que hora atienden, que hora abren,
--                        que hora cierran, hora de atencion
--                        (responde directo a "¿A qué hora atienden?")
--   ver-mis-citas     -> hora de mi cita, que hora es mi cita
--                        (así "¿A qué hora es mi cita?" no se confunde
--                        con el horario de atención)
--   crear-cuenta      -> creo
--                        (responde a "¿Cómo creo mi cuenta?"; "creo que..."
--                        se ignora en el código, en vita_palabras())
--
-- NO borra nada ni toca otras tablas u otras preguntas. Solo cambia la
-- columna palabras_clave del texto en español de esas tres preguntas, y
-- SOLO si todavía tiene el valor original de la migración: si alguien ya
-- la corrigió a mano, no se pisa (esa fila queda igual).
--
-- Se puede ejecutar varias veces: la segunda vez no cambia nada.
-- Igual que la migración, NO elige la base: se indica en el comando.
--     XAMPP:   mysql -u root --default-character-set=utf8mb4 clinica_vida_sana < sql/actualizacion-2026-11-vita-palabras-clave.sql
--     Fedora:  sudo mariadb --default-character-set=utf8mb4 clinica_vida_sana < /var/www/clinica/sql/actualizacion-2026-11-vita-palabras-clave.sql
-- Una instalación nueva NO la necesita: la migración ya trae estos cambios.
-- =====================================================================

START TRANSACTION;

UPDATE vita_textos t
JOIN vita_preguntas p ON p.id = t.pregunta_id
SET t.palabras_clave = 'horario,horario de atencion,atencion,atienden,abren,cierran,abierto,sabado,domingo,feriado,que hora atienden,que hora abren,que hora cierran,hora de atencion'
WHERE p.clave = 'horarios-atencion'
  AND t.idioma = 'es'
  AND t.palabras_clave = 'horario,horario de atencion,atencion,atienden,abren,cierran,abierto,sabado,domingo,feriado';

UPDATE vita_textos t
JOIN vita_preguntas p ON p.id = t.pregunta_id
SET t.palabras_clave = 'mis citas,ver mis citas,proxima,proximas citas,mis reservas,fecha,hora,hora de mi cita,que hora es mi cita'
WHERE p.clave = 'ver-mis-citas'
  AND t.idioma = 'es'
  AND t.palabras_clave = 'mis citas,ver mis citas,proxima,proximas citas,mis reservas,fecha,hora';

UPDATE vita_textos t
JOIN vita_preguntas p ON p.id = t.pregunta_id
SET t.palabras_clave = 'registro,registrarme,registrar,crear,crear cuenta,cuenta,inscribirme,creo'
WHERE p.clave = 'crear-cuenta'
  AND t.idioma = 'es'
  AND t.palabras_clave = 'registro,registrarme,registrar,crear,crear cuenta,cuenta,inscribirme';

COMMIT;

-- Comprobación: debe mostrar las tres preguntas con sus palabras nuevas.
SELECT p.clave, t.palabras_clave
FROM vita_preguntas p
JOIN vita_textos t ON t.pregunta_id = p.id AND t.idioma = 'es'
WHERE p.clave IN ('horarios-atencion', 'ver-mis-citas', 'crear-cuenta');
