# Contrato — flag `auto_apply_enabled` (guardrail de dinero, conciliación WhatsApp)

Item de roadmap #195. Aclara el comportamiento real (confirmado por auditoría
read-only) del interruptor `auto_apply_enabled` para que un refactor futuro no
lo rompa sin darse cuenta. Blindado por
`tests/Feature/Payments/AutoApplyFlagContractTest.php`.

## Dónde vive

- `App\Modules\Addons\Payments\Services\Conciliation\PaymentFromSessionService::apply()`
  es el ÚNICO lugar que evalúa el flag.
- Fuente del valor: `ConciliationSettings::enabled('auto_apply_enabled')`
  (tabla `conciliation_settings`, con `config('payments.auto_apply_enabled')` /
  `.env` como fallback si no hay fila).

## El contrato

`apply(Session $session, ?int $confirmedBy = null)` distingue la vía por un
solo dato: `$automatic = ($confirmedBy === null)`.

1. **Vía automática** (`$confirmedBy === null`, la usa
   `ApplyIdentifiedPaymentJob` cuando F3 resuelve una sesión): pasa por
   **todos** los candados en orden —
   - `isAutoApplicable()` (certeza `exact`, es decir referencia MEG, o ID de
     cliente si `id_cliente_auto_apply` está encendido) — si no, se manda a la
     cola de confirmación humana (`needs_human_confirmation`).
   - `resolved_multiple_services` — si el cliente tiene varios servicios,
     también va a humano (`multiple_services_needs_human`), sin importar el
     flag.
   - **`auto_apply_enabled`** — solo se llega aquí si los dos anteriores ya
     dejaron pasar el candidato (certeza exacta + un solo servicio). Si está
     apagado, el pago **NO se aplica** y la sesión queda esperando
     (`auto_apply_disabled`); no se escala a Tere ni se pierde el candidato.

2. **Vía manual/confirmada** (`$confirmedBy !== null`, la usa
   `applyConfirmed()` desde la confirmación humana de Tere en F6): **el flag
   NO se evalúa, a propósito**. Es la contraparte de una decisión humana
   explícita — Tere ya vio el comprobante y confirmó — y por diseño no debe
   depender de un interruptor pensado para frenar la automatización sin
   supervisión.

## Por qué es seguro (aclaración del caso 7500)

`id_cliente_auto_apply` no se lee en `apply()`; se lee antes, en
`IdentificationFsm` (línea ~190), donde decide si una identificación por ID de
cliente produce certeza `exact` (auto-aplicable) o `proposed` (requiere
humano). Para el caso 7500: certeza quedó en `proposed` + `auto_apply_enabled`
apagado → la sesión fue a la cola y la aplicó un **humano** (confirmación
manual, `confirmed_by_user_id` con el usuario real), nunca la vía automática.
No hubo bypass del guardrail.

## Fuera de este contrato (a propósito)

`PaymentApplicationService::applyPayment()` (el método que de verdad mueve el
dinero) es compartido por **tres** entradas distintas:

- Conciliación WhatsApp (`PaymentFromSessionService`, este contrato).
- Webhook SPEI / OpenPay.
- Captura de pago de mostrador (`/finanzas/captura-pago`).

SPEI y captura-pago llaman `applyPayment()` **directo**, sin pasar por
`auto_apply_enabled`. Es intencional: ese flag gobierna específicamente la
automatización de la *identificación conversacional* del cliente por IA
(WhatsApp); un webhook bancario o una captura de mostrador ya tienen su propia
fuente de verdad (el banco, o un humano tecleando en el mostrador) y no
pasan por la máquina de identificación F3/F4.

## Invariante blindada por el test

`tests/Feature/Payments/AutoApplyFlagContractTest.php`:

1. Automático + certeza exacta + flag OFF → **no aplica** (`auto_apply_disabled`), cero payments nuevos.
2. Automático + certeza exacta + flag ON → **sí aplica** (`auto`), `confirmed_by_user_id` queda `null`.
3. Manual (confirmado por un humano) + flag OFF → **sí aplica** (`confirmed`) — el salto es a propósito.
4. Automático + certeza `proposed` → nunca llega a evaluar el flag (`needs_human_confirmation`), sin importar su estado.
