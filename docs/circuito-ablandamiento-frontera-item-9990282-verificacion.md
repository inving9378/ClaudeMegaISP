# Item #9990282 — Support\AblandamientoFrontera (Fase 3a de #9990246) — RESUELTO por trabajo ya integrado bajo #9990246

## Qué pedía el item

Crear `app/Modules/Addons/Roadmap/Support/AblandamientoFrontera.php` como clase PURA (sin
Laravel/BD, mismo patrón que `Support/MencionFrontera.php`) con:

- `const VEREDICTO = 'ablandamiento_paso'`
- `static function esAblandamientoQueDejaPasar(?string $categoria, ?string $categoriaDetectada, bool $ablandada): bool`
  que detecta el caso concreto de `JarvisService::fronteraDuraDeItemDetalle()` en el que la
  válvula ablandó una mención (`categoria=null` + `categoria_detectada` seteada + `ablandada=true`)
  — el caso que antes se habría escalado y ahora sigue de largo, sin dejar rastro.
- Test candado PHPUnit puro (`TestCase` de PHPUnit, no `Tests\TestCase` de Laravel) con los 4 casos:
  true (ablandamiento real), false (`efecto=avisar`), false (sin `categoria_detectada`), false
  (`categoria` retenida).
- Explícitamente **sin wiring** a `TorreAutomationPolicy.php` — eso quedaba para la Fase 3b
  (item #9990246, "depende de que esta clase ya exista en main").

## Qué se encontró al llegar a ejecutarlo

Este item había sido reclamado antes por otra terminal (`wt-6`), que sí implementó la clase y el
test exactamente como pedía el spec (commits `84ef3088` + `05f5d0fd`, rama propia
`circuito/item-9990282-fase-3a-de-9990246-supportablandamie`), pero la vuelta murió antes de
integrar (`circuito:integrar` nunca corrió) — el log del item lo confirma
(`claim_liberado_al_morir_la_vuelta`, dos veces).

Mientras tanto, **otra sesión trabajando en #9990246** (Fase 3b, la que necesitaba esta clase)
**no esperó** a que #9990282 aterrizara en `main`: creó su **propia** versión de
`app/Modules/Addons/Roadmap/Support/AblandamientoFrontera.php` en la rama de #9990246, con una
API distinta (`evento(array $det): ?array`, que devuelve el evento completo a insertar en vez de
un booleano) pero **semánticamente equivalente** — mismo criterio exacto
(`categoria===null && ablandada===true && categoria_detectada no vacía`), mismo
`VEREDICTO='ablandamiento_paso'`, y su propio test candado con los mismos 4 casos. Esa rama sí
llegó a `circuito:integrar` y quedó mergeada en `main` (commits `35526028` +
`7c2e7be3 Integra circuito #9990246 ... a main`), **ya cableada** en
`TorreAutomationPolicy::estadoInicial()` (línea 197):

```php
$eventoAblandamiento = \App\Modules\Addons\Roadmap\Support\AblandamientoFrontera::evento($detFrontera);
if ($eventoAblandamiento !== null) {
    $this->registrarAblandamientoPaso($item, $eventoAblandamiento, $detFrontera['motivo'] ?? null);
}
```

El `colision-check` del circuito detectó que ambos items tocaban el mismo archivo y declaró
ganador a #9990246 (`colision_pausada`, `ganador:9990246`), pausando #9990282 — que se quedó
huérfano en su propia rama, nunca integrada.

## Verificación de que el objetivo ya está cumplido

- `php -l` limpio en los 3 archivos relevantes de `main`.
- `vendor/bin/phpunit tests/Unit/Modules/Addons/Roadmap/AblandamientoFronteraTest.php` → **6
  tests, 8 assertions, OK**, sin bootear Laravel ni tocar BD (mismo patrón exigido por el spec).
- `grep -rn "esAblandamientoQueDejaPasar"` en `app/`/`resources/`/`routes/` → **cero resultados**:
  la API literal que pedía el spec de #9990282 no tiene ningún consumidor en ningún lado — nadie
  la necesita porque el mismo criterio ya vive, probado y cableado, en `evento()`.
- `git merge-base --is-ancestor` confirma que los commits `84ef3088`/`05f5d0fd` (la
  implementación original de #9990282) **nunca llegaron a `main`** — son historia muerta en una
  rama huérfana.

## Decisión (regla de oro, sin frontera dura — dedup de código, no negocio/dinero/prod/credenciales)

**No fusionar** la rama original de #9990282. Hacerlo agregaría una segunda clase con el mismo
propósito, mismo `VEREDICTO`, mismo criterio, pero una API distinta (`esAblandamientoQueDejaPasar`
booleano) que **nadie llamaría** — puro código muerto duplicado junto a la versión que sí está en
producción de dev (`evento()`). Viola MINIMALISMO sin ganar nada: el objetivo real del item (que
`fronteraDuraDeItemDetalle()` pueda distinguir un ablandamiento-que-deja-pasar del resto de casos
`categoria=null`) ya está resuelto, probado y en uso.

La rama huérfana `circuito/item-9990282-fase-3a-de-9990210-registrar-en-torre` se reseteó a
`main` (era un scratch branch local del propio item, nunca integrado, sin pushear a ningún
remoto — nada que se pierda) antes de agregar este documento.

**Sin cambio de código de aplicación** — el trabajo real (la clase de decisión pura + su test
candado + el wiring en Torre) ya está en `main` desde `#9990246`.
