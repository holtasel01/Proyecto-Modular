# Facelog — Manual de instalación y ejecución

> Guía paso a paso para instalar el proyecto **desde cero** en una máquina nueva y dejarlo corriendo. Si el proyecto ya está instalado y solo quieres volver a arrancarlo, salta directo a la [§9 — Día a día](#9-día-a-día-una-vez-instalado). Para entender qué hace cada cosa (arquitectura, API, etc.), ver [docs/05-manual.md](05-manual.md).

## Qué se va a instalar

Tres proyectos independientes + una base de datos:

```
Proyecto Modular/
├── backend/            # Laravel — API REST (PHP)
├── frontend/            # React + TypeScript — plataforma web
├── recognition-app/      # Python — reconocimiento facial (laboratorio)
└── docs/                 # esta documentación
```

---

## 1. Requisitos previos

Instalar (u obtener acceso a) lo siguiente antes de empezar:

| Herramienta | Versión mínima recomendada | De dónde |
|---|---|---|
| PHP | 8.2 | Recomendado: [XAMPP](https://www.apachefriends.org/) (trae PHP + Apache juntos, en Windows) |
| Composer | 2.x | https://getcomposer.org (ver §2.3 si no está instalado en Windows) |
| Node.js + npm | Node 20+ | https://nodejs.org |
| Python | 3.12 | https://www.python.org (en Windows: marcar "Add to PATH" al instalar) |
| PostgreSQL | 16+ | https://www.postgresql.org/download/ (el `.exe` de Windows instala también `psql` y, opcionalmente, pgAdmin) |

Verificar que todo responde antes de continuar:

```bash
php -v
composer -V
node -v
npm -v
python --version
psql --version
```

Si alguno falla, resolverlo antes de seguir — el resto de esta guía asume que los seis comandos de arriba funcionan.

---

## 2. Preparar PHP y Composer

### 2.1 Habilitar extensiones de PHP

Editar el `php.ini` que usa tu PHP (`php --ini` muestra la ruta exacta; con XAMPP suele ser `C:\xampp\php\php.ini`) y quitar el `;` inicial de estas líneas (si no existen, agregarlas en la sección `[PHP]`/de extensiones):

```ini
extension=pdo_pgsql
extension=pgsql
extension=gd
```

- `pdo_pgsql`/`pgsql`: sin ellas, Laravel falla con `could not find driver` al conectar a PostgreSQL.
- `gd`: solo hace falta para correr las pruebas automatizadas del backend (§8).

Reiniciar cualquier servidor PHP que ya estuviera corriendo para que tome el cambio.

### 2.2 Verificar que se activaron

```bash
php -m | grep -i "pgsql\|gd"
```

Debe listar `gd`, `pdo_pgsql` y `pgsql`.

### 2.3 Instalar Composer (si `composer -V` falló en el paso 1)

En Windows, sin el instalador gráfico oficial:

```powershell
$dir = "$HOME\composer"
New-Item -ItemType Directory -Force -Path $dir
Invoke-WebRequest -Uri "https://getcomposer.org/installer" -OutFile "$dir\composer-setup.php"
php "$dir\composer-setup.php" --install-dir="$dir" --filename=composer.phar
@"
@echo off
php "%~dp0composer.phar" %*
"@ | Out-File -FilePath "$dir\composer.bat" -Encoding ascii

# agregarlo al PATH del usuario (efecto en terminales nuevas):
$userPath = [Environment]::GetEnvironmentVariable("Path", "User")
[Environment]::SetEnvironmentVariable("Path", "$userPath;$dir", "User")
```

Abrir una terminal nueva y confirmar `composer -V`.

---

## 3. Preparar PostgreSQL

Con PostgreSQL instalado y su servicio corriendo (en Windows, se instala como servicio y arranca solo), crear el rol y la base de datos de la aplicación. Necesitas la contraseña del superusuario `postgres` que se definió al instalarlo.

```bash
psql -U postgres -h 127.0.0.1
```

Dentro de `psql`:

```sql
CREATE ROLE facelog WITH LOGIN PASSWORD 'elige-una-contraseña';
CREATE DATABASE facelog OWNER facelog;
CREATE DATABASE facelog_testing OWNER facelog;  -- para las pruebas automatizadas, §8
\q
```

Anota la contraseña que elegiste — la vas a necesitar en el paso 4.

---

## 4. Backend (Laravel)

```bash
cd backend
composer install
copy .env.example .env          # Windows (macOS/Linux: cp .env.example .env)
php artisan key:generate
```

Editar `backend/.env` y completar:

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=facelog
DB_USERNAME=facelog
DB_PASSWORD=la-contraseña-del-paso-3
```

Luego:

```bash
php artisan migrate --seed
```

Esto crea todas las tablas (`docs/02-diseno.md` §3) y siembra dos usuarios de prueba:

| Código / Email | Password | Rol |
|---|---|---|
| `admin@facelog.test` | `password` | admin |
| `218900001` o `estudiante@facelog.test` | `password` | student |

El estudiante de prueba ya tiene su cuenta vinculada — el login acepta tanto su matrícula como su correo.

**Verificar que funciona:**

```bash
php artisan serve
```

Abrir `http://localhost:8000/up` en el navegador — debe mostrar "Application up". Dejar corriendo esta terminal.

> **Nota**: `php artisan serve` sirve para todo el backend **excepto** subir la foto de enrolamiento y la página de Analítica/K-Means (ambas necesitan Apache, ver §7) — es una limitación de Windows, no del proyecto (`docs/02-diseno.md` §1, `docs/08-mineria-datos.md` §4).

---

## 5. Frontend (React + TypeScript)

En **otra terminal** (dejar el backend corriendo en la anterior):

```bash
cd frontend
npm install
copy .env.example .env
npm run dev
```

Abrir `http://localhost:5173` — debe mostrar la pantalla de login de Facelog. Iniciar sesión con `admin@facelog.test` / `password` para confirmar que el frontend ya habla con el backend.

También se puede probar el **autoregistro** desde el enlace "Regístrate" (`/registro`): un estudiante que el admin ya dio de alta (con su matrícula) puede crear su propia cuenta con matrícula + correo + contraseña. Sin ese estudiante pre-existente, el registro se rechaza con un 404.

---

## 6. recognition-app (Python)

En **otra terminal**:

```bash
cd recognition-app
python -m venv venv
venv\Scripts\activate            # Windows. macOS/Linux: source venv/bin/activate
pip install -r requirements.txt
```

`pip install` descarga ~1 GB (DeepFace, TensorFlow, OpenCV, MediaPipe) — puede tardar varios minutos la primera vez.

```bash
copy config.example.env .env
```

`recognition-app/.env` necesita un **token de dispositivo**, que todavía no existe — se genera desde el panel admin:

1. En el navegador (con el frontend corriendo), entrar como admin → **Dispositivos** → **Nuevo dispositivo del laboratorio**.
2. Copiar el token que aparece (solo se muestra una vez).
3. Pegarlo en `recognition-app/.env` como `DEVICE_TOKEN=...`.

**Verificar la instalación:**

```bash
python src\main.py
```

Si el `DEVICE_TOKEN` es inválido, falla de inmediato con un error HTTP 401/403 al sincronizar el catálogo — eso sí es un problema real, revisar el token.

Si es válido, imprime `Facelog recognition-app - reconocimiento en vivo. 'q'/Esc o Ctrl+C para salir.` y luego intenta abrir la cámara:

- **Con cámara conectada**: se abre una ventana ("Facelog - Laboratorio") con el video en vivo y, en una barra inferior, el resultado del último intento de reconocimiento ("No reconocido", "Bienvenido, `<matrícula>`", etc. — detalle completo en [docs/07-interfaz-laboratorio.md](07-interfaz-laboratorio.md)). Cerrar con `q`, `Esc`, o `Ctrl+C` en la terminal.
- **Sin cámara conectada**: se cae con un traceback de Python ("No se pudo abrir la cámara...") antes de llegar a abrir la ventana — es normal y no significa que la instalación esté mal; solo confirma que ya pasó la validación del token (ver §8).

---

## 7. Apache, para el enrolamiento facial y la analítica (K-Means)

`php artisan serve` no puede procesar la subida de foto de enrolamiento, ni el cálculo de agrupamiento de estudiantes (página "Analítica" del panel admin), en Windows (`docs/02-diseno.md` §1, `docs/08-mineria-datos.md` §4) — ambas invocan un script de Python como subproceso que termina importando `asyncio` de forma transitoria. Para esas dos funciones, servir el backend con Apache en vez de `artisan serve`:

1. Confirmar que XAMPP incluye Apache (se instala junto con PHP si usaste XAMPP en el paso 1).
2. Agregar al final de `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (ajustando la ruta a donde tengas el proyecto):

   ```apache
   Listen 8088
   <VirtualHost *:8088>
       DocumentRoot "C:/ruta/completa/al/proyecto/backend/public"
       <Directory "C:/ruta/completa/al/proyecto/backend/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

   Antes de agregar el `Listen`, confirmar que el puerto elegido esté libre (`netstat -ano | findstr :8088` no debe mostrar nada) — si tu máquina ya usa el 8088 para otra cosa, elegir otro puerto libre.

3. Arrancar Apache: `C:\xampp\apache_start.bat` (o el botón "Start" de Apache en el panel de control de XAMPP).
4. Cambiar `frontend/.env`: `VITE_API_BASE_URL=http://localhost:8088`, y reiniciar `npm run dev`.

Con esto, subir una foto de enrolamiento desde el panel del estudiante/admin, y usar la página "Analítica" del panel admin, ya funcionan. El resto de los pasos de esta guía (§4–6) siguen funcionando igual con `artisan serve` normal — Apache solo hace falta para estas dos funciones puntuales.

---

## 8. Verificación final (checklist de que todo quedó bien instalado)

- [ ] `http://localhost:8000/up` (o `:8088` si usas Apache) responde "Application up".
- [ ] `http://localhost:5173` muestra el login y puedes entrar como `admin@facelog.test`.
- [ ] También puedes entrar como el estudiante de prueba (`218900001` o `estudiante@facelog.test` / `password`).
- [ ] Puedes crear un estudiante desde **Estudiantes → Nuevo estudiante**.
- [ ] Puedes registrar una cuenta nueva desde `/registro` usando la matrícula de ese estudiante recién creado.
- [ ] Puedes crear un dispositivo desde **Dispositivos** y copiar su token.
- [ ] `recognition-app`: `python src\main.py` llega al mensaje "reconocimiento en vivo" sin error HTTP 401/403 (el fallo por falta de cámara al final es normal, ver §6). Con cámara conectada, además debe abrirse la ventana "Facelog - Laboratorio".
- [ ] Pruebas del backend: `cd backend && php artisan test` → deben pasar (necesita la base `facelog_testing` del paso 3).
- [ ] Pruebas de `recognition-app`: `venv\Scripts\python.exe -m pytest tests/ -q` → deben pasar.
- [ ] (Si configuraste Apache, §7) subir una foto de enrolamiento desde el panel funciona sin error 500.
- [ ] (Si configuraste Apache, y hay al menos 3 estudiantes activos con alguna sesión) la página "Analítica" del panel admin muestra la tabla y el gráfico de agrupamiento sin error.

Si algo de esto falla, revisar la tabla de problemas comunes en `docs/05-manual.md` §9 antes de seguir.

---

## 9. Día a día (una vez instalado)

Ya no hace falta repetir nada de lo anterior — solo arrancar los servicios cada vez que quieras trabajar:

```bash
# Terminal 1
cd backend && php artisan serve
# (o, si necesitas subir fotos de enrolamiento: C:\xampp\apache_start.bat, y no uses esta terminal)

# Terminal 2
cd frontend && npm run dev

# Terminal 3 (solo si vas a probar reconocimiento con la cámara del laboratorio)
cd recognition-app && venv\Scripts\activate && python src\main.py
```

Para detener: `Ctrl+C` en cada terminal (`recognition-app` también se puede cerrar con `q`/`Esc` sobre su ventana; Apache: `C:\xampp\apache_stop.bat` o el botón "Stop" del panel de XAMPP).
