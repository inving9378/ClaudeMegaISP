# Estado — Facturación completa MegaISP (ejecución autónoma)

> Archivo de reanudación. **No es evidencia**: al retomar, comprobar Git, Roadmap y entorno reales.
> Solo lo edita la sesión de Facturación autónoma; entradas de otras sesiones no se sobrescriben.

- **Responsable:** sesión directa de Claude Code (cuenta de Irving), prompt «Facturación completa
  MegaISP — ejecución autónoma y alcance cerrado» (2026-10-16).
- **Entorno:** DEV `192.168.105.11`, BD `megaisp`. Producción **no** se toca ni se consulta.
- **Worktree propio:** `/var/www/megaisp-pf` (ramas `circuito/item-<id>-…`). Merges locales a `main`
  desde `/var/www/megaisp` comprobando `git branch --show-current` = `main`.
- **Coordinación:** comentario en #502 (2026-10-16 ~19:05). La interceptación de correo no se aplica
  al `.env` compartido hasta coordinar; se valida en proceso aislado.

## Mapa FAC → items (FASE 1, registrada 2026-10-16 ~19:00)

Todos con `excluir_pool_automatico=1`; los de desarrollo con `en_desarrollo_humano=1` (fuera del
pool y del reaper). Duplicados: no había ningún `FAC-`; #482 es el padre de FAC-04..08; #483 el de
FAC-15; #117 (PAC CFDI, implementación) se relaciona con FAC-15 sin ser equivalente.

| Orden | FAC | ID | Nuevo/reutilizado | Nivel | Estado | Padre | Dependencias | Decisión pendiente |
|---|---|---|---|---|---|---|---|---|
| 1 | FAC-01 | #506 | nuevo | A | aprobado_irving | — | — | Cuándo enviar la guía (Irving) |
| 2 | FAC-02 | #507 | nuevo | A | aprobado_irving | — | — | Cuándo/por dónde pedir CSF |
| 3 | FAC-03 | #508 | nuevo | B | aprobado_irving | — | — | URL real del aviso (la captura Irving) |
| 4 | FAC-04 | #509 | nuevo | B | aprobado_irving | #482 | #480/#505 en main | Activación vía FAC-08 |
| 5 | FAC-05 | #510 | nuevo | B | aprobado_irving | #482 | — | Retención/borrado → FAC-05b |
| 5b | FAC-05b | #511 | nuevo | C | requiere_irving | #510 | FAC-05 | Política de retención |
| 6 | FAC-06 | #512 | nuevo | B | aprobado_irving | #482 | — | N y regla de días → FAC-06b |
| 6b | FAC-06b | #513 | nuevo | C | requiere_irving | #512 | FAC-06 | Encender interruptor |
| 7 | FAC-07 | #514 | nuevo | C | requiere_irving | #482 | — | Fecha límite y ejecución en prod |
| 8 | FAC-08 | #515 | nuevo | C | requiere_irving | #482 | FAC-04/05/06 (desarrollo); FAC-07 ejecutado (activación) | Etapas y canales |
| 9 | FAC-09 | #516 | nuevo | B | aprobado_irving | — | — | Plantilla Meta → FAC-09b |
| 9b | FAC-09b | #517 | nuevo | C | requiere_irving | #516 | FAC-08 | Presentar plantilla / encender |
| 10 | FAC-10 | #518 | nuevo | B | aprobado_irving | — | #505 en main | — |
| 11 | FAC-11 | #519 | nuevo | B | aprobado_irving | — | — | — |
| 12 | FAC-12 | #520 | nuevo | C | requiere_irving | — | — | Si y cómo usar folio amigable |
| 13 | FAC-13 | #521 | nuevo | B | aprobado_irving | — | — | Activación → FAC-13b |
| 13b | FAC-13b | #522 | nuevo | C | requiere_irving | #521 | Certificación OpenPay | Encender |
| 14 | FAC-14 | #523 | nuevo | C | requiere_irving | — | — | Contratar revisión legal |
| 15 | FAC-15 | #524 | nuevo | C | requiere_irving | #483 | — | Decisiones fiscales con el contador |

## V1.61 — verificación inicial (solo lectura, 2026-10-16 ~18:55)

