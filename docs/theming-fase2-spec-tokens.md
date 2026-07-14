# Theming unificado — Fase 2: spec del sistema de tokens (item #333)

Spec de diseño (nivel C) para la Épica de theming unificado. Decisión de Irving registrada en el
log del item #333 (2026-07-14, `opcion_elegida` de `q1`): **Opción B — definir plantillas
iniciales concretas ahora**, con acento base `#7dd3fc`, antes de tocar ningún módulo.

Fase 3 (migración módulo por módulo) queda **diferida** (respuesta de `q2`: "Diferir el orden de
módulos hasta que Fase 2 esté cerrada") — este documento y su commit asociado **no migran ningún
módulo ni construyen el selector de UI**; eso es explícitamente Fase 3.

## 1. Punto de partida — NO se rediseña desde cero

La auditoría de Fase 1 (wt-3, 2026-07-12) encontró que ya existe una capa de tokens en curso
("Tema 0"): `resources/css/dark-light-tokens.css`, con dos plantillas (`light`/`dark`)
seleccionadas vía `body[data-layout-mode]`. Esa capa es la **fuente única de verdad** y sigue
siéndolo — Fase 2 la EXTIENDE (agrega la 3ª plantilla + formaliza el acento de marca), no la
reemplaza.

## 2. Las 3 plantillas iniciales (aprobadas)

| Plantilla | Atributo `data-layout-mode` | Estado |
|---|---|---|
| **Claro** | `light` | Ya existe (Tema 0) — sin cambios en esta fase |
| **Oscuro** | `dark` | Ya existe (Tema 0) — sin cambios en esta fase |
| **Alto contraste** | `alto-contraste` | **Nueva** — agregada en este commit (bloque CSS aditivo) |

La plantilla "Alto contraste" es una variante independiente pensada para accesibilidad (WCAG AA/AAA):
negro puro / blanco puro, bordes más marcados, sin grises intermedios de baja diferenciación.

## 3. Capa de tokens — categorías (sin cambios de forma, ya vigentes en Tema 0)

- **Backgrounds**: `--bg-primary`, `--bg-secondary`, `--bg-surface`, `--bg-hover`
- **Texto**: `--text-primary`, `--text-secondary`
- **Bordes / efectos**: `--border-default`, `--shadow-card`
- **Acento de marca**: `--accent`
- **Semántico**: `--success`, `--warning`, `--danger`, `--info`

Las 3 plantillas rellenan exactamente este mismo set de variables — ningún módulo que ya consuma
estos tokens necesita cambiar código para soportar la plantilla nueva.

## 4. Acento de marca unificado — `#7dd3fc`

Irving aprobó `#7dd3fc` (celeste/sky) como **acento base de la marca** para el sistema de theming
unificado hacia adelante. Los tokens `--accent` de `light`/`dark` **NO se tocan en esta fase**
(son valores ya en uso — cambiarlos ahora sería una migración de facto sin pasar por Fase 3, y
violaría la regla de estabilidad: no tocar código funcional ya aprobado). Quedan registrados
como candidato de unificación **para cuando Fase 3 decida el orden de migración**.

La plantilla nueva "Alto contraste" sí nace usando `#7dd3fc` como su `--accent` (no hay nada
previo que romper: es plantilla nueva, cero consumidores).

## 5. Reconciliación Bootstrap + Quasar + CSS propio (para Fase 3, documentado ahora)

- **Bootstrap**: ya referenciado parcialmente (`--accent: var(--bs-primary, ...)` con fallback).
  Fase 3, módulo por módulo, puede reforzar el puente completando `--bs-*` desde los tokens en
  vez de al revés.
- **Quasar UMD**: sin pipeline SCSS (es UMD/CDN) → no hay variables Quasar en build-time. El
  puente viable es runtime: `Quasar.Dark.set(bool)` sincronizado con `data-layout-mode` (hoy no
  se usa). Evaluar en Fase 3 cuando se aborde el primer módulo con más superficie Quasar.
- **CSS propio (`--mf-*`, `--wr-*`, etc.)**: exclusiones ya documentadas en el propio
  `dark-light-tokens.css` (MegaFamilia hasta Tema 0.5, War Room hasta Tema 6, WhatsApp Panel
  hasta Tema 5). Sin cambios — Fase 3 las aborda por módulo.

## 6. Selector de plantilla en la UI — explícitamente FUERA de esta fase

El prompt original lista "Construir el selector de plantilla/tema en la UI" bajo **Fase 3**, y
Irving difirió el orden/arranque de Fase 3. `ModeVisualBody.vue` / `appConfig.js` (mecanismo
actual de toggle claro/oscuro por pestaña) **no se tocan en este commit** — extenderlos a 3
plantillas (el toggle hoy es binario: `darkMode = data-layout-mode !== "light"`) es trabajo de
Fase 3.

## 7. Qué se ejecutó en este pase (item #333)

- Este documento (spec de Fase 2).
- Bloque CSS aditivo `[data-layout-mode="alto-contraste"]` en `resources/css/dark-light-tokens.css`
  — mismo set de variables que `light`/`dark`, sin tocar los bloques existentes. Cero
  consumidores hoy (nada en el sistema escribe `data-layout-mode="alto-contraste"` en el body)
  → cero riesgo de regresión visual.

## 8. Qué queda pendiente (Fase 3, requiere que Irving fije orden de módulos)

- Selector de plantilla en la UI (3 opciones) + persistencia por usuario.
- Migración módulo por módulo a los tokens (uno por rama, con verificación visual claro/oscuro/alto
  contraste).
- Decidir si `--accent` de `light`/`dark` migra a `#7dd3fc` como parte de esa migración, o se
  deja como está.
