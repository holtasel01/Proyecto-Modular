# Facelog — Etapa 1: Análisis del proyecto

> Documento de análisis previo a cualquier implementación. Debe ser aprobado antes de iniciar la Etapa 2 (Diseño).

---

## 1. Resumen del sistema

Facelog es un sistema de control de asistencia y horas de servicio social para un laboratorio universitario, que usa reconocimiento facial para identificar automáticamente a los estudiantes al entrar y salir. El resultado del reconocimiento alimenta un backend central (Laravel + PostgreSQL) que aplica las reglas de negocio (evitar duplicados, calcular horas, generar incidencias) y expone esa información a una plataforma web (TypeScript) donde estudiantes y administradores consultan y gestionan los registros.

El sistema tiene tres piezas de software con responsabilidades bien separadas:

1. **App de reconocimiento** (Python) — corre en el equipo del laboratorio, ve la cámara, identifica al estudiante y reporta el evento.
2. **Backend/API** (Laravel + PostgreSQL) — es el "cerebro": valida, decide, calcula y persiste.
3. **Plataforma web** (TypeScript) — interfaz para consultar y administrar.

---

## 2. Problema que resuelve

El registro manual de asistencia y horas de servicio social es propenso a: horas mal anotadas, registros incompletos, favoritismo o suplantación ("me firma un compañero"), y dificultad para auditar o corregir errores después. Facelog resuelve esto automatizando la identificación (reconocimiento facial en vez de firma/lista) y centralizando el cálculo y la trazabilidad de horas en un sistema con historial verificable.

---

## 3. Usuarios y roles

| Rol | Descripción | Necesita cuenta en la plataforma |
|---|---|---|
| **Estudiante** | Presta servicio social en el laboratorio. Se identifica por rostro para entrar/salir; consulta su propio progreso en la web. | Sí (solo lectura de sus propios datos) |
| **Administrador / Encargado del laboratorio** | Responsable del laboratorio. Da de alta estudiantes, revisa/corrige registros, resuelve incidencias. | Sí (gestión completa) |
| **Dispositivo de reconocimiento** (no es un "usuario humano") | La app del laboratorio se autentica ante la API con un token propio, no con una cuenta de estudiante ni de admin. | Token de API, sin login interactivo |

No se contempla un tercer rol humano (p. ej. "supervisor de escuela") porque el enunciado no lo exige y agregarlo sin un caso de uso claro sería complejidad innecesaria. Si en el futuro se necesita, el modelo de roles (ver §11) permite añadirlo sin rediseño.

---

## 4. Requisitos funcionales

**App de reconocimiento (laboratorio)**
- RF1. Capturar video de la cámara y detectar rostros en tiempo real.
- RF2. Reconocer si un rostro detectado corresponde a un estudiante registrado, con un nivel de confianza.
- RF3. Rechazar/ignorar reconocimientos por debajo del umbral de confianza configurado.
- RF4. Evitar procesar el mismo rostro múltiples veces en una ventana corta de tiempo (anti-duplicado en el punto de captura).
- RF5. Enviar el evento (entrada o salida) al backend vía API.
- RF6. Mostrar retroalimentación clara al estudiante (reconocido / no reconocido / ya registrado).
- RF7. Encolar localmente los eventos si no hay conexión con el backend, y reintentar el envío al recuperarla.
- RF8. Aplicar una verificación básica de vida (liveness) para reducir el riesgo de suplantación con foto/pantalla.

**Backend (Laravel)**
- RF9. Autenticar usuarios (estudiantes y administradores) y autenticar el dispositivo del laboratorio.
- RF10. Exponer una API para recibir eventos de entrada/salida desde la app de reconocimiento.
- RF11. Determinar si un evento entrante es una entrada o una salida (según el último estado del estudiante).
- RF12. Detectar y marcar automáticamente inconsistencias (entrada sin salida tras cierto tiempo, salida sin entrada, doble entrada, confianza baja).
- RF13. Calcular la duración de cada sesión y acumular horas por estudiante.
- RF14. Permitir a un administrador corregir manualmente un registro, dejando constancia de la corrección (auditoría).
- RF15. Gestionar el alta/baja/edición de estudiantes y su enrolamiento facial (fotos/embeddings de referencia).
- RF16. Gestionar roles y permisos (qué puede ver/hacer cada tipo de usuario).
- RF17. Registrar un historial de auditoría de cambios sensibles (quién, cuándo, qué valor antes/después).
- RF18. Generar/listar incidencias para revisión administrativa.

