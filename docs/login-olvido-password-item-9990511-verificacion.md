# Item #9990511 — Link "¿Olvidó su contraseña?" en login.blade.php (RESUELTO — ya implementado por el padre)

## Contexto

El item pedía editar `app/Modules/Core/Auth/views/login.blade.php` para agregar un link
"¿Olvidó su contraseña?" cerca del campo de contraseña, envuelto en
`@if(Route::has('password.request'))`, apuntando a `route('password.request')`, siguiendo el
mismo patrón defensivo de `app/Modules/Core/Auth/views/passwords/confirm.blade.php` (líneas
35-38).

Es un sub-item de seguimiento del item padre **#9990506** ("Agregar el link '¿Olvidó su
contraseña?'..."), creado por `circuito:cabida` cuando el padre fue marcado NO CABE
(`historico_excede_umbral`, módulo Core/Permisos nivel B) pese a ser un cambio trivial.

## Hallazgo

Al llegar a este item, el cambio **ya estaba implementado y mergeado a `main`** — el propio
item padre #9990506 lo ejecutó directamente en su misma vuelta:

- Commit `f37420a9` — "Agrega link '¿Olvidó su contraseña?' en login.blade.php" (autor
  `Irving MegaISP`, mismo patrón de commit del circuito, `Co-Authored-By: Claude Sonnet 5`).
- Integrado a `main` vía `ed1ea7b3` — "Integra circuito #9990506
  (circuito/item-9990506-agregar-el-link-olvido-su-contrasena) a main".
- `git merge-base --is-ancestor f37420a9 main` → confirma que el commit ya es ancestro de
  `main`.

Es el mismo patrón de "carrera de timing" documentado repetidas veces en `CLAUDE.md`
(#733/#741/#753/#9990003/#9990353/#9990385): el sub-item de seguimiento se generó a partir del
spec original del padre sin que el generador viera que el propio padre ya lo había resuelto en
la misma vuelta.

## Verificación (branch `circuito/item-9990511-...`, creado desde `main` ya con el fix)

- `git diff main -- app/Modules/Core/Auth/views/login.blade.php` → vacío (el archivo en esta
  rama ya es idéntico a `main`, que ya trae el fix).
- El código en `login.blade.php:53-57` coincide exactamente con lo pedido:
  ```blade
  @if(Route::has('password.request'))
      <div class="text-end mt-1">
          <a class="text-muted" href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
      </div>
  @endif
  ```
- `php -l app/Modules/Core/Auth/views/login.blade.php` → sin errores de sintaxis.
- `php artisan route:list --name=password.request` → `GET|HEAD password/reset` existe
  (`App\Modules\Core\Auth\Controllers\...`).
- `curl http://192.168.105.11/login` → `200`.
- `curl http://192.168.105.11/password/reset` → `200`.

## Conclusión

Sin cambio de código — el trabajo ya estaba hecho y verificado end-to-end (sintaxis, ruta,
render HTTP de ambas páginas). Se cierra el item documentando el hallazgo, siguiendo el mismo
criterio que los items previos de esta familia.
