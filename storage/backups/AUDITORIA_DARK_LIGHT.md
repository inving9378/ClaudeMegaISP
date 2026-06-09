# Auditoría Dark/Light — Fase A
Generado: 2026-06-09 | Solo lectura — sin modificaciones al código.

---

## 1. Mecanismo actual

| Campo | Detalle |
|---|---|
| **Componente toggle** | `resources/js/shared/ModeVisualBody.vue` — botón con iconos feather `moon` / `sun` |
| **Endpoint de guardado** | `POST /save-app-config-layout` → `Core\Layout\Services\AppLayoutConfigurationService` |
| **Tabla de persistencia** | `app_layout_configurations` (`user_id`, `color_mode`: `"dark"` / `"light"`) |
| **Aplicación en DOM** | `document.body.setAttribute('data-layout-mode', savedMode)` + `data-topbar` + `data-sidebar` |
| **Lectura inicial** | El blade inyecta `data-layout-mode` en `<body>` al cargar; `appConfig.js` lo lee en `dom.ready()` y setea `darkMode = ref(body.getAttribute !== "light")` |
| **Persistencia** | **BD por usuario** — NO es localStorage. Requiere login. |
| **Per-usuario** | **SÍ** — cada `user_id` tiene su row en `app_layout_configurations` |

**Flujo completo del toggle:**
```
Clic botón → changeMode(color) → POST /save-app-config-layout
  → BD guarda color_mode → response.data.color_mode
  → body.setAttribute('data-layout-mode', savedMode)   ← aplica el CSS
  → darkMode.value = (savedMode === "dark")             ← sincroniza Vue ref
  → mode.value = opuesto                                ← prepara próximo clic
```

---

## 2. Color tokens (CSS variables)

### Archivos con variables CSS encontrados:

| Archivo | Variables | Notas |
|---|---|---|
| `resources/css/megafamilia.css` | 18 vars `--mf-*` | Paleta propia del módulo MegaFamilia. Solo variante light. Sin bloque dark. |
| `public/css/app.css` | `--bs-*` heredadas de Bootstrap | Base del sistema. Bootstrap maneja sus propios tokens, no hay overrides dark explícitos en este archivo. |
| `resources/sass/base/quasar/_quasar.scss` | 2 vars `--bs-gutter-x` | Solo override de layout grid, sin relevancia de color. |
| `resources/sass/_variables.scss` | A verificar en Fase B | Archivo SASS de variables, puede contener colores base. |

### Variables en `megafamilia.css` (fuente más completa encontrada):
```css
--mf-primary, --mf-primary-dark, --mf-primary-light
--mf-accent, --mf-accent-light
--mf-success, --mf-warning, --mf-danger, --mf-info
--mf-gray-50/100/200/500/700
--mf-shadow, --mf-shadow-md, --mf-radius, --mf-radius-sm
```

### Variables propias del WarRoom (sistema aislado):
```css
--wr-bg: #0f0f1a  --wr-surface  --wr-surface-hover  --wr-border
--wr-text: #e8e8f0  --wr-text-muted: #9999b0  --wr-text-dim: #6b6b85
--wr-green  --wr-blue  --wr-purple  --wr-orange  --wr-pink
```

### Conclusión PASO 1:
- **NO existe** un archivo "fuente de verdad" de paleta dark/light para el sistema principal.
- Las `--mf-*` son exclusivas de MegaFamilia; el resto del sistema usa hardcoded hexs + `--bs-*` de Bootstrap.
- Tailwind: **NO instalado** (`tailwind.config.*` no encontrado, 0 hits de `dark:` en todo el codebase).
- Quasar: instalado como UMD (`public/plugins/quasar/js/quasar.umd.prod`), no tiene `quasar.config.js`. El prop `:dark` de Quasar es usado manualmente componente por componente.

---

## 3. Inventario de módulos

