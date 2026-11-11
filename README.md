# \# Clínica Vida Sana

# 

# Proyecto académico de Administración de Servidores Multiplataforma,

# Ingeniería de Sistemas, Universidad César Vallejo.

# 

# Clínica simulada: utilizar únicamente datos ficticios.

# 

# \## Tecnologías

# 

# \- PHP 8.2 o superior, sin frameworks.

# \- MariaDB, con conexión PDO.

# \- Apache.

# \- HTML, CSS y JavaScript.

# 

# \## Instalación nueva en Windows con XAMPP

# 

# 1\. Clonar el repositorio en:

# &#x20;  C:\\xampp\\htdocs\\clinica-vida-sana

# 2\. Iniciar Apache y MySQL desde XAMPP.

# 3\. Copiar config/config.example.php como config/config.php.

# 4\. Configurar las credenciales locales de MariaDB.

# 5\. Importar sql/schema.sql y después sql/seed.sql.

# 6\. Abrir:

# &#x20;  http://localhost/clinica-vida-sana/public/

# 

# ADVERTENCIA: schema.sql elimina y vuelve a crear las tablas.

# Utilizarlo únicamente para una instalación nueva o para reiniciar

# deliberadamente una base de desarrollo.

# 

# No subir config/config.php a GitHub.

# 

# \## Actualizar una base existente

# 

# Antes de actualizar, realizar un respaldo de la base de datos.

# 

# No importar nuevamente schema.sql ni seed.sql.

# 

# Revisar primero qué columnas existen:

# 

# \- Si citas no tiene medico\_id:

# &#x20; ejecutar sql/agregar-medico-citas.sql una sola vez.

# \- Si areas no tiene descripcion ni publica:

# &#x20; ejecutar sql/actualizar-areas-publicas.sql una sola vez.

# 

# No ejecutar esas actualizaciones después de una instalación

# con el schema.sql actualizado: las columnas ya estarán creadas.

# 

# Si la base tiene solo una de las columnas de áreas, revisar

# su estructura antes de ejecutar el archivo de actualización.

# 

# \## Usuarios de prueba

# 

# Todos utilizan la contraseña de desarrollo: Clinica2026!

# 

# | Usuario | Rol |

# |---------|-----|

# | admin | Administración |

# | admision1 | Admisión |

# | medico1 | Médico |

# 

# Cambiar las contraseñas de prueba antes de desplegar el sistema.

# 

# \## Funcionalidades

# 

# \### Zona pública

# 

# \- Consulta de servicios y áreas públicas.

# \- Solicitud de cita con validación PHP y protección CSRF.

# \- Confirmación de recepción sin crear automáticamente una cita.

# 

# \### Administración y admisión

# 

# \- Registro y búsqueda de pacientes.

# \- Rechazo de DNI duplicados.

# \- Consulta y registro de citas.

# \- Asignación de médicos activos del área correspondiente.

# \- Rechazo de dos citas activas del médico en la misma fecha y hora.

# \- Rechazo de citas del paciente en la misma fecha y hora,

# &#x20; salvo que la cita anterior esté cancelada.

# \- Gestión de estados de citas.

# \- Consulta y procesamiento de solicitudes.

# 

# \### Médico

# 

# \- Consulta de sus propias citas por fecha.

# \- Acceso a los datos básicos de los pacientes de esas citas.

# \- Sin acceso a la gestión general de pacientes y solicitudes.

# 

# \## Estados

# 

# Citas:

# 

# \- pendiente → confirmada o cancelada.

# \- confirmada → atendida o cancelada.

# \- atendida y cancelada no admiten nuevos cambios.

# \- Una cita futura no puede marcarse como atendida.

# 

# Solicitudes:

# 

# \- nueva → atendida o descartada.

# \- Una solicitud procesada no puede modificarse nuevamente.

# \- Marcar atendida una solicitud no crea una cita automáticamente.

# 

# \## Datos iniciales

# 

# seed.sql contiene:

# 

# \- 5 áreas.

# \- 3 usuarios.

# \- 5 pacientes.

# \- 6 citas.

# \- 3 solicitudes.

# 

# Las citas iniciales de Medicina General se asignan al médico

# de prueba. Las fechas son ejemplos fijos de noviembre de 2026.

# 

# Los registros creados durante pruebas locales no forman parte

# de seed.sql.

# 

# \## Seguridad

# 

# \- Consultas preparadas con PDO para datos de entrada.

# \- Contraseñas almacenadas como hash.

# \- Salida HTML escapada mediante e().

# \- Protección CSRF en formularios POST.

# \- Control de acceso por sesión y rol.

# \- Identidad del personal obtenida desde la sesión.

# \- Áreas internas rechazadas por el formulario público y el backend.

# \- Configuración local excluida del repositorio.

# 

# \## Alcance de la agenda

# 

# La comprobación de conflictos compara la fecha y hora exactas.

# No calcula duración de consultas ni solapamientos entre intervalos.

# 

# \## Despliegue en Fedora

# 

# El DocumentRoot debe apuntar a public/.

# Configurar base\_url como cadena vacía cuando el sitio se publique

# desde la raíz del dominio.

# 

# La configuración de Apache y del servidor se gestiona por separado.

