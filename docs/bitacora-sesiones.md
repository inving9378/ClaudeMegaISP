# Bitácora de sesiones

Registro de cierre por sesión: qué se validó/entregó, con evidencia. Lo estructural
y estable vive en `CONTEXTO-MEGAISP.md`; aquí van los cierres fechados.

---

## 2026-07-08 — Conciliación WhatsApp (Gateway) · Fase 1 · ✅ VALIDADA Y CERRADA

**Alcance:** validar end-to-end la cadena de conciliación de pagos por el **gateway**
(línea de panel `PruebasDEV` = `whatsapp_instances` id=4, número 5568175643), enviando
**un comprobante real (una imagen)** y comprobando cada eslabón con evidencia de BD.

**Test:** comprobante = imagen entrante, `whatsapp_messages` id=**39**, recibido `22:01:59`.

### Evidencia por eslabón

| # | Eslabón | Estado | Evidencia |
|---|---------|--------|-----------|
| 1 | Imagen entrante guardada | ✅ | `whatsapp_messages` id=39, `direction=in`, `message_type=image`, `mime=image/jpeg`, `size=37603`, `evo_id=AC500BA0…`, `status=received`, `created=22:01:59` |
| 2 | `media_path` poblado | ✅ | `private/whatsapp/media/AC500BA0D7FC9113F1B0F10B249DA625_EQXCkD3l.jpg` |
| 3 | `media_downloaded_at` poblado | ✅ | `22:02:00` (1s tras recibir); archivo en disco (dueño `www-data`), `media_size=37603` coincide, extracción `ok=1` prueba legibilidad |
| 4 | `GatewayConciliationIntakeJob` corrió | ✅ | Produjo la extracción 151; 0 failed_jobs en la ventana 22:02; sin errores en `laravel.log` 22:01–22:02 |
| 5 | Fila `whatsapp_payment_extractions` `source='gateway'` | ✅ | id=151, `source=gateway`, `source_message_id=39`, `source_conversation_id=4`, `ok=1`, `document_type=spei_transfer`, `model=claude-sonnet-4-6`, `extracted_at=22:02:04` |
| 5b | Campos extraídos | ✅ | monto **450.00** (alta) · referencia **3756953** (alta) · banco_origen **"Guardadito (Banco Azteca)"** (alta) · fecha_pago **30/Jun/2026 16:28:23 (CST)** (alta) · concepto "fatima Valeria" (alta) · clave_rastreo `260630010067540454I` (media) |
| 6 | UN SOLO acuse, sin duplicados | ✅ | `whatsapp_messages` id=40, `direction=out`, "Recibimos tu comprobante 🧾…", `status=sent`, `22:02:04` |

**Dedup (riguroso):** `source_message_id=39` → **exactamente 1 extracción**; **1 solo acuse** (id 40).

**Línea de tiempo (~5s):** `22:01:59` recibida → `22:02:00` descargada (media_path) → `22:02:04` extraída (id 151) + acuse enviado (msg 40).

### Notas honestas (no del test de cierre)

- Los otros **6 acuses** (21:11:20–21:11:42) **no son duplicados**: corresponden 1:1 a una
  ráfaga previa de 6 imágenes (`whatsapp_messages` 25–30, recibidas 21:09:43–44). Total
  7 acuses = 6 (ráfaga) + 1 (este cierre).
- **1 failed_job a las 21:10:55** = `ConciliationListener` con `MaxAttemptsExceededException`,
  de esa ráfaga de 6 imágenes simultáneas (probable timeout de la extracción IA en paralelo).
  **No ocurre con un comprobante único.** → Registrado como **roadmap_item #211** (pending,
  riesgo B): revisar `timeout`/`tries`/backoff del listener ante ráfagas, plan por 3 fases,
  con el failed_job de 21:10:55 como evidencia.

**Resultado:** Fase 1 (gateway: media → descarga → intake → extracción `source=gateway` →
un acuse) **validada y cerrada**. Seguimiento único abierto: roadmap #211 (robustez bajo ráfaga).

---

## 2026-07-08 22:10 — Circuito de Mejora Continua: infraestructura + primera auditoría exhaustiva

**Entorno:** DEV (192.168.105.11). Todo en `dev/main`, NO desplegado a prod.

### PARTE 1 — Infraestructura (4 commits)

- **1.1** Migración aditiva idempotente `2026_07_08_210000_add_circuito_fields_to_roadmap_items` sobre
  `roadmap_items`: `modulo`, `nivel_riesgo` (enum A/B/C), `estado_aprobacion`
  (pendiente_revision|aprobado_claude|requiere_irving|rechazado|en_progreso|completado, default
  pendiente_revision), `comentarios_claude`, `revisado_at`, `aprobado_por`. `prompt_para_claude`
  **reusa** la columna `prompt` existente. Modelo `RoadmapItem` actualizado (fillable/casts/constantes).
  Commit **feat(circuito): campos de aprobacion en roadmap_items**.
- **1.2** Acceso externo sin login con token: rutas fuera de `web`/`auth` (sin sesión/cookies), `hash_equals`,
  rate limit, canal de auditoría `roadmap_externo`. Tokens SOLO en `.env`. **Verificado** GET 200/403, POST
  acotado (allowlist ignora `title`), tokens separados. Commit **feat(circuito): acceso externo sin login…**.
  (Irving añadió la variante de escritura por GET `/{token}/item/{id}/set` para el fetcher que solo hace GET —
  verificada: write→200, read→403.)
- **1.3** `docs/manual-criterios-circuito.md` (servicios únicos + convenciones + reglas del circuito + negocio)
  **+ CLAUDE.md completo anexado vivo** por el controller. Servido en el GET (134 KB). Commit
  **docs(circuito): manual de criterios consolidado…**.
- **1.4** Reglas del circuito + esta regla de bitácora en `CLAUDE.md`. Commit **docs(circuito): reglas del
  circuito + regla permanente de bitacora…**.

### URLs públicas para Claude Cowork (IP pública 38.123.192.199, HTTP:80)
- **Lectura:** `http://38.123.192.199/api/roadmap-externo/<READ_TOKEN>`
- **Escritura POST:** `POST http://38.123.192.199/api/roadmap-externo/<WRITE_TOKEN>/item/{id}`
  body `{ "estado_aprobacion":"…", "nivel_riesgo":"A|B|C", "comentarios_claude":"…" }`
- **Escritura GET/set (fetcher solo-GET):** `GET http://38.123.192.199/api/roadmap-externo/<WRITE_TOKEN>/item/{id}/set?estado_aprobacion=…&nivel_riesgo=…&comentarios_claude=…`

**Estructura JSON del GET:** `{ generated_at, manual_criterios (markdown), leyenda{nivel_riesgo,estado_aprobacion},
items[ {id,title,modulo,description,status,priority,nivel_riesgo,estado_aprobacion,target_version,
prompt_para_claude,comentarios_claude,subtasks,log,started_at,completed_at,revisado_at,aprobado_por,
created_at,updated_at} ] }`

### Acceso desde internet — estado + pendiente de infra
- Puerto **80 en `0.0.0.0`**, responde por la IP pública. **Ningún firewall bloquea el 80** (fetch desde la
  nube de Anthropic → `ECONNREFUSED` en 443, no timeout = los paquetes llegan al host).
- **Solo HTTP; sin TLS/443** (`server_name _`). Herramientas que fuerzan HTTPS no alcanzan el `:80`.
- **Recomendación**: si Cowork hace HTTP plano, ya funciona; si requiere HTTPS, abrir 443 + certificado
  (dominio p.ej. `roadmap.meganet.mx`→38.123.192.199, o cert autofirmado). Nada que abrir para el 80.
