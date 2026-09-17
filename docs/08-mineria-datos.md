# Facelog — Etapa adicional: minería de datos (agrupamiento con K-Means)

> No es una de las diez etapas originales del plan. Se agrega después, a solicitud explícita del usuario, para cumplir un requisito de la parte de "minería de datos" del curso: usar el algoritmo K-Means.

---

## 1. Qué agrupa y por qué

K-Means agrupa a los **estudiantes activos** (con al menos una sesión de asistencia cerrada) según su **patrón de asistencia**, en tres perfiles: *baja actividad*, *actividad moderada*, *alta actividad*.

Se usan seis características por estudiante, todas derivadas de `attendance_sessions` — nada inventado ni fuera de lo que el sistema ya registra:

| Característica | Qué mide |
|---|---|
| `horas_acumuladas` | Total de horas registradas (histórico completo) |
| `numero_sesiones` | Cuántas sesiones cerradas tiene |
| `duracion_promedio_sesion_min` | Duración típica de una sesión suya |
| `horas_promedio_semana` | Ritmo de actividad reciente, normalizado por semana |
| `sesiones_promedio_semana` | Frecuencia con la que asiste, normalizada por semana |
| `variabilidad_semanal` | Qué tan constante es semana a semana (desviación estándar de horas/semana) |

**Por qué no basta con los tres primeros (los totales)**: dos estudiantes con las mismas horas acumuladas pueden tener historiales muy distintos — uno lleva un semestre viniendo poco, y otro empezó hace dos semanas viniendo mucho. `horas_promedio_semana` y `sesiones_promedio_semana` normalizan por el tiempo que cada estudiante lleva activo, en vez de comparar totales crudos que dependen de la antigüedad. `variabilidad_semanal` distingue, además, a un estudiante constante (2h cada semana) de uno en ráfagas (10h una semana, nada las siguientes tres) aunque su promedio sea parecido.

**No se incluyó "puntualidad"**: el sistema no tiene un horario de referencia por estudiante contra el cual medirla — agregar uno habría sido inventar un requisito que nadie pidió.

## 2. Por qué estas seis variables y no una selección distinta

Se decidió con el usuario en tres iteraciones: primero se propuso un conjunto de tres (totales), y el usuario pidió ampliarlo a las seis actuales, agregando las tres normalizadas por semana — exactamente para capturar ritmo reciente y consistencia, no solo acumulados.

## 3. Dónde vive cada parte

Mismo patrón que el enrolamiento facial (`docs/02-diseno.md` §1): Laravel arma los datos, un script de Python hace el trabajo real, invocado como subproceso.

```
backend/app/Services/StudentClusteringService.php   — arma los vectores de características desde PostgreSQL
recognition-app/scripts/compute_clusters.py          — K-Means real (scikit-learn), recibe/devuelve JSON
backend/app/Http/Controllers/Api/StudentClusterController.php  — GET /api/analytics/student-clusters (solo admin)
frontend/src/pages/admin/AdminAnalytics.tsx           — tabla + gráfico de dispersión
```

`StudentClusteringService` construye una fila por estudiante activo con al menos una sesión cerrada — un estudiante sin sesiones no tiene forma honesta de calcular duración/frecuencia/variabilidad, así que simplemente no entra al agrupamiento (no se le asigna un "0" que lo haría verse idéntico a alguien realmente inactivo). El JSON se manda por **stdin** al script de Python (no como argumento de línea de comandos, a diferencia de `compute_embedding.py`) porque el payload es una lista de tamaño variable — más limpio que armar un argumento gigante.

### 3.1 El algoritmo en sí (`compute_clusters.py`)

1. **Estandariza** las 6 variables (`StandardScaler` de scikit-learn) antes de calcular distancias. Es necesario porque K-Means mide distancia euclidiana: sin estandarizar, `horas_acumuladas` (decenas/cientos) dominaría por completo sobre `sesiones_promedio_semana` (unidades) solo por la escala, no porque importe más.
2. Corre `KMeans(n_clusters=3, random_state=42, n_init=10)` — `k=3` fijo, ya que no hay una rúbrica que pida método del codo o que el admin elija `k`; tres perfiles (bajo/medio/alto) es interpretable sin que nadie tenga que decidir nada.
3. **Reordena las etiquetas**: K-Means numera los grupos de forma arbitraria (el grupo `0` no es necesariamente el de menor actividad). Se reordenan por el nivel de actividad de cada centroide (`horas_promedio_semana` + `sesiones_promedio_semana`, ya estandarizadas) para que la etiqueta `"alta actividad"` sea, de verdad, siempre la más activa.
4. Los centroides se devuelven **en la escala original** (`scaler.inverse_transform`), no estandarizada — para que tengan sentido al mostrarlos (p. ej. "≈4 horas por semana", no un número sin unidad).

