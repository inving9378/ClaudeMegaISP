# Auditoría retroactiva — merges de Jarvis sin aprobación fresca de Irving (#747)

> Generado automáticamente por `php artisan circuito:auditar-merges-post-aprobacion` el 2026-08-29 03:11:26.
> **READ-ONLY**: no modifica items ni git, solo reporta. Re-ejecutar este comando pisa este
> archivo con la medición más reciente. La reversión automática de merges sospechosos fue
> explícitamente DESCARTADA por Irving al aprobar #279 — cada candidato aquí listado espera su
> revisión manual, uno por uno.

Parámetros: margen = 0h · ventana del reporte = últimos 14 días (fecha_aprobacion) · items con
merge_commit escaneados en total = 501 · en ventana = 158.

## Candidatos sospechosos

Un candidato es sospechoso cuando el ÚLTIMO commit que trajo la rama mergeada (padre 2 del merge
commit) es posterior a la fecha de aprobación del item + margen — es decir, la rama siguió
recibiendo trabajo después de que Irving la aprobó, y ese trabajo nuevo nunca pasó por su revisión.

| Item | Título | Aprobado | Último commit mergeado | Diff (h) | Merge commit |
|---|---|---|---|---:|---|
| #9 | Conectar chat IA al registry (knowledge/actions) | 2026-08-25 17:40:35 | 2026-08-26 16:05:41 | 22.4 | `9f9d4b27c0aa6fcc0bbf896743c211b63b487b69` |
| #18 | Cerrar modulo Smart Import/Export contra checklist | 2026-08-28 15:16:03 | 2026-08-28 15:39:46 | 0.4 | `b10be4b3f58a5fa1387f64d19ba465689d284a97` |
| #19 | Cerrar modulo MegaFamilia contra checklist (bloqueado por infra Padre-Hijo y motor de servicios) | 2026-08-25 17:38:56 | 2026-08-26 15:49:57 | 22.2 | `af53507c43e24607c0b3321ebc4e28c717c60735` |
| #20 | Cerrar modulo Embajadores Meganet contra checklist (bloqueado por motor de servicios). UI siempre Embajadores Meganet; NUNCA usar la palabra piramidal | 2026-08-25 17:38:57 | 2026-08-26 15:53:03 | 22.2 | `249a7445492e1322bc6b6070b7c7f314bd793749` |
| #23 | Auditoria de la APK 3.4: inventario de pantallas (funcional/parcial/pendiente), endpoints, stack y perfil de usuario | 2026-08-25 16:53:16 | 2026-08-26 15:07:13 | 22.2 | `295ba8f7c9b0d5c32ce717b9b81ec2228e61ddaf` |
| #24 | Completar pantallas parciales del perfil Cliente en la APK (sale del item de auditoria) | 2026-08-28 15:16:03 | 2026-08-28 16:08:17 | 0.9 | `6d1ede9472903e033706e3f1bb0c96f823b2f677` |
| #26 | Sincronizacion en tiempo real cliente/servidor en la APK | 2026-08-25 17:37:37 | 2026-08-26 15:12:49 | 21.6 | `40a6a20ef05c2baec02de809eed1315cc19927a4` |
| #28 | Vista Embajador en la APK (referidos, comisiones, niveles). Bloqueado por modulo Embajadores y motor de servicios | 2026-08-25 17:39:01 | 2026-08-26 16:01:34 | 22.4 | `a3aa34db519414752cf71d82dd2c9d5edb5523ba` |
| #29 | Flujo de registro del hijo desde la app del padre (crear/invitar, vincular dispositivo, definir permisos) | 2026-08-25 17:37:39 | 2026-08-26 15:22:20 | 21.7 | `a87004aac0a4a61d08f5d99948d5fcced0a4f99c` |
| #30 | Vista Hijo en la APK (perfil restringido segun permisos del padre) | 2026-08-25 17:39:02 | 2026-08-26 16:10:58 | 22.5 | `ba61edf3dc1e9363e95d3db9ee46df965dc7c8f1` |
| #31 | Control parental: impedir desinstalacion sin autorizacion del padre (Device Admin/Owner). Decision tecnica del enfoque cuando llegue su turno | 2026-08-25 17:39:04 | 2026-08-26 16:15:37 | 22.6 | `b451c43049af16c081badfe92d7830c30c686777` |
| #36 | Migrar /22 a VLAN 500 en MikroTik OFICINA SANTA INES (bloqueo Ubiquiti sfp 7 WIFI tagged) | 2026-08-28 15:47:35 | 2026-08-28 16:00:40 | 0.2 | `aada310c1bea54d8b034a195a36c0106ee7537aa` |
| #37 | Debug Issabel call blaster: CSV upload OK pero download da No Data Found | 2026-08-25 17:40:30 | 2026-08-26 16:08:38 | 22.5 | `209e8cdbed51d8ce04fa15901eea4128ac3b57cd` |
| #46 | Setup cuentas Meta — Business Manager + Instagram Business + App for Developers | 2026-08-28 16:18:27 | 2026-08-28 16:34:26 | 0.3 | `313903d51395a82bbc571b3fd273a6a1a0d1b0ec` |
| #47 | Primera publicación real Marketing Fase 5 — campaña multivariante | 2026-08-28 15:10:51 | 2026-08-28 15:22:02 | 0.2 | `40c495180b049bd63e9c98078324ad09080ee039` |
| #66 | Flotas Fase 7 — IA agrupada (OCR, prediccion, asistente conversacional) | 2026-08-28 16:16:02 | 2026-08-28 16:44:58 | 0.5 | `f51da355ef90566844c5112d4b004fc3b476300a` |
| #75 | MegaFamilia: google_maps_flutter también omitido del Flutter — afecta vista de mapas en APK | 2026-08-25 17:39:56 | 2026-08-26 16:23:41 | 22.7 | `88821e14036ce92440aa995d76a52e7649c89ed4` |
| #86 | Refactor: sincronización Mikrotik con eventual consistency (sync_status + queue + dashboard) | 2026-08-28 16:17:07 | 2026-08-28 16:21:54 | 0.1 | `1a7eec9d19478c8d6fc8360313fcc0009b4fb434` |
| #101 | Fase 5.5 Flotas — Push notifications APK conductor (FCM, requiere item #72 Firebase) | 2026-08-28 16:13:16 | 2026-08-28 16:20:38 | 0.1 | `5d5f5794a6d9d9d834f5d4906e5a656bd5ea9881` |
| #102 | Mapa overview multi-vehículo en sección Mis Flotas de la APK | 2026-08-28 15:16:03 | 2026-08-28 15:41:15 | 0.4 | `cb34459767ee2523a357e43891525dfb1b9cc8ef` |
| #104 | Probar APK MegaFamilia end-to-end con cuenta de cliente real (primer login post-fix) | 2026-08-28 15:12:13 | 2026-08-28 16:12:18 | 1 | `1f1d243b533d306fe87ee4952cd1b9239d53559d` |
| #108 | Definir modelo de cobro Flotas con socio (bloquea Fase 6.2) | 2026-08-26 15:08:47 | 2026-08-26 15:10:07 | 0 | `698c9920c78efa7fce46c16bd3f0b4a2c08efba0` |
| #109 | Patrón .catch(() => {}) silencioso oculta errores 500 del backend en la app web | 2026-08-25 16:51:39 | 2026-08-26 16:05:00 | 23.2 | `faee01a03d7cfd1c4d86bf625af60377ad28a9ab` |
| #111 | Auditar APIs jQuery Bootstrap 4 legacy restantes (.collapse / .tab / .tooltip / .popover / .dropdown) | 2026-08-25 17:38:45 | 2026-08-26 15:26:03 | 21.8 | `df085c9bcd99caf066828c5af6a2d11b8a733fad` |
| #114 | Refactor opcional: PermissionAssignmentModal.vue compartido + endpoint /api/permissions/all-grouped | 2026-08-25 17:40:00 | 2026-08-26 16:16:43 | 22.6 | `5ab2763f387fdab8a7fde761d5e4b4318a764110` |
| #121 | [DECISIÓN] Talento: definir reglas de compensación de roles no-técnicos | 2026-08-27 13:59:36 | 2026-08-27 14:09:33 | 0.2 | `6cd3082300ad0a41358ce259b004e3196a74b532` |
| #123 | [DEUDA TÉCNICA] Talento: sin bridge entre CommissionRule/TransactionSeller (vendedores) y el motor | 2026-08-26 15:04:21 | 2026-08-26 15:26:05 | 0.4 | `2a5795dfae659bf01b99a8b8aff2c7a42f810d22` |
| #124 | [REVISIÓN] Talento: revisar alcance de permisos talento.*.view (asignados a 17 roles) | 2026-08-25 17:40:36 | 2026-08-26 16:03:33 | 22.4 | `40dacdc1a0329deeb022455902133655fa02ab0d` |
| #138 | SPA: prefetch on hover de rutas del sidebar | 2026-08-28 16:15:55 | 2026-08-28 16:43:29 | 0.5 | `3e68c6e421a2cf463605b634b3627ba445fd9d26` |
| #139 | Migrar MegaISP a HTTPS interno (cert local) | 2026-08-25 17:40:15 | 2026-08-26 16:17:49 | 22.6 | `9a9987b400bd14d0f7a45e6a33fffdaf2fe48f70` |
| #143 | Webhook OpenPay en dashboard | 2026-08-28 16:17:14 | 2026-08-28 17:21:58 | 1.1 | `333683624b687b513391d2bf904cf921883d38f1` |
| #144 | Portal: subdominio + SSL (portal.meganet.mx) | 2026-08-28 15:16:03 | 2026-08-28 15:30:28 | 0.2 | `58bb2640130326040e72ca9a680407285451abab` |
| #147 | Re-habilitar índice unique de plan_bundles | 2026-08-25 17:39:21 | 2026-08-26 15:52:38 | 22.2 | `ad0e962f64b333f00844227719c4867d53e0cdf1` |
| #150 | Portal: funciones cliente MegaFamilia | 2026-08-25 17:39:27 | 2026-08-26 16:00:00 | 22.3 | `fdceb8802b3846ab7adc99dc72ccf22157c54875` |
| #152 | Portal: Flotas para cliente | 2026-08-25 17:40:17 | 2026-08-26 16:14:13 | 22.6 | `e0366bb1f8c61c54a21a14d12aa85d371f6e218d` |
| #154 | Portal Cliente: contraseñas en texto plano — evaluar migración a bcrypt (Opción B) | 2026-08-25 17:39:31 | 2026-08-26 16:03:59 | 22.4 | `a194fef4bacc9974e35f8d17ddf9b0e469fa4988` |
| #159 | Deuda: dos tablas de facturas (invoices vs client_invoices) | 2026-08-25 17:39:42 | 2026-08-26 16:00:21 | 22.3 | `af5801f1e069ec6af5cdceecb445fa9bd360436b` |
| #164 | Fase 2 Medussa: cuota de software por transacción conciliada | 2026-08-28 16:15:46 | 2026-08-28 16:47:23 | 0.5 | `22097068cf503bef980218012c0696ed49769e1a` |
| #167 | Manual: doble fuente de módulos (modules legacy vs module_registry) — consolidar | 2026-08-25 17:39:49 | 2026-08-26 15:59:17 | 22.3 | `b4b8000a31ba2dfd481f5a651fdc4dd6e9fe012e` |
| #171 | Guardrail de migraciones falla abierto en la ruta web de Ignition | 2026-08-27 13:28:06 | 2026-08-27 13:34:51 | 0.1 | `bc03c24822994fe37f57aba39197eb4a7fd0339e` |
| #172 | Respaldo automatico de MySQL inexistente: el ultimo es del 29-jun | 2026-08-25 17:37:00 | 2026-08-26 15:11:07 | 21.6 | `6c7895407c9e38234a1e2fdc9da82247c4c86b91` |
| #174 | vuelta.sh: timeout al proceso padre, tope de iteraciones y deteccion de huerfano | 2026-08-25 16:50:23 | 2026-08-26 15:19:38 | 22.5 | `e5caf74e2e268a9864872e41bf54112e4f96ca9d` |
| #176 | Alerta de errores en cascada: 268.896 excepciones en dos dias sin deteccion | 2026-08-28 15:09:26 | 2026-08-28 15:17:27 | 0.1 | `aaa267a00398fc16664e3eaf4a70925b57288c32` |
| #177 | Reconstruir la Hoja de Ruta posterior al 29-jun | 2026-08-25 17:37:03 | 2026-08-26 15:45:45 | 22.1 | `06bcd4455e809f4d433dd5313086fa22cb3fe32a` |
| #178 | Forense 22-ago: confirmar si el corte original tambien salio del boton de Ignition | 2026-08-26 06:20:15 | 2026-08-26 15:22:59 | 9 | `4198bcae76c2cb09ddc900f126b6a26018fea08d` |
| #180 | down() de widen_releases_summary_to_text trunca TEXT a varchar(255) | 2026-08-25 16:50:36 | 2026-08-26 15:05:29 | 22.2 | `6b559127430900dd812c9d1da834aea3e5095bdc` |
| #181 | SqlInjectionProtection lanza antes del abort(403) si su registro falla | 2026-08-25 17:37:05 | 2026-08-26 15:05:41 | 21.5 | `3061d168350203a35ff12b27d7a20db7c2f45893` |
| #184 | Más 8tems | 2026-08-24 17:36:53 | 2026-08-25 15:47:32 | 22.2 | `2df45de0da0fc2cb3f22f75384d0a9b3714798c9` |
| #192 | El pool real es 0 mientras el tablero muestra 12: un footprint desconocido en vuelo bloquea las 6 terminales y la compuerta lo pinta verde | 2026-08-26 15:04:21 | 2026-08-26 16:31:22 | 1.5 | `1832ae47314a365099729d4ffa906a5d254b47f7` |
| #193 | caberEnVuelta dice CABE sin senal empirica, y no existe planificador de descomposicion: partir un item depende de que el modelo obedezca la prosa | 2026-08-25 17:37:17 | 2026-08-26 16:42:42 | 23.1 | `8e79e04c55782ea15c97c80228e647b457b7c677` |
| #194 | parquear-timeout no incrementa reanudaciones_timeout: el circuito nunca aprende de los items que se atoran sin producir nada | 2026-08-28 15:09:42 | 2026-08-28 15:31:44 | 0.4 | `6ab0eb00db6e192f6f1575136dc209feebe19318` |
| #197 | circuito:scheduler --dry sella circuito_scheduler_beat antes del flock y sin mirar opciones: inspeccionar el scheduler miente sobre el scheduler | 2026-08-28 15:09:45 | 2026-08-28 15:37:27 | 0.5 | `f9ac9cbbdef933dc2d4544b3430afe9d3921d5ac` |
| #199 | Expediente RH — Hijo A: modelo de datos del expediente y formulario de alta | 2026-08-28 08:22:37 | 2026-08-28 08:29:20 | 0.1 | `b45348b5dea8eb769832fd0e6470b5db93b33bae` |
| #200 | Expediente RH — Hijo B: motor de plantillas con variables y versionado | 2026-08-28 16:15:40 | 2026-08-28 16:28:55 | 0.2 | `f79557e37f29da6b9a2b67b5a58c54439d491f14` |
| #208 | Vigilante on-box: que los atascos se avisen solos en vez de buscarlos a mano | 2026-08-28 18:28:15 | 2026-08-28 19:15:44 | 0.8 | `05109848737283563b72bc9f4f167d601ea79fc2` |
| #212 | INANICION: los items sin footprint nunca se despachan si hay cualquier cosa en vuelo — 5 urgentes inalcanzables | 2026-08-28 15:10:03 | 2026-08-28 15:57:02 | 0.8 | `58aaafc7cb82fe932d3b92954901b3f1df2ee41a` |
| #215 | pkill -f sobre el patron del ejecutor mata la propia sesion que lo lanza | 2026-08-28 16:16:59 | 2026-08-28 18:15:38 | 2 | `7cbd83c9222b5ebb92453c4af0c6eb8830c48e6c` |
| #216 | Deriva de esquema en dev: medir cuantas columnas existen solo porque alguien las agrego a mano, sin migracion que las respalde | 2026-08-28 19:21:56 | 2026-08-28 19:34:23 | 0.2 | `3b22c8f63e02c636b47df12e9f4a914bd89ead88` |
| #217 | GestionRed: amplía api_endpoints en module.json (29/112 = 26%, umbral 30%) | 2026-08-28 16:18:31 | 2026-08-28 16:35:06 | 0.3 | `067b8ca7d921bbe36bdf54e1d568fee04b934f26` |
| #218 | Inventario: ~18 tipos de artículo siguen sin categoría (herramienta/material) | 2026-08-28 16:16:56 | 2026-08-28 16:38:51 | 0.4 | `32d303382bdec3f5a4d587ebf3bc01e3d77546ba` |
| #224 | Flotas: el OCR de documentos es un placeholder rotulado "Fase 7" | 2026-08-28 16:16:50 | 2026-08-28 16:37:59 | 0.4 | `06f170f42e9fd309ae138e1b939a5b654a36d3cf` |
| #225 | Inventario y candado de los recursos globales que comparten las seis terminales | 2026-08-28 15:10:10 | 2026-08-28 16:02:15 | 0.9 | `4049a454e7bb60ac03e8da31afa0ec35757805b2` |
| #228 | Chequeo bd_integra en la vigilia de Thomas: base vacía = freno automático, luego aviso | 2026-08-28 16:18:39 | 2026-08-28 17:45:01 | 1.4 | `a0a5f42fa79c9db658cbf3afe657a78ca2736160` |
| #229 | Extender la API roadmap-externo (Opción 2): alta de items e historial de reportes | 2026-08-28 15:11:54 | 2026-08-28 16:15:52 | 1.1 | `5677c05a0b58597301dd29b4d63d00cdae7abeca` |
| #233 | Ningún archivo que el cron ejecute por ruta puede quedar sin +x: candado y fin del >/dev/null | 2026-08-28 16:17:47 | 2026-08-28 17:51:36 | 1.6 | `14afbfc5b15a02b911ff9e0e15926d243492e27e` |
| #275 | Circuito: clasificar el footprint de 1 item(s) sin módulo | 2026-08-28 16:16:37 | 2026-08-28 18:17:32 | 2 | `53a91553f09d07fdd3d1d95da90352010aa9ac8c` |
| #276 | Roadmap / Circuito CC: amplía api_endpoints en module.json (18/74 = 24%, umbral 30%) | 2026-08-28 16:16:20 | 2026-08-28 18:25:06 | 2.1 | `74f43811cfc5c26b7d1c95824de3034193382a3b` |
| #280 | Consumir /api/megafamilia/sync-status en la app móvil (megafamilia-rn) para refrescar sin recargar | 2026-08-28 17:52:05 | 2026-08-28 18:27:41 | 0.6 | `cd0fe4693ed0b2c1d50c074c1e83f20ee2bdd37e` |
| #281 | OLT Huawei — cerrar escritura real en laboratorio (alta/baja/suspensión de ONU, Fase A+B) | 2026-08-28 16:15:33 | 2026-08-28 16:50:18 | 0.6 | `f246da1cd8dfa0a50d2f319f8545c1bd60e38b28` |
| #282 | OLT Huawei — implementar capacidades Supports* opcionales para paridad con SmartOLT | 2026-08-28 15:16:04 | 2026-08-28 15:54:56 | 0.6 | `8f5cfaecb5e6db9c23947ded4b6442912d897e8a` |
| #283 | Decisión de negocio: ¿priorizar driver ZTE y/o V-SOL? confirmar acceso a hardware piloto | 2026-08-28 15:13:50 | 2026-08-28 16:04:34 | 0.8 | `876d0bef6e6008cd703ebf9c5bc8d089f83135e2` |
| #284 | GR-6 (opcional) — encapsular los 13 modelos OLT dentro del módulo GestionRed | 2026-08-28 16:15:35 | 2026-08-28 16:58:35 | 0.7 | `6b897279ad6a9d658f012922373ec62cdfd75435` |
| #291 | Auditar ~90 llamadas $(...).modal("show"/"hide") jQuery aún sin migrar a BS5 nativo | 2026-08-28 16:15:29 | 2026-08-28 16:49:27 | 0.6 | `897432c87fe394ab806a4837508b5ba7ce9e758f` |
| #296 | Marketing: otros 3 enlaces de menú 404 (/marketing/video, /marketing/publicar, /marketing/niches) — el auditor no los va a crear solo | 2026-08-26 15:38:30 | 2026-08-26 16:21:44 | 0.7 | `8c0cbe46bb49af238eb03e948e60e45d969ad89d` |
| #623 | Seguimiento: pregunta sin resolver de #277 | 2026-08-28 16:15:27 | 2026-08-28 16:49:37 | 0.6 | `9cdc4aaf67a80077d087d95fe44a8ca31b151e6a` |
| #624 | Reconstruir items del incidente P0 que NO llegaron a mergear (pendientes/rechazados/abandonados) | 2026-08-28 22:02:04 | 2026-08-28 22:18:43 | 0.3 | `3e9290922e589e14518e32fb83733983f0630120` |
| #626 | Portal Cliente: generar factura en PDF/XML (CFDI) — no existe endpoint | 2026-08-28 17:00:04 | 2026-08-28 17:32:11 | 0.5 | `29438e228dd14b945e70aee17bcee8066f01a684` |
| #627 | Infra transversal: registro de push token FCM (requerido por MegaFamilia app padre/hijo) | 2026-08-28 15:16:04 | 2026-08-28 17:03:37 | 1.8 | `dbc8efbc91b1a76950d2988d2933ccd8c4655193` |
| #628 | Ramas huerfanas FASE A + Fase 1 (anti-bucle/metadata decision, jul-14) — verificar redundancia y limpiar | 2026-08-28 16:15:23 | 2026-08-28 19:49:17 | 3.6 | `b3f510e36d8bc77c974ffb84b14126b5cdf616a9` |
| #630 | Rama huerfana C2 (revert-on-reject, jul-13) — automatizar el revert real al rechazar un item ya integrado | 2026-08-28 16:15:20 | 2026-08-28 22:22:38 | 6.1 | `d664f300377932ea31197718fddd506cf5009d9a` |
| #631 | Item #171 atorado: rama ya implementada y probada, estado contradictorio, nunca integrada | 2026-08-28 16:15:18 | 2026-08-28 22:26:19 | 6.2 | `afbe4db359f8e58dc42af487b2fdfc03264b46ba` |
| #633 | Cola 'default' sin worker en DEV: 579 jobs atorados (2+ días) — bloquea clasificación de items nuevos del Roadmap | 2026-08-28 15:14:20 | 2026-08-28 22:29:16 | 7.2 | `c9e172144ff1dfac90930f7959b6b7760948f1a2` |
| #634 | circuito:re-triage nunca se invoca con --apply: los frenos del clasificador no caducan solos | 2026-08-28 15:16:04 | 2026-08-28 22:32:08 | 7.3 | `4584aa5845c8f42d3789ac4710b1b8eb82b40231` |
| #636 | Montar IaChatFloat.vue en el layout (el widget flotante no se renderiza en ningún lado) | 2026-08-27 14:31:29 | 2026-08-27 14:35:06 | 0.1 | `8af5cd7425b451e7104d8ecfc2af2b8a72b18cb4` |
| #637 | Thomas.elegibleAutoMerge() no respeta bloqueado_por_bucle: retry infinito de merge (ej. item #57) | 2026-08-28 15:16:10 | 2026-08-28 22:35:02 | 7.3 | `28b2ebf001dc3515b2cb5bc92a08a20f140cfdfe` |
| #639 | Vista Hijo APK: reemplazar mocks de Logros/Apps permitidas/Tiempo de pantalla por datos reales | 2026-08-28 15:22:03 | 2026-08-28 15:59:24 | 0.6 | `cd38ab0547bca1fffa60309dde9ea23121e9f08f` |
| #640 | El bug de #210 (lease sin filtrar por item) sigue activo hoy: #100 y #86 huerfanos vivos, invisibles a reap-stuck | 2026-08-28 15:22:03 | 2026-08-28 22:39:28 | 7.3 | `78d3ba594b29c97c7f4f0510547f2e766a46e401` |
| #641 | Workers de cola caídos: 0 procesos activos, 606 jobs pendientes (el más viejo desde 2026-05-27) | 2026-08-28 15:22:03 | 2026-08-28 22:44:27 | 7.4 | `5b45d4a4d090a6ef798002f7ef941af3c37f3f60` |
| #643 | Items #57 y #182 atorados por anti-bucle (3 escalaciones repetidas, misma causa): investigar causa raíz y destrabar | 2026-08-28 16:14:54 | 2026-08-28 23:01:47 | 6.8 | `8ca74192c0d7a0e317d24ba3547261b9ff47c36c` |
| #647 | Barrido de comandos y servicios registrados que nada invoca: motores terminados y apagados | 2026-08-28 15:16:04 | 2026-08-28 15:27:16 | 0.2 | `e13cc06d08c33c4254b07abcc883dfe035f55710` |
| #648 | Torre: pestaña «Configuración» — consolida los engranes y hace gobernables las fronteras duras | 2026-08-27 15:00:08 | 2026-08-27 15:24:51 | 0.4 | `1e3ed84e8fa73a0eb42e3efd5749e7f5c716994e` |
| #650 | Renombrar ThomasService a JARVIS en el CODIGO (interno, sin prisa) | 2026-08-28 09:02:03 | 2026-08-28 09:17:28 | 0.3 | `def79a0f31f6b4dcf916459e9f239ebe4bb8e88a` |
| #651 | JARVIS · identidad: selector de icono en la pestaña Configuracion + estado en la burbuja | 2026-08-27 16:20:06 | 2026-08-27 16:58:56 | 0.6 | `ebf0d7c100b754b466aa8877b2562436f09e2bf5` |
| #653 | Vigilia de Jarvis: medición de tamaño de log ciega tras el cambio a canal daily (#175) | 2026-08-28 15:24:06 | 2026-08-28 23:04:53 | 7.7 | `3bb63d146a3b2c5bd45aa83f8313f7a57ccf0933` |
| #654 | Backfill users huérfanos — comando de diagnóstico (dry-run) en dev | 2026-08-28 15:30:15 | 2026-08-28 16:16:39 | 0.8 | `70a1f8e2ffb50dc1abc0f9cd34f09d367323e071` |
| #655 | Backfill users huérfanos — comando idempotente + tabla de auditoría/rollback | 2026-08-28 16:14:41 | 2026-08-28 16:54:52 | 0.7 | `ca30a63e5cf13c44488bd7d37c1d34ddc3d98fe3` |
| #656 | Backfill users huérfanos — rollback + runbook de ejecución manual en prod | 2026-08-28 16:14:37 | 2026-08-28 16:58:17 | 0.7 | `ad0290217882211042a07620c7daff03f9d20aa6` |
| #659 | Apps permitidas real en la Vista Hijo: UI admin de parental_app_blocks + endpoint hijo + reemplazar grid hardcodeado | 2026-08-28 15:34:17 | 2026-08-28 17:55:58 | 2.4 | `b836568d4f071cbb33be8fd3d44615eb5e305b5d` |
| #665 | DocumentaciónCorporativa — Fase 3: inventarios de activos físicos, digitales y accesos | 2026-08-28 18:31:12 | 2026-08-28 22:40:55 | 4.2 | `545fa52b5541dd3ef98b691237da944f7ad6c5dd` |
| #666 | DocumentaciónCorporativa — Fase 4: concesiones, permisos y calendario regulatorio | 2026-08-28 16:16:08 | 2026-08-28 17:51:39 | 1.6 | `d999c6e3f51a7c7d3e00c730870010f632a947e0` |
| #670 | Auditoría/diseño V-SOL (driver OLT) — retomar tras ZTE | 2026-08-28 16:08:13 | 2026-08-28 16:12:17 | 0.1 | `c8fceafe88f7c0723e90e170f12284e4de2a6e62` |
| #673 | Pieza 2 — Que la válvula ablande la frontera dura ('requiere_irving') en vez de apagarla ('pasa') | 2026-08-28 18:31:38 | 2026-08-28 23:39:18 | 5.1 | `96ee65232619d1bb835d5d929a69ed4c6653b080` |
| #674 | Pieza 3 — Medir cuánto se equivoca la autodeclaración (reversible/confianza vs. reverts reales) | 2026-08-28 18:31:34 | 2026-08-28 23:46:41 | 5.3 | `1237650da70c7dbf3a97c67ded565330f9a4df80` |
| #675 | Pieza 4 — Que la Torre distinga control verificado de autodeclaración | 2026-08-28 18:31:20 | 2026-08-29 00:28:45 | 6 | `754da20ef5b793c70bb1ca333ce33a97cb96ff22` |
| #676 | Mikrotik sync: cola de reintentos con backoff exponencial para servicios failed/pending | 2026-08-28 18:31:22 | 2026-08-28 18:39:46 | 0.1 | `aa6ba8e22ac9d681da9fefa3f5b2b3d202355762` |
| #682 | Hallazgo: `migrate:fresh` está roto en el repo y deja `megaisp_test` a medias para todas las terminales | 2026-08-28 18:28:49 | 2026-08-28 18:32:42 | 0.1 | `037e9ec6599bc779a7ca781ecaf5fc4760da6263` |
| #684 | Inventario: depurar el tipo de artículo 'POWER' (catálogo mezclado) | 2026-08-28 18:31:27 | 2026-08-28 18:41:42 | 0.2 | `a7a93e0d6085717ff967f5f608bf24d398920c80` |
| #685 | Seguimiento: pregunta sin resolver de #224 | 2026-08-28 18:29:52 | 2026-08-28 18:50:27 | 0.3 | `a4108eca9480dcfbc1e6666882cd712f4e695392` |
| #686 | Flotas — Predicción de próximos servicios (historial + km) | 2026-08-28 19:23:54 | 2026-08-28 19:32:33 | 0.1 | `ad252388d910a4f331c7670993c25ce13ff94b17` |
| #688 | Flotas — Análisis comparativo de gastos | 2026-08-28 18:30:00 | 2026-08-28 19:26:41 | 0.9 | `e4743f1a14c7334b2f719063d6b99cbbec7b1358` |
| #693 | Talento comisión-KPI — Fase C: catálogo de reglas (Irving debe fijar roles/KPIs/fórmula/piloto antes de código) | 2026-08-28 18:30:10 | 2026-08-28 18:43:37 | 0.2 | `6fa9ea62e3cbe9b99816e1d683fa2dffcde8db14` |
| #694 | Talento comisión-KPI — Fase B: motor de cálculo READ-ONLY (simulación/preview, sin aplicar movimientos) | 2026-08-28 19:23:39 | 2026-08-28 19:30:38 | 0.1 | `c12dd197b2ac44e94c53096bbc685fc13d7aa289` |
| #695 | Talento comisión-KPI — Fase A: motor activo + evaluación real de clawback | 2026-08-28 19:23:34 | 2026-08-28 19:34:04 | 0.2 | `4d6b85ec6245adef2869debf515d3d1f85e2a607` |
| #696 | Seguimiento: pregunta sin resolver de #623 | 2026-08-28 18:30:18 | 2026-08-28 18:41:59 | 0.2 | `4363b3f452aa0556872add50f8d7a29c061e7ec0` |
| #697 | Talento: catálogo de reglas KPI para roles no-técnicos — definiciones pendientes de Irving | 2026-08-28 18:30:20 | 2026-08-28 18:47:00 | 0.3 | `53315e5a2db9d4056bf3ece2fa18c51081880cc3` |
| #698 | Talento: motor de cálculo KPI en modo READ-ONLY (preview, sin pagar) | 2026-08-28 18:30:22 | 2026-08-28 18:49:47 | 0.3 | `44862ab1e1936e6ba72078eb322575437995e008` |
| #699 | Mikrotik: Fase 1 — detección de disponibilidad (polling, sin disparar sync) | 2026-08-28 17:06:26 | 2026-08-28 17:22:34 | 0.3 | `2c2f9d2a2b6b3e114fe03f4e29e5986190f49b8a` |
| #700 | Talento: activar motor de comisión-KPI + clawback real (pago y reversión) | 2026-08-28 18:30:24 | 2026-08-28 18:53:37 | 0.4 | `c902ba5d1a4f2f9fb60ea991459336b43df6a501` |
| #701 | Mikrotik: Fase 2 — disparo de sync masivo al reconectar (bloqueado por #676) | 2026-08-28 18:30:27 | 2026-08-28 19:51:09 | 1.3 | `ef7015f33c6647e24ce90d3d49ed5f330450c972` |
| #702 | Mikrotik Fase 1a: migración aditiva para trackear último estado de conexión por router | 2026-08-28 17:14:14 | 2026-08-28 17:52:57 | 0.6 | `2ea80f167d6a565c63cb5b5665220eb8de363bea` |
| #703 | Mikrotik Fase 1b: detectar transición offline→online en mikrotik:sync-ping y loguearla (sin sync) | 2026-08-28 17:14:23 | 2026-08-28 17:57:03 | 0.7 | `e7691d7ed771467e19a22ebcb999860c9d0691f2` |
| #704 | Vigilante on-box (#208) — invariantes de Reclamos: claimed_at sin updated_at, en_progreso sin worker_sid, en_progreso sin proceso vivo | 2026-08-28 17:16:08 | 2026-08-29 00:35:06 | 7.3 | `ded75fdd61f30061544cc88488dc365d0e99ad1e` |
| #706 | Vigilante on-box (#208) — invariante de Git (HEAD desatado en worktrees) y Gasto (invocaciones claude -p/hora) | 2026-08-28 17:16:28 | 2026-08-29 00:51:48 | 7.6 | `a056f830229e7bafc72bca4a25bebeaf4b65cd11` |
| #707 | Vigilante on-box (#208) — publicar en tablero de compuertas + canal de alerta fuera de la Torre + hombre-muerto visible en Torre | 2026-08-28 18:30:29 | 2026-08-29 01:13:00 | 6.7 | `4001431b3ff20a0b91be22043dc89ef1f75f2523` |
| #708 | Migración aditiva: columnas mikrotik_connectivity_status y mikrotik_status_changed_at en routers | 2026-08-28 17:28:09 | 2026-08-28 18:04:54 | 0.6 | `ee32e7fddc137749f7c71496b1152dee2fe7f442` |
| #709 | Agregar mikrotik_connectivity_status y mikrotik_status_changed_at a $fillable de Router.php (si aplica) | 2026-08-28 17:28:14 | 2026-08-28 18:04:54 | 0.6 | `ee32e7fddc137749f7c71496b1152dee2fe7f442` |
| #710 | Circuito: 'jarvis-ya-decidido' re-aprueba items bloqueados por anti-bucle sin verificar si el bloqueo sigue vigente | 2026-08-28 17:36:08 | 2026-08-28 18:57:25 | 1.4 | `2911d7183204305634ffbbb6b0f3925848bed900` |
| #711 | Thomas Parte 1 — Conocimiento vivo del sistema (índice derivado y auto-regenerado) | 2026-08-28 18:30:33 | 2026-08-29 01:22:28 | 6.9 | `adf9e2559a606eb8c6ee6d2ba5b5134c9d4743c5` |
| #712 | Thomas Parte 2 — Freno de sequía de dos niveles + generación continua de barridos (BLOQUEADO por #232) | 2026-08-28 18:30:31 | 2026-08-29 01:36:54 | 7.1 | `aa1eb536b09ca278068f55ebb8d92c049a3cc9de` |
| #715 | Ejecutar migración aditiva: mikrotik_connectivity_status y mikrotik_status_changed_at en routers | 2026-08-28 18:02:13 | 2026-08-28 18:24:24 | 0.4 | `7d8410d2f3fc7f230a55cf4f9a104baf0984aae3` |
| #717 | Ejecutar migración aditiva mikrotik_connectivity_status/mikrotik_status_changed_at en routers (retry directo) | 2026-08-28 18:16:12 | 2026-08-28 18:26:39 | 0.2 | `3f4d25484ef973c639c39c66ed776b2e9a68244e` |
| #723 | Unificación invoices/client_invoices — Fase 4: migrar lectores de bajo riesgo a invoices | 2026-08-28 22:02:05 | 2026-08-28 22:36:04 | 0.6 | `7bac50a50428dcff412fe3d2367f39f7fba7b13e` |
| #727 | Bug de reporte: War Room (KpiController) filtra mes actual sobre payment_date VARCHAR d/m/Y | 2026-08-28 18:48:10 | 2026-08-28 18:50:43 | 0 | `dd3b17906801c2b90c1ce4e35738e37462e765f3` |
| #728 | Fase 1.1 — Fuentes de datos vivos Apartado IV (finanzas): cartera, saldos, antigüedad, proveedores, cuentas por pagar, créditos, ingresos, conciliaciones | 2026-08-28 19:22:43 | 2026-08-28 23:32:20 | 4.2 | `13ebaa52a7b4b54b573ff70e04504e3b21c6d5a5` |
| #729 | Fase 1.2 — Clasificación de proveedores para Apartado X (10 conceptos) reusando finanzas.proveedores | 2026-08-28 19:22:46 | 2026-08-28 23:46:24 | 4.4 | `1f21a545c44cae8d888e6393eb3d007d01b2895d` |
| #730 | Fase 1.3 — Fuentes talento.plantilla y talento.externos para Apartado VII (8 conceptos: plantilla, prestadores, técnicos, programadores, contadores, consultores, capacitadores) | 2026-08-28 19:22:49 | 2026-08-28 23:51:21 | 4.5 | `6794e224775931066be2afd488e41a97172f7c09` |
| #731 | Fase 1.4 — Fuentes flotas.vehiculos (mapa Leaflet) y red.equipos para Apartado V parcial (conceptos 40-41) | 2026-08-28 19:22:54 | 2026-08-28 23:56:40 | 4.6 | `e1d1081714190780414db8a6de7f57f5adbd3ccc` |
| #733 | Seguimiento: pregunta sin resolver de #218 | 2026-08-28 19:23:00 | 2026-08-28 19:27:34 | 0.1 | `3a9905a260bf482abd7409b0116af6ce22dd80f4` |
| #735 | DocumentacionCorporativa Fase 2b — Bandeja de pendientes: responsables, fecha compromiso y recordatorios | 2026-08-28 19:23:08 | 2026-08-29 00:51:16 | 5.5 | `1e8fb36940f5ba9d17bad908a1867c04d41c535a` |
| #736 | DocumentacionCorporativa Fase 2c — Registros estructurados: accionistas, capital, actas, poderes y contratos | 2026-08-28 19:23:19 | 2026-08-29 01:41:39 | 6.3 | `389a4564253bfa94d2940ab1c0a66c729caf631a` |
| #737 | DocumentacionCorporativa Fase 2d — Plantillas: organigrama, estructura accionaria, relación de activos/pasivos y carátula | 2026-08-28 19:10:22 | 2026-08-29 01:50:57 | 6.7 | `a94bf70b4cd2c8409e436466609f2f7284e9cfeb` |
| #741 | Seguimiento: pregunta sin resolver de #733 | 2026-08-28 21:41:32 | 2026-08-28 22:08:15 | 0.4 | `4409fe3bc27349926e98ae1b851973c04fb7b68a` |
| #742 | P0 no-mergeados: inventario crudo desde logs de vueltas | 2026-08-28 19:54:07 | 2026-08-29 02:36:24 | 6.7 | `aff9dab2ef9404c76270275f71e06f01749cf686` |
| #743 | P0 no-mergeados: tabla de auditoría para que Irving decida cuáles reconstruir | 2026-08-29 02:50:03 | 2026-08-29 03:01:42 | 0.2 | `15504c5004be661317fe2dbdba81668811603055` |
| #744 | P0 no-mergeados: mecanismo de reconstrucción (reabre_item_id) — construir, NO ejecutar aún | 2026-08-28 19:54:30 | 2026-08-29 02:50:39 | 6.9 | `2e58c854f2dfaabe54fd5aa5b49fe539a58410ce` |
| #745 | [RESPUESTA] DocumentaciónCorporativa — Fase 2 — bucle reap/escalación en paraguas ya descompuesto | 2026-08-28 22:33:32 | 2026-08-28 22:36:08 | 0 | `0e3c4c7925010167c3d2fcaa9c841ed975cb7267` |
| #746 | Guard de aprobación fresca en elegibleAutoMerge (Jarvis): bloquear auto-merge si la rama recibió commits después de revisado_at | 2026-08-28 22:10:09 | 2026-08-29 03:05:39 | 4.9 | `5afb4c31587cbd2a582d7249d33a472771c74c44` |
| #748 | Unificación invoices/client_invoices — Fase 3a: migración aditiva + comando de backfill en modo dry-run (solo reporte) | 2026-08-28 22:36:05 | 2026-08-28 23:08:07 | 0.5 | `ff1702478871193eaea652e902c10b465d550c37` |
| #757 | Circuito: aprobarYaDecidido() resetea excluir_pool_automatico aunque el parqueo NO fuera por decisión pendiente (bucle en paraguas #722) | 2026-08-28 22:56:29 | 2026-08-28 23:21:46 | 0.4 | `b134e87f2aadc762228d8a2156a4edb4c2c7c5b2` |


## No verificables

No se pudo confirmar automáticamente (sin fecha de aprobación, SHA inválido, o el merge commit no
tiene 2 padres reales) — requieren revisión manual directa.

| Item | Título | Motivo |
|---|---|---|
| #161 | Portal de Pago: catálogo banco→clave Banxico para CEP | No se pudo leer el padre 2 del merge commit (ref inválida, podada, o no es un merge real de 2 padres). |
| #209 | El boton Agregar item de la Torre revienta y crea el item igual: eta_minutos/eta_asignada_at no existen en roadmap_items y no hay migracion | No se pudo leer el padre 2 del merge commit (ref inválida, podada, o no es un merge real de 2 padres). |
| #689 | RN Panel del Padre: pantalla Horario de uso + limite de tiempo (megafamilia-rn) | merge_commit no tiene forma de SHA válido. |