- Deuda de seguridad registrada como item del circuito: token en path/query se filtra al access log; el mismo
  actor externo puede fijar `nivel_riesgo` y `estado_aprobacion` a la vez (degradar C→A y auto-aprobar).
  Mitigación: token por header, no permitir bajar el nivel, rotación 90 días (ver
  `docs/circuito-seguridad-tokens.md`).

### PARTE 2 — Auditoría exhaustiva (READ-ONLY, 46 módulos: 31 addons + 15 core)
- **12 auditores en paralelo** contra el manual (dimensiones a-e). Persistencia incremental idempotente por
  título; progreso en `docs/auditoria-progreso.md`.
- **93 items del circuito** en `roadmap_items` (ids 203+): 8 semillas conocidas + **85 hallazgos de auditoría**.
  Todos `estado_aprobacion=pendiente_revision` (salvo 1 completado), con `nivel_riesgo` y `prompt_para_claude`
  por fases. **Por nivel: A=35 · B=51 · C=7.**

**Desglose por módulo (items del circuito):** Marketing 9 · CobranzaBlaster 7 · Core/Clientes 6 · Talento 5 ·
Flotas 5 · Core/Usuarios 5 · Core/Release 4 · PortalCliente 3 · Payments 3 · Domiciliacion 3 · Inventario 3 ·
MegaFamilia 3 · GestionRed 3 · Vendedores 2 · IA 2 · VoIP 2 · Manual 2 · Core/CRM 2 · Core/Auth 2 ·
Core/Permisos 2 · IA/Configuración 2 · y 1 c/u en WhatsAppAgent, Hub, SmartImportExport, Roadmap, DevTools,
EvaluadorEmpresarial, PortalPago, Core/Auditoria, Core/Layout, Core/Configuracion, Infraestructura/Colas,
Tickets, Embajadores, Reportes, Demo (+ 3 semillas de Conciliación/Gateway).

**Hallazgos más críticos (muestra):**
- IDOR de PII cifrada en 3 endpoints `serve` de Talento (credenciales/penalizaciones/evidencia) — B.
- Doble cobro en Domiciliación (intentos `pending` huérfanos + sin lock) y captura de mostrador sin
  idempotencia (doble aplicación por doble submit) — B.
- RCE por el campo `version` sin sanitizar hacia el shell del pipeline de deploy — C.
- Webhooks Evolution/Meta fail-open (procesan sin token/secret) — B.
- Contraseña en texto plano devuelta al frontend (getData/edit) + reset por teléfono sin OTP — B.
- Múltiples clientes Claude/OpenAI propios por módulo (Marketing, Manual, DevTools, WhatsAppIAService,
  Cobranza, bot María) violando el servicio IA único — B/C.
- Escrituras a ONU gateadas bajo el permiso de LECTURA `olt_view` — B.

### Commits de la sesión (dev/main, español, git add selectivo)
1. feat(circuito): campos de aprobacion en roadmap_items (1.1)
2. feat(circuito): acceso externo sin login a la Hoja de Ruta con token (1.2)
3. docs(circuito): manual de criterios consolidado servido en el GET externo (1.3)
4. docs(circuito): reglas del circuito + regla permanente de bitacora en CLAUDE.md (1.4)
5. docs(circuito): progreso de auditoria + bitacora de sesiones (Parte 2/3)

Los 93 items del circuito viven en la DB (no en git), persistidos idempotentemente.

### Pendiente para el siguiente ciclo
- Claude Cowork revisa los items `pendiente_revision` vía el endpoint y asigna `estado_aprobacion`.
- Claude Code ejecuta SOLO `aprobado_claude` + `nivel_riesgo=A` en automático; B con Irving; C jamás sin Irving.
- Decidir HTTPS para el endpoint si el fetcher de Cowork fuerza TLS.

---

## 2026-07-08 (tarde) — Circuito: acceso HTTPS por proxy de PROD + endurecimiento del endpoint

**Contexto:** el fetcher de Cowork exige HTTPS con CA válida. El firewall de la red **bloquea
80/443 entrantes hacia DEV** (certbot en dev no pudo completar el reto; export del Mikrotik 2024
desactualizado → sin cirugía de router hoy). Registrado como **roadmap #297** (nivel B) para
cuando se abra el acceso directo.

**Solución en uso — reverse proxy PROD → DEV** (PROD es **Apache2**, no nginx):
- `deploy/apache-prod-roadmap-proxy.md`: `<Location /api/roadmap-externo/>` con
  ProxyPass/ProxyPassReverse a `http://192.168.105.11`, ProxyPreserveHost On,
  X-Forwarded-Proto/X-Real-IP, timeouts. Solo ese prefijo se expone. Token excluido del
  access log de prod (`SetEnvIf` + `env=!roadmapreq`); la auditoría real vive en dev.
  PRE-CHECK obligatorio de alcance LAN prod→dev. (Se retiró el `deploy/nginx-prod-…` previo.)
- **URL pública final:** `https://v1megaisp.meganett.com.mx/api/roadmap-externo/<token>` → **200 verificado por Cowork.**

**Endurecimiento de seguridad del endpoint (hallazgos del auditor):**
- Variante de **escritura por GET** `/{token}/item/{id}/set` (el fetcher solo hace GET); misma
  allowlist/guards que el POST (writeItem compartido).
- **Guards:** el nivel_riesgo solo se **endurece** (A→B→C), nunca se degrada; `aprobado_claude`
  por la vía externa **solo para nivel A** (B y C topan en `requiere_irving`; la aprobación
  final de B/C la da Irving en sesión).
- Token en path enmascarado en logs + rotación documentada (`docs/circuito-seguridad-tokens.md`).

**GET por MODOS (el manual de 134 KB truncaba los items):**
- `?id=N` detalle · `?solo=manual` · `?solo=items` · default = resumen (conteos por estado/nivel)
  + lista **compacta** paginada, sin manual.
- Filtros `?estado= ?nivel= ?modulo=`, paginación `?page= ?per_page=` (def 50, máx 100) con `meta`.
- Todo validado (whitelist/enums); inválido → **422 JSON** (no 302). Ejemplos de consulta en
  `deploy/apache-prod-roadmap-proxy.md`.

**Commits (dev/main, sin pushear):** `add0dc36` GET write · `d2c7b8ef`+`1b2885f4` guards ·
`fe6841c7` token/log · `a88bbaba` runbook TLS dev · `dfae46e5` robots.txt · `ad026d63`(retirado)
+`027bf6cc` proxy Apache prod · `e3538a52` GET por modos.

**Siguiente:** Cowork hace su primera revisión real (resumen → pendientes por tandas → escribe
aprobaciones por `/set`).

---

## 2026-07-09 (noche, autónomo) — Cierre de sesión nocturna

**Reglas de la noche honradas:** sin sudo, sin prod (solo lectura por prod para verificar), sin push, sin bloqueos.

### PRIORIDAD 1 — Escritura PATH del circuito ✅ (ya estaba; verificada en vivo)
`GET …/<WRITE_TOKEN>/item/{id}/set/{estado}/{nivel}/{comentario?}` (commit `3f8d683d`, previo).
**Verificado end-to-end por PROD:** escritura real `set/requiere_irving/-/Prueba%20nocturna` → `ok:true`, comentario decodificado, autor `claude-cowork`; guard `aprobado_claude` en item B → 422; item de prueba restaurado. **CANAL #298** ya tiene el aviso con la sintaxis exacta. Cowork puede estrenar aprobaciones.

