# Torre de control V2 — qué trajo

> **Origen:** este contenido vivía como banner verde permanente en el Panorama de `/releases`
> (`TorreControl.vue`, marcador #560). Se retiró de la pantalla el **2026-08-19**: ya está aplicada y
> verificada, y un anuncio permanente deja de leerse y sólo ocupa espacio.
>
> La versión sigue visible como dato en el título de la Torre (**«Torre de control V2»**); el detalle
> de qué trajo vive aquí. No se borró nada: se movió.

## Lo que entregó la v2

1. **API de alta de items + historial.** `POST /{token}/item` y la variante GET en base64url para el
   fetcher de Cowork. Tabla `roadmap_item_reports` (append-only) + `RoadmapReportService`: antes cada
   terminal concatenaba a mano sobre `comentarios_claude` y con seis escribiendo se pisaban. Esa
   columna quedó como espejo legible acotado, no como fuente.
   Además `estado_cola` (accessor **derivado**, no columna) y la terminal asignada.

2. **Thomas — autoridad determinista.** El eslabón que faltaba entre las seis terminales e Irving.
   Política por coincidencia de términos, **sin llamada a IA**: la terminal corre `circuito:consultar`
   y recibe respuesta en el acto; el contrato es el **exit code** (`0` procede, `1` detente).
   Cinco categorías de escalamiento — producción · borrar datos · gastar dinero ·
   credenciales/seguridad · spec contradictorio. Todo lo demás lo decide Thomas.

3. **Harness no-interactivo con la regla de oro.** `deploy/circuito/prompt-item.txt`: ante duda,
   opción recomendada → avanza → registra. Revisión **posterior**, no previa. La terminal ya no puede
   escalar a Irving por su cuenta.

4. **`docs/politica-thomas.md`** — la política escrita, anexada al manual que sirve la API externa.

## Prueba real

Items **#555** y **#556** · commits `ea744de0`, `502a4866`.
