# Item #9990259 — UI en TorreConfiguracion.vue para editar `mencion_retiene_categorias` (RESUELTO — ya implementado)

## Contexto

El item pedía, condicionado a que el sub-item de backend (#9990258) estuviera mergeado a `main`:

1. Agregar en `TorreConfiguracion.vue`, entre las secciones de Válvula y Techo del autopilot, una
   card nueva con las 4 categorías (`dinero`, `credenciales`, `produccion`, `borrar_datos`) y un
   toggle "retiene en mención" por cada una, precargado del valor devuelto por `index()`.
2. Al guardar, `POST /torre/fronteras/categoria-mencion` (nombre del endpoint tal como lo escribió
   el spec original) con el array completo de categorías marcadas.
3. Mostrar debajo el texto de `mencionRetieneCategoriasFuente()` (BD vs `config/circuito.php`).
4. Mostrar la bitácora reciente del cambio, reusando `bitacoraFronteras()` ya incluido en `index()`.
5. Gatear la edición con `puede_editar` (mismo permiso `torre.config.edit`, sin crear uno nuevo).
6. Verificar compilando con el semáforo.

## Verificación — todo ya estaba hecho antes de que esta vuelta empezara

Una vuelta anterior (`wt-5`) había declinado ejecutar el item porque su dependencia — el backend de
#9990258 — seguía `en_progreso` sin `merge_commit`. Esa dependencia **ya se resolvió**: el historial
de `main` muestra el merge `0d19e33d "Integra circuito #9990258 ... a main"`, y el endpoint quedó
registrado en `app/Modules/Addons/Roadmap/routes.php:145`:

```php
Route::post('/torre/fronteras/mencion-categorias', [TorreFronterasController::class, 'mencionCategorias']);
```

(El nombre real del endpoint es `mencion-categorias`, no `categoria-mencion` como decía el spec
textual del item — es el mismo endpoint que ya construyó y wireó el propio #9990256/#9990258; no hay
ambigüedad porque UI y backend usan literalmente la misma cadena.)

Pero al llegar a picar la UI, se encontró que **ya existe** — commit `b6ba8d46`
("feat(circuito#9990256): UI para mencion_retiene_categorias en Torre → Configuración"), escrito
directamente por Irving el mismo día como parte del propio #9990256 (el punto (5) de ese spec ya
contemplaba exponerlo en pantalla). Verificado línea por línea contra el spec de #9990259:

| Requisito del item | Dónde ya está | Cumple |
|---|---|---|
| Card nueva, mismo patrón visual, entre Válvula y Techo del autopilot | `TorreConfiguracion.vue:175-203`, literal entre esas dos cards | ✅ |
| 4 categorías con toggle "retiene en mención" | `v-for="cat in (fronteras.mencion?.categorias \|\| [])"` (línea 193), toggle `form-check form-switch` | ✅ |
| Precargado del valor de `index()` | `:checked="cat.retiene"` — viene de `TorreFronterasController::index()` línea 135-141 | ✅ |
| POST con el array completo al guardar | `toggleMencionCategoria()` arma la lista completa desde el estado vigente y llama `guardarMencion(nuevas)` → `POST /torre/fronteras/mencion-categorias` | ✅ |
| Array vacío como decisión explícita válida | `toggleMencionCategoria` permite llegar a `[]` (última categoría destildada) sin bloqueo especial; el controller (`categoriaMencion`, línea ~352) no exige mínimo | ✅ |
| Mostrar fuente (`mencionRetieneCategoriasFuente()`) | Header de la card: `Fuente: <code>{{ fronteras.mencion?.fuente }}</code>` (línea 179) | ✅ |
| Bitácora reciente del cambio, reusando `bitacoraFronteras()` | La sección compartida "Bitácora de fronteras" (línea 438 en adelante) ya lee `fronteras.bitacora`, y `bitacoraFronteras()` (`TorreFronterasController.php:152-167`) incluye explícitamente la compuerta `mencion_categorias` en su `orWhereIn` (línea 156) — no hace falta una tabla nueva, la general ya la muestra | ✅ |
| Gatear con `puede_editar` / `torre.config.edit`, sin permiso nuevo | `:disabled="!puedeEditar"` en el checkbox (línea 195); `puedeEditar` viene de `index()` → `auth()->user()?->can('torre.config.edit')` | ✅ |
| Confirmación en dos pasos (patrón de la pantalla) | `toggleMencionCategoria` usa `pedirConfirmacion({...})` igual que el resto de los controles de esta pantalla | ✅ |

## Verificación de build

```
$ php -l app/Modules/Addons/Roadmap/Controllers/TorreFronterasController.php
No syntax errors detected
$ bash deploy/circuito/npm-build.sh
```
(ver salida en el commit de cierre — compila sin errores; el archivo `.vue` no se tocó en esta
vuelta, ya estaba compilando desde que se mergeó `b6ba8d46`).

## Conclusión

No hay código que escribir: el punto (5) del spec de #9990256 (UI) y este item (#9990259) piden
exactamente lo mismo, y #9990256 ya lo cerró en su propia vuelta. Se cierra #9990259 como resuelto
sin cambio de código, dejando esta nota para que quede trazado por qué no generó una rama con
diffs de `.vue`.