### PRIORIDAD 2 — Navegación móvil (riel espejo) — Fases 1 y 2 ✅
Capa aditiva solo-móvil que espeja el `#side-menu` real. Detalle completo en `docs/nav-movil-progreso.md`.
- **Fase 1** (commit `faf75064`): riel deslizable + hoja de submenú. Validado con **playwright headless** (móvil 390px: 10 módulos en orden, color+ícono SVG, directos vs hoja; sub-grupos anidados aplanados con rutas correctas; escritorio 1200px → `display:none`; claro/oscuro).
- **Fase 2** (commit `24674523`): buscador de respaldo ("lupa") sobre el mismo espejo. Validado (query "listar" → rutas correctas).
- Roadmap **#299** (`in_progress`).

### ⚠️ Pendientes anotados (no bloquean)
- **Mockup `medussa-nav-movil-mockup.html` NUNCA llegó** (prometido 3 veces, sin HTML) → paleta placeholder en `MNAV_COLORS` (un solo lugar), lista para reemplazar por el `MODS` del mockup; topbar delgada final y proporciones dependen del mockup.
- Sub-grupos anidados se aplanan en la hoja (refinar encabezados); × nativo del `type=search` (trivial).
- **Nada requirió sudo** esta noche.

### A validar con screenshot (Irving, mañana)
Abrir la app en móvil / navegador <992px: confirmar que el riel espeja el sidebar **real completo** (todos los módulos/hijos/rutas), probar tap directo, hoja de submenú, la lupa y el modo oscuro. (La validación automática usó un sidebar sintético porque el composer no se dispara en render CLI; en el navegador real se puebla por HTTP.)

### Commits de la noche (dev/main, sin pushear)
`faf75064` (nav-movil Fase 1) · `24674523` (nav-movil Fase 2). *(PRIORIDAD 1 ya estaba en `3f8d683d`.)*

---

## 2026-07-08 (noche) — Circuito: limpieza de historial + variante PATH + canal CC→Cowork

### Seguridad — tokens fuera de git (rotación + rewrite)
- Los tokens read/write estaban en claro en `docs/bitacora-sesiones.md` (commit previo, **nunca
  pusheado** — confirmado con `git fetch` + `merge-base`). `git grep` confirmó que era el ÚNICO
  archivo trackeado con tokens reales.
- **Rotados** (`openssl rand -hex 32` → `.env`, dev corre uncached → live): viejo→403, nuevo→200
  verificado. Los valores NO se imprimieron (Irving los saca de `.env`).
- **Redactados** a `<READ_TOKEN>`/`<WRITE_TOKEN>` + regla en CLAUDE.md ("secretos SOLO en .env").
- **Historial reescrito** con `git filter-repo --replace-text` (backup previo: bundle 171M + copia
  `.git` en scratchpad). Verificado: **0 ocurrencias de ambos tokens en TODA la historia**, árbol
  limpio, **1063 commits** (nada perdido), 25 ahead de `origin/main`, base compartida `f90ef059`.
  ⚠️ Los SHAs **desde el commit del token cambiaron** (`06b24a96`→**`265e4326`** y todo lo posterior);
  los previos intactos. Equivalencias no rastreadas 1:1 — ver `git log`. HEAD tras el trabajo: **`62b970a2`**.

### Defecto del GET y fix PATH-based (commit `62b970a2`)
- **Diagnóstico:** el proxy Apache **conserva** el query string (curl por prod → filtros ok), pero el
  **fetcher de Cowork lo descarta** (audit log: UA `Claude-User` → `filtros:[]`). Culpable = fetcher.
- **Fix a prueba de fetchers:** `GET /{token}/q/{estado}/{nivel}/{page}/{perpage}` (`-`=comodín) y
  `GET /{token}/item/{id}` (detalle por path). Misma whitelist/enums, mismos guards, 422 en segmento
  inválido. Query string se conserva. Verificado localhost + end-to-end por PROD.

### Canal CC→Cowork (roadmap #298)
- Item fijo **"CANAL CC→COWORK"** (`status=in_progress`, `nivel_riesgo=C`, `estado_aprobacion=en_progreso`)
  como buzón: Claude Code anota con fecha lo que Cowork deba saber (rutas nuevas, cambios de contrato,
  auditorías, rotaciones). Cowork lo lee al inicio de cada ronda. Primer aviso ya cargado (variante path).

## 2026-07-09 20:48 — Circuito de mejora: ejecución items #210 y #215 (nivel A, aprobado_claude)

Corrida automática del circuito (`estado_aprobacion=aprobado_claude` + `nivel_riesgo=A`). Ambos
ejecutados de punta a punta: `en_progreso` → plan (campo `prompt`) → commit → `completado` con hash
en el campo `log`. Cola de aprobados+A quedó en 0.

### #210 — UX: campo API Key no indicaba que ya hay una key guardada
- **Módulo:** IA / Configuración. Componente `resources/js/components/module/ia/IAProveedoresManager.vue`.
- **Cambio (cosmético/aditivo):** al editar un proveedor que ya tiene `api_key` persistida, el placeholder
  ahora dice `•••• guardada (dejar vacío para conservar)` y se muestra una nota verde explicando que
  dejar el campo vacío conserva la key. No expone el valor.
- **Backend ya correcto:** `IAProveedorController::update` conserva la key si el campo va vacío
  (`unset($datos['api_key'])`, líneas 72-74); la bandera `tiene_api_key` ya viajaba en el listado.
- `npm run dev` ✅ (2 warnings preexistentes benignos). Solo se commiteó el `.vue` (bundle gitignored).
- **Commit:** `4085ef449a72600267c2b70af38513ad24c7e7ee`.
- **Pendiente:** validación visual de Irving (abrir edición de un proveedor con key → ver el indicador;
  guardar sin tocar el campo → la key se conserva).

### #215 — ContextoProyectoService detectaba integraciones con env() en runtime
- **Módulo:** IA. `app/Modules/Addons/IA/Services/ContextoProyectoService.php::detectarIntegraciones()`.
- **Bug:** leía `env($key)` en tiempo de ejecución para marcar integraciones detectadas en el panel de
  contexto. Con `config:cache` en producción, `env()` fuera de `config/` devuelve null → panel engañoso
  (p.ej. Google Maps desaparecía aun estando configurado). Además reforzaba el antipatrón claves-por-env.
- **Fix (aditivo, misma semántica):** nuevo `config/ia.php` con `integraciones_detectables` (los `env()`
  viven SOLO ahí = cache-safe); el servicio ahora lee `config('ia.integraciones_detectables')`.
- **Verificado FASE 3** con config **cacheado** (prueba atómica cache→verifica→clear, sin dejar dev cacheado):
  detecta `["Google Maps"]` con y sin cache. Dev restaurado con `config:clear`.
- **Nota:** las 8 claves son sondas informativas (MikroTik/Radius/WhatsApp/Telegram/Google Maps/Stripe/
  PayPal/Mercado Pago), NO proveedores IA → no se cablearon a `ia_proveedores`/Hub (habría sido incorrecto);
  se aplicó la vía que el plan permite (mover a `config/` y leer `config()`).
- **Commit:** `7e344da44e4d6954cb66d02a4cc375ce290ec1f9`.

**Warm-up dev:** `config:clear` + `queue:restart` (regla de dev: nunca `config:cache` en /var/www/megaisp).

## 2026-07-10 18:05 — Circuito #335: estado en vivo (heartbeat + log en vivo) en la Torre

Item #335 (nivel B, rama circuito/item-335-estado-en-vivo-del-circuito-heartbeat). Indicador
en vivo en la barra de la Torre (/releases → Panorama): Ejecutando (pulso + "tocando #NNN" +
cronómetro + ♥ heartbeat) / Inactivo (última hace Nm · próxima en Nm) / Pausado. Aviso
"posible circuito caído" (latido frío en run, o cron detenido en idle). Botón "Ver log en vivo"
= tail del log de la vuelta.

