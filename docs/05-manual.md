# Facelog — Manual técnico (Etapa 10)

> Documento de cierre: reúne en un solo lugar instalación, configuración, arquitectura, base de datos, funcionamiento, API, reconocimiento facial, pruebas y mantenimiento. No repite todo lo que ya está en `docs/01-analisis.md` a `docs/04-pruebas.md` — los referencia para el "por qué" de cada decisión; aquí está el "qué es esto y cómo lo uso hoy".

## Índice

1. [Instalación](#1-instalación)
2. [Configuración](#2-configuración)
3. [Arquitectura](#3-arquitectura)
4. [Base de datos](#4-base-de-datos)
5. [Funcionamiento](#5-funcionamiento)
6. [API](#6-api)
7. [Reconocimiento facial](#7-reconocimiento-facial)
8. [Pruebas](#8-pruebas)
9. [Mantenimiento](#9-mantenimiento)

---

## 1. Instalación

> Guía completa, paso a paso, desde una máquina sin nada instalado: **[docs/06-instalacion.md](06-instalacion.md)**. Aquí solo el resumen de referencia rápida.

| Herramienta | Versión usada | Notas |
|---|---|---|
| PHP | 8.2 | En este entorno viene de XAMPP |
| Composer | 2.10 | Ver nota Windows abajo si no está instalado |
| Node.js / npm | 24 / 11 | |
| Python | 3.12 | |
| PostgreSQL | 18 | Servicio nativo, sin Docker (decisión de la Etapa 2/3) |

```bash
# 1. Backend
cd backend
composer install
cp .env.example .env        # completar DB_PASSWORD (ver §2)
php artisan key:generate
php artisan migrate --seed
php artisan serve            # http://localhost:8000

# 2. Frontend
cd frontend
npm install
cp .env.example .env
npm run dev                  # http://localhost:5173

# 3. recognition-app
cd recognition-app
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
copy config.example.env .env   # completar DEVICE_TOKEN (ver §2)
python src\main.py
```

**Windows/XAMPP — dos extensiones de PHP que vienen deshabilitadas y hay que activar** en `php.ini` (quitar el `;` inicial): `pdo_pgsql`, `pgsql` (sin ellas, Laravel falla con `could not find driver`) y `gd` (solo hace falta para correr las pruebas, ver §8).

**Composer**, si no está instalado, se agrega con el instalador oficial (`https://getcomposer.org/installer`, verificando la firma) — no viene con XAMPP.

---

## 2. Configuración

### `backend/.env` (además de lo estándar de Laravel)

| Variable | Valor en desarrollo | Para qué |
|---|---|---|
| `DB_CONNECTION` / `DB_DATABASE` | `pgsql` / `facelog` | Base de datos principal |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173` | Dominios que pueden usar sesión de cookie (frontend) |
| `SESSION_DOMAIN` | `localhost` | Debe coincidir entre backend y frontend |
| `FACELOG_PYTHON_BIN` | ruta al `python.exe` del venv de `recognition-app` | Usado por `FaceEmbeddingComputer` para invocar `compute_embedding.py` |
| `FACELOG_COMPUTE_EMBEDDING_SCRIPT` | ruta a `recognition-app/scripts/compute_embedding.py` | Idem |

Ambas rutas de Python tienen defaults razonables en `config/facelog.php` asumiendo que `backend/` y `recognition-app/` son carpetas hermanas — solo hace falta tocar el `.env` si se mueve la estructura.

### `frontend/.env`

| Variable | Valor | Para qué |
|---|---|---|
| `VITE_API_BASE_URL` | `http://localhost:8000` | Raíz de la API (el cliente le agrega `/api`) |

### `recognition-app/.env`

| Variable | Para qué |
|---|---|
| `API_BASE_URL`, `DEVICE_TOKEN` | Cómo y con qué credencial hablarle a Laravel (el token se genera desde el panel admin → Dispositivos) |
| `MIN_CONFIDENCE_REJECT`, `DUPLICATE_WINDOW_SECONDS` | Fallback local si la sincronización con la API falla — normalmente los define el admin desde `/api/settings` |
| `CAMERA_INDEX`, `SYNC_INTERVAL_SECONDS`, `LOCAL_QUEUE_DB_PATH`, `CATALOG_CACHE_PATH` | Parámetros operativos del laboratorio |

### Configuración ajustable en tiempo real (tabla `settings`, sin redeploy)

| Clave | Default | Significado |
|---|---|---|
| `min_confidence_reject` | 0.50 | Por debajo de esto, el reconocimiento se rechaza (§7) |
| `min_confidence_trust` | 0.75 | Por debajo de esto (pero sobre el rechazo), se acepta y se genera una incidencia de revisión |
| `duplicate_window_seconds` | 30 | Ventana anti-duplicado |
| `max_session_hours` | 12 | A partir de aquí, una sesión abierta se marca inconsistente (`CloseStaleSessions`) |
| `default_horas_meta` | 480 | Meta de horas por defecto para estudiantes nuevos |

Se editan desde `/api/settings` (panel admin → Configuración) — ver `docs/02-diseno.md` §4 para la lógica que las usa.

---

## 3. Arquitectura

Tres componentes independientes que se comunican por HTTP/REST, con PostgreSQL como única base de datos (solo Laravel la toca directamente):

```
recognition-app (Python) ──HTTP/token──▶ Laravel API ──▶ PostgreSQL
        ▲                                    ▲
        │ (mismo proceso, sin red)           │ HTTP/cookie
   compute_embedding.py               React SPA (frontend)
   (invocado por Laravel como
    subproceso, para enrolar)
```

- **`recognition-app`**: reconoce en tiempo real y reporta eventos de asistencia; nunca toca la base de datos ni recibe fotos por la web.
- **Laravel**: dueño único de la base de datos y de las reglas de negocio (`AttendanceService`, Policies, auditoría).
- **React SPA**: consume la misma API que usaría cualquier otro cliente — no tiene lógica de negocio propia.

Decisiones y alternativas evaluadas: `docs/02-diseno.md` (diseño original) y las correcciones reales encontradas al implementar: `docs/03-seguridad.md` y `docs/04-pruebas.md`.

---

## 4. Base de datos

PostgreSQL, 9 tablas de dominio (además de las de Laravel/Sanctum: `users`, `sessions`, `personal_access_tokens`):

| Tabla | Qué guarda |
|---|---|
| `students` | Perfil de servicio social (matrícula, nombre, meta de horas, estado) |
| `face_embeddings` | Un vector por estudiante (array nativo `float4[]`) — **nunca la foto** |
| `devices` | Equipos del laboratorio autorizados (dueños de sus tokens vía Sanctum `HasApiTokens`) |
| `attendance_events` | Evento crudo de reconocimiento (entrada o salida) |
| `attendance_sessions` | Sesión derivada de un par entrada/salida, con duración |
| `incidents` | Anomalías: confianza baja, duplicado, entrada sin salida, corrección manual |
| `audit_logs` | Quién cambió qué, cuándo, valor anterior y nuevo |
| `settings` | Los parámetros de la tabla de arriba (§2) |

Diagrama entidad-relación completo y el porqué de cada decisión (por ejemplo, eventos crudos + sesiones derivadas en vez de una sola fila editable): `docs/02-diseno.md` §3.

---

## 5. Funcionamiento

**Dar de alta a un estudiante**: el admin lo crea desde el panel (`Estudiantes → Nuevo estudiante`) con matrícula, nombre y carrera. El estudiante todavía no puede ser reconocido por la cámara.

**Enrolamiento facial** (una sola vez, la hace el propio estudiante): inicia sesión en la plataforma web y sube su foto desde su panel. Laravel calcula el embedding invocando a Python y **descarta la foto de inmediato** — nunca se guarda. A partir de ahí, el estudiante no tiene forma de volver a tocar su enrolamiento; solo un admin puede reemplazarlo (`docs/02-diseno.md` §1).

**Entrada/salida diaria**: `recognition-app`, corriendo en la PC del laboratorio, reconoce el rostro contra el catálogo sincronizado, aplica el chequeo de vida (parpadeo) y reporta el evento a Laravel, que decide si es entrada o salida según si el estudiante ya tenía una sesión abierta.

**Consulta**: el estudiante ve sus horas acumuladas y su historial; el admin ve quién está en el laboratorio ahora mismo, corrige registros cuando hace falta (siempre auditado) y resuelve incidencias.

**Estado actual de esto en la práctica** (transparencia, no todo está probado con datos 100% reales todavía):
- El flujo completo (Python → Laravel → PostgreSQL → Web) está verificado de punta a punta, pero con un embedding **sintético** para la parte de reconocimiento — falta una foto real de una persona (Etapa 1, decisión pendiente del usuario) para validar el "camino feliz" de reconocimiento real.
- El enrolamiento vía web necesita que Laravel corra detrás de Apache, no `php artisan serve`, por una limitación de Windows (`docs/02-diseno.md` §1) — **ya configurado y verificado** (vhost en el puerto 8088, ver §9).

---

## 6. API

Prefijo `/api`. Autenticación: cookie de sesión (Sanctum SPA) para usuarios, `Authorization: Bearer <token>` con *ability* específica para el device.

| Método | Ruta | Quién | Qué hace |
|---|---|---|---|
| POST | `/login` | público (con rate limit) | Inicia sesión |
| POST | `/logout` | usuario | Cierra sesión |
| GET | `/me` | usuario | Perfil propio |
| GET/POST/GET/PATCH | `/students`, `/students/{id}` | admin (GET propio también estudiante) | CRUD de estudiantes |
| GET/POST/PUT | `/students/{id}/face-profile`, `/face-photo` | ver §5 | Consultar/subir/reemplazar enrolamiento |
| POST | `/attendance/events` | **device** (`attendance:write`) | Reporta un reconocimiento |
| GET | `/me/attendance`, `/me/summary` | estudiante | Historial y horas propias |
| GET/PATCH | `/attendance/sessions[/{id}]` | admin | Listar / corregir manualmente |
| GET | `/lab/status` | admin | Quién está dentro ahora |
| GET/PATCH | `/incidents[/{id}]` | admin | Listar / resolver |
| GET | `/audit-logs` | admin | Historial de cambios |
| GET/PATCH | `/settings` | admin | Ver/editar parámetros (§2) |
| GET/POST/DELETE | `/devices[/{id}]` | admin | Alta/baja de dispositivos y sus tokens |
| GET | `/sync/face-catalog` | **device** (`sync`) | Catálogo de embeddings para reconocer localmente |

Contrato completo (request/response, validaciones) en `docs/02-diseno.md` §5.

---

## 7. Reconocimiento facial

Pipeline (`recognition-app/src/recognition/`): detección + embedding con **DeepFace** (modelo `Facenet512`, detector `opencv`), comparación por **similitud coseno** contra el catálogo sincronizado, liveness básico por **detección de parpadeo** (Eye Aspect Ratio con landmarks de MediaPipe FaceMesh).

Por qué estas alternativas y no otras (dlib, InsightFace, modelos de anti-spoofing dedicados): `docs/02-diseno.md` §11.

**Limitaciones documentadas a propósito** (no ocultas): el liveness por parpadeo bloquea una foto o pantalla estática, no un video en reproducción. No hay todavía una foto real de una persona para validar la precisión del reconocimiento en este entorno — el pipeline está probado con una imagen sintética "sin rostro" (para el camino de error) y con un embedding sintético (para el flujo de datos, Etapa 7), pero no con una cara real todavía.

---

## 8. Pruebas

```bash
# Backend (necesita una base Postgres de pruebas, una sola vez):
psql -U postgres -c "CREATE DATABASE facelog_testing OWNER facelog;"
cd backend && php artisan test          # 59 pruebas

# recognition-app:
cd recognition-app
venv\Scripts\python.exe -m pytest tests/ -q   # 14 pruebas (~30s por TensorFlow)
```

Qué cubre cada archivo, y las trampas no obvias de probar Laravel+Sanctum que costó tiempo resolver: `docs/04-pruebas.md`.

---

## 9. Mantenimiento

**Estructura del repositorio**: `backend/` (Laravel), `frontend/` (React+TS), `recognition-app/` (Python), `docs/` (esta documentación). Cada uno es un proyecto independiente con su propio gestor de dependencias.

**Agregar un endpoint nuevo en el backend** — el patrón que sigue todo el proyecto:
1. Migración si hace falta una tabla/columna nueva.
2. Modelo (o campo `$fillable` nuevo) + relación si aplica.
3. `FormRequest` en `app/Http/Requests/` con las reglas de validación y el `authorize()` (casi siempre delega a una Policy).
4. Policy en `app/Policies/` si es un recurso nuevo — nunca poner `if ($user->role === 'admin')` suelto en un controlador.
5. Controlador delgado en `app/Http/Controllers/Api/` — la lógica de negocio no trivial va en `app/Services/`, no en el controlador.
6. `Resource` en `app/Http/Resources/` para dar forma a la respuesta (y para no exponer campos sensibles por accidente).
7. Ruta en `routes/api.php`, dentro del grupo correcto (`auth:sanctum` + `user-principal` para usuarios; `auth:sanctum` + `device-principal` + `abilities:*` para el device).
8. Prueba en `tests/Feature/` — al menos el camino feliz y un caso de permisos denegados.

**Agregar un tipo de incidencia nuevo**: agregar el valor al `CHECK` constraint de la migración de `incidents` (o una nueva migración `ALTER TABLE`), usar `IncidentService::create()` desde donde corresponda, y agregar su etiqueta en `frontend/src/pages/admin/AdminIncidents.tsx` (`TYPE_LABELS`).

**Agregar un `setting` nuevo**: agregarlo al arreglo `$defaults` de `DatabaseSeeder`, a las reglas de `UpdateSettingsRequest`, y a `LABELS` en `frontend/src/pages/admin/AdminSettings.tsx`. Se lee en el código con `Setting::get('clave', $default)`.

**Problemas comunes ya resueltos, que no hace falta volver a investigar**:

| Síntoma | Causa | Dónde está la solución |
|---|---|---|
| `could not find driver` con Postgres | `pdo_pgsql`/`pgsql` deshabilitados en `php.ini` | §1 |
| `419 CSRF token mismatch` intermitente en el frontend | Doble llamada a `/me` por `React.StrictMode` — dos sesiones antes de tiempo | `frontend/src/auth/AuthContext.tsx` (guardia con `useRef`) |
| `WinError 10106` al subir una foto con `php artisan serve` | Limitación de Windows: un subproceso que importa `asyncio` falla si el padre mantiene un socket en escucha | `docs/02-diseno.md` §1 — usar Apache, no `artisan serve`, para esa ruta |
| Error de PostgreSQL sobre `duration_minutes` no es entero | Carbon 3 devuelve `diffInMinutes()` como float | Ya corregido en `AttendanceService`/`AttendanceSessionController` — si aparece de nuevo en otro cálculo de duración, envolver en `(int)` |
| Una prueba de PHPUnit no ve el efecto de revocar un token/cerrar sesión en la siguiente petición | `Illuminate\Auth\RequestGuard` cachea el usuario resuelto dentro del mismo test | `docs/04-pruebas.md` §4 — `Auth::forgetGuards()` entre peticiones |

**Apache para el enrolamiento en vivo — ya configurado**: vhost en `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (`Listen 8088` + `VirtualHost *:8088` → `backend/public`), elegido tras verificar qué puertos estaban libres antes de tocar nada (había otro servicio ya corriendo en el 8080, sin relación con Facelog, que no se tocó). Se arranca con `C:\xampp\apache_start.bat`; el frontend debe apuntar `VITE_API_BASE_URL` a `http://localhost:8088` para usarlo en vez de `artisan serve`. Verificado subiendo una foto real de punta a punta.

**Para retomar el trabajo pendiente** (lo único que sigue sin resolver, no inventar que sí): conseguir una foto real de una persona para probar el "camino feliz" del reconocimiento — todo lo probado hasta ahora usó datos sintéticos para el reconocimiento en sí (Etapa 1, decisión 3, sigue en pie).
