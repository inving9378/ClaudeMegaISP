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

**Hallazgo adicional, no corregido (fuera de alcance de este fix):** `hook/comunValues.js:82`
— `export const isEdit = window.location.href.includes('editar');` es una constante calculada
UNA sola vez, al cargar el bundle — no reacciona a la navegación SPA. Puede quedar "congelado"
en `true`/`false` según cuál fue la PRIMERA URL cargada en la sesión, incluso al navegar después
a una pantalla de tipo distinto (crear↔editar). No es la causa del síntoma reportado (solo
afecta si se muestra o no el checkbox "usar como valor por defecto"), pero es la misma familia
de bug de estado congelado en SPA — vale la pena su propio item si se decide corregir.