**Plataforma web (TypeScript)**
- RF19. Login diferenciado por rol.
- RF20. Estudiante: ver su perfil, sus registros de entrada/salida, horas acumuladas, progreso respecto a la meta de horas, e incidencias asociadas a sus registros; además, subir su propia fotografía de enrolamiento facial por única vez (decisión de diseño, ver `docs/02-diseno.md` §1) — es la única acción de escritura permitida al rol estudiante; cualquier corrección posterior requiere al administrador.
- RF21. Administrador: ver/gestionar estudiantes, ver todos los registros de asistencia, corregir registros, revisar y resolver incidencias, ver información general del laboratorio (ej. quién está dentro ahora mismo).

## 5. Requisitos no funcionales

- RNF1. **Confiabilidad**: un evento de entrada/salida no debe perderse; si la red falla, debe quedar en cola y reintentarse (RF7).
- RNF2. **Seguridad**: los datos biométricos (embeddings) se tratan como datos sensibles; el acceso a la API está autenticado y autorizado en cada endpoint; las contraseñas se almacenan con hash (bcrypt/argon2, estándar de Laravel).
- RNF3. **Usabilidad**: la app del laboratorio debe dar retroalimentación inmediata y entendible (menos de ~2 segundos por reconocimiento en hardware modesto).
- RNF4. **Mantenibilidad**: separación estricta de responsabilidades entre los tres componentes; código documentado; convenciones consistentes.
- RNF5. **Trazabilidad**: toda modificación manual de un registro de asistencia debe quedar auditada (RF17).
- RNF6. **Rendimiento razonable**: el reconocimiento debe funcionar en una laptop/PC de gama media sin GPU dedicada.
- RNF7. **Portabilidad de datos**: PostgreSQL como única fuente de verdad; ni Python ni el frontend mantienen estado que no pueda reconstruirse desde el backend.
- RNF8. **Capacidad de prueba**: cada componente debe poder probarse de forma aislada (Python con imágenes de prueba, Laravel con tests de feature/unit, frontend con pruebas de componente).

---

## 6. Arquitectura propuesta

Arquitectura de **tres componentes desacoplados que se comunican por HTTP/REST**, con PostgreSQL como única base de datos compartida (accedida solo por Laravel; Python y el frontend nunca tocan la base de datos directamente — siempre pasan por la API). Este es el patrón más simple que cumple el requisito de integración sin introducir un microservicio adicional ni una cola de mensajes, que serían sobreingeniería para el alcance de este proyecto.

```
┌─────────────────────┐        HTTPS/REST (token)        ┌──────────────────────────┐
│  App de laboratorio   │ ───────────────────────────────▶ │        Backend API        │
│  (Python)              │ ◀─────────────────────────────── │        (Laravel)          │
│  cámara + reconoc.    │        respuestas / catálogo      │                            │
└─────────────────────┘                                    │   PostgreSQL (única BD)   │
                                                              │                            │
┌─────────────────────┐        HTTPS/REST (token)          │                            │
│   Plataforma web       │ ───────────────────────────────▶ │                            │
│   (TypeScript SPA)     │ ◀─────────────────────────────── └──────────────────────────┘
└─────────────────────┘
```

**Por qué no un microservicio Python permanente (FastAPI/Flask) al que Laravel le pide "reconoce esta imagen":**
Se evaluó, pero para un solo laboratorio con una sola cámara, mantener dos servidores HTTP corriendo (Laravel + un servicio Python) agrega una pieza de infraestructura sin beneficio real. En cambio, la app Python corre como proceso local en el equipo del laboratorio, hace todo el procesamiento de video ahí mismo (más rápido, sin subir video por red) y solo llama a la API de Laravel para reportar el resultado ya decidido. Si en el futuro hay varios laboratorios, cada uno simplemente ejecuta su propia instancia de esta app, todas hablando con la misma API — el diseño ya escala así sin cambios.

