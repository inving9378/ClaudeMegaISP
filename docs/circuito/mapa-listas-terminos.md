# Mapa de las seis listas de términos del circuito (#872)

> Medido en dev el 2026-08-19, leyendo código, no memoria. Este documento es **solo el mapa**: dónde
> vive cada lista, quién la lee, con qué semántica matchea, qué decide y si se solapa con otra.
> **No propone fusión ni cambio de código** — eso es explícitamente fuera de alcance de este item
> (la única de las seis que ya tiene su propio item de consolidación es la #4, `revisor.alcance.
> denylist`, en el #865, porque migrarla cambia comportamiento).
>
> Origen: `docs/circuito/inventario-de-controles.md` §6 traía una fila por lista con una sola
> etiqueta de semántica («palabra completa» / «substring»). Ese resumen resulta **incompleto**: como
> se documenta abajo, al menos una lista (#1) tiene semánticas de matcheo **distintas según quién la
> lee**, algo que una fila no puede expresar. Este documento reemplaza esa fila por el detalle real.

---

## Resumen (una fila por lista)

| # | Lista | Dónde vive | Tamaño | Semántica | Consumidores |
|---|---|---|---:|---|---|
| 1 | `thomas.escalamiento` | `config/circuito.php:223-252` | 51 (4 categorías: 12+9+15+15) | **Dos semánticas distintas según el consumidor** — ver abajo | 4 sitios en `ThomasService` + 1 en `AuditorService` |
| 2 | `thomas.mecanico.senales` | `config/circuito.php:314-326` | 35 | palabra completa (`apareceComoPalabra`, regex unicode) | 1 sitio en `ThomasService::clasificarMecanico` |
| 3 | `thomas.mecanico.negocio` | `config/circuito.php:329-334` | 20 | palabra completa (`apareceComoPalabra`) | 2 sitios en `ThomasService` |
| 4 | `revisor.alcance.denylist` | `config/circuito.php:105-123` | 40 | ⚠️ `Str::contains` = **substring crudo** | 1 sitio en `RevisorService::enAlcance` |
| 5 | `RevisorService::TRIAJE_C_PLAIN` / `TRIAJE_C_WORD` / `TRIAJE_NEGACIONES` | en duro, `RevisorService.php:267-301` | 37 + 6 + 27 | substring (PLAIN) / palabra completa (WORD) · **única con lógica de negación** | 1 sitio, `RevisorService::triarNivelNull` |
| 6 | `RoadmapItem::SENALES_PRODUCCION` | en duro, `RoadmapItem.php:436-443` | 6 | `stripos` = substring, case-insensitive | 2 sitios (`RoadmapCircuitoService`, `RoadmapController`) |

---

## 1. `circuito.thomas.escalamiento` — las cuatro fronteras duras

**Dónde vive:** `config/circuito.php:223-252`. Cuatro categorías (claves del array): `produccion`
(12 términos), `borrar_datos` (9), `dinero` (15), `credenciales` (15).

**Qué decide:** es LA frontera dura del circuito — lo que ningún actor automático puede autorizar,
sin importar nivel de riesgo ni confianza del brief. Si un texto cae aquí, la decisión es de Irving.

**Quién la lee (4 call sites, 2 semánticas distintas):**

| Consumidor | Método | Texto que escanea | Semántica |
|---|---|---|---|
| `ThomasService::evaluar()` (`ThomasService.php:100-114`) | consulta en vivo de una terminal (`circuito:consultar`) | `pregunta` + `item->title` + `item->modulo` | `str_contains(mb_strtolower($heno), mb_strtolower($t))` — **substring crudo**, sin normalizar acentos |
| `ThomasService::clasificarMecanico()` puerta 1 (`:279-285`) | carril mecánico (#566), antes de mirar `mecanico.senales` | `item->title` + `item->description` + `item->prompt` | igual: `str_contains`, substring crudo |
| `ThomasService::categoriaFronteraDura()` (`:341-354`) | alta de item desde la Torre (`RoadmapController.php:1613` vía `store`) y carril «ya decidido» (`ThomasService::aprobarYaDecidido`, `:445`) y `TorreAutomationPolicy::tocaFronteraDura()` (`:251-256`, usada en `:149` como gate del nivel de automatización) | texto que le pase el llamador (título+descripción+prompt en los tres casos reales) | igual: `str_contains`, substring crudo |
| `AuditorService::fronteraDura()` (`AuditorService.php:923-935`) | generación de gaps del auditor, para decidir si un TODO/hallazgo nace `requiere_irving` | texto del TODO/hallazgo detectado | **DISTINTA**: `contieneTermino()` (`:957-968`) sobre `normalizar()` (`:971-977`) — quita acentos (á→a, é→e…) y, si el término no tiene espacio, exige límite de palabra (`(?<![a-z0-9_])…(?![a-z0-9_])`); si el término trae espacio (frase), sigue siendo substring |

**Hallazgo:** el propio inventario (`inventario-de-controles.md:106`) etiqueta esta lista como
«palabra completa, regex con acentos» — eso solo es cierto para el consumidor de `AuditorService`.
Los **tres** call sites de `ThomasService` (que deciden la mayoría del tráfico real: consultas,
carril mecánico, alta de items, carril «ya decidido») matchean por **substring crudo sin normalizar
acentos**. El comentario del propio `config/circuito.php:220` («Ojo con los substrings: los términos
van con el contexto suficiente para no pegar de más») confirma que esto es sabido y aceptado — la
mitigación es la redacción de los términos (frases largas, no palabras sueltas de 3-4 letras), no un
límite de palabra. Es exactamente lo contrario de lo que dice hoy el inventario resumen.

**Se solapa con:**
- **#4 `revisor.alcance.denylist`** — 19 términos literales idénticos: `producción, remote:deploy,
  migrate:fresh, truncate, delete from, borrado masivo, destructiv, openpay, spei, nómina,
  credencial, secret, contraseña, password, .env, permiso, spatie, bcrypt, idor`. Gobiernan cosas
  distintas (escalamiento = frontera dura para Thomas/autopilot; denylist = alcance del Revisor
  adversarial), pero el vocabulario es casi el mismo escrito dos veces.
- **#5 `TRIAJE_C_PLAIN`/`TRIAJE_C_WORD`** — 15 términos idénticos: `producción, produccion,
  remote:deploy, migrate:fresh, drop table, drop column, truncate, delete from, destructiv,
  credencial, contraseña, password, permiso, spatie, bcrypt`.
- **#6 `SENALES_PRODUCCION`** — dentro de la categoría `produccion`: `192.168.105.108` y
  `38.123.192.198` son literales **idénticos** en ambas listas; `v1megaisp` (escalamiento) y
  `v1megaisp.com.mx` (señales) se solapan por substring (cualquier texto con el segundo dispara
  también el primero); igual `ClaudeMegaISP` (escalamiento) vs `/var/www/ClaudeMegaISP` (señales).
  Ver §6 para la diferencia de propósito (una bloquea, la otra solo avisa).
- **No se solapa** con `mecanico.senales` ni `mecanico.negocio` (cero términos en común).

---

## 2. `circuito.thomas.mecanico.senales` — allowlist de trabajo mecánico

**Dónde vive:** `config/circuito.php:314-326`. 35 términos: andamiaje/código muerto, huecos/rutas
404, higiene mecánica (typo, renombrar, lint, `php -l`…).

**Qué decide:** puerta 3 (última) del carril mecánico (#566): si el item **no** cae en la frontera
dura (#1) y **no** menciona negocio (#3), pero **sí** coincide aquí, se auto-aprueba sin brief. Es
allowlist a propósito: sin señal conocida, el item se queda con Irving.

**Quién la lee:** un único call site — `ThomasService::clasificarMecanico()` (`:294-304`), sobre
`item->title . description . prompt` en minúsculas.

**Semántica:** `apareceComoPalabra()` (`ThomasService.php:585-588`) — regex Unicode con límite de
palabra: `(?<![\p{L}\p{N}])término(?![\p{L}\p{N}])`. Palabra completa real (acepta acentos como
parte de la palabra, a diferencia de #1).

**Se solapa con:** ninguna de las otras cinco listas — cero términos en común con #1, #3, #4, #5 ni
#6. Es la única de las seis con dominio semántico propio (higiene de código, no dinero/prod/permiso).

---

## 3. `circuito.thomas.mecanico.negocio` — veto de "esto es decisión de producto"

**Dónde vive:** `config/circuito.php:329-334`. 20 términos: `negocio, producto, estrategia, precio,
precios, tarifa, tarifas, comercial, modelo de cobro, qué debe hacer, rediseñar, rediseño, ux,
decidir el alcance, política de, item madre…`.

**Qué decide:** puerta 2 del carril mecánico — veto. Si coincide, el item **no** es mecánico aunque
traiga una señal de #2, porque "qué debe hacer una feature" no lo decide una máquina.

**Quién la lee (2 call sites):**
- `ThomasService::clasificarMecanico()` puerta 2 (`:288-292`).
- `ThomasService::aprobarYaDecidido()` (`:451-455`) — el carril «ya decidido» (#566 E2) aplica el
  mismo veto antes de aprobar un item cuyo brief ya está contestado.

Ambos sobre `title + description + prompt`.

**Semántica:** `apareceComoPalabra()` — palabra completa, igual que #2.

**Se solapa con:**
- **#4 `revisor.alcance.denylist`** — 4 términos: `negocio, estrategia, precio, tarifa`.
- **No se solapa** con #1, #5 ni #6 (cero términos en común) — ninguna de esas otras listas
  incluye vocabulario de "negocio/producto", solo dinero/prod/credenciales/borrado.

---

## 4. `circuito.revisor.alcance.denylist` — alcance conservador del Revisor adversarial

**Dónde vive:** `config/circuito.php:100-124`. 40 términos agrupados por comentario (no por clave de
array, a diferencia de #1) en: dinero/cobros, seguridad/permisos/auth, producción/despliegue, datos
destructivos, negocio/estrategia/arquitectura.

**Qué decide:** pre-filtro **antes de gastar IA**: si el item cae aquí, el Revisor adversarial
(`RevisorService::revisar()`) ni siquiera llama al modelo — lo declara fuera de alcance y escala.
Es una frontera dura **propia**, distinta de #1 aunque el vocabulario se parezca (ver hallazgo del
inventario: «Los topes duros significan dos cosas distintas según a quién le preguntes»).

**Quién la lee:** un único call site — `RevisorService::enAlcance()` (`:58-75`), llamado desde
`RevisorService::revisar()` (`:85`).

**Texto que escanea:** ⚠️ **`title + modulo + prompt`** — **NO incluye `description`**. Esto importa
porque en la práctica muchos items (como este mismo #872) llevan el cuerpo del spec en
`description` y dejan `prompt` vacío; un término de la denylist que solo viva en `description` no
dispara este filtro (sí puede disparar #1 o #5, que sí incluyen `description`).

**Semántica:** `Str::contains($heno, $kw)` sobre ambos en minúsculas — **substring crudo**, sin
límite de palabra ni normalización de acentos. Es la semántica que el inventario (`:161`, `:503`)
documenta como el bug conocido desde el #338 («'login'/'token' se sacaron por falsos positivos
mecánicos») y que ya tiene su propio item de migración (#865) — no se toca aquí.

**Se solapa con:** #1 (19 términos), #5 (20 términos), #3 (4 términos) — ver el detalle en cada una.

---

## 5. `RevisorService::TRIAJE_C_PLAIN` / `TRIAJE_C_WORD` / `TRIAJE_NEGACIONES` — triaje de items sin nivel

**Dónde vive:** en duro, `RevisorService.php:267-301` (tres constantes, no config):
- `TRIAJE_C_PLAIN` (37 términos: dinero/fiscal/Medussa-CFDI-Facturama/producción/permisos-auth/
  migraciones destructivas).
- `TRIAJE_C_WORD` (6 términos cortos/ambiguos: `iva, rol, roles, auth, sat, prod`).
- `TRIAJE_NEGACIONES` (27 frases: `no toca, sin tocar, no incluye, no modifica, no afecta…`).
- `TRIAJE_VENTANA_NEGACION_BYTES` = 90 (ventana de búsqueda de la negación, en bytes).

**Qué decide:** el único triaje **determinista** (sin IA) para un item con `nivel_riesgo = NULL` —
el punto ciego donde ni el ejecutor (que pide A) ni el revisor (que pide B) lo toman. Si matchea →
nivel `C` + `requiere_irving` directo. Si no → nivel `B` por default seguro (lo evalúa la siguiente
pasada del Revisor normal).

**Quién la lee:** un único call site — `RevisorService::triarNivelNull()` (`:409-451`), llamado desde
`RevisarBacklogCommand.php:117`.

**Texto que escanea:** `title + modulo + description + prompt`, con un paso previo único entre las
seis listas: `stripBoilerplate()` (`:382-407`) borra **líneas enteras** que contengan marcadores de
proceso/guardrail (`"guardrail", "solo en dev", "nunca prod", "checkpoint"`…) antes de buscar
keywords — para no marcar C solo porque el boilerplate estándar de la instrucción menciona "prod" o
"dinero" sin que el trabajo real los toque.

**Semántica — la única con dos capas:**
1. Matcheo: `TRIAJE_C_PLAIN` por `str_contains` (substring); `TRIAJE_C_WORD` por
   `preg_match('/\bkw\b/u', …)` (palabra completa, límite ASCII `\b`, no Unicode como #2/#3).
2. **Negación** (única entre las seis listas): si TODAS las apariciones de un término matcheado
   tienen una de las 27 frases de `TRIAJE_NEGACIONES` inmediatamente antes (misma oración, ventana
   de 90 bytes, sin cruzar `. ! ?` ni línea en blanco), esa keyword se exime y sigue buscando. Si
   **al menos una** aparición no está negada, sigue disparando C (sesgo conservador).

**Se solapa con:** #1 (15 términos) y #4 (20 términos) — ver el detalle en cada una. No se solapa
con #2, #3 ni #6.

---

## 6. `RoadmapItem::SENALES_PRODUCCION` — aviso (no bloqueo) de que un item toca prod

**Dónde vive:** en duro, `RoadmapItem.php:436-443`. 6 literales de infraestructura: dos IPs
(`192.168.105.108`, `38.123.192.198`), un dominio (`v1megaisp.com.mx`), un nombre de BD
(`meganet_prod`), dos rutas de filesystem (`/var/www/ClaudeMegaISP`, `/var/www/MEGANET`).

**Qué decide — la única de las seis que NO frena nada:** por decisión explícita de Irving
(2026-08-18, documentada en el propio código `:428-435`), esta lista **no bloquea, no escala, no
saca del carril automático**. Solo deja rastro (canal de auditoría `roadmap_externo` + el `log` del
item, de donde lo leen la Torre y el digest) y sigue de largo. El objetivo declarado: con 6
terminales autónomas hasta nivel B, prod es lo único que no se deshace con un `git checkout`, así que
no se trata de impedirlo sino de que sea imposible enterarse tarde.

**Quién la lee (2 call sites), vía `RoadmapItem::tocaProduccion()` (`:446-458`):**
- `RoadmapCircuitoService::avisarSiTocaProduccion()` (`:1768-1782`) — se dispara al despachar/
  reclamar un item; escribe el aviso en el log y el canal, con manejo falla-segura (una excepción
  aquí nunca tumba el reclamo).
- `RoadmapController.php:187` — expone `toca_produccion` como campo de la respuesta que pinta la
  tarjeta del item en la Torre (badge visual, sin efecto en el flujo).

**Texto que escanea:** el más amplio de las seis — `title + description + prompt +
comentarios_claude + branch` (único que mira `comentarios_claude` y `branch`).

**Semántica:** `stripos($blob, $senal) !== false` — substring, case-insensitive (`stripos`, no
`mb_stripos`: con literales ASCII como IPs/rutas no hay diferencia práctica).

**Se solapa con:** #1 (categoría `produccion`) — 2 términos idénticos + 2 pares por substring, ver
detalle en §1. Es el único solapamiento de esta lista; no comparte vocabulario con #2, #3, #4 ni #5.

---

## Matriz de solapamiento (términos literales en común, no conceptual)

| | #1 escalamiento | #2 senales | #3 negocio | #4 denylist | #5 TRIAJE | #6 prod |
|---|---:|---:|---:|---:|---:|---:|
| **#1 escalamiento** | — | 0 | 0 | 19 | 15 | 4* |
| **#2 senales** | 0 | — | 0 | 0 | 0 | 0 |
| **#3 negocio** | 0 | 0 | — | 4 | 0 | 0 |
| **#4 denylist** | 19 | 0 | 4 | — | 20 | 0 |
| **#5 TRIAJE** | 15 | 0 | 0 | 20 | — | 0 |
| **#6 prod** | 4* | 0 | 0 | 0 | 0 | — |

\* #1↔#6: 2 idénticos + 2 por substring (no literales idénticos), contados juntos — ver §1 y §6.

**Lectura de la matriz:** hay dos familias claramente separadas. La familia
**«frontera dura / dinero-prod-credenciales»** (#1, #4, #5, y parcialmente #6) comparte vocabulario
pesado entre sí — son, en los hechos, tres-cuatro redacciones independientes de una idea parecida,
con semánticas de matcheo distintas cada una. La familia **«trabajo mecánico»** (#2, #3) vive aparte
y solo #3 (el veto de negocio) cruza hacia la familia dura, y solo con #4.

---

## Lo que este documento NO hace

No propone fusionar listas, no cambia ninguna semántica de matcheo, no toca `config/circuito.php` ni
ningún `.php` de servicio. Es lectura del código tal como está hoy en dev. La consolidación de #4
(`revisor.alcance.denylist`, el bug de substring del #338) ya tiene su propio item (#865); las
demás quedan mapeadas aquí para que una consolidación futura —si Irving la decide— sepa exactamente
qué se solapa con qué y qué semántica rompe si se toca.
