# Fase 1 — Reporte de QA (agente-qa) · 2026-11-13

Worktree `/var/www/megaisp-henry-marketing`, rama `feat/marketing-360-equipo`, base `a0af9d95b`. Sin commits, sin
escribir en `megaisp` (solo lecturas de catálogo de permisos y de una columna), pruebas por nombre de archivo con
`flock /tmp/megaisp-suite.lock`. Incluye el CAMBIO DE ALCANCE de Henry (A–E): regla B de acceso, comando
`marketing:equipo-fijar`, y sin `MarcaNuevaVeSusRegistrosTest` / `SlugFormularioGlobalTest` (punto D).

**Veredicto QA: DETENIDA** hasta que se corrijan o Henry decida sobre las fallas F-1 a F-4 (abajo). Todo lo demás pasa.

---

## 1. Pruebas creadas (`tests/Feature/Marketing/Fase1/Qa/`)

| Archivo | Resultado | Qué cubre |
|---|---|---|
| `Concerns/EscenarioFase1.php` | — | Escenario SIN sembrar los 13 permisos de la migración (si faltan, la prueba truena: lo que la Fase 0 tapó). Solo siembra 11 permisos que existen en `megaisp` (dev = prod) y faltan en `megaisp_test` (vienen de seeders, no de migración); verificado por lectura. Recorre las rutas desde el router (URI, acción en el namespace del módulo o closure definido en el módulo). |
| `AccesoNegadoPorDefectoTest.php` | **18/18 OK** | Condiciones de Henry F1-1 + regla B, **ruta por ruta (134 desde el router)**: estructura `auth` + `marketing.empresa` salvo lista CERRADA de 7 públicas con nombre; ninguna lleva ya `check_route_permission`; sin sesión → nunca 200 (JSON 401 / HTML redirect a login, también POST/PUT/DELETE); sin rol ni empresa → 403 `sin_empresa` (JSON) y «Aún no tienes empresa» (pantallas); super-administrador / DESARROLLADOR sin fila → 403; permisos Spatie sueltos sin fila → 403; fila `marketing` → 403 `sin_acceso`; fila `ventas` → 403 `sin_acceso`; admin de otra empresa → 403 aunque pida la 1 en sesión; inactivo → 401 `usuario_inactivo` y sesión cerrada; admin con fila en la 1 → entra en todas (GET sin parámetros = 200, salvo las de diseño: logo sin subir 404, OAuth de Meta 302, retiradas 410); webhooks sin firma ≠ 200; slug inexistente ≠ 200. |
| `PermisosFaltantesTest.php` | **12/12 OK** | F1-2: exactamente los 13 (sin `marketing.templates.manage`); partiendo de cero (borrados dentro de la transacción) SOLO super-administrator y DESARROLLADOR los reciben, también con `auto_grant_view_base_roles=true`; 0 directos a usuarios; idempotente; aditiva; `down()` no borra; sin `syncRoles/syncPermissions`; estado actual de `megaisp_test` igual; super-administrador y DESARROLLADOR sin sembrar nada abren Leads/Formularios/Brand Kit; rol «Administrador» NO los recibió; `module.json` los declara sin subir `version`. |
| `RutasRetiradasTest.php` | **9/12** (fallan 3 → F-3, F-4) | F1-3: 410 con el cuerpo exacto; 422 antes (validación, campaña de otra empresa); 404 antes (findOrFail con scope); ver campaña → 302 a `/marketing`; ninguna llamada HTTP/IA; nada se escribe; la UI en uso ya no llama a ninguna retirada; barridos de 500. |
| `DashboardKpisTest.php` | **13/13 OK** | KPIs contra conteo a mano (leads, conversión, gasto, costo por lead, variaciones, embudo de 5 etapas, perdidos/archivados), dinero como cadena, half-up (`5.025 → 5.03`, `223.456 → 223.46`), USD excluido y contado aparte, gasto 0 → costo null, montos de 10 mil millones sin perder centavos, bordes de mes en hora de México, sin `mes` = mes actual; ataques a `mes` (vacío, `2026-13`, `2019-12`, futuro, `<script>`, SQL, 10 000 caracteres, arreglos → 422; el SQL lo frena antes el WAF global con 403 «bloqueada»); otra empresa nunca suma; `company_id` del request ignorado. |
| `CampanasActivasTest.php` | **7/7 OK** | Forma exacta, etiquetas de tipo, orden por inicio desc, límite default 5 / 1 / 20, `total`, leads distintos por atribución (borrados fuera), USD sin convertir, `budget = 0` → null, límite inválido → 422, solo admin. |
| `NavegacionTest.php` | **6/6 OK** | Orden = `module.json` (sin «Empresas y equipo», punto A), sub-items de Video y Publicación, solo `clave/etiqueta/url/icono/sub`, item oculto si falta el permiso, regla B. |
| `AccesoSinRutaPermisoTest.php` | **8/8 OK** | Admin de la 1 SIN roles de sistema abre las 15 pantallas y 13 APIs (antes `check_route_permission` lo sacaba); las acciones siguen pidiendo su permiso Spatie; filas marketing/ventas fuera; sin empresa; inactivo (también si se desactiva a media sesión). |
| `EquipoFijarCommandTest.php` | **9/9 OK** | Comando C con usuarios ficticios: sin `--aplicar` no escribe nada; con `--aplicar` deja SOLO esos logins como admin activos de la 1 y borra las demás filas de la 1 (incluidas inactivas); no toca otras empresas; permisos `marketing.*` aditivos (no quita roles ni permisos, no toca a otros); `activity_log`; login inexistente → exit 1 sin cambios; idempotente (logins repetidos/espacios = una fila); el efecto se ve en el acceso; aviso de inactivo; el comando no trae logins ni ids escritos. |

