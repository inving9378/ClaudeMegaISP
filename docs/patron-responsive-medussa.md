# Patrón responsive Medussa

> Molde establecido con la pestaña **Hoja de ruta** (`RoadmapTab.vue`).
> Aplicar módulo por módulo conforme se cierren. Sin librerías nuevas.

---

## Breakpoints

| Nombre  | Rango          | Media query                                        |
|---------|----------------|----------------------------------------------------|
| Móvil   | < 640 px       | `@media (max-width: 639px)`                        |
| Tablet  | 640 – 1023 px  | `@media (min-width: 640px) and (max-width: 1023px)`|
| Desktop | ≥ 1024 px      | default (sin media query)                          |

**Contexto del proyecto:** el sidebar se colapsa a `max-width: 992px` (Bootstrap).
A 640 px el layout ya no tiene sidebar visible, por eso ese es el corte de "móvil".

---

## Reglas generales

### Grids
- Desktop: 4 columnas (`display: flex; flex-wrap: wrap`).
- Tablet: 2 columnas (`display: grid; grid-template-columns: 1fr 1fr`).
- Móvil: 2 columnas o stack, dependiendo del contenido.

```css
/* Tablet */
@media (min-width: 640px) and (max-width: 1023px) {
    .mi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
}
/* Móvil */
@media (max-width: 639px) {
    .mi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
}
```

### Filtros de pestaña / chips horizontales
En móvil, los filtros se ponen en scroll horizontal sin barra visible.
El botón de acción principal (ej. "Agregar") va en fila propia, ancho completo.

**Template:** envolver los botones de filtro en `<div class="rdm-filter-row">` (o similar);
el botón de acción queda fuera del wrapper como hermano.

```css
/* Desktop: el wrapper es transparente al flexbox padre */
.mi-filter-row { display: contents; }

@media (max-width: 639px) {
    .mi-filters { flex-direction: column; gap: 8px; align-items: stretch; }
    .mi-filter-row {
        display: flex; gap: 6px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .mi-filter-row::-webkit-scrollbar { display: none; }
    .mi-filter-btn  { flex-shrink: 0; white-space: nowrap; }
    .mi-action-btn  { margin-left: 0; width: 100%; }
}
```

### Items de lista (tarjeta con título + metadatos)
En desktop los tags/badges van en línea con el título.
En móvil el título ocupa su propia fila y los tags bajan a una segunda fila horizontal.

**Template mínimo:**
```html
<div class="mi-item-title">
    <span class="mi-item-text">{{ item.title }}</span>
    <div class="mi-item-tags">
        <span class="tag-prio">...</span>
        <span class="tag-ver">...</span>
    </div>
</div>
```

```css
/* Desktop */
.mi-item-title { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.mi-item-text  { flex: 1; min-width: 0; }
.mi-item-tags  { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }

/* Móvil: texto arriba, tags abajo */
@media (max-width: 639px) {
    .mi-item-title { flex-direction: column; align-items: flex-start; gap: 4px; }
    .mi-item-text  { flex: unset; }
}
```

### Acciones en formularios / detalle expandible
En móvil, botones de acción se apilan verticalmente con ancho completo.

```css
@media (max-width: 639px) {
    .mi-actions { flex-direction: column; align-items: stretch; gap: 8px; }
    .mi-actions .btn { width: 100%; padding: 10px 16px; margin: 0 !important; }
}
```

### Inputs de bitácora / comentarios
En móvil, el par input + botón se apilan verticalmente.

```css
@media (max-width: 639px) {
    .mi-input-row { flex-direction: column; gap: 8px; }
    .mi-input-row .btn { width: 100%; }
}
```

---

## Tap targets (accesibilidad móvil)

Mínimo recomendado: **44 × 44 px**.

| Elemento            | Desktop | Móvil   |
|---------------------|---------|---------|
| Botón circular/icono | 28–32px | ≥ 44px  |
| Checkbox            | 15px    | 24px    |
| Botones de texto    | auto    | padding 10px 16px |
| Botones de mover/borrar | auto | min 36×36px |

```css
@media (max-width: 639px) {
    .mi-icon-btn { min-width: 44px; min-height: 44px; display: flex; align-items: center; justify-content: center; }
    .mi-checkbox  { width: 24px; height: 24px; }
}
```

---

## Tema / colores

- **Nunca** usar `@media (prefers-color-scheme: dark)`.
- Las media queries responsive solo cambian **layout y tamaños**, no colores.
- Los colores los gestiona `[data-layout-mode=dark]` + el ref `darkMode` de `appConfig.js`.
- Si se necesita un color específico en un estado responsive, usar las mismas variables/clases que el componente usa en desktop.

---

## Referencia de implementación

`resources/js/components/module/releases/RoadmapTab.vue` — primera pantalla
del sistema convertida al patrón. Leer su `<style scoped>` como referencia canónica.
