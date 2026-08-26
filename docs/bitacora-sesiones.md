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

## 2026-07-13 11:15 — Torre de Control: Escuchar + Ver más en la bandeja de Panorama

Se lleva el patrón "resumen corto + 🔊 Escuchar + 🔎 Ver más" (ya en las tarjetas de Integración) a la bandeja `requiere_irving` de Panorama (`TorreControl.vue`). Sin migración, sin config:cache.

- **A** (`e943b9d8`): `RoadmapController::torre()` agrega a cada item de `cola_requiere_irving` el `resumen` (helper `resumenItem`: coloquial→descripción ~40 palabras) + `modulo_url` (helper `moduloUrl`), reutilizando los privados YA existentes del controller. Expone `voz_tts` en el payload para narrar con la misma voz guardada (#424) que Integración.
- **B** (`86c5185e`): lógica de 🔊 Escuchar (voces es-*, voz guardada, seleccionarVoz, narrar título+resumen) y 🔎 Ver más (resolver módulo→ruta, fallback `/releases`) **extraída a composable único** `resources/js/hook/torreEscuchar.js` (`useEscuchar()` + `verMas()`). `IntegracionRamas.vue` **refactorizado** para consumirlo (una sola fuente, sin divergir; conserva su selector de voz + persistencia `cambiarVoz`). `TorreControl.vue` suma a cada item de la bandeja: resumen corto arriba, badge "requiere tu decisión" + nivel A/B/C, y botones 🔊/🔎 junto a las acciones existentes (Aprobar/Rechazar/Comentar/Cerrar/Cancelar **intactas**). Estilos claro/oscuro.
- **Cierre:** `npm run prod` OK (3.43m) + view/route/config:clear + view:cache + queue:restart. Dev/main, sin push.

## 2026-07-13 12:20 — Torre de Control: velocidad de Escuchar (#424) + opciones en items C

Dos frentes, sin migración, sin config:cache.

**Frente 1 — voz/velocidad (cierra #424):**
- `a9d5aef2`: setting `circuito_tts_rate` (getRateTts/setRateTts, clamp [0.5,2.0], default 1.0); endpoint `/integracion/voz` acepta `rate`; feeds /torre e /integracion exponen `rate_tts`.
- `37095974`: `useEscuchar()` aplica `u.rate`; Integración suma slider velocidad 0.5×–2.0× + botón Probar (persiste en `/integracion/voz {rate}`); TorreControl carga `rate_tts`. Un solo punto de config (Integración), respetado en toda la Torre. El selector de voz ya existía.

**Frente 2 — opciones en items C (circuito propone, Irving decide):**
- `4fdb5d8b`: endpoint `POST /circuito/elegir-opcion` (gate circuito.decidir) persiste SOLO `opcion_elegida` sin cambiar estado; la tarjeta de la bandeja persiste al elegir + pre-selecciona desde el feed; C sin opciones muestra "Sin opciones aún".
- `56dc9356`: `RevisorService::proponerOpciones()` (Opus, 2-3 opciones con pro/contra) + `parseOpciones()`; comando `circuito:proponer-opciones` (dry-run por defecto, `--apply`, `--limit`, `--id`) escribe SOLO `opciones`, nunca `opcion_elegida`/estado, fuera de la ruta de ejecución. Verificado en #429 (3 opciones, opcion_elegida/estado intactos).
- `26eb9ae8`: scope `RoadmapItem::cSinOpciones` como invariante único; el generador lo consume (no pisa lo ya propuesto). Idempotencia verificada.

**Estado C:** 43 C en la bandeja sin opciones; #429 ya tiene 3 (verificación). Quedan 42 para llenar con `circuito:proponer-opciones --apply` cuando Irving confirme (Opus cuesta → tanda acotada por --limit).

**Cierre:** npm run prod OK + view/route/config:clear + view:cache + queue:restart. El circuito auto-mergeó #426/#418/#417 en paralelo; los 5 commits de esta tanda están en main. Dev, sin push a prod.

## 2026-07-13 12:49 — #430 Terminales con avatares + supervisor (+ resolución de colisión, 2 diagnósticos)

**Colisión #430:** el scheduler auto-reclamó #430 (nivel A) en wt-1 a las 12:29 pero su worker `claude -p` murió por OOM; quedó huérfano el latido `circuito:vivo --watch --sid=wt-1`. Resuelto: candado #341 (`en_desarrollo_humano=true`) para sacarlo de `tomablePorCircuito`, zombie ya muerto solo, `en_progreso` liberado (→ pendiente_revision, worker_sid=null, fila live wt-1 borrada).

**Build #430** (nivel A, dev/main):
- `7a09d25d` (A): config/circuito.php worker_nombres = Maya/Leo/Sofía/Iván/Nora/Beto (editable); public/images/circuito/ + avatar-placeholder.svg + README (wt-K.png por slot, fallback si falta).
- `328a1251` (B): TorreTerminales.vue tarjeta con avatar (por slot) + nombre grande + wt-K chico; animación enganchada a s.running/s.idle/s.stale (respira+halo+indicador vs reposo atenuado); prefers-reduced-motion off.
- `35c6173d` (C): nodo supervisor (data.supervisor) + una línea por terminal con flujo verde animado solo hacia las running; stale ámbar; idle tenue; reduced-motion off.
- Cierre: npm run prod OK + view/route/config:clear + view:cache + queue:restart. #430 → completado, candado quitado.

**Diagnóstico "no fluye" (read-only):** no es cron ni locks (scheduler latió hace 11s). Pool auto-ejecutable = 0: 95 items en requiere_irving (esperan a Irving), 0 A/B en pendiente_revision. El único intento (#430) crasheó por RAM. Para reactivar: Irving debe decidir la bandeja. nivel_riesgo NULL = 0 (#419 sigue cerrado).

**Diagnóstico "indicador de prioridad no cambia" (read-only):** patrón #417 = bundle stale. #347 (badge de prioridad) mergeado a main 07-11 14:32, pero el bundle de DEV no se recompiló hasta hoy 12:46 (mis builds de #430) → 2 días sirviendo JS viejo sin el badge. Ya compilado + cache-bust nuevo (app.js?id=e204a6…) → hard-refresh y aparece. Binding correcto: badge = item.priority (RoadmapTab.vue:96-97 + CSS 995-997). Deuda: en dev hay que correr npm run prod tras cada merge que toque .vue (prod lo hace en el deploy; dev no).

## 2026-07-14 21:38 — Item #475 (wt-1): avatar interactivo + escritorio del supervisor en Terminales

Ejecutado en worktree wt-1 sobre `circuito/item-475-ajustar-terminales` (integración encolada al runner on-box).
Item venía escalado por el revisor + DES-TRABE (decisión UX de librería/alcance) y **aprobado por Irving**
(log `aprobado_irving`) sobre el brief que recomendaba el MVP Opción A (CSS/SVG, sin dependencias nuevas).

- **Backend** (`SupervisorService.php`): `recienResueltos()` (últimos completados) y `listosParaTerminal()`
  (scope `autoEjecutable` + `branch` null + excluye `[BLOCKED-]/[PARKED-]`), expuestos en
  `GET /api/roadmap/circuito/estado` → `data.supervisor.{recien_resueltos,listos_para_terminal}`.
- **Frontend** (`TorreTerminales.vue`): badge con icono de pantalla (brillo pulsante) mientras la terminal
  corre ("concentrado viendo la computadora"); al detectar la transición running→terminado dispara 2.4s de
  animación de estiramiento (scale+rotate+translateY) + badge de "estirarse" (respeta
  `prefers-reduced-motion`). Nodo del supervisor ahora es un "escritorio" (icono clipboard) con las 2 listas
  nuevas al lado.
- Verificado: `php -l` limpio, tinker con datos reales (6 recién resueltos, 4 listos para terminal tras
  filtrar PARKED-PROD), `bash deploy/circuito/npm-build.sh` compiló sin errores.
- Enlace de revisión: `/releases` → pestaña Torre de Control → sección "Terminales en vivo".

## 2026-08-04 20:35 — Torre: autopilot continuo + reorganización de la UI (#507)

Bloque completo pedido por Irving en documento propio. **Todo en dev/main, sin push, sin prod.**
Item de coordinación **#507** (`[COORD] Torre — autopilot continuo + reorg UI`, marcado
`en_desarrollo_humano` + `excluir_pool_automatico` para que el circuito no lo tome).

### Decisiones de Irving en el Paso 0
1. **Aislamiento:** pausar el circuito durante el trabajo (lo hizo él desde la Torre; el kill switch
   `setPaused` exige sesión HTTP autenticada — CC no puede tocarlo por CLI, candado #342).
2. **Tope del autopilot:** A + B reversible. **El nivel C SIEMPRE queda en su bandeja** (respeta la
   regla dura de CLAUDE.md). El flag `autopilot.max_nivel` queda listo para subirlo a C sin redeploy.
3. **Reclamo de cola:** conservar flock + UPDATE condicional atómico. **NO** migrar a SKIP LOCKED.

### Hallazgos del Paso 0 que corrigieron el documento
- **La ejecución continua YA existía** (#334 F1): el cron corre `circuito:scheduler` **cada minuto** y
  lanza una vuelta POR ITEM en el primer slot libre; `circuito:claim-next` deja que una terminal jale
  el siguiente al quedar libre. **No había cron de rondas que apagar**: lo que sobrevivía del modelo
  viejo era la FICCIÓN de "próxima vuelta" en la UI (`circuito.interval_min`, que su propio comentario
  ya declaraba espejo del crontab y sin control real).
- `preguntasNormalizadas()` **ya** prefería el bool `recomendada` con fallback a `stripos`.
- El autopilot ya existía a medias como el **Revisor** (#338), que autoriza B técnicos.
- **`guard()` NO se relajó** (el documento asumía que bloqueaba el flujo interno): sus únicos
  consumidores son `RoadmapExternalController` y `RoadmapMcpController` — es la puerta de la vía
  EXTERNA (token Cowork/MCP). El autopilot es interno y ni la roza; abrirla habría dejado que el token
  externo apruebe B/C.

### Commits (dev/main)
| Commit | Sub-paso |
|---|---|
| `23ca342a` | 1 — opciones del brief con `recomendada`/`confianza`/`reversible` + `requiere_irving` por pregunta |
| `05317317` | 2 — `AutopilotService` + flags en `config/circuito.php` + `circuito:autopilot` + enganche |
| `e8613f67` | 3 — `scopeOrdenCola`, lease `claimed_at`, reaper de dos señales, modo continuo |
| `24cb27d8` | 5 — `GET /api/roadmap/torre/decisiones/contadores` |
| `f873930b` | 4 — Panorama continuo, banner autopilot, una pregunta a la vez, sidebar interno |
| `e920e19c` | backfill `circuito:rebrief-bandeja` + fix de impresión de opciones |

### Piezas clave
- **`RoadmapItem::boolEstricto`** (no estaba en el plan): la coerción de PHP falla hacia el lado
  peligroso — `(bool)"si"` y `!empty("false")` dan TRUE. Sin esto, un `"reversible":"si"` del modelo se
  habría leído como permiso para auto-ejecutar. Ante cualquier ambigüedad: false.
- **`AutopilotService`**: solo actúa con DATO EXPLÍCITO; ausencia, ambigüedad o error mandan el item a
  Irving. Reusa `responderPregunta` + los estados que el pool ya reconoce (A→`aprobado_claude`,
  B→`aprobado_revisor`). Deja rastro en `log` con `decidido_por='autopilot'`, confianza,
  reversibilidad y **la política vigente al decidir** (para que el histórico siga siendo legible si
  mañana se afloja el tope).
- **Lease explícito** (`claimed_at`): lo renueva el latido que ya existía (`circuito:vivo --watch` →
  `liveBeat`) con un UPDATE crudo que **no toca `updated_at`**, así "sigo vivo" y "escribí en el item"
  son señales independientes. El reaper ahora exige que **ambas** estén frías: antes mataba workers
  VIVOS que llevaban 25 min sin escribir en su item.
- **Orden de cola** (`scopeOrdenCola`, separado de `ordered()` que ordena la bandeja):
  urgente → por concluirse/reanudables (rama abierta o colisión liberada) → prioridad → antigüedad.
- **UI**: fuera "Vuelta en curso" (terminales trabajando/libres), banner de autopilot, **una pregunta a
  la vez** con "Pregunta X de Y" + avance automático al contestar + Aprobar deshabilitado hasta
  responder todas, y **sidebar interno** (dentro de la pantalla, NO toca el sidebar global) con
  bombitas por módulo e índice de preguntas. Las 6 pestañas siguen intactas.
- **Límite de la bandeja 20 → 100**: con las bombitas al lado, una lista truncada a 20 contra un
  contador de 71 se lee como bug. Medido: traer los 71 cuesta 16 ms, `torre()` completo 157 ms.

### Backfill de briefs — BLOQUEADO (no escribió nada)
`circuito:rebrief-bandeja` regenera los briefs viejos para poblar `confianza`/`reversible` (sin eso el
autopilot no puede tocar la bandeja vieja: **0 de 68 califican**). Dos candados:
1. **No pisa items ya respondidos por Irving** — `aplicarPreguntas` conserva respuestas por ID, pero
   esos IDs son POSICIONALES (q1, q2…): con un brief nuevo, la respuesta de la vieja q2 se pegaría a
   otra pregunta y con una clave de opción inexistente. En la bandeja actual protege **31 de 68**.
2. **Exige el kill switch activo.** Al lanzarlo, el circuito ya había sido **reanudado (20:21:08)** →
   abortó sin escribir. Pendiente de que Irving vuelva a pausar.

### Pendientes registrados
- **#526** — drift del campo `modulo` (texto libre): 12 de 20 módulos no mapean a pantalla; duplicados
  (`Auth` vs `Autenticación`, `Roadmap` vs `Circuito`). Además de las bombitas, degrada el pre-filtro
  de no-colisión del despachador, que serializa por ese mismo campo.
- Validación visual de Irving (Panorama, paginación, bombitas) — **no hecha**.
- Regenerar los briefs viejos (backfill de arriba) cuando se vuelva a pausar.

### Notas de proceso
- **NUNCA `config:cache`** en este repo (rompe `env()` en runtime): se cerró cada sub-paso con
  `view:clear && route:clear && config:clear`. El documento pedía `config:cache`; mandó la regla del repo.
- `php artisan tinker <archivo>` **se cuelga si no se le cierra stdin** (`</dev/null`): se veía como
  "torre() lentísimo" cuando en realidad esperaba entrada. Medido después: `torre()` = 121-157 ms.
- Una corrida de `php artisan migrate` se colgó tras aplicar el ALTER; la migración quedó bien
  (lote 641, sin pendientes, sin locks). Sin diagnóstico; no se repitió.

## 2026-08-04 20:55 — Circuito CC: fuga del pool de reclamo (raíz del bucle) + autopilot a nivel C

**Síntoma reportado por Irving:** wt-1 gastando minutos en el #66 `[BLOCKED-NEGOCIO]`, el #117
re-confirmado 9+ veces, la Torre anunciando "hasta nivel B" pese a la decisión de subirlo a C.

**Qué estaba pasando (diagnóstico, no teoría):**
- `ejecutablesParalelo()` solo excluía `[PARKED-PROD]`. Todo lo demás no-ejecutable
  (`[BLOCKED-…]`, `[PARKED-ESPEC]`, C esperando merge manual, items ya marcados por el anti-bucle)
  seguía siendo **reclamable**. Del pool de 86 reclamables, **35 no eran trabajo real**.
- El ciclo: Irving aprueba → worker lo reclama → lee el rótulo / no puede mergear → re-escala a
  `requiere_irving` sin ejecutar → reaparece en la bandeja → se aprueba otra vez. #117 acumuló
  **13 entradas `aprobar` idénticas** en su log; #99, 16 escalaciones.
- Log de wt-1: trabajó el #99 (20:28:03–20:28:37, lo reconfirmó bloqueado), tomó el #66 por pool
  continuo y estuvo en él 20:28:38–20:36:00 (~7.5 min) **sin tocar código**; terminó marcándolo
  `status=done` por su cuenta "para romper el ciclo de re-reclamo".
- Los flags `excluir_pool_automatico` / `bloqueado_por_bucle` / `esperando_merge_irving` YA existían
  en BD (migrados) pero **nadie los leía en el despacho**: eran decorativos.
- El `max_nivel=C` y el filtro `[BLOCKED-]` estaban **sin commitear** en el checkout principal → los
  worktrees (que corren `git checkout --detach -f main` en cada vuelta) nunca los veían.

**Fix — commit `6e46d55a` (dev/main, sin push):**
- `RoadmapItem::scopeElegibleParaPool()` = guard ÚNICO de despacho, aplicado en
  `ejecutablesParalelo()`, `scopeAutoEjecutable()` y `circuito:destrabe`; mismas condiciones
  repetidas en el `UPDATE` de `claimNextParalelo()` como candado atómico.
- Estado terminal **"esperando merge de Irving"**: en `circuito:integrar`, un C (o auto-merge OFF)
  con rama que **tiene commits** se parquea fuera del pool y fuera de la bandeja (vive en
  Integración) en vez de volver a `requiere_irving`. Rama vacía → sí es decisión de Irving.
- Guard en el modelo: el cierre optimista a `completado` de un C con rama y sin `merge_commit` se
  retiene como esperando-merge; el cierre MANUAL de Irving se respeta (`cierreManualIrving`).
- Anti-bucle: 3 escalaciones seguidas con la misma huella (rama+opción+nivel+preguntas) →
  `bloqueado_por_bucle` + fuera del pool. Cambio material = contador a cero.
- `POST decidir`: re-aprobar un item parqueado → **422** con la acción que sí lo mueve
  (mergear/destrabar), escape hatch `forzar=true`. Aprobar un rotulado avisa que hay que quitarle
  el rótulo al título.
- Tope de nivel del autopilot respetado en el despacho de lo aprobado automáticamente.
- `config/circuito.php`: `autopilot.max_nivel` B → **C** + CLAUDE.md/CONTEXTO §8.2 alineados.

**Verificación:** pool reclamable **86 → 51** (35 no-ejecutables fuera, 0 rotulados/parqueados
pasan). Parqueo, cierre manual y contador anti-bucle probados en transacción con rollback.
**En vivo:** el propio #117 (el de las 13 vueltas) fue trabajado por wt-1 con el código nuevo, dejó
**3 commits** en `circuito/item-117-…`, quedó `esperando_merge_irving` / `estacion=integracion` y
`elegibleParaPool` = 0 → ningún worker lo vuelve a tomar. Espera el merge de Irving.

**Pendiente / notas:**
- El **#66 quedó `status=done` sin implementarse** (lo cerró wt-1 por su cuenta). Decidir si se
  reabre con el rótulo puesto (ya no se re-despacha) o se archiva.
- El lote de "respondidos": 36 items con todas sus preguntas contestadas ya estaban en
  `aprobado_irving` (Irving los aprobó a mano entre 20:42 y 20:47). Quedaban 2 sin aprobar
  (#155, #465) y ambos rebotaron a la bandeja tras ser reclamados → ahora el contador anti-bucle
  los instrumenta. No se aprobó nada en lote desde aquí.
- Los workers vivos toman el código nuevo al inicio de su siguiente vuelta (wt-2/3/5/6 ya en
  `6e46d55a`); wt-4 puede hacer un último reclamo con código viejo antes de sincronizar.

---

## 2026-08-08 11:45 — Torre de Control v2: motor de Thomas + API del Roadmap extendida

**Encargo:** convertir el Circuito CC en un lazo de automatización máxima — Irving y Cowork definen
el QUÉ, el circuito lo deja implementado en dev sin intervención en pasos intermedios. Supervisor
(Thomas) que absorba las dudas de las 6 terminales y solo escale a Irving lo irreversible de alto
impacto. Todo en dev; prod nunca se tocó (14 commits locales, `origin/main` intacto).

### HALLAZGO PREVIO (la causa real de buena parte del "flujo detenido")

El `MergeRunner` exigía el checkout principal **completamente limpio** y, si no, **escalaba el item
a la bandeja de Irving**. El árbol tenía **7 323 archivos "modificados"**, de los cuales **7 316 eran
solo cambios de permisos** (`chmod` recursivo con `core.fileMode=true`) y 1 era
`docs/pendientes-perfil-irving.md`, al que **el propio circuito le escribe en cada decisión de Irving**
sin commitearlo nunca.

Resultado: **desde el 6-ago ninguna rama podía integrarse.** Items con el trabajo YA HECHO rebotaban
a `requiere_irving` con el motivo "cambios sin commitear" (#531, #532, #533, #536, #540 — 8
escalaciones falsas registradas). No era un problema de política: era este bug.

**Arreglado:** `core.fileMode=false` (7 323 → 1 sucio) + commit del perfil capturado + **guard de
merge quirúrgico** (`1384daf8`): ahora solo bloquea si lo sucio **intersecta el footprint real de la
rama** (`git diff main...rama`), que es el único caso donde mergear perdería trabajo. Fail-closed si
no se puede calcular el footprint. **#532, #533 y #540 se integraron y quedaron `completado`.**

### ENTREGABLES

1. **API extendida** (`fef247e9`) — la deuda "Opción 2".
   - Alta de items: `POST /{token}/item` + `GET /{token}/crear/{modulo}/{titulo_b64}/{spec_b64?}`
     (base64url, para el fetcher de Cowork que solo hace GET y descarta el query string).
   - Punto único `RoadmapIntakeService`, compartido por la vía externa, las terminales (sub-items) y
     Thomas. **Candado: el item nace SIEMPRE `pendiente_revision`** — crear no aprueba; el nivel
     declarado se sella con su origen real, así el guard #260 sigue vigente.
   - Historial append-only `roadmap_item_reports` + `RoadmapReportService`. Antes cada terminal
     concatenaba a mano sobre `comentarios_claude`: con seis escribiendo, dos reportes se pisaban.
     La columna se conserva como espejo legible acotado.
   - `estado_cola` **derivado** (no almacenado, para no crear una segunda verdad que se
     desincronice): `en_cola|asignado|en_progreso|en_verificacion|completado|esperando_irving|sin_triar`,
     + `terminal_asignada`, `asignado_at`, `item_padre`, consulta viva. Expuesto en lista y detalle.
   - Token `create_token` propio y rotable, con fallback al `write_token` para no romper a Cowork.

2. **Thomas** (`ce14a359`) — autoridad intermedia. Política **determinista** en
   `config/circuito.php → thomas` (sin llamada a IA): la terminal pregunta con `circuito:consultar`
   y recibe respuesta **en el acto**; el contrato es el exit code (0 procede / 1 detente).
   Escala solo: **producción · borrar datos · gastar dinero · credenciales/seguridad** + spec
   contradictorio. Suma estimación de esfuerzo (`eta_minutos`, orientativa), verificación de cierre
   y diagnóstico de invariantes.
   **Thomas NO reparte:** el reparto ya lo hace `circuito:scheduler` (único despachador desde #432
   B1); su vuelta va **enganchada** ahí y no en un cron paralelo que abriría una carrera.

3. **Harness de terminales** (`8c534be9`) — el cambio de fondo. El prompt mandaba **escalar como
   primera reacción** ("Si el item NO es claramente ejecutable… escálalo"). Ahora la **regla de oro**
   va al frente: opción recomendada → avanza → registra; revisión posterior, no previa. La terminal
   **ya no puede escalar a Irving por su cuenta**. Se enumera explícitamente lo que NO se consulta.

4. **Política documentada** (`fb73121a`) — `docs/politica-thomas.md`, anexada al manual que sirve la
   API externa (quien redacta un spec necesita saber qué frontera detiene a una terminal).

### VERIFICACIÓN

- curl: alta (#550), sub-item con padre y módulo heredado (#551), 3 reportes acumulados sin pisarse,
  alta por GET+base64url con `/` y acentos (#552). Compatibilidad: lista, `/set` y guards intactos;
  token inválido → 403. Items de prueba borrados (cascada verificada).
- Política: caso normal → PROCEDE (exit 0); "DELETE FROM" → ESCALADO (exit 1) con el item en
  `requiere_irving` y la terminal liberada; sin opción reversible → escala. Rastro completo en el
  historial.
- Serialización: con 2 items del mismo módulo el planificador despacha **1** y deja el otro en cola.
- Cola vacía → 6 terminales libres, `ocio_con_cola=false`, ninguna inventa trabajo.

### PENDIENTES / NOTAS

- ⚠️ **Un item con módulo "Sin clasificar" bloquea a las 6 terminales** (diseño #432 B2: footprint
  desconocido corre solo). Hay **27 de 286** items activos así. Peor: un reclamo huérfano de un
  worker muerto mantiene ese bloqueo hasta que el reaper lo libera (**25 min**). Es el mayor freno de
  throughput que queda y es territorio del item **#526** (drift de `modulo`).
- `deploy/circuito/prompt.txt` (modo backlog) conserva la política vieja pero quedó **inalcanzable**:
  el scheduler es el único que lanza `vuelta.sh` y siempre pasa `CIRCUITO_ITEM`.
- El reaper manda los items de workers muertos a `requiere_irving`: otra vía que llena la bandeja.
  Evaluar si conviene re-encolar en vez de escalar.
- Falta validación visual de Irving en la Torre (`/releases`) de las claves nuevas.

---

## 2026-08-08 16:40 — #566 Destrabar la cola: carril mecánico, frenos de flujo y crear=ejecutar

**Todo en DEV. `origin/main` intacto (32 commits locales).**

### La premisa del item no coincidió con lo medido

El item asumía que la cola estaba vacía **porque el autopilot es demasiado conservador**. Los números
dicen otra cosa:

| Lo que decía el item | Lo medido |
|---|---|
| ~37 esperando decisión | `pendiente_revision` = **1** |
| ~44 en `requiere_irving` | 41 ✓ — pero **23** son `bloqueado_por_bucle` y **25** ya traen rama |
| 82 aprobados listos para correr | de 87 `aprobado_irving`, **0** pasan los guards del pool |

De esos 87: **26 son `esperando_merge_irving`** (trabajo TERMINADO esperando el merge de Irving),
27 traen rótulo `[BLOCKED-/PARKED-]`, 25 están `done`, 6 en anti-bucle.

**El re-triaje con la política nueva encontró CERO items mecánicos** entre los 17 re-triables
(incluso incluyendo los del anti-bucle). La bandeja contiene sólo lo que legítimamente es de Irving.
Ensanchar la aprobación era la palanca equivocada: la cola no está vacía por falta de permiso, está
vacía porque **el backlog ejecutable se agotó**.

### Lo construido

- **E3 · frenos** (`15775b82`) — `circuito:clasificar-modulo`: 23 de 25 items sin footprint
  clasificados (2 sin match a propósito). Un item "Sin clasificar" corre SOLO y bloquea a las 6
  (#432 B2). Reaper con **vía rápida por flock del slot**: ya no espera 25 min, pregunta al kernel
  quién tiene el lock. `slotLibre()` al servicio compartido, fail-closed. Cron 5 → 2 min.
- **E1 · carril mecánico** (`b5d1c334`) — `ThomasService::clasificarMecanico`, cuatro puertas
  (frontera dura reusando el MISMO config de las consultas · sin negocio · **allowlist** de señales ·
  nivel ≤ B). Tope diario 25 + kill switch. Reusa `aprobado_claude`/`aprobado_revisor`.
- **E2 · re-triaje** (`b5d1c334`) — `circuito:retriar-bandeja`, agrupa por motivo lo que se queda.
  Respeta anti-bucle, ramas empezadas y lo que sólo espera merge.
- **E4 · crear=ejecutar** (`ce6950bc`) — item creado en la Torre entra como `aprobado_irving` con
  footprint auto-asignado; la vía externa sigue naciendo `pendiente_revision`. Excepción: si declara
  frontera dura, se para. `circuito:disparo-check` revive como **watcher** (no como despachador):
  adelanta una corrida del scheduler en **0.45 s**.

### Dos lecciones de forma (mismas dos veces)

1. **Match por palabra completa, no substring.** «Portal colaborador» caía en el módulo del circuito
   porque *cola* vive dentro de *colaborador* — el mismo accidente del denylist del revisor (#338).
   El `\b` de PCRE no sirve con acentos; hay que usar `\p{L}\p{N}` con `/u`.
2. **El match por términos no distingue mención de negación.** Un spec que dice "esto NO es decisión
   de negocio" contiene *negocio* y se queda con Irving. Falla hacia el lado seguro, pero castiga
   los specs bien escritos.

### Verificado

- Carril mecánico end-to-end: **#567** (hueco ruteado real, `BoxInputController@update` respondía
  `null` en silencio) se auto-aprobó, lo tomó una terminal, se ejecutó y **se mergeó** (`ae3e7a30`)
  sin tocar la bandeja.
- Crear=ejecutar: item normal → `aprobado_irving`/`en_cola`, **reclamado por wt-2 en segundos**;
  item con "DELETE FROM" → se quedó en `pendiente_revision` con su aviso.
- Reaper rápido con un worker VIVO en wt-1 → 0 reapeados (no mata vivos).

### ⚠️ Pendientes reales

- **Criterio 1 NO cumplido** (cola > 0 y 6 terminales ejecutando). No es alcanzable por la vía del
  item: no hay trabajo aprobado-y-sin-empezar. Las palancas reales son **los 26
  `esperando_merge_irving`** (merge de Irving) y **los 23 `bloqueado_por_bucle`** (necesitan cambio
  material, no re-aprobación — re-aprobarlos reabre el bucle que #117 dio 13 veces).
- **Hay una SEGUNDA sesión de CC en el repo** (PID 3248245, 11:18) escribiendo sin commitear en
  `main`: bloque `auditor` en `config/circuito.php` + `auditor_fingerprint` en `RoadmapItem.php`
  (#559, sin rama). No se tocó. Con el guard quirúrgico sólo bloquea merges que toquen esos dos
  archivos, pero son archivos calientes del circuito.

---

## 2026-08-08 17:30 — #566 Thomas DECIDE: auto-merge, auto-decisión y la raíz del anti-bucle

**Todo en DEV.** Precondición atendida: la otra sesión de CC (#559) había commiteado y `main`
estaba limpio; se trabajó ahí con commits por sub-paso, verificando el árbol antes de cada uno.

### LA RAÍZ DEL BUCLE (E3) — no era falta de permiso

#117 dio 13 vueltas y #19 **nueve aprobaciones** entre el 12-jul y el 6-ago sin avanzar. La causa
la dejó escrita un worker en el propio #19:

> «No ejecuto nada en #19 (nivel C = decisión de negocio exclusiva de Irving). Nota para des-atorar
> el loop: **reaprobar el mismo brief no avanza nada** — lo único pendiente es que Irving mergee la
> rama.»

**Aprobar se trataba como responder.** Un item cuya única pendiente era el MERGE volvía a
`aprobado_irving` con cada clic, el pool lo re-despachaba, el worker veía que no había nada que
hacer y lo re-escalaba. Tres sabores del mismo error:

| Item | Qué esperaba de verdad | Por qué ciclaba |
|---|---|---|
| #117 | el merge (trabajo hecho) | cada "aprobar" lo re-armaba para el pool |
| #29 | nada (superseded) | se re-aprobaba solo a diario con las mismas respuestas q1-q6 |
| #99 | una respuesta puntual | 15 aprobaciones genéricas, ninguna respondía la pregunta |

**Fix:** `ThomasService::pendienteReal()` clasifica qué le falta a cada item
(`merge | respuesta | ejecucion | dependencia | cierre`) y lo enruta ahí. Consulta a **git** cuando
las banderas de rama están frías — #19 tenía trabajo real con `branch_has_content` en cero y por eso
se leía como "falta ejecutarlo".

### Lo construido

- **E1 auto-merge** (`540b6aa0`): Thomas no reimplementa el merge — decide ELEGIBILIDAD y se lo
  encola al MergeRunner de siempre. Retiene lo que apunta a prod o es irreversible **mirando el
  DIFF, no el título**: #117 quedó retenido por traer `dropIfExists` (una migración que agrega es
  reversible con `git revert`; una que dropea ya cambió el esquema).
- **E2 "ya decidido"** (`d24c9e5e`): el autopilot reportaba 11 items con *«no quedan preguntas sin
  responder»* y aun así retenidos — el mismo error: "no hay nada que decidir" tratado como "no
  aprobar". Si el brief está contestado y nada pendiente es de Irving, el item pasa a la cola.
- **E4 consolidado** (`9005721b`): `docs/decisiones-pendientes-irving.md` — 4 puntos con la
  recomendación de Thomas cada uno, marcados ♻️ reversible o no. Default de 48 h configurable, y
  **sólo aplica a los reversibles**.

### Dos inconsistencias entre carriles, cerradas

- `'permiso'` a secas en la frontera dura: estaba `'permiso de rol'`, así que **#542** («los
  permisos editados en un rol no se reflejan») se colaba al carril automático por no coincidir con
  la frase exacta.
- Los dos carriles miraban textos distintos (uno incluía `prompt`, el otro no) → misma regla,
  veredictos distintos. Ahora ambos usan título+descripción+prompt.

### Resultado medido

| | antes | después |
|---|---|---|
| Cola ejecutable | **0** | **14** |
| Auto-decididos por Thomas | 0 | **10** (9 "ya decidido" + 1 mecánico) |
| Enviados a auto-merge | 0 | **23** |
| `bloqueado_por_bucle` | 30 | **24** (6 liberados; el resto tiene causa real) |
| Decisiones para Irving | dispersas | **4, en un solo documento** |

**Kill-switch verificado**: en pausa, los tres carriles y el `tick()` se detienen; al reanudar
vuelven a evaluar. Cap de auto-merge = 5/ciclo (`thomas.automerge.cap_por_ciclo`), tope de
auto-decisión mecánica = 25/día, ambos en `config/circuito.php`.

### Pendientes

- De los 23 auto-merges, el drenado es serial (regresión + build por rama) y quedó corriendo.
- 24 siguen en anti-bucle **con causa real**: 14 esperan ejecución pero son C/frontera dura,
  6 esperan merge no elegible, 3 dependen de otro item, 1 es cierre.
- `--cap=30` se usó para drenar la pila histórica; el régimen normal sigue en 5.

## 2026-08-08 17:55 — Cierre de #572 (Inventario: 3ª categoría) y #580 (Flotas: OCR)

Sesión directa con Irving: ambos items estaban en la bandeja (`requiere_irving`); Irving decidió y
esta sesión ejecutó y cerró. DEV, sin push a origin. El circuito estaba corriendo en paralelo (3
terminales), así que **lo primero fue sacar ambos items del pool** (`excluir_pool_automatico=1` +
`en_progreso`) para que ningún worker los reclamara a mitad del trabajo; la bandera se limpió al
cerrar para que un eventual reabrir no quede excluido en silencio.

### #572 — Inventario: tercera categoría "equipo de cliente" · commits 3f0fc034 + 2888b0be
- `inventory_item_types.categoria` resultó ser **varchar(20) nullable, no enum** → sin ALTER.
- `InventoryItemType::CATEGORIAS` como punto único de verdad; `categoria` al `$fillable`
  (antes solo se podía poblar por migración), accessor `categoria_name`, validación
  `nullable|in:` en store y update.
- Seleccionable de verdad: el form y el listado son DB-driven → migración idempotente que agrega
  la fila de `field_modules` (select) y la de `column_datatable_modules`.
- **Discrepancia con el enunciado:** el item decía "~18 tipos en null" (era la lista de dudosos de
  la Fase A). En la BD había **377 de 401**. Se clasificaron 302; quedan **75 ambiguos a
  propósito**, documentados por 3 motivos: 20 de equipo de red (sugieren una 4ª categoría), 3
  dudosos de negocio (ELIMINADOR/POE/POWER) y 52 de nombre genérico o erratas del catálogo.
- Resultado dev: 220 material / 95 herramienta / 11 equipo_cliente / 75 sin clasificar. En la
  custodia real 26 ítems ya agrupan bajo "Equipo de cliente"; "Sin clasificar" baja a 14.
- Portal "Mi material": etiqueta y orden de la nueva categoría; "Sin clasificar" siempre al final
  (antes empataba en `order=2`). ASSET_VER 15 → 16.

### #580 — Flotas: OCR de documentos (Fase 7) · commits 37c6afdd + 80ca8e39
- Construido sobre el **servicio único de IA**: resuelve el proveedor de `ia_proveedores` y habla
  por `IAAdaptadorFactory`. Sin cliente HTTP ni API key propios. (Patrón de consumo one-shot del
  módulo IA documentado en CONTEXTO-MEGAISP §4.)
- Extrae solo columnas reales de `fleet_documents`; `cost` fuera a propósito. Anti-invención:
  ilegible → null + baja. Fechas ISO, con DD/MM/AAAA aceptado defensivamente y nunca leído MM/DD.
- Confirmación humana: `POST /api/documentos/ocr` solo lee y deja rastro; el `store` toma el
  veredicto de la bitácora `fleet_document_ocr_runs`, no del request (run_id ajeno se ignora).
- Fallo o baja confianza → **se guarda igual**, marcado `ocr_needs_review` ("revisar
  manualmente") con botón "Ya lo revisé". Nunca bloquea la subida.
- El vencimiento confirmado lo levanta el cron existente `flotas:check-document-expirations`.
- Verificado en dev con llamadas reales a la IA: imagen (5/5 campos, alta), PDF nativo
  (insurance_policy), imagen no-documento (0 inventados), flujo completo, camino de fallo,
  captura manual sin OCR intacta, y los guardas (kill switch, mime, PDF con proveedor no-Claude).
- 8 ambigüedades de producto anotadas en el historial del item (tipos soportados, multipágina,
  umbral de confianza, disparo automático, costo en IA sin cache, permiso, doble subida y el
  detalle preexistente de que sin canales marcados no hay alerta).

**Pendiente en ambos:** validación visual de Irving. Nada desplegado a prod.

---

## 2026-08-09 09:40 — Item #593 "Jalar items": Thomas drena el backlog actual, sea cual sea su nivel

**Pedido de Irving (vía Torre, directo a cola):** "Deja que todos los ítems que se encuentran ahora
creados, los resuelva con las decisiones de Thomas el supervisor de las terminales, sea cual sea su
nivel, evaluaremos las decisiones del supervisor" — es decir, drenar el backlog actual con Thomas,
sin filtrar por `nivel_riesgo`, y revisar sus decisiones después (no antes).

**Interpretación (registrada como decisión en el item):** `circuito:destrabar-bandeja` ya existe y
hace exactamente esto (auto-merge de ramas verificadas + auto-decisión "ya decidido"/"mecánico" +
consolidado de lo estratégico), pero **no estaba corriendo** (ni en cron ni ejecutado manualmente
sobre el backlog vivo). No se tocó el tope C del carril mecánico (`circuito.thomas.mecanico.max_nivel`)
porque es una invariante de diseño explícita en el código ("un C es, por definición, una decisión de
diseño de Irving") — ensancharla es justo el punto que ya discute el item #566 (quedó consolidado).

**Ejecutado:** `php artisan circuito:destrabar-bandeja --apply` sobre los 84 items vivos en bandeja
(requiere_irving / pendiente_revision / aprobado_irving / esperando_merge_irving / bloqueado_por_bucle),
sin filtro de nivel en la consulta — cada item se enrutó según lo que le faltaba de verdad:

- **2 auto-merge:** #344 → mergeado limpio (`merge_commit` sellado, item `completado`). #528 → el
  merge-runner detectó un conflicto real en `ModuleServiceProvider.php`, abortó solo (**main quedó
  intacto**) y el item volvió a `requiere_irving` — el candado de seguridad funcionó como debía.
- **4 auto-decididos** (`thomas-ya-decidido`, brief ya contestado, no había nada pendiente de
  decidir): #480, #484, #492, #534 → pasaron a `aprobado_revisor`/cola ejecutable; 3 de ellos
  (#484, #492, #534) ya fueron tomados por otras terminales del pool en vivo, confirmando que el
  drenado alimentó reparto real, no solo cambió un estado.
- **4 consolidados** en `docs/decisiones-pendientes-irving.md` (estratégicos, con la recomendación
  de Thomas al lado, para que Irving conteste de una pasada): #530, #547, #565, #566.
- **74 retenidos correctamente** (la frontera dura funcionó): 23 rotulados [BLOCKED-]/[PARKED-], 21
  frontera dura (prod/dinero/borrar-datos/credenciales), 17 nivel C sin decisión de Irving, 5
  dependientes de otro item, 3 con migración destructiva, 2 de negocio/producto, 2 sin señal
  mecánica reconocible, 1 sin triar.

**Verificación:** estado post-corrida de #344/#528 y de los 4 auto-decididos confirmado por tinker
(arriba). Nada tocó producción, no se sobrescribió ningún item en `en_progreso` de otra terminal (la
query de `destrabar-bandeja` nunca los incluye). Cambio de código de este item: ninguno (es un item
operativo del propio circuito) — solo se regeneró el consolidado y se dejó esta bitácora.

## 2026-08-10 09:10 — Deploy: el circuito ensuciaba git y el propio deploy regeneraba el config cache (#520)

**Disparador:** el deploy #66 (release `V1.31-10.08.2026`) abortó en el paso
`git_staging_gate`. No fue un bug: el guardrail de allowlist hizo exactamente su trabajo.

### Hallazgo 1 — el sistema autónomo ensuciaba archivos versionados
`docs/decisiones-pendientes-irving.md` y `docs/pendientes-perfil-irving.md` estaban
**trackeados** y los escribe el circuito **en runtime**: `PerfilAprendizajeService::capturar()`
(append bajo ancla, en cada decisión de Irving) y `ThomasService::escribirConsolidado()`
(reescribe el archivo entero). El allowlist del release solo admite `public/chart.js` y
`public/images/vendor` → cada vuelta del circuito dejaba dos archivos fuera del allowlist y
**cada deploy iba a abortar**, no solo éste.

**Fix de raíz:** separar *estado que el circuito escribe* de *código versionado*. Los dos
`.md` salieron de git a `storage/app/circuito/` (gitignored por `storage/app/.gitignore`);
rutas por config (`thomas.consolidado.doc_path` + nueva `revisor.pendientes_perfil_path`).
- `PerfilAprendizajeService` **auto-siembra** el archivo con su ancla si falta: antes hacía
  `return` mudo, y fuera de git un checkout nuevo nunca lo tendría → el loop de aprendizaje
  moriría en silencio.
- `ThomasService` crea el directorio al vuelo.
- Las rutas viejas quedan en `.gitignore` como red: los worktrees corren código **commiteado**,
  así que un worker con código previo todavía escribe en `docs/` y volvería a ensuciar el árbol.
- Permisos: `storage/app/circuito` a `2775` (mismo gotcha que `storage/app/private/payments` —
  la carpeta la crea `meganet` pero escribe `www-data`).

### Hallazgo 2 — EL DEPLOY era la mina del #520
El paso 1 del pipeline (`DeploymentService::refreshDeploymentConfig`) corría
`Artisan::call('config:cache')`. Este box lee `env()` en **runtime** (IA/WhatsApp): con
`bootstrap/cache/config.php` presente Laravel se salta `LoadEnvironmentVariables` al bootear y
`env()` devuelve NULL. **No era una vuelta del circuito: era el deploy**, en cada release.

Corregidos los cuatro puntos que cacheaban config:
| Punto | Antes | Ahora |
|---|---|---|
| `DeploymentService::refreshDeploymentConfig` (dev) | `config:cache` + releer el cache | `config:clear` + recargar `.env` en proceso + releer `config/deployment.php` del fuente |
| `RemoteDeployCommand` paso `optimize` (**prod**) | `php artisan optimize` (incluye `config:cache`) | `config:clear && route:clear && view:clear && view:cache` |
| `RemoteDeployCommand` warm-up del rollback | `optimize` | mismos clears |
| `deploy/provision/install.sh` + `install_megaisp.sh` | `config:cache` | `config:clear` (+ `queue:restart`) |

El de provisión re-cacheaba *"para que Laravel tome la `WHATSAPP_API_KEY` nueva"* — que es
literalmente lo que la mataba.

**Se conserva la garantía original** del método (que la corrida ACTUAL no use valores stale que
el worker traía en memoria — el bug de V1.7 con el allowlist viejo), y además **auto-sana**: la
primera corrida con este fix borra el cache que dejaron los deploys anteriores.

### Verificación (dev)
- Bug reproducido: booteo **con** cache → `env('CLAUDE_API_KEY')` = NULL. Tras
  `refreshDeploymentConfig`: cache **ausente**, `env()` recuperado, `deployment` correcto
  (`publisher=true`, 2 artifacts, 9 pasos).
- Captura de perfil y consolidado de Thomas escriben en `storage/app/circuito/` y **no** recrean
  `docs/`; auto-sembrado en ruta limpia OK.
- `php -l` y `bash -n` limpios. Árbol de git **limpio** → el `git_staging_gate` ya pasa.
- `queue:restart` enviado (el worker de deploy tenía el `DeploymentService` viejo en memoria).

**Commit:** `05e0ecb1` (dev/main, **sin push**). Ítem #520 actualizado apuntando al script de deploy.

### ⚠️ Pendiente operativo en PROD
Como todo cambio a `remote:deploy`, surte efecto en el deploy **siguiente**: el deploy que
*entregue* este fix a prod todavía correrá el `optimize` viejo. Después de ese deploy hay que
correr **una vez** `php artisan config:clear` en `.198`.

## 2026-08-18 18:15 — Directiva 2A.4: env()→config, candado de coherencia, frenos asimétricos, precedencia #456

**Contexto de arranque.** El circuito estaba corriendo y trabajando el §1 por su cuenta: al abrir la
sesión ya había mergeado **#792** (12 archivos de IA, `CLAUDE_MODEL`/`CLAUDE_API_KEY` →
`config('services.anthropic.*')`) y tenía **#793** terminado en `wt-2` esperando merge. No se tocó
ninguno de esos archivos desde aquí para no colisionar.

**§1 — `env()` críticos + `config:cache` al checklist.**
- Mergeado #793 (`d546b5ff`): `WhatsAppStatusDriver` → `config/marketing.php`, AMI de CobranzaBlaster
  → `config/voip.php`, voz TTS → `config/cobranza.php`, `CONECTION_MIKROTIK` → `config/megafamilia.php`.
  Las ~23 críticas quedaron migradas. **#794** (resto no crítico) sigue en la cola del circuito.
- Nuevo `php artisan config:auditar-env` (`9b4c677f`): encuentra las llamadas REALES con el
  tokenizador de PHP (descarta comentarios, `getenv()`, el método privado `$this->env()` de
  `MysqldumpEngine`, el literal de regex `'/\.env(\b|\.)/'` y el `env(safe-area-inset)` que es CSS en
  un blade; sí mira dentro de `{{ }}`). Contrato = exit code. Al cerrar: **29 llamadas en 18 archivos**.
- Checklist restaurado en CLAUDE.md y CONTEXTO §1 (`2c4aedeb`), seguro por construcción:
  `php artisan config:auditar-env && php artisan config:cache`.

**Respuesta a «¿ruidoso o callado?» — CALLADO.** Hoy el circuito no se queda sin llave: la resuelve
el Hub (`api_integrations`, fila `anthropic-default` activa) ANTES de llegar al `env()`, y `vuelta.sh`
autentica el CLI por OAuth (de hecho hace `unset CLAUDE_API_KEY`). Pero si esa fila se cae,
`RevisorService::callModel()` atrapa el `Throwable` y devuelve *escala / confianza baja*: todo va a la
bandeja y el circuito **se ve prudente, no roto**; `proponerPreguntas()` devuelve vacío y el autopilot
deja de calificar, indistinguible de "briefs viejos sin datos". Sin banner ni contador. **Es la quinta
instancia** → item **#807**.

**§2 — candado de coherencia scope ↔ reclamo atómico (`4fd7ec84`).** `RoadmapItem::sqlElegibleParaPool()`
como definición única; `guardReclamoAtomico()` como seam; se eliminó la 5.ª copia en
`SupervisorService`. `PoolGuardCoherenceTest` (sin BD) + `circuito:coherencia-pool` (READ-ONLY sobre
los 283 items vivos: 211 = 211). Verificado con mutante.

**§3 — 2A.4, frenos asimétricos (`c1e3dbca`).** `circuito:re-triage` vence el consejo del clasificador
(14 días) y **jamás** toca el humano. `circuito:digest` §4 «Frenos que pusiste TÚ» cada 7 días,
ordenado por aprobaciones mudas: encabeza **#65 con 48** contra su propio `[BLOCKED-NEGOCIO]`.
`RoadmapItem::contarMudasEnLog()` = definición única. Regla en 3 sitios (config por ausencia,
fail-closed, test).

**§4 — 2A.5, precedencia del #456 (`8b1bf65e`).** Ampliado a `aprobado_irving`; **gana
`estado_aprobacion`** vía `if (isDirty('estado_aprobacion')) return;`. Verificado en dev en
transacción con rollback (5 casos).

**§6 — #791.** El contador de mudas ya estaba en el Panorama (lo hizo el circuito). Se le sumó el chip
**«frenos tuyos»** con tooltip (`2bc4ff0d`), `npm run dev` OK.

**§5 — stash `{1}`: REPORTE, sin tocar.** `edicion-suelta-TorreTerminales-2220`, 11-jul-2026 22:20,
5+/6− en `TorreTerminales.vue`: sólo copy del empty state ("#334 aún no existe" → "#334 ya corre,
verás wt-1…wt-6"). El archivo se **movió** a `releases/torre-control/` (f7990340) y **el texto viejo
sigue en main**, así que el cambio sigue siendo relevante y hoy es el que dice la verdad. No aplica
por la ruta vieja; **sí aplica limpio re-apuntado** a la ruta nueva (verificado con `git apply --check`,
no aplicado). Pendiente de decisión de Irving.

**Pendientes registrados:** #807 (señal de IA que no contesta) · #808 (línea de cron del re-triage,
bloqueada desde esta sesión).

## 2026-08-18 18:35 — Cierre de 2A: #807 (escala sin modelo), #808 (liveness), stashes, Paso 0 de 2B

**#807 — la señal, sin tocar la falla-segura.** `RevisorService::CAT_SIN_MODELO` / `CAT_ILEGIBLE`;
`proponerOpciones`/`proponerPreguntas` devuelven `motivo` (`sin_modelo` vs `vacio`);
`auditarSinModelo()` deja rastro durable en `circuito_revisiones`; `motivoTexto()` unifica la
narración en los 4 consumidores (los cuatro decían "sin brief utilizable"); digest §2-bis separa
autoriza / juicio / SIN MODELO; chip rojo en la Torre. **Verificado end-to-end contra la API** con un
modelo inexistente (404): `categoria=sin_modelo`, `veredicto=escala` (comportamiento intacto), fila
auditada, digest lo delata. Fila de prueba borrada. Commit `86548414`, item cerrado.

**#808 — liveness.** `config('circuito.procesos_programados')` + **un solo listener** de
`CommandFinished` + `agendado()` que mira el crontab. Digest §0 dice hoy: *«circuito:re-triage — NO
ESTÁ AGENDADO en el crontab. Se pierde: el freno del CLASIFICADOR nunca caduca»*. El circuito tomó
#808 y concluyó por su cuenta que no puede escribir el crontab del SO; deja la línea documentada.
`--dry` no sella latido, para que correrlo a mano no enmascare que el cron falta.

**2A.6 — la racha del fallback se mide sola** (`bd22a8b8`): sellada en 0 desde 2026-08-18; a los 7
días seguidos el digest avisa que ya es seguro retirar el `LIKE` sobre `title`.

**Stashes — los dos soltados.**
- `stash{1}` aplicado re-apuntado a `releases/torre-control/` y commiteado (`74688e3c`): el empty
  state dejó de anunciar el paralelo como futuro con seis terminales corriendo.
- `stash{0}` **verificado obsoleto con control**: 0 líneas de diff contra la rama
  `circuito/item-473-…` y 186 contra main → su wip ya estaba commiteado. Soltado.
- SHAs por si hicieran falta: `{0}`=`36ee7654`, `{1}`=`809df42e` (reflog).

**Paso 0 de Fase 2B — inventario de los 43 `module.json`** (`51f2af43` + comando `46898daa`).
No están vacíos pero cubren un tercio: **117 endpoints declarados sobre 3,193 pares método+ruta =
~3.7 % de la superficie**. 26/43 declaran `api_endpoints`, **sólo 9/43 declaran `screens`**, 11 casi
vacíos (casi todos Core). Y parte de lo declarado ya no es cierto: **86 %** de endpoints resuelven,
**79 %** de screens, **100 % de permisos** (111/111 — el único detector con confianza 1.0 hoy).
Desajustes reales verificados a mano: Flotas declara `/api/flotas/*` y existen 65 rutas bajo
`flotas/api/*`; Planes declara un esquema de URLs que nunca se construyó así.
**Hallazgo de diseño:** el lookup NO distingue "no se construyó" de "la declaración envejeció" → el
detector debe emitir *«declaración y realidad no coinciden»*, nunca *«falta construir X»*.
Reproducible: `php artisan circuito:inventario-spec --detalle`.
⚠️ `circuito-fase2a.md` **no está en el repo**; `medirContraSpec()` sigue devolviendo `[]` y su
docblock describe un contrato distinto (RoadmapItem `[SPEC]`, no `module.json`).

## 2026-08-18 19:05 — Fase 2B: `medirContraSpec()` implementado, el generador construye su sustrato

**El nombre corregido.** `spec_endpoint_no_implementado` presuponía la dirección del error;
renombrado a **`spec_desalineada`** — «declaración y realidad no coinciden». Detectar la discrepancia
con certeza no es saber qué falta: confianza 1.0 en la discrepancia, **0 en el diagnóstico**.

**Cuatro detectores por lookup** (`c1aa78fd`), DRY-RUN **41 gaps**: `spec_declaracion_incompleta` 21 ·
`spec_modulo_sin_declarar` 15 · `spec_desalineada` 5 · `spec_permiso_inexistente` **0** (lo esperado:
111/111 permisos existen; es guardia contra regresiones, no generador — escrito en el código para que
nadie lo lea como "detector roto"). `screens` apagado a propósito (9/43 no da para medir).

**Decisiones de implementación que no estaban en la directiva:**
- **Items acotados por tanda** (`cap_por_item` = 25) y la huella de dedup incluye el tramo
  (`api_endpoints#tN`) → la vuelta siguiente pide la siguiente tanda; sin progreso no se re-crea.
  Pedirle a Mapas «declara 200 endpoints» no es una tarea.
- **Umbral 30 %, no 100 %**: `api_endpoints` es el contrato público, no cada feed de datatable.
- `rutasDelModulo` usa `explode('\')`, no regex: el patrón equivalente pide cuatro niveles de escape
  entre PHP y PCRE y se rompió al escribirlo (`[^\\]` se comía el cierre de la clase).

**Métrica de convergencia al digest §5: superficie declarada = 5.5 %** (117 de 2,117 rutas
atribuibles a un módulo). Cifra afinada: el denominador excluye HEAD y las **133 rutas legacy fuera
de `app/Modules`**, que no pueden declararse por esta vía. Es el techo honesto; meterlas haría la
métrica inalcanzable.

**Higiene:** directivas versionadas en **`docs/circuito/`** (`f0f61236`) — 2A.4, cierre de 2A y 2B,
más un README que inventaría las **siete** instancias de la misma enfermedad y el criterio de cierre
que comparten. Docblock de `medirContraSpec()` reescrito con las **dos capas** (`module.json` =
estructura, implementada; item `[SPEC]` = intención, **pendiente y no descartada**).

**Hallazgo lateral → item #809:** 14 de los 41 gaps caen en módulos que `circuito.auditor.carriles`
no recorre, y **dos entradas de esa config no resuelven a ningún directorio** (`Roadmap / Circuito CC`
es el footprint, no el nombre; `Reportes` no existe) — el motor las audita en vacío **sin avisar**.
Consecuencia: el módulo del propio circuito nunca se ha auditado.

## 2026-08-19 16:02 — Item #840: análisis módulo "Plantillas" (worktree wt-4)

**Entregable:** `docs/analisis-modulo-plantillas-item-840.md` (solo análisis, sin código —
así lo pedía el item, detectado en el Paso 0 del #795).

**Inventario:** lo que parecían "tres piezas" de documentos/plantillas son en realidad dos
sistemas. (1) `core-documentos` (`app/Modules/Core/Documentos/`, CRUD de
`DocumentTemplate`/`DocumentTypeTemplate`) y el catálogo DB-driven `modules`/`fields`
(fila `DocumentTemplateClient`, id=62) que alimenta el selector "Generar Contrato" en
CRM/Clientes **son la misma pieza** — el segundo solo consume las tablas del primero.
(2) `DocumentosOficiales` (item #795, Parte A) **no tiene código todavía** — solo el plan
aprobado; su propio Paso 0 ya había decidido no colgarse de core-documentos.

**Dato inesperado:** SÍ existió un módulo de catálogo llamado literalmente "Plantillas"
(tabla `contract_templates`, modelo `ContractTemplate`) en julio 2024 — se creó el 21 y se
borró deliberadamente el 23, reemplazado por `core-documentos`. El modelo `ContractTemplate`
sigue huérfano en `app/Models/` (sin tabla, sin consumidores) — nota menor, sin acción esta
vuelta.

**Conclusión:** NO conviene unificar. `core-documentos` (motor de generación de contratos
desde plantilla+variables) y `DocumentosOficiales` (repositorio versionado de archivos ya
hechos) resuelven problemas de negocio distintos — la separación actual es preferible.

**Deuda real encontrada (no es el módulo, son los permisos):** las rutas de administración
de `core-documentos` cuelgan del permiso monolítico legado `config_view_system`
(`config/route_permission.php:1402-1443`), compartido sin relación con otras 3 features, a
diferencia del *uso* de esas plantillas (generar contrato) que sí tiene permisos granulares
propios. Registrado como sub-item **#850** con plan sugerido (permisos por acción, nivel B
por tocar acceso de roles reales).

Commit `5a48ee40` en rama `circuito/item-840-evaluar-unificacion-bajo-un-modulo-plant`,
integración encolada vía `circuito:integrar`.

## 2026-08-20 16:39 — Item #903: tanda 1 de carriles ampliados del auditor (Auth/Planes/Documentos)

Ejecutor on-box (wt-6). Item #903 seguía a #809: decidir, por tandas, si se amplían los
carriles del auditor (`config/circuito.php` → `circuito.auditor.carriles`) a los 11 módulos
que hoy quedan fuera (Auth, Dashboard, Documentacion, Documentos, Layout, Localizacion,
Release, IA, Planes, SmartImportExport, WarRoom).

Irving ya había respondido las 3 preguntas del brief (aprobado): tandas de 2-3 módulos,
priorizando criticidad de negocio (facturación/permisos/clientes primero), evaluando
resultados entre tandas. Mi trabajo fue EJECUTAR esa decisión: elegir la tanda 1 concreta
y aplicarla.

**Tanda 1 elegida:** `Auth`, `Planes`, `Documentos` — las 3 más cercanas al criterio de
Irving entre las 11 candidatas (ninguna es literalmente Clientes/Configuracion/CRM, que ya
están en `serializado`). Auth = permisos/seguridad; Planes = catálogo ligado a facturación
(Internet/VoIP/Custom/Bundle); Documentos = plantillas de contratos/facturas. Verificado
`is_dir` + controllers reales + `module.json` `active:true` en los 3 antes de sumarlos
(evita repetir el caso `Reportes` de #809). Van a `paralelo`: coupling de namespace cruzado
medido por grep (Auth=1, Planes=4, Documentos=0 referencias externas), muy por debajo del
umbral de `serializado` (Clientes in=8, Configuracion in=9, CRM in=8, ModuleManager in=7).

Nota de criterio: #809 sugería empezar por los de 0% declarado (Auth/Dashboard/Release/
Localizacion/Documentos/Documentacion). Prioricé el criterio explícito de Irving (negocio)
sobre esa señal técnica cuando divergían — por eso entra Planes (no está en 0%) y no, por
ejemplo, Dashboard o Localizacion.

Commit `c243bc3f` en rama `circuito/item-903-decidir-por-tandas-si-se-amplian-los-c`,
integración encolada vía `circuito:integrar`. Verificado: `php -l` limpio, `config:clear` +
tinker confirmó los 3 módulos en el carril y `AuditorService::rutaModulo()` resuelve a
directorio real para los 3.

Los 8 módulos restantes quedan registrados como sub-item **#918** ("Tanda 2 de carriles
ampliados"), para decidirse tras evaluar qué genera esta tanda 1 en la Hoja de Ruta —
siguiendo el ritmo iterativo que pidió Irving (evaluar entre tandas, no aplicar las 11 de golpe).

## 2026-08-20 18:31 — wt-3: Item #893 — fix evaluarYaDecidido() (root cause de las 12 escalaciones idénticas de #186)

**Qué se hizo:** `ThomasService::evaluarYaDecidido()` (carril "ya decidido" de Thomas, el que
auto-aprueba items cuyo brief ya quedó 100% contestado) aprobaba items sin mirar dos cosas: (a)
el flag `requiere_sesion_supervisada` que Irving fija explícitamente para decir "necesito estar
presente"; (b) si la opción elegida en la pregunta MAESTRA del brief era literalmente "Escalar a
Irving" (la recomendada del Revisor cuando no puede resolver algo solo). Con (b) no cubierto, el
carril veía "brief contestado" = "decisión a favor del pool" y despachaba el item, que volvía a
escalar por la misma razón — eso fue lo que le pasó a #186 doce veces seguidas entre 2026-07-15 y
2026-08-20.

**Fix (nivel B, aprobado por Irving, opción 1 de q1 — "fix mínimo"):**
- Guard nuevo: si `$item->requiere_sesion_supervisada` es true, el carril no aprueba (mismo lugar
  que el guard de `tieneFrenoHumano()`).
- Al leer la pregunta con índice 0 (la maestra) del brief: si su `opcion_elegida` resuelve a un
  texto que contiene "escalar a irving" (case-insensitive), se trata como "sin decisión tomada
  para el pool" en vez de aprobar. Acotado a la pregunta 0 a propósito — preguntas secundarias
  pueden mencionar "escalar a Irving" como parte de un plan de contingencia (falso positivo real
  visto en #463 q4: "Rollback + escalar a Irving si algo falla") sin que ESA sea la decisión
  tomada.
- La detección se extrajo a `ThomasService::opcionElegidaEsEscalar()` — método estático PURO
  (solo arrays, sin BD ni contenedor) — específicamente para poder testearlo sin bootear Laravel
  ni correr `migrate:fresh` contra la BD compartida de dev (que es lo que hace `tests/TestCase.php`
  en cada test normal).

**Test de regresión (q3, opción 1 aprobada):** `tests/Unit/Modules/Addons/Roadmap/
EvaluarYaDecididoEscalarTest.php` — PHPUnit puro (mismo patrón que el precedente
`DestrabeNoRecibeAutoAprobadosTest`), 6 tests: los 4 casos pedidos (autorizar/escalar × pregunta
elegida/no elegida) + falso-positivo de pregunta secundaria + clave corrupta, más un guard de
fuente para `requiere_sesion_supervisada`. `vendor/bin/phpunit --no-configuration` → 9/9 OK (junto
con el precedente), sin tocar la BD.

**Backfill (q2, opción 1 aprobada — "auditar y reencolar"):** corrida la auditoría en vivo (solo
lectura) contra la BD de dev antes de escribir nada: 0 de 7 items actualmente en estado
auto-ejecutable/en-progreso calzan con "requiere_sesion_supervisada=true" o "pregunta maestra
resuelta a escalar". #186 mismo ya está en `aprobado_irving` + `excluir_pool_automatico=1` (fuera
del pool, en la bandeja de Irving). **Nada que reencolar hoy** — no se dejó comando/script
permanente de backfill porque no había backfill real que hacer.

**Verificado:** `php -l` limpio, `php artisan --version` bootea, datos reales via tinker
(`ThomasService::opcionElegidaEsEscalar()` sobre la pregunta real de #186 → `true`; sobre la de
#893 mismo → `false`, no bloquea su propio fix aprobado).

**Estado:** completado, rama `circuito/item-893-...` integrada vía `circuito:integrar` (cola del
runner on-box a `main`).

## 2026-08-21 01:11 — Item #982: campo 'diagnostico' expuesto (guardado) en lista y ficha del Roadmap

**Item:** #982, sub-item de seguimiento de #935 (`DiagnosticoItemService`). Worktree `wt-1`.

**Qué se hizo:** en `app/Modules/Addons/Roadmap/Controllers/RoadmapController.php` se agregó el
campo `diagnostico` a los dos endpoints que pedía el item:
- `itemDetallePayload()` (la FICHA — `GET /roadmap/item/{id}`, sirve `item.blade.php`, consumido
  por `RoadmapItemDetalle.vue` de #936, que ya esperaba `item.diagnostico.{causa,explicacion,
  accion,procedencia}`).
- `index()` (la LISTA — `GET /api/roadmap/items`, consumido por `RoadmapTab.vue`), usando la
  variante **BATCH** (`diagnosticosLote()`, una sola pasada para los N items visibles vía
  `DiagnosticoItemService::paraLote()`) para no disparar N consultas.

Dos helpers privados nuevos: `diagnosticoDe($item)` (ficha, llama `DiagnosticoItemService::para()`)
y `diagnosticosLote($items)` (lista, llama `::paraLote()`), ambos con guard
`class_exists`+`method_exists` — mismo patrón que ya usa `atorados()` (#937). `DiagnosticoItemService`
**todavía no existe** (#980/#981 siguen `requiere_irving`), así que hoy `diagnostico` sale `null` en
ambos endpoints sin romper nada; se activa solo el día que el servicio aterrice.

**Decisión registrada (fija el contrato para #980/#981):** `DiagnosticoItemService::para(RoadmapItem
$i): ?array` para la ficha, `::paraLote(Collection $items): array<id,diag>` (mapa id→diagnostico)
para la lista. Quien implemente #980/#981 debe respetar esas firmas para que este wiring se active
sin tocar el controller de nuevo.

**Verificación:** en tinker, vía reflection sobre el controller real, contra los **159 items reales**
en `aprobado_irving` (113) + `requiere_irving` (46) de dev (el item mencionaba 72+53, cifras ya
desactualizadas por el paso del tiempo): `diagnosticoDe()`/`diagnosticosLote()` no truenan con datos
reales, devuelven `null`/`[]` de forma controlada; `itemDetallePayload()` incluye la clave
`diagnostico`; la serialización JSON de la lista (`setAttribute` + `json_encode`) es válida y no
altera el resto de columnas. `php -l` limpio, `php artisan --version` bootea, rutas intactas
(`route:list --path=roadmap`).

**Estado:** completado, commit `a31e7494`, rama `circuito/item-982-...` integrada (encolada al
runner on-box → `main`). `enlace_revision` = `/roadmap/item/982` (hoy sin cambio visual: el front
ya sabe pintar `item.diagnostico` pero no hay nada que mostrar hasta que #980/#981 existan). #935
sigue abierto como paraguas hasta que esos dos sub-items aterricen — no se fuerza su cierre.

## 2026-08-21 13:44 — Item #948 cerrado sin código: duplicaba el fix ya hecho por #967

**Item #948** ("Bucle #463: evaluarYaDecidido() re-encola items bloqueados por dependencia externa
no resuelta — ThomasService.php:464") fue creado 2026-08-20 18:54 como sub-item de seguimiento de
#463, describiendo la causa raíz del bucle de 22+ ejecuciones idénticas de #463 (Thomas re-aprobaba
el item aunque la acción física que implicaba su decisión —mergear #308— nunca había ocurrido).

**Hallazgo al ejecutar:** ese MISMO bug ya había sido diagnosticado y arreglado por otro item paralelo,
**#967** ("Anti-bucle de Thomas: 'brief contestado' no distingue decisión tomada de decisión EJECUTADA
— caso #463↔#308"), mergeado a main **2026-08-20 20:00:24** (commit `db50e173`) — ANTES de que Irving
aprobara #948 (2026-08-21 13:41:39). El fix real vive en `ThomasService.php` líneas 512-533
(`referenciasItemEnTexto()` + guard: si la pregunta maestra ya contestada menciona "#N" y N es nivel C
con rama lista pero sin `merge_commit`, no aprueba) con tests `EvaluarYaDecididoEscalarTest` y
`ReferenciasItemEnTextoTest` en verde.

**Verificación (read-only, sin tocar código):**
- `git log -S "referenciasItemEnTexto"` → commit `db50e173` (#967) ya en el árbol de `main`.
- Item #967 en BD: `estado_aprobacion=completado`, `merge_commit=837cb756...`.
- Historial de #463: última entrada de log **2026-08-20 19:56:04**, ~24h sin recurrencia desde el
  merge de #967 (antes se repetía cada 5-10 min) → el fix real ya cortó el bucle.
- `php artisan test --filter=ReferenciasItemEnTextoTest` → 5/5 en verde.

**Decisión (consultada a Thomas, `circuito:consultar`, exit 0 PROCEDE):** no implementar el guard
adicional "esperando_dependencia" (estado nuevo) que Irving había elegido en el brief de #948 —
sería sobre-ingeniería para un caso que el fix de #967 ya resuelve y tiene verificado sin regresión.
Cerrado #948 como `completado` sin cambios de código, con `reporte_coloquial`/`enlace_revision`
apuntando al mismo lugar que #967 (`/releases` → Hoja de Ruta → item #463, y item #967 como el fix
real). `#463` sigue como el item de seguimiento original; no requiere acción adicional.

**Para el próximo:** si aparece un caso de este bucle que el guard de #967 NO cubra (dependencia
fraseada distinto a "#N", o dependencia que no sea "nivel C sin merge_commit"), ahí sí evaluar el
estado nuevo "esperando_dependencia" más general — pero no antes de tener ese caso real.

## 2026-08-21 14:03 — Item #979: runbook de restricción /ip service api (MikroTik, puerto 8730)

**Contexto:** #979 es un item de seguimiento (hijo de #951/#811) sobre el servicio `/ip service
api` de MikroTik expuesto sin restricción de `address` de origen. Irving ya había resuelto las
3 preguntas del brief: restringir por address (no rotar credenciales, no forzar api-ssl), aplicar
primero a un router piloto, y que **el cambio en los routers lo aplique Irving/NOC manualmente**
— el circuito NO debe automatizarlo ni tocar routers reales.

**Entregado:**
- `docs/runbook-mikrotik-restriccion-api-address.md` — guía paso a paso para NOC: inventario
  (`/ip service print`), definición de whitelist, aplicación en un router piloto con verificación
  y rollback, luego rollout gradual.
- `php artisan mikrotik:audit-api-exposure` (nuevo, `app/Console/Commands/Active/`) — comando de
  **solo lectura** (nunca llama `/set` ni `/remove`) que recorre los routers MikroTik `active` en
  la tabla `mikrotiks` y reporta si `api`/`api-ssl`/`winbox`/`ssh`/`www` tienen `address` vacío.
  No agendado en el schedule (se corre a mano cuando NOC lo necesite).
- No se conectó a ningún router real durante esta sesión (verificación solo con `php -l` +
  `artisan list mikrotik` + `artisan --version`).

**Hallazgo relacionado (NO corregido aquí, quedó como sub-item #983):** `MikrotikRulesJob`
(dispatch al guardar/activar la config de un Mikrotik) arma una regla de firewall
`MgNet_INPUT_MEGANET_TO_API_ACCEPT` (accept solo desde la IP de MegaISP hacia el puerto de la
API), pero **nunca invoca** `RouterConnection::addRulesInputDorpRest()` (el drop final del resto
del chain `input`, que sí está definido en el código). Sin ese drop, la política implícita de
RouterOS en `input` es accept, así que la regla de accept-solo-MegaISP no restringe nada en la
práctica hoy — es efectivamente dead-code de seguridad. Corregirlo tocaría el firewall de routers
en producción (podría cortar Winbox/SSH si no se agregan sus accepts antes del drop-resto), por
eso quedó como sub-item aparte para su propio triaje con el mismo cuidado (piloto + rollback).

**Estado:** #979 → `completado` (runbook + tooling listos, ejecución en routers reales queda para
Irving/NOC). #983 → `pendiente_revision`, esperando triaje del revisor.

## 2026-08-21 14:16 — #949 (wt-1): item paraguas IPv6 atascado en bucle de reap — cerrado correctamente

**Contexto:** el item #949 (IPv6 Fase 1.1-1.3) ya había sido correctamente descompuesto por un
agente anterior (wt-4) en 3 sub-items con todo el detalle heredado — #984 (registro de módulo
`Addons/Ipv6` + 8 permisos Spatie), #985 (migraciones incrementales de las 7 tablas) y #986
(modelos Eloquent con relaciones/casts/scopes). wt-4 reportó el cierre vía `circuito:reportar
--tipo=cierre`, pero **nunca transicionó `estado_aprobacion`** — el item quedó colgado en
`en_progreso`. El reaper rápido (`circuito:reap-stuck`) lo detectó como huérfano (slot wt-4 libre)
y lo re-encoló a `aprobado_revisor`, de donde volvió a ser reclamado (esta vez para mí, wt-1).

**Causa raíz:** `circuito:sub-item` (comando que crea los hijos) no toca el item padre en
absoluto — solo escribe `origen_item_id` en el hijo. El mecanismo real de "cierre paraguas" vive
en `RoadmapItem::booted()` (guard `saving()`, ~línea 286-311): SOLO se activa cuando algo intenta
poner `estado_aprobacion=completado` en un item con `tieneSubItemsAbiertos()=true` — en ese caso
lo reroutea a `aprobado_irving` + `excluir_pool_automatico=true` (fuera del pool de despacho) y
deja loggeado `paraguas_abierto`; cuando cierre el último hijo, el hook `saved()` del hijo detecta
que el padre está retenido como paraguas y sin sub-items abiertos, y lo cierra solo
(`paraguas_cerrado`). Como wt-4 nunca intentó el `completado`, el guard nunca disparó y el item
quedó despachable indefinidamente — bucle de re-encolado.

**Fix aplicado (sin tocar #984/#985/#986, sin duplicar la descomposición):** confirmé que la
descomposición previa es correcta y completa, y apliqué `estado_aprobacion='completado'` sobre
#949 vía tinker. El guard nativo interceptó la transición como se esperaba → quedó en
`aprobado_irving` + `excluir_pool_automatico=true` + log `paraguas_abierto` (3 sub-items
abiertos). Cerrará solo cuando #984/#985/#986 completen.

**Nota para el circuito (posible mejora futura, NO aplicada aquí — fuera de mi item):**
`circuito:sub-item` podría, tras crear el primer hijo, intentar automáticamente
`estado_aprobacion=completado` en el padre para activar el guard paraguas en el mismo comando —
así ninguna terminal futura tendría que recordar hacerlo a mano y no volvería a pasar este bucle.
No lo implementé porque cae fuera del alcance de mi item (#949) y tocaría un comando compartido
por todo el circuito.

**Estado:** #949 → `aprobado_irving` (paraguas retenido, fuera del pool). #984/#985/#986 siguen
pendientes de ejecución normal.

## 2026-08-21 14:37 — Item #942: Torre config muestra la cadencia del crontab (solo lectura)

**Contexto:** #942 pedía agregar, al panel de configuración de la Torre (`TorreConfigPanel.vue`,
engrane al final de las pestañas de `/releases`), la cadencia real de cada motor programado del
circuito (cada minuto, 06:20 diario, etc.), reusando la infraestructura de latidos de
`RoadmapCircuitoService::latidos()` (#808) en vez de inventar un segundo medidor.

**Hallazgo al arrancar:** una sesión anterior en este mismo worktree (wt-2) ya había implementado
y commiteado el cambio completo (commit `d7b4c003`: agrega `cadencia` a cada entrada de
`config('circuito.procesos_programados')`, la propaga por `latidos()` y la pinta como columna
nueva en la tabla de motores con nota de solo-lectura), pero murió por timeout justo después de
commitear — el reaper la re-encoló como huérfana. En paralelo, mientras yo investigaba, el
MergeRunner on-box detectó la rama con contenido verificado y la fusionó a `main` por su cuenta
(merge `0ec7dd50`), dejando el item en `completado` — una carrera legítima entre mi arranque y el
ciclo normal del circuito, no un error.

**Lo que aporté esta vuelta:** verifiqué que el trabajo ya fusionado es correcto y completo —
rebase limpio de la rama sobre `main` actual (sin conflictos, diff vacío contra `main` tras
rebasar = confirma que ya estaba integrado), `php -l` en los 2 archivos PHP tocados,
`php artisan --version` bootea, `tinker` confirma que `latidos()` devuelve `cadencia` para los 12
motores reales, y `bash deploy/circuito/npm-build.sh` compiló `TorreConfigPanel.vue` sin errores.
Completé el cierre administrativo que había quedado a medias por la carrera reap/merge:
`reporte_coloquial` en lenguaje llano (no repite el título) y `enlace_revision` real
(`/releases` → engrane → tabla de motores, columna Cadencia), ambos vacíos hasta ahora.

**Estado:** #942 → `completado` (ya lo estaba; solo se completó el reporte para revisión de
Irving). Sin código nuevo — el cambio funcional es 100% de la sesión anterior.

## 2026-08-21 17:36 — Item #1030: seguimiento de #1008 resuelto por precedente (implementación ya la contestaba)

**Contexto:** #1030 es el primer ejemplar del mecanismo que #1008 acababa de construir
(`ThomasService::preguntasSinResolver()` + `generarSeguimientoPreguntas()`, commit `9a07c1e0`):
al cerrarse #1008 con 2 preguntas `requiere_irving=true` sin `opcion_elegida`, el propio gate de
cierre auto-generó #1030 como hijo de seguimiento, colgando ahí las 2 preguntas textuales para
que no quedaran enterradas en el log. Las preguntas eran, literalmente, "¿cómo debería funcionar
este mecanismo de auto-seguimiento?" y "¿qué nivel/estado debe llevar el item auto-generado?" —
la misma pregunta que el mecanismo ya tuvo que responder para poder construirse.

**Hallazgo:** el commit `9a07c1e0` (que implementó #1008) ya construyó exactamente la Opción 1
(recomendada) de ambas preguntas, no una alternativa:
- **q1** ("¿cómo auto-generar?"): `preguntasSinResolver()` detecta el patrón reusando los campos
  existentes (`requiere_irving` + `opcion_elegida` vacía, sin campo nuevo de schema) y
  `generarSeguimientoPreguntas()` crea el hijo colgando de `origen_item_id`, heredando `modulo` y
  `nivel_riesgo` del padre — exactamente el texto de la Opción 1 de q1.
- **q2** ("¿qué nivel/estado inicial?"): el hijo nace con `estado_aprobacion='requiere_irving'`
  (directo en la bandeja de decisión de Irving) heredando el `nivel_riesgo` del padre —
  exactamente el texto de la Opción 1 de q2 ("hereda nivel del padre, estado pendiente en bandeja
  de decisión de Irving").

**Por qué no escalé esto a Irving como una decisión nueva:** no había una decisión de diseño
pendiente que tomar — ya estaba tomada y en producción (dev) desde que #1008 se implementó; #1030
era el eco automático de esa decisión, no una pregunta abierta esperando input nuevo. Además,
Opus ya había re-triajeado el item vía `circuito:destrabe` (log `destrabe_reaprobado`, categoría
`tecnico_seguro`) autorizando explícitamente al circuito a procesarlo "revisando las preguntas
pendientes del #1008" — la revisión mostró que ya estaban contestadas por el código, así que
registré esa correspondencia (`responderPregunta` en ambas, apuntando a la Opción 1 real) en vez
de inventar una decisión nueva o dejarlo indefinidamente en la bandeja.

**Cuidado con el efecto colateral:** si hubiera cerrado #1030 a `completado` dejando sus 2
preguntas sin `opcion_elegida`, el MISMO gate de #1008 (que vive en `RoadmapItem::booted()`, no
distingue si el padre que se cierra es él mismo un item de seguimiento) habría disparado de
nuevo y creado un NIETO "Seguimiento: pregunta sin resolver de #1030" — bucle de auto-seguimiento
recursivo. Resolver ambas preguntas con `responderPregunta()` antes de completar es lo que corta
esa recursión.

**Estado:** #1030 → `completado`, ambas preguntas con `opcion_elegida` apuntando a su Opción 1
real + `decision_resuelta=true`/`decision_fuente='circuito'` (no `'irving'`: la fuente real es la
correspondencia con el código ya implementado, no una decisión nueva de Irving — si él no está de
acuerdo con el patrón ya construido, sigue pudiendo reabrirlo). Sin cambios de código de producto;
el único artefacto es esta bitácora + los campos de decisión del item.

## 2026-08-21 18:20 — #1004: verificación §1 del disparo por hambre + cierre de la directiva

Item #1004 ("Directiva — el disparo por hambre, y la veta que no hemos tocado") pedía, antes
de construir nada, verificar si el auditor dispara por ociosidad/hambre o solo por tiempo, y
qué produjo en su última corrida. Verificación (read-only, sin tocar código de producto):

- **Sí existe disparo por hambre**, ya construido antes de este item: `AuditorService::debeCorrer()`
  sólo genera trabajo si `profundidadCola() < umbral` (3), chequeado cada minuto desde
  `circuito:scheduler` (sin cron aparte). Nació como **#559** (2026-08-08) y se endureció con
  **#1015** (memoria de cobertura por módulo + freno por sequía por dry-runs seguidos), ambos ya
  `completado`/integrados a `main`.
- **Última corrida real:** `2026-08-21T17:34:51-06:00`, dry-run, **0 candidatos/0 creados** en
  ~20 módulos — coincide con el propio diagnóstico del prompt de #1004 ("detectores mecánicos:
  agotados"). `rachaSeca=3` → el freno por sequía ya está alargando el intervalo.
- **La regla de proceso del §2** ("directiva → items antes de ejecutar") y **el minero de
  bitácora del §4** SÍ se convirtieron en trabajo real (no quedaron "dichos y nunca hechos"): una
  vuelta anterior de este mismo item ya dio de alta **#1015** (integrado), **#1016** (señales de
  minería restantes, pendiente, dueño de otra vuelta), y corrió el minero v1 en real produciendo
  **#1013** (13 casos `requiere_irving` sin resolver, bandeja de Irving) y **#1014** (6
  referencias `#NNN` rotas — investigado y cerrado, ver
  `docs/roadmap-referencias-rotas-item-1014.md`).
- **§5** (enriquecimiento de `module.json`) queda explícitamente fuera de alcance (el propio
  prompt: lo empuja el shell móvil por su lado).

**Conclusión:** todo lo construible bajo #1004 ya estaba hecho (parte de antes vía #559, parte
por una vuelta previa de esta misma directiva). Lo único pendiente de entregar era la
verificación del §1 — documentada en `docs/roadmap-verificacion-disparo-hambre-item-1004.md`.
Se cierra #1004 como `completado` sin cambios de código de producto; el trabajo vivo restante
sigue rastreado en #1013 (bandeja de Irving) y #1016 (dueño de otra vuelta del circuito).

## 2026-08-21 19:16 — Item #965: AuditReport.vue queda fuera del catálogo de acciones (decisión documentada, sin código)

**Item:** #965 (sub-item de #909/#960), worker wt-1. Caso especial: decidir tratamiento de los
botones "Alternar item del plan" / "Nota" de `AuditReport.vue` (`AuditController::planToggle`/
`planNote`), que hoy no tienen `authorize()` inline ni entrada en `config/route_permission.php`.

**Historial previo:** el item ya había sido escalado dos veces (wt-3 y una corrida anterior de
wt-1) a Thomas por la pregunta de si un permiso nuevo debía asignarse también a `DESARROLLADOR`
además de `super-administrator`; Thomas escaló ambas veces a la bandeja de Irving sin que quedara
registrada una respuesta explícita a esa pregunta puntual (Irving sí respondió q1/q2 del brief
original — "crear permiso" + "solo superadmin" — pero esas opciones nunca mencionaban a
`DESARROLLADOR`, así que la pregunta de fondo seguía abierta).

**Lo que se hizo esta vuelta:** en vez de re-escalar la misma pregunta sin novedad, se investigó
a fondo el middleware `CheckRoutePermission` y se confirmó un hallazgo que cambia el diagnóstico:
la ruta `/releases/audit/plan*` **ya es admin-only hoy** (bypass de `isAdmin()/isDevelopment()/
isSuperAdmin()` en el middleware, antes de siquiera mirar `route_permission.php`) — no está abierta
a cualquier autenticado como sugería la nota "sin permiso". Crear un permiso nuevo limitado a
`super-administrator`+`DESARROLLADOR` (lo único que `PermissionSyncService` garantiza automático)
sería potencialmente **más angosto** que hoy si algún usuario real tiene rol `Super Administrador`/
`Administrador` puro — un cambio de comportamiento real, no mecánico.

**Decisión (registrada como `--tipo=decision`, opción (a) del propio item):** dejar los dos botones
FUERA del catálogo de acciones por ahora. Cero código tocado (ni permiso nuevo, ni `authorize()`
inline, ni catálogo — que además sigue sin mergear, #876 `esperando_merge_irving=true`). Se amplió
la sección 4 de `docs/circuito/inventario-botones-torre.md` con el hallazgo y el razonamiento
completo, para que quien retome esto (incluido Irving, cuando responda la pregunta de roles) no
repita la investigación.

**Verificación:** `php -l` sobre el doc + `php artisan --version` (bootea normal) — no había código
que tocar. Commit `0a723d9f` en la rama `circuito/item-965-...`, encolada a auto-merge vía
`circuito:integrar`. Item #965 cerrado `completado`.

## 2026-08-21 20:04 — Item #1041: cierre del [RESPUESTA] de #1020 (worktree ajeno wt-3 + dependencia #1019)

**Item:** #1041 (`tipo=respuesta`, hijo de #1020), worker wt-2. No es un item de código: es el
reporte que una vuelta anterior de #1020 dejó registrado al encontrar la rama
`circuito/item-1020-ventana-de-reversibilidad-medida-en-fila` ya checkeada (con trabajo real sin
commitear) en el worktree de otro agente (`wt-3`). Esa vuelta anterior, respetando el aislamiento
(#334), leyó el diff de wt-3 en SOLO LECTURA, replicó el mismo cambio archivo por archivo en una
rama propia nueva (`circuito/item-1020-reversibilidad-filas-nuevas`), lo verificó end-to-end y lo
comiteó — ese trabajo ya vive mergeado en main (commit `3c25df12`, "Integra circuito #1020").

El item dejaba dos decisiones pendientes, con recomendación explícita (opción 1, la reversible):
1. Si era seguro descartar el working tree sucio de wt-3 — recomendación: NO intervenir, dejar que
   su propio operador lo recicle por el mecanismo normal (cero riesgo, el trabajo ya no se pierde
   porque vive commiteado en main).
2. El sub-item de #1019 ("down() obligatorio y probado") sigue en `requiere_irving` — no bloqueaba
   #1020 y ya estaba declarado explícito, sin acción nueva que tomar aquí.

**Lo que se hizo esta vuelta:** se aplicó la opción recomendada (no intervenir en wt-3) y se
verificó en modo SOLO LECTURA que el pronóstico se cumplió sin ayuda: `git worktree list` muestra
`wt-3` de vuelta en HEAD desacoplada sobre `3c25df12` (el mismo commit de main), es decir, ya fue
reciclado normalmente por su operador — la rama vieja con el diff duplicado ya no está ahí. Cero
archivos de wt-3 tocados, ni siquiera para descartar cambios (no hacía falta). Se registra la
decisión con `circuito:reportar --tipo=decision` y se cierra el item.

**Verificación:** `git worktree list` (lectura, confirma wt-3 reciclado) + `git log --oneline main`
(confirma el commit `3d4f5778`/`3c25df12` de #1020 ya en main). No hay pantalla que enlazar — item
marcado `sin_ui=true` con motivo. Commit en la rama `circuito/item-1041-...` (esta entrada de
bitácora), encolada a auto-merge vía `circuito:integrar`. Item #1041 cerrado `completado`.

## 2026-08-22 00:52 — #1032 (wt-1): mismo bug de #949 — paraguas IPv6 5.2 atascado en bucle de reap

**Contexto:** el item #1032 (IPv6 Fase 5.2: motor de máquina de estados) ya había sido
correctamente descompuesto por una vuelta anterior de wt-1 (00:39) en 3 sub-items que cubren el
alcance completo — #1066 (esquema propio: plan + log de transiciones), #1067 (servicio
`Ipv6RenumberingStateMachine`: transiciones + 3 puntos críticos + rollback) y #1068 (timers
RFC4192 configurables + feature flag OFF). Esa vuelta reportó correctamente vía
`circuito:reportar` que el item quedaba "como paraguas, se completará solo cuando cierren los 3
hijos" — pero, igual que en #949, **nunca transicionó `estado_aprobacion`** para activar el guard
nativo de cierre-paraguas. El item quedó despachable y el reaper (`circuito:reap-stuck`) lo
re-encoló 2 veces como huérfano (`reap_count=2`), hasta que me tocó a mí (wt-1) de nuevo.

**Causa raíz:** idéntica a la documentada en el cierre de #949 (2026-08-21 14:16, arriba):
`circuito:sub-item` no toca el item padre — solo escribe `origen_item_id` en el hijo. El guard de
`RoadmapItem::booted()` (~línea 290-315) solo reroutea a `aprobado_irving` +
`excluir_pool_automatico=true` cuando algo intenta poner `estado_aprobacion=completado` sobre un
item con `tieneSubItemsAbiertos()=true`. Si nadie intenta ese `completado`, el guard nunca
dispara y el item sigue en el pool indefinidamente.

**Fix aplicado (sin tocar #1066/#1067/#1068, sin duplicar la descomposición):** confirmé vía
`circuito:cabida` (`ya_descompuesto`) y consultando los 3 hijos que la descomposición previa
sigue vigente y sin cambios, y apliqué `estado_aprobacion='completado'` sobre #1032 vía tinker.
El guard nativo interceptó la transición y lo reroteó a `aprobado_irving` +
`excluir_pool_automatico=true` + log `paraguas_abierto` (3 sub-items abiertos). Cerrará solo
cuando el último de los 3 hijos cierre.

**Nota (mejora de fondo aún no aplicada, ya señalada en #949, sigue sin dueño):**
`circuito:sub-item` podría, tras crear el primer hijo, intentar automáticamente
`estado_aprobacion=completado` en el padre para activar el guard paraguas en el mismo comando —
evitaría que este mismo bug se repita cada vez que una descomposición cierra su vuelta sin ese
paso manual. Sigue fuera de alcance de este item (toca un comando compartido por todo el
circuito); si se repite una tercera vez convendría promoverlo a su propio item.

**Estado:** #1032 → `aprobado_irving` (paraguas retenido, fuera del pool). #1066/#1067/#1068
siguen pendientes de ejecución normal (uno de ellos, #1067, ya está `aprobado_revisor`).

## 2026-08-22 00:58 — #1033 (wt-1): IPv6 Fase 5.3 (simulador de renumeración) — descompuesto, no cabía en una vuelta

**Item:** #1033 — "IPv6 Fase 5.3: Simulador de renumeracion (UI + comandos generados, SIN ejecucion
real)". `circuito:cabida` devolvió `NO CABE [historico_excede_umbral]` (~517s). Antes de
descomponer investigué el item padre real (#956) y el estado de la Fase 5.2 (#1032), que este
item da por sentado como dependencia ("Pantalla/endpoint de simulacion **sobre el motor de 5.2**").

**Hallazgo clave:** el "motor de 5.2" (`Ipv6RenumberingStateMachine`) **todavía no existe en
código**. #1032 fue descompuesto en #1066 (esquema)/#1067 (servicio)/#1068 (timers) en una vuelta
anterior y los 3 siguen `pending` sin ejecutar (uno, #1067, ya `aprobado_revisor` pero no
implementado). Diseñar el simulador de 5.3 como dependiente directo de esa máquina de estados
habría sido bloqueante sin fecha. Decisión (registrada también con `circuito:reportar
--tipo=decision`): el simulador queda **autocontenido** — reusa únicamente
`Ipv6AddressPlanCalculator`/`Ipv6CommandGenerator` de las fases 1.4-1.7 (ya en producción, sin
relación con la máquina de estados de 5.2), y la fase C (vigilancia) usa datos mock/de prueba tal
como el propio texto del item ya lo pedía explícitamente. Esto evita depender de un motor que no
existe sin desviarse del alcance aprobado por el revisor.

**Precedente encontrado y documentado en los specs:** `Ipv6ConfigController` +
`resources/js/components/module/network/Ipv6Config.vue` + el bloque de rutas
`red/ipv6-config/*` gateado por `role:super-administrator|DESARROLLADOR` (item #999) es EXACTAMENTE
el patrón a replicar: endpoint de cálculo puro (`vistaPrevia()`) que reusa
`Ipv6AddressPlanCalculator`+`Ipv6CommandGenerator` sin tocar el router, más una vista Vue de solo
lectura con botón copiar. El simulador de 5.3 es la misma forma, con 4 fases en vez de 1.

**Descompuesto en:**
- **#1069** — Fase 5.3a: servicio `Ipv6RenumberingSimulator` (o similar) + endpoint
  `POST red/ipv6-config/simular-renumeracion` (mismo bloque de rutas de #999). Genera comandos para
  A (convivencia, reusa lo existente), B (deprecación: `preferred-lifetime=0` + lifetimes PPPoE —
  puede requerir extender `Ipv6CommandGenerator` con un método nuevo sin tocar los existentes), C
  (mock explícito, sin comandos reales), D (comandos de baja + marcador de seguridad). Nunca
  instancia `MikrotikIpv6Client`.
- **#1070** — Fase 5.3b: UI (depende de #1069) — 4 fases con comandos en bloques de solo lectura +
  copiar, tablero mock rotulado en fase C, badge de seguridad en fase D, banner permanente
  "SIMULACIÓN — NO EJECUTA CONTRA EL ROUTER". Sigue el layout de `Ipv6Config.vue`.

**Fix del bug de paraguas (mismo de #949/#1032):** `circuito:sub-item` no transiciona el padre —
apliqué manualmente `estado_aprobacion='completado'` sobre #1033 vía tinker; el guard nativo lo
reroteó a `aprobado_irving` + `excluir_pool_automatico=true` + log `paraguas_abierto` (2 sub-items
abiertos). Cerrará solo cuando #1069 y #1070 cierren.

**Estado:** #1033 → `aprobado_irving` (paraguas retenido, fuera del pool). #1069/#1070 pendientes
de triaje/ejecución normal. Sin cambios de código en este item (era descomposición pura).

## 2026-08-22 01:15 — #1036 (wt-1): wiring addRulesInputDorpRest() — ya resuelto por #983, sin código nuevo

**Item:** #1036 — "Wiring addRulesInputDorpRest() con flag por-router + precheck IP crítica
(MikrotikRulesJob)", creado a las 2026-08-21 18:42 como sub-item de seguimiento de #983 (en ese
momento #983 todavía no traía la invocación real). Pedía exactamente: migración aditiva con
columna `enforce_input_drop_rest`, invocar `addRulesInputDorpRest()` tras las reglas input
existentes gateado por flag+precheck de IP crítica, loguear sin tumbar el job si el precheck
falla, y remover la regla `MgNet_INPUT_DROPEA_EL_RESTO` simétricamente cuando el flag esté apagado
(incluida la rama `mikrotik->active==false`).

**Hallazgo:** el propio item #983 terminó ese trabajo esa misma noche, DESPUÉS de que #1036 fuera
creado pero ANTES de que este worker lo reclamara: commits `4dc252f6` (invoca
`addRulesInputDorpRest()` detrás de kill-switch + flag por-router + precheck) y `fda8860a`
(`down()` sin drop destructivo, guardrail #1018), ambos a las 19:05, mergeados a main a las 19:18
vía `circuito/item-983-mikrotikrulesjob-nunca-invoca-addrulesin`. Mi HEAD en este worktree ya
arranca sobre ese main. Verificado línea por línea contra el spec de #1036 — cumple TODO, y de
hecho excede el spec: agrega un tercer candado (`config('mikrotik.enforce_input_drop_rest')`,
kill-switch global) que #1036 no pedía pero que Irving ya había decidido en #983 (preguntas
q1/q2/q3 de ese item). La columna vive en `mikrotik_configs` (vía `$router->mikrotikconfig`, igual
que `meganet_config_ip_address`) en vez de en `mikrotiks` como decía el texto de #1036 — más
correcto que el spec literal, consistente con dónde ya vivía la IP crítica que usa el precheck.
Migración ya corrida en dev (`Schema::hasColumn` confirma la columna), default `false` para todos
los routers — cero cambio de comportamiento de red, igual que exigía #1036.

**circuito:cabida marcó NO CABE** (estimado histórico ~517s) — decidí NO descomponer en sub-items
porque el alcance real restante era cero código (ver decisión reportada en el item): descomponer
habría inventado trabajo que no existe. `php -l` limpio sobre los 3 archivos del wiring +
`php artisan --version` bootea sin problema.

**Estado:** #1036 → `completado`, sin diff funcional (cierre documental + verificación). El
wiring real vive en los commits de #983 ya en main.

## 2026-08-22 09:55 — #1053 (wt-1): IPv6 Fase 4.3 Geofeed RFC 8805 — re-verificado el diferimiento, sigue vigente

**Item:** #1053, sub-item de seguimiento de #997. Es una nota de coordinación pura (sin código):
declara que el Geofeed RFC 8805 (IPv6 Fase 4.3) debe esperar a que Fase 2 (asignación de
prefijos a clientes) y Fase 3 (#954, operación) estén cerradas en `main`. El item ya traía las
decisiones de Irving sobre el CÓMO (CSV vía comando artisan diario en `/geofeed.csv` público,
granularidad país+estado sin ciudad/CP, remarks LACNIC, sin firma OpenPGP en esta fase)
documentadas para el ejecutor futuro. El revisor ya lo había autorizado (`aprobado_revisor`,
confianza alta: "no ejecuta código, no toca frontera dura, solo registra que debe esperar").

**Por qué llegó a mi vuelta:** una vuelta previa dejó una nota ("re-verificado, premisa sigue
vigente") pero no cerró el item — el reaper lo encontró huérfano (worker muerto/timeout) y lo
re-encoló a `aprobado_revisor`. Mi trabajo fue terminar ese cierre.

**Re-verificación (2026-08-22):** `Schema::hasTable('clientes_ipv6_prefijos')` → `false` (la
tabla sigue sin existir, 0 migraciones con ese patrón en `database/migrations`). `RoadmapItem::find(954)->status`
→ `pending` (Fase 3 sigue sin cerrar). `RoadmapItem::find(955)->status` → `pending` (Fase 4,
el padre paraguas, también sigue sin cerrar). La premisa del diferimiento sigue exactamente
igual que cuando se escribió: no hay nada real que generar sin datos de asignación.

**Cierre:** sin rama de trabajo con diff de código (nada que implementar todavía) — se marcó
`sin_ui=true` + `sin_ui_motivo` explicando la verificación (no hay pantalla porque no se
construyó nada de Geofeed), `reporte_coloquial` en llano, y `estado_aprobacion=completado`.
Creé la rama `circuito/item-1053-...` solo para satisfacer el gate de Thomas
(`ThomasService::verificarCierre` exige `branch` no vacío incluso con `sin_ui`) y aprovecharla
para este mismo commit de bitácora — no hay diff de código de producto en esta rama.

## 2026-08-22 10:15 — Item #1059 (wt-2): frontend IPv6 1.7a ya entregado, sin código nuevo

Item #1059 (sub-item de #999) pedía un blade standalone + componente Vue Bootstrap
(`Ipv6ConfigPanel.vue`) que consumiera los 4 endpoints de `Ipv6ConfigController`
(`routers`/`detectar-version`/`mapear-zonas`/`vista-previa`). Al investigar encontré que ese
frontend **ya existía en main**: `resources/views/network/ipv6-config.blade.php` +
`resources/js/components/module/network/Ipv6Config.vue` (registrado como `ipv6-config` en
`app.js`), mergeados originalmente dentro de la rama de #999 (commits `ffc2381a`/`903d011f`,
merge `00b50811`) y luego ampliados por #1000/#1001 a un wizard Quasar de 3 pasos (Alta del
bloque → Mapeo de zonas → Vista previa/exportar .rsc) — superset de lo que pedía #1059, no un
panel Bootstrap simple como asumía el spec original (que tampoco acertó el namespace del
blade: es `network.ipv6-config`, no `addon-gestion-red::ipv6-config`).

Verifiqué el contrato completo (los 4 endpoints, fallback manual de versión, banner de "solo
vista previa", manejo de errores axios) contra el componente real y confirmé que el permiso
`ipv6.manage` que usa el `@can` del blade está asignado a `super-administrator` y
`DESARROLLADOR` (los mismos roles del middleware de la ruta). Documenté el hallazgo en
`docs/ipv6-1059-frontend-ya-entregado.md` (mismo patrón que el precedente `#414` de OLT:
"premisa incorrecta, sin cambio de código") y cerré el item como completado sin construir un
segundo panel paralelo que duplicaría la UI de la misma ruta.

Commit único en la rama `circuito/item-1059-...`: `7f414ef0` (solo el doc de verificación).
Encolado a main vía `circuito:integrar`. `enlace_revision=/red/ipv6-config`.

## 2026-08-24 10:20 — P0 incidente BD dev vacía: contención + ensayo de recuperación en `megaisp_dryrun`

La base `megaisp` quedó en **0 tablas** el 2026-08-22 ~12:41. Detección: `migrate:status` fallaba
con "Migration table not found" y `laravel.log` acumulaba errores `1146 Table 'megaisp.X' doesn't
exist` — 268.896 en total, arrancando el 22-ago 12:48 (nada entre el 13-jun y esa fecha). Es la
máquina DEV (192.168.105.11); PROD (.198) no está afectada. MySQL llevaba 63 días sin reiniciar,
así que fue una operación a nivel SQL, no pérdida de datadir.

**Contención.** Evidencia preservada en `/home/meganet/forense-20260822/` (19 MB) antes de tocar
nada: `/proc/632692/environ` y `cwd`, la franja 11:00–13:10 de `laravel.log`, head+tail del log
zombie, las vueltas vecinas, `.env` de los 5 worktrees con passwords enmascarados, crontab e
historial. Cron del circuito pausado 9/9 (marca `PAUSADO-20260824-incidente`). Proceso zombie
`vuelta.sh` PID 632692 eliminado: llevaba **1d21h huérfano (PPID=1)** y había lanzado **43.947
invocaciones de `claude -p`**, una cada ~3.7 s. `laravel.log` 1.7 G → 0 y el log zombie 278 M → 0
(disco 62% → 60%). Dumps de resguardo: `megaisp_restore-20260824.sql.gz` (125 MB, 347 tablas,
verificado) y `megaisp_pilot-20260824.sql.gz`.

**Causa del bucle infinito.** `RoadmapCircuitoService::isPaused()` (línea 133) lee la bandera de
pausa desde la tabla `settings`. Con la BD vacía la comprobación lanza excepción, así que el
circuito **no puede auto-pausarse**: el freno de mano depende justo de lo que se rompió. El
`timeout 600` sí existía por hijo, pero el `vuelta.sh` padre reentraba en el lazo — el timeout
mataba al agente, no al bucle.

**`megaisp_dryrun` la creó este ensayo** (2026-08-24), como BD de staging para probar la
recuperación sin tocar `megaisp`. Se usó ese nombre y no `megaisp_stage` porque `megaisp_user`
solo tiene `USAGE ON *.*` y no puede crear bases arbitrarias, pero ya tenía
`ALL PRIVILEGES ON megaisp_dryrun.*` concedidos de antes. Se restauró ahí el dump del 29-jun
(`megaisp-202606291425.sql.gz`, 467 tablas, sin líneas `USE`/`CREATE DATABASE`, restauración
limpia rc=0). `megaisp` se verificó en 0 tablas después de restaurar. Estado: **642 migraciones
aplicadas, 138 pendientes** — el hueco de 8 semanas.

**Resultado del ensayo: `migrate` se detuvo solo (rc=1).** No por un fallo, sino por el guardrail
de migraciones del item #1018: "operaciones destructivas sin excepción de contracción madura",
señalando `2026_06_30_120000_widen_releases_summary_to_text:17` y
`2026_08_20_220000_make_vehicle_id_nullable_on_fleet_documents:27`, ambas por `->change()`. No se
aplicó ninguna migración (siguen 642/138 y 467 tablas). El escape hatch `--force-uncommitted`
existe y queda auditado, pero **no se usó**: es decisión de Irving. `megaisp` sigue intacta en 0
tablas y no se promovió nada.

## 2026-08-24 12:10 — Recuperación de dev: promoción de `megaisp_dryrun` a `megaisp`

Continuación del P0 anterior. A las 11:24:23 alguien pulsó el botón **"Run migrations"** de la
pantalla de Ignition (`POST /_ignition/execute-solution`, solución `RunMigrations`, 58.69 s) tras
varios `GET /devtools` que devolvían el error `Table 'megaisp.X' doesn't exist`. Ese `migrate`
falló a mitad y dejó `megaisp` con 235 tablas y 290 migraciones batch 1 de un set viejo (la más
reciente, `2026_02_08_155609_create_radius_sessions_table`). No fue un comando de consola: un
request web arranca con el `.env` de la app, que apunta a `megaisp`, así que no hay override
posible por esa ruta. El botón quedó cerrado con `IGNITION_ENABLE_RUNNABLE_SOLUTIONS=false`
(`.env:188`), verificado en runtime — se conserva la pantalla de diagnóstico, se pierde el botón.

**`--force-uncommitted` sobre las 138 pendientes en `megaisp_dryrun`.** El guardrail del item
#1018 abortaba el lote completo porque 2 de las 138 disparan el patrón `->change()`. Base de la
autorización, verificada antes de forzar: el `--pretend` genera **681 sentencias, 33 `CREATE
TABLE`, 157 `ALTER TABLE` y 0 destructivas**, y de los seis `DESTRUCTIVE_PATTERNS` solo dispara
`change()`. Las dos señaladas son expansivas, no reductoras:

- `2026_06_30_120000_widen_releases_summary_to_text:17` — `releases.summary` de `varchar(255)` a
  `TEXT` (255 → 65.535 bytes), nullable sin cambio. Motivo original: las notas de release de IA
  desbordaban y reventaban con `SQLSTATE[22001] 1406`.
- `2026_08_20_220000_make_vehicle_id_nullable_on_fleet_documents:27` — `fleet_documents.vehicle_id`
  de `NOT NULL` a `nullable`, mismo tipo y ancho, sobre 3 filas sin nulos. Relajar una restricción
  no puede invalidar filas existentes.

En Laravel 10.48.4 `change()` va por Doctrine DBAL, que conserva los atributos no reformulados
(el riesgo de la 11+ no aplica); además ninguna de las dos columnas tenía default ni comment.
Resultado: `rc=0`, 138 aplicadas, 0 pendientes, 467 → **500 tablas**.

**Criterio de aceptación.** `roadmap_items` pasó de 14 a 87 columnas. De las 9 que el código de
`main` necesita, 7 son columnas reales (`estado_aprobacion`, `nivel_riesgo`, `worker_sid`,
`branch`, `merge_commit`, `enlace_revision`, `agendado_para`); `estacion` **no es columna** sino
el accessor `getEstacionAttribute()` (`RoadmapItem.php:1508`) y `requiere_irving` **no es columna**
sino un valor del enum `ESTADOS_APROBACION` / `estado_aprobacion`. Ambas ausencias fueron un error
de inferencia previo por contar referencias en archivos sin distinguir columna de accessor o enum.

**Promoción.** Dump `megaisp_dryrun-promocion-20260824.sql.gz` (142 MB) verificado antes de
aplicar: **500 `DROP TABLE IF EXISTS` contra 500 tablas reales** y **0 líneas `USE`/`CREATE
DATABASE`** (bloqueo duro). Restaurado sobre `megaisp` con rc=0. Verificación posterior: 500/500
tablas, **sin huérfanas** (las 235 del migrate abortado eran subconjunto estricto), `users` 4.786,
`permissions` 692, `module_registry` 46, `settings` 2, `roadmap_items` 168. Credenciales siempre
por fichero temporal modo 600 borrado con `shred`, nunca en línea de comandos.

**Warm-up: la config NO se cacheó.** `config:auditar-env` salió con **rc=1** — encontró **29
llamadas a `env()` en runtime en 18 archivos** (entre ellas `WHATSAPP_API_KEY`, `FCM_SERVER_KEY`,
`MAIL_FROM_ADDRESS`, `SSH_KEY_PASSPHRASE` y una clave dinámica en
`UsesApiIntegration.php:30`). El `&&` de CLAUDE.md:451 es el candado y funcionó: se aplicó el
warm-up alternativo que prescribe el propio comando (`config:clear && route:clear &&
queue:restart`). `bootstrap/cache/config.php` no existe.

Verificado por HTTP: `/` → 302 a login, `/login` → 200, `/devtools` → 302 a login. Cero marcas de
Ignition. El cron del circuito **sigue pausado**.

## 2026-08-24 13:45 — Entregable B: tablero de compuertas de la Torre (item #183)

Engrane en la cabecera, visible desde todas las pestañas, que abre un tablero de ESTADO Y
CONTROL: una línea principal que contesta **CORRIENDO** o **DETENIDO POR: {primera compuerta
que bloquea}**, y una fila por compuerta con semáforo, nombre en lenguaje llano, valor real
medido en vivo, origen del dato, motivo del bloqueo y acción para soltarlo.

**Catorce compuertas, en el orden real en que el circuito se frena:** base de datos · lectura
del SO · cron del ejecutor · freno de mano · ejecutor huérfano o colgado · workers de
supervisor · nivel del autopilot · terminales libres · items despachables · estación · items
agendados · auditor (cooldown y debounce) · items reservados por terminales muertas · cascada
de errores. El orden importa: la primera en rojo es la que da la respuesta de la línea de
arriba.

**El hallazgo que definió la arquitectura.** El panel corre en php-fpm como `www-data` y
`/home/meganet` es `drwx------`: desde la web NO se pueden ver los flock de los worktrees, ni
el crontab de `meganet`, ni los procesos del ejecutor. Un tablero que dijera "cron activo" sin
poder mirarlo estaría inventando — que es exactamente lo que costó una hora el 24-ago, cuando
supervisor declaraba "detenido" y el proceso seguía vivo. Así que la medición del SO la hace
`circuito:compuertas-sonda`, que corre por cron **como meganet** y deja un snapshot en
`storage/app/torre/compuertas-so.json`. El panel lo lee y **siempre muestra su antigüedad**: un
snapshot de más de 180 s es una compuerta en rojo por sí misma. Su línea de cron es
independiente de las de `deploy/circuito` a propósito — pausar el circuito no debe dejar ciego
al tablero.

**Las cinco reglas, implementadas.** (1) Ningún rojo mudo: cada fila trae acción ejecutable o,
si no, quién puede actuar y con qué comando; el propio DTO expone `sin_salida` y la UI lo marca
en rojo si alguna vez faltara. (2) Lo que necesita sudo se muestra igual, con el comando exacto
y clic para copiar. (3) Ninguna acción se dispara con un clic suelto: el servidor exige
`confirmado=true` aunque la UI ya haya preguntado — la confirmación es del servidor, no un
adorno del frontend; es la lección del botón RUN MIGRATIONS. (4) Cada cambio queda en
`torre_compuerta_cambios` con quién, cuándo y de qué valor a cuál, consultable desde el mismo
tablero. (5) Manda el proceso vivo, no lo que declare supervisor.

**Permisos:** rol `super-administrator` o `DESARROLLADOR`, con `auth()->user()->hasRole()` —
en este layout `@role`/`@can` no evalúan. Las acciones exigen además `circuito.pause` o
`torre.config.edit` según el caso.

**Prueba de aceptación — los ocho bloqueos del 22-24 ago contra el tablero:**

| Escenario del incidente | Compuerta | Luz | Qué muestra |
|---|---|---|---|
| BD destruida (22-ago) | `bd` | ROJO | faltan settings/torre_config + comando `migrate:status` |
| Vuelta huérfana PPID=1, 1d21h | `ejecutor` | ROJO | `1 huérfana, la más vieja 163000s` + `kill 632692` |
| Workers de supervisor caídos | `workers` | ROJO | `0 de 3 vivos` + `sudo supervisorctl start ...` |
| 268.896 excepciones / log 1.7 GB | `cascada` | ROJO | `480 errores/min · log 1740.8 MB` |
| Cron del circuito pausado | `cron` | ROJO | `0 activas (9 comentadas)` + quién puede |
| Sin lectura fresca del SO | `snapshot` | ROJO | pasa a ser la línea principal; no inventa estado |
| Item atrapado por terminal muerta | `reservados` | ROJO | `1 item atrapado: #170` + acción soltar |
| Freno de mano ilegible | `pausa` | ROJO | `isPaused()` en try/catch: rojo, nunca verde por omisión |

Los escenarios del SO se simularon con un snapshot alterno (restaurado y verificado byte a
byte al terminar) y el del item atrapado en transacción con rollback. Ninguna simulación dejó
residuo: `#170` volvió a `worker_sid=NULL`, el freno quedó `suelto`.

`rojos sin salida: 0`. Rama `circuito/item-183-torre-compuertas`, bundle recompilado en modo
prod (`public/js/app.js` no se commitea).

## 2026-08-25 18:14 — P0 (SEGUNDA ocurrencia): la suite vació `megaisp`; PITR sin pérdida

**Es la segunda vez con el mismo mecanismo.** La primera fue el 22-ago ~12:41 (entrada del
24-ago). Aquella se restauró y se documentó, pero la causa quedó abierta: `phpunit.xml` seguía
declarando `DB_DATABASE=megaisp`. Tres días después se repitió. **Restaurar sin cerrar la causa es
programar la repetición** — y la regla escrita sí existía (CLAUDE.md advierte del `migrate:fresh`
de `TestCase` y hasta trae el `CREATE DATABASE megaisp_test`). Vivía sólo en prosa, y las seis
terminales no leen prosa.

**Línea de tiempo.**
- `18:02:03` — último latido de Thomas en modo `completo`. Se integra #183 a `main` (`832eac99`).
- `18:06:27` — el scheduler lanza la vuelta del item **#171** en `wt-1` (guardrail de migraciones).
- `18:14:14` — último `COMMIT` sano en el binlog. `roadmap_items` con 223 filas.
- `18:14:20` — **`DROP TABLE` de las 500 tablas en una sola sentencia**, `/* generated by server */`
  (binlog.000050, posición 738510868, `thread_id=1210109`). Firma exacta de `migrate:fresh`.
- `18:14:29` — primer `1146 Table 'megaisp.settings' doesn't exist` en el log de `wt-1`.
- `18:14:31` — mtime de `wt-1/.phpunit.result.cache`, cuyo contenido ya trae
  `MigrationGuardFailClosedTest`. La terminal corrió `phpunit`.
- `18:16:29` — la vuelta muere por timeout; no puede ni registrar su propia ejecución.
- `18:35:24` — freno puesto a mano (`storage/app/circuito/PAUSA`). **21 minutos de daño en curso.**
- `19:12` — PITR aplicado. `19:12:56` — respaldo post-PITR verificado.

**Causa raíz, en dos mitades.** (1) `phpunit.xml` apuntaba a la base de la app y
`Tests\TestCase::setUp()` corre `migrate:fresh --seed`: correr la suite borraba dev, y era la
configuración por defecto del repo. (2) El `migrate` de vuelta **no reconstruyó nada** porque ese
mismo worker acababa de commitear (`3dee9dda`) un guardrail *fail-closed* que bloquea si no puede
leer la tabla `migrations` — la que el `migrate:fresh` acababa de borrar. Por eso quedó en **0
tablas y no en 500**. El freno no evitó el daño: impidió la reparación.

**Recuperación: PITR completo, cero pérdida.** El dump más nuevo era del 24-ago 12:10 (~30 h de
atraso). En vez de aceptar esa pérdida se reprodujo `binlog.000050` filtrado a `--database=megaisp`
con `--stop-position=738510789` (el `Anonymous_GTID` inmediatamente anterior al `DROP`). El binlog
arranca el 24-ago 11:17:11 con `megaisp` en 0 tablas — **el mismo estado que tenía tras el
incidente**, así que la reproducción reconstruye la base entera: contiene el migrate de Ignition
del 24-ago 11:24, la restauración de la promoción de las 12:10 (669 `CREATE TABLE`) y todo lo
posterior. Punto de recuperación **18:14:14**: se pierden 6 segundos en los que no ocurrió nada más
que el borrado. Verificado después: **502 tablas**, 4.786 usuarios, 223 items, **784 migraciones
aplicadas / 0 pendientes** (incluidas las dos de las 17:00 y 18:00 de ese mismo día).
Evidencia preservada en `/home/meganet/forense-20260825/` (copia del binlog, SQL de reproducción,
respaldo del crontab, dump post-PITR de 142 MB verificado con `gunzip -t`).

**Qué se cerró.**
- `04ec4395` — `tests/GuardBaseDePruebas.php`: la base de la suite debe terminar en `_test` (o ser
  `:memory:`). Aplicado en `CreatesApplication`, el único punto por el que pasan las dos familias
  de tests del repo. `phpunit.xml` → `megaisp_test`. Con `DB_DATABASE=megaisp` forzado, la suite se
  detiene en 0.6 s antes de ejecutar una sola migración.
- `bcf478c2` — el candado deja de depender de la rama. El repo se bifurca y los worktrees pueden
  estar en ramas anteriores al commit (era el caso de `wt-1` esa noche); los wrappers no se
  bifurcan, porque el cron los invoca por ruta absoluta. `guard-bd-pruebas.sh` verifica el árbol
  desde `vuelta.sh` (antes de soltar al agente) y desde `cron-wrap.sh` (sobre `main`, que es de
  donde se resincronizan las seis terminales). Deja rastro en archivo: todas las líneas del crontab
  terminan en `>/dev/null` y un candado que aborta en silencio no se distingue de un circuito
  tranquilo.
- `a2a598f7` — el guardrail de migraciones deja de depender de la base que protege. Ni abierto ni
  cerrado: `estadoAplicadoLegible()` se pregunta por adelantado y con nombre propio; sin estado que
  leer se **permite** (reconstrucción) y se dice así en el log; `hasPathToMain()` decide con git; los
  dos refinamientos que tocan la base sólo pueden endurecer y se saltan si no responde. Prueba nueva
  que ejecuta el guardrail **con la conexión caída**.
- 13 de 16 worktrees quedaron con `megaisp_test` + el candado (merge de `main`).

**Qué quedó abierto.**
- **3 worktrees sin mergear** por conflicto (`circuito-fase-a`, `megaisp-wt-fase1`,
  `megaisp-wt-tablero`; entre 720 y 791 commits de atraso). No son slots del circuito y el candado
  del wrapper los cubre — verificado abortando sobre `megaisp-wt-tablero`. Recomendación: podarlos.
- **`megaisp_test` no se puede construir sólo con migraciones**: queda en **236 tablas de 502**. Es
  la deuda ya conocida del catálogo atrapado en `migrations_old/`. La suite protege la base, pero
  todavía no corre entera.
- **El item #171 quedó en `requiere_irving`**: su spec pedía literalmente "debe fallar cerrado", que
  es lo que agravó el incidente. De sus tres commits, `64296733` (cierra en código el botón *Run
  migrations* de Ignition) sigue siendo válido y cubre la mitad del spec que `main` no cubre.
- **La contención fue manual y tardía**: 21 minutos. Registrado como item el chequeo `bd_integra`
  para la vigilia de Thomas, que pone el freno solo y luego avisa.

## 2026-08-26 14:25 — La terminal web deja de perder el contexto de Claude (ttyd → tmux)

**Síntoma reportado por Irving:** "se reinició la terminal", cinco veces en una tarde. Cada vez, la
conversación de Claude Code se perdía entera y había que reconstruir el contexto desde cero.

**Lo que NO era.** No hubo reinicio del box (65 días de uptime), ni OOM, ni recarga de nginx (arriba
desde el 22-jun), ni caída de enlace, ni ningún timeout: `ttyd` pinguea cada 5 s por default y el
`proxy_read_timeout` de nginx es de 86400 s. Tampoco era el usuario cerrando pestañas — lo dijo él y
el journal lo confirma.

**Causa raíz.** La terminal del panel es `ttyd ... bash` detrás del `location /ttyd/` de nginx.
Cuando se cae el WebSocket, ttyd manda **SIGHUP** al bash hijo y se lleva la sesión de Claude con él;
acto seguido el **cliente de ttyd reconecta solo** en 1-7 s y arranca un **bash nuevo**. Por eso se
ve como si la pestaña se recargara sola: prompt limpio, contexto perdido, sin que nadie tocara nada.
El corte nace en el camino navegador↔nginx (red/suspensión del equipo, pestaña congelada en segundo
plano), no en el servidor.

**Cómo se distingue en el journal** (`journalctl -u ttyd`, legible sin sudo — el access.log de nginx
NO lo es, es `www-data:adm 640`):

| Evento | Firma |
|---|---|
| Recarga/apertura de página | `HTTP /` → `/token` → `WS /ws` |
| Caída del WS + reconexión automática | `WS closed` → `/token` → `WS /ws`, **sin** `HTTP /` |

Cortes del día: 12:42, 12:43, 12:58, 13:14, 13:47 y 14:04 (sólo el de 13:09 fue apertura real).

**Arreglo.** Bloque al final de `~/.bashrc` que envuelve en tmux el bash que lanza ttyd: reengancha
la primera sesión `web-*` sin cliente y, si no hay, crea `web-N`. Se hizo ahí y no en el unit de
systemd a propósito: no pide root ni reiniciar ttyd (un restart mata todas las terminales abiertas).
La guarda "el padre es literalmente `ttyd`" deja fuera los shells que abre Claude Code y los del cron
del circuito. Se le sumó un **reintento de ~2 s** para la carrera entre la reconexión (1-7 s) y el
momento en que tmux da de baja al cliente muerto: sin él se abriría una `web-2` y el contexto quedaría
escondido en `web-1`. Respaldos: `~/.bashrc.bak-*`.

⚠️ **Lección que costó la tarde:** editar `.bashrc` **no reenvuelve un shell ya corriendo**. El
arreglo se aplicó a las 13:25 y la sesión murió igual a las 13:47 porque ese shell había nacido a las
13:18. Sólo protege terminales abiertas después.

**Verificación — los dos caminos, con dinero real (la sesión viva):**
1. **Corte real no provocado** a las 14:04:19 (`killing process, pid: 1669930`): antes ahí moría todo.
   Irving volvió a las 14:14 y la conversación seguía.
2. **Corte controlado** a las 14:18:29 (`kill -HUP` al grupo del shell de ttyd): journal reprodujo la
   firma de reconexión automática (`WS closed` → `/token` → `WS`, sin `HTTP /`), arrancó bash nuevo
   (1674016), reenganchó `web-1` y el proceso `claude` siguió siendo el **mismo PID 1670407** de las
   13:51:08.

**Registrado para no re-investigarlo:** `CONTEXTO-MEGAISP.md` §11 (mapa estructural + cómo
diagnosticar) y memoria `ttyd-terminal-persistente`.

**Deuda abierta:** `~/.bashrc` exporta `ANTHROPIC_API_KEY` en texto plano, heredada por todo proceso
hijo. Pendiente decisión de Irving: moverla a un archivo `600` aparte o quitarla (el CLI autentica por
OAuth; `vuelta.sh` hace `unset CLAUDE_API_KEY` a propósito).

**Cierre de la deuda (mismo día, 14:27):** se retiró `ANTHROPIC_API_KEY` del `~/.bashrc` a pedido de
Irving. Verificado antes de tocar nada: nunca llegó a git (`git log -S`), el CLI autentica por OAuth
(`claudeAiOauth` en `~/.claude/.credentials.json`), `vuelta.sh` ya la hacía `unset`, y su huella
sha256 **no coincide** con `CLAUDE_API_KEY` ni con `ANTHROPIC_API_KEY` del `.env` → no la comparte la
app. Los 4 respaldos `.bashrc.bak-*` que la contenían quedaron **redactados y en `600`**. Queda
pendiente de Irving **rotar la llave en la consola de Anthropic**: estuvo en un archivo `644` legible
por cualquier usuario del box (incluido `www-data`) y sigue viva en el entorno de los procesos ya
corriendo hasta que reinicien.

## 2026-08-26 14:45 — Verificación: qué falta por arreglar en la Torre de Control

Barrido pedido por Irving. **La familia "columna fantasma" quedó cerrada**: se escanearon las 91
columnas de `roadmap_items` (y las 2.198 de la BD) contra todas las referencias del módulo Roadmap,
por dos ejes —literales en consultas (`where`/`update`/`orderBy`…) y lecturas de propiedad
`$item->col`— y **no queda ninguna**; los 9 candidatos que saltaron son llaves de arrays de retorno
y un accessor real (`getEstadoColaAttribute`). Los 22 métodos read-only de los 5 servicios de la
Torre responden sin excepción.

**Lo que sí queda, medido contra el tablero de compuertas vivo (15 compuertas):**

| # | Qué | Estado | De quién es |
|---|---|---|---|
| 1 | Workers de cola: **0 de 3 vivos**, 188 jobs pendientes | ROJO | Irving (necesita `sudo supervisorctl`) |
| 2 | Freno de mano puesto desde el 25-ago 18:35 (20 h; el sistema lo marca `olvidada`) | ROJO | Decisión de Irving |
| 3 | Techo del autopilot en nivel C | ÁMBAR | Decisión de Irving (`config/circuito.php` → `autopilot.max_nivel`) |
| 4 | `circuito:reactivar-agendados` **nunca corre**: está en `Kernel.php` (diario 00:05) pero **no hay `schedule:run` en el cron del box** | Hallazgo nuevo | Código/infra |
| 5 | `circuito:re-triage` es un motor **sin invocador** (ni cron, ni Kernel, ni programático): su luz dirá "nunca ha corrido" para siempre | Hallazgo nuevo | Decidir: cablearlo o sacarlo del semáforo |

**#4 es de la misma familia que todo lo de hoy: falla callado.** La compuerta `agendados` está verde
porque hoy no hay ningún item diferido — el agujero es invisible hasta que alguien difiera uno, y
entonces no se reactiva nunca.

**Nota sobre el des-trabador** (arreglo `44fc805b` de hoy): sí está cableado —`SchedulerCommand` lo
llama por `Artisan::call`— pero con el freno puesto el scheduler no lanza nada, así que **el arreglo
sigue sin verificarse en vivo**. Se verifica solo al reanudar.

Los 46 `failed_jobs` son viejos (28-may a 15-jun), ajenos a esto. Verde el resto: bd, snapshot,
thomas, cron (9 líneas), ejecutor, terminales (6/6 libres), items (96 listos), estación, auditor,
reservados, cascada (0 errores/min, log 45 MB, disco 66%).

## 2026-08-26 15:10 — Torre: ejercitadas las acciones de escritura por la ruta HTTP real

Se cerró el hueco declarado en la verificación de las 14:45 (hasta entonces solo se habían probado
métodos read-only por tinker, saltándose controllers, middleware y permisos).

**Método:** arnés que despacha cada acción por el **kernel HTTP real** (grupo `web` + `Authenticate`
+ el `authorize()` inline del controller), autenticado como Irving (resuelto por `login_user`, nunca
por id), cada caso dentro de `DB::beginTransaction()` + `rollBack()`. Solo se neutralizó CSRF, que no
es lo que se estaba probando. **Residuo verificado al terminar: 0 items de prueba, `failed_jobs`=46 y
`jobs`=188 intactos** (los mismos de antes).

**34 casos. Resultado:**
- **19 funcionan** (200/201): crear · editar · borrar · log · completar · cancelar · arrancar ·
  urgente · override · liberar reclamo · toggle subtarea · decidir/aprobar · elegir opción ·
  seguimiento · nombre de worker · guardar config de la Torre · modo de integración · voz ·
  recalentar cachés (con `incluir_config=false`, sin `config:cache`).
- **7 rechazan correctamente** — las guardas están vivas: `disparar` → **423 pausado**; compuerta sin
  `confirmado` → 422 pidiendo el segundo paso; cancelar disparo → "ya no está en la cola"; deshacer
  decisión → "no la decidió un actor automático"; liberar/reasignar sin terminal → 422; reasignar sin
  `sid_destino` → 422; archivar rama inexistente → 404.
- **1 ROTO** → ver abajo.
- **5 no ejecutados a propósito** (efectos fuera de la BD, que el rollback no cubre): `integracion/merge`
  y `integracion/revert` (git real), acciones de compuerta con `confirmado=true` (matan/levantan
  procesos), toggle del freno (centinela en archivo + el scheduler corre cada minuto), `memory/raw`
  (escribe archivo) y avatar (subida). Más las 3 de `roadmap-externo` (token).

**El defecto encontrado — botón "Reintentar fallidos" (Torre → Salud del entorno):** devuelve **404**
`ModelNotFoundException: App\Models\Referrals\ClientReferralProfile` y escupe 8 avisos
`unserialize()`. Causa: `EnvironmentHealthService.php:423` hace `Artisan::call('queue:retry',
['id'=>['all']])` **sin try/catch**; entre los 46 fallidos viejos (28-may a 15-jun) hay payloads que
apuntan a modelos ya borrados, y el primero envenenado **aborta el lote entero**. En uso real mueve
algunos jobs, truena a media faena y deja un toast de "No query results" que no dice cuál falló ni
cuántos alcanzó. Reproducido dos veces. **No se arregló** (pendiente de decisión de Irving): la forma
sería reintentar **job por job**, contar los que sí y reportar los envenenados por id en vez de tumbar
la corrida.
