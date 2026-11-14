# Despliegue en Fedora Server

Guía paso a paso para publicar la web en el servidor de producción.

| Dato | Valor |
|---|---|
| Servidor | Fedora Server 44, `srv.clinica.lan` (192.168.10.1) |
| Código | `/var/www/clinica` |
| DocumentRoot | `/var/www/clinica/public` |
| URL | `https://www.clinica.lan` |

> Todos los comandos se escriben **en el servidor**, con un usuario que pueda usar `sudo`.
> Donde aparece `CAMBIAR_ESTO`, pon tu propio valor. **Nunca** subas esos valores a GitHub.

Al final de cada sección hay un **✅ Comprobar**. Corresponde a los ítems de la Fase 3 de [`plan-de-trabajo.md`](plan-de-trabajo.md).

---

## 1. Instalar los programas

Si ya están instalados, `dnf` lo avisa y no pasa nada.

```bash
sudo dnf install -y httpd mod_ssl php php-fpm php-mysqlnd php-mbstring mariadb-server git
sudo systemctl enable --now httpd php-fpm mariadb
```

- `httpd`: Apache. `mod_ssl`: HTTPS.
- `php-mysqlnd`: PDO para MariaDB. `php-mbstring`: el código usa `mb_strlen` y similares.

Abrir el cortafuegos para HTTP y HTTPS:

```bash
sudo firewall-cmd --permanent --add-service=http --add-service=https
sudo firewall-cmd --reload
```

✅ **Comprobar:** `systemctl status httpd mariadb php-fpm` muestra los tres `active (running)`.

---

## 2. Base de datos (ítem 3.2)

### 2.1 Asegurar MariaDB

```bash
sudo mariadb-secure-installation
```

Responde: poner contraseña a root, **sí** a quitar usuarios anónimos, **sí** a quitar acceso remoto de root, **sí** a borrar la base `test`.

### 2.2 Clonar el código (lo necesitamos para importar el SQL)

```bash
sudo git clone https://github.com/josephceverino/clinica-vida-sana.git /var/www/clinica
```

### 2.3 Importar tablas y datos ficticios

```bash
sudo mariadb < /var/www/clinica/sql/schema.sql
sudo mariadb < /var/www/clinica/sql/seed.sql
```

### 2.4 Crear un usuario propio (no `root`)

```bash
sudo mariadb
```

Dentro de MariaDB:

```sql
CREATE USER 'clinica_web'@'localhost' IDENTIFIED BY 'CAMBIAR_ESTO';
GRANT SELECT, INSERT, UPDATE, DELETE ON clinica_vida_sana.* TO 'clinica_web'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

La web solo necesita leer y escribir datos, **no** crear ni borrar tablas. Por eso no tiene `ALL PRIVILEGES`.

✅ **Comprobar:** `mariadb -u clinica_web -p clinica_vida_sana -e "SELECT COUNT(*) FROM usuarios;"` muestra un número.

---

## 3. Cambiar las contraseñas de prueba (ítem 3.8)

El seed trae `Clinica2026!` en todas las cuentas. **No puede quedarse así en el servidor.**

Genera un hash para cada contraseña nueva (repite con cada una):

```bash
php -r 'echo password_hash("CAMBIAR_ESTO", PASSWORD_DEFAULT), PHP_EOL;'
```

Copia el resultado (empieza por `$2y$`) y ponlo en MariaDB (`sudo mariadb clinica_vida_sana`):

```sql
-- Personal: una contraseña distinta para cada uno.
UPDATE usuarios SET password_hash = 'HASH_ADMIN'     WHERE usuario = 'admin';
UPDATE usuarios SET password_hash = 'HASH_ADMISION'  WHERE usuario = 'admision1';
UPDATE usuarios SET password_hash = 'HASH_MEDICO1'   WHERE usuario = 'medico1';
UPDATE usuarios SET password_hash = 'HASH_MEDICO2'   WHERE usuario = 'medico2';

-- Pacientes de demostración: pueden compartir una, pero no la del seed.
UPDATE usuarios SET password_hash = 'HASH_PACIENTES' WHERE rol = 'paciente';
```

> Usa comillas **simples** alrededor del hash. Con comillas dobles, el `$` del hash puede dar problemas.

Guarda las contraseñas nuevas fuera del repositorio (por ejemplo, en un papel para la demo).

✅ **Comprobar:** en la web, `admin` con `Clinica2026!` **no** entra; con la contraseña nueva sí.

---

## 4. Configuración de producción (ítem 3.3)

```bash
cd /var/www/clinica
sudo cp config/config.example.php config/config.php
sudo nano config/config.php
```

Deja estos valores:

```php
'base_url' => '',          // vacío: el DocumentRoot ya es public/
'debug'    => false,       // nunca true en producción
'db' => [
    'host'     => 'localhost',
    'nombre'   => 'clinica_vida_sana',
    'usuario'  => 'clinica_web',
    'password' => 'CAMBIAR_ESTO',   // la del paso 2.4
    'charset'  => 'utf8mb4',
],
```

`config.php` está en `.gitignore`, así que `git pull` nunca lo pisa ni lo sube.

✅ **Comprobar:** `git status` en `/var/www/clinica` **no** muestra `config/config.php`.

---

## 5. Certificado HTTPS

Como `clinica.lan` es un dominio interno, usamos un certificado **autofirmado**. El navegador avisará la primera vez y es normal en una red de laboratorio.

```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/pki/tls/private/clinica.key \
  -out /etc/pki/tls/certs/clinica.crt \
  -subj "/CN=www.clinica.lan" \
  -addext "subjectAltName=DNS:www.clinica.lan,DNS:clinica.lan"
