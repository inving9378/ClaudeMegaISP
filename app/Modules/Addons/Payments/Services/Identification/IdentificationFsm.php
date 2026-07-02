<?php

namespace App\Modules\Addons\Payments\Services\Identification;

use App\Models\Marketing\Setting;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use Illuminate\Support\Facades\DB;

/**
 * FASE 3 (F3.3) — Motor de la máquina de estados de identificación.
 *
 * PURO: decide transiciones y el TEXTO que se enviaría, pero NO envía nada
 * (eso lo hace el responder en F3.5 / lo muestra el simulador en F3.4). No
 * aplica pago. Muta y guarda la Session; devuelve un "step" con el outbound.
 *
 * Contrato de certeza (para F4):
 *   MEG      → method=meg,                certainty=exact    (auto-aplicable).
 *   nombre   → method=name_single/…,      certainty=proposed (confirma humano).
 *
 * Decisiones de Irving integradas: 2 reintentos → escala; expiración → escala
 * (el comprobante no se pierde); educar MEG tras identificar por nombre.
 */
class IdentificationFsm
{
    // Textos (placeholder; se afinan con Irving en el simulador / F4). Sin markdown.
    public const MSG_ASK_CLIENT_ID =
        "Hola 👋 Recibimos tu comprobante de pago. Para aplicarlo a tu cuenta, "
        . "¿me compartes tu número de cliente? Lo encuentras en tu ticket, contrato, "
        . "factura, correo o en el portal. Si no lo tienes a la mano, escríbeme el "
        . "nombre completo del titular del servicio.";

    public const MSG_ASK_NAME =
        "Gracias. ¿Me confirmas el nombre completo del titular del servicio, "
        . "tal como aparece en el contrato?";

    public const MSG_ASK_NAME_RETRY =
        "No encontré ese nombre en el sistema. ¿Me lo puedes escribir completo, "
        . "con apellidos, tal como está en el contrato? Si tienes tu número de cliente, también me sirve.";

    // Desambiguación por CALLE (privacidad: pedimos la calle, NO mostramos direcciones).
    public const MSG_ASK_STREET =
        "Encontré más de un registro con ese nombre. Para ubicar el tuyo, "
        . "¿en qué calle está instalado tu servicio?";

    public const MSG_ASK_STREET_RETRY =
        "No logré ubicarlo con esa calle. ¿Me confirmas la calle donde está tu "
        . "servicio? Si prefieres, dame tu número de cliente.";

    public const MSG_TOO_MANY =
        "Encontré varios registros con ese nombre. ¿Me das tu nombre COMPLETO con "
        . "apellidos, tal como aparece en el contrato, para ubicarte bien?";

    public const MSG_ESCALATE =
        "Gracias. Voy a pasar tu caso con una persona del equipo para aplicar tu pago correctamente. "
        . "En breve te contactan. 🙌";

    // Cierre amable cuando la sesión expira sin respuesta (el comprobante NO se pierde).
    public const MSG_EXPIRED_CLOSE =
        "No recibimos tu respuesta a tiempo, pero tu comprobante está a salvo 🙂. "
        . "Lo paso con una persona del equipo para aplicar tu pago. ¡Gracias por tu paciencia!";

    // Recordatorios (nudges) por etapa. Se disparan a 1h y 5h por defecto.
    public const MSG_REMINDER_1 =
        "Seguimos pendientes de tu respuesta para aplicar tu pago 🙂. "
        . "En cuanto me confirmes el nombre del titular, lo dejo listo.";
    public const MSG_REMINDER_2 =
        "Solo para no perder tu pago 🙏: ¿me confirmas el nombre del titular del "
        . "servicio? Si prefieres, con gusto te paso con una persona del equipo.";

    /** Máximo de opciones a listar para desambiguar; más que esto → pedir nombre completo. */
    public const MAX_DISAMBIG_OPTIONS = 6;

    public function __construct(
        private MegReferenceResolver $meg,
        private SubscriberSearchService $subs,
    ) {}

    /**
     * Arranca la identificación de un comprobante (estado detecting).
     * @return array step
     */
    public function start(Session $session, string $concepto, ?string $titular = null, ?string $phoneHint = null): array
    {
        // 1) MEG (exacto) en concepto o titular → auto-aplicable.
        $hit = $this->meg->resolveFromText($concepto) ?? $this->meg->resolveFromText($titular);
        if ($hit) {
            return $this->resolve($session, $hit['client_id'], Session::METHOD_MEG, Session::CERTAINTY_EXACT);
        }

        // 2) Sin MEG → pedir el ID de cliente (la siguiente llave más fuerte).
        $session->state = Session::STATE_AWAITING_CLIENT_ID;
        $session->save();
        return $this->step($session, self::MSG_ASK_CLIENT_ID, terminal: false);
    }

