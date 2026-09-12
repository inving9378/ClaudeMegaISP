# CIRC-02a Fase 1 — Datos crudos: items requiere_irving con comentario humano posterior

Generado: 2026-09-11 (item #9990872, worker wt-2). Diagnóstico SOLO LECTURA — no se tocó BD ni código fuera de este archivo.

Universo: `\App\Modules\Addons\Roadmap\Models\RoadmapItem::whereNotNull('log')->get()` → **1573** items con log no vacío.

## Método (reproducible)

Snippet de tinker usado: `/tmp/circ02a_fase1.php` (ejecutado vía `php artisan tinker < archivo.php`), reproducido íntegro en la sección final de este documento.

1. Para cada item, se localiza la **última** entrada del log con `estado === 'requiere_irving'` (la escalada más reciente).
2. Se buscan entradas de actor **humano** (según taxonomía) con `ts` posterior a esa escalada.
3. Si existe al menos una → candidato. Se toma la **última** (más reciente) como "el comentario humano" de referencia.
4. Se revisa si, después de ese comentario, hay evidencia de avance (`merge_a_main`, o una entrada con `estado` distinto de `requiere_irving`, o el `estado_aprobacion` actual ya no es `requiere_irving`):
   - **Grupo (a) AVANZARON**: sí hay evidencia de avance.
   - **Grupo (b) SIGUEN PARADOS**: `estado_aprobacion` actual sigue siendo `requiere_irving` y no hay evidencia de avance tras el comentario.

## Auditoría de la taxonomía de actores (frecuencia real en TODOS los logs)

La taxonomía del item cubre los actores más frecuentes, pero el grep real encontró **79** actores distintos (la lista dada no es 100% exhaustiva — hay variantes nuevas). Clasificación aplicada:

- **humano/exacto**: coincide literal con la lista humana dada.
- **humano/extension**: no está en la lista literal, pero seguro por patrón (`irving:NOMBRE` no listado, o `claude-code (...)` que menciona irving/instruccion/dictado).
- **automatico/exacto**: coincide literal con la lista automática dada.
- **automatico/extension**: no está literal, pero mismo namespace de subsistema (`circuito:*`, `wt-N`, `jarvis:*`, `thomas:*`, etc.) o variantes obvias (`jarvis`, `thomas`, `barrido`, `reaper`, `claude-code:sesion`).
- **ambiguo**: no se pudo clasificar con confianza — **se excluyó del conteo de "comentario humano"** (conservador, para no inflar falsos positivos). Requiere que Fase 2 (o Irving) lo revise.

| Actor | Apariciones | Bucket | Vía |
|---|---:|---|---|
| `jarvis-mecanico` | 2650 | automatico | exacto |
| `merge-runner` | 1586 | automatico | exacto |
| `irving:admin` | 844 | humano | exacto |
| `paraguas` | 808 | automatico | exacto |
| `thomas-mecanico` | 774 | automatico | exacto |
| `limite_cuenta` | 717 | automatico | exacto |
| `consola:tinker` | 539 | automatico | exacto |
| `reaper-rapido` | 510 | automatico | exacto |
| `valvula:contexto` | 376 | automatico | exacto |
| `revisor:backlog` | 374 | automatico | exacto |
| `soltar-claim` | 364 | automatico | exacto |
| `jarvis:verificarCierre` | 357 | automatico | exacto |
| `thomas:verificarCierre` | 349 | automatico | exacto |
| `timeout` | 244 | automatico | exacto |
| `wt-1` | 235 | automatico | extension |
| `wt-2` | 229 | automatico | extension |
| `claude-code` | 143 | automatico | exacto |
| `wt-3` | 142 | automatico | extension |
| `timeout:reanudado` | 137 | automatico | exacto |
| `jarvis-ya-decidido` | 136 | automatico | exacto |
| `irving:CARLOS` | 125 | humano | exacto |
| `colision-check` | 112 | automatico | exacto |
| `consola:circuito:scheduler` | 111 | automatico | extension |
| `consola:circuito:integrar` | 107 | automatico | extension |
| `irving:Irving` | 107 | humano | exacto |
| `david:relay-irving` | 102 | humano | exacto |
| `destrabe(opus)` | 88 | automatico | exacto |
| `wt-4` | 88 | automatico | extension |
| `wt-6` | 66 | automatico | extension |
| `wt-5` | 64 | automatico | extension |
| `irving:david_marsal` | 64 | humano | exacto |
| `autopilot` | 51 | automatico | exacto |
| `auditor` | 46 | automatico | exacto |
| `circuito:wt-1` | 45 | automatico | extension |
| `consola:circuito:priorizar-seguridad` | 42 | automatico | extension |
| `circuito:reactivar-agendados` | 25 | automatico | extension |
| `jarvis:generarSeguimientoPreguntas` | 24 | automatico | extension |
| `circuito:auditar-huerfanos` | 24 | automatico | extension |
| `jarvis` | 24 | automatico | extension |
| `consola:circuito:parquear-timeout` | 22 | automatico | extension |
| `consola:circuito:consultar` | 21 | automatico | extension |
| `revisor:triaje-null` | 20 | automatico | exacto |
| `consola:circuito:reap-stuck` | 18 | automatico | extension |
| `valvula:nacimiento` | 18 | automatico | exacto |
| `claude-code:auditoria-191` | 15 | automatico | extension |
| `circuito:despacho` | 13 | automatico | extension |
| `barrido` | 12 | automatico | extension |
| `reaper` | 11 | automatico | extension |
| `claude-code (doc CIRC v2 de Irving 2026-09-12)` | 10 | ambiguo | extension |
| `cadena-rh` | 6 | ambiguo | sin_clasificar |
| `circuito:liberar-cascada-mapa-red` | 6 | automatico | extension |
| `irving:pedido-directo` | 5 | humano | exacto |
| `irving:admin (via claude-code)` | 4 | humano | exacto |
| `consola:circuito:liberar-cascada-mapa-red` | 4 | automatico | extension |
| `claude-code:sesion` | 4 | automatico | extension |
| `david_marsal` | 4 | humano | exacto |
| `Claude Code (sesión versionador)` | 4 | ambiguo | extension |
| `saneamiento-9990855` | 4 | ambiguo | sin_clasificar |
| `circuito:re-triaje(9990861)` | 3 | automatico | extension |
| `claude-code (por instruccion de irving:admin)` | 3 | humano | exacto |
| `consola:artisan` | 3 | automatico | extension |
| `claude-code (por instruccion explicita de irving:admin)` | 3 | humano | exacto |
| `claude-code (sesion supervisada)` | 2 | ambiguo | extension |
| `consola:circuito:disparo-check` | 1 | automatico | extension |
| `irving:re-sello` | 1 | humano | exacto |
| `claude-code (sesion supervisada, por instruccion de irving:admin)` | 1 | humano | exacto |
| `wt-5 (ejecutor)` | 1 | automatico | extension |
| `wt-2 (ejecutor)` | 1 | automatico | extension |
| `irving:admin (dictado a claude-code)` | 1 | humano | exacto |
| `thomas:generarSeguimientoPreguntas` | 1 | automatico | extension |
| `thomas` | 1 | automatico | extension |
| `consola:queue:work` | 1 | automatico | extension |
| `irving:merge` | 1 | humano | exacto |
| `consola:circuito:merge-run` | 1 | automatico | extension |
| `wt-1:claude-code` | 1 | automatico | extension |
| `consola:9990218` | 1 | automatico | extension |
| `wt-1:fix-bug-9990408` | 1 | automatico | extension |
| `circuito:wt-5` | 1 | automatico | extension |
| `Claude Code (sesión VoIP)` | 1 | ambiguo | extension |

### ⚠️ Actores AMBIGUOS (excluidos del conteo humano, pendientes de revisión)

- `claude-code (doc CIRC v2 de Irving 2026-09-12)` (10 apariciones)
- `cadena-rh` (6 apariciones)
- `Claude Code (sesión versionador)` (4 apariciones)
- `saneamiento-9990855` (4 apariciones)
- `claude-code (sesion supervisada)` (2 apariciones)
- `Claude Code (sesión VoIP)` (1 apariciones)

## Resultado: candidatos con comentario humano posterior a `requiere_irving`

Total candidatos: **191** — Grupo (a) avanzaron: **191** — Grupo (b) siguen parados: **0**

### Grupo (a) — AVANZARON (comentario humano + evidencia de avance posterior)

| Item | Estado actual | Comentario humano (ts / por) | Evidencia de avance |
|---|---|---|---|
| #21 Construir infra de relacion Padre-Hijo en backend (modelo, asociacion, permisos) | completado | 2026-08-28T18:28:54-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #47 Primera publicación real Marketing Fase 5 — campaña multivariante | completado | 2026-08-28T15:10:51-06:00 / `irving:admin` | estado=aprobado_irving en idx 3 |
| #67 Desplegar y validar CobranzaBlaster en produccion (Servnet + llamada real) | completado | 2026-08-28T15:09:14-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #72 Greenfield: configurar Firebase desde cero + integrar FCM HTTP v1 en MegaFamilia | aprobado_irving | 2026-08-28T16:18:13-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #78 Configurar SPF/DKIM para dominio meganett.com.mx — mejorar entregabilidad email | completado | 2026-08-28T15:11:42-06:00 / `irving:admin` | estado=aprobado_irving en idx 4 |
| #81 Limpieza: identificar y limpiar documentos CRM huérfanos (sin file asociado) | completado | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #86 Refactor: sincronización Mikrotik con eventual consistency (sync_status + queue + dashboard) | completado | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #93 Flotas — Documentos de conductor (driver_id en fleet_documents) | completado | 2026-08-28T16:15:59-06:00 / `irving:admin` | estado=aprobado_irving en idx 9 |
| #99 Fase 6.2 Flotas — Gancho markBilled() → InvoiceService (item polimórfico en invoice_items) | completado | 2026-08-28T18:29:00-06:00 / `irving:admin` | estado=aprobado_irving en idx 7 |
| #100 Fase 6.3 Flotas — Sección "Mi Plan" en APK MegaFamilia (cliente ve trial/active/próxima factura) | completado | 2026-08-28T16:16:33-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #103 Celular conductor como tracker GPS en APK (UI + permisos ubicación) | completado | 2026-09-04T10:44:52-06:00 / `irving:admin` | estado=completado en idx 8 |
| #105 Backfill masivo users rows huérfanos (~879 clientes sin row) — ejecutar en producción | completado | 2026-09-08T11:37:40-06:00 / `david:relay-irving` | merge_a_main en idx 13 |
| #106 Verificar/corregir crontab schedule:run en servidor productivo (path correcto) | completado | 2026-08-28T15:11:08-06:00 / `irving:admin` | estado=en_progreso en idx 5 |
| #117 Integración PAC para CFDI 4.0 (factura fiscal) | aprobado_irving | 2026-09-08T13:06:41-06:00 / `david:relay-irving` | estado=aprobado_irving en idx 10 |
| #126 en el modulo de administracion administradores y permisos , al dar click en el candado la pantalla se queda en gris y en clientes cuando quiero aplicar pago tambien se queda en gris | completado | 2026-09-03T21:29:13-06:00 / `irving:admin` | estado=aprobado_revisor en idx 12 |
| #142 OpenPay producción (certificación + llaves + sandbox=false) | completado | 2026-08-28T16:13:20-06:00 / `irving:admin` | estado=aprobado_irving en idx 6 |
| #144 Portal: subdominio + SSL (portal.meganet.mx) | completado | 2026-08-28T15:11:23-06:00 / `irving:admin` | estado=aprobado_revisor en idx 3 |
| #145 Permisos /var/backups/mysql en PROD a 750 www-data | completado | 2026-08-28T18:29:24-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #149 Portal: cobro/tarifas premium MegaFamilia | aprobado_irving | 2026-08-28T19:22:11-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #153 Admin: migración base64 → bcrypt (users.password) | completado | 2026-08-28T15:13:23-06:00 / `irving:admin` | estado=aprobado_irving en idx 4 |
| #155 client_main_information.user: normalizar formato de número de cliente (padding 004981 vs 4981) | aprobado_irving | 2026-08-28T18:29:34-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #157 Producción .198: SESSION_SECURE_COOKIE=true en el .env de produccion (HTTPS) | completado | 2026-08-28T15:12:54-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #162 Portal de Pago: fijar APP_URL al dominio público en .env de producción | completado | 2026-08-28T15:11:33-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #163 Portal de Pago: activar cron pagos:enviar-recurrentes en .198 | completado | 2026-09-04T10:44:48-06:00 / `irving:admin` | estado=completado en idx 10 |
| #176 Alerta de errores en cascada: 268.896 excepciones en dos dias sin deteccion | completado | 2026-08-28T15:09:26-06:00 / `irving:admin` | estado=aprobado_irving en idx 3 |
| #182 Torre de Control: listado vacío en Hoja de ruta y panel de ajustes bajo engrane | completado | 2026-08-28T16:16:40-06:00 / `irving:admin` | estado=completado en idx 12 |
| #185 Más 8tems | aprobado_irving | 2026-09-08T11:37:40-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #190 Más items | aprobado_irving | 2026-08-28T18:29:08-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #195 La compuerta de reservas no ve reclamos huerfanos sin worker_sid | completado | 2026-08-28T16:18:29-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #196 cEstaciones cuenta mal por select corto: 11 items A/B se pintan sin triar | completado | 2026-08-28T16:17:03-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #200 Expediente RH — Hijo B: motor de plantillas con variables y versionado | completado | 2026-08-28T16:15:40-06:00 / `irving:admin` | merge_a_main en idx 12 |
| #202 Expediente RH — Hijo D: paquetes de documentos por puesto y generacion automatica al alta | completado | 2026-09-03T10:24:00-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 17 |
| #203 Expediente RH — Hijo E: pestana Documentos en perfil colaborador y vendedor | aprobado_irving | 2026-09-05T15:58:44-06:00 / `irving:admin` | estado=aprobado_irving en idx 13 |
| #208 Vigilante on-box: que los atascos se avisen solos en vez de buscarlos a mano | completado | 2026-08-28T18:28:15-06:00 / `irving:admin` | estado=aprobado_irving en idx 15 |
| #210 El latido sobrevive al proceso que lo lanzo y renueva el lease de un item abandonado | completado | 2026-08-28T15:09:53-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #211 El pool continuo no admite una sola vuelta: CIRCUITO_ITEM significa "empieza por este", no "solo este" | completado | 2026-08-28T15:09:56-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #212 INANICION: los items sin footprint nunca se despachan si hay cualquier cosa en vuelo — 5 urgentes inalcanzables | completado | 2026-08-28T15:10:02-06:00 / `irving:admin` | estado=aprobado_revisor en idx 9 |
| #213 El reporte de la descomposicion se pierde: la bitacora se commitea en HEAD desatado y la iteracion siguiente la borra | completado | 2026-08-28T16:18:09-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #216 Deriva de esquema en dev: medir cuantas columnas existen solo porque alguien las agrego a mano, sin migracion que las respalde | completado | 2026-08-28T19:21:56-06:00 / `irving:admin` | estado=en_progreso en idx 14 |
| #226 [INFRA] Renovar el certificado de dev.meganett.com.mx con hook DNS-01 automatizado | aprobado_irving | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #230 megaisp_test no se puede construir sólo con migraciones: queda en 236 tablas de 502 | aprobado_irving | 2026-08-28T19:22:02-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #232 Falta la migración del motor auditor: roadmap_items no tiene tipo, hallazgo_firma ni auditoria_ciclo | completado | 2026-09-03T20:09:59-06:00 / `irving:Irving` | estado=aprobado_irving en idx 3 |
| #277 Clientes: cerrar 2 marcador(es) TODO/FIXME en ClientController.php | completado | 2026-09-08T10:58:43-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #279 Auto-merge de Thomas puede cerrar un item con una rama vieja que Irving nunca revisó | completado | 2026-08-28T21:39:37-06:00 / `irving:admin` | estado=aprobado_irving en idx 7 |
| #281 OLT Huawei — cerrar escritura real en laboratorio (alta/baja/suspensión de ONU, Fase A+B) | aprobado_irving | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #283 Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL? confirmar acceso a hardware piloto | aprobado_irving | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #624 Reconstruir items del incidente P0 que NO llegaron a mergear (pendientes/rechazados/abandonados) | completado | 2026-08-28T21:40:07-06:00 / `irving:admin` | estado=aprobado_revisor en idx 10 |
| #625 MegaFamilia/Portal: flujo OTP real de login (hoy solo existe el toggle sin flujo) | aprobado_irving | 2026-08-28T19:22:07-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #629 Ramas huerfanas Pieza C (descomposicion+watchdog+DependenciaGate, jul-11) — evaluar rescate del gap real | aprobado_irving | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #630 Rama huerfana C2 (revert-on-reject, jul-13) — automatizar el revert real al rechazar un item ya integrado | completado | 2026-08-28T16:15:20-06:00 / `irving:admin` | merge_a_main en idx 8 |
| #633 Cola 'default' sin worker en DEV: 579 jobs atorados (2+ días) — bloquea clasificación de items nuevos del Roadmap | completado | 2026-08-28T15:14:20-06:00 / `irving:admin` | merge_a_main en idx 4 |
| #635 Portal Cliente: ejecutar migración a bcrypt (Opción B) — decisión de diseño | aprobado_irving | 2026-08-28T19:22:14-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #639 Vista Hijo APK: reemplazar mocks de Logros/Apps permitidas/Tiempo de pantalla por datos reales | aprobado_irving | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #641 Workers de cola caídos: 0 procesos activos, 606 jobs pendientes (el más viejo desde 2026-05-27) | completado | 2026-08-28T15:16:18-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #642 circuito:re-triage sin invocador: los items bloqueados por anti-bucle (freno humano) nunca se resurfacean solos | completado | 2026-08-29T07:42:42-06:00 / `irving:admin` | merge_a_main en idx 11 |
| #646 Válvula de contexto: instrumentarla, y que ablande la frontera dura en vez de apagarla | completado | 2026-08-28T16:13:09-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #647 Barrido de comandos y servicios registrados que nada invoca: motores terminados y apagados | completado | 2026-08-28T15:10:45-06:00 / `irving:admin` | estado=aprobado_revisor en idx 3 |
| #657 Barrido de comandos y servicios genéricos de negocio (Active/Scripts/Olts) que nada invoca | completado | 2026-08-29T13:41:10-06:00 / `irving:admin` | estado=aprobado_irving en idx 11 |
| #663 DocumentaciónCorporativa — Fase 1: apartados con datos vivos (IV, X, VII, V parcial) | completado | 2026-08-28T18:31:08-06:00 / `irving:admin` | estado=aprobado_irving en idx 4 |
| #664 DocumentaciónCorporativa — Fase 2: repositorio documental, bandeja de captura y plantillas | completado | 2026-08-28T21:39:27-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #665 DocumentaciónCorporativa — Fase 3: inventarios de activos físicos, digitales y accesos | completado | 2026-08-28T18:31:12-06:00 / `irving:admin` | estado=aprobado_irving en idx 4 |
| #679 Ola escalonada: convertir las 4043 cuentas no-privilegiadas con password legacy base64 | aprobado_irving | 2026-08-28T21:40:41-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #680 Retirar el fallback legacy de PasswordService::check() una vez agotada la ola no-privilegiada | aprobado_irving | 2026-08-28T21:40:49-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #683 PAC CFDI 4.0 — integrar Facturama real (bloqueado por credenciales de Irving) | aprobado_irving | 2026-08-29T07:45:14-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #687 Flotas — Asistente conversacional sobre la flota | aprobado_irving | 2026-09-04T07:43:27-06:00 / `irving:CARLOS` | estado_aprobacion actual ya no es requiere_irving |
| #691 Implementar cálculo real de cuota Medussa por transacción conciliada (BLOQUEADO sin autorización) | aprobado_irving | 2026-08-28T21:42:00-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #693 Talento comisión-KPI — Fase C: catálogo de reglas (Irving debe fijar roles/KPIs/fórmula/piloto antes de código) | completado | 2026-09-08T10:58:43-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #705 Vigilante on-box (#208) — invariantes de Cola y discrepancias Sistema/Supervisor | completado | 2026-08-29T13:40:17-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #713 Thomas Parte 3 — Sugerencias proactivas en el chat (patrones, deuda, capacidades casi existentes) | completado | 2026-08-29T07:44:20-06:00 / `irving:admin` | estado=aprobado_irving en idx 4 |
| #718 Decidir alcance/plataforma de tiempo de pantalla real (Vista Hijo) y ejecutar cuando exista worktree para megafamilia-rn | aprobado_irving | 2026-08-28T22:34:49-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #722 Unificación invoices/client_invoices — Fase 3: backfill histórico a invoices | completado | 2026-08-28T21:41:07-06:00 / `irving:admin` | estado=aprobado_revisor en idx 5 |
| #725 Unificación invoices/client_invoices — Fase 6: cortar escritura primaria a invoices (5 subsistemas) | aprobado_irving | 2026-09-04T07:42:30-06:00 / `irving:CARLOS` | estado_aprobacion actual ya no es requiere_irving |
| #726 Unificación invoices/client_invoices — Fase 7: decommission client_invoices | aprobado_irving | 2026-08-29T13:40:20-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #732 Fase 1.5 — Exportación por apartado a PDF/Excel con bitácora + gate documento.download para detalle nominal de cartera | completado | 2026-08-29T13:40:12-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #738 Deriva de esquema #216 — Fase 1: esquema de referencia desde migraciones en BD desechable | completado | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #739 Deriva de esquema #216 — Fase 2: diff esquema vivo (megaisp) vs referencia de migraciones | completado | 2026-08-29T07:44:12-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #740 Deriva de esquema #216 — Fase 3: consumidores + entregable final + cierre | completado | 2026-08-31T17:09:42-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #749 Unificación invoices/client_invoices — Fase 3b: ejecutar el backfill real + reporte de reconciliación para aprobación de Irving | completado | 2026-08-29T07:42:19-06:00 / `irving:admin` | merge_a_main en idx 4 |
| #750 Doc. Corporativa Fase 3.1 — tablas y modelos de dc_activos, dc_activos_digitales y dc_inventario_accesos | completado | 2026-08-29T13:40:26-06:00 / `irving:admin` | estado=aprobado_irving en idx 8 |
| #753 Seguimiento: pregunta sin resolver de #741 | completado | 2026-09-03T20:15:58-06:00 / `irving:admin` | merge_a_main en idx 20 |
| #754 Fase 4 (real): migrar KpiController/HomeController/AuditController a leer invoices — bloqueado hasta #719/#721/#722 en main | aprobado_irving | 2026-08-29T13:40:31-06:00 / `irving:admin` | estado=aprobado_irving en idx 6 |
| #765 Pieza 1b — Captura en vivo del evento de apertura de frontera dura + invocador real | completado | 2026-08-29T07:43:57-06:00 / `irving:admin` | merge_a_main en idx 3 |
| #778 P0 no-mergeados auditoria — lote A (24 ids: 186-866) | completado | 2026-08-29T07:44:46-06:00 / `irving:admin` | estado=aprobado_revisor en idx 5 |
| #781 P0 no-mergeados auditoria — lote D (24 ids: 1019-1078) | completado | 2026-08-29T07:44:52-06:00 / `irving:admin` | estado=aprobado_revisor en idx 5 |
| #790 Barrido de comandos en app/Console/Commands/Scripts/ (~51) que nada invoca | completado | 2026-08-31T17:09:47-06:00 / `irving:admin` | estado=aprobado_irving en idx 4 |
| #798 Deriva #216 Fase 1b: exportar el esquema de megaisp_dryrun a storage/schema/reference.sql (comando schema:build-reference) | completado | 2026-09-08T11:37:40-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #806 Jarvis Parte 3b — Decidir dónde vive 'el chat' de las sugerencias (brief de Irving) y cablearlo | completado | 2026-08-31T17:09:56-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #816 DocumentaciónCorporativa Fase 5d-2 — ampliar checklist offboarding con ítems sin tabla propia (correo, VPN, WhatsApp, equipo, respaldo, finiquito RH) | completado | 2026-09-01T13:03:38-06:00 / `irving:admin` | estado=aprobado_irving en idx 6 |
| #818 Fase 1a-ii: schema:rebuild-dryrun — correr TODAS las migraciones vía Migrator + reporte de tiempo/fallos | completado | 2026-09-01T13:04:12-06:00 / `irving:admin` | merge_a_main en idx 11 |
| #819 Verificar e integrar schema:build-reference (Fase 1 #216, código ya implementado) | completado | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #829 Re-analizar consumidores+desde-cuándo del diff #216 tras el rebuild limpio de megaisp_dryrun (#817/#818) | completado | 2026-09-02T16:16:37-06:00 / `irving:admin` | merge_a_main en idx 15 |
| #832 Correr schema:build-reference en dev, verificar reference.sql e integrar #819 | completado | 2026-09-08T11:37:40-06:00 / `david:relay-irving` | merge_a_main en idx 21 |
| #833 Fase 1a-ii parte 3/N: resolver colisión failed_jobs (aislado de #831) y continuar el ciclo fix-drift por el siguiente lote | completado | 2026-09-04T07:43:20-06:00 / `irving:CARLOS` | estado_aprobacion actual ya no es requiere_irving |
| #841 Auditoría de permisos (nivel A, solo lectura) | completado | 2026-09-01T14:11:54-06:00 / `irving:admin` | estado=aprobado_irving en idx 6 |
| #842 Item 2 — Catálogo completo de permisos por módulo (nivel B) | aprobado_irving | 2026-09-08T15:31:57-06:00 / `irving:david_marsal` | estado_aprobacion actual ya no es requiere_irving |
| #843 Item 3 — Enforcement real en el portal (nivel C, decisiones ya resueltas) | completado | 2026-09-03T20:18:27-06:00 / `irving:admin` | merge_a_main en idx 14 |
| #844 Ejecutar schema:build-reference en dev, commitear reference.sql y cerrar #819 | completado | 2026-09-01T16:29:50-06:00 / `irving:admin` | estado=aprobado_irving en idx 15 |
| #848 Fase 2 - Menu de una sola fuente de verdad: ocultar modulo completo si 0 entradas visibles | completado | 2026-09-01T15:38:15-06:00 / `irving:admin` | estado=aprobado_irving en idx 7 |
| #852 Item 2 Fase A — fix columna description + comando permissions:sync-roles + registrar los 34 módulos que YA declaran permisos | completado | 2026-09-01T15:38:22-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #871 Expediente RH — Hijo D2: generación automática de documentos al alta del colaborador | completado | 2026-09-03T15:44:45-06:00 / `irving:CARLOS` | estado=aprobado_revisor en idx 27 |
| #876 Torre 24/7 · Pieza 4 — auto-corregir seguridad EN CÓDIGO sin preguntar, dejando permisos/credenciales/dinero en la bandeja | cancelado | 2026-09-03T11:32:20-06:00 / `irving:CARLOS` | estado=cancelado en idx 4 |
| #880 Torre 24/7 · Pieza 4 — auto-corregir seguridad EN CÓDIGO sin preguntar, dejando permisos/credenciales/dinero en la bandeja | completado | 2026-09-03T12:29:34-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 4 |
| #881 Torre 24/7 · cerrar en cascada los 9 paraguas cuyos hijos ya cerraron | completado | 2026-09-03T13:10:10-06:00 / `irving:CARLOS` | merge_a_main en idx 7 |
| #883 Torre 24/7 · revisar los 58 esperando_merge_irving: separar mergeables legítimos de retenidos con motivo real | completado | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #892 Changelog de release trunca en silencio: con 571 commits en rango sólo mira los 40 más recientes y lo presenta como completo | completado | 2026-09-03T12:28:54-06:00 / `irving:CARLOS` | merge_a_main en idx 7 |
| #897 Liberar en bulk los reclamos worker_sid huérfanos de items paraguas parqueados | completado | 2026-09-03T14:12:07-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #924 Root-cause: paraguas cierre-en-cascada dejó pasar un nivel-C sin merge a 'completado' (item #32) | aprobado_irving | 2026-09-08T11:09:54-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #937 MR-01 — Mapas: auditoría READ-ONLY del modelo actual (Paso 0 del rediseño) | completado | 2026-09-03T15:44:25-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 5 |
| #939 MR-03 — Crear el módulo MapaRed (esqueleto, registro, sidebar, permisos) | completado | 2026-09-03T21:36:21-06:00 / `irving:admin` | estado=aprobado_revisor en idx 6 |
| #944 MR-08 — Catálogos: tipos de cable, tipos de splitter, tipos de caja, conectores | aprobado_irving | 2026-09-07T09:05:31-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #952 MR-16 — Grafo de red y trazo extremo a extremo OLT → ONT | completado | 2026-09-07T07:26:00-06:00 / `irving:Irving` | estado=aprobado_irving en idx 5 |
| #953 MR-17 — Trazo inverso de impacto (clientes y MRR afectados) | completado | 2026-09-07T15:17:10-06:00 / `irving:admin` | estado=aprobado_irving en idx 6 |
| #955 MR-19 — Carta de empalme por caja (vista + PDF) | completado | 2026-09-07T15:17:02-06:00 / `irving:admin` | estado=aprobado_irving en idx 6 |
| #958 MR-22 — Nueva navegación: buscador, filtros por capa, clustering, render por zoom | aprobado_irving | 2026-09-07T06:59:49-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 4 |
| #962 MR-26 — Cobertura comercial derivada de la infraestructura | completado | 2026-09-07T08:48:32-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 4 |
| #984 Circuito: 29 llamada(s) a env() en tiempo de ejecución fuera de config/ | completado | 2026-09-03T16:07:47-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 4 |
| #988 circuito:verificar-vuelta {modulo} — motor de detección (php -l + boot + tests del módulo + dry-run migraciones) | completado | 2026-09-03T18:41:59-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #989 circuito:verificar-vuelta — capa de acción: revertir la rama de la vuelta o escalar via circuito:consultar cuando falla | completado | 2026-09-05T15:58:48-06:00 / `irving:admin` | merge_a_main en idx 111 |
| #9990008 Torre 24/7 Pieza 4 — Fase 3+4 (retomar): activar carril AUTO en priorizar-seguridad + candado de regresión — bloqueado por #918 sin mergear | completado | 2026-09-03T19:30:25-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #9990012 Reproducir en dev la carrera del cierre-en-cascada de #32 y confirmar el mecanismo exacto que esquivó el guard (1) | completado | 2026-09-03T19:37:44-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #9990013 Endurecer el punto exacto que dejó pasar a #32 sin merge (según causa confirmada en el sub-item de reproducción) + test de regresión | aprobado_irving | 2026-09-04T07:43:35-06:00 / `irving:CARLOS` | estado_aprobacion actual ya no es requiere_irving |
| #9990063 Repro con transacción+rollback de la carrera del cascade sobre #32: confirmar mecanismo exacto que esquivó guard(1) | completado | 2026-09-04T05:54:33-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 10 |
| #9990085 Frontend: vista admin CRM 'Documentos huérfanos' (tabla Quasar + export CSV) — decisión oficial de Irving q3 | cancelado | 2026-09-09T05:25:03-06:00 / `david:relay-irving` | estado=cancelado en idx 13 |
| #9990099 Fase 2 - Repro con 2 procesos PHP concurrentes del cascade sobre #32 (columna omitida del UPDATE por no-dirty) | completado | 2026-09-04T05:55:33-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 7 |
| #9990207 Fase 2 de #923 — pantalla de administración del catálogo de puestos | completado | 2026-09-04T07:44:30-06:00 / `irving:CARLOS` | merge_a_main en idx 3 |
| #9990210 Que los hallazgos del barrido se arreglen solos: una MENCIÓN de frontera dura deja de retener, salvo dinero y credenciales | completado | 2026-09-04T12:43:43-06:00 / `irving:Irving` | estado_aprobacion actual ya no es requiere_irving |
| #9990228 Cablear DependenciaGate al scheduler vivo + circuito:sub-item --depende-de | aprobado_irving | 2026-09-04T08:47:23-06:00 / `irving:admin` | estado=aprobado_irving en idx 6 |
| #9990254 Wrappers de circuito:scheduler que no mueren: 13 procesos vivos y merges que se quedan encolados | completado | 2026-09-04T14:14:50-06:00 / `irving:Irving` | estado=completado en idx 10 |
| #9990265 Embeber <crm-orphan-documents> en la vista de Documentos huérfanos CRM (tras merge de #9990244) | completado | 2026-09-04T10:48:08-06:00 / `irving:admin` | estado=aprobado_irving en idx 33 |
| #9990270 Fase 1c-ii — Verificación manual en dev de --depende-de + limpieza de datos de prueba | completado | 2026-09-04T12:03:04-06:00 / `irving:CARLOS` | estado=aprobado_revisor en idx 14 |
| #9990328 MR-06a — Backend: portar los 6 controllers Geo (grupo vivo) a MapaRed apuntando a mapared_* | completado | 2026-09-04T10:31:04-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 18 |
| #9990331 Torre: reloj/techo por terminal debe reflejar el timeout por-nivel_riesgo (no el uniforme 600s) | completado | 2026-09-04T10:47:09-06:00 / `irving:admin` | estado=aprobado_irving en idx 13 |
| #9990334 MR-06a-2 — Portar ConnectionsController + DevicesController a MapaRed | completado | 2026-09-04T10:44:23-06:00 / `irving:admin` | estado=aprobado_irving en idx 10 |
| #9990335 MR-06a-3 — Portar KMZController a MapaRed | completado | 2026-09-04T18:10:32-06:00 / `irving:Irving` | merge_a_main en idx 46 |
| #9990336 MR-06a-4 — Portar LayersController a MapaRed (el más grande, 21 métodos) | completado | 2026-09-04T10:44:33-06:00 / `irving:admin` | estado=aprobado_irving en idx 12 |
| #9990337 MR-06a-5 — Portar ProyectsController + ServiceBoxController a MapaRed | completado | 2026-09-04T10:48:04-06:00 / `irving:admin` | estado=aprobado_irving en idx 13 |
| #9990338 Torre: fix backend del techo real por nivel_riesgo (EstimadorTiempo + buildSesion) | completado | 2026-09-04T10:44:44-06:00 / `irving:admin` | estado=aprobado_irving en idx 12 |
| #9990340 Seguimiento: pregunta sin resolver de #269 | completado | 2026-09-04T10:53:48-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 10 |
| #9990366 Mapa de Red: quitar el techo de la cascada y construir la épica COMPLETA de forma autónoma | completado | 2026-09-05T16:28:32-06:00 / `irving:admin` | estado=aprobado_revisor en idx 3 |
| #9990408 MR-12 UI: panel de unión de hilos (doble clic en Rack/Mufa/NAP) | completado | 2026-09-07T09:58:50-06:00 / `irving:admin` | estado=aprobado_irving en idx 8 |
| #9990423 MR-21 frontend — colorear NAP por salud (D17) + dashboard de ONUs al hacer clic | completado | 2026-09-07T08:47:35-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 8 |
| #9990439 MR-24e — Modo dibujo en el mapa: alta de NAP en 3 pasos + cable con snap a extremos | completado | 2026-09-07T15:16:53-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #9990444 MR-25 Fase 3b — Importador CSV con mapeo de campos | completado | 2026-09-07T06:59:18-06:00 / `irving:CARLOS` | estado=aprobado_revisor en idx 3 |
| #9990445 MR-25 Fase 4 — Exportadores KML/GeoJSON/carta de empalme PDF/BOM XLSX (D21) | aprobado_irving | 2026-09-07T15:16:55-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #9990446 [RESPUESTA] MR-27 — Ratificar: no retirar Mapas, activar MR-30 (MR-05 nunca corrió) | completado | 2026-09-07T06:58:23-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 3 |
| #9990450 Talento: catálogo de artículos de vendedor (reusa selleritems de Vendedores) | completado | 2026-09-07T08:47:17-06:00 / `irving:CARLOS` | estado=aprobado_irving en idx 3 |
| #9990451 MR-23 fase 4a — acción 'Trazar' (dibujar y guardar enlace entre 2 nodos) | aprobado_irving | 2026-09-07T09:58:53-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #9990452 Migrar el motor de comisiones y pagos de vendedor a Talento — EJECUCIÓN (continúa #123) | aprobado_irving | 2026-09-08T10:36:32-06:00 / `irving:david_marsal` | estado_aprobacion actual ya no es requiere_irving |
| #9990454 MR-23 fase 4b — acción 'Ver impacto' (clientes/nodos afectados aguas abajo) | aprobado_irving | 2026-09-07T15:16:25-06:00 / `irving:admin` | estado=aprobado_irving en idx 7 |
| #9990455 MR-23 fase 4c — sección 'Fotos' por nodo/enlace del Mapa de Red | aprobado_irving | 2026-09-07T22:34:25-06:00 / `irving:admin` | estado=aprobado_irving en idx 21 |
| #9990456 MR-23 fase 4d — sección 'Historial de cambios' por nodo/enlace del Mapa de Red | aprobado_irving | 2026-09-09T05:20:27-06:00 / `david:relay-irving` | estado=aprobado_irving en idx 14 |
| #9990457 MR-22 Fase 1 — Buscador global del mapa (nombre/cliente/serie ONT/dirección) | aprobado_irving | 2026-09-07T08:48:03-06:00 / `irving:CARLOS` | estado=aprobado_revisor en idx 4 |
| #9990458 MR-22 Fase 2 — Panel de capas encendibles + render dependiente de zoom | completado | 2026-09-07T08:45:23-06:00 / `irving:CARLOS` | estado=aprobado_revisor en idx 4 |
| #9990459 MR-22 Fase 3 — Árbol derivado de máx 3 niveles como panel lateral colapsable (D22) | completado | 2026-09-07T08:46:07-06:00 / `irving:CARLOS` | estado=aprobado_revisor en idx 5 |
| #9990472 Vendedores: vendedor "Guadalupe" duplicado (sellers 12 y 48) — consolidar/limpiar | completado | 2026-09-09T07:30:11-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #9990474 Recuperación de contraseña rota: falta la vista auth.passwords.email (500) | completado | 2026-09-08T10:44:30-06:00 / `david:relay-irving` | estado=aprobado_irving en idx 17 |
| #9990478 Estrategia de datos de dev: clasificar las 223 tablas vacías (importar / sembrar / muertas / en construcción) | completado | 2026-09-08T10:41:38-06:00 / `david:relay-irving` | estado=aprobado_irving en idx 16 |
| #9990491 Rebuild del bundle no es atómico: pantallas dan "no se puede pintar" mientras se recompila | aprobado_irving | 2026-09-08T11:09:55-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #9990494 MR-22 Fase 2c — Capas de Drops y Cobertura: no existen hoy como capas geográficas (requiere decisión de diseño antes de UI) | aprobado_irving | 2026-09-08T11:09:55-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #9990504 MR-08 Fase 2 — CRUD backend + permisos mapa_red_catalogo_* de los 4 catálogos | aprobado_irving | 2026-09-07T15:15:05-06:00 / `irving:admin` | estado=aprobado_irving en idx 8 |
| #9990505 MR-08 Fase 3 — UI de administración de los 4 catálogos | aprobado_irving | 2026-09-07T15:15:09-06:00 / `irving:admin` | estado=aprobado_irving en idx 7 |
| #9990508 MR-22 Fase 3 (retoma) - Implementar arbol derivado 3 niveles en panel lateral, tras liberar colision con MR-22 Fase 2 | completado | 2026-09-07T09:59:02-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #9990509 MR-22 Fase 1a — Endpoint backend de búsqueda global del mapa | aprobado_irving | 2026-09-08T11:37:40-06:00 / `david:relay-irving` | estado_aprobacion actual ya no es requiere_irving |
| #9990510 MR-22 Fase 1b — Componente frontend del buscador global del mapa | completado | 2026-09-07T15:16:29-06:00 / `irving:admin` | estado=aprobado_irving en idx 6 |
| #9990511 Agregar link '¿Olvidó su contraseña?' en login.blade.php (ejecución) | completado | 2026-09-07T15:16:16-06:00 / `irving:admin` | merge_a_main en idx 5 |
| #9990523 MR-26 Fase 2 — endpoint de consulta de cobertura por coordenada + NAP más cercana | completado | 2026-09-07T22:33:11-06:00 / `irving:admin` | merge_a_main en idx 8 |
| #9990549 MR-24e Fase 3 — Modo dibujo: cable/troncal con snap a extremos (frontend) | completado | 2026-09-07T18:39:59-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #9990554 MR-17 Fase 3 — Endpoint + boton Quien depende de esto + panel de impacto + verificar DoD | completado | 2026-09-07T22:33:54-06:00 / `irving:admin` | estado=aprobado_irving en idx 5 |
| #9990571 Build atómico — aplicar el mismo patrón al deploy de PROD (RemoteDeployCommand npm_build) [FRONTERA DURA — requiere Irving] | aprobado_irving | 2026-09-07T22:33:16-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #9990640 Merge-runner corre en /var/www/megaisp y sus merges ignoran la pausa (Fase D.3) | completado | 2026-09-09T05:18:36-06:00 / `irving:david_marsal` | estado=aprobado_revisor en idx 4 |
| #9990650 Talento: placeholders de firma en plantillas + colocacion en el render (empresa/trabajador) | completado | 2026-09-09T08:34:39-06:00 / `irving:david_marsal` | estado=aprobado_irving en idx 4 |
| #9990676 F5 — Eje del auditor «versiones sin publicar» + reconciliación diaria | completado | 2026-09-10T10:58:11-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #9990713 Empaquetar Asterisk como .deb propio y montar el repositorio APT de Meganet | aprobado_irving | 2026-09-10T16:21:15-06:00 / `irving:admin` | estado=aprobado_irving en idx 9 |
| #9990718 Provisionador del módulo VoIP: que la actualización deje Asterisk instalado y funcionando | aprobado_irving | 2026-09-10T15:33:52-06:00 / `irving:admin` | estado=aprobado_revisor en idx 8 |
| #9990720 tests/TestCase.php corre migrate:fresh dos veces y rompe toda prueba que use base | completado | 2026-09-10T15:34:00-06:00 / `irving:admin` | merge_a_main en idx 5 |
| #9990721 permisos | aprobado_irving | 2026-09-10T18:01:59-06:00 / `irving:admin` | estado=aprobado_irving en idx 7 |
| #9990730 Implementar auditoría periódica + gate en el cierre para items completado sin merge (decisión de #9990719) | aprobado_irving | 2026-09-10T18:01:18-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #9990733 Mapa de Red — flujo animado de puntos en los enlaces OLT | aprobado_irving | 2026-09-10T18:02:20-06:00 / `irving:admin` | estado=en_progreso en idx 3 |
| #9990741 MR flujo animado Fase 3 — guardas de rendimiento + boton congelar animacion | completado | 2026-09-10T20:17:10-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #9990745 Fase 4 — inventario y matriz completa de permisos (obligatorio, en paralelo) | aprobado_irving | 2026-09-11T05:51:40-06:00 / `irving:david_marsal` | estado=aprobado_revisor en idx 5 |
| #9990764 Fase 4b — Rutas/acciones sin protección de permiso = agujero espejo (punto 3) | completado | 2026-09-11T10:55:39-06:00 / `irving:admin` | estado=aprobado_revisor en idx 5 |
| #9990780 Motor de custodia de prospectos: 7 días, evidencia, 3 renovaciones, pool general | completado | 2026-09-11T14:15:40-06:00 / `irving:admin` | merge_a_main en idx 3 |
| #9990781 Catálogos parametrizables: modalidades, modos de pago y parámetros del reglamento | aprobado_irving | 2026-09-11T14:04:55-06:00 / `irving:admin` | estado=aprobado_revisor en idx 2 |
| #9990790 [RESPUESTA] Subir el reglamento de ventas y comisiones al repo — falta el texto fuente de Irving | en_progreso | 2026-09-11T13:33:42-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #9990816 Tablero admin para Irving: qué colaborador tiene qué documento pendiente (con antigüedad + recordar) | aprobado_irving | 2026-09-11T16:40:29-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #9990817 Reapertura de acuses — verificación end-to-end con datos sintéticos (rollback) | aprobado_irving | 2026-09-11T16:40:19-06:00 / `irving:admin` | estado_aprobacion actual ya no es requiere_irving |
| #9990826 Reapertura de acuses — implementar y correr el test E2E (Feature, DB de test) | aprobado_irving | 2026-09-11T15:34:57-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #9990835 Fase 2 (#9990833): esquema + comando clientes:normalizar-series (backfill SN de equipo) | completado | 2026-09-11T16:40:23-06:00 / `irving:admin` | merge_a_main en idx 7 |
| #9990837 Fase 4 (#9990833): reporte de discrepancias SN captura-manual vs. OLT | completado | 2026-09-11T17:25:28-06:00 / `irving:admin` | estado=aprobado_revisor en idx 6 |
| #9990839 Fase 4a — Medir p50/p95 real (nombre vs SN) y repasar los 10 criterios de aceptación de #9990803 | aprobado_irving | 2026-09-11T16:04:04-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |
| #9990848 Fase 3b (#9990836): listado de Clientes muestra serie_equipo (formato corto) + indicador de serie_equipo_origen en la columna Modem Serie | completado | 2026-09-11T17:25:36-06:00 / `irving:admin` | estado=aprobado_revisor en idx 4 |

### Grupo (b) — SIGUEN PARADOS (comentario humano SIN avance posterior)

**Métricas del grupo (b):** promedio de días parado = **0** días. Caso más viejo = **0** días

| Item | Comentario humano (ts / por / vía) | Días parado | Texto/resumen del comentario | Sub-items generados (origen_item_id inverso) |
|---|---|---:|---|---|

## Casos cercanos (near-miss) — HOY en `requiere_irving`, con algún comentario humano en su historial, pero NO candidatos bajo la definición estricta

Universo: `RoadmapItem::where('estado_aprobacion','requiere_irving')->whereNotNull('log')->get()` → **6** items. De esos, **1** tienen algún actor humano en algún punto de su historial, pero en los **1** casos la ÚLTIMA entrada a `requiere_irving` es POSTERIOR al comentario humano (no anterior) — es decir: el comentario SÍ fue consumido (el sistema reintentó), y el reintento volvió a fallar por causa propia (ej. `max_turns` sin commits). **Esto es un patrón distinto al que busca esta Fase 1** ("comentario ignorado, nunca reprocesado"): aquí no hay comentario ignorado, hay un reintento fallido después del comentario. Se documentan porque son la evidencia más cercana en los datos vivos y probablemente el objeto real de Fase 2/3 de CIRC-02.

| Item | Último comentario humano (ts / por) | Último evento del log (ts / por / evento) | Motivo del último evento |
|---|---|---|---|
| #9990853 corrección del Circuito CC — para desatorar el flujo | 2026-09-11T17:58:07-06:00 / `irving:admin` | 2026-09-11T18:24:03-06:00 / `reaper` / huerfano_reencolado | worker murió/timeout con el item en en_progreso (hace 25 minutos) |

## Snippet de tinker completo (reproducible)

```php


// ============================================================
// Taxonomía EXACTA dada por el item (ya construida sobre datos reales)
// ============================================================
$HUMANO_EXACTO = [
    'irving:admin',
    'irving:CARLOS',
    'irving:Irving',
    'irving:david_marsal',
    'irving:admin (via claude-code)',
    'irving:admin (dictado a claude-code)',
    'irving:pedido-directo',
    'irving:re-sello',
    'irving:merge',
    'david:relay-irving',
    'david_marsal',
    'claude-code (por instruccion de irving:admin)',
    'claude-code (por instruccion explicita de irving:admin)',
    'claude-code (sesion supervisada, por instruccion de irving:admin)',
];

$AUTOMATICO_EXACTO = [
    'jarvis-mecanico', 'merge-runner', 'paraguas', 'thomas-mecanico', 'limite_cuenta',
    'consola:tinker', 'reaper-rapido', 'valvula:contexto', 'revisor:backlog', 'soltar-claim',
    'jarvis:verificarCierre', 'thomas:verificarCierre', 'timeout', 'timeout:reanudado',
    'jarvis-ya-decidido', 'colision-check', 'destrabe(opus)', 'autopilot', 'auditor',
    'revisor:triaje-null', 'valvula:nacimiento', 'claude-code',
];

// Prefijos automáticos EXTENDIDOS (no listados literal, pero mismo namespace de subsistema)
$AUTOMATICO_PREFIJOS = [
    'consola:circuito:', 'circuito:', 'wt-', 'jarvis:', 'jarvis-', 'thomas:', 'thomas-',
    'revisor:', 'valvula:', 'consola:',
];
$AUTOMATICO_EXTRA_EXACTO = ['jarvis', 'thomas', 'barrido', 'reaper'];

function classify(string $por, array $HUMANO_EXACTO, array $AUTOMATICO_EXACTO, array $AUTOMATICO_PREFIJOS, array $AUTOMATICO_EXTRA_EXACTO): array {
    if (in_array($por, $HUMANO_EXACTO, true)) {
        return ['bucket' => 'humano', 'via' => 'exacto'];
    }
    if (in_array($por, $AUTOMATICO_EXACTO, true)) {
        return ['bucket' => 'automatico', 'via' => 'exacto'];
    }
    if (in_array($por, $AUTOMATICO_EXTRA_EXACTO, true)) {
        return ['bucket' => 'automatico', 'via' => 'extension'];
    }
    // irving:XXX no listado literal -> humano por namespace (nombre real de persona)
    if (str_starts_with($por, 'irving:')) {
        return ['bucket' => 'humano', 'via' => 'extension'];
    }
    // claude-code (...) con mención explícita a "instruccion"/"dictado" de irving -> humano
    // (mismo patrón que los 3 literales de la lista: dictado/instrucción real, no solo mencionar el nombre)
    if (preg_match('/^claude-code\s*\(.*(instruccion|dictado).*irving.*\)$/i', $por) || preg_match('/^claude-code\s*\(.*irving.*(instruccion|dictado).*\)$/i', $por)) {
        return ['bucket' => 'humano', 'via' => 'extension'];
    }
    // claude-code (...) que solo REFERENCIA a Irving (ej. "doc CIRC v2 de Irving") sin ser dictado/instrucción
    // explícita -> ambiguo (probablemente un proceso automático de creación masiva desde un documento, no
    // una viva dictada por Irving en el momento). Igual para cualquier otra variante "claude-code (...)".
    if (preg_match('/^claude-code\s*\(/i', $por) || preg_match('/^Claude Code\s*\(/', $por)) {
        return ['bucket' => 'ambiguo', 'via' => 'extension'];
    }
    if (preg_match('/^claude-code:/i', $por)) {
        return ['bucket' => 'automatico', 'via' => 'extension'];
    }
    foreach ($AUTOMATICO_PREFIJOS as $pref) {
        if (str_starts_with($por, $pref)) {
            return ['bucket' => 'automatico', 'via' => 'extension'];
        }
    }
    return ['bucket' => 'ambiguo', 'via' => 'sin_clasificar'];
}

// ============================================================
// 1) Frecuencia de actores + clasificación (auditoría de la taxonomía)
// ============================================================
$items = \App\Modules\Addons\Roadmap\Models\RoadmapItem::whereNotNull('log')->get(['id', 'title', 'estado_aprobacion', 'log', 'origen_item_id', 'updated_at', 'created_at']);

$actorFreq = [];
foreach ($items as $it) {
    foreach ((array) $it->log as $entry) {
        if (!is_array($entry) || !isset($entry['por'])) continue;
        $p = $entry['por'];
        $actorFreq[$p] = ($actorFreq[$p] ?? 0) + 1;
    }
}
arsort($actorFreq);

$actorClass = [];
foreach ($actorFreq as $p => $c) {
    $actorClass[$p] = classify($p, $HUMANO_EXACTO, $AUTOMATICO_EXACTO, $AUTOMATICO_PREFIJOS, $AUTOMATICO_EXTRA_EXACTO);
}

// ============================================================
// 2) Por cada item: detectar entradas a requiere_irving + comentario humano posterior
// ============================================================
$candidatos = []; // items con >=1 comentario humano posterior a su ÚLTIMA entrada a requiere_irving
$now = \Carbon\Carbon::parse('2026-09-11 23:59:59', 'America/Mexico_City');

foreach ($items as $it) {
    $log = (array) $it->log;
    if (empty($log)) continue;

    // localizar TODAS las entradas con estado === 'requiere_irving' (transición explícita)
    $entradasRI = [];
    foreach ($log as $idx => $entry) {
        if (!is_array($entry)) continue;
        if (($entry['estado'] ?? null) === 'requiere_irving') {
            $entradasRI[] = ['idx' => $idx, 'ts' => $entry['ts'] ?? null, 'entry' => $entry];
        }
    }

    $aproximado = false;
    if (empty($entradasRI)) {
        // fallback: sin marca explícita en el log. Si el estado actual es requiere_irving,
        // aproximar usando el primer timestamp del log como límite inferior (documentado como limitación).
        if ($it->estado_aprobacion !== 'requiere_irving') {
            continue; // nunca estuvo (registrado) en requiere_irving y no lo está ahora -> no es candidato
        }
        $aproximado = true;
        $entryTs = $log[0]['ts'] ?? null;
        $lastEntradaIdx = -1;
    } else {
        $ultima = end($entradasRI);
        $entryTs = $ultima['ts'];
        $lastEntradaIdx = $ultima['idx'];
    }

    if (!$entryTs) continue;
    $entryCarbon = \Carbon\Carbon::parse($entryTs);

    // buscar entradas de actor HUMANO con ts > entryCarbon (posteriores a la última entrada a requiere_irving)
    $comentariosHumanosPosteriores = [];
    foreach ($log as $idx => $entry) {
        if ($idx <= $lastEntradaIdx) continue;
        if (!is_array($entry) || !isset($entry['por']) || !isset($entry['ts'])) continue;
        $cls = $actorClass[$entry['por']] ?? ['bucket' => 'ambiguo', 'via' => 'sin_clasificar'];
        $ts = \Carbon\Carbon::parse($entry['ts']);
        if ($ts->gt($entryCarbon) && $cls['bucket'] === 'humano') {
            $comentariosHumanosPosteriores[] = ['idx' => $idx, 'ts' => $entry['ts'], 'entry' => $entry, 'via' => $cls['via']];
        }
    }

    if (empty($comentariosHumanosPosteriores)) continue; // no es candidato: nadie comentó tras la escalada

    $ultimoComentario = end($comentariosHumanosPosteriores);
    $comentarioIdx = $ultimoComentario['idx'];
    $comentarioTs = \Carbon\Carbon::parse($ultimoComentario['ts']);

    // ¿hubo avance tras el comentario?
    $avanzoPorEstadoActual = $it->estado_aprobacion !== 'requiere_irving';
    $avanzoPorEntradaLog = false;
    $evidenciaAvance = null;
    foreach ($log as $idx => $entry) {
        if ($idx <= $comentarioIdx) continue;
        if (!is_array($entry)) continue;
        if (($entry['evento'] ?? null) === 'merge_a_main') {
            $avanzoPorEntradaLog = true;
            $evidenciaAvance = 'merge_a_main en idx ' . $idx;
            break;
        }
        if (isset($entry['estado']) && $entry['estado'] !== 'requiere_irving') {
            $avanzoPorEntradaLog = true;
            $evidenciaAvance = 'estado=' . $entry['estado'] . ' en idx ' . $idx;
            break;
        }
    }

    $avanzo = $avanzoPorEstadoActual || $avanzoPorEntradaLog;

    $candidatos[] = [
        'id' => $it->id,
        'title' => $it->title,
        'estado_aprobacion_actual' => $it->estado_aprobacion,
        'origen_item_id' => $it->origen_item_id,
        'entrada_requiere_irving_ts' => $entryTs,
        'entrada_aproximada' => $aproximado,
        'comentario_humano_ts' => $ultimoComentario['ts'],
        'comentario_humano_por' => $ultimoComentario['entry']['por'],
        'comentario_humano_via' => $ultimoComentario['via'],
        'comentario_humano_texto' => $ultimoComentario['entry']['comentario'] ?? $ultimoComentario['entry']['motivo'] ?? $ultimoComentario['entry']['decision'] ?? null,
        'grupo' => $avanzo ? 'a' : 'b',
        'evidencia_avance' => $evidenciaAvance,
        'dias_parado' => $avanzo ? null : $comentarioTs->diffInDays($now),
    ];
}

$grupoA = array_values(array_filter($candidatos, fn($c) => $c['grupo'] === 'a'));
$grupoB = array_values(array_filter($candidatos, fn($c) => $c['grupo'] === 'b'));

// ============================================================
// 3) Métricas de grupo (b) + dependientes
// ============================================================
usort($grupoB, fn($a, $b) => $b['dias_parado'] <=> $a['dias_parado']);

$diasArr = array_column($grupoB, 'dias_parado');
$promDias = count($diasArr) ? array_sum($diasArr) / count($diasArr) : 0;
$maxDias = count($diasArr) ? max($diasArr) : 0;
$peor = $grupoB[0] ?? null;

foreach ($grupoB as &$item) {
    $hijos = \App\Modules\Addons\Roadmap\Models\RoadmapItem::where('origen_item_id', $item['id'])->get(['id', 'title', 'estado_aprobacion']);
    $item['sub_items_generados'] = $hijos->map(fn($h) => "#{$h->id} ({$h->estado_aprobacion}) {$h->title}")->all();
}
unset($item);

// ============================================================
// 3b) "Casos cercanos" (near-miss): items HOY en requiere_irving cuyo historial
// SÍ contiene algún actor humano en algún punto, pero la ÚLTIMA entrada a
// requiere_irving es POSTERIOR a ese comentario (patrón distinto: comentario
// consumido -> reintento -> volvió a fallar por causa propia, NO "comentario ignorado").
// No son candidatos bajo la definición estricta del método, pero son la evidencia
// más cercana en los datos actuales y relevantes para Fase 2.
// ============================================================
$hoyRequiereIrving = \App\Modules\Addons\Roadmap\Models\RoadmapItem::where('estado_aprobacion', 'requiere_irving')->whereNotNull('log')->get(['id', 'title', 'log']);
$casosNearMiss = [];
foreach ($hoyRequiereIrving as $it) {
    $log = (array) $it->log;
    $ultimoComentarioHumano = null;
    foreach ($log as $idx => $entry) {
        if (!is_array($entry) || !isset($entry['por'])) continue;
        $cls = $actorClass[$entry['por']] ?? ['bucket' => 'ambiguo', 'via' => 'sin_clasificar'];
        if ($cls['bucket'] === 'humano') {
            $ultimoComentarioHumano = ['idx' => $idx, 'entry' => $entry];
        }
    }
    if (!$ultimoComentarioHumano) continue; // nunca hubo actor humano en su historial
    $ultimoEntry = end($log);
    $casosNearMiss[] = [
        'id' => $it->id,
        'title' => $it->title,
        'comentario_humano_ts' => $ultimoComentarioHumano['entry']['ts'] ?? null,
        'comentario_humano_por' => $ultimoComentarioHumano['entry']['por'],
        'ultimo_evento_por' => $ultimoEntry['por'] ?? null,
        'ultimo_evento_evento' => $ultimoEntry['evento'] ?? ($ultimoEntry['estado'] ?? null),
        'ultimo_evento_ts' => $ultimoEntry['ts'] ?? null,
        'ultimo_evento_motivo' => $ultimoEntry['motivo'] ?? null,
    ];
}

// ============================================================
// 4) Escribir docs/circuito-item-9990857-fase1-datos.md
// ============================================================
$out = "# CIRC-02a Fase 1 — Datos crudos: items requiere_irving con comentario humano posterior\n\n";
$out .= "Generado: 2026-09-11 (item #9990872, worker wt-2). Diagnóstico SOLO LECTURA — no se tocó BD ni código fuera de este archivo.\n\n";
$out .= "Universo: `\App\Modules\Addons\Roadmap\Models\RoadmapItem::whereNotNull('log')->get()` → **{$items->count()}** items con log no vacío.\n\n";
$out .= "## Método (reproducible)\n\n";
$out .= "Snippet de tinker usado: `/tmp/circ02a_fase1.php` (ejecutado vía `php artisan tinker < archivo.php`), reproducido íntegro en la sección final de este documento.\n\n";
$out .= "1. Para cada item, se localiza la **última** entrada del log con `estado === 'requiere_irving'` (la escalada más reciente).\n";
$out .= "2. Se buscan entradas de actor **humano** (según taxonomía) con `ts` posterior a esa escalada.\n";
$out .= "3. Si existe al menos una → candidato. Se toma la **última** (más reciente) como \"el comentario humano\" de referencia.\n";
$out .= "4. Se revisa si, después de ese comentario, hay evidencia de avance (`merge_a_main`, o una entrada con `estado` distinto de `requiere_irving`, o el `estado_aprobacion` actual ya no es `requiere_irving`):\n";
$out .= "   - **Grupo (a) AVANZARON**: sí hay evidencia de avance.\n";
$out .= "   - **Grupo (b) SIGUEN PARADOS**: `estado_aprobacion` actual sigue siendo `requiere_irving` y no hay evidencia de avance tras el comentario.\n\n";

$out .= "## Auditoría de la taxonomía de actores (frecuencia real en TODOS los logs)\n\n";
$out .= "La taxonomía del item cubre los actores más frecuentes, pero el grep real encontró **" . count($actorFreq) . "** actores distintos ";
$out .= "(la lista dada no es 100% exhaustiva — hay variantes nuevas). Clasificación aplicada:\n\n";
$out .= "- **humano/exacto**: coincide literal con la lista humana dada.\n";
$out .= "- **humano/extension**: no está en la lista literal, pero seguro por patrón (`irving:NOMBRE` no listado, o `claude-code (...)` que menciona irving/instruccion/dictado).\n";
$out .= "- **automatico/exacto**: coincide literal con la lista automática dada.\n";
$out .= "- **automatico/extension**: no está literal, pero mismo namespace de subsistema (`circuito:*`, `wt-N`, `jarvis:*`, `thomas:*`, etc.) o variantes obvias (`jarvis`, `thomas`, `barrido`, `reaper`, `claude-code:sesion`).\n";
$out .= "- **ambiguo**: no se pudo clasificar con confianza — **se excluyó del conteo de \"comentario humano\"** (conservador, para no inflar falsos positivos). Requiere que Fase 2 (o Irving) lo revise.\n\n";

$out .= "| Actor | Apariciones | Bucket | Vía |\n|---|---:|---|---|\n";
foreach ($actorFreq as $p => $c) {
    $cls = $actorClass[$p];
    $out .= "| `{$p}` | {$c} | {$cls['bucket']} | {$cls['via']} |\n";
}
$out .= "\n";

$ambiguos = array_filter($actorClass, fn($c) => $c['bucket'] === 'ambiguo');
if ($ambiguos) {
    $out .= "### ⚠️ Actores AMBIGUOS (excluidos del conteo humano, pendientes de revisión)\n\n";
    foreach ($ambiguos as $p => $c) {
        $out .= "- `{$p}` ({$actorFreq[$p]} apariciones)\n";
    }
    $out .= "\n";
}

$out .= "## Resultado: candidatos con comentario humano posterior a `requiere_irving`\n\n";
$out .= "Total candidatos: **" . count($candidatos) . "** — Grupo (a) avanzaron: **" . count($grupoA) . "** — Grupo (b) siguen parados: **" . count($grupoB) . "**\n\n";

$out .= "### Grupo (a) — AVANZARON (comentario humano + evidencia de avance posterior)\n\n";
$out .= "| Item | Estado actual | Comentario humano (ts / por) | Evidencia de avance |\n|---|---|---|---|\n";
foreach ($grupoA as $c) {
    $out .= "| #{$c['id']} " . str_replace('|', '\\|', $c['title']) . " | {$c['estado_aprobacion_actual']} | {$c['comentario_humano_ts']} / `{$c['comentario_humano_por']}` | " . ($c['evidencia_avance'] ?? 'estado_aprobacion actual ya no es requiere_irving') . " |\n";
}
$out .= "\n";

$out .= "### Grupo (b) — SIGUEN PARADOS (comentario humano SIN avance posterior)\n\n";
$out .= "**Métricas del grupo (b):** promedio de días parado = **" . round($promDias, 1) . "** días. Caso más viejo = **" . round($maxDias, 1) . "** días";
if ($peor) {
    $out .= " (#{$peor['id']} — {$peor['title']}, comentario del {$peor['comentario_humano_ts']}).";
}
$out .= "\n\n";
$out .= "| Item | Comentario humano (ts / por / vía) | Días parado | Texto/resumen del comentario | Sub-items generados (origen_item_id inverso) |\n|---|---|---:|---|---|\n";
foreach ($grupoB as $c) {
    $texto = $c['comentario_humano_texto'] ? str_replace(["\n", '|'], [' ', '\\|'], mb_substr($c['comentario_humano_texto'], 0, 200)) : '(sin texto/motivo en el log)';
    $subitems = $c['sub_items_generados'] ? implode('; ', $c['sub_items_generados']) : '(ninguno)';
    $out .= "| #{$c['id']} " . str_replace('|', '\\|', $c['title']) . " | {$c['comentario_humano_ts']} / `{$c['comentario_humano_por']}` / {$c['comentario_humano_via']} | {$c['dias_parado']} | {$texto} | {$subitems} |\n";
}
$out .= "\n";

$aproximados = array_filter($candidatos, fn($c) => $c['entrada_aproximada']);
if ($aproximados) {
    $out .= "### ⚠️ Limitación: items sin marca explícita de entrada a `requiere_irving` en el log\n\n";
    $out .= count($aproximados) . " candidato(s) están en `requiere_irving` hoy pero su log no tiene una entrada con `estado==='requiere_irving'` explícita (se aproximó con el primer ts del log como límite inferior):\n\n";
    foreach ($aproximados as $c) {
        $out .= "- #{$c['id']} {$c['title']}\n";
    }
    $out .= "\n";
}

$out .= "## Casos cercanos (near-miss) — HOY en `requiere_irving`, con algún comentario humano en su historial, pero NO candidatos bajo la definición estricta\n\n";
$out .= "Universo: `RoadmapItem::where('estado_aprobacion','requiere_irving')->whereNotNull('log')->get()` → **" . $hoyRequiereIrving->count() . "** items. ";
$out .= "De esos, **" . count($casosNearMiss) . "** tienen algún actor humano en algún punto de su historial, pero en los **" . count($casosNearMiss) . "** casos la ÚLTIMA entrada a `requiere_irving` es POSTERIOR al comentario humano (no anterior) — es decir: el comentario SÍ fue consumido (el sistema reintentó), y el reintento volvió a fallar por causa propia (ej. `max_turns` sin commits). **Esto es un patrón distinto al que busca esta Fase 1** (\"comentario ignorado, nunca reprocesado\"): aquí no hay comentario ignorado, hay un reintento fallido después del comentario. Se documentan porque son la evidencia más cercana en los datos vivos y probablemente el objeto real de Fase 2/3 de CIRC-02.\n\n";
$out .= "| Item | Último comentario humano (ts / por) | Último evento del log (ts / por / evento) | Motivo del último evento |\n|---|---|---|---|\n";
foreach ($casosNearMiss as $c) {
    $motivo = $c['ultimo_evento_motivo'] ? str_replace(["\n", '|'], [' ', '\\|'], mb_substr($c['ultimo_evento_motivo'], 0, 180)) : '—';
    $out .= "| #{$c['id']} " . str_replace('|', '\\|', $c['title']) . " | {$c['comentario_humano_ts']} / `{$c['comentario_humano_por']}` | {$c['ultimo_evento_ts']} / `{$c['ultimo_evento_por']}` / {$c['ultimo_evento_evento']} | {$motivo} |\n";
}
$out .= "\n";

$out .= "## Snippet de tinker completo (reproducible)\n\n```php\n" . file_get_contents('/tmp/circ02a_fase1.php') . "\n```\n";

file_put_contents(base_path('docs/circuito-item-9990857-fase1-datos.md'), $out);

echo "OK — escrito docs/circuito-item-9990857-fase1-datos.md\n";
echo "universo=" . $items->count() . " candidatos=" . count($candidatos) . " grupoA=" . count($grupoA) . " grupoB=" . count($grupoB) . "\n";
echo "promDias=" . round($promDias, 1) . " maxDias=" . round($maxDias, 1) . "\n";

```
