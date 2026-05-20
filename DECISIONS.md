# Decisiones de Arquitectura — MegaISP (ClaudeMegaISP)

## Contexto general
- Repo: https://github.com/inving9378/ClaudeMegaISP
- Branch principal: main
- Branch activo: feature/modular-arch
- Desarrollador: Irving
- Stack: Laravel 10 + PHP 8.2.17 + Vue 3 + Quasar UMD + Laravel Mix

---

## 2025-05-20 — Migración Modular completada (tag: 2025.05.modular-arch)

### Estructura
- 26 módulos en app/Modules/{Core,Addons}/
- 13 Core: Auth, Clientes, Configuracion, CRM, Dashboard, Layout,
  ModuleManager, Release, Usuarios, Localizacion, Auditoria,
  Documentos, Documentacion
- 13 Addons: DevTools, Finanzas, GestionRed, IA, Inventario, Mapas,
  MegaFamilia, Mensajes, Planes, Reportes, Scheduling, Tickets, Vendedores
- 219 controllers migrados
- 978 rutas resueltas en App\Modules\*
- app/Http/Controllers/Module/ eliminado completamente

### Decisiones clave
- Arquitectura modular PROPIA (sin nwidart/laravel-modules)
- BaseModuleServiceProvider en app/Modules/ — NO modificar
- Auto-discovery de providers via ModuleManagerService
- URLs preservadas byte-identical (sin renombrar rutas)
- check_route_permission middleware preservado (no migrado a Spatie gates)
- Sub-namespaces por dominio dentro de módulos grandes:
  GestionRed/{Network,Router,OLTs}, Mapas/{Mapas,Geo},
  Vendedores/{Sellers,Vendors}

### Pendiente (bajo demanda, no urgente)
- Fase 5: Migrar app/Models/ a módulos (194 modelos, 51 relaciones FQN — riesgo alto)
- Fase 6: Migrar app/Http/Repository/ a módulos
- Fase 7: Migrar resources/js/components/module/ a módulos
- phpunit.xml + suite de tests (branch separado)
- Migrar check_route_permission a Spatie gates por módulo

### Shims activos (NO eliminar sin migrar modelos)
- 15 shims en app/Models/Client*.php — extienden Core/Clientes/Models/
- 339 refs externas apuntan a ellos (jobs, observers, commands, migrations)
- Eliminar solo cuando se haga Fase 5 de Clientes

---

## 2025-05-20 — DevTools Panel upgrade (en progreso)

### Feature
Rediseño de DevtoolsPanel.vue a layout 3 columnas:
[Sidebar Nav colapsable] | [Claude Chat] | [Terminal ttyd]

### Decisiones tomadas
- Sidebar: 220px expandido / 52px colapsado (solo íconos)
- Toggle: botón ☰, estado persiste en localStorage
- Theme: heredado de data-layout-mode del body (dark/light automático)
- Fix aplicado: master-without-nav.blade.php ahora propaga data-layout-mode
- Nav items: endpoint GET /devtools/nav-items (hardcoded + filtro permisos)
- Markdown: reusar composable useMarkdown.js existente (no instalar deps)
- Voz input: Web Speech API nativo (SpeechRecognition)
- Voz output: Browser speechSynthesis, idioma es-MX, voz seleccionable
- Adjuntos: imágenes (base64 vision) + archivos texto/PDF (texto extraído)
- Feedback 👍👎: solo visual, sin persistencia en BD
- Acciones por mensaje: Regenerar, Copiar, Escuchar, 👍, 👎

### Commits de esta feature
- 474c343: fix(layout): propagate data-layout-mode to master-without-nav
- 0d183e9: feat(devtools): add navItems endpoint for sidebar navigation
- PENDIENTE: feat(devtools): rewrite DevtoolsPanel.vue 3-col layout

---

## Convenciones del proyecto

### Commits
- Siempre selectivos por scope: git add ruta/especifica
- Nunca git add -A
- Formato: tipo(scope): descripción en español o inglés técnico

### Nuevos módulos
- Path: app/Modules/{Core|Addons}/NombreModulo/
- Archivos requeridos: module.json, ModuleServiceProvider.php, routes.php
- module.json: slug kebab-case, type core|addon, active true
- Provider extiende BaseModuleServiceProvider — auto-descubierto, sin registrar en config/app.php
- Routes: middleware(['web','auth','check_route_permission'])

### Vue components
- Registrar en resources/js/app.js con kebab-case
- Rebuild: npm run dev después de agregar componente
- Quasar UMD (no npm) — agregar componentes al app.use(Quasar, {components:[]})

### Modelos compartidos
- Quedan en app/Models/ — acceder con use App\Models\X desde cualquier módulo
- BaseModel extiende todos los modelos — auto-stamp created_by/updated_by

### Commits completados
- 474c343: fix(layout): propagate data-layout-mode to master-without-nav
- 0d183e9: feat(devtools): navItems endpoint for sidebar navigation
- 4f96326: docs: add DECISIONS.md
- b176296: feat(devtools): SUB-PASO 3A scaffold layout 3-col + theme switching
- d17b51f: feat(devtools): SUB-PASOS 3B+3C+3D full chat, attachments, voice, actions

### Estado: COMPLETO