No se crearon `MarcaNuevaVeSusRegistrosTest` ni `SlugFormularioGlobalTest` (punto D).

## 2. Pruebas de Fase 0 modificadas (todas marcadas `[Fase 1 · B]` o `[Fase 1]` en el código, con su razón)

Ninguna baja la exigencia de NEGAR: donde antes se esperaba que ventas/marketing/admin de B entraran, ahora se exige 403.

| Archivo | Cambio | Razón |
|---|---|---|
| `Fase0/Qa/RutasMarketingProtegidasTest.php` | 58 rutas Blade/Publicación: `['check_route_permission']` → `[]`; nueva `test_ninguna_ruta_de_marketing_lleva_check_route_permission`; total 131 → 134; `nuevas()` + 3 rutas (dashboard ×2 con `admin,marketing`, navegación sin rol) | F1-1 = SÍ y §2.4 |
| `Fase0/Qa/MatrizRolesTest.php` | 15 métodos (con sus data providers): ventas/marketing ahora 403 `sin_acceso` (antes 200 o `sin_permiso_rol`); config compartida desde B ahora `sin_acceso` (sigue verificando que `marketing_settings` no cambia); sesión en B ya no cambia la empresa activa; «quitar asignación» y «bajar de rol» con el admin de la 1 | Regla B / A |
| `Fase0/Qa/AislamientoEndpointsExistentesTest.php` | B→A: 404 → 403 `sin_acceso` (mismas verificaciones de «no cambia nada», sin datos de A); listados/stats/generate-image/alta desde B → 403 sin filas nuevas | Regla B (el 403 `sin_acceso` no depende del id: no revela si existe) |
| `Fase0/Qa/ContextoEmpresaApiTest.php` | 8 métodos: roles ventas/marketing/admin B → 403; filas en la 1 pasan a `admin`; la empresa activa siempre es la 1 aunque la sesión diga B; ventas pidiendo B → 403, admin de la 1 pidiendo B → 404 | Regla A/B |
| `Fase0/Qa/EmpresasApiTest.php` | 7 métodos: el admin de B recibe además fila admin en la 1 para poder entrar; «empresa ajena» se prueba al revés (admin de la 1 contra B → 404; admin solo de B → 403 `sin_acceso`) | Regla B |
| `Fase0/Qa/EquipoApiTest.php` | `comoAdminB()` agrega fila admin en la 1; 5 pruebas «empresa ajena» usan un admin SOLO de B (→ 403) y el admin de la 1 contra B (→ 404) | Regla B |
| `Fase0/Qa/CaminosSinSesionTest.php` | 1 línea: el staff de B ahora recibe 403 al navegar; el formulario público con esa sesión sigue funcionando y el lead cae en A (lo que se prueba) | Regla B |
| `Fase0/Backend/CapaEmpresaMiddlewareTest.php` | 6 métodos: sesión B → activa 1; ventas/marketing → `sin_acceso`; config compartida desde B → `sin_acceso` | Regla A/B |
| `Fase0/Backend/ContextoEmpresaBackendTest.php` | 3 métodos (capacidades, lista, cambiar) | Regla A/B |
| `Fase0/Backend/EquipoApiBackendTest.php` | 1 línea: el «otro admin» recibe fila admin en la 1 | Regla B |
| `Fase0/Backend/ExistsAcotadoBackendTest.php` | Se invierte el actor: admin de la 1 contra datos de B → 422 (antes admin de B contra A) | Regla B; la exigencia `exists` acotado no cambia |

