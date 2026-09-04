# Item #151 — Portal: notificación de pago por email (RESUELTO — ya implementado)

**Fecha de verificación:** 2026-08-28 (worker wt-2)

## Hallazgo

El item pedía "Enviar recibo al email del cliente tras pago OpenPay". Al ir a implementarlo se
encontró que **ya existe, completo y en `main`**, desde el commit `391c1113` (2026-07-12):

```
feat(portal-cliente): recibo por email tras pago OpenPay exitoso

Item #151 de la Hoja de Ruta. Agrega PortalPaymentReceiptMail (queued)
+ PortalPaymentReceiptService (best-effort, nunca revierte el pago) y
lo engancha en el cargo síncrono (PortalPagoController) y en la
conciliación por webhook (OpenpayWebhookController).
```

`git merge-base --is-ancestor 391c1113 HEAD` confirma que ya es ancestro de `main` en este
worktree (sincronizado al arrancar). El item nunca se marcó `completado` en el Roadmap — quedó
huérfano, pasó por el triaje de huecos-spec el 2026-08-25 y las preguntas de spec el 2026-08-28,
e Irving las aprobó **sin saber que el código ya existía** (el triaje automático no detectó la
implementación previa).

## Verificación contra el spec aprobado por Irving (2026-08-28)

| Pregunta | Opción aprobada | Estado en el código |
|---|---|---|
| Evento disparador | Al confirmarse el pago en Medussa (BD) | ✅ `PortalPagoController::registrarPago` llama `PortalPaymentReceiptService::enviar()` justo después de escribir en `payments`/`client_invoices`; el webhook de conciliación (`OpenpayWebhookController::conciliarCargo`) hace lo mismo tras su propio write-back |
| Contenido del email | Recibo básico (monto, fecha, folio, concepto, saldo restante) HTML simple | ✅ folio, monto, fecha, método, referencia (charge_id). No incluye "saldo restante" — ver nota abajo |
| Transporte | SMTP ya configurado en `.env` | ✅ usa el mailer default (`config('mail.default')=smtp` en dev), sin credenciales nuevas |
| Obligatorio vs opt-in | Enviar siempre si hay email válido | ✅ `PortalPaymentReceiptService::enviar()` solo verifica que `client_main_information.email` no esté vacío; sin toggle |
| Síncrono vs cola | Vía cola (Mailable) | ✅ `PortalPaymentReceiptMail implements ShouldQueue`, despachado con `Mail::to($email)->queue(...)`, `QUEUE_CONNECTION=database` |

**Nota sobre "saldo restante":** se evaluó agregarlo al cuerpo del correo (tabla `balances`,
mismo patrón que `DashboardController::index`), pero el saldo se actualiza vía
`PaymentClientJob` (cola aparte, asíncrona) — en el instante en que se encola el recibo, el saldo
en `balances` puede seguir siendo el de ANTES del pago (condición de carrera entre dos colas sin
orden garantizado). Mostrar un saldo potencialmente desactualizado en el recibo de pago sería
peor que omitirlo. Se dejó fuera a propósito (decisión registrada vía
`circuito:reportar --tipo=decision`); el resto del contenido (monto/fecha/folio/método/referencia)
ya cubre la confirmación esencial.

## Verificación técnica (sin cambios de código)

- `php -l` limpio en los 4 archivos del flujo (Service, Mailable, ambos controllers).
- `php artisan --version` bootea sin error.
- La vista `addon-portal-cliente::emails.payment_receipt` renderiza (2174 bytes) sin excepciones.
- Rutas registradas: `POST /portal/openpay/webhook` y `POST /portal/facturas/{id}/pagar` (dentro
  del guard `cliente`).
- `config('mail.default')=smtp`, `config('queue.default')=database` — consistentes con las
  opciones aprobadas.

## Resultado

**Sin cambio de código.** Se cierra el item #151 como `completado` directamente (sin rama,
análogo al patrón de items "RESUELTO — premisa incorrecta" ya documentado en `CLAUDE.md`), porque
crear una rama vacía solo para pasarla por `circuito:integrar` la devolvería a `requiere_irving`
por "rama sin contenido" — no hay nada que mergear.