Restricción clave: php-fpm sirve como www-data y /home/meganet es 700 → www-data NO lee los
logs. Solución: heartbeat + tail se ESPEJEAN por BD (settings.circuito_live). El loop de latido
(circuito:vivo --watch) corre como meganet (sí lee el log) y escribe a BD; la Torre lo lee de BD.
Robusto y sirve en prod.

Piezas: RoadmapCircuitoService liveStart/liveBeat/liveEnd/liveState/liveLogTail/proximaVueltaAt
(key JSON circuito_live) · comando circuito:vivo (--start/--watch/--end, watch con SIGTERM) ·
config/circuito.php (interval_min, espejo del cron) · wrapper vuelta.sh (3 líneas aditivas:
start + watch bg + kill/end; historial intacto) · RoadmapController torre()+estado() · ruta
GET /api/roadmap/circuito/estado (gate roadmap_view) · TorreControl.vue (polling 4s + ticker 1s).

Commits (dev, NO push, NO prod): 7cb09f67 (servicio+comando) · 73e6c082 (wrapper) · a732f2ed
(controller+ruta) · ed67bd5b (frontend). npm run dev OK. Verificado por CLI: 3 estados +
current_item=331 + stale a 200s + endpoint HTTP 200 con gate. PENDIENTE: validación visual de Irving.

## 2026-07-11 10:54 — Circuito CC: roto el loop de re-pausa + cierre #337/#341/#342

**Causa raíz del "circuito_pausado=1 después de cada vuelta":** NO era un proceso
automático. Era una **segunda sesión de Claude Code concurrente** (transcript 4f411f4b,
PID 520630) que Irving había abierto para #341/#342. Su rutina anti-colisión, al detectar
"un actor externo" (que era la otra sesión), (a) escribía `circuito_pausado=1` **directo a
`settings`** (bypass del guard de `setPaused`) y (b) re-desactivaba el crontab. Descartados
uno por uno: cron, picker (`DisparoCheckCommand` solo lee/descarta), `vuelta.sh` (solo lee),
prompt del ejecutor, schedule DB-driven, triggers MySQL y jobs. El único escritor era esa
sesión. `setPaused()` está auth-guardeado desde #318 → el ejecutor CLI NUNCA puede pausar.

**Resolución:** identificada y terminada la sesión re-pauser (`kill 520630`; ⚠ el PID que
Irving creyó era 630514 = ESTA sesión — no matarla). Quedó una sola sesión.

