<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoPenalty;
use App\Modules\Addons\Talento\Models\TalentoPenaltyAppeal;
use App\Modules\Addons\Talento\Models\TalentoPenaltyType;
use App\Modules\Addons\Talento\Support\PayWeek;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenaltyService
{
    /**
     * Ficha con la que firma quien aplica, confirma o resuelve una penalización.
     * Quien tiene ficha firma con la suya. Un admin/DESARROLLADOR sin ficha firma
     * como MEGAISP (el mismo usuario de sistema que firma los pagos automáticos),
     * cuya ficha se crea la primera vez. Antes cada camino exigía ficha propia y
     * ningún admin podía, por ejemplo, resolver una apelación (2026-10-19).
     */
    public function firmanteDe(User $user): ?TalentoColaborador
    {
        $propia = TalentoColaborador::where('user_id', $user->id)->first();
        if ($propia) {
            return $propia;
        }

        $bot = User::systemBot();
        if (! $bot) {
            return null;
        }

        return TalentoColaborador::firstOrCreate(
            ['user_id' => $bot->id],
            ['type' => 'interno', 'status' => 'active', 'department' => 'Sistema']
        );
    }

    /**
     * Apply a penalty and write an immutable DEBIT to the ledger.
     * Returns the new TalentoPenalty.
     */
    public function apply(array $data): TalentoPenalty
    {
        return DB::transaction(function () use ($data) {
            $type = TalentoPenaltyType::findOrFail($data['penalty_type_id']);

            $penalty = TalentoPenalty::create([
                'colaborador_id'    => $data['colaborador_id'],
                'penalty_type_id'   => $type->id,
                'amount'            => $data['amount'] ?? $type->amount, // snapshot
                'applied_by'        => $data['applied_by'],
                'evidence_photo_path'=> $data['evidence_photo_path'] ?? null,
                'captured_lat'      => $data['captured_lat'] ?? null,
                'captured_lng'      => $data['captured_lng'] ?? null,
                'captured_in_app'   => $data['captured_in_app'] ?? false,
                'status'            => 'applied',
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $data['created_by'] ?? null,
                'created_at'        => now(),
            ]);

            // Ledger DEBIT — immutable. period_start/period_end deben caer en una
            // semana de PayWeek completa (el mismo rango que usa LiquidationService::
            // calculate() para sumar "other_debits") — nunca un solo día. Antes esto
            // escribía $today/$today (un solo día): como ninguna liquidación real
            // pide exactamente ese rango de un día, la penalización SIEMPRE quedaba
            // invisible para el pago semanal real, sin importar quién la aplicara ni
            // por qué camino (bug real encontrado 2026-10-13, verificado contra
            // LiquidationService::calculate() con rollback — "other_debits" salía en
            // $0.00 pese al débito ya creado). Por default cae en la semana de AHORA
            // (mismo criterio que ya usa HealthBonusService para el bono de calidad);
            // un llamador puede pasar 'period_start'/'period_end' explícitos cuando
            // el evento penalizado ocurrió en una semana distinta a la de hoy (ver
            // TalentoReglaDesempenoController::confirmarPenalizacion()).
            if (! empty($data['period_start']) && ! empty($data['period_end'])) {
                $periodStart = $data['period_start'];
                $periodEnd   = $data['period_end'];
            } else {
                $w = PayWeek::boundsFor(now());
                $periodStart = $w['period_start'];
                $periodEnd   = $w['period_end'];
            }

            TalentoLedgerEntry::create([
                'colaborador_id' => $penalty->colaborador_id,
                'type'           => 'debit',
                'concept'        => 'penalty',
                'amount'         => $penalty->amount,
                'reference_type' => TalentoPenalty::class,
                'reference_id'   => $penalty->id,
                'period_start'   => $periodStart,
                'period_end'     => $periodEnd,
                'notes'          => "Penalización: {$type->name}",
                'created_by'     => $data['created_by'] ?? null,
            ]);

            return $penalty;
        });
    }

    /**
     * Resolve an appeal.
     * Justice rule: reviewed_by MUST differ from applied_by.
     * If overturned: add a CREDIT reversal to ledger (never delete the original debit).
     */
    public function resolveAppeal(TalentoPenaltyAppeal $appeal, int $reviewerColaboradorId, string $decision, ?string $notes, ?int $reviewerUserId = null): TalentoPenaltyAppeal
    {
        $penalty = $appeal->penalty;

        // Justice validation: quien aplicó no resuelve. Se compara la ficha y,
        // además, la persona (penalties.created_by): dos admins sin ficha firman
        // ambos como MEGAISP y siguen siendo personas distintas. Sin created_by
        // (penalizaciones viejas) no se puede distinguir, así que la ficha de
        // sistema repetida también se bloquea.
        $fichaSistema = ($bot = User::systemBot())
            ? TalentoColaborador::where('user_id', $bot->id)->value('id')
            : null;
        $mismaFicha = $reviewerColaboradorId === (int) $penalty->applied_by
            && ($reviewerColaboradorId !== (int) $fichaSistema || $penalty->created_by === null);
        $mismaPersona = $reviewerUserId !== null && $penalty->created_by !== null
            && (int) $penalty->created_by === $reviewerUserId;

        if ($mismaFicha || $mismaPersona) {
            throw new \InvalidArgumentException(
                'El revisor de la apelación debe ser distinto de quien aplicó la penalización.'
            );
        }

        return DB::transaction(function () use ($appeal, $penalty, $reviewerColaboradorId, $decision, $notes, $reviewerUserId) {
            $appeal->update([
                'reviewed_by'    => $reviewerColaboradorId,
                'reviewed_by_user_id' => $reviewerUserId,
                'decision'       => $decision,
                'decision_notes' => $notes,
                'resolved_at'    => now(),
            ]);

            $newStatus = $decision === 'overturned' ? 'overturned' : 'upheld';
            $penalty->update(['status' => $newStatus]);

            if ($decision === 'overturned') {
                // Ledger CREDIT reversal — the original debit stays untouched.
                // Reusa el MISMO period_start/period_end del débito original (no
                // "hoy") para que la reversión cancele el cargo en la liquidación
                // exacta donde cayó — una apelación puede resolverse semanas
                // después del cargo; fecharla a "hoy" la dejaría en una semana
                // distinta (mismo bug ya corregido arriba en apply()).
                $originalEntry = TalentoLedgerEntry::where('reference_type', TalentoPenalty::class)
                    ->where('reference_id', $penalty->id)
                    ->where('type', 'debit')
                    ->first();
                if ($originalEntry) {
                    $periodStart = $originalEntry->period_start;
                    $periodEnd   = $originalEntry->period_end;
                } else {
                    $w = PayWeek::boundsFor(now());
                    $periodStart = $w['period_start'];
                    $periodEnd   = $w['period_end'];
                }

                TalentoLedgerEntry::create([
                    'colaborador_id' => $penalty->colaborador_id,
                    'type'           => 'credit',
                    'concept'        => 'penalty_reversal',
                    'amount'         => $penalty->amount,
                    'reference_type' => TalentoPenalty::class,
                    'reference_id'   => $penalty->id,
                    'period_start'   => $periodStart,
                    'period_end'     => $periodEnd,
                    'notes'          => "Reversión de penalización #{$penalty->id} por apelación aprobada",
                    'created_by'     => $reviewerUserId ?? $reviewerColaboradorId,
                ]);

                Log::info('Talento: penalty reversed via appeal', [
                    'penalty_id' => $penalty->id,
                    'appeal_id'  => $appeal->id,
                    'reviewer'   => $reviewerColaboradorId,
                ]);
            }

            return $appeal->fresh(['penalty', 'reviewedByColaborador.user', 'reviewedByUser:id,name,father_last_name']);
        });
    }
}
