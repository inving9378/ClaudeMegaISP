# El chat con Thomas — CONTRATO (entrega G, todavía sin construir)

> Registro del encargo §7 y §7b, dictado el 2026-08-25. **No hay código de esto.** El chat va al
> final a propósito: es la cara del vigilante y no puede contar lo que el cuerpo todavía no sabe
> medir. Se escribe ahora para que el contrato no se pierda entre aquí y entonces.

## 1. Dónde vive

Un solo lugar en la Torre: la conversación **y** la bandeja —lo que resolvió, lo que hizo y avisó,
lo que está preguntando— ordenada **por nivel, no por hora**. Un solo sitio, no dos. Con permiso
propio: sin él, la pestaña no se renderiza.

## 2. La regla más importante

**Nunca responde sobre el estado del sistema desde memoria.** Vuelve a medir, o cita la medición
con su hora ("medido hace 40 s"). Si no lo sabe, lo dice y ofrece medirlo. Un chat que da datos
viejos con tono seguro es peor que no tener chat — ya pasó dos veces, con los workers y con la
base vacía.

Con la base caída responde **en modo mínimo**, desde archivo, y lo dice con esas palabras: "estoy
en modo mínimo, la base no responde, esto es lo que sé". La entrega A ya deja esa mitad construida:
`estado.json` trae `modo: minimo|completo` y la edad de cada medición.

## 3. Qué tiene que saber contestar

- «¿Por qué no avanza el item #X?» → la compuerta exacta que lo detiene y qué haría falta para soltarla.
- «¿Qué está corriendo ahora?» → terminales, items, desde cuándo.
- «¿Qué pasó mientras no estuve?» → resumen desde la última visita: qué resolvió solo, qué hizo y
  avisó, qué está esperando. **El que más importa.**
- «¿Por qué hiciste X?» → la evidencia que lo justificó, no una explicación reconstruida.
- «¿Cómo está el disco / la RAM / el gasto?» → cifra y tendencia.
- Fuera del sistema: lo dice. No es un asistente general.

## 4. Cómo se comporta

- Una petición de Irving por chat **autoriza lo que está en el nivel "ME PREGUNTA"**, pero **no**
  salta la confirmación en dos pasos de las acciones peligrosas ni el techo del autopilot.
- Las novedades llegan solas. Las de nivel **ALARMA** salen además por un canal que **no dependa
  de la Torre ni de la base**.
- La conversación persiste y es registro auditable.

## 5. §7b — Crear items desde el chat

| Regla | Qué significa |
|---|---|
| **Antes de guardar, pregunta** | Dos o tres preguntas concretas: qué hay que ver en pantalla para darlo por hecho, si toca producción, qué módulo primero. Si Irving dice "guárdalo así", lo guarda **incompleto y marcado como incompleto**. No lo obliga a contestar. |
| **Nunca guarda sin que Irving vea el borrador** | Ningún comentario se vuelve trabajo sin haberlo visto escrito. |
| **Distingue contarle de pedirle** | Ante la duda: «¿te lo levanto como item o sólo lo anoto?». Preguntar es barato; llenar la cola de comentarios, no. |
| **Busca antes de crear** | «Esto se parece a #126, ¿es el mismo o es otro?». Decide Irving. |
| **El item nace como cualquier otro** | Con módulo asignado —**nunca "Sin clasificar"**, que engorda el freno ya documentado—, con su bloque de canal de respuesta, y pasa por clasificación normal. **Que venga de Irving NO lo aprueba**: aprobar sigue siendo un paso aparte y explícito. |
| **Al revés también** | Cuando Thomas detecta algo, lo propone con la evidencia adjunta: «encontré esto, ¿te levanto el item?». |
| **Lo que no es item, no se vuelve item** | Corregir un comportamiento, cambiar un umbral o expresar una preferencia **ajusta su configuración** y él dice qué cambió y dónde quedó registrado. |

### Verificado: la búsqueda de parecidos la tiene que hacer él

Confirmado leyendo el código (2026-08-25): **el camino de alta no tiene deduplicación propia**.
Ni `RoadmapIntakeService` ni `RoadmapCircuitoService` tienen nada de huella, similitud o duplicado.
El único mecanismo que existe es **`auditor_fingerprint`**, y es exclusivo del auditor:
`AuditorService` calcula la huella del hallazgo, consulta `where('auditor_fingerprint', …)->exists()`
antes de crear y la sella al guardar.

O sea: cuando el chat cree un item, **la búsqueda de parecidos es responsabilidad de Thomas**, no
del sistema. Y conviene que Thomas selle también su propia huella al crear —reusando esa misma
columna— para que el peldaño 3 (items de investigación automáticos) no pueda convertir una cascada
de errores en una cascada de items.