---

## 7. Componentes principales

1. **`recognition-app/` (Python)** — aplicación que corre en el equipo del laboratorio: captura de cámara, detección de rostro, generación de embedding, comparación contra el catálogo local de estudiantes, liveness básico, cola local de reintentos, cliente HTTP hacia la API.
2. **`backend/` (Laravel)** — API REST, autenticación, modelos de dominio, reglas de negocio de asistencia, auditoría, administración.
3. **`frontend/` (TypeScript SPA)** — interfaz web para estudiante y administrador, consumiendo la API de Laravel.
4. **PostgreSQL** — persistencia única y compartida, accedida solo por Laravel.

---

## 8. Flujo de información

**Enrolamiento (una vez por estudiante, hecho por el admin):**
Admin sube fotos del estudiante desde la web → Laravel las envía/entrega a un paso de generación de embedding (ver §12) → se guarda el/los embedding(s) en PostgreSQL asociados al estudiante.

**Entrada/salida (flujo repetido):**
1. Estudiante se para frente a la cámara del laboratorio.
2. La app Python detecta el rostro y calcula su embedding.
3. Compara contra el catálogo local (sincronizado periódicamente desde la API) y obtiene el estudiante más parecido + nivel de confianza.
4. Si pasa el umbral de confianza y el chequeo de liveness, arma un evento `{student_id, confidence, timestamp, device_id}`.
5. Aplica el filtro anti-duplicado local (no reenviar el mismo estudiante en los últimos N segundos).
6. Envía el evento a `POST /api/attendance/events` en Laravel (con reintento/cola si no hay red).
7. Laravel determina si es entrada o salida según el último estado abierto del estudiante, valida reglas (ver §14), persiste el evento y, si corresponde, cierra/abre una sesión de asistencia.
8. Laravel responde a Python con el resultado (para mostrarlo en pantalla) y, si detectó una anomalía, crea una incidencia.
9. El estudiante o el administrador consultan después esa información desde la plataforma web, que la pide a la API de Laravel.

---

## 9. Propuesta inicial de base de datos (PostgreSQL)

Diseño orientado a **eventos crudos + sesiones derivadas**, en vez de una sola fila "entrada/salida" editada in-place. Se eligió así porque el enunciado exige manejar explícitamente casos anómalos (entrada sin salida, doble reconocimiento, salida sin entrada): guardar siempre el evento crudo, tal como ocurrió, permite reconstruir o corregir la interpretación después sin perder información original. Si se guardara solo una fila de "sesión" que se va sobreescribiendo, un error de reconocimiento sería más difícil de auditar.

| Tabla | Propósito | Columnas clave |
|---|---|---|
| `users` | Cuentas de acceso (login) | id, name, email, password_hash, role (`student`\|`admin`), timestamps |
| `students` | Perfil de servicio social | id, user_id (FK, nullable si aún no tiene cuenta), matrícula, carrera, horas_meta, estado (activo/inactivo), timestamps |
| `face_embeddings` | Referencias biométricas de enrolamiento | id, student_id (FK), vector (embedding serializado), modelo (nombre/versión del modelo usado), created_at |
| `devices` | Equipos autorizados a reportar eventos | id, nombre, ubicación, token_id (referencia al personal access token de Sanctum), last_seen_at |
| `attendance_events` | Evento crudo de reconocimiento (entrada o salida) | id, student_id (nullable si no se identificó), device_id, type (`entry`\|`exit`), confidence, occurred_at, source (`face_recognition`\|`manual`), raw_meta (json opcional) |
| `attendance_sessions` | Sesión derivada de un par entrada/salida | id, student_id, entry_event_id (FK), exit_event_id (FK nullable), started_at, ended_at (nullable), duration_minutes (nullable), status (`open`\|`closed`\|`inconsistent`) |
| `incidents` | Situaciones anómalas a revisar | id, type (enum: entrada_sin_salida, duplicado, baja_confianza, salida_sin_entrada, corregido_manualmente...), attendance_session_id (FK nullable), attendance_event_id (FK nullable), description, status (`open`\|`resolved`), resolved_by (FK users), resolved_at |
| `audit_logs` | Trazabilidad de modificaciones manuales | id, user_id (quién modificó), auditable_type, auditable_id, action, old_values (json), new_values (json), created_at |
| `settings` | Parámetros ajustables sin redeploy (umbral de confianza, ventana anti-duplicado, meta de horas por defecto) | id, key, value, updated_at |