    /**
     * Procesa una respuesta del cliente en una sesión en curso.
     * @return array step
     */
    public function handleReply(Session $session, string $reply, ?string $phoneHint = null): array
    {
        // Expiración (decisión 2): responde tarde → escala, no se pierde.
        if ($session->isExpired()) {
            return $this->escalate($session, 'session_expired');
        }

        // Terminal → no-op.
        if (in_array($session->state, [Session::STATE_RESOLVED, Session::STATE_ESCALATED], true)) {
            return $this->step($session, null, terminal: true);
        }

        // El cliente pide humano explícitamente.
        if ($this->wantsHuman($reply)) {
            return $this->escalate($session, 'client_requested_human');
        }

        return match ($session->state) {
            Session::STATE_AWAITING_CLIENT_ID => $this->handleClientId($session, $reply, $phoneHint),
            Session::STATE_AWAITING_NAME      => $this->searchAndBranch($session, [$reply], $phoneHint, isRetry: true),
            Session::STATE_AWAITING_STREET    => $this->handleStreet($session, $reply),
            default                            => $this->step($session, null, terminal: false),
        };
    }

    /**
     * Avanza el "reloj de silencio" a $elapsedMinutes desde el último contacto y
     * dispara lo que corresponda: recordatorios por etapa (1h, 5h configurable)
     * o, si ya pasó la ventana de la sesión, el cierre amable + escalado.
     *
     * En vivo lo dispara un job programado; en el simulador es acelerable
     * (botones "avanzar 1h/5h/expirar"). Idempotente por etapa (reminders_sent).
     * @return array step
     */
    public function advanceTime(Session $session, int $elapsedMinutes): array
    {
        if (in_array($session->state, [Session::STATE_RESOLVED, Session::STATE_ESCALATED], true)) {
            return $this->step($session, null, terminal: true);
        }

        // ¿Ya venció la ventana de la sesión? → cierre amable + escala.
        if ($elapsedMinutes >= $this->expiryMinutes()) {
            return $this->escalate($session, 'session_expired');
        }

        $waiting = in_array($session->state, [
            Session::STATE_AWAITING_CLIENT_ID,
            Session::STATE_AWAITING_NAME,
            Session::STATE_AWAITING_STREET,
        ], true);
        if (!$waiting) {
            return $this->step($session, null, terminal: false, extra: ['reminder_skipped' => true]);
        }

        // Cuántos recordatorios corresponden ya según los umbrales cruzados.
        $thresholds = $this->reminderThresholds();
        $due = 0;
        foreach ($thresholds as $t) {
            if ($elapsedMinutes >= $t) {
                $due++;
            }
        }

        if ($due <= (int) $session->reminders_sent) {
            return $this->step($session, null, terminal: false, extra: ['reminder_skipped' => true]);
        }

        $session->reminders_sent = $due;
        $session->reminder_sent_at = now();
        $session->save();

        $msg = $due === 1 ? self::MSG_REMINDER_1 : self::MSG_REMINDER_2;
        return $this->step($session, $msg, terminal: false, extra: ['reminder' => true, 'reminder_stage' => $due]);
    }

    // ── Ramas ────────────────────────────────────────────────────────────────

    /**
     * Respuesta en AWAITING_CLIENT_ID: si trae un número de cliente válido →
     * resuelve exacto (distingue homónimos). Si no, se trata como NOMBRE.
     */
    private function handleClientId(Session $session, string $reply, ?string $phoneHint): array
    {
        // Tratamos la respuesta como ID solo si es predominantemente numérica
        // (ej. "7110", "id 7110", "es 7110"), NO un nombre con dígitos.
        $alpha = preg_match_all('/[\p{L}]/u', $reply);
        if ($alpha <= 8 && preg_match('/\d{2,}/', $reply, $m)) {
            $cand = $this->subs->findById((int) $m[0]);
            if ($cand) {
                $certainty = config('payments.id_cliente_auto_apply')
                    ? Session::CERTAINTY_EXACT
                    : Session::CERTAINTY_PROPOSED;
                return $this->resolve($session, $cand['client_id'], Session::METHOD_CLIENT_ID, $certainty);
            }
        }
        // No trajo ID válido → interpretamos su respuesta como el nombre (primer intento).
        return $this->searchAndBranch($session, [$reply], $phoneHint, isRetry: false);
    }

    private function searchAndBranch(Session $session, array $names, ?string $phoneHint, bool $isRetry): array
    {
        $candidates = $this->subs->search(array_values(array_filter($names)), $phoneHint);
        $cls        = $this->subs->classify($candidates);

        if ($cls['status'] === 'single') {
            return $this->resolve($session, $cls['client_id'], Session::METHOD_NAME_SINGLE, Session::CERTAINTY_PROPOSED);
        }

        // Varios matches (pocos) → desambiguar por CALLE (privacidad: no listamos).
        if ($cls['status'] === 'multiple' && count($cls['candidates']) <= self::MAX_DISAMBIG_OPTIONS) {
            $session->state = Session::STATE_AWAITING_STREET;
            $session->candidate_client_ids = array_map(fn ($c) => $c['client_id'], $cls['candidates']);
            $session->save();
            return $this->step($session, self::MSG_ASK_STREET, terminal: false);
        }

        // Demasiados matches (nombre de pila común) → pedir nombre completo.
        $tooMany = $cls['status'] === 'multiple';

        if ($isRetry) {
            $session->attempts = (int) $session->attempts + 1;
            if ($session->attempts >= Session::MAX_ATTEMPTS) {
                $session->save();
                return $this->escalate($session, 'no_match_after_retries');
            }
        }
        $session->state = Session::STATE_AWAITING_NAME;
        $session->save();

        $msg = $tooMany
            ? self::MSG_TOO_MANY
            : ($isRetry ? self::MSG_ASK_NAME_RETRY : self::MSG_ASK_NAME);
        return $this->step($session, $msg, terminal: false);
    }

