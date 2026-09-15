# Facelog — Etapa 9: Pruebas

> Backend: 59 pruebas de PHPUnit (`backend/tests/Feature/`) contra una base de datos PostgreSQL real y separada (no sqlite — ver §1). `recognition-app`: 14 pruebas de pytest más una de integración en vivo opcional (Etapa 5/7). No se listan todas las pruebas una por una aquí; este documento explica **cómo correrlas, qué cubren, y las trampas no obvias que aparecieron al escribirlas** — varias de las cuales revelaron bugs reales, no solo confirmaron que el código ya andaba bien.
>
> **Actualización (etapa adicional, posterior)**: `docs/07-interfaz-laboratorio.md` agregó 4 pruebas más (`tests/test_feedback_overlay.py`), llevando `recognition-app` a 18 pruebas de pytest en total. El resto de este documento describe el estado al cierre de la Etapa 9 y sigue siendo válido para esas 14 + 59.

---

## 1. Por qué las pruebas de Laravel usan PostgreSQL y no sqlite

El `phpunit.xml` que trae Laravel por defecto apunta a sqlite en memoria. Se cambió a una base Postgres dedicada (`facelog_testing`, mismo rol `facelog`) porque varias migraciones (`docs/02-diseno.md` §3) usan `CHECK` constraints agregados con `ALTER TABLE ... ADD CONSTRAINT` y un índice parcial (`WHERE status = 'open'`) — sqlite no soporta ninguna de las dos cosas vía `ALTER TABLE`, así que las migraciones fallarían al correr las pruebas.

```bash
# una sola vez, para crear la base de pruebas:
psql -U postgres -c "CREATE DATABASE facelog_testing OWNER facelog;"

# correr toda la suite:
cd backend && php artisan test
```

`tests/TestCase.php` ya trae `RefreshDatabase` para toda la suite, así que cada prueba corre en una sesión limpia.

---

## 2. Qué cubre cada grupo de pruebas (backend)

| Archivo | Cubre |
|---|---|
| `Auth/LoginTest.php` | login válido/inválido, email desconocido (mismo código que contraseña incorrecta, no revela si existe), **throttling real** (5 intentos → 429), logout, `/me`, rutas protegidas sin sesión |
| `Auth/DevicePrincipalSeparationTest.php` | regresión de la vulnerabilidad de la Etapa 9 (ver §3) + que un device sin la ability correcta no pase |
| `Students/StudentManagementTest.php` | CRUD, búsqueda, Policy (estudiante no ve a otro, no crea), matrícula única |
| `Students/FacePhotoTest.php` | subida única, conflicto en el segundo intento, estudiante no puede reemplazar, admin sí y queda auditado, error de cómputo → 422 limpio, archivo no-imagen rechazado |
| `Attendance/AttendanceServiceTest.php` | entrada, salida con duración correcta, duplicado ignorado, confianza baja rechazada, confianza media → incidencia, duplicados repetidos → incidencia, `student_id` inexistente, confianza fuera de rango |
| `Attendance/ManualCorrectionAndSummaryTest.php` | corrección manual (auditada + incidencia), corrección sin motivo rechazada, estudiante no puede corregir, cálculo de horas acumuladas/progreso, historial propio únicamente, estado del laboratorio |
| `Incidents/IncidentTest.php` | `CloseStaleSessions` marca y no marca según corresponda, listar/filtrar, resolver, resolución sin nota rechazada, estudiante sin acceso |
| `Devices/DeviceManagementTest.php` | crear (token visible una vez), listado nunca expone el token, estudiante sin acceso, **revocar invalida el token de inmediato** |
| `Settings/SettingsTest.php` | ver/editar, validación cruzada (`trust >= reject`), rangos inválidos, estudiante sin acceso |

`recognition-app/tests/` (Etapa 5/7): detección real de "sin rostro" con DeepFace, contrato JSON de `compute_embedding.py`, comparación de embeddings, caché/fallback del catálogo sin conexión, cola de reintentos offline, y `test_integration_live.py` (opcional, contra un backend real).

---

## 3. Vulnerabilidad real encontrada al escribir estas pruebas

Ya documentada en detalle en `docs/03-seguridad.md` §0: al preparar `DevicePrincipalSeparationTest`, la prueba manual con `curl` (antes de automatizarla) reveló que un `User` autenticado por la web podía llamar a las rutas exclusivas del device. Se corrigió con `EnsureDevicePrincipal` antes de terminar la Etapa 9. Se menciona aquí también porque es el ejemplo más claro de por qué esta etapa vale la pena: la prueba que "debería pasar trivialmente" fue la que encontró el problema.

---

## 4. Trampas no obvias de probar Laravel+Sanctum (para no volver a perder tiempo en esto)

Todas se resolvieron; se documentan para la próxima vez.

- **`Illuminate\Auth\RequestGuard` cachea el usuario resuelto** la primera vez que se consulta dentro de un mismo método de prueba. Si una prueba hace una petición autenticada, cambia algo que debería invalidar esa autenticación (revocar un token, cerrar sesión), y luego hace OTRA petición esperando que ya no funcione — **sin `Auth::forgetGuards()` entre medio, la segunda petición reutiliza el resultado cacheado de la primera** y el cambio parece no haber tenido efecto. Afectó `DeviceManagementTest` (revocar token) y `LoginTest` (logout).
- **`actingAs($user)` persiste para todas las peticiones del mismo método de prueba**, incluso si una petición posterior manda un header `Authorization` distinto. Si una prueba necesita simular "un usuario autenticado hace A" y luego "un token distinto hace B", no mezclar `actingAs()` con manipulación directa de tokens en el mismo test — separarlos, o revocar/crear directamente sin pasar por `actingAs()` en la parte que le sigue.
- **`postJson()`/`putJson()`, no `post()`/`put()`, para subir archivos en pruebas.** `post()` sin cabecera `Accept: application/json` hace que Laravel intente devolver una vista de error HTML ante una falla (422/redirección 302) en vez de JSON — rompe las aserciones y, en un proyecto sin vistas de error configuradas, puede manifestarse como una excepción no capturada en vez de una respuesta clara. `postJson()` sí soporta adjuntar `UploadedFile` dentro del arreglo de datos (Laravel los extrae del cuerpo JSON automáticamente) y ya manda `Accept: application/json`.
- **Un `JsonResource` que envuelve un modelo recién creado (`wasRecentlyCreated`) devuelve `201`, no `200`, sin importar el verbo HTTP de la ruta.** El endpoint de reemplazar foto es un `PUT` pero internamente *crea* una fila nueva (borra la vieja, inserta otra) — Laravel lo marca `201` automáticamente. No es un bug, es el comportamiento esperado; hay que probarlo así, no forzar un `200`.
- **`UploadedFile::fake()->image(...)` necesita la extensión GD de PHP.** Sin ella, la aserción `'image'` de validación falla de forma confusa. Ya está habilitada en este entorno (`C:\xampp\php\php.ini`).
- **Los floats sin parte fraccionaria pierden el `.0` al pasar por `json_encode`/`json_decode`.** `round(5, 2)` es un float de PHP (`5.0`), pero como JSON viaja como `5`, y `assertJsonPath` compara con el tipo exacto tras decodificar — comparar contra `5`, no contra `5.0`.
- **Los endpoints que dependen de la sesión (`login`, `logout`) necesitan una cabecera `Referer`/`Origin`** que coincida con `SANCTUM_STATEFUL_DOMAINS` para que Sanctum arranque el middleware de sesión en la prueba — igual que se necesitaba con `curl` manual en etapas anteriores (Etapa 4, Etapa 6).
