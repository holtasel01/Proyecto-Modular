# Facelog

Sistema de asistencia y horas de servicio social para un laboratorio universitario, basado en reconocimiento facial.

> **¿Instalando el proyecto por primera vez?** → **[INSTALACION.txt](INSTALACION.txt)** (instalar desde cero) y **[COMO_LEVANTAR_EL_PROYECTO.txt](COMO_LEVANTAR_EL_PROYECTO.txt)** (arrancarlo una vez instalado) — también disponibles juntos, con el mismo contenido, en [docs/06-instalacion.md](docs/06-instalacion.md).

- Análisis (Etapa 1): [docs/01-analisis.md](docs/01-analisis.md)
- Diseño (Etapa 2): [docs/02-diseno.md](docs/02-diseno.md)
- Seguridad (Etapa 8): [docs/03-seguridad.md](docs/03-seguridad.md)
- Pruebas (Etapa 9): [docs/04-pruebas.md](docs/04-pruebas.md)
- [Manual técnico (Etapa 10)](docs/05-manual.md) — arquitectura, base de datos, API, reconocimiento facial y mantenimiento
- [Instalación y ejecución](docs/06-instalacion.md) — guía completa paso a paso (instalar de cero + operación día a día); también como texto plano en [INSTALACION.txt](INSTALACION.txt) / [COMO_LEVANTAR_EL_PROYECTO.txt](COMO_LEVANTAR_EL_PROYECTO.txt)
- [Interfaz visual del laboratorio (Etapa adicional)](docs/07-interfaz-laboratorio.md) — ventana con el video de la cámara y retroalimentación en pantalla para el estudiante (antes solo existía por consola)
- [Minería de datos: agrupamiento K-Means (Etapa adicional)](docs/08-mineria-datos.md) — agrupa estudiantes por patrón de asistencia (baja/media/alta actividad) usando K-Means real con scikit-learn
- [Ventana de predicción del estudiante (Etapa adicional)](docs/09-prediccion-estudiante.md) — cada estudiante ve su ritmo actual y una fecha estimada para completar sus 480 horas

## Credenciales de prueba (seed)

Tras `php artisan migrate:fresh --seed`:

| Usuario | Código / Email | Password | Rol |
|---|---|---|---|
| Administrador | `admin@facelog.test` | `password` | `admin` |
| Estudiante de prueba | `218900001` o `estudiante@facelog.test` | `password` | `student` |

El estudiante de prueba ya viene con su cuenta vinculada (no hace falta registrarlo) — el login acepta tanto su matrícula como su correo, indistintamente.

Junto con estos dos usuarios, el seeder crea los `settings` por defecto (umbrales de confianza, ventana anti-duplicado, horas de sesión máximas, meta de horas por defecto — ver `docs/02-diseno.md` §5 y §9).

### Autoregistro de estudiantes

Un estudiante nuevo (que el admin ya dio de alta con su matrícula, pero que todavía no tiene cuenta) puede crear su propia cuenta desde `/registro` en el frontend, con tres datos: **código de estudiante** (la matrícula que le dio el admin), **correo** (institucional o no) y **contraseña**. El login (`/login`) acepta después tanto la matrícula como el correo, junto con la contraseña.

```bash
# vía API, ejemplo con curl (requiere el flujo de cookie CSRF de Sanctum, igual que /login):
POST /api/register
{"matricula": "218999001", "email": "alumno@correo.com", "password": "una-contraseña-de-8+"}
```

Si la matrícula no existe todavía en `students` (el admin no lo ha dado de alta), o si ya tiene una cuenta asociada, el registro se rechaza con un mensaje claro (404 o 409 respectivamente) — ver `app/Http/Controllers/Api/AuthController.php`.

## Estructura del proyecto

