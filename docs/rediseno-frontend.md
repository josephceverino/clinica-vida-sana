# CLÍNICA VIDA SANA 2.0
## Documento maestro de rediseño frontend

**Proyecto:** Clínica Vida Sana  
**Repositorio:** https://github.com/josephceverino/clinica-vida-sana  
**Rama funcional:** `main`  
**Objetivo:** Rediseñar integralmente la interfaz conservando el backend y las funcionalidades existentes.  
**Estado:** Especificación lista para iniciar implementación controlada.

---

## 1. Objetivo general

Modernizar la experiencia visual del sitio público, el portal del paciente y el panel del personal, mediante un sistema de diseño uniforme, responsive y accesible.

El resultado debe parecer una aplicación clínica profesional, no una colección de páginas diseñadas por separado.

**Principio fundamental:** el rediseño modifica la presentación y las interacciones visuales, no las reglas de negocio.

## 2. Tecnologías obligatorias

- PHP 8.2+ sin frameworks.
- MariaDB con PDO.
- HTML, CSS y JavaScript puros.
- Apache en XAMPP y Fedora.
- Sin React, Vue, Tailwind, Bootstrap ni dependencias nuevas sin autorización.

Respetar las instrucciones de `CLAUDE.md` y las convenciones actuales.

## 3. Sistema de diseño

### 3.1 Colores

| Token | Modo claro | Modo oscuro |
|---|---|---|
| Primario | `#087C94` | `#087C94` |
| Fondo | `#F2F7F9` | `#12232D` |
| Superficie | `#FFFFFF` | `#1D3540` |
| Texto principal | `#193847` | `#EDF6F8` |
| Sidebar | `#087C94` | `#075267` |
| Éxito | `#22B77A` | Ajustar contraste |
| Error | Rojo accesible | Rojo accesible |
| Advertencia | Ámbar accesible | Ámbar accesible |

Los colores deberán implementarse con variables CSS. Los valores de texto, bordes y estados interactivos deberán comprobarse para garantizar contraste suficiente.

### 3.2 Estilo visual

- Diseño clínico contemporáneo, sobrio y ordenado.
- Tipografía legible, preferentemente del sistema.
- Tarjetas funcionales, no excesivamente grandes.
- Bordes suaves de aproximadamente 10–12 px.
- Espaciado consistente.
- Tablas compactas, con encabezados claros.
- Sombras discretas.
- Iconos uniformes.
- Sin gradientes decorativos excesivos.
- Sin animaciones que distraigan de las tareas.

### 3.3 Componentes compartidos

Crear estilos reutilizables para:

- Botones primarios, secundarios y destructivos.
- Campos de texto, selectores, fechas y áreas de texto.
- Formularios con etiquetas flotantes cuando sean compatibles con el comportamiento existente.
- Tarjetas de resumen.
- Tablas, filtros y paginación existente.
- Etiquetas de estado.
- Alertas y notificaciones.
- Ventanas de confirmación.
- Estados vacíos.
- Indicadores de carga.
- Navegación y menús.

No implementar componentes que requieran endpoints o funciones inexistentes.

## 4. Navegación

### Panel del personal

Conservar `app/layout/panel_header.php` como punto central de la navegación.

Implementar:

- Sidebar contraíble mediante botón.
- Transición aproximada de 300 ms.
- Modo expandido con iconos y etiquetas.
- Modo contraído con iconos y descripciones accesibles.
- Persistencia de preferencia cuando el almacenamiento del navegador esté disponible.
- Menú móvil tipo panel superpuesto.
- Header compacto.
- Selector de tema.
- Acceso a perfil y cierre de sesión.

**No expandir automáticamente la sidebar al pasar el cursor.**

La visibilidad de cada enlace seguirá dependiendo de los permisos PHP existentes.

### Portal del paciente

Conservar su navegación propia, adaptándola a móviles. Mantener la estructura de acceso a Inicio, Mis citas, Pedir cita e Historia clínica, además de Mi perfil.

### Sitio público

Conservar Inicio, Servicios, Nosotros, Contacto y el acceso único al Portal del paciente.

## 5. Modo claro y oscuro

El cambio de tema debe funcionar en las tres zonas del sistema.

Requisitos:

- Preferencia persistente.
- Respeto inicial por el tema del sistema cuando no exista una preferencia guardada.
- Transición visual suave.
- Sin destellos innecesarios al cargar.
- Tablas, formularios, menús y ventanas adaptados.
- Compatibilidad con `prefers-reduced-motion`.

Utilizar JavaScript externo compatible con la política CSP existente.

## 6. Animaciones

Las animaciones deben mejorar la comprensión de las acciones.

| Acción | Comportamiento |
|---|---|
| Guardar | Indicador de proceso; confirmación únicamente tras respuesta exitosa |
| Enviar | Movimiento discreto del icono |
| Eliminar o rechazar | Confirmación previa cuando corresponda |
| Cerrar sesión | Microinteracción visual sin retrasar el envío |
| Ingresar | Elevación muy sutil |
| Abrir modal | Aparición suave, aproximadamente 180 ms |
| Contraer sidebar | Transición aproximada de 300 ms |
| Cambiar tema | Transición suave de colores |

No mostrar una confirmación de éxito antes de que PHP complete la operación.

## 7. Cobertura obligatoria