## 4. Particularidad de Windows — otra vez

Igual que con `compute_embedding.py` (`docs/02-diseno.md`, nota tras la Etapa 5), **`php artisan serve` no puede invocar este script tampoco**: falla con el mismo tipo de error de E/S asíncrona, porque scikit-learn (vía `joblib`/`scipy`) también termina importando `asyncio` de forma transitiva. Confirmado reproduciendo el mismo síntoma: funciona perfecto invocado directo o desde `php artisan tinker`, falla desde `artisan serve`, funciona de nuevo sirviendo el backend con Apache (mismo vhost del puerto 8088 que ya existía para el enrolamiento — no hace falta configurar nada nuevo).

**En la práctica**: para usar la página de Analítica del panel admin, el backend debe estar corriendo bajo Apache, no `artisan serve` — exactamente la misma condición que ya existía para subir fotos de enrolamiento (ver `docs/06-instalacion.md` §7).

## 5. Rendimiento: por qué era lento y cómo se resolvió

Reportado por el usuario ("se tarda demasiado en cargar los datos"). Se midió cada endpoint del proyecto con `curl -w "%{time_total}"`: todos responden en 100-250ms, **excepto** `/api/analytics/student-clusters`, que tomaba entre 1.6 y 3.5 segundos — de lejos el más lento de toda la API.

**Causa**: cada petición arrancaba un intérprete de Python nuevo desde cero, que tiene que importar `numpy`, `scipy` y `scikit-learn` (con todo lo que eso arrastra) antes de poder calcular nada — ese costo de arranque se pagaba en **cada** carga de la página de Analítica, aunque los datos de asistencia no hubieran cambiado desde la última vez.

**Corrección**: el resultado del agrupamiento se cachea 5 minutos (`StudentClusteringService`, `Cache::remember`-equivalente). La carga normal de la página usa la caché si hay una vigente (tan rápida como cualquier otro endpoint, ~150ms); el botón **"Recalcular"** del frontend manda `?refresh=1`, que se salta la caché a propósito y fuerza un cálculo fresco. Verificado en vivo contra el backend real: primera llamada (forzada) ≈1.8s, llamadas siguientes dentro de la ventana de caché ≈0.16s.

No se tocó nada del enrolamiento facial (`compute_embedding.py`) — ese sí necesita ser síncrono y sin caché, porque cada foto es distinta; el problema de rendimiento era específico del agrupamiento, que sí puede reutilizar un resultado reciente sin perder utilidad real para el admin.

## 6. Limitación conocida: pocos estudiantes distorsionan los grupos

`k=3` es fijo, así que con muy pocos estudiantes con datos (el mínimo exigido es 3), K-Means puede separar en grupos distintos a estudiantes con patrones casi idénticos — no hay forma de evitarlo sin bajar `k` dinámicamente, lo que se decidió no hacer para mantener las tres etiquetas consistentes (bajo/medio/alto) sea cual sea el tamaño del laboratorio. Con más estudiantes (una decena o más), los grupos se estabilizan y reflejan patrones reales, no ruido. Verificado con datos sintéticos: 6 estudiantes en tres parejas claramente distintas se agruparon correctamente; con solo 4 estudiantes muy dispares en actividad, dos de ellos con valores parecidos (20h vs. 21h acumuladas) terminaron en grupos distintos — es un efecto esperado de `k=3` fijo con pocos datos, no un error del algoritmo.

## 7. Pruebas

- `recognition-app/tests/test_compute_clusters_cli.py` (4 pruebas): agrupamiento correcto con datos sintéticos claramente separados, rechazo con menos de 3 estudiantes, JSON inválido, estudiante sin `features`.
- `backend/tests/Feature/Analytics/StudentClusterTest.php` (8 pruebas): solo admin, error claro con menos de 3 estudiantes con sesiones, estudiantes sin sesiones (o inactivos) excluidos del cálculo, **corrección matemática de las 6 características verificada con un caso de estudiante perfectamente constante** (4 sesiones de 2h, una por semana durante 4 semanas exactas → variabilidad = 0), el camino feliz/de error del script simulado con `Process::fake()`, y las dos pruebas de caché (§5): una segunda llamada no vuelve a invocar Python, `?refresh=1` sí lo fuerza.

Verificado también de punta a punta con datos reales (vía Apache, no simulado): estudiantes con historial real de asistencia sembrado a propósito, agrupados correctamente y visibles en el navegador (tabla + gráfico de dispersión).