**Deliberadamente no se incluyen todavía:** tabla `roles`/`permissions` separada (con solo 2 roles, un enum en `users` es suficiente — ver §12), ni tabla de "asistencias" plana sin eventos crudos, ni tablas para NFC/QR (no son requisito, §16).

Relaciones principales: `students 1—N face_embeddings`, `students 1—N attendance_events`, `attendance_events 1—1 attendance_sessions` (como entrada o salida), `attendance_sessions 1—N incidents`, `users 1—N audit_logs`.

---

## 10. Comunicación entre Python y Laravel

**HTTP REST sobre la red local (o LAN de la universidad), con autenticación por token.**

- La app Python se autentica con un **Personal Access Token de Laravel Sanctum** asociado a un registro en `devices`, con alcance limitado (solo puede llamar a los endpoints de sincronización y de reporte de eventos, no a los endpoints administrativos).
- **`GET /api/sync/face-catalog`** — Python descarga (al iniciar y luego periódicamente, ej. cada 5–10 min) la lista de estudiantes activos con sus embeddings, para comparar localmente sin depender de la red en cada reconocimiento.
- **`POST /api/attendance/events`** — Python reporta un evento ya decidido (`student_id`, `confidence`, `occurred_at`, `device_id`); Laravel aplica las reglas de negocio y responde con el resultado (para mostrarlo en pantalla) y si generó una incidencia.
- **Tolerancia a fallos de red**: si Python no puede contactar la API, guarda el evento en una cola local (archivo o SQLite embebido) y reintenta con backoff hasta confirmarlo — así ningún registro se pierde por una caída temporal de red (RF7/RNF1).

Se evaluó gRPC y WebSockets: se descartan por complejidad innecesaria — no hay necesidad de streaming continuo ni de un contrato binario; REST/JSON es más simple de depurar, documentar y probar en un proyecto universitario, y es nativo en Laravel.

---

## 11. Propuesta para el reconocimiento facial

Pipeline con **embeddings faciales**, no comparación de imágenes completas:

1. **Detección de rostro** en el frame de video.
2. **Extracción de un embedding** (vector numérico que representa el rostro) del rostro detectado.
3. **Comparación** del embedding contra los embeddings guardados de estudiantes activos, usando distancia (coseno o euclidiana).
4. Si la distancia/similitud pasa el **umbral configurado**, se considera identificado con un nivel de confianza; si no, se marca como "no reconocido".
5. **Liveness básico**: antes de aceptar el reconocimiento, verificar una señal de "persona real" (ver abajo).

**Librería de reconocimiento — alternativas evaluadas:**

| Opción | Ventajas | Desventajas |
|---|---|---|
| `face_recognition` (basado en dlib) | Muy documentada, API simple, ampliamente usada en tutoriales/proyectos académicos | Depende de `dlib`, que requiere compilar con CMake/Visual Studio Build Tools — instalación frágil en Windows |
| **DeepFace** | Instalación solo con `pip` (sin compilar nada), API de alto nivel (`represent`, `verify`), permite elegir el modelo backend (Facenet512, ArcFace, VGG-Face, etc.) sin cambiar el resto del código, ya trae detección integrada y umbrales de referencia documentados | Primera ejecución descarga pesos del modelo (~decenas de MB); algo más lenta que dlib puro en CPU |
| InsightFace (ArcFace, ONNX) | Precisión estado del arte | Configuración más compleja, curva de aprendizaje mayor, desproporcionado para el alcance del proyecto |
| MediaPipe | Muy ligero, buena detección/landmarks, fácil instalación | No resuelve por sí solo la identificación de identidad (embeddings de reconocimiento), sí es útil para el liveness (ver abajo) |