Copias de los originales: `/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/fase1/qa/*.orig`.

Resultado tras ajustar (por nombre): las 16 de Fase 0 en verde salvo **2 que fallan por F-1** (ContextoEmpresaApiTest y
ContextoEmpresaBackendTest, prueba «cambiar de empresa»). Pruebas viejas `tests/Feature/Marketing/*.php` (15 archivos):
19 fallas, **todas ya estaban en la línea base** (`fase-1-linea-base-fallas.txt`), 0 nuevas.

## 3. Fallas encontradas

```
FALLA F-1 · media · backend
Qué pasa:       POST /api/marketing/contexto/empresa-activa con company_id de B (si la persona tiene fila en B) responde
                200 anunciando B («empresa_activa.id» = B, rol ventas) y guarda B en sesión. La siguiente petición el
                middleware la regresa a la 1, así que no hay fuga de datos, pero contradice la regla A.
Qué debería:    Regla A: la empresa activa siempre es la 1 (responder la 1, o 404/410 al pedir otra).
Dónde:          app/Modules/Addons/Marketing/Controllers/ContextoEmpresaController.php:41-53 (cambiar)
Pasos:          1. Usuario admin en 1 y ventas en B. 2. POST empresa-activa {company_id: B}. 3. La respuesta dice B/ventas.
Prueba:         Fase0/Qa/ContextoEmpresaApiTest::test_cambiar_de_empresa_guarda_en_sesion_y_responde_la_misma_forma
                Fase0/Backend/ContextoEmpresaBackendTest::test_cambiar_de_empresa_guarda_en_sesion_y_responde_la_nueva (fallan hoy)

FALLA F-2 · media · backend (decisión de Henry)
Qué pasa:       marketing:equipo-fijar solo da permisos `marketing.%`, pero los controladores también exigen
                view-video-templates, view-video-content, generate-video-content, delete-video-content,
                view-marketing-campaigns, create-/delete-marketing-campaigns, regenerate-marketing-variants,
                manage-marketing-niches y test-voices. Un admin fijado por el comando que NO sea super-administrador/
                DESARROLLADOR recibe 403 en Video, Campañas IA, Voces y Nichos. En dev ambos roles base sí los tienen
                (10/10, leído en megaisp), así que hoy solo afecta a quien no tenga esos roles.
Qué debería:    Punto B: «admin con todas las facultades».
Dónde:          app/Modules/Addons/Marketing/Console/EquipoFijarCommand.php:73
Prueba:         no se agregó prueba que falle (depende de la decisión: ¿el comando da también esos permisos?)

FALLA F-3 · baja (preexistente / entorno) · backend → supervisor
Qué pasa:       GET /api/marketing/voice-comparator/voices → 500 en megaisp_test: «Unknown column preferred_voice_model».
                La columna existe en megaisp (dev) pero NINGUNA migración del repo la crea (drift). Una instalación
                limpia daría 500 en el Comparador de voces.
Dónde:          app/Modules/Addons/Marketing/Controllers/VoiceComparatorController.php:36 y :109
Propuesta:      migración aditiva con guard hasColumn en el módulo (decisión del supervisor; fuera del paquete).
Prueba:         Fase1/Qa/RutasRetiradasTest::test_barrido_toda_ruta_get_sin_parametros_… y ::test_barrido_toda_ruta_autenticada_… (fallan hoy)

FALLA F-4 · baja (preexistente) · backend
Qué pasa:       Un id no numérico en la URL (abc, «1 OR 1=1», 20 dígitos) da 500 TypeError en 58 rutas por los
                parámetros `int $id` (p. ej. MarketingLeadController.php:79). Con APP_DEBUG=true el 500 muestra
                ruta de archivos. No hay inyección (el TypeError corta antes de consultar).
Qué debería:    404 (p. ej. ->whereNumber('id') en las rutas del módulo).
Prueba:         Fase1/Qa/RutasRetiradasTest::test_ataque_id_no_numerico_en_la_url_no_da_500 (falla hoy)
```

