# Facelog — Etapa 8: Seguridad

> Revisión de lo ya construido (Etapas 3–7) contra los riesgos identificados en `docs/01-analisis.md` §14 y las decisiones de autenticación/autorización de `docs/02-diseno.md` §7. No es una reescritura: es una auditoría que corrige lo que encontró mal y confirma explícitamente lo que ya estaba bien, para no dar una falsa sensación de que "ya se revisó todo" sin decir qué se revisó.

> **Actualización (Etapa 9)**: al escribir las pruebas automatizadas de las rutas de device se encontró una vulnerabilidad real que esta auditoría no había detectado. Se documenta en la sección 0 en vez de reescribir la sección 3 como si siempre hubiera estado bien — por transparencia sobre qué se revisó y cuándo.

---

## 0. Vulnerabilidad real encontrada en la Etapa 9 (post-auditoría) y corregida

**Cualquier usuario autenticado por la web (admin o estudiante) podía llamar a `POST /api/attendance/events` y `GET /api/sync/face-catalog` como si fuera el dispositivo del laboratorio**, falsificando entradas/salidas de asistencia para cualquier estudiante o descargando el catálogo completo de embeddings.

**Causa**: esas rutas estaban protegidas con `auth:sanctum` + `abilities:*`, y la Etapa 8 asumió que eso bastaba porque los tokens de `Device` tienen abilities acotadas (`sync`, `attendance:write`). Lo que la auditoría pasó por alto es que **Sanctum le asigna a toda sesión SPA autenticada (cookie, no token) un `TransientToken` que responde que sí a *cualquier* ability** — es el mecanismo con el que Sanctum deja usar la API de primera parte sin tener que emitir un token real. Como consecuencia, `abilities:sync`/`abilities:attendance:write` nunca rechazaban a un `User` logueado por la web; solo rechazaban a un `Device` al que le faltara esa ability específica, que no era el problema real.

**Cómo se detectó**: al preparar las pruebas de la Etapa 9 para "un `User` no puede acceder a las rutas de device", se probó manualmente primero (antes de automatizarla) con `curl`, sesión de admin autenticado, y la petición **no fue rechazada** — llegó hasta el controlador (y ahí falló por una razón distinta, intentar actualizar una columna que no existe en `users`, lo cual expuso el problema de raíz).

**Corrección**: se agregó `App\Http\Middleware\EnsureDevicePrincipal` (la contraparte exacta de `EnsureUserPrincipal` de la Etapa 4, que resuelve el problema simétrico) — exige `$request->user() instanceof Device` **antes** de que la petición llegue a los `abilities:*`. Se aplicó a ambas rutas de device. Verificado en vivo: la misma sesión de admin que antes llegaba al controlador ahora recibe `403` inmediato; el flujo real del device (token con abilities) sigue funcionando (`200`/`201`) sin cambios.

**Lección para la Etapa 9 en general**: esto confirma por qué "crear pruebas" no es un trámite — la prueba que se estaba a punto de escribir (¿puede un usuario acceder a una ruta de device?) fue justamente la que reveló el hallazgo, antes incluso de automatizarla. El resto de la Etapa 9 se hace con esa misma disciplina: probar primero el camino que "no debería funcionar", no solo el camino feliz.

---

## 1. Hallazgo real y corregido: no había rate limiting en toda la API

**El más importante de esta etapa.** Laravel 11, al quitar `RouteServiceProvider`, dejó de registrar automáticamente el limitador `api` que en versiones anteriores protegía toda la API por defecto. Como nunca se llamó a `$middleware->throttleApi()` ni se definió `RateLimiter::for('api', ...)`, **ningún endpoint tenía límite de peticiones** — ni siquiera `/api/login`, que quedaba completamente abierto a fuerza bruta.

**Corregido:**
- `app/Providers/AppServiceProvider.php`: se registran dos limitadores.
  - `api`: 120 solicitudes/minuto por usuario autenticado (o por IP si aún no hay sesión) — límite general contra abuso/DoS básico.
  - `login`: 5 intentos/minuto por combinación `email + IP` — específico para frenar fuerza bruta sin bloquear a otros usuarios que compartan la misma IP (relevante porque varios estudiantes podrían usar la red del laboratorio).
- `bootstrap/app.php`: `$middleware->throttleApi()` activa el límite `api` sobre **todas** las rutas de `routes/api.php` (login, estudiantes, asistencia, dispositivos, todo).
- `routes/api.php`: `/login` además lleva `throttle:login` explícito.