| Módulo | Blades | Vues | Tipo | Path principal |
|---|---|---|---|---|
| Clientes | 10 | 65 | **Mixed (Vue-heavy)** | `views/module/client/` + `js/components/module/client/` |
| Configuración / Setting | 1 | 50 | **Mixed** | `views/module/setting/` + `js/components/module/setting/` |
| Vendors / Billing | 0 | 77 | **Vue** | `js/components/module/vendors/` |
| Talento | 0 | 24 | **Vue** | `js/components/module/talento/` |
| WarRoom | 0 | 22 | **Vue** | `js/components/module/warroom/` |
| Flotas | 0 | 20 | **Vue** | `js/components/module/flotas/` |
| Scheduling | 8 | 10 | **Mixed** | `views/module/scheduling/` + `js/components/module/scheduling/` |
| Mapas | 43 | 0 | **Blade** | `views/module/mapas/` |
| CRM | 0 | 12 | **Vue** | `js/components/module/crm/` |
| Network | 2 | 9 | **Mixed** | `views/module/network/` + `js/components/module/network/` |
| IA | 0 | 11 | **Vue** | `js/components/module/ia/` + `js/components/ia/` |
| Releases / Roadmap | 2 | 6 | **Mixed** | `views/module/releases/` + `js/components/module/releases/` |
| Embajadores | 0 | 6 | **Vue** | `js/components/module/embajadores/` |
| Planes | 1 | 5 | **Mixed** | `views/module/planes/` + `js/components/module/planes/` |
| Sellers / Vendedores | 6 | 7 | **Mixed** | `views/module/sellers/` + `js/components/module/sellers/` |
| CRM | 0 | 12 | **Vue** | `js/components/module/crm/` |
| Hub (Integraciones) | 0 | 1 | **Vue** | `js/components/module/hub/IntegrationsHubView.vue` |
| Manual | 0 | 1 | **Vue** | `js/components/module/manual/ManualIndex.vue` |
| WhatsApp Agent | 0 | 2 | **Vue** | `js/components/module/whatsapp/` |
| Cobranza Blaster | 0 | 2 | **Vue** | `js/components/module/cobranza/` |
| DevTools | 0 | 1 | **Vue** | `js/components/module/devtools/DevtoolsPanel.vue` |
| Configuracion API | 0 | 1 | **Vue** | `js/components/module/configuracion/CatalogoApiPanel.vue` |
| Admin / Módulos | 0 | 2 | **Vue** | `js/components/module/admin/` |
| Finance / Finanzas | — | — | **Blade** | `views/module/finance/` |
| Inventario | — | — | **Blade** | `views/module/inventory/` |
| Administración | — | — | **Blade** | `views/module/administration/` |

---

## 4. Top archivos con hardcoded colors

Ordenados por cantidad de ocurrencias de `#RRGGBB` / `#RGB`.
> Nota: algunos archivos tienen colores intencionales (WarRoom, WhatsApp) — ver §6.

| # | Archivo | Hex count | RGB/RGBA count | Dark support | Severidad |
|---|---|---|---|---|---|
| 1 | `js/components/module/whatsapp/WhatsAppPanel.vue` | 99 | 29 | Tema fijo (WhatsApp dark) | INTENCIONAL — ver §6 |
| 2 | `js/components/module/releases/RoadmapTab.vue` | 78 | 0 | PARCIAL (`.rdm-dark` via darkMode) | MEDIA — tiene clase dark pero hardcoded en bloque light |
| 3 | `js/components/module/setting/SmartImport.vue` | 58 | 26 | SÍ (`:dark="darkMode"` + `.is-dark`) | MEDIA — tiene soporte pero hardcoded en CSS vars propias |
| 4 | `js/components/module/flotas/FleetVehicleShow.vue` | 53 (estimado) | 0 | **NO** | **ALTA** |
| 5 | `js/components/module/warroom/meeting/MeetingLivePanel.vue` | 49 | 29 | Tema fijo dark (`#0c0c1e`) | INTENCIONAL — ver §6 |
| 6 | `js/components/module/warroom/WarroomDashboard.vue` | 48 | 24 | Tema fijo dark (variables `--wr-*`) | INTENCIONAL — ver §6 |
| 7 | `js/components/module/warroom/meeting/MeetingHistoryView.vue` | 48 | 25 | Tema fijo dark | INTENCIONAL — ver §6 |
| 8 | `js/components/ia/IaChatFloat.vue` | 48 | 14 | **NO** (gradiente fijo `#6366f1→#8b5cf6`) | **ALTA** |
| 9 | `js/components/module/manual/ManualIndex.vue` | 45 (estimado) | 0 | **NO** | **ALTA** |
| 10 | `js/components/module/hub/IntegrationsHubView.vue` | 44 | 0 | **NO** | **ALTA** |
| 11 | `js/components/module/whatsapp/WhatsAppInstanceManager.vue` | 35 | 0 | **NO** | **ALTA** |
| 12 | `js/components/module/planes/CatalogoServiciosPanel.vue` | 34 | 0 | **NO** | **ALTA** |
| 13 | `js/components/module/configuracion/CatalogoApiPanel.vue` | 34 | 0 | **NO** | **ALTA** |
| 14 | `js/components/module/releases/AuditReport.vue` | 29 | 0 | Desconocido | MEDIA |
| 15 | `js/components/module/maps/helper/mapUtils.js` | 29 | 15 | N/A (colores de marcadores de mapa) | BAJA (funcional) |
| 16 | `js/components/admin/ModuleVisibilityConfig.vue` | 29 | 0 | SÍ (`[data-layout-mode=dark]`) | BAJA — ya cubierto |
| 17 | `js/components/module/ia/IAChatIndex.vue` | 27 | 0 | PARCIAL | MEDIA |
| 18 | `js/components/module/devtools/DevtoolsPanel.vue` | 27 | 0 | Desconocido | MEDIA |
| 19 | `js/components/module/client/InformationClientCrud.vue` | 27 | 0 | **NO** | **ALTA** |
| 20 | `js/components/module/warroom/meeting/MeetingSetup.vue` | 26 | 19 | Tema fijo dark | INTENCIONAL — ver §6 |

