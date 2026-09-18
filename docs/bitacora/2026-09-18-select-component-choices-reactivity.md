## 2026-09-18 12:32 — SelectComponent.vue: Choices.js no se destruía/resincronizaba en SPA

**Reporte de Irving:** "a veces los selects tienen comportamientos extraños, a veces se
quedan desplegados desde el principio y otras esta como deshabilitados, hay que recargar
para que carguen bien."

**Investigación:**
- `resources/js/shared/SelectComponent.vue` es el componente detrás del tipo de campo
  `select-component` en `ComponentFormDefault.vue` (el formulario dinámico usado por ~90+
  CRUDs del sistema, vía `grep -rl "ComponentFormDefault"`).
- Envuelve un `<select>` nativo con Choices.js (`convertToSelect2`, `resources/js/helpers/
  Transform.js`). El `onMounted` lo inicializaba una vez y punto: **sin `onBeforeUnmount`
  que llame `.destroy()`, y sin ningún `watch` sobre `props.modelValue`**.
- Los CRUDs renderizan sus campos con `<template v-for="val in fieldsJson"><ComponentFormDefault
  :key="val" ...></template>` (ej. `ColonyCrud.vue:9`) — la llave es el objeto de config del
  campo, no un id primitivo. `fieldsJson` es un singleton module-level (`hook/crudHook.js`,
  ya documentado en CLAUDE.md por el bug de la ficha de cliente) con las MISMAS referencias
  de objeto por campo mientras se permanece en el mismo módulo. Al cambiar de registro
  (otro cliente, otra colonia, etc.) sin salir del módulo, Vue **patchea** la misma instancia
  de `SelectComponent` en esa posición del v-for en vez de desmontarla/remontarla —
  `setup()`/`onMounted` NO vuelven a correr, pero `props.modelValue` sí cambia al nuevo
  registro. Sin ningún watch que lo detecte, el widget visual de Choices.js se queda
  congelado en el valor/estado del registro anterior: a veces "abierto" (arrastra el
  `class="is-open"` de la última interacción), a veces con pinta de deshabilitado. Recargar
  la página fuerza que TODO monte de cero, por eso "arregla" el síntoma.
- Comparado contra el resto de la familia (`Select2Component.vue`, `SelectSrcComponent.vue`,
  `Select2TypeTemplateSelectComponent.vue`) — mismo copy-paste base, mismo problema de fondo
  en distintos grados: `Select2Component.vue` sí tiene un watch de `modelValue` pero tampoco
  destruye al desmontar; `SelectSrcComponent.vue` tiene un watch pero solo actualiza el `val`
  interno, nunca refresca el widget visual de Choices; `Select2TypeTemplateSelectComponent.vue`
  ya usa el patrón correcto (`destroy()` + reconstruir) pero solo para su propio caso de
  refresco en cascada type→template, no para su ciclo de vida completo.

**Fix aplicado (rama `fix/select-component-choices-reactivity-spa`, commit `948475a9`):**
`SelectComponent.vue` — `watch(() => props.modelValue, ...)` que resincroniza `val` (con guard
`syncingFromProp` para no disparar de rebote el POST de "guardar como valor por defecto" del
campo, que es una configuración de MÓDULO, no de registro) y reconstruye Choices.js
(`destroy()` + `convertToSelect2()` de nuevo) con el valor correcto; `onBeforeUnmount` destruye
siempre la instancia. Mismo patrón que ya usa el propio código en
`Select2TypeTemplateSelectComponent.vue::changeTemplate()`. `npm run dev` compiló limpio.

**Extendido a los 7 hermanos (commit `c1f08690`, misma rama):** Irving pidió extender el mismo
fix a toda la familia, no solo a `SelectComponent.vue`. Mismo copy-paste original, mismo
defecto en distintos grados:

- `SelectComponentTask.vue` — copia exacta del bug de `SelectComponent.vue` (sin watch de
  `modelValue`, sin destroy al desmontar). Mismo fix aplicado íntegro.
