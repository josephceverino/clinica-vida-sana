# CLAUDE.md — Clínica Vida Sana (portal web)

## Contexto
Proyecto académico del curso **Administración de Servidores Multiplataforma** (Ingeniería de Sistemas, UCV, 2026).
La "Clínica Vida Sana" es una **clínica simulada**: todos los datos son **ficticios**. Nunca uses nombres, DNI ni datos de personas reales.

El curso evalúa la **infraestructura** (Fedora Linux + Windows Server). Esta web es el servicio HTTP que publica el servidor Fedora. Debe ser **simple, funcional y segura**, no grande.

Equipo: **Joseph** y **Alexander**. Somos estudiantes y **estamos aprendiendo PHP**:
- Explica cada cambio en español, en pocas líneas y con palabras simples.
- Trabaja en pasos pequeños, una funcionalidad a la vez.
- Antes de crear o modificar varios archivos, di qué vas a hacer y por qué.
- Prefiere código claro y comentado sobre código "elegante" o abreviado.

## Tecnologías (no agregar otras sin preguntar)
- **PHP 8.2+** sin frameworks (sin Laravel, sin Composer obligatorio).
- **MariaDB** (compatible con MySQL), acceso con **PDO**.
- **HTML, CSS y JavaScript puros** (sin React, sin Bootstrap salvo que se pida).
- **Apache** como servidor web.

## Entornos
| Entorno | Dónde | URL |
|---|---|---|
| Desarrollo | Windows + XAMPP, repo en `C:\xampp\htdocs\clinica-vida-sana` | `http://localhost/clinica-vida-sana/public` |
| Producción | Fedora Server 44 (`srv.clinica.lan`, 192.168.10.1), código en `/var/www/clinica` | `https://www.clinica.lan` |

En producción, el `DocumentRoot` de Apache apunta a `public/`. **No modifiques la configuración del servidor desde el código**; los cambios de servidor los hace Joseph a mano.

## Estructura
```
public/                # Lo único accesible desde el navegador
  index.php            # Inicio (sitio público)
  servicios.php  nosotros.php  contacto.php
  login.php  logout.php          # Acceso único (usuario o correo) para todos los roles
  registro.php                   # Registro de pacientes (la cuenta nace pendiente)
  paciente/                      # Portal del paciente: inicio, mis citas y pedir cita (historia clínica en construcción)
  panel/                         # Zona del personal (requiere login)
    index.php  pacientes.php  citas.php  solicitudes.php  usuarios.php ...
  errores/             # 403.php, 404.php
  assets/css, assets/js, assets/img
app/                   # Lógica PHP, NO accesible desde el navegador
  core/                # El "motor": db.php, auth.php, helpers.php, errores.php, limites.php
  modelos/             # Lógica de datos: pacientes.php, citas.php, solicitudes.php, cuentas.php
  layout/              # Plantillas: publico_, panel_ y paciente_ (header/footer)
config/
  config.example.php   # Se sube a GitHub (sin contraseñas)
  config.php           # NO se sube (.gitignore)
sql/
  schema.sql           # Tablas
  seed.sql             # Datos ficticios
docs/                  # Plan de trabajo y guías del equipo
deploy.sh              # Actualiza el servidor (git pull + permisos + restorecon)
```

## Zonas de la web
- **Sitio público (sin login):** inicio, servicios y especialidades, nosotros, contacto. Un solo botón de acceso: **Portal del paciente** (lleva al login).
- **Login único:** lo usan todos. Según el rol, redirige a `/paciente/` o a `/panel/`. No hay enlace "Acceso personal".
- **Portal del paciente (cuenta verificada):** ver sus citas, pedir cita y consultar su historia clínica.
- **Panel del personal:** pacientes, citas, solicitudes, cuentas por verificar y usuarios, según el rol.

Nadie puede pedir una cita sin cuenta: la cuenta identifica a quién responde por los datos.