**Inline styles con `background:/color:` — top archivos:**

| Archivo | Inline style count |
|---|---|
| `js/components/module/setting/IndexSetting.vue` | 16 |
| `views/module/client/template/FacturaProforma.blade.php` | 15 |
| `views/module/mapas/data/passive_equipment.blade.php` | 12 |
| `js/components/module/talento/TalentoPenalizaciones.vue` | 6 |
| Templates de email/plantillas-por-arreglar (`recibo`, `factura`) | 7–10 cada uno |

> Los templates en `plantillas-por-arreglar/` son PDFs/emails — los colores hardcoded son intencionales (documentos impresos). No deben tocarse.

---

## 5. Cobertura del prefijo `dark:` (Tailwind)

- **Total líneas con `dark:`:** 0
- **Archivos que lo usan:** 0
- **Conclusión:** Tailwind **no está instalado** en el proyecto. No existe `tailwind.config.js`.

### Patrones de dark mode efectivamente en uso (3 coexistiendo):

| Patrón | Archivos que lo usan | Ejemplo |
|---|---|---|
| `[data-layout-mode=dark] .clase` | `ModuleConfigPanel.vue`, `AdminPanel.vue`, `ModuleManager.vue`, `ModuleVisibilityConfig.vue` | `[data-layout-mode=dark] .cfg-pill { border-color: ... }` |
| `body[data-layout-mode="dark"] .clase` | `IAChatIndex.vue`, `IaChatFloat.vue` (en módulos IA) | `body[data-layout-mode="dark"] .ia-main { background: #25282d; }` |
| `:dark="darkMode"` prop Quasar | `SmartImport.vue` (23 usos), `billing/PaymentsList.vue` (5), `sellers/inventory_items/index.vue` (10), varios billing | `<q-table :dark="darkMode" ...>` |
| `:class="{ 'is-dark': darkMode }"` | `SmartImport.vue` | CSS propio para wrapper |
| `:class="{ 'rdm-dark': darkMode }"` | `RoadmapTab.vue` | CSS propio para wrapper |

**Sin dark support de ningún tipo:** Flotas, Manual, Hub, Planes, CatalogoApi, WhatsAppInstanceManager, IaChatFloat (widget flotante).

---

## 6. Módulos con tema fijo intencional (NO modificar en Fase B)