- `SelectSrcComponent.vue` — ya tenía un `watch(() => props.modelValue)`, pero solo
  actualizaba el `ref` interno `val` sin refrescar el widget visual de Choices.js (el "select"
  seguía mostrando el valor/opciones del registro anterior aunque el valor interno sí
  cambiara). Se completó con destroy+reconstrucción, igual que `SelectComponent.vue`.
- `Select2Component.vue`, `SelectComponentWithSearch.vue`, `SelectComponentWithSearchClient.vue`,
  `SelectTemplateListVerificationComponent.vue` — estos ya tenían un
  `watch(() => props.modelValue)` que llamaba `choice.value.setChoiceByValue(actual)`
  directamente sobre la instancia existente (sin destruir/reconstruir), pero (a) nunca
  destruían la instancia al desmontar el componente, y (b) no limpiaban la selección previa
  (`removeActiveItems()`) antes de fijar la nueva ni manejaban el caso de valor vacío/null. Se
  completaron ambos puntos + `try/catch` defensivo (Choices.js puede tronar si su contenedor ya
  no está en el DOM).
- `Select2WithLabelComponent.vue` — **sin ningún uso actual en el codebase**
  (`grep -rln "Select2WithLabelComponent" resources/js` solo encuentra el propio archivo). Se
  le aplicó el mismo destroy preventivo por consistencia, pero **no** se tocó un bug
  preexistente y no relacionado detectado de paso en su llamada a `convertToSelect2` (los
  argumentos `val`/`options.val` están en el orden equivocado respecto a la firma real de la
  función) — al no estar en uso por nada, no hay forma de probarlo en producción; se deja
  anotado aquí si algún día se reactiva este componente.

`npm run dev` compiló limpio después de los 7 cambios. Todo en la rama
`fix/select-component-choices-reactivity-spa` (2 commits: `948475a9` + `c1f08690`), pusheada,
pendiente de que Irving la revise/mergee y de una prueba visual en el navegador (no se pudo
probar en vivo esta sesión — sin herramientas de navegador disponibles).

Sigue pendiente, fuera de alcance de este fix, la misma familia de bug aplicada a jQuery
(`$(document).on` sin `.off()`, ítem ya abierto en CLAUDE.md: "Falta auditar el resto del
codebase por el mismo patrón") — es el mismo tipo de defecto (listener/estado que sobrevive al
desmontaje en la SPA), pero en un mecanismo distinto.

**Verificado en navegador real (Playwright + Chromium, commit `b092c177` incluido) — Irving pidió
probarlo antes de mergear:**
- Se leyó `resources/js/spa-nav.js` completo para confirmar el mecanismo real: `spaNavigate()`
  hace `window.__megaVueApp.unmount()` + `window.createMainApp()` en cada navegación (desmontaje y
  remontaje GENUINOS del árbol de Vue, no un patch en el sitio) — pero el **módulo JS** (y
  cualquier singleton module-level, como `fieldsJson`/`isEdit`) sobreviven porque los imports de
  JS no se re-evalúan. Confirma que el bug real es el singleton, no el ciclo de vida de Vue en sí.
- Con credenciales de prueba reales (`david_marsal`, rol DESARROLLADOR) se reprodujo el bug ANTES
  del fix: cargar `/crm/editar/763` (crm_status real = "Instalacion") y navegar por SPA (con
  `window.__spaNavigate`, el mismo mecanismo que un click real) a `/crm/editar/466` (crm_status
  real = "Interesado") deja el select **congelado mostrando "Instalacion"** — reproducción exacta
  del síntoma reportado. Con el fix aplicado (mismo flujo, misma sesión), el select muestra
  correctamente "Interesado".
- El fix de `isEdit` (commit `b092c177`) no rompió el fix de Choices.js — se re-corrió la misma
  prueba 763→466 después de aplicarlo y el resultado sigue correcto.
