## 2026-09-21 — Talento Fase B, lote 1 (personal): tema Torre

**Contexto:** plan aprobado `ethereal-fluttering-simon.md`, Fase B (25 pantallas admin
de Talento sin el tema Torre). Primer lote temático: Colaboradores, Custodia,
Academia, Niveles, Escalafón, Puestos — 2486 líneas en total.

**Método:** 6 agentes en paralelo, cada uno con la receta exacta de 10 pasos
(extraída en la fase de investigación del plan) aplicada a UN solo archivo, sin
correr build ni git — verificación y compilación centralizadas después.

**Resultado por pantalla** (todas: `.tc-wrap` + `darkMode` de `hook/appConfig.js`,
`.tc-card`/`.tc-cardhead`, botones → `.tc-btn`+variante, badges → `.tc-status`+variante):
- **Colaboradores** (601 líneas): 5 botones de página convertidos, 4 badges, 7
  selects con `tc-select`. Los 4 botones DENTRO de modales se dejaron nativos
  (mismo criterio ya usado en las 4 pantallas migradas antes de este lote —
  Bootstrap estándar ya se recolorea gratis dentro de `.tc-wrap`).
- **Custodia** (168 líneas): sin estructura de tarjeta previa, se construyó desde
  cero. 1 botón, 3 badges. Un `bg-primary-subtle` decorativo (avatar) se dejó
  intacto a propósito — no es de los selectores que `dark_mode.scss` rompe.
- **Academia** (823 líneas, la más grande): 24 botones, 8 badges, 6 selects, 9
  tarjetas. Gap `bg-light` en la tarjeta de progreso, resuelto con clase local
  `aca-bg-neutral` (`var(--tc-bg2)`).
- **Niveles** (565 líneas): 8 botones, 10 badges (incluye reescritura de
  `rankColor()`), 3 selects, 4 tarjetas. Gap `bg-light` (×2) resuelto con clase
  local `tc-bg-neutral`.
- **Escalafón** (195 líneas): 3 botones, 1 select. Gap real encontrado:
  `table-warning` para resaltar el top-3 del ranking → clase local
  `escalafon-toprank` (`rgba(217,119,6,.12)`).
- **Puestos** (134 líneas): 3 botones, 1 badge. Sin gaps. Los botones del modal se
  dejaron nativos (mismo criterio que Colaboradores).

**Verificado:**
- Barrido `grep "badge bg-\|class=\"badge\""` sobre los 6 archivos → 0 resultados
  (cero badges sin convertir).
- `npm run dev` compiló limpio.
- Playwright real (usuario `david_marsal`), las 6 pantallas, claro y oscuro: las 6
  cargan sin errores nuevos en consola, `.tc-wrap` presente, capturas visuales
  confirman contraste correcto en ambos modos (tablas, botones, badges, tabs,
  resaltado de top-3 en Escalafón). El único warning de consola presente en las 6
  es un `<style>` inline preexistente y no relacionado (`megaisp-claude-test-pill`).

Rama `feat/talento-fase-b-torre-lote1-personal`, mergeada a `main`.
