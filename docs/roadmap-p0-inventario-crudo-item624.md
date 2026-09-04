# P0 no-mergeados: inventario crudo desde logs de vueltas (item #742, fase 1 de #624)

Generado: 2026-08-29T08:35:40+00:00 por wt-2 (circuito on-box, solo lectura).

**Alcance:** solo lectura. Este documento SOLO inventaria ids candidatos + su evidencia de log; NO juzga si el item se completo, se rechazo o se abandono a medias — eso queda para la fase 2 (sub-item hermano de este, aun no creado a la fecha de este documento).

## Metodologia

- **Fuente:** `/home/meganet/circuito/logs/vuelta-*.log`
- **Ventana pedida por el item:** 2026-06-29 a 2026-08-22 (rango declarado en el spec del item #742, ver #177)
- **Ventana con evidencia real disponible:** 2026-08-18 a 2026-08-22 (los logs de vuelta anteriores a esa fecha NO existen en este servidor; verificado con find/ls, sin archivo mas antiguo ni respaldo comprimido) — la parte 2026-06-29..2026-08-17 del rango pedido no tiene evidencia de log disponible
- **Archivos analizados:** 397 (con bloque `CIRCUITO_META` estructurado: 301)
- **Extraccion:**
  - Fuente primaria (confiable): campo items_tocados de la linea CIRCUITO_META: {...} de cada vuelta (estructurado, confiable)
  - Fuente secundaria (prosa): menciones en prosa del patron #NNN (3-4 digitos) en el cuerpo del log
  - Filtro de falsos positivos: se descartan matches de #NNN precedidos (60 caracteres) por las palabras reporte/nota/decision/consulta/hallazgo/constancia/informe/registro/bitacora — esos numeros NO son ids de roadmap, son un numero de referencia narrativo que el propio modelo inventa en su resumen (ej. 'registre la decision (reporte #1900)'); confirmado en el codigo (no existe tabla ni contador con esos valores) y por muestreo manual: TODOS los matches de 4 digitos >1078 encontrados en los logs seguian ese patron y fueron excluidos
  - Filtro de rango base: se descartan ids <=168 (fila del snapshot de restauracion pre-incidente, ver migracion item177) y cualquier id que SI tenga un commit "Integra circuito #N (...) a main" en git log --all (527 ids unicos, ver item177) — esos ya estan recuperados y marcados excluir_pool_automatico=true

### ⚠️ Caveat para la fase 2 — reuso de ids tras el incidente

IMPORTANTE para la fase 2 (no juzgar por esto, solo tenerlo presente): tras el incidente P0 el autoincrement de roadmap_items se reinicio y los ids nuevos posteriores al 2026-08-24 REUSAN numeros que ya habian pertenecido a items reales pre-incidente. De los 96 ids candidatos de este inventario, 11 (186, 308, 463, 485, 528, 541, 542, 544, 559, 566, 595) EXISTEN HOY en la tabla roadmap_items pero corresponden a un item DISTINTO y sin relacion (verificado en tinker: p.ej. el id=308 actual es '[Reconstruido #248] ...' del propio item177, nada que ver con el #308 original mencionado en los logs del 2026-08-20 como bloqueador de #463). Fase 2 NO debe usar 'existe en BD con este id' como señal de que un candidato ya fue recuperado.

## Resumen

- **Ids candidatos (no mergeados, dentro de la ventana con evidencia):** 96
- **Total de menciones en logs:** 1333
- **Ids con una sola mencion (evidencia debil, revisar con mas cuidado en fase 2):** 2

## Tabla de candidatos

Snippet de la PRIMERA mencion encontrada (cronologicamente por archivo). El detalle completo (todas las ocurrencias, archivo+fecha+hora+sid+snippet) esta en el JSON hermano `docs/roadmap-p0-inventario-crudo-item624.json`, clave `candidatos.{id}.ocurrencias`.

| id | menciones | reusado en BD actual | primera mencion (archivo / fecha hora) | snippet |
|---|---|---|---|---|
| #186 | 16 | ⚠️ SI | `vuelta-20260820-154603-wt-3.log` / 2026-08-20 15:46:04 | [15:46:04] modo POR-ITEM: trabajando SOLO el item #186 |
| #254 | 4 | no | `vuelta-20260820-160403-wt-3.log` / 2026-08-20 16:27:37 | [16:27:37] Pool continuo: worker wt-3 toma de inmediato el siguiente item #254. |
| #308 | 33 | ⚠️ SI | `vuelta-20260820-154603-wt-4.log` / 2026-08-20 15:46:04 | trado. No hice commits ni toqué código: #463 sigue bloqueado por la falta del merge de #308 a main, algo fuera de mi alcance (ítem ajeno, de… |
| #463 | 48 | ⚠️ SI | `vuelta-20260820-154603-wt-4.log` / 2026-08-20 15:46:04 | [15:46:04] modo POR-ITEM: trabajando SOLO el item #463 |
| #485 | 3 | ⚠️ SI | `vuelta-20260818-132002-wt-3.log` / 2026-08-18 13:20:04 | [13:20:04] modo POR-ITEM: trabajando SOLO el item #485 |
| #528 | 3 | ⚠️ SI | `vuelta-20260820-155602-wt-2.log` / 2026-08-20 15:56:03 | [15:56:03] modo POR-ITEM: trabajando SOLO el item #528 |
| #541 | 5 | ⚠️ SI | `vuelta-20260820-160403-wt-3.log` / 2026-08-20 16:05:52 | [16:05:52] Pool continuo: worker wt-3 toma de inmediato el siguiente item #541. |
| #542 | 3 | ⚠️ SI | `vuelta-20260820-160603-wt-5.log` / 2026-08-20 16:22:57 | [16:22:57] Pool continuo: worker wt-5 toma de inmediato el siguiente item #542. |
| #544 | 4 | ⚠️ SI | `vuelta-20260820-160603-wt-6.log` / 2026-08-20 16:21:23 | [16:21:23] Pool continuo: worker wt-6 toma de inmediato el siguiente item #544. |
| #559 | 4 | ⚠️ SI | `vuelta-20260819-155202-wt-1.log` / 2026-08-19 15:52:03 | el "motor auditor auto-encadenado", pero ese motor **ya existe y está vivo**: el item #559 ("Motor de Auditoría Continua") ya implementó `Au… |
| #566 | 1 | ⚠️ SI | `vuelta-20260819-184202-wt-1.log` / 2026-08-19 18:42:03 | esta vía: los items creados desde la Torre se auto-aprueban por la regla preexistente #566 ("crear es aprobar"), fuera de alcance de este it… |
| #595 | 3 | ⚠️ SI | `vuelta-20260820-160603-wt-4.log` / 2026-08-20 16:16:07 | [16:16:07] Pool continuo: worker wt-4 toma de inmediato el siguiente item #595. |
| #794 | 6 | no | `vuelta-20260818-172101-wt-2.log` / 2026-08-18 17:21:02 | ()` se repartió en 3 sub-items (#792 IA, #793 WhatsApp/CobranzaBlaster/MikroTik, #794 resto) con inventario exacto por archivo para ejecutar… |
| #795 | 16 | no | `vuelta-20260818-173803-wt-1.log` / 2026-08-18 17:44:39 | [17:44:39] Pool continuo: worker wt-1 toma de inmediato el siguiente item #795. |
| #811 | 19 | no | `vuelta-20260818-185603-wt-5.log` / 2026-08-18 19:07:51 | [19:07:51] Pool continuo: worker wt-5 toma de inmediato el siguiente item #811. |
| #820 | 5 | no | `vuelta-20260818-185603-wt-5.log` / 2026-08-18 19:09:17 | [19:09:17] Pool continuo: worker wt-5 toma de inmediato el siguiente item #820. |
| #835 | 4 | no | `vuelta-20260818-192804-wt-2.log` / 2026-08-18 19:30:07 | [19:30:07] Pool continuo: worker wt-2 toma de inmediato el siguiente item #835. |
| #836 | 4 | no | `vuelta-20260819-154101-wt-1.log` / 2026-08-19 15:41:02 | [15:41:02] modo POR-ITEM: trabajando SOLO el item #836 |
| #842 | 10 | no | `vuelta-20260819-154702-wt-2.log` / 2026-08-19 15:52:49 | [15:52:49] Pool continuo: worker wt-2 toma de inmediato el siguiente item #842. |
| #850 | 6 | no | `vuelta-20260819-155502-wt-4.log` / 2026-08-19 15:55:03 | o (`completado`) con reporte coloquial y enlace de revisión, se registró el sub-item #850 con la deuda real encontrada, y la integración que… |
| #853 | 5 | no | `vuelta-20260820-160603-wt-4.log` / 2026-08-20 16:23:53 | [16:23:53] Pool continuo: worker wt-4 toma de inmediato el siguiente item #853. |
| #857 | 2 | no | `vuelta-20260819-182302-wt-1.log` / 2026-08-19 18:23:03 | [18:23:03] modo POR-ITEM: trabajando SOLO el item #857 |
| #862 | 5 | no | `vuelta-20260819-183702-wt-2.log` / 2026-08-19 18:37:03 | rado con reporte coloquial y enlace de revisión poblados; además se creó el sub-item #862 (limpieza definitiva a 30 días) que exigía el prop… |
| #866 | 3 | no | `vuelta-20260819-185802-wt-2.log` / 2026-08-19 18:58:03 | [18:58:03] modo POR-ITEM: trabajando SOLO el item #866 |
| #867 | 4 | no | `vuelta-20260819-185802-wt-2.log` / 2026-08-19 19:29:41 | [19:29:41] Pool continuo: worker wt-2 toma de inmediato el siguiente item #867. |
| #874 | 4 | no | `vuelta-20260820-141301-wt-1.log` / 2026-08-20 14:13:03 | [14:13:03] modo POR-ITEM: trabajando SOLO el item #874 |
| #875 | 19 | no | `vuelta-20260820-141301-wt-2.log` / 2026-08-20 14:13:03 | [14:13:03] modo POR-ITEM: trabajando SOLO el item #875 |
| #876 | 22 | no | `vuelta-20260820-141301-wt-3.log` / 2026-08-20 14:13:03 | [14:13:03] modo POR-ITEM: trabajando SOLO el item #876 |
| #877 | 15 | no | `vuelta-20260820-141301-wt-2.log` / 2026-08-20 14:23:06 | [14:23:06] Pool continuo: worker wt-2 toma de inmediato el siguiente item #877. |
| #879 | 13 | no | `vuelta-20260820-141301-wt-1.log` / 2026-08-20 14:13:03 | Épica #874 (paraguas, sin código) descompuesta en 7 sub-items de fase (#879-#885, Fase 4 ya existía como #852) y cerrada como completado. |
| #881 | 37 | no | `vuelta-20260820-141301-wt-1.log` / 2026-08-20 14:13:03 | Épica #874 (paraguas, sin código) descompuesta en 7 sub-items de fase (#879-#885, Fase 4 ya existía como #852) y cerrada como completado. |
| #882 | 10 | no | `vuelta-20260820-141301-wt-1.log` / 2026-08-20 14:13:03 | Épica #874 (paraguas, sin código) descompuesta en 7 sub-items de fase (#879-#885, Fase 4 ya existía como #852) y cerrada como completado. |
| #883 | 37 | no | `vuelta-20260820-141301-wt-1.log` / 2026-08-20 14:13:03 | Épica #874 (paraguas, sin código) descompuesta en 7 sub-items de fase (#879-#885, Fase 4 ya existía como #852) y cerrada como completado. |
| #888 | 16 | no | `vuelta-20260820-160403-wt-3.log` / 2026-08-20 16:39:04 | [16:39:04] Pool continuo: worker wt-3 toma de inmediato el siguiente item #888. |
| #892 | 13 | no | `vuelta-20260820-160603-wt-4.log` / 2026-08-20 16:27:06 | [16:27:06] Pool continuo: worker wt-4 toma de inmediato el siguiente item #892. |
| #909 | 8 | no | `vuelta-20260820-160603-wt-5.log` / 2026-08-20 16:16:10 | on the branch, item parked correctly awaiting Irving's manual merge (nivel C), sub-item #909 registered for the remaining ~30 buttons. |
| #915 | 3 | no | `vuelta-20260820-160403-wt-3.log` / 2026-08-20 16:24:53 | El item quedó correctamente como **paraguas**: tiene 1 sub-item abierto (#915, "cablear UI de tickets en la app RN"), que es trabajo fuera d… |
| #916 | 7 | no | `vuelta-20260820-155203-wt-1.log` / 2026-08-20 16:19:51 | a), evitando además duplicar ese endpoint. La UI en APK queda registrada como sub-item #916 con la ubicación exacta y precisa del bloqueo (a… |
| #919 | 2 | no | `vuelta-20260820-191103-wt-3.log` / 2026-08-20 19:24:47 | Resumen: migré los 4 casos de #921 al mecanismo `agendado_para`. #919 recibió `agendado_para=2026-09-20` (confirmé que HOY sí era despachabl… |
| #920 | 12 | no | `vuelta-20260820-163804-wt-5.log` / 2026-08-20 16:38:05 | state — the platform's own mechanism, since I registered follow-up UI work as sub-item #920, which will auto-close #177 once #920 completes.… |
| #922 | 9 | no | `vuelta-20260820-173402-wt-2.log` / 2026-08-20 17:50:38 | [17:50:38] Pool continuo: worker wt-2 toma de inmediato el siguiente item #922. |
| #930 | 31 | no | `vuelta-20260820-173402-wt-3.log` / 2026-08-20 17:51:35 | y componentes `torre-control/*.vue`) y dejé todo documentado en el sub-item **#930**, que se retomará cuando #881 esté validada y con la con… |
| #932 | 1 | no | `vuelta-20260820-202102-wt-4.log` / 2026-08-20 20:21:03 | recomendación, y pasé #938 a `requiere_irving` sin tocar código ni cerrar #932/#934 (que quedan como referencia hasta que se resuelva). |
| #939 | 7 | no | `vuelta-20260820-182202-wt-1.log` / 2026-08-20 18:38:28 | - **#939** — backend: orden real de la cola + endpoint `GET /torre/cola` (solo lectura), con la spec apuntando a la lógica ya buena que exis… |
| #952 | 25 | no | `vuelta-20260820-182402-wt-4.log` / 2026-08-20 19:00:05 | Item #811 no cabía en una vuelta (circuito:cabida NO CABE); descompuesto en 8 sub-items (#949-#956) por fase, sin tocar código. |
| #953 | 108 | no | `vuelta-20260820-182402-wt-4.log` / 2026-08-20 19:00:05 | Item #811 no cabía en una vuelta (circuito:cabida NO CABE); descompuesto en 8 sub-items (#949-#956) por fase, sin tocar código. |
| #954 | 27 | no | `vuelta-20260820-182402-wt-4.log` / 2026-08-20 19:00:05 | Item #811 no cabía en una vuelta (circuito:cabida NO CABE); descompuesto en 8 sub-items (#949-#956) por fase, sin tocar código. |
| #955 | 21 | no | `vuelta-20260820-182402-wt-4.log` / 2026-08-20 19:00:05 | Item #811 no cabía en una vuelta (circuito:cabida NO CABE); descompuesto en 8 sub-items (#949-#956) por fase, sin tocar código. |
| #956 | 27 | no | `vuelta-20260820-182402-wt-4.log` / 2026-08-20 19:00:05 | He descompuesto el item #811 (épica IPv6 de 5 fases) en 8 sub-items (#949-#956) siguiendo la estructura de fases/puntos de parada que ya tra… |
| #959 | 9 | no | `vuelta-20260820-184120-wt-2.log` / 2026-08-20 19:01:26 | , y la migración de los 4 casos reales incluyendo la renovación del certificado (#959). No toqué código ni hice commits esta vuelta. |
| #961 | 19 | no | `vuelta-20260820-185803-wt-6.log` / 2026-08-20 19:00:47 | No cabía en una vuelta (cabida=NO); descompuesto en 6 sub-items (inventario + 4 lotes de migración por permiso/riesgo + caso especial AuditR… |
| #962 | 10 | no | `vuelta-20260820-185803-wt-6.log` / 2026-08-20 19:00:47 | No cabía en una vuelta (cabida=NO); descompuesto en 6 sub-items (inventario + 4 lotes de migración por permiso/riesgo + caso especial AuditR… |
| #963 | 11 | no | `vuelta-20260820-185803-wt-6.log` / 2026-08-20 19:00:47 | No cabía en una vuelta (cabida=NO); descompuesto en 6 sub-items (inventario + 4 lotes de migración por permiso/riesgo + caso especial AuditR… |
| #964 | 11 | no | `vuelta-20260820-185803-wt-6.log` / 2026-08-20 19:00:47 | No cabía en una vuelta (cabida=NO); descompuesto en 6 sub-items (inventario + 4 lotes de migración por permiso/riesgo + caso especial AuditR… |
| #969 | 2 | no | `vuelta-20260820-191103-wt-3.log` / 2026-08-20 19:24:47 | 856 (Armar versión) lo verifiqué genuinamente resuelto, no lo toqué. Creé #969 (renovar certificado dev.meganett.com.mx) con la fecha real d… |
| #973 | 8 | no | `vuelta-20260820-202305-wt-6.log` / 2026-08-20 20:33:45 | [20:33:45] Pool continuo: worker wt-6 toma de inmediato el siguiente item #973. |
| #977 | 5 | no | `vuelta-20260820-202305-wt-5.log` / 2026-08-20 20:23:06 | formados para estado/municipio/colonia/sucursal) y lo dejé registrado como sub-item #977 para que se corrija aparte. |
| #978 | 5 | no | `vuelta-20260820-215802-wt-1.log` / 2026-08-20 21:58:03 | oles), lo integré (merge `8ab8b806` ya en `main`), y dejé registrado el sub-item #978 para retirar el permiso legado más adelante, tal como … |
| #981 | 30 | no | `vuelta-20260820-232603-wt-1.log` / 2026-08-20 23:36:06 | No cabía en una vuelta (ya había timeouteado antes); descompuesto en 3 sub-items (#980 servicio base 4 causas, #981 investigar 3 causas rest… |
| #984 | 46 | no | `vuelta-20260821-140403-wt-4.log` / 2026-08-21 14:04:04 | 949 no cabía en una vuelta; descompuesto en sub-items 984/985/986 (1.1/1.2/1.3) con PASO 0 resuelto y decisiones registradas, sin código toc… |
| #985 | 22 | no | `vuelta-20260821-140403-wt-4.log` / 2026-08-21 14:04:04 | 949 no cabía en una vuelta; descompuesto en sub-items 984/985/986 (1.1/1.2/1.3) con PASO 0 resuelto y decisiones registradas, sin código toc… |
| #986 | 19 | no | `vuelta-20260821-140403-wt-4.log` / 2026-08-21 14:04:04 | ales (#984 registro+permisos, #985 migraciones con las 7 tablas completas, #986 modelos+relaciones) que heredan toda esa investigación para … |
| #991 | 18 | no | `vuelta-20260821-152902-wt-2.log` / 2026-08-21 15:29:03 | No cabía en una vuelta; #954 sigue bloqueado por #953 (Fase 2) sin cerrar — descompuesto en 4 sub-items (#991-#994), sin tocar código ni inf… |
| #993 | 145 | no | `vuelta-20260821-152902-wt-2.log` / 2026-08-21 15:29:03 | No cabía en una vuelta; #954 sigue bloqueado por #953 (Fase 2) sin cerrar — descompuesto en 4 sub-items (#991-#994), sin tocar código ni inf… |
| #994 | 12 | no | `vuelta-20260821-152902-wt-2.log` / 2026-08-21 15:29:03 | No cabía en una vuelta; #954 sigue bloqueado por #953 (Fase 2) sin cerrar — descompuesto en 4 sub-items (#991-#994), sin tocar código ni inf… |
| #995 | 22 | no | `vuelta-20260821-155101-wt-2.log` / 2026-08-21 15:51:03 | - **#995** (4.1 búsqueda inversa) — solo lectura, sin dependencias, bajo riesgo, desbloqueado ya. |
| #996 | 11 | no | `vuelta-20260821-155101-wt-2.log` / 2026-08-21 15:51:03 | - **#996** (4.2 salud/alertas) — bloqueado de facto: su sub-bloque "deriva" depende del job de Fase 3.4, que vive en #954 (aún `pending`/`re… |
| #997 | 7 | no | `vuelta-20260821-155101-wt-2.log` / 2026-08-21 15:51:03 | #955 no cabía en una vuelta; descompuesto en 4 sub-items (#995-#998) con sus bloqueos/fronteras documentados, sin código ni rama. |
| #998 | 14 | no | `vuelta-20260821-155101-wt-2.log` / 2026-08-21 15:51:03 | - **#998** (4.4 prevuelo/plantillas/reversión) — toca equipos MikroTik reales, nivel C, requiere confirmación explícita antes de ejecutar co… |
| #1002 | 53 | no | `vuelta-20260821-161402-wt-2.log` / 2026-08-21 16:14:03 | - **#1002** — pantalla 4 (historial de despliegues), explícitamente bloqueada hasta que #949 aporte la persistencia real, para no duplicar e… |
| #1012 | 10 | no | `vuelta-20260821-171702-wt-4.log` / 2026-08-21 17:17:03 | [17:17:03] modo POR-ITEM: trabajando SOLO el item #1012 |
| #1013 | 4 | no | `vuelta-20260821-180403-wt-3.log` / 2026-08-21 18:04:04 | [18:04:04] modo POR-ITEM: trabajando SOLO el item #1013 |
| #1019 | 9 | no | `vuelta-20260821-171702-wt-4.log` / 2026-08-21 17:17:03 | #1012 no cabía en una vuelta (item padre con 5 piezas de arquitectura ya especificadas en el propio prompt); descompuesto en sub-items #1017… |
| #1031 | 13 | no | `vuelta-20260821-175002-wt-1.log` / 2026-08-21 17:54:49 | - **#1031** — Esquema de datos aditivo (tablas propias, sin FK a la fundación inexistente) |
| #1034 | 11 | no | `vuelta-20260821-175002-wt-1.log` / 2026-08-21 17:54:49 | - **#1034** — Enganche real con el módulo Addons/Ipv6 + simulacro de aceptación — explícitamente bloqueado hasta que #955 y #984/#985/#986 e… |
| #1039 | 8 | no | `vuelta-20260821-191403-wt-2.log` / 2026-08-21 19:21:38 | cabida NO CABE (histórico); descompuesto #984 en sub-items #1039 (crear+registrar módulo Ipv6+permisos) y #1040 (verificar+cerrar), sin toca… |
| #1040 | 9 | no | `vuelta-20260821-191403-wt-2.log` / 2026-08-21 19:21:38 | cabida NO CABE (histórico); descompuesto #984 en sub-items #1039 (crear+registrar módulo Ipv6+permisos) y #1040 (verificar+cerrar), sin toca… |
| #1042 | 6 | no | `vuelta-20260821-192602-wt-2.log` / 2026-08-21 19:46:08 | marcó el item como NO CABE en esta vuelta, así que lo descompuse en el sub-item #1042 con el diagnóstico completo y el fix ya diseñado (arch… |
| #1044 | 20 | no | `vuelta-20260821-195150-wt-3.log` / 2026-08-21 20:01:53 | - **#1044** — cliente/despliegues: `clientes_ipv6_prefijos`, `clientes_ipv6_config`, `clientes_ipv6_excepciones`, `ipv6_despliegues` (depend… |
| #1048 | 13 | no | `vuelta-20260821-201202-wt-2.log` / 2026-08-21 20:14:47 | Sub-items creados (#1047 viable ya, #1048 explícitamente bloqueado). El item #991 queda como paraguas, sin rama creada. Termino la vuelta. |
| #1052 | 6 | no | `vuelta-20260821-204302-wt-2.log` / 2026-08-21 20:46:43 | con datos simulados y en su lugar dejé todo registrado en el sub-item #1052 para retomarlo cuando esas fases cierren. |
| #1054 | 3 | no | `vuelta-20260821-215402-wt-1.log` / 2026-08-21 21:54:03 | All 4 sub-items created (#1054–#1057) under #998, covering the a/b/c/d breakdown with the hard-boundary notes and dependency on #949 already… |
| #1055 | 2 | no | `vuelta-20260821-215402-wt-1.log` / 2026-08-21 21:54:03 | 998 no cabía en una vuelta (histórico ~23min); descompuesto en 4 sub-items (#1054-#1057) para Fase 4.4a-d sin tocar código. |
| #1056 | 2 | no | `vuelta-20260821-215402-wt-1.log` / 2026-08-21 21:54:03 | 998 no cabía en una vuelta (histórico ~23min); descompuesto en 4 sub-items (#1054-#1057) para Fase 4.4a-d sin tocar código. |
| #1057 | 3 | no | `vuelta-20260821-215402-wt-1.log` / 2026-08-21 21:54:03 | All 4 sub-items created (#1054–#1057) under #998, covering the a/b/c/d breakdown with the hard-boundary notes and dependency on #949 already… |
| #1058 | 2 | no | `vuelta-20260821-221403-wt-1.log` / 2026-08-21 22:14:04 | - **#1058** — Backend: `Ipv6ConfigController` + rutas (routers/detectar-version/mapear-zonas/vista-previa). |
| #1060 | 2 | no | `vuelta-20260821-224402-wt-1.log` / 2026-08-21 22:44:03 | Ambos sub-items quedaron creados (#1060, #1061) con el detalle técnico ya investigado (rutas, servicios, drivers de versión). El item #1000 … |
| #1063 | 9 | no | `vuelta-20260821-232502-wt-1.log` / 2026-08-21 23:28:41 | 2 (agregar el tab "Historial" con banner informativo al wizard IPv6, accionable ya) y #1063 (la Pantalla 4 real con datos reales, que sigue … |
| #1066 | 5 | no | `vuelta-20260822-002702-wt-2.log` / 2026-08-22 00:27:03 | El item #1032 quedó como paraguas (no completado) — sus 3 sub-items (#1066, #1067, #1068) entrarán a triaje normal y quedan disponibles para… |
| #1068 | 5 | no | `vuelta-20260822-002702-wt-2.log` / 2026-08-22 00:27:03 | El item #1032 quedó como paraguas (no completado) — sus 3 sub-items (#1066, #1067, #1068) entrarán a triaje normal y quedan disponibles para… |
| #1069 | 3 | no | `vuelta-20260822-005602-wt-1.log` / 2026-08-22 00:56:03 | - **#1069** — servicio simulador + endpoint (backend, 4 fases RFC4192 + comandos generados) |
| #1074 | 5 | no | `vuelta-20260822-111803-wt-3.log` / 2026-08-22 11:28:06 | pv6_bloques` no existen en la BD de dev). Lo descompuse en dos sub-items propios (#1074 backend, #1075 frontend) con el bloqueo y el diseño … |
| #1075 | 5 | no | `vuelta-20260822-111803-wt-3.log` / 2026-08-22 11:28:06 | existen en la BD de dev). Lo descompuse en dos sub-items propios (#1074 backend, #1075 frontend) con el bloqueo y el diseño ya documentados,… |
| #1076 | 3 | no | `vuelta-20260822-122005-wt-2.log` / 2026-08-22 12:20:05 | -items antes de tocar nada real. Ejecuté exactamente eso: creé los sub-items #1076 (reglas firewall v6), #1077 (corte transaccional dual-sta… |
| #1077 | 3 | no | `vuelta-20260822-122005-wt-2.log` / 2026-08-22 12:20:05 | eal. Ejecuté exactamente eso: creé los sub-items #1076 (reglas firewall v6), #1077 (corte transaccional dual-stack v4+v6 atómico) y #1078 (p… |
| #1078 | 3 | no | `vuelta-20260822-122005-wt-2.log` / 2026-08-22 12:20:05 | reglas firewall v6), #1077 (corte transaccional dual-stack v4+v6 atómico) y #1078 (pruebas ping -4/-6), cada uno con el spec técnico complet… |

## Proximo paso

Fase 2 (sub-item hermano de #742, aun por crear): para cada uno de estos 96 ids, leer TODAS sus ocurrencias en el JSON, inferir el estado final probable (pending / rechazado / abandonado a medio camino / bloqueado) y decidir que hacer con cada uno — eso SI es juicio de negocio, fuera de alcance de esta fase 1.