**Recomendación**: **DeepFace** para detección + embeddings, por su facilidad de instalación (crítico porque no se puede asumir un entorno con herramientas de compilación en la máquina del laboratorio) y por dar una API lista para comparar rostros con umbrales ya documentados. Se deja `face_recognition`/dlib como alternativa si el rendimiento de DeepFace resulta insuficiente en las pruebas de la Etapa 5.

**Liveness detection**: se implementará una verificación **básica y proporcional**, no un modelo anti-spoofing dedicado:
- Detección de parpadeo (Eye Aspect Ratio sobre landmarks faciales, obtenibles con MediaPipe) y/o pequeño movimiento de la cabeza entre frames consecutivos, antes de aceptar el reconocimiento como válido.
- Esto bloquea el ataque más simple (mostrar una foto impresa o una imagen estática en el teléfono) sin la complejidad de entrenar/integrar un modelo de anti-spoofing dedicado.
- Se documentará explícitamente como limitación: no cubre un ataque con video en reproducción. Se deja como mejora futura si el proyecto lo requiere.

**Múltiples rostros en el frame**: si se detecta más de un rostro simultáneamente, el sistema no adivina — ignora el frame para reconocimiento automático y puede pedir que se acerque una persona a la vez (regla simple y segura, evita asociar mal un evento).

---

## 12. Propuesta de autenticación

- **Laravel Sanctum** para toda la autenticación de la API (se descarta Passport: es para OAuth2 con clientes de terceros, que no es el caso aquí; Sanctum es más simple, suficiente y es lo recomendado por Laravel para una SPA propia + tokens de dispositivo).
  - **Estudiantes y administradores**: login con email/contraseña desde la SPA, sesión autenticada vía cookies (Sanctum "SPA authentication").
  - **App del laboratorio**: token personal de acceso (Sanctum Personal Access Token) asociado a un registro en `devices`, con capacidad ("ability") restringida a los endpoints de sincronización/reporte de eventos.
- **Roles**: campo `role` en `users` (`student` | `admin`) más **Policies** de Laravel para autorizar acciones (ej. `AttendancePolicy`, `StudentPolicy`). Se evaluó el paquete `spatie/laravel-permission` (muy usado y bien documentado) pero con solo dos roles fijos, agregar esa dependencia sería complejidad innecesaria; si más adelante se necesitan permisos granulares por función, se puede introducir sin rediseñar el resto.
  - **Decisión confirmada sobre `admin`**: por ahora existe un único rol `admin` (sin distinción en base de datos). Se confirma que, más adelante, este rol podría derivar en perfiles más específicos — por ejemplo "encargado de laboratorio" (uso operativo diario: revisar asistencias, resolver incidencias) y "programador/asistente" (acceso técnico: configuración del sistema, catálogo de dispositivos, mantenimiento). Para no rediseñar la autorización cuando eso ocurra, toda comprobación de permisos se centraliza en **Policies/Gates** (nunca `if ($user->role === 'admin')` disperso en controladores) — así, cuando se necesite diferenciar sub-perfiles de admin, basta con ajustar la Policy (o migrar a `spatie/laravel-permission`) sin tocar cada controlador.
- Contraseñas con el hash por defecto de Laravel (bcrypt/argon2). Rate limiting en el endpoint de login (throttle nativo de Laravel) para mitigar fuerza bruta.

---

## 13. Riesgos técnicos

