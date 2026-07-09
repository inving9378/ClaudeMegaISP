# Navegación móvil — riel deslizable (progreso)

**Orden directa de Irving en sesión** (no circuito). Roadmap **#299**. Capa **100% aditiva
solo-móvil**; escritorio intacto. Entorno: DEV `192.168.105.11`.

## Principio
El riel **ESPEJA el DOM del sidebar ya renderizado** (`#side-menu`): el servidor ya aplicó
todos los `@can`/roles, así que el riel hereda **módulos, orden, hijos, rutas y permisos
idénticos** (incluye los 2 bloques hardcodeados WhatsApp/Portal de Pago y los addons
dinámicos), sin duplicar lógica. **Nada inventado.**

## Reglas respetadas
- Activación **solo por `@media (max-width:992px)`** (alineado al colapso actual), **nunca User-Agent** (confirmado: no hay detección UA para la UI).
- Escritorio intacto (`#mobile-nav-root` nace `display:none`; validado `display:none` a 1200px).
- Respeta `body[data-layout-mode]` (claro/oscuro).
- Convive con el hamburguesa existente (no se elimina).
- Degrada con gracia: si `#side-menu` falta/cambia → el riel no aparece, la página no rompe.
- Se construye **una vez** por carga; solo LEE el sidebar (no toca metisMenu/sessionStorage).
- No rompe `spa-nav.js`: el partial vive fuera de `#init-vue` (persiste entre navegaciones SPA); los enlaces del riel son `<a href>` normales.
- Íconos: reusa los **feather** ya renderizados del sidebar (sin CDNs nuevos).

## Archivos
- `app/Modules/Core/Layout/views/mobile-nav.blade.php` (nuevo) — riel + hoja + buscador + CSS + JS.
- `app/Modules/Core/Layout/views/master.blade.php` — `@include('core-layout::mobile-nav')` tras el sidebar.

## Fases
### Fase 1 — Riel + hoja de submenú ✅ (commit `faf75064`)
- Riel inferior fijo, `scroll-snap-type:x`, difuminado derecho (mask lineal), cada módulo con color+ícono.
- Módulos con hijos → **hoja** (bottom-sheet) con backdrop; sin hijos → navegan directo.
- **Validado (playwright headless, sidebar sintético):** móvil 390px → 10 módulos en orden, color+ícono SVG 10/10, 4 directos/6 con hoja; hoja de "Gestión de red" (sub-grupos anidados) aplana a 4 hijos con rutas correctas; escritorio 1200px → `display:none`; claro y oscuro OK.
- Fix: `[hidden]` sobre `.mnav-sheet` (el `display:flex` lo pisaba y la hoja tapaba el riel).

### Fase 2 — Buscador de respaldo ("lupa") ✅ (commit `24674523`)
- Índice construido durante el espejo (módulos + hijos con ruta); overlay full-screen, filtro live, contexto "Módulo › Pantalla", navega al tocar. Lupa flotante interina.
- **Validado:** query "listar" → 2 resultados con rutas correctas (`/red/router/listar`, `/red/ipv4/listar`).

## Colores
Paleta **placeholder** en UN solo lugar: objeto `MNAV_COLORS` (por label normalizado) dentro
de `mobile-nav.blade.php`. **Pendiente:** el mockup `medussa-nav-movil-mockup.html` **nunca
llegó** (prometido 3 veces, sin HTML). Al llegar el array `MODS`, reemplazar SOLO ese objeto
(fallback determinista por hash cubre módulos no listados).

## Pendiente / deudas registradas
1. **Mockup ausente** → tomar de ahí la paleta (`MODS`) y las proporciones/estilo final. Reemplazar `MNAV_COLORS`.
2. **Topbar delgada (lupa+campana+tema):** la campana y el tema ya viven en la topbar Vue actual; falta el rediseño "delgado" y mover la lupa ahí (hoy flotante). **Depende del mockup.**
3. **Sub-grupos anidados** (gestión-red, inventario): la hoja los **aplana** → se pierden los encabezados de grupo ("Add/Listar/Add/Listar" ambiguos). Refinar mostrando el encabezado del sub-grupo.
4. `type="search"` agrega un × nativo además del propio — pulido trivial.

## ⚠️ A VALIDAR CON SCREENSHOT (Irving, mañana)
La validación automática usó un `#side-menu` **sintético** (el composer no se dispara en render CLI). En el **navegador real** el sidebar se puebla por HTTP → **abrir la app en móvil (o navegador angosto <992px) y confirmar que el riel espeja el sidebar COMPLETO real** (todos los módulos que ve el usuario, en orden, con hijos y rutas). Probar: tap en módulo directo (navega), tap en módulo con hijos (abre hoja), la lupa (buscar y navegar), y el modo oscuro.