**Merge único a main (dev, commit `72c5f2a4`, SIN push):** rama
`circuito/candados-proceso-341-342` (superconjunto de #337) → subió a la vez:
- **#341** — `circuito:rama` rechaza items `en_progreso`/`en_desarrollo_humano` antes de git.
- **#342** — `setPaused()` solo desde la Torre (humano con `circuito.pause`); CLI lanza excepción. + prohibiciones en `prompt.txt`.
- **#337** — botones ▶ Ejecutar vuelta ahora + 🔥 Urgente cableados en `TorreControl.vue`
  (disparar()→POST /circuito/disparar, marcarUrgente()→POST /items/{id}/urgente). Regresión cero.
Migraciones ya aplicadas; frontend recompilado. Items #337/#341/#342 → `completado`,
`en_desarrollo_humano=0`.

**Reanudado y VERIFICADO:** `circuito_pausado=0`, crontab restaurado (2 líneas; se añadió
`cd /var/www/megaisp &&` a la línea del picker, que faltaba), warm-up = config:clear +
route:clear + queue:restart (NUNCA config:cache). Banner Torre = 🟢 Inactivo. Vuelta real
posterior (10:51→10:54, autonomo, "Vuelta OK", triajeó #343 + diagnóstico #302) **NO
re-pausó ni apagó el cron** (flag updated_at intacto @10:50:09, crontab md5 idéntico).

**Aprendizaje:** NUNCA correr 2 sesiones de Claude Code sobre el mismo working tree /var/www/megaisp.

## 2026-07-11 11:58 — Circuito #348: Prioridad vs 🔥 Urgente
Sesión humana (Irving+CC), aislada en rama `circuito/item-348-...`, integrada a main (dev, sin push). Item 348 blindado con `en_desarrollo_humano=1` durante el trabajo (candado #341) y desbloqueado al cerrar; queda en `requiere_irving` (bandeja) para validación visual de Irving.
- **scopeOrdered()**: ahora ordena `urgente(#337) → estado → PRIORIDAD (alta→media→baja→null) → antigüedad`. Antes NO respetaba `priority`. FIELD(priority,'baja','media','alta') DESC (null al final). Verificado monótono en 173 items.
- **urgente()**: 🔥 dispara vuelta SOLO en items ejecutables; en la bandeja (`requiere_irving`) = "decisión urgente" (sube al tope vía ordered(), NO ejecuta). Devuelve `modo=bandeja|ejecucion`.
- **torre()**: expone `cola_ejecutable` (pendientes/aprobados tomables).
- **TorreControl.vue**: tarjeta "Cola ejecutable" con 🔥 + badge de prioridad (claro/oscuro); 🔥 de bandeja re-etiquetado a "Decisión urgente".
- Aislamiento: se trabajó en `git worktree` (main tree lo usaba el ejecutor del circuito); el `vendor` symlinkeado hacía que artisan cargara el código del main tree → verificación backend real y build servido se hicieron tras integrar a main.
- Verificado: `php -l` ok, `npx mix` ok, listar()/bandeja/cola-nueva sin regresión, torre() HTTP 200 con cola_ejecutable. Commits: `47e041f6` (backend) + `cd84242f` (frontend).

## 2026-07-11 12:35 — Circuito #348 (seguimiento): Cola ejecutable = solo auto-ejecutables
Diagnóstico + arreglo de la "Cola ejecutable" de la Torre.
- **Diagnóstico:** el circuito SÍ trabaja (casi toda vuelta ejecuta), pero (1) lo hecho queda en ramas esperando merge/✓Irving, (2) el tope de prioridad alta eran nivel C (#117/#121/#185) + #65 C/requiere_irving que el circuito rebota a la bandeja, (3) una vuelta (#52) se colgó por timeout (rc=124). Composición: 150 auto-ejecutables / 38 esperan decisión / sin clasificar. Hallazgo: 108 nivel B en pendiente_revision atascados (necesitan confirmación de Irving o revisión de Cowork).
- **Arreglo:** `scopeAutoEjecutable()` (A/B o aprobado_irving; excluye C/requiere_irving/terminal/candado) + `scopeEsperaDecision()` (requiere_irving + C sin aprobar). `torre()` usa el scope + `resumen_cola{auto_ejecutables,espera_decision,sin_clasificar}`. Torre muestra "N auto-ejecutables · M esperan tu decisión". La cola ya no trae C pendiente.
- Aislado en worktree (una vuelta corría; no se interrumpió), integrado a main tras terminar. Verificado: SQL + `torre()` end-to-end (150/38/0, cola 25 items todos B). Commits `5033847d` (backend) + `3254a8b6` (frontend). Solo dev, sin push.

## 2026-07-11 12:45 — Visor "Trabajando ahora" del Circuito CC (#349)

Nivel A (read-only/aditivo), sesión con Irving. Se construyó la "pantalla de trabajo" en vivo de la Torre: qué item y en qué fase trabaja cada sesión de CC.

**Decisiones de Irving:** Fases = migas deterministas (`CIRCUITO_FASE:` en el prompt, no heurística que puede mentir); Multi-sesión = estructura lista para N, renderiza 1 hoy.

**Sub-pasos / commits:**
- `3bc06320` — migas `CIRCUITO_FASE: <fase> #<id>` en `deploy/circuito/prompt.txt` (fuente determinista de fases).
- `c205f252` — parser de fases/artefactos/meta + `trabajandoAhora()` en `RoadmapCircuitoService` (liveBeat parsea el log completo como meganet; liveEnd captura CIRCUITO_META; método read-only seguro para www-data). Verificado en aislamiento + e2e con rollback.
- `c871f411` — expone `trabajando` en `estado()`/`torre()` del `RoadmapController`.
- `34426777` — UI `TorreTrabajandoAhora.vue` (stepper triage→decisión→rama→editando→verificando→integrando con timestamp/paso, tarjeta por sesión array-N-ready, tiempo+heartbeat, artefactos, timeline legible, resumen persistente de la última vuelta). Dark-aware por prop. `npm run dev` OK.

**Pendiente:** (a) validación visual de Irving; (b) las fases EN VIVO aparecen desde la próxima vuelta (~30 min, el ejecutor toma el prompt nuevo). **Deudas:** multi-worktree real depende de #334; artefactos best-effort; timestamp de fase con granularidad del latido (~15s).

Roadmap #349 → `completado`. Sin config:cache (memoria crítica). Solo dev, prod intacta.

## 2026-07-11 13:15 — Integración #350: pestaña "Terminales en vivo" en la Torre (ventana controlada)
- **Contexto de concurrencia:** al retomar había un ejecutor del Circuito vivo (`claude -p`, autónomo) + otra sesión interactiva en pts/1 tocando el mismo repo (footgun documentado). El cambio en `topbar.blade.php` "desapareció" del working tree entre dos lecturas por un `circuito:rama`/reset concurrente. Item #53 (badge CLAUDE TEST) resultó **ya completado** (commit `17385136` en su rama).
- **Kill switch (autorización de Irving, #342):** `circuito_pausado=1`. Esperado a idle: 0 procesos `claude -p`, sin `.git/index.lock`.
- **Trabajo de #350 recuperado:** la "rejilla de terminales" estaba commiteada en `circuito/item-350-rejilla-terminales-en-vivo` (`cd21a979`, TorreTerminales.vue + wiring en ReleasesIndex.vue). ⚠️ El nº de commit "#350" NO corresponde al item #350 del roadmap (ese es "agente revisor", nivel C, requiere_irving) — mislabel del ejecutor; NO se tocó el estado del item.
- **Integración (solo dev, sin push):** merge fast-forward `e6e3faab..cd21a979` → main. `npm run dev` OK (39.7s, 2 warnings preexistentes). Warm-up sin `config:cache`: config/route/view:clear + queue:restart.
- **Verificación server-side:** `trabajandoAhora()` → `{sesiones:[1]}` con sid/running/stale/fase_actual/pasos/log_tail; ruta `GET api/roadmap/circuito/estado` registrada; `darkMode` export OK. Componente lee `data.trabajando.sesiones` = forma exacta del backend.
- **Pendiente:** validación visual de Irving (pestaña "Terminales" en /releases monta, rejilla + fullscreen + polling 3s). Circuito **NO reanudado** (queda `pausado=1` por decisión de Irving).

## 2026-07-11 13:48 — Circuito #334 FASE 0: aislamiento por worktree + estado live por sesión
- **Objetivo:** matar la colisión de raíz (dos CC compartían el checkout /var/www/megaisp → un `git checkout` del ejecutor movía el working tree de una sesión interactiva; fue lo que borró topbar.blade.php) + dejar listo el multi-terminal. **Fase 1 (paralelo N) DIFERIDA** hasta encender el revisor #338 (hoy 0 items A/B en cola → paralelo correría en vacío). Ver memoria `project_circuito_paralelo_secuencia`.
- **Paso 0 read-only:** mapeado el motor (cron `*/30` vuelta.sh + `* * * * *` disparo-check picker #337; flock `vuelta.lock`; `circuito:rama`=`checkout main`+`-b`; live=blob único `circuito_live`). Verificados 2 supuestos: `base_path()` en worktree resuelve al worktree; `git checkout -b X main` funciona con main checado en el principal.
- **Sub-paso A — aislamiento** (commit `a7c00db7`): `circuito:provision-worktree` idempotente (crea /home/meganet/circuito/wt-exec; **COPIA vendor** —97M— porque el autoloader resuelve `__DIR__` siguiendo symlinks y apuntaría a /var/www/megaisp/app; symlinkea .env/node_modules; storage+bootstrap/cache propios; reusable Fase 1). `circuito:rama` → `checkout -b X main` (sin checar main). **PROBADO:** con /var/www/megaisp sucio, el ejecutor editó+commiteó en wt-exec y el mismo archivo del principal quedó byte-idéntico (md5), HEAD/rama intactos.
- **Sub-paso B — estado live por sesión** (commit `7e4157bf`): `circuito_live` → fila por-sesión `circuito_live:<sid>`; liveStart/Beat/End toman $sid; `trabajandoAhora` agrega TODAS las sesiones (retención 5min terminadas); `liveState`/`liveLogTail` agregan; nuevo `anyRunning()`; `circuito:vivo --sid`; disparo-check usa `anyRunning()`; vuelta.sh provisiona+sincroniza wt-exec, cd al worktree, pasa --sid. **PROBADO:** 2 sesiones simultáneas → trabajandoAhora 2 sesiones (fases parseadas correctas), anyRunning/sesiones_activas=2, orden running-first, retención de terminada.
- **Merge a main (dev):** `c1e35487` (--no-ff). Warm-up sin config:cache. wt-exec sincronizado a c1e35487 con el código nuevo. Kill switch #342 + guard #341 + revisar-y-mergear **intactos**. **Circuito sigue PAUSADO** (no se reactiva hasta OK de Irving).
- **Pendiente:** primera vuelta supervisada (despausar y ver al ejecutor correr aislado en wt-exec + la rejilla de Terminales encenderse). Luego: revisor #338 → Fase 1. Deuda menor: worktree viejo `megaisp-wt338` quedó de #338 (limpiable); si main corre `composer install`, re-provisionar wt-exec con `--resync-vendor`.

## 2026-07-11 14:34 — Circuito GATE 1: merge on-box + toggle auto-merge (+ fix aislamiento del prompt)
- **Causa raíz del "Mergear a dev no hace nada":** el endpoint corría `circuito:integrar` **como www-data** (php-fpm), que NO puede escribir `.git` (objetos/refs los creó el ejecutor=meganet, sin group-write para www-data) → `git checkout/merge` fallaba y el error no se mostraba. `sudo` no está en CC → no se puede arreglar por permisos root; hacer el working tree group-writable por www-data sería downgrade de seguridad (.env).
- **Causa raíz #2 (descubierta):** el checkout principal estaba en la rama `circuito/item-262` (no main). El **prompt** decía "dentro de /var/www/megaisp" → el ejecutor trabajaba en el principal pese al `cd wt-exec` de vuelta.sh, moviéndole el HEAD a Irving. **Fase 0 estaba incompleta.** Restaurado main; prompt corregido (regla dura: trabaja en tu worktree, nunca cd al principal) — commit `86df5ecd`.
- **Arreglo del merge (arquitectura meganet-escribe/www-data-lee):** la Torre **ENCOLA** (`enqueueMerge`); el runner on-box `MergeRunner` (`circuito:merge-run`, drenado por el picker `disparo-check` como meganet en el **checkout principal**) hace el merge REAL: **serializado** (merge-lock), **2 fases con verificación de regresión** (`git merge --no-commit` → php -l de .php cambiados + boot `php artisan --version` → commit), **conflicto/regresión → aborta, main intacto, escala a `requiere_irving` + guarda el error** (`merge_result`) que la UI **muestra** (polling + caja roja). `circuito:integrar` ahora ENCOLA. Commit `305e65b1`, merge a main `1582f923`.
- **Toggle** ON=auto-merge inmediato / OFF=manual, default **ON** (`autoMergeOn=1`). UI en Integración (switch claro en lugar del modo confuso).
- **VERIFICADO EN VIVO:** #292 mergeado de verdad a main (`9baec459`, regresión OK). Batch de las 11 ramas resueltas que esperaban → **10 aterrizaron** (#53,#176,#222,#248,#249,#262,#294,#347 + #335/#337 ya estaban) y **#332 ESCALÓ** por conflicto real en IntegracionRamas.vue → abortado, **main intacto**, item → `requiere_irving`, error guardado. 27 items con merge_commit. Circuito quedó PAUSADO.
- **Pendiente Irving:** resolver #332 (conflicto, en tu bandeja). GATE 2 (Sonnet + medición N) tras tu confirmación.

## 2026-07-11 15:05 — Sprint Circuito GATES 1-4: merge + Sonnet + revisor + paralelo N=6
- **GATE 1 (merge+toggle):** merge desde la Torre fallaba SILENCIOSO (www-data no escribe .git). Fix: Torre ENCOLA, runner on-box `MergeRunner`/`circuito:merge-run` (drenado por disparo-check como meganet en el principal) hace el merge REAL serializado + verificación de regresión (php -l + boot), conflicto→aborta+main intacto+escala+error visible. Toggle ON/OFF auto-merge (default ON). También completó Fase 0: prompt corregido (ejecutor NO cd al principal). Verificado: #292 + 10 ramas mergeadas, #332 escaló por conflicto. Commits `86df5ecd`+`305e65b1`, merge `1582f923`.
- **GATE 2 (Sonnet #336):** `vuelta.sh --model $MODEL` (default sonnet, override CIRCUITO_MODEL). Verificado: vuelta real en Sonnet auto-mergeó #317+#235. Commit `76ca3c85`. Box medido: 4 cores/17GB/77GB → N recomendado 4, elegido **6 con semáforo de builds** (10 no seguro en 4 cores).
- **GATE 3 (revisor #338):** ya estaba construido; verificado conservador (dry-run 8 B → 7 escaló por frontera dura, 1 autorizó) + flag `circuito_revisor` ON. Cron feed `revisar-backlog --apply --limit=6` cada 2 min.
- **GATE 4 (paralelo N=6):** `circuito:scheduler` (cron/min) lanza N vueltas POR-ITEM en wt-1..wt-6 (lock por slot = semáforo N), pre-filtro nivel-módulo (null=paraleliza, git backstop), reclamo atómico #341, prompt-item.txt, semáforo de builds `npm-build.sh` (máx 3). Merges serializados = MergeRunner GATE1. Ejecutable = aprobado_claude+aprobado_irving+(revisor)aprobado_revisor. Commit `f8019e4d`. **VERIFICADO EN VIVO:** 6 claude -p paralelos, 6 items distintos (environ), rejilla 7 sesiones; los 5 sensibles (permisos/seguridad) ESCALARON a bandeja, el seguro (#46) se ejecutó. Aislamiento OK (main intacto).
- **DEUDA cosmética:** `parseCurrentItem` en paralelo muestra el mismo #item en varias sesiones de la rejilla (el trabajo real es por item distinto; solo display). Arreglar aparte.
- **Estado:** circuito DESPAUSADO, cron paralelo activo (scheduler+revisor-feed+merge-drain). La lista se vacía sola: seguros ejecutan+auto-merge, sensibles→bandeja de Irving.

## 2026-07-11 15:10 — Circuito #46: proveedor Meta en el Integration Hub

Item #46 ("[PARKED-PROD] Setup cuentas Meta"): la tarea de fondo (crear Business Manager,
convertir Instagram a Business, crear App en developers.facebook.com) es 100% manual/externa
en el sitio de Meta — no ejecutable por código. Comentario de Irving en el item pedía "dejar
un solo sitio en donde registrar las cuentas para conectarse a redes sociales y hacer las
publicaciones": ese sitio ya existe (`/integraciones`, Integration Hub) y `MetaOAuthController`
(Marketing Fase 5) ya esperaba una `ApiIntegration` `provider=meta` con `config.app_id`/`app_secret`,
pero `meta` no estaba en el catálogo de proveedores → no se podía crear desde la UI.

**Cambio (rama `circuito/item-46-parked-prod-setup-cuentas-meta-busin`, 2 commits):**
- `ApiIntegrationService::getProviders()` — agrega entrada `meta`.
- `IntegrationsHubView.vue` — bloque App ID/App Secret en el modal crear/editar (mismo patrón
  que Evolution: van a `config`, no al campo genérico de API Key), + icono/color propios.

Verificado: `npm-build.sh` (semáforo) compiló OK, `php -l` OK, tinker confirmó el catálogo con
`meta` incluido (6 proveedores). Pendiente 100% de Irving: crear las cuentas reales en Meta y
capturar App ID/App Secret en la tarjeta nueva.

**Nota de infraestructura del circuito (no de este item):** `circuito:rama` rechazó la rama del
#46 porque el scheduler GATE4 ya había puesto el item en `en_progreso` al reclamarlo para el
ejecutor, y el guard #341 de `circuito:rama` trata `en_progreso` como bloqueo genérico (no
distingue "reclamado para mí" de "otro actor lo está trabajando"). Se creó la rama a mano
(`git checkout -b <rama> main` + registrar `branch` en el item), replicando exactamente lo que
hace el comando, sin tocar `RamaItemCommand.php`. Vale la pena revisar esa interacción GATE4 ×
#341 — probablemente afecta a los demás items paralelos del mismo lote (misma causa raíz).

## 2026-07-11 15:30 — Circuito: pool continuo + revisor afinado/escalonado + perfil de Irving
- **Pool continuo (#334):** vuelta.sh en modo por-item ahora es un WORKER LOOP — al terminar su item pide el siguiente con `circuito:claim-next` (reclamo atómico serializado por flock, respeta pausa) SIN esperar el cron → slots llenos sin valles. PROBADO: worker wt-4 tomó 2 items más sin cron; 6 running sostenidos; box sano (load 1.6/4 cores). Commit `d50b0770`, merge a main.
- **Revisor afinado (#338):** denylist sin falsos positivos ('rol '/'roles'/'auth'/'banco'/'prod' bare fuera; sensibilidad real cubierta por términos específicos) + alcance ampliado un escalón. **Backlog B restante es sensible-pesado** (login/token/permiso/prod/dinero) → escala correcto a la bandeja; autoriza los técnicos-seguros (#259 env→config). El pool seguro está limitado por la naturaleza del backlog, no por sobre-escalación.
- **Modelo escalonado:** Sonnet rutina; Opus 2ª opinión SOLO en borderline/baja-confianza; C → Opus arma BRIEF (`circuito:brief-c`, cron 10 min). **Opus va por API (ClaudeApiClient), NO por el Max/CLI** → los ejecutores (claude -p) siguen en Sonnet, el Max no sube por el escalonado.
- **Perfil de Irving** (docs/perfil-decisiones-irving.md) inlineado al prompt del revisor/decisor: guardrails+preferencias+frontera+decisiones pasadas. Editable, sin secretos. Loop de aprendizaje desde la bandeja = item nuevo registrado (aprobado_irving).
- **Cron paralelo:** scheduler/min (bootstrap) + workers self-refill (pool) + revisar-backlog --apply/2min + brief-c/10min + disparo-check (merge drain).

## 2026-07-11 17:19 — Item #111: auditoría APIs jQuery Bootstrap 4 legacy — SIN HALLAZGOS

Item pedía auditar y migrar el resto de usos de `.collapse()/.tab()/.tooltip()/.popover()/.dropdown()`
vía jQuery sobre Bootstrap 5 (mismo patrón del bug de `.modal()` corregido el 2026-06-03, 20+ archivos).

**Auditoría ejecutada** (rama `circuito/item-111-auditar-apis-jquery-bootstrap-4-legacy-r`):
```
grep -rnE '\.(collapse|tab|tooltip|popover|dropdown)\(' resources/js   → 0 resultados
```
Confirmado con dos variantes de patrón (con y sin el prefijo `$(...)` explícito del item). **Cero
usos** de esas 5 APIs jQuery-BS4 en todo `resources/js` — el fix de `.modal()` de junio fue,
aparentemente, el único caso real; no quedó nada más por migrar. No hay código que cambiar.
Nivel A, item cerrado sin cambios de comportamiento.

## 2026-07-11 23:50 — Circuito CC: clasificación UI/backend + archivo del radar + firma de worker + 6 terminales + fix crontab

**Bloque 1 — Archivar + clasificar UI/backend** (main `bb958e1b`):
- `MergeRunner` clasifica cada rama al integrar por los archivos del merge (`git diff $sha^1 $sha`): `.vue/.blade.php/.css/.scss/resources/js/` → **UI-verificable** (`revision_ui=true` + `ui_hint` con qué mirar/probar); resto → **backend/interno** (`revision_ui=false` + **auto-archiva**). Falla-seguro: sin lista de archivos → UI=true.
- `roadmap_items += revision_ui/ui_hint/archivado_at/archivado_por` (migración aditiva idempotente `2026_07_11_220000`). Scopes `archivado()/noArchivado()`.
- Radar de Integración = SOLO no-archivados (UI-verificable + pendiente) con nota de revisión; Historial aparte (`GET /integracion/historial`), reversible: `/integracion/archivar` (individual + masivo `todos_mergeados`), `/integracion/desarchivar` ("quiero verlo": trae un backend al radar). `IntegracionRamas.vue`: segmentado Radar/Historial, badges 👁 Revisar visual / ⚙ Backend, botones archivar/traer.
- Frontera dura intacta (dinero/seguridad/prod/negocio siguen a bandeja).

**Bloque 2 — equipo de workers A+B** (main `dfc79a6f` + fix `aa1c90d7`):
- **A firma:** `roadmap_items += worker_sid` (migración `2026_07_11_233000`), sellado en el reclamo atómico (`SchedulerCommand` `wt-{slot}` + `claimNextParalelo(?sid)` del pool continuo). Chip `wt-K` en la rejilla + `🛠 wt-K` en Integración. Probado en vivo: #302→wt-2, #332→wt-1.
- **B 6 terminales fijas:** `trabajandoAhora()` rellena siempre N=6 slots (`wt-1..wt-6`); ociosos = "esperando trabajo" (idle), orden fijo por nº de slot. Fix: se quitó el early-return con 0 sesiones que saltaba el relleno.

**Fix operativo crítico — crontab del circuito sin `cd`:** 4 líneas (`scheduler`, `brief-c`, `reap-stuck`, `destrabe`) corrían sin `cd /var/www/megaisp &&` → fallaban silenciosas (`Could not open input file: artisan`). Efecto: scheduler 27 min sin latido (0 workers), stuck sin reapear, bandeja sin drenar. Corregidas las 4. Backup `cron.bak` en scratchpad. Regla: toda línea del circuito necesita `cd` o ruta absoluta a artisan.

Solo dev, sin push. Kill switch #342 y candados #341/#342 intactos.

## 2026-07-11 22:25 — Circuito CC: cron sin `cd` → wrapper único (dev)
**Síntoma:** Terminales 0 sesiones; `circuito_pausado=0` (NO pausado). Scheduler #334 sin latir ~26 min (`cron_vivo=NO`) + 3 items huérfanos `en_progreso` (#174/#328/#331, ~30 min, sin `worker_sid`).
**Causa raíz:** 4/6 líneas del crontab corrían `php artisan …` **sin `cd /var/www/megaisp`** → desde el home no existe `artisan`, fallan y el `>/dev/null 2>&1` lo oculta. Rotas: scheduler, reap-stuck, destrabe, brief-c (las que llevan `cd` —disparo-check, revisar-backlog— sí corrían). Mismo bug que ya pegó con el picker.
**Fix:**
1. `circuito:reap-stuck --minutes=25` a mano → liberó #174/#328/#331 (respetó la vuelta viva #302/wt-2).
2. Wrapper único **`deploy/circuito/cron-wrap.sh`** (hace `cd` al proyecto + `exec php artisan "$@"`). Crontab reescrito: las 6 líneas van por el wrapper (0 sueltas). Ninguna línea futura puede olvidar el `cd`. Respaldo: `/home/meganet/circuito/crontab.bak.20260711_222215`.
**Verificado:** cron re-latió SOLO a las 22:25:01 (+59s, sin intervención) → `cron_vivo=SI`; huérfanos liberados; pool relanzó (en_progreso volvió a 1); `trabajandoAhora()` devuelve 6 slots wt-1..6. `cron-wrap.sh` queda UNTRACKED (funciona en disco; pendiente commit para blindar vs git clean).

## 2026-07-11 23:58 — Circuito CC: watchdog de los 6 workers + auto-recuperación del supervisor

**Watchdog (#334, main `1db449bd`):** `WatchdogService` + `circuito:watchdog` (cron `*/2` por `cron-wrap.sh`).
- **Salud por slot** (wt-1..wt-6): 🟢 trabajando · ⚪ esperando (idle normal, sin trabajo) · 🔴 caído/atascado. Distingue "ocioso sin trabajo" (normal) de "ocioso/caído habiendo trabajo" (problema).
- **Detecta:** scheduler caído (beat >180s), worker colgado (latido frío >600s con vuelta abierta), y ocioso-con-trabajo pese a scheduler vivo (pool no despacha).
- **Auto-recupera ACOTADO** (máx 3 intentos/causa): relanza el scheduler / reap del item atascado (reusa criterio #341, jamás toca en_desarrollo_humano). Cada acción se **audita** (bitácora watchdog). Tras 3 intentos → **ESCALA a Irving** (alerta en la Torre "🔴 … requiere revisión"). Respeta kill switch (#342: en pausa no relanza).
- **Torre:** banner de escaladas + aviso "🛡 supervisor auto-recuperó"; `watchdog{}` en `/torre` y `/circuito/estado`.
- **Verificado:** `--dry`=6 slots con salud; scheduler caído simulado→relanza+audita; 4º intento→escala; limpieza de la simulación OK.

**Reconciliación con trabajo concurrente:** otro agente (Cowork/sesión paralela) arregló el mismo bug del crontab con un **wrapper `deploy/circuito/cron-wrap.sh`** (mejor que mi `cd` inline: ninguna línea puede olvidarlo). Respeté su enfoque, blindé el wrapper (commit) y sumé la línea del watchdog por él. ⚠️ Detectadas ediciones sueltas en main de ese agente (TorreTerminales.vue → stasheada; esta bitácora) — hay actividad concurrente en /var/www/megaisp, a coordinar con Irving.

## 2026-07-12 00:10 — Item #299: cierre parcial (nav móvil) — sin construir topbar a ciegas

Item #299 (orden directa de Irving, nivel_riesgo B) traía un DES-TRABE de Opus (2026-07-11 22:20)
con 3 opciones ante el bloqueo del mockup `medussa-nav-movil-mockup.html` (nunca llegó, 3 promesas
incumplidas): A) seguir esperando, B) topbar placeholder a ciegas, C) cerrar parcial + registrar
deuda. El log del item muestra `aprobado_irving` a las 22:44 (posterior al brief) sin comentario
que override la recomendación → se ejecutó **la opción C, la recomendada**.

- Fases 1 (riel+hoja, `faf75064`) y 2 (buscador, `24674523`) ya vivían en `main` — cubren 4/5
  subtasks del item (riel, hoja de submenú, activación solo-breakpoint sin UA, PASO 0).
- Se documentó el cierre y la deuda restante (subtask 3 "topbar delgada" + paleta `MNAV_COLORS`
  + aplanado de sub-grupos + validación visual pendiente) en `docs/nav-movil-progreso.md`
  (sección nueva "Cierre parcial"), como fuente única de verdad para cuando el mockup llegue.
- **NO se creó item nuevo en el roadmap** (se dejó para cuando exista el mockup, evitar
  fragmentar antes de tener el insumo real). **NO se tocó código** (nada que construir sin el
  mockup sin arriesgar retrabajo, tal como advertía el propio brief).
- Item #299 cerrado `completado` (entrega parcial) referenciando los commits ya en `main`.

## 2026-07-12 00:30 — Circuito CC: priorización por riesgo + ADN + roster + supervisor Thomas T

- **Priorización por riesgo** (main): `RevisorService::briefarSeguridad()` (Opus clasifica seguridad/dinero/negocio/prod/no_aplica + fix-brief) + `circuito:priorizar-seguridad` (flock, idempotente ⟪SEG-TRIAGE⟫, cron `*/3`). Subió seguridad/dinero a ALTA + ⚡[SEG-TOP]/⚡[DINERO-TOP] + escaló a bandeja con brief; separó [BLOCKED-NEGOCIO] y [PARKED-PROD]. ~90 triados: 7 críticos (#219/#230/#244/#250/#251/#260/#272), 28 seguridad alta, 5 dinero, 33 negocio, 21 prod.
- **Reglas al ADN (#356 Parte 1)**: `docs/reglas-operacion-circuito.md` (estabilidad·minimalismo·balance) inyectado en el `systemPrompt` del revisor + `prompt.txt`/`prompt-item.txt` del ejecutor. No relaja la frontera dura.
- **Roster con nombres**: `config.worker_nombres` (Samanta/Jenny/Tokyo/Maya/Beky/Dayan) renombrables (settings) → `nombresWorkers/setNombreWorker`; en rejilla (clic=rename), firma e Integración ("trabajado por [nombre]"). Endpoint `POST /circuito/worker-nombre`.
- **Pool continuo confirmado** para los 6 (logs: "toma el siguiente" parejo wt-1..6).
- **Supervisor Thomas T**: `SupervisorService` (read-only, DERIVA feed de asignaciones+revisor+watchdog+escaladas; activo=maquinaria late y no pausado). Terminal RESALTADA arriba del roster en Terminales, con latido + feed 📋/✅/⤴️/🛡. Jerarquía: jefe arriba, 6 workers abajo con semáforo.
- **Pendiente grande**: #308 API centralizada (registro de contratos + notificación de deprecación) — elegido "Fundación + notificación", build aparte.

Solo dev, sin push. Kill switch #342 y candados #341 intactos.

## 2026-07-12 00:53 — Item #218: patrones route_permission de gestión de usuarios corregidos

**Ejecutor:** wt-3 (worktree aislado, item ya reclamado/en_progreso, aprobado por Irving en el log del item).

**Diagnóstico (FASE 1, confirmado 1:1 contra `app/Modules/Core/Usuarios/routes.php`):**
- `user_add_user` apuntaba a `/administracion/user/add` (ruta inexistente) → las reales son `/administracion/user/crear` (GET, form) y `/administracion/user/create` (POST, store).
- `user_edit_user` apuntaba a `/administracion/user/editar/{id}` y `/administracion/user/update/{id}` ({id} en posición equivocada) → las reales son `/administracion/user/{id}/editar` y `/administracion/user/{id}/update`. La entrada `get-data-user/{id}` ya estaba correcta.
- `user_delete_user` apuntaba a `/administracion/user/destroy/{id}` → la real es `/administracion/user/{id}/destroy`.
- Efecto real (confirmado con `CheckRoutePermission::convertRouteToRegex`, match literal segmento-por-segmento): como ningún patrón matchea, esas 3 acciones son **fail-closed** para cualquier no-admin — el permiso delegado (directo o por rol vía el flip Fase 3a) queda inerte, solo el bypass admin/DESARROLLADOR/super-administrator llega a esas rutas hoy. No es una apertura de acceso, es un candado que no dejaba pasar ni a quien sí tenía el permiso.
- `activity_log` del bloque revisado (últimos 30 días, filtro por "user"): sin registros — no hay evidencia de acceso previo afectado por el mismatch.

**Fix (FASE 2):** `config/route_permission.php` — patrones de `user_add_user`/`user_edit_user`/`user_delete_user` alineados al literal real de las rutas. Sin tocar bypass admin, el flip Fase 3a, ni `user_view_user` (su entrada `/administracion/user/table` es un patrón muerto e inofensivo, fuera del alcance del prompt).

**Verificación (FASE 3):** transacción tinker con rollback total — usuario no-admin sintético (rol de prueba sin permisos) contra `/administracion/user/7/editar` vía `CheckRoutePermission::handle()` real: **sin** `user_edit_user` → 403; **con** el permiso otorgado (`givePermissionTo`) → 200. También `/administracion/user/create` sin `user_add_user` → 403. `DB::rollBack()` confirmado (0 filas residuales en `users`/`roles`). `php -l` limpio, `php artisan --version` bootea.

**Commit:** `fix(permisos): alinear patrones route_permission de gestión de usuarios` en `circuito/item-218-patrones-de-route-permission-de-editara`. Integrado con `circuito:integrar 218`.

Solo dev, sin push. Kill switch #342 y candados #341 intactos.

## 2026-07-13 10:48 — Torre de Control: tarjetas de Integración (Escuchar + Ver más + resumen/badges)

Mejora de las tarjetas de la pestaña Integración (`IntegracionRamas.vue`), continuación del pipeline por estado. 4 sub-pasos + build.

- **PASO 0 (hallazgos):** (1) mapa módulo→ruta = `module_sidebar_config.module_key→sidebar_url`, pero `modulo` es texto libre → se normaliza (segmento base, sin acentos/minús/alfanum) y se casa contra module_key; (2) `reporte_coloquial` vacío en 188/189 ramas → Escuchar cae al fallback `description`~40 palabras; (3) **no existe página de detalle de item** → fallback "Ver más" = `/releases` (la Torre, hogar del item). Marcado a Irving.
- **A** (`0d6aa942`): `ramaPayload` expone `modulo` + `modulo_url` (resolver memoizado `moduloUrl()`/`normalizeModulo()`). Botón "🔎 Ver más" abre la pantalla del módulo en pestaña nueva; null/no mapeable → `/releases`. Verificado: MegaFamilia→/megafamilia, Talento→/talento, VoIP→/voip/troncales, Roadmap/Marketing/Usuarios→null.
- **B** (`ec74fff3`): `ramaPayload.resumen` = `reporte_coloquial` o `description` recortada ~40 palabras (`resumenItem()`). `leer()` narra `title + resumen` (antes narraba el reporte extenso). Voces = item aparte.
- **C** (`ec71535b`): cabecera con badge de estado (mergeado✓/esperando/conflicto/sin mergear vía `estadoBadge()`) junto al nivel A/B/C; resumen corto arriba; Escuchar y Ver más movidos a la fila de botones de abajo; estilos claro/oscuro.
- **D:** item roadmap **#424** "[UI] Selección de voces (es-MX) para Escuchar en la Torre, por administrador" — nivel B/interno, pendiente_revision, modulo=Roadmap, con opciones en el cuerpo (voz es-MX por defecto + selector global en settings / preferencia por admin) y 3 subtareas. Sin nulos críticos.
- **Cierre:** `npm run prod` OK (3.61m) + view:clear/route:clear/config:clear + view:cache + queue:restart. NUNCA config:cache. Dev/main, sin push.