| Entregable | Código | En `main` | Tag | Dev | Prod |
|---|---|---|---|---|---|
| Prefactura v2 (#480, #497) | sí | sí (`933ab6d0`, `732cabc4`) | no | sí | no verificada |
| Ícono único (#505) | sí | sí (`52c9d68e`) | no | sí | no verificada |
| Columna Documento + «Tipo de factura» oculta (#505) | sí | sí (`52c9d68e`) | no | sí | no verificada |
| Badges claro/oscuro (#505) | sí | sí (`01195bb3`, `523408c8`) | no | sí (contraste medido) | no verificada |
| Método de pago por nombre (#505) | sí | sí (`01195bb3`) | no | sí | no verificada |
| ttyd 404/502 = no disponible (#504) | sí | sí (`28fc5729`) | no | sí (4 escenarios) | no verificada |

Sin tag ni release **a propósito** (la instrucción vigente no autoriza publicar).

**Pendientes de aplicación V1.61 (cerrados 2026-10-16 ~19:10):**
- [x] Exportación con filtro fijo (`0c81c3d7`, merge `ddb253fc`): bundle del principal recompilado
  (19:11) y validado: Excel de la ficha 7689 = 2 filas (BD: 2), con «Tipo de factura», Documento y
  Método por nombre. El navegador de prueba abortaba cualquier exportación sin `persistentFilter`.
- [x] #504 y #505 → `completado` con su merge (`28fc5729`, `ddb253fc`).
- [x] `docs/bitacora/2026-10-16-release-v161-preparacion.md`: sección de actualización ~19:12
  (notas vigentes, migraciones, checklist con Imprimir en pendiente y pagada).
- Pendiente fuera de mi alcance: publicar (no autorizado) y el preflight `arbol_limpio` (4
  bitácoras sin commitear de otras sesiones en el principal).

## Avance

**Completado (dev, en `main`, sin publicar):**

| FAC | ID | Commits | Merge | Estado |
|---|---|---|---|---|
| — | V1.61 pendientes | `0c81c3d7` (export), docs `852d0938` | `ddb253fc`, `7795cc56` | #504/#505 completados |
| — | Interceptación correo | `3ea55c32` `198cbc9a` | `e972a27e` | apagada; aplicación compartida pendiente (#502) |
| FAC-01 | #506 | `4a706d7c` | `052ad6d0` | completado |
| FAC-02 | #507 | `a3997f0a` | `ac9e462e` | completado |
| FAC-03 | #508 | `53b9825c` `cdbbdeb7` `4d775b27` | `34054ffd` | completado; migrado en dev |
| FAC-04 | #509 | `b9d3a5c5` `7c50b96a` `a40f4946` | `3917580d` | completado (activación = FAC-08) |
| FAC-05 | #510 | `9316cec9` `82520cff` `cb770c56` `0957425d` | `d63c6f31` | desarrollo hecho; padre aparcado (`aprobado_irving`) hasta FAC-05b #511 |
| FAC-06 | #512 | `831051f5` `fd4bb50f` `758f0d56` | `dbeff1da` | desarrollo hecho; padre aparcado hasta FAC-06b #513 |
| FAC-07 | #514 | `d9f07554` `97a5dc92` `9fc251e2` | `deb88456` | script listo; `requiere_irving` (fecha y ejecución en prod) |
| FAC-08 | #515 | `af8a3c36` `c6a623ab` | `b8691d3f` | compuerta lista (cerrada); `requiere_irving` (etapas) |
| FAC-09 | #516 | `4b3705b5` `fb3b7171` | `d99d6060` | desarrollo hecho (apagado); padre aparcado hasta FAC-09b #517 |

**Siguiente trabajo elegible:** FAC-10 (#518, Portal Cliente) → FAC-11 (#519) → FAC-12 (#520, doc)
→ FAC-13 (#521) → FAC-14 (#523, doc) → FAC-15 (#524, doc).

**Punto de reanudación:** worktree `/var/www/megaisp-pf`; rama nueva `circuito/item-518-…` desde
`main`; FAC-10 Paso 0 (Portal Cliente: `app/Modules/Addons/PortalCliente/Controllers/FacturasController.php`,
guard `cliente`, `PortalClientScope`). Pruebas con BD bloqueadas por #529.

## Bloqueos y decisiones

- **Muestras de correo:** antes de este programa se enviaron 2 pruebas al correo autorizado de Irving por
  pedido expreso de Irving (Prefactura normal y fallback, 2026-10-16). En este programa: **0**.
- **IA del circuito sin saldo** (Claude API 400 «credit balance is too low», 2026-10-16 17:12):
  `ClasificarRiesgoJob` y el des-trabador fallan cerrado; los FAC se clasificaron a mano.
- ⚠️ **Incidente #529:** una corrida de pruebas incluyó por error una prueba ajena con RefreshDatabase
  y el timeout dejó `megaisp_test` a medio migrar (207 tablas). No se reparó (sería migrate:fresh en BD
  compartida); pruebas con BD bloqueadas hasta que Irving la reconstruya. Lección en memoria.
- **5,879 de 7,314 prefacturas en espera en dev son de facturas ya pagadas** (FAC-04): consulta
  para prod en `docs/bitacora/2026-10-16-item-509.md`. Pesa en FAC-07/FAC-08.
- **Hallazgos registrados aparte** (no se ejecutan): menú «Finanzas › Facturas» apunta a
  `client_invoices`; exportación sin límite en `/finanzas/invoices`; «Estado» pierde color en
  oscuro; «marcar como pagada» visible en canceladas → **#525** (menú), **#526** (exportación sin
  límite), **#527** (marcar pagada en canceladas), **#528** (Estado en oscuro). Todos
  `requiere_irving`, fuera del pool.

## Muestras de correo (máx. 5 intentos por lote, acumulados)

| Clave | Lote | Intento | Registrada | Qué | Resultado |
|---|---|---|---|---|---|
| `FAC-MUESTRA-L1-01` | 1 | 1 | 2026-10-16 19:17 (antes de enviar) | Prefactura **sintética** (cliente ficticio, folio PRUEBA-0001), To/Cc/Bcc ficticios `@example.test`, interceptación activa SOLO en el proceso (env), destino = correo autorizado de Irving | 19:18 **aceptado por el SMTP de dev**; ensayo previo con transporte array: sobre con 1 solo destinatario, sin `example.test`, asunto `[DEV — PRUEBA] Tu prefactura noviembre 2026 — Meganet`, PDF 29 KB. Entrega en buzón: pendiente de que Irving confirme |

## Lotes de versión (borradores, sin número oficial)

| Lote | Archivo | Contenido |
|---|---|---|
| 1 | `docs/bitacora/2026-10-16-borrador-version-facturacion-lote1.md` | interceptación, FAC-01, FAC-02, FAC-03 |
| 2 | `docs/bitacora/2026-10-16-borrador-version-facturacion-lote2.md` | FAC-04, FAC-05, FAC-06 (unidad #482) |
| 3 | `docs/bitacora/2026-10-16-borrador-version-facturacion-lote3.md` | FAC-07, FAC-08, FAC-09 |

## Últimos commits de esta sesión

- `d99d6060` Integra FAC-09 a main (último merge de código).