### Sitio público

- Inicio.
- Servicios.
- Nosotros.
- Contacto.
- Login.
- Registro.
- Privacidad.
- Error 403.
- Error 404.

Conservar el carrusel existente y sus controles accesibles. Renovar sus imágenes y presentación solo cuando corresponda.

### Portal del paciente

- Inicio.
- Mis citas.
- Pedir cita.
- Historia clínica.
- Mi perfil.
- Estado de cuenta pendiente de verificación.

### Panel del personal

- Resumen administrativo.
- Resumen médico.
- Pacientes.
- Registrar paciente.
- Citas.
- Registrar cita.
- Gestionar cita.
- Atender cita.
- Agenda semanal.
- Solicitudes.
- Programar solicitud.
- Cuentas por verificar.
- Usuarios del personal.
- Horarios de médicos.
- Auditoría.
- Mi perfil.

La disponibilidad de cada pantalla debe seguir las restricciones del rol actual.

## 8. Archivos principales identificados

### Plantillas

- `app/layout/publico_header.php`
- `app/layout/publico_footer.php`
- `app/layout/paciente_header.php`
- `app/layout/paciente_footer.php`
- `app/layout/panel_header.php`
- `app/layout/panel_footer.php`
- `app/layout/form_password.php`

### Estilos

- `public/assets/css/base.css`
- `public/assets/css/paciente.css`
- `public/assets/css/panel.css`

### JavaScript existente

- `public/assets/js/portada.js`

Pueden añadirse archivos CSS o JavaScript compartidos si existe una justificación clara. No reorganizar las rutas actuales sin necesidad.

## 9. Archivos y comportamientos protegidos

No modificar sin autorización específica:

- `app/core/auth.php`
- `app/core/db.php`
- `app/core/limites.php`
- `app/modelos/`
- `config/`
- `sql/`
- `deploy.sh`
- Configuración de Apache.
- Reglas de seguridad y autorización.

En los archivos PHP de presentación, preservar:

- `method` y `action` de los formularios.
- Atributos `name` y valores enviados.
- Campos ocultos.
- Tokens CSRF.
- Identificadores necesarios para scripts.
- Rutas de redirección.
- Validaciones PHP.
- Escape de salida con `e()`.
- Restricciones por rol.
- Estados reales de la base de datos.
- Datos obtenidos de consultas existentes.

## 10. Plan de implementación

### Fase 0 — Preparación

- Crear una rama de trabajo independiente.
- Verificar el estado funcional inicial.
- Registrar pantallas y comportamientos.
- Confirmar respaldo de la base de datos.
- No ejecutar `schema.sql` sobre una base con datos que deban conservarse.

### Fase 1 — Fundamentos visuales

Actualizar variables, tipografía, botones, campos, tablas y componentes básicos.

**Criterio de aprobación:** las páginas existentes siguen funcionando y los nuevos estilos no rompen su estructura.

### Fase 2 — Navegación del personal

Implementar sidebar, header, tema y responsive.

**Criterio de aprobación:** cada rol conserva exactamente sus opciones y permisos.

### Fase 3 — Portal del paciente

Rediseñar navegación, resumen, citas, formularios e historia clínica.

**Criterio de aprobación:** el paciente solo accede a sus datos y conserva los procesos de reserva.

### Fase 4 — Pantallas internas del personal

Rediseñar formularios, tablas, agenda, verificaciones y atención médica.

**Criterio de aprobación:** cada operación POST conserva los mismos resultados y controles.

### Fase 5 — Sitio público

Renovar portada, servicios, nosotros y contacto, conservando navegación y contenido funcional.

### Fase 6 — Acceso y páginas complementarias

Rediseñar login, registro, privacidad y errores 403/404.

### Fase 7 — Pruebas finales

Verificar todas las páginas en escritorio y móvil, ambos temas y cada rol.

## 11. Protocolo obligatorio para Claude

Antes de cada fase:

1. Leer los archivos que se van a modificar.
2. Explicar qué cambiará y por qué.
3. Enumerar los archivos afectados.
4. Señalar posibles riesgos.
5. Esperar autorización antes de aplicar cambios amplios.

Después de cada fase:

1. Mostrar los archivos modificados.
2. Resumir los cambios.
3. Ejecutar las comprobaciones disponibles.
4. Identificar lo que todavía requiere pruebas manuales.
5. No continuar automáticamente a la fase siguiente.

No declarar una funcionalidad como probada si no se ha ejecutado.

## 12. Criterios de aceptación final

El rediseño se considerará completo cuando:

- Todas las pantallas estén cubiertas.
- Exista coherencia visual entre las tres zonas.
- El modo oscuro funcione correctamente.
- La navegación sea responsive.
- No aparezcan errores de PHP o JavaScript atribuibles a los cambios.
- Se conserven autenticación, roles, CSRF y validaciones.
- Las citas, solicitudes, verificaciones y atenciones funcionen.
- Las animaciones respeten accesibilidad.
- Los cambios estén revisados en una rama independiente.
- La integración a `main` se realice únicamente después de aprobar las pruebas.

---

**Estado del documento:** especificación inicial completa, basada en las decisiones visuales acordadas y en los archivos examinados del repositorio. Los detalles de implementación deberán validarse contra cada archivo antes de editarlo.
