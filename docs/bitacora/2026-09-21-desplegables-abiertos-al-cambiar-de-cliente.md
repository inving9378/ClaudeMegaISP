# Fix — Desplegables (Estado/Municipio/Colonia y otros) quedaban rotos al pasar de un cliente a otro

## 2026-09-21 14:10 — Reporte de Irving y corrección

### Reporte

Irving (vía el usuario, "esto ya se había visto antes — sigue pasando"): al entrar a
la ficha de un cliente, algunos desplegables aparecen "abiertos" — la pantalla se ve
rota. Recargar la página (F5) lo corrige, pero vuelve a pasar cada vez que se navega
de un cliente a otro, y ocurre en varias pestañas que tienen desplegable.

### Investigación

Descarté primero acordeones Bootstrap (`.accordion-collapse`) y el sidebar (ambos
verificados sin problema, con Playwright real navegando entre clientes). El hallazgo
real estaba en los **selects "enriquecidos" con Choices.js** (`shared/Select*.vue`,
9 componentes distintos que envuelven un `<select>` nativo con esa librería).

**Causa raíz confirmada, código en mano:** estos componentes son reusados por Vue
entre un cliente y otro sin desmontarse/remontarse — el mismo mecanismo de
singleton compartido (`hook/crudHook.js`) ya documentado en este repo para el bug
"la ficha mostraba datos del cliente anterior" (commits `7041b920`+`a04da21c`,
ver `CLAUDE.md`). Ese fix cubrió el FORM (`dataForm`), pero **no** los widgets de
Choices.js dentro de los selects — a esos nunca se les avisó que el registro había
cambiado.

De los 9 componentes, 3 tenían el problema real:

1. **`Select2EstadoMunicipioColoniaComponent.vue`** (Estado + Municipio + Colonia,
   juntos en un solo componente — exactamente lo que se ve en la ficha del cliente,
   sección "Dirección"). **No tenía NINGÚN watch sobre `modelValue`** — los 3
   selects se construían una sola vez en `onMounted` y JAMÁS volvían a actualizarse.
   Si el componente se reusaba al cambiar de cliente, se quedaban mostrando el
   Estado/Municipio/Colonia del cliente ANTERIOR indefinidamente (hasta F5) — y si
   alguno se había dejado con el desplegable de Choices.js abierto, seguía abierto.
2. **`Select2Component.vue`** (11 campos en el sistema, ej. Estado/Municipio del
   catálogo admin, cliente potencial de un Ticket) — sí tenía un watch sobre
   `modelValue`, pero solo reasignaba la selección (`removeActiveItems` +
   `setChoiceByValue`) **sin cerrar el desplegable** si había quedado abierto.
3. **`SelectComponentWithSearch.vue`** (1 campo) — mismo patrón/mismo hueco que el
   anterior.

Los otros 6 (`SelectComponent.vue` — 178 campos, la inmensa mayoría del sistema —,
`SelectSrcComponent.vue`, `SelectComponentTask.vue`, y 3 más de uso puntual) **ya
tenían el patrón correcto**: destruyen el widget de Choices.js por completo y lo
reconstruyen desde cero cuando el registro cambia, lo que de paso cierra cualquier
desplegable que hubiera quedado abierto.

### Corrección

Aplicado a los 3 componentes con el hueco el mismo patrón ya probado (usado con
éxito en los 178 campos de `SelectComponent.vue`): **destruir y reconstruir** el
widget de Choices.js completo cada vez que cambia el registro, en vez de solo
reasignar el valor seleccionado.

- `Select2EstadoMunicipioColoniaComponent.vue`: agregado `watch(() => props.modelValue, ...)`
  que, si el componente se reusa con un cliente distinto, vuelve a pedir los datos
  (`getValueDB`) y reconstruye los 3 selects en cascada (Estado→Municipio→Colonia).
  También se agregó limpieza al desmontar (`onBeforeUnmount`), que tampoco existía.
- `Select2Component.vue` / `SelectComponentWithSearch.vue`: su watch de
  `modelValue` ahora destruye y reconstruye el widget en vez de solo reasignar la
  selección — mismo mecanismo, sin cambiar el comportamiento visible cuando SÍ
  cambia de verdad (input del usuario sigue funcionando igual).

**Sin tocar:** `Select2TypeTemplateSelectComponent.vue` (selector de plantilla en el
modal "Generar Contrato", 1 solo campo en todo el sistema) tiene el mismo hueco,
pero vive dentro de un modal que normalmente sí se desmonta/remonta al abrir/cerrar
— riesgo mucho menor. Queda anotado para revisar si algún día se reporta ahí
también, no se tocó en esta pasada para no ampliar el alcance sin evidencia.

### Verificación (Playwright, datos reales)

Con dos clientes reales de colonias distintas (Ex-hacienda Santa Inés vs. La
Candelaria): cargar el primero, navegar por SPA al segundo, y volver al primero —
en los tres pasos Estado/Municipio/Colonia mostraron **exactamente** los datos
correctos de cada cliente (nunca datos pegados del anterior), y ningún desplegable
quedó con la clase `is-open`. `npm run dev` compila limpio.