## Roles
- `admin`: todo, incluida la gestión de usuarios.
- `admision`: pacientes, citas y solicitudes; verifica las cuentas de pacientes nuevos.
- `medico`: ver sus citas del día y los datos básicos del paciente; registra las atenciones (historia clínica).
- `paciente`: solo ve y pide lo suyo. Su cuenta nace "pendiente" y admisión la verifica antes de que pueda ver su historial.

## Base de datos (borrador; ajustar si hace falta)
- `areas` (id, nombre) → Admisión, Laboratorio, Sistemas, Medicina General, etc.
- `usuarios` (id, nombre, usuario, correo, password_hash, rol [admin|admision|medico|paciente], area_id, paciente_id, activo, verificado, verificado_por, verificado_en, creado_en). El personal entra con `usuario`; el paciente con `correo` y enlazado a su ficha.
- `pacientes` (id, tipo_documento, numero_documento, nombres, apellidos, correo, fecha_nacimiento, telefono, creado_en). Documento único junto con su tipo.
- `citas` (id, paciente_id, area_id, medico_id, fecha, hora, motivo, estado [pendiente|confirmada|atendida|cancelada], registrado_por, creado_en)
- `solicitudes_cita` (id, paciente_id, area_id, fecha_preferida, mensaje, estado [nueva|atendida|descartada], creado_en). La crea el paciente desde su portal; el nombre, documento y teléfono se leen de su ficha.
- `atenciones` (historia clínica: cita_id, paciente_id, medico_id, diagnostico, tratamiento, notas) e `intentos_acceso` (límite de intentos de login y registro).

Charset `utf8mb4`, motor InnoDB, claves foráneas declaradas.

## Reglas de seguridad (obligatorias)
1. **Siempre PDO con consultas preparadas.** Nunca concatenar datos del usuario en SQL.
2. **Contraseñas con `password_hash()` / `password_verify()`.** Nunca guardar texto plano.
3. **Escapar todo lo que se imprime** con un helper `e()` (`htmlspecialchars`).
4. **Token CSRF** en todos los formularios POST.
5. Sesiones: `session_regenerate_id(true)` al iniciar sesión; cookies `HttpOnly` y `SameSite=Lax`.
6. Toda página de `public/panel/` empieza con `requerir_login()` (y `requerir_rol()` si aplica).
7. **Nunca subir secretos a GitHub** (el repositorio es público): las credenciales van solo en `config/config.php`.
8. Validar en el servidor (PHP) aunque también se valide en JavaScript.
9. Mensajes de error genéricos al usuario; los detalles solo en desarrollo.

## Convenciones
- Interfaz y comentarios en **español**.
- Nombres de archivos en minúsculas con guiones (`solicitar-cita.php`); variables y funciones en `snake_case`.
- Indentación de 4 espacios. Formato PSR-12 en PHP.
- Una página = un archivo en `public/`; la lógica reutilizable va en `app/`.
- Diseño: corporativo y minimalista. Color principal `#0B6E8A`, títulos `#0F2A3D`, fondo `#F3F6F8`, acento `#2BB673`.

## Flujo de trabajo con Git
- `main` = lo que funciona y se publica en el servidor. No hacer commits directos en `main`.
- Una rama por tarea: `feature/login`, `feature/pacientes`, `fix/...`.
- Commits pequeños, en español e imperativo: "Agrega formulario de solicitud de cita".
- Cada rama se une a `main` con un Pull Request revisado por el otro integrante.

## Reparto
- **Joseph:** zona pública (HTML/CSS/JS), plantilla y diseño, login y seguridad (`auth.php`, `helpers.php`), `deploy.sh` y despliegue en Fedora.
- **Alexander:** `schema.sql` y `seed.sql`, backend de pacientes, citas y solicitudes de cita (incluido el portal del paciente: mis citas, pedir cita e historia clínica).

## Cómo probar
1. Iniciar Apache y MySQL (MariaDB) en el panel de XAMPP.
2. Importar `sql/schema.sql` y luego `sql/seed.sql` (phpMyAdmin o la extensión Database Client).
3. Copiar `config/config.example.php` a `config/config.php` y poner las credenciales locales.
4. Abrir `http://localhost/clinica-vida-sana/public`.
