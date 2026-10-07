## 2026-10-07 — Módulos IA: IA general por módulo + IA propia por función

Rama `ia-general-por-modulo` (worktree `/home/meganet/megaisp-wt-integrations`). Sigue el trabajo de IA por
módulo (`docs/bitacora/2026-10-05-ia-cambio-de-llave.md`).

Pedido: poder elegir un proveedor y modelo **general por módulo** que se aplique a todas sus funciones, y
seguir pudiendo elegir uno **individual** en una función; esa se queda con el suyo y las demás con el
general. Aplica a proveedor y a modelo.

### Qué se veía → qué pasa ahora

- **Había que elegir integración y modelo función por función** (Marketing tiene 7) → cada módulo tiene arriba
  una fila **«General del módulo»** (integración + modelo + Probar + Guardar). Todas las funciones que no tengan
  una propia usan la general.
- **En cada función**, la primera opción de la lista ahora es **«Usar la general (…)»**. Se puede:
  - dejarla así → usa integración y modelo de la general;
  - elegir otra integración y modelo → se queda con la suya aunque cambie la general;
  - dejar la integración en «Usar la general» y escribir **solo otro modelo** → usa la llave de la general con
    ese modelo (el campo muestra «De la general: …» cuando está vacío).
- **El estado de cada función dice de dónde sale su IA** («Activa · usa la general», «Activa · propia»,
  «Activa · general con modelo propio») y abajo qué integración y modelo usa de verdad. Si la general no le
  sirve (p. ej. Claude en una función de voz), lo dice en rojo y explica qué hacer.
- **Las funciones de voz** (voz de María, de cobranza, de videos, transcripción) toman la llave de la general
  pero no su modelo de chat: usan su modelo de audio por defecto (tts-1 / whisper-1) o el que se les escriba.
- **Al guardar una general** avisa: qué funciones no pueden usarla (p. ej. «Claude no sirve para: Voz de los
  videos — necesita voz») y, si cambió de proveedor (Claude ↔ OpenAI), qué modelos propios se quitaron porque
  eran del proveedor anterior y ya no existirían en el nuevo.
- **Quitar la general** avisa qué funciones quedan sin IA.
- Una integración usada como general **no se puede borrar** y al desactivarla avisa «IA general de «X»».
- Lo ya asignado hoy (14 funciones en dev) **no cambia**: son asignaciones propias y siguen igual.

### Piezas

- `IA::resolver()`: primero lo propio de la función; lo que no tenga propio sale de la general del grupo
  (`ia_asignaciones.clave = "grupo:<slug>"`, sin migración: la tabla ya admitía cualquier clave).
  Nuevos: `IA::efectivo()`, `claveGeneral()`, `grupos()`, `funcionesDe()`, `probarGeneral()`; `usosDeIntegracion()`
  nombra las generales.
- `IaModulosController`: `index` devuelve `generales` y el `efectivo` de cada función; `update` acepta «solo
  modelo»; nuevos `updateGeneral` y `probarGeneral` (rutas `PUT|POST api/hub/ia-modulos/general/{grupo}[/probar]`,
  mismos permisos que las de función).
- `IntegrationsHubView.vue`: fila general por módulo, opción «Usar la general», estados y avisos.

### Verificación (transacción + rollback; una consulta real mínima a OpenAI)

- General Marketing = Claude → función sin propia usa Claude; la de voz marca que Claude no hace voz.
- General = OpenAI → función de chat usa gpt-4o-mini; la de voz usa tts-1 con la llave de OpenAI.
- Solo modelo (gpt-4o / tts-1-hd) con la llave de la general → OK; solo modelo sin general → rechazado con
  explicación.
- Cambiar la general de OpenAI a Claude → quita el modelo propio de chat (avisa) y avisa la función de voz.
- Funciones con IA propia no cambian al mover la general.
- Quitar la general → avisa qué queda sin IA y limpia los «solo modelo».
- Integración usada como general → borrar bloqueado (422).
- Probar general con Claude sin saldo → explica «sin saldo»; probar función que hereda OpenAI → «Funciona».
- Frontend compila.

### Pendiente

- Validación visual en el navegador.