- **Bonus, hallazgo aparte sin tocar (fuera de alcance):** durante la prueba se vio en consola un
  error real y distinto: `500 POST /helper/get-value-colony-state-municipality — Unknown column ''
  in where clause` al montar `Select2EstadoMunicipioColoniaComponent` en el tab "Información" de
  CRM — un bug de backend no relacionado con Choices.js/isEdit, no investigado ni corregido aquí.

**Verificación adicional pedida por Irving — "las plantillas de CRM también se arreglaron"**
(sobre el fix de #9991219, Generar-vs-Previsualizar): se rastreó el flujo REAL con calma, porque
hay dos pares de rutas/componentes con el mismo nombre de método que NO son los que usa la UI viva:
- La UI real de "Generar Contrato" en CRM (`DocumentCrmCrud.vue` → `CrmTemplate.vue`) usa el
  componente compartido `TextTemplate.vue` (el mismo que usa `PlantillasClientes.vue` del lado
  Cliente) — su botón "Previsualizar" pega a la ruta GENÉRICA
  `/administracion/document_template/show_content_template`
  (`DocumentTemplateController::showContentTemplate()` → `returnPath()`, **sin** wrap) — no a
  `crm/document/show_content_template` (`DocumentCrmController::showContentTemplate()` →
  `saveTemporalTemplateAndReturnPath()`, que **sí** wrappea pero está **muerto**: 0 referencias en
  el frontend, igual que su análogo de Cliente y que `ContractTemplate.vue`, que tampoco lo importa
  nadie). Confirmado por grep exhaustivo antes de concluir nada.
- El botón "Generar" sí pega a la ruta real `crm/document/generate_contract/{id}` →
  `ContractCrmService::generateContractClient()`, que ya tenía el wrap quitado (mismo commit que
  el de Cliente).
- **Prueba end-to-end real con Playwright** sobre el lead CRM #763 (real, no sintético): abrir tab
  Documentos → "Generar Contrato" → tipo "Cliente Potencial" → plantilla "CONTRATO 6 MESES" →
  "Cargar" (82,876 caracteres de HTML) → "Previsualizar" (interceptando la respuesta blob real) →
  "Generar" (interceptando la respuesta JSON + descargando el PDF resultante). Resultado: **ambos
  PDFs, 29,933 bytes, 5 páginas cada uno**, diff byte a byte confirma que las únicas 62 posiciones
  distintas son `/CreationDate`+`/ModDate` (1 segundo de diferencia entre ambos renders) y el
  `/ID` del trailer (derivado del timestamp) — cero diferencia de contenido real. **Las plantillas
  de CRM sí quedaron arregladas**, mismo patrón y misma garantía que Clientes.
- Se limpió el documento de prueba (`document_crms` id 864 + `files` id 9044, ambos sobre el lead
  real #763) generado por esta verificación. El PDF físico
  (`storage/app/public/client/763/document/CONTRATO 6 MESES.pdf`) quedó huérfano en disco —
  `meganet` no tiene permiso de escritura sobre ese árbol (`www-data:www-data`, sin sudo sin
  contraseña disponible) para borrarlo; es exactamente el tipo de archivo que ya cubre la pantalla
  existente `crm/documentos-huerfanos`, 29 KB, sin dato real, sin URL pública nueva expuesta.

**Hallazgo adicional, no corregido (fuera de alcance de este fix):** `hook/comunValues.js:82`
— `export const isEdit = window.location.href.includes('editar');` es una constante calculada
UNA sola vez, al cargar el bundle — no reacciona a la navegación SPA. Puede quedar "congelado"
en `true`/`false` según cuál fue la PRIMERA URL cargada en la sesión, incluso al navegar después
a una pantalla de tipo distinto (crear↔editar). No es la causa del síntoma reportado (solo
afecta si se muestra o no el checkbox "usar como valor por defecto"), pero es la misma familia
de bug de estado congelado en SPA — vale la pena su propio item si se decide corregir.