sudo chmod 600 /etc/pki/tls/private/clinica.key
```

> Si el Windows Server tiene una entidad certificadora (AD CS), se puede pedir el certificado ahí. Así los clientes del dominio no ven el aviso.

---

## 6. Apache: VirtualHost (ítems 3.4 y 3.5)

Crea el archivo `/etc/httpd/conf.d/clinica.conf`:

```bash
sudo nano /etc/httpd/conf.d/clinica.conf
```

Con este contenido:

```apache
# --- HTTP: solo redirige a HTTPS ---------------------------------------
<VirtualHost *:80>
    ServerName www.clinica.lan
    ServerAlias clinica.lan
    Redirect permanent / https://www.clinica.lan/
</VirtualHost>

# --- HTTPS: la web -----------------------------------------------------
<VirtualHost *:443>
    ServerName www.clinica.lan
    ServerAlias clinica.lan

    # Solo public/ es visible. app/, config/ y sql/ quedan fuera.
    DocumentRoot /var/www/clinica/public

    SSLEngine on
    SSLCertificateFile    /etc/pki/tls/certs/clinica.crt
    SSLCertificateKeyFile /etc/pki/tls/private/clinica.key

    <Directory /var/www/clinica/public>
        # Sin listado de carpetas. El .htaccess del repo es para XAMPP:
        # aquí se ignora y todo se configura en este archivo.
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    # Páginas de error con el diseño de la web (rutas de producción).
    ErrorDocument 404 /errores/404.php
    ErrorDocument 403 /errores/403.php

    ErrorLog  /var/log/httpd/clinica_error.log
    CustomLog /var/log/httpd/clinica_access.log combined
</VirtualHost>
```

### 6.1 Ocultar versiones

Crea `/etc/httpd/conf.d/seguridad.conf`:

```apache
ServerTokens Prod
ServerSignature Off
TraceEnable Off
```

En `/etc/php.ini` cambia la línea `expose_php` (y revisa `display_errors`):

```ini
expose_php = Off
display_errors = Off
```

### 6.2 Aplicar

```bash
sudo apachectl configtest      # debe decir: Syntax OK
sudo systemctl restart php-fpm httpd
```

✅ **Comprobar:**
- `https://www.clinica.lan` carga el inicio.
- `http://www.clinica.lan` redirige solo a `https://`.
- `https://www.clinica.lan/no-existe` muestra **nuestra** página 404.
- `https://www.clinica.lan/config/config.php`, `/app/core/db.php` y `/sql/schema.sql` dan **404**, porque están fuera de `public/`.
- `curl -kI https://www.clinica.lan` muestra `Server: Apache` sin versión ni `X-Powered-By`.

---

## 7. Permisos y SELinux con `deploy.sh` (ítems 3.6 y 3.7)

La primera vez, dale permiso de ejecución y lánzalo:

```bash
cd /var/www/clinica
sudo chmod 750 deploy.sh
sudo ./deploy.sh
```

El script hace `git pull`, pone dueño `root:apache` con permisos `750`/`640` (sin 777) y aplica `restorecon`.

**A partir de ahora, para publicar cambios basta con:**

```bash
cd /var/www/clinica && sudo ./deploy.sh
```

> `deploy.sh` publica la rama **`main`**. Primero se une el Pull Request y después se despliega.

✅ **Comprobar:**
- `getenforce` dice `Enforcing` y la web funciona.
- `ls -lZ /var/www/clinica/public` muestra `httpd_sys_content_t`.
- `ls -l /var/www/clinica/config/config.php` muestra `-rw-r-----  root apache`.

---

## 8. Acceso desde otro equipo (ítem 3.9)

El cliente Windows tiene que saber que `www.clinica.lan` es `192.168.10.1`. Hay dos formas:

- **DNS del Windows Server (recomendado):** en la zona `clinica.lan`, crea un registro **A** `www` → `192.168.10.1`.
- **Archivo hosts (solo para probar):** en el cliente, edita como administrador `C:\Windows\System32\drivers\etc\hosts` y añade:
  ```
  192.168.10.1   www.clinica.lan clinica.lan
  ```

✅ **Comprobar:** desde el cliente, `ping www.clinica.lan` responde `192.168.10.1` y el navegador abre `https://www.clinica.lan`.

---

## Si algo falla

| Síntoma | Dónde mirar |
|---|---|
| Página en blanco o error 500 | `sudo tail -n 30 /var/log/httpd/clinica_error.log` y `sudo tail -n 30 /var/log/php-fpm/www-error.log` |
| "Permission denied" con SELinux | `sudo ausearch -m avc -ts recent` y luego `sudo restorecon -Rv /var/www/clinica` |
| No conecta a la base | Revisa usuario y contraseña en `config/config.php` y prueba el `mariadb -u clinica_web -p` del paso 2.4 |
| `git pull` se detiene en `deploy.sh` | Alguien cambió archivos a mano en el servidor: `git status` para verlos |
| El navegador no encuentra `www.clinica.lan` | DNS o `hosts` del paso 8 |
