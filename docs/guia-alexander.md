# Guía para Alexander

Hola. Esta guía te deja listo para empezar tu parte del proyecto. Léela completa una vez; después úsala como consulta.

Antes de nada, lee [`CLAUDE.md`](../CLAUDE.md): tiene las reglas del proyecto, sobre todo las 9 de seguridad.

---

## 1. Tu parte

| Qué | Archivo(s) | Quién puede entrar |
|---|---|---|
| Revisar y ajustar la base de datos | `sql/schema.sql`, `sql/seed.sql` | — |
| Formulario público "Solicitar cita" | `public/solicitar-cita.php` | Cualquier visitante |
| Solicitudes recibidas | `public/panel/solicitudes.php` | admin, admision |
| Pacientes | `public/panel/pacientes.php` | admin, admision, medico (solo ver) |
| Citas | `public/panel/citas.php` | admin, admision, medico (solo las suyas) |

Hazlas **una por una**, cada una en su propia rama. El orden que recomendamos:
1. Revisar el SQL.
2. `solicitar-cita.php` (es la más parecida a `login.php`, buen punto de partida).
3. `solicitudes.php`.
4. `pacientes.php`.
5. `citas.php`.

---

## 2. Preparar tu PC

### 2.1 Clonar el repo
En la carpeta de XAMPP:
```
cd C:\xampp\htdocs
git clone https://github.com/josephceverino/clinica-vida-sana.git
cd clinica-vida-sana
```

### 2.2 Traer la rama más reciente
Por ahora `main` solo tiene el `CLAUDE.md`. Todo el trabajo está en ramas, así que cámbiate a la última:
```
git fetch
git switch feature/usuarios
```
Esa rama ya incluye todo lo anterior: páginas públicas, login, panel y usuarios.

### 2.3 Iniciar XAMPP
Abre el panel de XAMPP e inicia **Apache** y **MySQL**.

### 2.4 Crear tu `config.php`
Copia `config/config.example.php` y llámalo `config/config.php`. Con XAMPP normal ya sirve tal cual (usuario `root`, sin contraseña). Este archivo **no se sube a GitHub**.

### 2.5 Importar la base de datos
En phpMyAdmin (`http://localhost/phpmyadmin`), pestaña **Importar**: primero `sql/schema.sql` y después `sql/seed.sql`.

> `schema.sql` empieza con `DROP TABLE`, así que reimportarlo **borra los datos**. Está bien en tu PC; en producción no.

### 2.6 Comprobar que funciona
Abre `http://localhost/clinica-vida-sana/public` y entra en `/login.php` con:

| Usuario | Rol | Contraseña |
|---|---|---|
| `admin` | admin | `Clinica2026!` |
| `admision1` | admision | `Clinica2026!` |
| `medico1` | medico | `Clinica2026!` |

---

## 3. Crear tu rama

Una rama por tarea. Parte de `feature/usuarios` (hasta que se unan los primeros Pull Requests):
```
git switch -c feature/solicitar-cita
```
Nunca trabajes directo en `main`.

---

## 4. Cómo es una página del panel

Copia esta estructura. El menú lateral activa el enlace solo cuando el archivo existe.

```php
<?php
// Pacientes (zona privada).
require_once __DIR__ . '/../../app/core/auth.php';

// Regla 6: toda página del panel empieza protegida.
requerir_rol(['admin', 'admision']);

$titulo_pagina = 'Pacientes';
$pagina_activa = 'pacientes.php';   // Para marcar el menú
$pdo = db();

// 1) Si llega un formulario (POST): primero CSRF, luego validar, luego guardar.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verificar()) {
        mensaje_guardar('La sesión expiró. Inténtalo otra vez.', 'error');
        redirigir('/panel/pacientes.php');
    }
    // ... validar los datos en el servidor y guardar con prepare() ...
    mensaje_guardar('Paciente guardado.');
    redirigir('/panel/pacientes.php');   // Evita reenviar el formulario al recargar
}

// 2) Leer los datos que se van a mostrar.
$pacientes = $pdo->query('SELECT id, dni, nombres, apellidos FROM pacientes ORDER BY apellidos')->fetchAll();
$mensaje = mensaje_tomar();

require __DIR__ . '/../../app/layout/panel_header.php';
?>

<header class="panel-titulo">
    <div><h1>Pacientes</h1></div>
</header>

<?php if ($mensaje !== null): ?>
    <p class="aviso aviso-<?= e($mensaje['tipo']) ?>"><?= e($mensaje['texto']) ?></p>
<?php endif; ?>

<!-- Aquí va tu HTML. Todo lo que imprimes lleva e(). -->

<?php require __DIR__ . '/../../app/layout/panel_footer.php'; ?>
```

