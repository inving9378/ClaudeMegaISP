# Item #9990775 — Reglamento de ventas y comisiones: texto fuente no disponible en el repo (verificación)

## Objetivo del item

Subir `docs/reglamento-ventas-comisiones.md` con las reglas numeradas **R1…R30** y los casos de
prueba **TC1…TC12** "tal como Irving los aprobó", para que sea la fuente única de verdad que citan
los items #9990782 (motor de comisiones), #9990783 (motor de maduración), #9990784 (Convenio
Individual de Comisiones) y #9990787 (migrar Embajadores al motor unificado) — todos parte de la
misma fusión "Vendedores + Talento" (prompts semilla #9990773/#9990774).

## Búsqueda realizada (exhaustiva, antes de concluir que no existe)

1. `grep` recursivo en todo el árbol del repo (excluyendo `node_modules/`, `vendor/`) de:
   `reglamento`, `R1…R30`, `TC1…TC12`, `R15`, `TC3`, "quitar el IVA"/"desglosar IVA"/"sin IVA".
   Sin resultados relevantes fuera de las menciones al propio item en `docs/bitacora-sesiones.md`
   y los prompts semilla #9990773/#9990774.
2. `find` de cualquier archivo con "reglamento" en el nombre → los únicos hallazgos son las
   migraciones y la plantilla HTML de Talento (`03-reglamento-interior-trabajo.html`) del
   **Reglamento Interior de Trabajo** (documento laboral RH exigido por la ley mexicana, con su
   propia "comisión mixta" — una comisión obrero-patronal de higiene y seguridad, sin relación
   alguna con comisiones de venta). Verificado línea por línea: no contiene reglas numeradas de
   ventas ni casos de prueba.
3. Búsqueda en la propia tabla `roadmap_items` (todas las columnas de texto: `description`,
   `prompt`, `comentarios_claude`, `reporte_tecnico`) por `R15`/`TC3` → solo aparecen en los
   prompts semilla #9990773/#9990774 (que **citan** las reglas como referencia externa, sin
   transcribir su contenido completo) y en la descripción de #9990783 (mismo patrón: cita "R15"
   y "TC3" al pasar, sin el texto completo del reglamento).
4. `git log --all` por "reglamento" → mismo resultado: solo commits del Reglamento Interior de
   Trabajo (RH), ninguno de un reglamento de ventas/comisiones.
5. `docs/talento-comisiones-migracion-analisis-item-9990453.md` (el análisis más cercano al tema)
   describe el **código** existente (`SalesCommissionPayment`, IVA opcional, etc.) pero no cita
   ningún reglamento formal con reglas numeradas — es documentación de comportamiento de código,
   no del reglamento de negocio.

## Conclusión

El reglamento de ventas y comisiones que el prompt semilla de la fusión da por "ya aprobado por
Irving" **no está en el repositorio ni en ningún sistema interno accesible** (ni como archivo, ni
como texto pegado en algún item de la Hoja de Ruta, ni en la bitácora de sesiones). Los fragmentos
que sí aparecen citados al pasar en los prompts semilla —la regla **R15** (tope de 80% en la mejora
de comisión automática para los paquetes de $349 y $449) y que el **TC3** debe fallar por ser "el
método incorrecto de quitar el IVA"— confirman que el documento existe y tiene contenido real y
específico, pero solo como conocimiento externo/verbal de Irving, tal como anticipaba la propia
descripción de este item.

Siguiendo el propio criterio de aceptación del item ("si el texto fuente no estaba disponible en
ningún sistema del repo, el item se cierra igual documentando de dónde salió, nunca inventando
contenido") y la regla explícita de alcance ("NO interpretar o resolver ambigüedades del
reglamento por cuenta propia"), **no se crea `docs/reglamento-ventas-comisiones.md` con contenido
inventado o reconstruido a partir de los fragmentos sueltos** — hacerlo sería adivinar 28 de 30
reglas y 12 de 12 casos de prueba a partir de dos menciones parciales, exactamente lo que el
prompt semilla prohíbe ("no inventes reglas de negocio que no estén ahí").

## Qué sigue

Se crea el item de respuesta `[RESPUESTA]` (ver Hoja de Ruta) pidiendo a Irving el documento/fuente
original del reglamento (Word, PDF, Google Doc, o el texto pegado directo) para poder completar
este item con el contenido real. Mientras tanto, los items que dependen de este reglamento como
fuente (#9990782, #9990783, #9990784, #9990787) deben seguir bloqueados — sus propios prompts ya
anticipan este bloqueo explícitamente ("si el reglamento (#0) no había cerrado... este item se
bloquea y no se ejecuta con reglas inventadas").