    /**
     * Respuesta en AWAITING_STREET: cruza la calle que escribió el cliente contra
     * las direcciones de los candidatos (server-side, sin exponerlas). Un único
     * match → resuelve. 0 ó >1 → reintento; a los 2 fallidos → escala.
     */
    private function handleStreet(Session $session, string $reply): array
    {
        $ids     = $session->candidate_client_ids ?? [];
        $matches = $this->subs->matchByStreet($ids, $reply);

        if (count($matches) === 1) {
            return $this->resolve($session, (int) $matches[0], Session::METHOD_NAME_DISAMBIGUATED, Session::CERTAINTY_PROPOSED);
        }

        // Ambiguo o sin match → cuenta reintento.
        $session->attempts = (int) $session->attempts + 1;
        if ($session->attempts >= Session::MAX_ATTEMPTS) {
            $session->save();
            return $this->escalate($session, 'street_disambiguation_failed');
        }
        $session->save();
        return $this->step($session, self::MSG_ASK_STREET_RETRY, terminal: false);
    }

    // ── Resolución (unificada) ────────────────────────────────────────────────

    /**
     * Marca la sesión resuelta con el cliente/método/certeza. MEG y (según flag)
     * ID de cliente → exact (auto-aplicable en F4); nombre/calle → proposed
     * (requiere confirmación humana). Marca si el cliente tiene varios servicios
     * (no bloquea; F4/humano decide). Educa sobre el MEG cuando es 'proposed'.
     */
    private function resolve(Session $session, int $clientId, string $method, string $certainty): array
    {
        $session->state                      = Session::STATE_RESOLVED;
        $session->method                     = $method;
        $session->certainty                  = $certainty;
        $session->resolved_client_id         = $clientId;
        $session->resolved_multiple_services = $this->serviceCount($clientId) > 1;
        $session->save();

        // Exacto (MEG, o ID con flag on): sin mensaje; F4 aplicará y confirmará.
        if ($certainty === Session::CERTAINTY_EXACT) {
            return $this->step($session, null, terminal: true);
        }

        // Proposed → gancho "educar MEG" (decisión 3): informar su referencia real.
        $meg    = DB::table('client_payment_references')->where('client_id', $clientId)->value('reference');
        $notice = "¡Gracias! Recibimos tu comprobante y lo estamos validando. "
            . ($meg ? "Para la próxima, incluye tu referencia {$meg} en el concepto y lo aplicamos más rápido." : "");

        return $this->step($session, $notice, terminal: true, extra: ['meg_hint' => $meg]);
    }

    private function escalate(Session $session, string $reason): array
    {
        $session->state             = Session::STATE_ESCALATED;
        $session->escalation_reason = $reason;
        $session->save();
        // Cierre amable distinto si fue por expiración.
        $msg = $reason === 'session_expired' ? self::MSG_EXPIRED_CLOSE : self::MSG_ESCALATE;
        // El handoff real a Tere (assign_to_human) lo dispara el responder (F3.5/F3.6).
        return $this->step($session, $msg, terminal: true, extra: ['escalated' => true, 'reason' => $reason]);
    }

    // ── Config (ajustable con datos) ─────────────────────────────────────────

    /** Minutos hasta expirar la sesión (default 12h). */
    private function expiryMinutes(): int
    {
        return (int) (Setting::get('reconciliation_session_hours', 1) ?? 12) * 60;
    }

    /** Umbrales de recordatorio en minutos (default 60, 300 = 1h y 5h). */
    private function reminderThresholds(): array
    {
        $raw = Setting::get('reconciliation_reminder_minutes', 1) ?? '60,300';
        $mins = array_filter(array_map('intval', explode(',', (string) $raw)), fn ($n) => $n > 0);
        sort($mins);
        return array_values($mins);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Cuántos servicios (bundle + custom) tiene el cliente. */
    private function serviceCount(int $clientId): int
    {
        return DB::table('client_bundle_services')->where('client_id', $clientId)->count()
            + DB::table('client_custom_services')->where('client_id', $clientId)->count();
    }

    private function wantsHuman(string $reply): bool
    {
        return (bool) preg_match('/\b(humano|persona|agente|ejecutiv|asesor|alguien real)\b/i', $reply);
    }

    private function step(Session $session, ?string $outbound, bool $terminal, array $extra = []): array
    {
        return array_merge([
            'state'              => $session->state,
            'outbound'           => $outbound,
            'resolved_client_id' => $session->resolved_client_id,
            'method'             => $session->method,
            'certainty'          => $session->certainty,
            'terminal'           => $terminal,
        ], $extra);
    }
}
