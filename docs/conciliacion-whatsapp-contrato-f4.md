# Conciliación de pagos por WhatsApp — Contrato F3 → F4 (item #204 FASE C)

Documenta, en un solo lugar, el contrato entre la identificación del cliente
(F3, `WhatsappIdentificationSession` + `IdentificationFsm`) y la aplicación
del pago (F4, `PaymentFromSessionService` + `ApplyIdentifiedPaymentJob`).
Consolida lo que ya vivía como comentarios inline en esas tres clases — no
inventa comportamiento nuevo.

## Qué entrega F3 a F4

Al llegar a `state = resolved`, la sesión (`whatsapp_identification_sessions`)
deja fijos:

| Campo | Significado |
|---|---|
| `resolved_client_id` | id del cliente identificado |
| `resolved_multiple_services` | `true` si ese cliente tiene más de un servicio (Tere debe elegir cuál) |
| `method` | cómo se identificó: `meg` \| `client_id` \| `name_single` \| `name_disambiguated` |
| `certainty` | ver tabla siguiente — **el campo que decide el camino en F4** |

## `certainty`: `exact` vs `proposed`

| certainty | Cuándo se asigna | Camino en F4 |
|---|---|---|
| `exact` | Referencia MEG leída del comprobante (`MegReferenceResolver`) — la existencia de la fila en `client_payment_references` YA es la verificación, no hay ambigüedad de nombre. | `isAutoApplicable() === true` → candidato a aplicación **automática**, sujeta ADEMÁS al freno maestro `payments.auto_apply_enabled` (default `false`, `config/payments.php`). Si el freno está apagado, la sesión resuelta `exact` se queda pendiente hasta que Irving decida encenderlo o hasta que alguien la confirme a mano. |
| `proposed` | Identificación por nombre (single match) o por nombre+calle (desambiguado) — es una propuesta de la IA, no una prueba dura. | Requiere **confirmación humana** (Tere) — nunca se auto-aplica sin importar el estado del freno maestro. |

`resolved_multiple_services = true` fuerza cola humana igual, aunque
`certainty` sea `exact` (Tere debe elegir el servicio, F4 no puede adivinar).

## Bifurcación en F4 (`ApplyIdentifiedPaymentJob` / `PaymentFromSessionService`)

```
sesión resuelta (pendingApplication: state=resolved, applied_at=null)
  │
  ├─ certainty=exact AND !resolved_multiple_services
  │     │
  │     ├─ payments.auto_apply_enabled = true  → apply() automático (confirmed_by=null, add_by=MEGAISP)
  │     └─ payments.auto_apply_enabled = false → NO se aplica; espera confirmación humana o activación del freno
  │
  └─ certainty=proposed OR resolved_multiple_services  → SIEMPRE cola de Tere (nunca evalúa el freno)
```

- `PaymentFromSessionService::apply(session, confirmedBy=null)` — vía automática; internamente exige `certainty=exact` (`isAutoApplicable()`) + freno encendido.
- `PaymentFromSessionService::applyConfirmed(sessionId, tereUserId)` — vía F6 (confirmación humana); **no la frena el flag maestro**, porque ya hubo ojo humano.
- 3 candados anti-duplicado (dinero), independientes de `certainty`: (1) `clave_rastreo` única en `payments.number`/`reported_payments.clave_rastreo`; (2) claim atómico `UPDATE applied_at WHERE null`; (3) una sola sesión activa por mensaje (idempotencia de F3).

## Cola humana (Tere) — `source`-aware

La cola (`/finanzas/conciliacion-cola`, `ReconciliationQueueController`) lee
directo `whatsapp_identification_sessions` (scopes `scopeProposedQueue` /
`scopeEscalatedQueue`), **no** la tabla `reconciliation_tickets` (esa es del
motor de cobro nativo/bancario, propósito distinto — decisión F6.1). El
discriminador `source` (`marketing` | `gateway`, migración
`2026_07_08_211000_add_source_discriminator_to_conciliation_tables`) permite
que la misma cola reciba sesiones de ambos canales (WhatsApp de ventas vía
Evolution, y el gateway unificado `WhatsAppAgent`) sin que se mezclen ni
colisionen sus ids de mensaje/conversación de origen. El escalado a Tere
(`state=escalated`) reusa `AssignToHumanTool` (Marketing) para pasar la
conversación a `human_review` — mismo tool que usa el bot de ventas.

## Disparo de F3 (item #204 FASE A)

Dos entradas a `IdentificationFsm::start()` sobre el canal Marketing/Evolution,
ambas terminan en el mismo motor:

1. **Automático** — `ConciliationRouter::route()` (mensaje entrante en vivo,
   imagen/PDF o texto de intención de pago) → `ConciliationIntakeJob`
   (espera descarga, extrae, corre antifraude, arranca sesión).
2. **Manual** — botón "Identificar cliente" en `/finanzas/whatsapp-comprobantes`
   (pantalla F2) → `WhatsappReceiptReviewController::identify()` →
   `IdentificationSessionStarter::startFromExtraction()`, sobre una extracción
   que el admin ya corrió a mano con "Extraer con IA". Mismo candado
   antifraude (clave/referencia duplicada en otra conversación) y mismo
   `IdentificationFsm` + `ConciliationResponder` que el camino automático;
   idempotente (si ya existe sesión real para el mensaje, la devuelve en vez
   de duplicarla).

`ProcessIncomingMessageJob` (el flujo de ventas) no se modifica en ninguno de
los dos casos — el ruteo hacia conciliación ya vivía ahí (paso "6b", F3.5,
2026-07-02) detrás del flag maestro `payments.wa_conciliation`, fail-open si
el router falla.
