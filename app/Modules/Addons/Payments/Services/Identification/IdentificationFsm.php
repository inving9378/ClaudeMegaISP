<?php

namespace App\Modules\Addons\Payments\Services\Identification;

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
    public const MSG_ASK_NAME =
        "Hola 👋 Recibimos tu comprobante de pago. Para aplicarlo a tu cuenta, "
        . "¿me confirmas el nombre completo del titular del servicio, tal como aparece en el contrato?";

    public const MSG_ASK_NAME_RETRY =
        "No encontré ese nombre en el sistema. ¿Me lo puedes escribir completo, "
        . "con apellidos, tal como está en el contrato?";

    public const MSG_ASK_SERVICE_INVALID =
        "Por favor responde solo con el número de la opción (por ejemplo: 1).";

    public const MSG_ESCALATE =
        "Gracias. Voy a pasar tu caso con una persona del equipo para aplicar tu pago correctamente. "
        . "En breve te contactan. 🙌";

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
        // 1) MEG (exacto) — se busca en concepto y en titular.
        $hit = $this->meg->resolveFromText($concepto) ?? $this->meg->resolveFromText($titular);
        if ($hit) {
            return $this->resolveByMeg($session, $hit['client_id'], $hit['reference']);
        }

        // 2) Nombre. La primera búsqueda automática NO cuenta como reintento.
        return $this->searchAndBranch($session, [$concepto, $titular], $phoneHint, isRetry: false);
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
            Session::STATE_AWAITING_NAME    => $this->searchAndBranch($session, [$reply], $phoneHint, isRetry: true),
            Session::STATE_AWAITING_SERVICE => $this->handleDisambiguation($session, $reply),
            default                          => $this->step($session, null, terminal: false),
        };
    }

    // ── Ramas ────────────────────────────────────────────────────────────────

    private function searchAndBranch(Session $session, array $names, ?string $phoneHint, bool $isRetry): array
    {
        $candidates = $this->subs->search(array_values(array_filter($names)), $phoneHint);
        $cls        = $this->subs->classify($candidates);

        if ($cls['status'] === 'single') {
            return $this->resolveByName($session, $cls['client_id'], Session::METHOD_NAME_SINGLE);
        }

        if ($cls['status'] === 'multiple') {
            $session->state = Session::STATE_AWAITING_SERVICE;
            $session->candidate_client_ids = array_map(fn ($c) => $c['client_id'], $cls['candidates']);
            $session->save();
            return $this->step($session, $this->renderOptions($cls['candidates']), terminal: false);
        }

        // none
        if ($isRetry) {
            $session->attempts = (int) $session->attempts + 1;
            if ($session->attempts >= Session::MAX_ATTEMPTS) {
                $session->save();
                return $this->escalate($session, 'no_match_after_retries');
            }
        }
        $session->state = Session::STATE_AWAITING_NAME;
        $session->save();
        return $this->step($session, $isRetry ? self::MSG_ASK_NAME_RETRY : self::MSG_ASK_NAME, terminal: false);
    }

    private function handleDisambiguation(Session $session, string $reply): array
    {
        $ids = $session->candidate_client_ids ?? [];
        $n   = $this->parseChoice($reply, count($ids));

        if ($n !== null) {
            return $this->resolveByName($session, (int) $ids[$n - 1], Session::METHOD_NAME_DISAMBIGUATED);
        }

        // Elección inválida → cuenta como reintento.
        $session->attempts = (int) $session->attempts + 1;
        if ($session->attempts >= Session::MAX_ATTEMPTS) {
            $session->save();
            return $this->escalate($session, 'disambiguation_failed');
        }
        $session->save();
        return $this->step($session, self::MSG_ASK_SERVICE_INVALID, terminal: false);
    }

    // ── Resoluciones ─────────────────────────────────────────────────────────

    private function resolveByMeg(Session $session, int $clientId, string $reference): array
    {
        $session->state              = Session::STATE_RESOLVED;
        $session->method             = Session::METHOD_MEG;
        $session->certainty          = Session::CERTAINTY_EXACT;
        $session->resolved_client_id = $clientId;
        $session->save();

        // MEG = exacto → F3 no manda mensaje (F4 aplicará y confirmará). Sin outbound.
        return $this->step($session, null, terminal: true);
    }

    private function resolveByName(Session $session, int $clientId, string $method): array
    {
        $session->state              = Session::STATE_RESOLVED;
        $session->method             = $method;
        $session->certainty          = Session::CERTAINTY_PROPOSED; // requiere confirmación humana (F4)
        $session->resolved_client_id = $clientId;
        $session->save();

        // Gancho "educar MEG" (decisión 3): informar su referencia al cliente.
        // Texto final se afina en F4; aquí queda el gancho con el MEG real.
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
        // El handoff real a Tere (assign_to_human) lo dispara el responder (F3.5/F3.6).
        return $this->step($session, self::MSG_ESCALATE, terminal: true, extra: ['escalated' => true, 'reason' => $reason]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function renderOptions(array $candidates): string
    {
        $lines = ["Encontré varios registros con ese nombre. ¿Cuál es tuyo? Responde con el número:"];
        $i = 1;
        foreach ($candidates as $c) {
            $svc = !empty($c['services']) ? $c['services'][0]['description'] : 'servicio';
            $col = $c['colonia'] ?? ($c['address'] ?? 's/d');
            $lines[] = "{$i}) {$col} — {$svc}";
            $i++;
        }
        return implode("\n", $lines);
    }

    private function parseChoice(string $reply, int $count): ?int
    {
        if (preg_match('/\d+/', $reply, $m)) {
            $n = (int) $m[0];
            if ($n >= 1 && $n <= $count) {
                return $n;
            }
        }
        return null;
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