| Módulo | Tema | Color base | Razón |
|---|---|---|---|
| **WarRoom** (`WarroomDashboard`, `MeetingLivePanel`, `MeetingHistoryView`, `MeetingSetup`, `MeetingControlBar`) | Oscuro fijo | `--wr-bg: #0f0f1a` / `#0c0c1e` | Diseño intencionalmente "sala de guerra" nocturna. Sistema propio `--wr-*`. |
| **WhatsApp Panel** (`WhatsAppPanel.vue`) | Oscuro fijo (replica WhatsApp Web) | `#0b141a`, `#111b21`, `#202c33` | Réplica fiel de la interfaz WhatsApp. Cambiar rompería el contexto visual. |

---

## 7. Recomendación de paleta unificada

Propuesta de 8 CSS variables que servirían como fuente única del sistema principal (excluyendo WarRoom y WhatsApp que tienen sistemas propios):

```css
:root,
[data-layout-mode="light"] {
  --bg-primary:      #ffffff;
  --bg-secondary:    #f8f9fa;
  --bg-surface:      #f1f3f5;
  --text-primary:    #212529;
  --text-secondary:  #6c757d;
  --border-default:  #dee2e6;
  --accent:          var(--bs-primary, #556ee6);
  --shadow-card:     0 2px 8px rgba(0,0,0,.08);
}

[data-layout-mode="dark"] {
  --bg-primary:      #1e2433;
  --bg-secondary:    #252836;
  --bg-surface:      #2a2d3e;
  --text-primary:    #cdd6f4;
  --text-secondary:  #adb5bd;
  --border-default:  #3a3d50;
  --accent:          var(--bs-primary, #6c84f7);
  --shadow-card:     0 2px 8px rgba(0,0,0,.3);
}
```

> Estos valores están inferidos de los colores ya usados en los componentes que sí tienen dark support (ModuleManager, ModuleVisibilityConfig, AdminPanel). No son inventados — son los que el sistema ya aplica en las partes que funcionan.

---

## 8. Plan sugerido de remediación (orden propuesto para Fase B)

1. **Establecer paleta única** — Crear `resources/css/dark-light-tokens.css` con las 8 variables de §7. Importarlo en `resources/sass/app.scss`.

2. **Normalizar los 3 patrones de dark** — Decidir patrón canónico (recomendado: `[data-layout-mode=dark]`) y documentarlo. Los componentes con `body[data-layout-mode="dark"]` son equivalentes pero inconsistentes.

3. **Módulos prioritarios por impacto / sin dark support:**
   - `Flotas/FleetVehicleShow.vue` — 53 colores, muy visible
   - `IaChatFloat.vue` — widget flotante en todas las vistas, gradiente fijo
   - `Manual/ManualIndex.vue` — 45 colores, experiencia de lectura
   - `Hub/IntegrationsHubView.vue` — 44 colores
   - `Clientes` (65 vues) — módulo más grande, reemplazar gradualmente

4. **Módulos con soporte parcial a completar:**
   - `RoadmapTab.vue` — tiene `.rdm-dark` pero los colores light están hardcoded
   - `IAChatIndex.vue` — tiene `body[data-layout-mode="dark"]` pero incompleto

5. **Módulos que NO tocar:**
   - WarRoom (sistema propio cerrado)
   - WhatsAppPanel (réplica WhatsApp Web)
   - `plantillas-por-arreglar/` (PDFs/emails — colores de documento)
   - `mapUtils.js` (colores funcionales de marcadores de mapa)

6. **Validar visualmente** cada módulo after fix con Irving antes de pasar al siguiente.

---

## Notas de hallazgos inesperados

- **3 patrones de dark coexisten** sin que ninguno sea "el oficial": `[data-layout-mode=dark]`, `body[data-layout-mode="dark"]` y `:dark="darkMode"` (Quasar). Funcionan todos porque el mecanismo es el mismo atributo en `<body>`. No es un problema de raíz, pero sí genera inconsistencia.
- **MegaFamilia tiene su propio sistema de tokens** (`--mf-*`) completamente separado del sistema principal — y NO tiene variante dark. Es el módulo más "moderno" arquitectónicamente pero tampoco tiene dark mode.
- **Quasar UMD** (no CLI/Vite) — no hay `quasar.config.js`, el prop `:dark` se pasa manualmente. No hay un "modo oscuro global" de Quasar; cada componente Quasar necesita el prop explícito.
- **Tabla `app_layout_configurations`** guarda también `tabs_json` y `client_datatable_color` — el tema dark es parte de una configuración de layout más amplia.
