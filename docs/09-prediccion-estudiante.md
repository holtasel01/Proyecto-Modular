# Facelog — Etapa adicional: ventana de predicción para el estudiante

> No es una de las diez etapas originales del plan. Se agrega a continuación de la etapa de minería de datos (`docs/08-mineria-datos.md`), a solicitud explícita del usuario, tomando en cuenta que **la meta de horas de servicio social en la UDG es de 480 horas**.

---

## 1. La meta de 480 horas ya era el valor por defecto — se confirmó, no se cambió

Antes de tocar nada se verificó dónde vive el valor de 480 horas en el sistema, para no duplicarlo ni inventar una segunda fuente de verdad:

- `settings.default_horas_meta = '480'` (sembrado en `DatabaseSeeder`) — es lo que usa `StoreStudentRequest` cuando el admin da de alta un estudiante sin especificar una meta propia.
- `students.horas_meta` — cada estudiante puede tener una meta distinta si el admin la ajusta explícitamente (columna ya existía desde la Etapa 4); 480 es el valor por defecto, no uno fijo a nivel de código.

**Conclusión**: no hizo falta ninguna migración ni cambio de esquema — 480 ya era el default correcto en todo el sistema.

## 2. ¿Se modificó el agrupamiento K-Means por esto?

**No.** Se consideró agregar "progreso hacia la meta" como una séptima característica del agrupamiento (`docs/08-mineria-datos.md`), y se decidió no hacerlo: la meta de horas es una **meta/estado** (cuánto llevas de una meta fija), no un **patrón de comportamiento** (cómo y con qué ritmo asistes). Mezclar ambas cosas en la misma distancia de K-Means confundiría "casi terminaste tu meta" con "vienes muy seguido", que son preguntas distintas — el progreso hacia la meta ya se muestra por separado (ver §3). El conjunto de 6 características del agrupamiento queda igual.

## 3. Qué es la ventana de predicción

Página nueva, **exclusiva del rol estudiante** (`/prediccion` en el frontend, `GET /api/me/prediction` en el backend), con una proyección personal hacia la meta de 480 horas (o la meta particular del estudiante, si el admin se la cambió):

| Dato mostrado | De dónde sale |
|---|---|
| Horas acumuladas / meta, con barra de progreso | Igual que `/me/summary` (Etapa 4), ya existente |
| Horas por semana, horas por día | `horas_promedio_semana` (ver §4) y esa misma cifra ÷ 7 |
| Sesiones por semana | `sesiones_promedio_semana` |
| Duración típica de una sesión | `duracion_promedio_sesion_min` |
| Constancia | Interpretación en palabras de `variabilidad_semanal` (§4) |
| Horas que faltan | `horas_meta - horas_acumuladas`, nunca negativo |
| Semanas / días estimados para terminar | `horas_restantes / horas_promedio_semana` |
| Sesiones estimadas que faltan | `horas_restantes / duración_promedio_de_sesión_en_horas` |
| Fecha estimada de finalización | hoy + días estimados |

## 4. Reutiliza el mismo cálculo que K-Means, no lo duplica

Las 6 métricas de patrón de asistencia (horas acumuladas, número de sesiones, duración promedio, horas/semana, sesiones/semana, variabilidad semanal) se extrajeron de `StudentClusteringService` a una clase nueva, **`AttendanceFeatureCalculator`**, que ahora usan tanto el agrupamiento K-Means como esta predicción — miden exactamente lo mismo para un estudiante, ya sea que se esté comparando contra el resto (K-Means) o viendo su propio progreso (esta ventana). Evita que las dos features calculen "horas por semana" de dos formas ligeramente distintas por accidente.

`StudentProjectionService` toma esas 6 métricas y les agrega la aritmética específica de la proyección (horas restantes, semanas/días/fecha estimados, sesiones restantes) — esa parte sí es exclusiva de la predicción, no del agrupamiento.

## 5. Casos sin estimación posible — no se inventa un número

- **Sin ninguna sesión todavía**: `tiene_datos: false`. La página muestra un mensaje simple en vez de una tabla vacía o ceros que parecerían datos reales.
- **Meta ya cumplida** (`horas_acumuladas >= horas_meta`): `meta_cumplida: true`, `horas_restantes: 0`, y **no** se calcula semanas/días/fecha — no tiene sentido proyectar hacia una meta que ya se alcanzó. La página muestra un mensaje de felicitación en su lugar.
- **Ritmo de 0 horas/semana** (con datos, pero `horas_promedio_semana` redondea a 0 — un caso extremo): tampoco se estima fecha, para no dividir entre cero ni mostrar un infinito. `semanas_restantes_estimadas` queda `null`, y la página lo explica en vez de fallar.

## 6. Dónde vive cada parte

```
backend/app/Services/AttendanceFeatureCalculator.php   — las 6 métricas compartidas (nuevo, extraído de StudentClusteringService)
backend/app/Services/StudentClusteringService.php       — ahora usa AttendanceFeatureCalculator (sin cambios de comportamiento)
backend/app/Services/StudentProjectionService.php        — la proyección personal (nuevo)
backend/app/Http/Controllers/Api/AttendanceSessionController.php  — método myPrediction() (nuevo, junto a mySummary/myAttendance)
frontend/src/pages/student/StudentPrediction.tsx          — la página (nueva), ruta /prediccion
```

No hace falta Apache para esta función — a diferencia del enrolamiento facial y del agrupamiento K-Means, `StudentProjectionService` no invoca ningún subproceso de Python; toda la aritmética es PHP puro sobre datos que ya están en PostgreSQL.

## 7. Pruebas

`backend/tests/Feature/Attendance/StudentPredictionTest.php` (5 pruebas): sin sesiones todavía, proyección completa verificada a mano (mismo caso de referencia del estudiante perfectamente constante que ya se usó para K-Means: 4 sesiones de 2h, una por semana durante 4 semanas — horas/semana=2, variabilidad=0, y a partir de ahí toda la aritmética de semanas/días/sesiones/fecha restantes verificada con los números exactos), meta ya cumplida, admin no puede llamar el endpoint (no tiene `student`), invitado sin sesión rechazado.

Verificado también en un navegador real (Playwright): estudiante de prueba con 6 sesiones sembradas, la página muestra correctamente el progreso, el ritmo y la estimación completa, con los números coincidiendo con lo calculado a mano.