```
Proyecto Modular/
├── backend/            # Laravel — API REST, lógica de negocio, PostgreSQL
├── frontend/            # React + TypeScript + Vite — SPA web
├── recognition-app/      # Python — reconocimiento facial (laboratorio) + script de enrolamiento
└── docs/                 # documentación del proyecto
```

## Requisitos previos

| Herramienta | Versión usada en este entorno |
|---|---|
| PHP | 8.2 (XAMPP) |
| Composer | 2.10 (instalado en `%USERPROFILE%\composer`, agregado al PATH del usuario) |
| Node.js / npm | 24 / 11 |
| Python | 3.12 |
| PostgreSQL | 18 (servicio de Windows `postgresql-x64-18`, instalado y corriendo) |

No se usa Docker por ahora (decisión de la Etapa 2): cada herramienta corre nativa en la máquina de desarrollo.

## 1. Backend (Laravel)

```bash
cd backend
composer install          # ya ejecutado en la Etapa 3
cp .env.example .env       # ya existe un .env local, no lo sobrescribas sin revisar
php artisan key:generate   # ya generado
```

La base de datos ya está configurada (ver [Base de datos](#2-base-de-datos-postgresql) abajo) y las migraciones ya corrieron. Para levantar el servidor:

```bash
php artisan serve          # http://localhost:8000
```

Verificación rápida: `GET http://localhost:8000/up` debe responder `200`.

Ya están instalados y configurados en el código: **Laravel Sanctum** (`config/sanctum.php`, trait `HasApiTokens` en `User`, `routes/api.php`, `statefulApi()` en `bootstrap/app.php`), y **CORS** (`config/cors.php`) permitiendo `http://localhost:5173` con `supports_credentials = true`, necesario para la autenticación SPA de Sanctum.

## 2. Base de datos (PostgreSQL)

PostgreSQL 18 ya está instalado y corriendo como servicio, y el rol/base de datos `facelog` ya fueron creados:

```sql
CREATE ROLE facelog WITH LOGIN PASSWORD '...';
CREATE DATABASE facelog OWNER facelog;
```

Las credenciales reales están solo en `backend/.env` (no versionado). Si necesitas recrear el entorno en otra máquina, usa esas mismas dos sentencias con una contraseña propia y actualiza `DB_PASSWORD` en tu `.env` local.

**Nota importante para Windows/XAMPP**: por defecto, `php.ini` trae comentadas (`;extension=...`) las extensiones `pdo_pgsql` y `pgsql`. Sin ellas, Laravel falla con `could not find driver` aunque las credenciales sean correctas. Ya están habilitadas en `C:\xampp\php\php.ini` en este entorno; si configuras el proyecto en otra máquina con XAMPP, revisa que también lo estén.

`php artisan migrate` ya corrió con el esquema completo del dominio (`students`, `face_embeddings`, `devices`, `attendance_events`, `attendance_sessions`, `incidents`, `audit_logs`, `settings` — `docs/02-diseno.md` §3), y `php artisan db:seed` crea el usuario admin y los `settings` por defecto (ver [Credenciales de prueba](#credenciales-de-prueba-seed)).

Para reconstruir todo desde cero: `php artisan migrate:fresh --seed`.

### Qué hace el backend hoy (Etapa 4)

- **Autenticación**: `POST /api/login` (Sanctum SPA, cookie de sesión), `POST /api/logout`, `GET /api/me`.
- **Estudiantes**: CRUD (sin `DELETE` — se desactivan, no se borran) en `/api/students`.
- **Enrolamiento facial** (`/api/students/{id}/face-photo`, `/api/students/{id}/face-profile`): sube una foto, Laravel la guarda de forma transitoria e invoca `recognition-app/scripts/compute_embedding.py` como subproceso; la foto se borra siempre antes de responder. El script ya está implementado de verdad (DeepFace, Etapa 5) — ver la limitación de Windows documentada más abajo antes de probarlo con `php artisan serve`.
- **Asistencia**: `POST /api/attendance/events` (solo el device del laboratorio, token con ability `attendance:write`) aplica `AttendanceService` — umbrales de confianza, ventana anti-duplicado, resolución automática de entrada/salida, generación de incidencias. `GET /api/me/attendance`, `GET /api/me/summary`, `GET /api/attendance/sessions`, `PATCH /api/attendance/sessions/{id}` (corrección manual auditada), `GET /api/lab/status`.
- **Incidencias**, **auditoría**, **configuración** (`/api/settings`, ajustable sin redeploy) y **dispositivos** (`/api/devices` — emite el token de Sanctum del laboratorio, visible solo una vez al crearlo).
- **Sincronización** (`GET /api/sync/face-catalog`, ability `sync`): catálogo de embeddings para el device del laboratorio.
- **Tarea programada** `attendance:close-stale-sessions` (diaria, 02:00): cierra sesiones abandonadas como `inconsistent` y genera la incidencia correspondiente.

Todo esto se probó manualmente con `curl` end-to-end (login, alta de estudiante, alta de device, evento de asistencia con resolución de entrada/duplicado/confianza baja, subida de foto). Las pruebas automatizadas formales son la Etapa 9.

**Corrección de seguridad encontrada y resuelta durante esta etapa**: un token de dispositivo, al compartir el guard `sanctum` con los usuarios, podía autenticarse en rutas pensadas para `User` y provocar un error 500 en vez de un 403 al llegar a una Policy. Se agregó el middleware `user-principal` (`app/Http/Middleware/EnsureUserPrincipal.php`) que lo bloquea limpiamente — ver `docs/02-diseno.md` §7. (La dirección inversa — un `User` accediendo a rutas de device — se encontró y corrigió después, en la Etapa 9, con `EnsureDevicePrincipal`.)

### Pruebas del backend (Etapa 9)

```bash
psql -U postgres -c "CREATE DATABASE facelog_testing OWNER facelog;"   # una sola vez
cd backend
php artisan test
```

59 pruebas de Feature contra una base PostgreSQL real y separada (`facelog_testing`) — no sqlite, ver por qué en [docs/04-pruebas.md](docs/04-pruebas.md) §1. Cubren autenticación (incluido el rate limiting), la separación device/usuario, CRUD y Policies de estudiantes, enrolamiento facial (con `FaceEmbeddingComputer` simulado), toda la lógica de `AttendanceService`, incidencias, dispositivos y configuración.

## 3. Frontend (React + TypeScript + Vite)

```bash
cd frontend
npm install                # ya ejecutado en la Etapa 3
cp .env.example .env        # ya existe, ajusta VITE_API_BASE_URL si cambias el puerto del backend
npm run dev                 # http://localhost:5173
```

`npm run build` ya se verificó que compila sin errores (TypeScript + Vite).

### Qué hace el frontend hoy (Etapa 6)

- **Login** (Sanctum SPA vía cookies) con guard de rutas por rol (`ProtectedRoute`).
- **Panel de estudiante**: perfil, horas acumuladas + barra de progreso, subida de la foto de enrolamiento (una sola vez — deshabilitado después), historial de sesiones de asistencia.
- **Panel de administrador**: estudiantes (listar/buscar/crear/editar/ver detalle + reemplazar foto de enrolamiento), asistencias (quién está en el laboratorio ahora + listado de sesiones con filtro por estado + corrección manual auditada), incidencias (listar/resolver), dispositivos (crear y ver el token una sola vez, revocar), configuración (editar los `settings` del backend).
- Capa `api/` con transformación automática snake_case ↔ camelCase (`docs/02-diseno.md` §8) y manejo de CSRF/cookies de Sanctum.

**Verificado en un navegador real** (Playwright + Chromium, no solo compilado): login como admin y como estudiante, alta de un estudiante desde la UI, navegación por las 5 páginas de administración y las 2 de estudiante, sin errores de consola.

**Bug real encontrado y corregido durante esta verificación**: `AuthContext` llamaba a `GET /me` dos veces al montar (comportamiento normal de `React.StrictMode` en desarrollo — no es exclusivo de este proyecto). Esas dos llamadas casi simultáneas, sin sesión previa, hacían que Laravel creara dos sesiones distintas antes de que el navegador estabilizara una sola cookie, lo que rompía la verificación CSRF del login (`419 CSRF token mismatch`) de forma intermitente. Se corrigió con el patrón estándar de React para este caso (`useRef` como guardia contra el doble efecto) en `src/auth/AuthContext.tsx`.

## 4. recognition-app (Python)

```bash
cd recognition-app
python -m venv venv
venv\Scripts\activate        # Windows (PowerShell: venv\Scripts\Activate.ps1)
pip install -r requirements.txt
copy config.example.env .env
python src\main.py
```

`pip install` ahora sí instala las dependencias de visión artificial (DeepFace, OpenCV, MediaPipe, TensorFlow) — son ~1 GB entre todas, tenlo en cuenta la primera vez. El primer uso real (`compute_embedding.py` o `main.py`) también descarga los pesos del modelo Facenet512 (~95 MB) a `~/.deepface/weights/`.

Para correr las pruebas: `venv\Scripts\python.exe -m pytest tests/ -q` (18 pruebas, ~1 min por la carga de TensorFlow).

### Qué hace `recognition-app` hoy (Etapa 5 + etapa adicional de interfaz)

- `src/recognition/embedding.py`: detección + embedding facial real con DeepFace (modelo Facenet512, detector OpenCV).
- `src/recognition/matcher.py` + `similarity.py`: compara un embedding contra el catálogo sincronizado (similitud coseno).
- `src/liveness/blink.py`: liveness básico por detección de parpadeo (Eye Aspect Ratio con MediaPipe FaceMesh) — bloquea fotos/pantallas estáticas, no un video en reproducción (limitación documentada, `docs/02-diseno.md` §11).
- `src/capture/camera.py`, `src/api_client/client.py`, `src/sync/catalog.py` (con caché en disco y fallback sin conexión), `src/queue/outbox.py` (cola SQLite para reintentos offline).
- `src/ui/feedback.py` (**nuevo**, etapa adicional): ventana con el video de la cámara y un mensaje superpuesto ("Bienvenido, ...", "No reconocido", etc.) — antes la única retroalimentación era texto en la consola. Ver [docs/07-interfaz-laboratorio.md](docs/07-interfaz-laboratorio.md).
- `src/main.py`: loop de reconocimiento en vivo completo (captura → liveness → embedding → comparación → anti-duplicado → reporte a la API o cola local → ventana con el resultado).
- `scripts/compute_embedding.py`: ya no es un placeholder — usa el DeepFace real.

### ⚠️→✅ Limitación de Windows con `php artisan serve` + enrolamiento (resuelta)

Al probar el enrolamiento de punta a punta se descubrió que **`php artisan serve` falla al invocar `compute_embedding.py`** con `OSError: [WinError 10106]`, un problema de Windows donde un proceso hijo que importa `asyncio` (DeepFace/TensorFlow lo hacen de forma transitoria) no puede inicializar su E/S asíncrona porque su proceso padre (`artisan serve`) ya mantiene un socket en escucha. Se confirmó con pruebas aisladas que el mismo script funciona perfecto ejecutado directo o desde `php artisan tinker`, y que el fallo ocurre exactamente al importar `asyncio`, sin relación con el contenido de la imagen — **no es un bug de Facelog**, es una particularidad de Windows/PHP con el servidor de desarrollo embebido. El resto de la API (login, estudiantes, asistencia) nunca se vio afectado porque no invoca subprocesos de Python.

**Resuelto**: se configuró un vhost de Apache (XAMPP) dedicado para Facelog en el puerto **8088**, sin tocar la configuración de nada más que ya estuviera corriendo (se verificó primero qué usaba cada puerto antes de tocar algo). Con esto, el enrolamiento vía web funciona igual que el resto de la API — verificado subiendo una foto real de punta a punta y confirmando el mensaje de error específico de DeepFace (no el genérico).

```bash
# Para levantar Facelog con Apache en vez de artisan serve:
C:\xampp\apache_start.bat
# luego, en frontend/.env: VITE_API_BASE_URL=http://localhost:8088
```

El vhost queda en `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (bloque `Listen 8088` + `VirtualHost *:8088` apuntando a `backend/public`) — persiste entre reinicios de Apache, no hace falta repetir esta configuración.

**El mismo problema apareció de nuevo con la etapa adicional de minería de datos** (`docs/08-mineria-datos.md`): `compute_clusters.py` también falla bajo `artisan serve` por la misma razón (scikit-learn/joblib también terminan importando `asyncio` de forma transitoria). Se confirmó y se resuelve exactamente igual — sirviendo el backend con el mismo Apache de arriba, sin configuración adicional.

## 5. Integración (Etapa 7)

Se conectó y probó el flujo completo **Python → Laravel → PostgreSQL → Web** con datos reales circulando por cada componente real (no solo curl aislado por endpoint como en la Etapa 4):

1. Se sembró un estudiante con un **embedding sintético** (un vector de 512 números, del mismo tamaño que produce Facenet512, pero que no corresponde a ningún rostro real — suficiente para probar el flujo de datos de esta etapa) y un device con su token. La validación con una fotografía real de una persona se hizo por separado, ver Etapa 5 en "Estado actual".
2. `recognition-app/tests/test_integration_live.py` (prueba permanente, se salta sola si no hay backend corriendo) usa el **`ApiClient` y `matcher` reales** de `recognition-app` — no curl, no mocks — para: sincronizar el catálogo real desde Laravel, encontrar la mejor coincidencia, reportar un evento de entrada, reportar un segundo evento (debe ignorarse como duplicado), y confirmar que una falla de red se distingue de un rechazo del servidor.
3. Se simuló también la salida (segundo evento tras la ventana anti-duplicado) y el comando programado `attendance:close-stale-sessions`, y se verificó visualmente en el frontend (Playwright) que el estudiante aparece y desaparece de "en el laboratorio ahora mismo" y que su sesión queda con la duración correcta en el listado — cerrando el ciclo hasta la pantalla.
4. Se probó también el flujo de incidencias (crear vía `CloseStaleSessions`, resolver desde la UI) de punta a punta.

**Bug real encontrado y corregido en esta etapa** (no cosmético — afectaba toda salida y toda corrección manual): Carbon 3 (la librería de fechas que trae Laravel 11) cambió `diffInMinutes()` para devolver un `float` en vez de un `int`. Como `attendance_sessions.duration_minutes` es una columna `integer` de PostgreSQL, cualquier cierre de sesión con una duración no exacta en minutos (es decir, casi siempre) fallaba con un `QueryException` de PostgreSQL. No se detectó antes porque las pruebas de la Etapa 4 no habían llegado a cerrar una sesión real con el tiempo suficiente para producir una fracción de minuto. Corregido en `AttendanceService::closeSession()` y `AttendanceSessionController::update()` — ver `docs/02-diseno.md` §4.

**Actualización posterior**: tanto la foto real como Apache, mencionados como pendientes en versiones anteriores de este README, ya se resolvieron — ver la Etapa 5 más abajo en "Estado actual" y [docs/07-interfaz-laboratorio.md](docs/07-interfaz-laboratorio.md) para la etapa adicional de interfaz visual.

## Estado actual

- ✅ Etapa 1 — Análisis (aprobada)
- ✅ Etapa 2 — Diseño (aprobado)
- ✅ Etapa 3 — Configuración: los tres proyectos están creados, instalados, con la base de datos `facelog` creada y migrada, y se comunican mínimamente (Python ↔ Laravel vía `/up` verificado).
- ✅ Etapa 4 — Backend: modelos, migraciones del dominio, Policies, servicios de negocio (`AttendanceService`, `IncidentService`, `AuditLogger`, `FaceEmbeddingComputer`), controladores y API completa, probados manualmente de punta a punta.
- ✅ Etapa 5 — Reconocimiento facial: implementado y probado (18 pruebas automatizadas: detección real de rostro, contrato de `compute_embedding.py`, comparación de embeddings, cola offline, caché del catálogo, overlay visual). La limitación de Windows con `artisan serve` se resolvió con Apache. Validado de punta a punta con una fotografía real de una persona (enrolamiento vía API, sincronización, reconocimiento con confianza ≈1.0, registro de asistencia) — ya no queda ninguna brecha con datos sintéticos.
- ✅ Etapa adicional — Interfaz visual del laboratorio: ventana con el video de la cámara y retroalimentación en pantalla para el estudiante ("Bienvenido, ...", "No reconocido", etc., con OpenCV — sin agregar dependencias nuevas). Antes solo existía por consola. Ver [docs/07-interfaz-laboratorio.md](docs/07-interfaz-laboratorio.md).
- ✅ Etapa adicional — Minería de datos (K-Means): agrupa estudiantes activos por patrón de asistencia (baja/media/alta actividad) con scikit-learn real, invocado desde Laravel igual que el enrolamiento facial. Misma limitación de Windows con `artisan serve` (resuelta con el mismo Apache). Ver [docs/08-mineria-datos.md](docs/08-mineria-datos.md).
- ✅ Etapa adicional — Predicción del estudiante: cada estudiante ve, en `/prediccion`, su ritmo de horas/semana, qué tan constante es, y una fecha estimada para completar sus 480 horas de meta. Reutiliza el mismo cálculo de patrón de asistencia que el agrupamiento K-Means. Ver [docs/09-prediccion-estudiante.md](docs/09-prediccion-estudiante.md).
- ✅ Etapa 6 — Plataforma web: SPA completa (estudiante + administrador), verificada en un navegador real de punta a punta contra el backend real.
- ✅ Etapa 7 — Integración: flujo completo Python↔Laravel↔PostgreSQL↔Web verificado con datos reales circulando (embedding sintético, ver arriba); encontrado y corregido un bug real de cálculo de duración.
- ✅ Etapa 8 — Seguridad: auditoría completa (ver [docs/03-seguridad.md](docs/03-seguridad.md)). **Hallazgo real corregido**: la API entera no tenía rate limiting (Laravel 11 dejó de registrarlo por defecto) — se agregó y se verificó en vivo (5 intentos de login, el 6º ya da 429). Cobertura de Policies auditada endpoint por endpoint.
- ✅ Etapa 9 — Pruebas: 59 pruebas de PHPUnit (backend, contra PostgreSQL real) + 14 de pytest (recognition-app) + 1 de integración en vivo opcional — ver [docs/04-pruebas.md](docs/04-pruebas.md). **Otro hallazgo real corregido**: escribir la prueba de "un usuario no puede acceder a las rutas del device" reveló que sí podía (ver `docs/03-seguridad.md` §0) — corregido de inmediato con `EnsureDevicePrincipal`.
- ✅ Etapa 10 — Documentación final: [docs/05-manual.md](docs/05-manual.md) reúne instalación, configuración, arquitectura, base de datos, funcionamiento, API, reconocimiento facial, pruebas y mantenimiento en un solo documento.

**Con esto, Facelog tiene un prototipo funcional de punta a punta**, con las diez etapas originales completas y validadas con datos reales, más una etapa adicional (interfaz visual del laboratorio) agregada después a pedido explícito. No queda ninguna brecha de cobertura conocida.
