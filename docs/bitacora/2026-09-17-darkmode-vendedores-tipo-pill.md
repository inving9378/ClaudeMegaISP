## 2026-09-17 09:xx — Fix: pill de "Tipo" en Vendedores desaparecía en modo oscuro

**Reporte de Irving:** en `http://38.123.192.199:3032/vendedores` en modo oscuro no aparece el
pill de la columna Tipo (Interno/Externo/Distribuidor). Sesión previa había confirmado que en
modo CLARO el pill sí está y funciona bien (Torre restyle del lunes) — el bug era específico de
oscuro.

**Investigación:** se forzó `color_mode='dark'` para un usuario real (`david_marsal`, id 4883,
`app_layout_configurations`) y se verificó con Playwright + login real (no solo toggling client-side)
contra `192.168.105.11`. Se encontraron DOS causas independientes, ambas necesarias para el bug
completo:

1. **`resources/sass/base/dark_mode/dark_mode.scss`** — el "AJUSTE 2" (aplana todos los `.badge`
   a texto blanco + línea inferior, sin fondo ni `border-radius`, con `!important`) listaba por
   nombre `.badge-Interno/.badge-Externo/.badge-Distribuidor/.badge-Activo/.badge-Inactivo/.badge-Bloqueado`
   — las 6 clases que `VendedorListar.vue` usa para sus pills (grep confirmó: 0 usos de esas 6
   clases en el resto del código). Esos badges YA tienen su propio tratamiento oscuro correcto en
   `_torre-theme.scss` (`.tc-wrap.tc-dark`, tokens `--tc-*`, pill con fondo+borde real), pero esa
   regla no lleva `!important` → perdía contra AJUSTE 2. Además un segundo reset genérico
   `[data-layout-mode="dark"] span { color:#fff !important }` (los badges son `<span>`) seguía
   forzando el texto a blanco aunque ya se excluyera del primer bloque.
   **Fix:** se excluyen las 6 clases con `:not()` de ambas reglas (AJUSTE 2 y el reset de `span`).
   Sin efecto en ningún otro badge del sistema (verificado por grep — son exclusivas de Vendedores).

2. **`master.blade.php` / `master-without-nav.blade.php`** — el script de tema por-pestaña
   persistía a `sessionStorage` incluso en `/login` (usuario invitado, sin config real → cae al
   default `"light"` de relleno). Ese `"light"` sobrevivía la navegación (misma pestaña) a la
   página ya autenticada y pisaba el `color_mode='dark'` real del usuario recién logueado — la
   página ENTERA renderizaba en claro pese a la preferencia guardada en BD, así que el pill
   (ya arreglado en el punto 1) ni siquiera llegaba a mostrarse en modo oscuro porque el modo
   oscuro mismo nunca se activaba tras un login fresco. Confirmado con `MutationObserver` +
   captura de la respuesta HTTP cruda: el servidor sí mandaba `data-layout-mode="dark"`, pero el
   DOM final (tras el script inline) quedaba en `"light"`.
   **Fix:** el script solo persiste a `sessionStorage` cuando el body SÍ trae un
   `data-layout-mode` real del servidor (`hasServerConfig`), nunca desde la página de invitado.

**Verificado end-to-end** (login real vía Playwright, sin tocar el DOM a mano): tras el fix,
`data-layout-mode` queda en `"dark"` inmediatamente después de un login normal, y los 6 badges de
Vendedores renderizan como pill coloreado (`border-radius:999px`, fondo y texto del color
semántico correcto) en vez de texto blanco plano sin forma.

**Commit:** `9af386aa` (3 archivos, sin tocar código de otras vueltas del circuito que estaban en
staging en el mismo checkout — se separó con pathspec explícito). Pusheado a `origin/main`.

**Limpieza:** se borró la fila de prueba creada en `app_layout_configurations` para
`david_marsal` (id 4883) — quedó exactamente como estaba antes de esta verificación.
