# Facelog — Etapa adicional: Interfaz visual del dispositivo del laboratorio

> No es una de las diez etapas originales del plan (`docs/01-analisis.md` a `docs/05-manual.md`), que ya se dieron por completas y verificadas. Se agrega después, a solicitud explícita del usuario, al notar que RF6 ("Mostrar retroalimentación clara al estudiante: reconocido / no reconocido / ya registrado") solo se cumplía por texto impreso en la consola de `recognition-app` — nunca se mostraba nada en pantalla al estudiante frente a la cámara.

---

## 1. Problema encontrado

`recognition-app/src/main.py` (Etapa 5) implementa correctamente todo el flujo de reconocimiento — captura, liveness, comparación, reporte a la API — pero es un script de **consola pura**: no abre ninguna ventana, ni siquiera muestra el video de la cámara. La única retroalimentación existente eran líneas de `print()`, visibles solo para quien esté mirando la terminal donde corre el proceso, no para el estudiante que se paró frente a la cámara.

Esto no era un defecto de una etapa completada — el análisis (RF6) siempre pidió retroalimentación al estudiante, y las etapas previas no la habían dejado pendiente por descuido, sino porque nadie había preguntado explícitamente por una interfaz gráfica hasta ahora.

## 2. Decisión de diseño

Se evaluaron tres opciones: no hacer nada (dejar la consola), una aplicación de escritorio con un framework de UI dedicado (`tkinter`/`PyQt`), o una ventana simple con OpenCV superpuesta sobre el propio video de la cámara.

**Se eligió OpenCV** (`cv2.imshow` + `cv2.putText`/`cv2.rectangle`), por ser la opción más simple que resuelve el problema real:

- OpenCV **ya es una dependencia obligatoria** del proyecto (`recognition-app/requirements.txt`, usada por `capture/camera.py` y por DeepFace) — no se agrega ninguna librería nueva.
- Permite mostrarle al estudiante el propio video en el que está parado, lo cual además ayuda a que se posicione bien frente a la cámara — un beneficio que un framework de ventanas sin video no daría gratis.
- Evita la complejidad de un framework de GUI completo (manejo de eventos, hilos separados para no bloquear la UI, empaquetado como ejecutable) que sería sobreingeniería para mostrar un mensaje de una línea sobre un video que de todas formas ya se está leyendo en el bucle principal.

Es la aplicación del mismo criterio de priorización del proyecto: funcionalidad y confiabilidad antes que complejidad.

## 3. Qué se implementó

### 3.1 `recognition-app/src/ui/feedback.py` (nuevo)

Clase `FeedbackOverlay`, sin dependencias del resto del proyecto salvo OpenCV:

- `set(text, level, duration)` — guarda el mensaje a mostrar, su categoría (`info`/`good`/`warn`/`bad`, cada una con su color) y por cuánto tiempo debe permanecer visible (por defecto 3 segundos) antes de volver al mensaje de reposo ("Facelog - colócate frente a la cámara").
- `draw(frame)` — dibuja una barra inferior semitransparente con el texto vigente sobre el frame recibido y lo devuelve. No conoce nada de reconocimiento facial ni de la API; solo sabe dibujar texto con expiración, lo que la hace trivial de probar de forma aislada (§4).

### 3.2 `recognition-app/src/main.py` (modificado)

- `handle_recognition_attempt()` recibe ahora un `FeedbackOverlay` y llama a `feedback.set(...)` en cada desenlace posible del intento de reconocimiento, en paralelo a los `print()` que ya existían (no se quitó ninguno — la consola sigue siendo útil para quien opere el equipo):

  | Desenlace | Mensaje en pantalla | Nivel |
  |---|---|---|
  | Varios rostros a la vez | "Solo una persona a la vez frente a la cámara" | advertencia |
  | Falla el parpadeo (liveness) | "No se detectó parpadeo, inténtalo de nuevo" | advertencia |
  | Sin coincidencia / confianza insuficiente | "No reconocido" | rechazo |
  | Evento aceptado, tipo `entry` | "Bienvenido, `<matrícula>`" | éxito |
  | Evento aceptado, tipo `exit` | "Hasta luego, `<matrícula>`" | éxito |
  | Sin conexión (se encola) | "`<matrícula>`: sin conexión, guardado para reintentar" | advertencia |
  | Rechazado por el backend | "`<matrícula>`: evento rechazado por el servidor" | rechazo |

  El mensaje usa la **matrícula**, no el nombre — `GET /api/sync/face-catalog` nunca entregó el nombre del estudiante a `recognition-app` (por diseño, ver `docs/02-diseno.md` §5), así que mostrar el nombre habría requerido ampliar ese contrato; se mantuvo el mismo dato que ya usaban los `print()` existentes, sin ampliar el alcance de esta etapa adicional más allá de lo pedido.

- El bucle principal ahora llama a `cv2.imshow(WINDOW_TITLE, feedback.draw(frame))` en cada iteración y a `cv2.waitKey(1)` para mantener la ventana activa y detectar la tecla de salida. Presionar **'q' o Esc** cierra la ventana y termina el programa limpiamente (además de seguir funcionando `Ctrl+C` en la terminal, como antes). `cv2.destroyAllWindows()` se ejecuta en un bloque `finally` para no dejar la ventana huérfana si algo falla.

### 3.3 Pruebas (`recognition-app/tests/test_feedback_overlay.py`, nuevo)

Prueba la máquina de estados de `FeedbackOverlay` (qué mensaje/color está activo antes y después de expirar) sin necesitar cámara ni pantalla real — `cv2.rectangle`/`cv2.putText` operan sobre un array de NumPy en memoria, no requieren un display. 4 pruebas nuevas, todas pasando junto con el resto de la suite (18 pruebas de pytest en total ahora, más las 3 de integración en vivo opcionales, sin cambios en esas).

## 4. Cómo verificarlo

No es verificable por el agente (requiere cámara y pantalla física), igual que el resto de `main.py` desde la Etapa 5. Para comprobarlo en el laboratorio:

```bash
cd recognition-app
venv\Scripts\activate
python src\main.py
```

Debe abrirse una ventana con el video de la cámara y, cada `RECOGNITION_WINDOW_SECONDS` (3 segundos), una barra inferior con el resultado del intento más reciente. Cerrar con `q`, `Esc`, o `Ctrl+C` en la terminal.

## 5. Alcance no cubierto (deliberado)

- No se agregó el nombre del estudiante en pantalla (solo matrícula) — requeriría ampliar `GET /api/sync/face-catalog`, fuera del pedido original de esta etapa.
- No se cambió nada del backend, del frontend, ni de la lógica de reconocimiento/negocio — esta etapa es exclusivamente la ventana de `recognition-app`.
- No se empaquetó como ejecutable independiente (`.exe`) ni se agregó un ícono de sistema — se sigue ejecutando igual que antes, con `python src/main.py`, solo que ahora abre una ventana además de escribir en la consola.
