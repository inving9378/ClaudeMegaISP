<?php

namespace App\Modules\Addons\Talento\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * UNI-T L2 — revisión de garantías. La calidad del trabajo original (responsabilidad/resolución) es independiente
 * de la actividad de quien atiende el ticket. Sin resolución registrada no hay efecto económico (`puedeAfectarEconomia`).
 */
class WarrantyReviewService
{
    public const RESPONSABILIDADES = ['ninguna', 'trabajo_original', 'externa', 'cliente'];
    public const RESOLUCIONES = ['sin_efecto', 'atribuida_trabajo_original', 'atribuida_externa'];

    public function abrir(int $warrantyEventId): object
    {
        $row = DB::table('talento_warranty_reviews')->where('warranty_event_id', $warrantyEventId)->first();
        if ($row) {
            return $row;
        }
        $id = DB::table('talento_warranty_reviews')->insertGetId([
            'warranty_event_id' => $warrantyEventId, 'estado' => 'abierta', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('talento_warranty_reviews')->find($id);
    }

    /** Toma la revisión: el revisor no puede ser un participante de la orden original. */
    public function tomar(int $reviewId, int $revisorUserId): object
    {
        $r = $this->get($reviewId);
        if ($r->estado === 'resuelta') {
            throw new InvalidArgumentException('La revisión ya está resuelta.');
        }
        $ev = DB::table('talento_warranty_events')->find($r->warranty_event_id);
        $ids = array_map(fn ($p) => (int) ($p['user_id'] ?? 0), json_decode($ev->participantes ?? '[]', true) ?: []);
        if (in_array($revisorUserId, $ids, true)) {
            throw new InvalidArgumentException('El revisor no puede ser quien hizo el trabajo original.');
        }
        DB::table('talento_warranty_reviews')->where('id', $reviewId)->update(['estado' => 'en_revision', 'revisor_user_id' => $revisorUserId, 'updated_at' => now()]);
        return $this->get($reviewId);
    }

    public function resolver(int $reviewId, int $causaId, string $responsabilidad, string $resolucion, ?string $nota = null): object
    {
        $r = $this->get($reviewId);
        if ($r->estado !== 'en_revision') {
            throw new InvalidArgumentException('La revisión debe estar en revisión (con revisor) para resolverse.');
        }
        if (! in_array($responsabilidad, self::RESPONSABILIDADES, true) || ! in_array($resolucion, self::RESOLUCIONES, true)) {
            throw new InvalidArgumentException('Responsabilidad o resolución no válida.');
        }
        if (! DB::table('talento_warranty_causes')->where('id', $causaId)->where('activa', true)->exists()) {
            throw new InvalidArgumentException('Causa no válida.');
        }
        DB::table('talento_warranty_reviews')->where('id', $reviewId)->update([
            'estado' => 'resuelta', 'causa_id' => $causaId, 'responsabilidad' => $responsabilidad, 'resolucion' => $resolucion,
            'nota' => $nota, 'resuelta_at' => now(), 'updated_at' => now(),
        ]);
        return $this->get($reviewId);
    }

    /** Compuerta: solo una garantía resuelta puede producir efecto económico (y solo si la resolución lo atribuye). */
    public function puedeAfectarEconomia(int $warrantyEventId): bool
    {
        if (! config('unificacion.interruptores.garantia_l2')) {
            return true; // comportamiento actual
        }
        $r = DB::table('talento_warranty_reviews')->where('warranty_event_id', $warrantyEventId)->first();
        return $r && $r->estado === 'resuelta' && $r->resolucion !== 'sin_efecto';
    }

    /**
     * UNI-T L3c: efecto sobre el trabajo original, SOLO con la revisión resuelta (L2) y como ajuste registrado (J3,
     * talento_period_adjustments) en la semana abierta; nunca sobrescribe lo original ni descuenta por la sola apertura.
     * Idempotente por revisión. Devuelve null si no corresponde efecto (sin_efecto / responsabilidad ajena).
     */
    public function aplicarAjuste(int $reviewId, int $porUserId, float $puntos = 0, float $monto = 0): ?object
    {
        $r = $this->get($reviewId);
        if ($r->estado !== 'resuelta') {
            throw new InvalidArgumentException('Sin revisión resuelta no hay efecto sobre el trabajo original.');
        }
        if ($r->resolucion !== 'atribuida_trabajo_original' || $r->responsabilidad !== 'trabajo_original') {
            return null;
        }
        $existente = DB::table('talento_period_adjustments')->where('original_type', 'warranty_review')->where('original_id', $reviewId)->first();
        if ($existente) {
            return $existente;
        }
        $ev = DB::table('talento_warranty_events')->find($r->warranty_event_id);
        $principal = collect(json_decode($ev->participantes ?? '[]', true) ?: [])->first(fn ($p) => ($p['rol'] ?? null) === 'principal') ?? [];
        $colab = DB::table('talento_colaboradores')->where('user_id', (int) ($principal['user_id'] ?? 0))->value('id');
        if (! $colab) {
            throw new InvalidArgumentException('No se encontró al colaborador del trabajo original.');
        }
        $orig = \App\Modules\Addons\Talento\Support\PayWeek::boundsFor(\Carbon\Carbon::parse($ev->orden_validada_at ?? $ev->created_at));
        $target = \App\Modules\Addons\Talento\Support\PayWeek::current();
        $id = DB::table('talento_period_adjustments')->insertGetId([
            'colaborador_id' => $colab, 'original_type' => 'warranty_review', 'original_id' => $reviewId,
            'original_period_start' => $orig['period_start'], 'original_period_end' => $orig['period_end'],
            'target_period_start' => $target['period_start'], 'target_period_end' => $target['period_end'],
            'points_delta' => $puntos, 'amount_delta' => $monto,
            'reason' => 'Garantía atribuida al trabajo original ('.$ev->orden_ref.'), revisión #'.$reviewId,
            'created_by' => $porUserId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('talento_period_adjustments')->find($id);
    }

    private function get(int $id): object
    {
        return DB::table('talento_warranty_reviews')->find($id) ?? throw new InvalidArgumentException('Revisión inexistente.');
    }
}