Observaciones (no bloquean):
- O-1 · baja · backend: el mensaje `sin_empresa` dice «te agregue en «Empresas y equipo»», pantalla que el punto A ocultó
  (`Middleware/ResolverEmpresaActiva.php:35`, `views/sin-empresa.blade.php:19`).
- O-2 · baja · preexistente: `MetaAdsWebhookController.php:30` — si `meta_verify_token` no está configurado, el GET de
  verificación con token vacío responde 200 con el `hub_challenge` (texto plano, no escribe nada).
- O-3 · entorno: en `megaisp_test` no existen 11 permisos de seeder (video, campañas, voces, integraciones) → en la vista
  previa las pantallas de Video/Campañas IA/Voces/Publicar muestran 403 en sus llamadas. En dev existen y los dos roles base
  los tienen; no es defecto del código.
- O-4 · frontend: en la captura del Dashboard (compilación de las 10:30) la vigencia de «Fibra 500 Megas» sale «31 oct»; el
  frontend ya lo arregló (`formatoDia`, 10:34) en la compilación 3 — falta verlo compilado.
- O-5: la lista cerrada de públicas incluye el tracking del piloto A/B (`marketing.pilot.track.open|click`, token del correo,
  item #47). No es formulario ni webhook: Henry debe confirmar que se queda como pública.
- Deuda (punto D): quedan 40 líneas `company_id = 1` / `forCompany(1)` en Controllers.

## 4. Ataques en navegador (Playwright 1.60, `php artisan serve --port=8097 --no-reload`, `megaisp_test`)

Sesión generada para cada usuario de vista previa (sin tocar contraseñas). Capturas y salidas en
`/tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/fase1/qa/nav/` (`resultado-*.txt`, `*.png`).

| Ataque | Resultado |
|---|---|
| Doble clic en guardar (dblclick y 3 clics en el mismo instante) — crear lead, crear formulario, crear piloto | **OK**: 1 petición y 1 fila en cada caso (6 de 6). «Agregar persona» no aplica: Empresas y equipo está oculta (A). Renderizar video: no se pudo en `megaisp_test` (O-3). |
| Recargar a mitad — guardando un lead | **OK**: 1 fila, empresa 1, la pantalla vuelve a la lista. |
| Recargar a mitad — cargando el Dashboard (2 recargas seguidas) | **OK**: KPIs, embudo y campañas pintan; consola limpia (`recarga-dashboard.png`). Cambio de empresa: no aplica (selector oculto, A). |
| «Otro usuario de LM escribe la URL» (sustituye a «URL de otra marca», D) — 10 URLs (panel, lista y ficha de lead, editor de formulario, conversación, video, publicación, empresas, 2 APIs) | **OK**: ventas y marketing → 403 (página 403); super-administrador sin fila y sin empresa → 403 «Aún no tienes empresa»; ninguna respuesta trae datos (`acceso-*.png`). |
| Ventas / sin empresa | **OK** (fila anterior). |
| 375 px — 19 pantallas | **OK**: ninguna con scroll horizontal (`p375-*.png`); 1440 px igual. Consola: solo los 403 de O-3 en Video/Generador/Cola/Voces/Campañas IA/Publicar. La captura `p375-marketing.png` salió antes de montar Vue (primera carga en frío); la recarga confirma que pinta. |

## 5. Skill `pruebas-antes-de-avanzar`, pasos 2–7 (el 1 lo corre la sesión principal)

- **Paso 2 — migraciones:** `flock … php artisan deploy:dry-run-migrations --keep --with-data=permissions,roles,role_has_permissions,model_has_permissions,model_has_roles` → exit 0 (1 migración). Con los datos reales de dev: 13 permisos, 26 repartos, SOLO DESARROLLADOR y super-administrator, 0 directos. `migrate:rollback --step=1` (exit 0) y `migrate` (exit 0) en `megaisp_dryrun` → mismo resultado. Migración aditiva (`firstOrCreate` + `givePermissionTo`), `down()` vacío por criterio de catálogo, no inserta en `marketing_empresas` (solo las 2 de Fase 0 la tocan). **Pasa.**
- **Paso 3 — pruebas nuevas:** tabla §1 (normal/error/vacío/borde). **Pasa salvo F-3/F-4.**
- **Paso 4 — aislamiento y rol:** regla B probada en cada ruta; `company_id` nunca del request; datos de otra empresa nunca suman ni aparecen. **Pasa** (F-1 es de forma, no de datos).
- **Paso 5 — seguridad** (sobre `git diff a0af9d95b` + archivos nuevos sin rastrear): grep de llaves vacío; validación por FormRequest en las 2 rutas nuevas; `selectRaw` solo con texto fijo; sin `$guarded = []`; sin `v-html` en código (solo en docs); `(float)` solo para porcentajes (`DashboardMarketingService` `numero()`), dinero con bcmath. **Pasa.**
- **Paso 6 — reglas del sistema:** sin IA directa, sin WhatsApp/Sofía, sin mapas, sin escrituras a Meta, sin USD/float en dinero. **Pasa.**
- **Paso 7 — regresión:** Fase 0 y pruebas viejas por nombre: 0 fallas nuevas fuera de F-1; `git diff --stat` solo toca `app/Modules/Addons/Marketing/**`, `resources/js/components/module/marketing/**`, `tests/Feature/Marketing/**` y `docs/marketing/**` (más `public/js/` sin rastrear). Compilación de frontend: exit 0 (10:30), 2 advertencias de webpack ajenas a Marketing. **Pasa salvo F-1.**

## 6. Limpieza (§2.10)

- Hecha: usuarios `mkt_prev_qa_*` (3) con sus roles/permisos/filas y todo lo que QA creó en navegador («PREV-F1 QA …»: 5 leads, 2 formularios, 2 pilotos). Servidor 8097 detenido; archivos de sesión borrados.
- **Pendiente** (frontend seguía capturando a las 10:49): borrar los datos de vista previa de frontend (PREV-F1, `mkt_prev_admin`, `mkt_prev_sinempresa`, empresa 2 si existe). Script listo:
  `cd /var/www/megaisp-henry-marketing && flock -w 600 /tmp/megaisp-suite.lock env APP_ENV=testing DB_DATABASE=megaisp_test php /tmp/claude-1000/-var-www-megaisp/78fb3bd1-90ba-47f4-819f-1aca73a1fd54/scratchpad/fase1/qa/nav/limpieza.php todo`

---

## 7. §2.6.2 — Restricción automática al aplicar la versión (2026-11-13, tras el commit 98978ff71)

**Prueba nueva:** `tests/Feature/Marketing/Fase1/Qa/RestriccionEquipoEnvTest.php` → **20/20 OK**. Usa usuarios ficticios
(`qa1_eq_*`). La lista se pone con putenv/`$_ENV`/`$_SERVER` y se restaura en `tearDown`. Cubre:
- **Sin marca:** 403 `sin_acceso` en las 134 rutas (127 combinaciones de ruta y método fuera de las 7 públicas), en JSON y en las pantallas, aun con fila admin. También para super-administrador y DESARROLLADOR con fila. Una marca de otra empresa no cuenta.
- **Con marca:** sigue la regla B.
- **Lista vacía** (`''`, `'   '`, `' , ,'`) **o ausente:** nadie activo, no se borran filas, la marca se escribe con group `acceso` y una fecha.
- **Dos logins (con espacios):** solo ellos quedan como admin activos. Las demás filas de la 1 quedan inactivas, no se borran. Reciben todos los permisos de Marketing y no `manage-integrations`; los permisos se agregan sin quitar roles ni otros permisos.
- **Login inexistente:** se ignora. No se crean usuarios.
- **Otras empresas:** sus filas no se tocan.
- **Admins por rol de la 100300:** quedan inactivos (se corre la 100300 real dentro de la transacción).
- **Orden real de deploy (100300 → 100200):** entre las dos nadie entra; después solo entra la lista.
- **Idempotencia:** `up()` dos veces da lo mismo (una sola marca).
- **`down()`:** borra la marca, no reactiva ni borra filas, y nadie entra.
- **`activity_log`:** `equipo_restringido` solo con conteos; ningún login ni `user_id` en las filas nuevas.
- **Código:** la lista se lee del entorno (`Env::get`), no de `config/`; sin `sync*` ni `delete` en la migración; sin ids ni logins escritos.
- **`marketing:equipo-fijar --aplicar`:** escribe la marca; la vista previa no.

**Skill, pasos 2–7 sobre el cambio:**
- **Paso 2 — migraciones:** `deploy:dry-run-migrations --keep --with-data=permissions,roles,role_has_permissions,model_has_permissions,model_has_roles,marketing_empresa_usuario,marketing_settings,users` → exit 0, con 3 pendientes (100000, 100100 y 100200). Con los datos reales de dev y la lista ausente en el `.env` del worktree: 13 filas de la empresa 1 desactivadas, 0 activas, marca escrita. `activity_log` = `{en_lista:0, encontrados:0, ignorados:0, filas_desactivadas:13}`. `migrate:rollback --step=1` quita la marca sin reactivar filas; re-`migrate` la vuelve a escribir. Todo exit 0.
- **Paso 5 — seguridad:** `config:auditar-env` → exit 0 (lee con `Env::get`; si la config estuviera cacheada lee el `.env` directo). Grep de llaves, IA, WhatsApp, Meta, mapas, `sync*`, `*Raw` y `v-html` sin coincidencias en código.
- **Paso 6 — reglas del sistema:** sin coincidencias.
- **Paso 7 — regresión, por nombre:** en verde Fase1/Qa (8 archivos más este) y Fase 0 (MatrizRoles, RutasMarketingProtegidas, CaminosSinSesion, ContextoEmpresaApi, CapaEmpresaMiddleware, EquipoApi y ContextoEmpresaBackend). El diff solo toca el módulo, `MarketingTestCase.php` y `docs/marketing`. F-3 y F-4 ya pasan: RutasRetiradasTest 12/12.

**Observaciones (no bloquean):**
- R-1 · baja: `migrations/2026_11_13_100200_…php:42` escribe en la salida los logins de la lista que no existen. En prod esa salida termina en el log del deploy (`self-update-{id}.log`). Es lo que pide el contrato («se avisa en la salida»), pero deja un login (con errata) en un log. Alternativa: avisar solo el conteo.
- R-2 · baja: `Support/EquipoMarketing.php:173` no filtra por tipo de cuenta. Un login de la lista que sea cuenta `client`, o un usuario inactivo, recibe fila admin y permisos. El inactivo igual no entra (middleware). La lista la controla Irving: solo se anota.
- R-3 · info: sin marca, alguien sin ninguna fila recibe `sin_acceso` en lugar de `sin_empresa` (`Middleware/ResolverEmpresaActiva.php:63`, el candado va antes). Es coherente con «nadie entra».