**Un ejemplo completo para copiar:** `public/panel/usuarios.php`. Tiene lista, formulario, validación, errores y mensajes. Léelo antes de empezar.

### Lo que ya tienes hecho (no lo repitas)

| Función | Para qué sirve |
|---|---|
| `db()` | Conexión PDO. |
| `e($texto)` | Escapa texto antes de imprimirlo. |
| `url('/ruta')` | Arma enlaces correctos en cualquier entorno. |
| `csrf_campo()` / `csrf_verificar()` | Token CSRF en formularios POST. |
| `requerir_login()` / `requerir_rol([...])` | Protegen la página. |
| `usuario_actual()` | Datos del usuario con sesión (`id`, `nombre`, `usuario`, `rol`). |
| `redirigir('/ruta')` | Redirige y detiene el script. |
| `mensaje_guardar()` / `mensaje_tomar()` | Aviso que se muestra una vez tras redirigir. |

### Clases de CSS del panel (en `public/assets/css/panel.css`)
`.caja`, `.tabla`, `.estado estado-pendiente` (también `confirmada`, `atendida`, `cancelada`, `nueva`), `.form-panel`, `.campo`, `.boton`, `.boton-tabla`, `.aviso aviso-ok` / `aviso-error`.

---

## 5. Ejemplo de consulta segura

```php
// BIEN: los datos del usuario van en ? (consulta preparada)
$consulta = $pdo->prepare('SELECT id FROM pacientes WHERE dni = ?');
$consulta->execute([$dni]);

// MAL: nunca pegues datos del usuario dentro del SQL
// $pdo->query("SELECT id FROM pacientes WHERE dni = '$dni'");   // ¡inyección SQL!
```

Para el DNI duplicado, la columna es `UNIQUE`: captura `PDOException` con código `23000` (mira cómo lo hace `usuarios.php`).

---

## 6. Checklist de seguridad (antes de cada commit)

- [ ] La página empieza con `requerir_login()` o `requerir_rol()` (solo las del panel).
- [ ] Todas las consultas con datos del usuario usan `prepare()` y `?`.
- [ ] Todo lo que se imprime pasa por `e()`.
- [ ] Cada formulario POST tiene `csrf_campo()` y la página llama a `csrf_verificar()`.
- [ ] Validas en el servidor (largo, formato, valores permitidos), no solo en el navegador.
- [ ] Mensajes de error genéricos al usuario.
- [ ] Datos ficticios: nada de nombres, DNI o teléfonos reales.
- [ ] No subes `config/config.php` ni contraseñas.

---

## 7. Guardar tu trabajo en Git

```
git status                              # Qué cambió
git add public/panel/pacientes.php      # Elige los archivos
git commit -m "Agrega listado de pacientes"
git push -u origin feature/pacientes    # -u solo la primera vez
```

- Commits pequeños, en español e imperativo ("Agrega...", "Corrige...").
- Cuando termines una tarea, abre un **Pull Request** en GitHub y pide revisión a Joseph.
- Mientras los primeros PR no estén unidos a `main`, tu PR debe apuntar a `feature/usuarios` (en el desplegable **base**). Joseph te avisa cuando eso cambie.

---

## 8. Si algo falla

| Síntoma | Qué mirar |
|---|---|
| Pantalla en blanco | Revisa que `debug` sea `true` en tu `config.php` y mira el log de Apache en XAMPP. |
| "Error de conexión" | ¿MySQL está en verde? ¿El nombre de la base es `clinica_vida_sana`? |
| El menú no se activa | El archivo debe llamarse exactamente igual que en el menú (`pacientes.php`) y estar en `public/panel/`. |
| Te saca al login | Tu usuario está inactivo o no tienes el rol que pide `requerir_rol()`. |
| Error 403 | Entraste con un rol sin permiso (es lo esperado). |

Cualquier duda, pregúntale a Joseph antes de inventar una solución: es mejor entender el porqué.
