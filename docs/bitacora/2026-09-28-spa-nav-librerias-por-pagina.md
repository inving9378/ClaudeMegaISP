## 2026-09-28 08:58 — spa-nav: carga completa cuando la página destino trae librerías que la pestaña no tiene

### Síntoma
Entrando al sistema por el Dashboard y navegando por el menú a un formulario (`/cliente/crear`,
`/tickets/crear`…), los selects de Choices.js salían sin estilos: caja, buscador y la lista de
opciones impresa en línea. Con Ctrl+Shift+R se arreglaba. En MEGANET no pasa.

### Causa
`spa-nav.js` solo intercambia `#init-vue`. El `<head>` y los `<script>` de `vendor-scripts` se
quedan como los dejó el primer full-load. Las librerías por módulo (tabla `packages` vía
`IncludeLibraryTrait`: `choices.min.css`, select2, bootstrap-multiselect, DataTables, CKEditor,
toastr…) solo se declaran en las páginas de módulo, y el Dashboard no trae ninguna. Resultado:
Choices.js arma su DOM pero sin su CSS. MEGANET no tiene spa-nav (cada clic es full-load), por eso
no falla.

Reproducido en Chromium headless (Dashboard → menú → Clientes → Crear): `choices.min.css` ausente
del documento y `.choices__list--dropdown` con `position: static` y visible. Con F5, presente y
oculta.

### Fix
`spaNavigate()` paso 2.5: `missingPageAssets(doc, url)` compara hojas de estilo
(`rel=stylesheet` y `rel=preload as=style`) y `<script src>` que declara la página destino —fuera
de `<noscript>` y de `#__spa-scripts`— contra los ya cargados en la pestaña. Si falta alguno, lanza
el error que ya existía para el fallback → `window.location.href = url` (carga completa). Una vez
cargadas, las navegaciones siguientes vuelven a ser SPA.

Efecto colateral deseado: si `app.js` se recompila (cambia `?v=filemtime`), la siguiente navegación
hace full-load y la pestaña toma el bundle nuevo en vez de mezclarlo con el viejo.

### Verificación (Chromium headless, `localhost:8000`, sesión de prueba de admin)
| Navegación | Recarga completa | `choices.min.css` | Lista oculta |
|---|---|---|---|
| Dashboard → Clientes → Crear (menú) | sí | sí | sí (8 selects) |
| Clientes → Crear → CRM → Crear (menú) | no (SPA) | sí | sí (7) |
| CRM → Dashboard (menú) | no (SPA) | sí | — |
| Atrás → CRM → Crear (popstate) | no (SPA) | sí | sí (7) |
| Dashboard → Tickets → Crear | sí | sí | sí (5) |

### Hallazgos anotados (no tocados)
- Vue monta en cuanto responde `/permissions-auth` (`app.js` ~995), antes de `DOMContentLoaded`: los
  23 scripts que siguen a `app.js` pueden no existir aún al montar (medido: `select2` y
  `window.Choices` ausentes al montar). Existe igual en MEGANET; no era la causa de este bug.
- Dos jQuery (`bootstrap.js` pone el 3.7.1 de npm en `window.$`; `assets/libs/jquery` 3.6.0 lo
  reemplaza y los plugins se enganchan a este) y dos Choices (bundle v11.1.0 vs CSS/JS servidos v9.0.1).
- Repo local de `/home/PROJECTS/ClaudeMegaISP` corrupto a las 07:57 (HEAD → commit con objeto vacío,
  4 objetos vacíos, reflog con NUL). Reparado: contenido del commit local perdido recuperado íntegro
  (mismo árbol `73b43db3`) y dejado sin commitear (`.gitignore` + `package-lock.json`); `main`
  alineado con `origin/main`.