**Verificado en vivo** (no solo leído en el código): 5 intentos de login con credenciales incorrectas devolvieron `422` como se espera; el 6º y 7º devolvieron `429 Too Many Requests` con cabeceras `X-RateLimit-*` y `Retry-After` correctas.

**Decisión de diseño dentro de la corrección**: el límite de `login` cuenta *todos* los intentos (correctos o no), no solo los fallidos. Es más simple que llevar un contador de fallos aparte (lo que haría, por ejemplo, Laravel Fortify) y es suficiente para el nivel de riesgo de este proyecto — se documenta la simplificación en vez de ocultarla.

---

## 2. Autenticación y sesiones — confirmado correcto, con matices

- **Sanctum SPA** para usuarios (cookie de sesión) y **Personal Access Tokens con abilities** para el device del laboratorio — ya implementado desde la Etapa 4, revisado de nuevo aquí sin cambios porque sigue siendo el diseño correcto.
- **`EnsureUserPrincipal`** (Etapa 4) sigue siendo la barrera correcta para que un `Device` no toque rutas de usuario — se re-verificó que cubre **todas** las rutas de usuario, incluidas `/me/attendance` y `/me/summary` que no se habían revisado explícitamente antes. Su contraparte simétrica, **`EnsureDevicePrincipal`** (para que un `User` no toque rutas de device), faltaba — ver §0, corregido en la Etapa 9.
- **Expiración de tokens de dispositivo**: `config/sanctum.php` tiene `expiration = null` (no expiran solos). Es una decisión consciente, no un descuido: un device representa hardware del laboratorio, no una sesión de usuario — su ciclo de vida es "hasta que un admin lo revoque" (`DELETE /api/devices/{id}`, ya implementado), no "hasta que expire un timer". Revocar un device borra sus tokens de inmediato (`$device->tokens()->delete()`), verificado en la Etapa 4.
- **Contraseñas**: hash con bcrypt (`BCRYPT_ROUNDS=12`, default de Laravel), nunca se devuelven en ninguna respuesta (`User::$hidden` incluye `password`).

---

## 3. Autorización (Policies) — auditoría de cobertura

Se revisó, controlador por controlador, que **ninguna ruta de escritura o de datos sensibles carezca de una comprobación explícita** (Policy en el controlador, o `authorize()` dentro del FormRequest):

| Controlador | Acción | Dónde se autoriza |
|---|---|---|
| `StudentController` | index/show/store/update | `$this->authorize()` o `StoreStudentRequest`/`UpdateStudentRequest` |
| `StudentFacePhotoController` | show/store/update | `StudentPolicy::view` / `uploadFacePhoto` / `replaceFacePhoto` (vía `UploadFacePhotoRequest`) |
| `AttendanceSessionController` | index/update/labStatus | `AttendanceSessionPolicy` (`myAttendance`/`mySummary` no necesitan Policy: usan `$request->user()->student()`, no un parámetro de ruta, así que no hay forma de pedir los datos de otro estudiante) |
| `IncidentController` | index/update | `IncidentPolicy` |
| `SettingController` | index/update | `SettingPolicy` |
| `DeviceController` | index/store/destroy | `DevicePolicy` |
| `AuditLogController` | index | chequeo directo `isAdmin()` (no hay Policy de `User`, ver nota abajo) |

**Nota de consistencia** (menor, no se cambia): `AuditLogController` usa `abort_unless($request->user()->isAdmin(), 403)` en vez de una Policy formal, porque el recurso "audit logs" no tiene un modelo propio al que atar una Policy de forma natural (son un log transversal, no pertenecen a un dueño). Es una excepción deliberada al patrón, no un descuido — se documenta para que quede claro que se consideró.

Todas las Policies siguen resolviendo `User` como tipo — combinado con `EnsureUserPrincipal` (§2), un token de `Device` nunca llega a ejecutar código de Policy con un objeto del tipo equivocado.

---

## 4. Protección de la API contra ataques comunes