- **Precisión del reconocimiento** afectada por iluminación, ángulo o cambios de apariencia (lentes, mascarilla, corte de cabello) → mitigar permitiendo varios embeddings de referencia por estudiante y un umbral configurable ajustable tras pruebas reales.
- **Rendimiento en hardware modesto** del laboratorio (sin GPU) → elegir modelos livianos, procesar a una tasa de frames reducida (no cada frame), medir tiempos en la Etapa 5 antes de comprometerse a un modelo pesado.
- **Instalación de dependencias de Python** (modelos de visión) puede ser pesada/lenta la primera vez → documentar bien el setup (Etapa 3) y fijar versiones.
- **Reloj desincronizado** entre el equipo del laboratorio y el servidor → usar siempre la hora del servidor (Laravel asigna `occurred_at` si no es confiable el timestamp del cliente, o al menos se valida contra desviaciones grandes).
- **Concurrencia**: dos estudiantes frente a la cámara casi simultáneamente → decisión de diseño en §11 (ignorar frames con múltiples rostros para eventos automáticos).
- **Pérdida de conexión** entre Python y Laravel → cola local con reintentos (§10).

## 14. Riesgos de seguridad

- **Datos biométricos sensibles**: los embeddings faciales identifican a una persona y deben tratarse como dato personal sensible. Mitigación: no exponerlos nunca por la API hacia el frontend (el catálogo de sincronización solo lo puede pedir el dispositivo autenticado, nunca la SPA), restringir acceso a nivel de base de datos. **Decisión confirmada**: no se conservan las fotografías originales de enrolamiento; se procesan solo para calcular el embedding y se descartan (no se persisten en disco ni en la base de datos) una vez validado que el enrolamiento fue correcto. Esto reduce significativamente la superficie de riesgo de los datos biométricos, al costo de no poder re-generar el embedding con un modelo distinto sin volver a captar al estudiante — se documenta como consecuencia aceptada.
- **Suplantación con foto/pantalla**: mitigada parcialmente con liveness básico (§11); se documenta como riesgo residual aceptado y proporcional al alcance universitario.
- **Robo/uso indebido del token del dispositivo**: si se filtra el token de la app del laboratorio, un atacante podría reportar eventos falsos. Mitigación: alcance del token limitado solo a esos endpoints, posibilidad de revocar/rotar el token desde el panel admin, registro de `device_id` en cada evento para poder auditar su origen.
- **Manipulación de registros por un administrador**: mitigado con auditoría obligatoria (`audit_logs`) en toda corrección manual — no elimina el riesgo pero lo hace verificable.
- **Exposición de la API**: rate limiting, validación estricta de entrada en cada endpoint, CORS configurado solo para el origen del frontend conocido, HTTPS en producción.
- **Fuerza bruta / robo de sesión**: throttling de login, expiración de tokens, contraseñas hasheadas (nunca en texto plano).

---

## 15. Tecnologías y librerías recomendadas (resumen)

| Capa | Tecnología | Justificación breve |
|---|---|---|
| Backend/API | Laravel (versión LTS más reciente disponible) | Requisito del proyecto |
| Auth API | Laravel Sanctum | Más simple que Passport; cubre SPA + tokens de dispositivo, que es exactamente lo que se necesita |
| Autorización | Policies nativas de Laravel + enum de rol | Suficiente para 2 roles; evita dependencia extra |
| Frontend | React + TypeScript + Vite, SPA independiente consumiendo la API REST | Amplísima documentación, separación limpia de la API (útil porque la API también la consume Python), curva de aprendizaje razonable |
| Base de datos | PostgreSQL | Requisito del proyecto |
| Visión artificial | Python + DeepFace (detección + embeddings) | Instalación simple vía pip, API de alto nivel, permite intercambiar el modelo backend sin rediseñar |
| Liveness | Landmarks faciales (MediaPipe) + Eye Aspect Ratio para detectar parpadeo | Proporcional al alcance; no requiere entrenar/integrar un modelo anti-spoofing dedicado |
| Cliente HTTP en Python | `requests` | Estándar, simple, suficiente |
| Cola local de reintento (Python) | SQLite embebido (vía `sqlite3` de la librería estándar) | No requiere servidor adicional; persiste en disco |

## 16. Alternativas importantes que deberían evaluarse más adelante (no ahora)

