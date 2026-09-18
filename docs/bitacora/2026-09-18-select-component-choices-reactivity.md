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

**Alcance de esta vuelta:** solo `SelectComponent.vue` (el más usado). El resto de la familia
Choices.js (`Select2Component.vue`, `SelectComponentTask.vue`, `SelectComponentWithSearch.vue`,
`SelectComponentWithSearchClient.vue`, `SelectTemplateListVerificationComponent.vue`,
`Select2WithLabelComponent.vue`, `SelectSrcComponent.vue`) comparte el mismo defecto en
distintos grados y queda pendiente de la misma auditoría/fix — igual que el ítem ya abierto en
CLAUDE.md para el antipatrón `$(document).on` sin `.off()` ("Falta auditar el resto del
codebase por el mismo patrón"), esto es la misma familia de bug aplicada a Choices.js en vez
de jQuery.

**Hallazgo adicional, no corregido (fuera de alcance de este fix):** `hook/comunValues.js:82`
— `export const isEdit = window.location.href.includes('editar');` es una constante calculada
UNA sola vez, al cargar el bundle — no reacciona a la navegación SPA. Puede quedar "congelado"
en `true`/`false` según cuál fue la PRIMERA URL cargada en la sesión, incluso al navegar después
a una pantalla de tipo distinto (crear↔editar). No es la causa del síntoma reportado (solo
afecta si se muestra o no el checkbox "usar como valor por defecto"), pero es la misma familia
de bug de estado congelado en SPA — vale la pena su propio item si se decide corregir.