| Riesgo | Estado | Detalle |
|---|---|---|
| Fuerza bruta en login | ✅ Corregido en esta etapa | §1 |
| Abuso/DoS genérico de la API | ✅ Corregido en esta etapa | límite `api` global, §1 |
| CSRF | ✅ Ya cubierto (Etapa 6) | Sanctum SPA + `X-XSRF-TOKEN`; se encontró y corrigió además una condición de carrera de `React.StrictMode` que rompía esto intermitentemente (ver Etapa 6) |
| Inyección SQL | ✅ Sin hallazgos | Toda consulta usa Eloquent/Query Builder con bindings — incluida la búsqueda con `ilike` en `StudentController`, que interpola el patrón `%...%` en PHP pero lo pasa como *valor* con binding, nunca como SQL crudo |
| Inyección de comandos | ✅ Sin hallazgos | `FaceEmbeddingComputer` invoca Python con `Process::run([...])` en forma de **array** (no string de shell), por lo que Symfony Process escapa cada argumento — no hay forma de inyectar comandos desde una ruta de archivo |
| XSS | ✅ Sin hallazgos | El frontend es React puro; no se usa `dangerouslySetInnerHTML` ni `eval` en ningún componente (verificado por búsqueda en todo `frontend/src`) |
| Mass assignment | ✅ Sin hallazgos | Cada modelo declara `$fillable` explícito; no existe ningún endpoint público que permita a un usuario asignarse `role` (los `User` solo se crean vía seeder/tinker) |
| Subida de archivos maliciosos | ✅ Sin hallazgos | `UploadFacePhotoRequest` valida `image` (verifica que el archivo sea una imagen real, no solo la extensión) + `mimes:jpg,jpeg,png` + `max:5120` (5 MB) |
| Exposición de datos biométricos | ✅ Sin hallazgos nuevos | El vector nunca aparece en ningún `Resource` salvo `GET /sync/face-catalog`, exclusivo del device (ability `sync`) — ver también §5 |
| Divulgación de información en errores | ✅ Correcto por defecto | Sin manejo custom en `withExceptions()`, así que Laravel usa su comportamiento estándar: con `APP_DEBUG=false` (obligatorio en producción, ver §6) las respuestas de error no incluyen trazas ni rutas del servidor |

---

## 5. Datos biométricos — riesgo residual aceptado (no nuevo, reafirmado)

El catálogo de embeddings viaja completo al device del laboratorio en cada sincronización (`GET /sync/face-catalog`), porque el diseño (Etapa 1 §11, Etapa 2 §10) decidió comparar localmente en Python en vez de mandar cada frame al servidor. Esto significa que un token de device comprometido podría descargar los embeddings de todos los estudiantes activos.

No se cambia el diseño en esta etapa — sigue siendo la decisión correcta para evitar depender de la red en cada reconocimiento — pero se reafirma explícitamente el riesgo y sus mitigaciones ya existentes: abilities acotadas del token (`sync`, nada más), revocación inmediata desde el panel admin, y que los embeddings nunca se combinan con nombre/matrícula de forma que un archivo filtrado por sí solo identifique a alguien fuera del propio sistema (el catálogo si incluye `matricula`, así que esto es una identificación directa — se documenta el matiz: **el riesgo es real si el token se filtra**, no se minimiza).

**No implementado, considerado y descartado por desproporcionado para el alcance**: cifrado en reposo del vector en PostgreSQL. Requeriría abandonar el array nativo de Postgres (Etapa 2 §3) por un campo de texto cifrado, complicando la comparación y la sincronización sin una amenaza adicional real para un proyecto universitario de un solo laboratorio (la base de datos ya está protegida por autenticación de PostgreSQL y no expuesta a la red pública). Si el proyecto escalara a producción real con datos de estudiantes reales, esto debería reconsiderarse.

---

## 6. Checklist antes de un despliegue real (no aplica al entorno de desarrollo actual)

Ninguno de estos aplica mientras el proyecto corra solo en `localhost` para la demo universitaria, pero quedan documentados para no olvidarlos si el proyecto se despliega de verdad:

- [ ] `APP_DEBUG=false` en `.env` de producción.
- [ ] `SESSION_SECURE_COOKIE=true` y servir todo por HTTPS (hoy es `null`/`false`, correcto solo porque se sirve por HTTP en desarrollo).
- [ ] Actualizar `SANCTUM_STATEFUL_DOMAINS` y `config/cors.php` (`allowed_origins`) al dominio real, quitando `localhost:5173`.
- [ ] Revisar si 120 req/min sigue siendo un límite razonable con tráfico real (hoy es una estimación para el alcance de un laboratorio).
- [ ] Rotar `APP_KEY` y las credenciales de PostgreSQL (hoy son valores triviales de desarrollo, documentados como tales en el README).

---

## 7. Lo que se dejó exactamente igual (por transparencia)

Para que quede explícito qué NO se tocó en esta etapa porque ya estaba bien: los Policies y su cobertura por rol (Etapa 4), el manejo de tokens de Sanctum y sus abilities (Etapa 4), el borrado inmediato de fotos de enrolamiento tras calcular el embedding (Etapa 2 §1, Etapa 4), la auditoría de reemplazos de foto y correcciones manuales (Etapa 4), y la validación de todos los `FormRequest` existentes (Etapa 4). Se revisaron de nuevo como parte de esta auditoría y no se encontraron problemas adicionales.