- **Docker/Docker Compose** para levantar PostgreSQL (y opcionalmente Laravel) en desarrollo — decidir en la Etapa 3 según la comodidad del equipo con Docker.
- **spatie/laravel-permission** si los roles/permisos crecen en granularidad.
- **`face_recognition`/dlib** como alternativa a DeepFace si el rendimiento no es suficiente en pruebas reales (Etapa 5).
- **NFC/QR como segundo factor**: no se implementa por ahora (no es requisito); se revisará solo si, tras probar el reconocimiento facial real, se concluye que el liveness básico es insuficiente para el nivel de confianza deseado.
- **Minería de datos sobre el historial** (detección de patrones de asistencia, agrupamiento): se evaluará en una etapa posterior a tener datos reales acumulados — implementarlo ahora sería prematuro y sin datos para validar que aporte valor.
- **Inertia.js** (en vez de SPA + API separada) si se prefiriera reducir la duplicación de lógica de autenticación entre web y API — se descarta por ahora porque la API debe existir de todas formas para que Python la consuma, así que una SPA separada no agrega trabajo extra real.

---

## 17. Estructura inicial de carpetas del proyecto

```
Proyecto Modular/
├── backend/                    # Laravel (API REST, lógica de negocio, PostgreSQL)
├── frontend/                   # React + TypeScript + Vite (SPA web)
├── recognition-app/             # Python (captura, detección, reconocimiento, cliente API)
│   ├── src/
│   ├── models/                  # (o cache de pesos descargados, si no se versiona)
│   └── tests/
├── docs/                        # documentación del proyecto (este análisis, diseño, manuales)
└── README.md                    # visión general y cómo levantar cada componente
```

Cada subcarpeta será, en la práctica, un proyecto independiente con su propio gestor de dependencias (Composer en `backend/`, npm/pnpm en `frontend/`, `venv`/`requirements.txt` o `pyproject.toml` en `recognition-app/`).

---

## 18. Orden recomendado para comenzar el desarrollo

1. **Base de datos y fundamentos de Laravel**: migraciones de `users`, `students`, `settings`; autenticación básica (Sanctum) y roles.
2. **CRUD de estudiantes** en el backend (necesario antes de poder enrolar rostros).
3. **Prototipo aislado de reconocimiento en Python**: probar detección + embeddings + comparación con un pequeño set de fotos de prueba, **sin conectarlo todavía a Laravel** — validar que la precisión y el rendimiento son aceptables antes de integrar.
4. **Contrato de API de asistencia** (`attendance_events`, `attendance_sessions`) y lógica de negocio (entrada/salida, duplicados, incidencias) en Laravel.
5. **Conectar Python → Laravel** usando ese contrato (sincronización de catálogo + envío de eventos).
6. **Frontend**: login y panel de estudiante (solo lectura) primero; panel de administrador después.
7. **Incidencias y auditoría** visibles en el panel admin.
8. **Revisión de seguridad** end-to-end (rate limiting, policies, exposición de datos biométricos).
9. **Pruebas** de cada componente y del flujo integrado.
10. **Documentación final**.

Este orden coincide con las Etapas 3–10 ya definidas, aterrizando cuáles módulos internos de cada etapa conviene construir primero.

---

## Decisiones confirmadas (2026-09-09)

1. **Fotos de enrolamiento**: no se conservan. Se procesan únicamente para calcular el embedding y se descartan una vez validado el enrolamiento (ver §14). Consecuencia aceptada: si se cambia de modelo de reconocimiento en el futuro, será necesario volver a captar a los estudiantes, ya que no habrá imagen original para re-procesar.
2. **Roles**: un único rol `admin` por ahora (sin distinción en base de datos), con la autorización centralizada en Policies para poder derivar más adelante, sin rediseño, perfiles como "encargado de laboratorio" y "programador/asistente" (ver §12).
3. **Dataset de prueba**: no se dispone de uno todavía; se conseguirá cuando sea necesario para las pruebas de reconocimiento de la Etapa 5. Para el prototipo aislado (paso 3 del orden de desarrollo, §18) se usará un set mínimo propio (ej. fotos de quienes desarrollan el proyecto) mientras tanto.

---

Con estas decisiones confirmadas, se procede a la **Etapa 2 — Diseño** (arquitectura detallada, estructura de carpetas interna de cada componente, diseño de API, diagramas de flujo y relación de entidades).
