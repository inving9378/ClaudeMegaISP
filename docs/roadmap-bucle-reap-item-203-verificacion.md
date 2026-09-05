# Item #203 — bucle reap sobre paraguas ya descompuesto (verificación)

## Contexto

Mismo patrón documentado repetidamente en `CLAUDE.md` (familia #738/#745/#830/#816/#818/#848/#852/
#905/#878/#906/#907/#924/#9990012/#917/#9990269): una vuelta previa (`wt-1`, 2026-09-05 16:09) hizo
lo correcto — confirmó con `circuito:cabida` que #203 (Expediente RH, Hijo E — pestaña "Documentos"
en perfil de colaborador y vendedor) NO CABÍA en una vuelta, y lo descompuso en 4 sub-items por
fase:

- **#9990358** — Hijo E1: permisos `documentos.ver`/`.gestionar` + extraer componente Vue reusable
  (Ver/Imprimir).
- **#9990359** — Hijo E2: Regenerar + Subir escaneado firmado con congelamiento de versión (depende
  de E1).
- **#9990360** — Hijo E3: pestaña Documentos en perfil de Vendedor + expediente ligero automático
  (depende de E1).
- **#9990361** — Hijo E4: acceso Documentos en perfil de Colaborador (modal reusando el componente,
  sin ficha con tabs — no existe hoy en Talento) (depende de E1).

Pero esa vuelta murió justo después de escribir la decisión (mismo minuto, 16:09) sin intentar
**cerrar** al padre — el evento de log inmediatamente siguiente es `soltar-claim` /
`claim_liberado_al_morir_la_vuelta`, no un intento de `estado_aprobacion = 'completado'`. El item
volvió a `aprobado_irving` con el claim liberado, sin nadie habiendo disparado el guard de paraguas.
Una vuelta posterior (esta, `wt-1`, 22:10) lo reclamó de nuevo desde el pool sin que hubiera trabajo
propio pendiente (todo el trabajo real ya vive en los 4 sub-items).

## Verificación (esta vuelta)

```
9990358: estado_aprobacion=requiere_irving    | worker_sid=  | origen_item_id=203
9990359: estado_aprobacion=pendiente_revision | worker_sid=  | origen_item_id=203
9990360: estado_aprobacion=pendiente_revision | worker_sid=  | origen_item_id=203
9990361: estado_aprobacion=requiere_irving    | worker_sid=  | origen_item_id=203
```

Los 4 hijos siguen intactos y sin reclamar — la descomposición original seguía siendo correcta,
nadie más la tocó.

## Corrección

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'`). A diferencia de los
casos anteriores de esta familia, aquí NO fue el guard **(2b) PARAGUAS** el que interceptó primero,
sino el guard **(1)** (`RoadmapItem.php` ~301-318): #203 es `nivel_riesgo = 'C'` y trae un `branch`
no vacío (`circuito/item-203-expediente-rh-hijo-e-pestana-document`, creado en algún ciclo anterior
de consulta/decisión) sin `merge_commit` — condición que ese guard intercepta ANTES de llegar al
bloque de paraguas, reenrutando a `aprobado_irving` + `esperando_merge_irving=true` +
`excluir_pool_automatico=true`. Verificado que esa rama no tiene ningún commit propio
(`git log main..circuito/item-203-...` vacío): es un artefacto de una vuelta anterior que corrió
`circuito:rama` sin llegar a escribir código (todas las vueltas de #203 terminaron en consulta o
decisión, nunca en edición). El resultado funcional es equivalente al de la familia (2b): el item
queda **autorizado, parqueado, fuera del pool automático**, y no se completa hasta que sus 4
sub-items cierren — el hook de cierre en cascada (`RoadmapItem.php:459-491`) sigue vigente para
ellos. Se limpió a mano `worker_sid`/`claimed_at` (el guard (1) no lo hace como sí lo hace el guard
(2b) por el fix de `#898`), para no dejar el claim de esta vuelta pegado a un item ya parqueado.

```
estado_aprobacion final: aprobado_irving
excluir_pool_automatico: true
esperando_merge_irving:  true   (branch vacía de commits — sin efecto práctico, no hay nada que mergear)
worker_sid / claimed_at: null
```

## Sin cambio de código de negocio

El trabajo técnico real (componente de Documentos, permisos, regenerar/congelar, pestaña de
Vendedor, acceso desde Colaborador) sigue en #9990358 (`requiere_irving`), #9990359
(`pendiente_revision`), #9990360 (`pendiente_revision`) y #9990361 (`requiere_irving`), pendientes
de que Irving/el revisor los destrabe en orden (E1 es prerequisito de los otros tres).
