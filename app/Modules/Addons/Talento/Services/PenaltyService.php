<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoPenalty;
use App\Modules\Addons\Talento\Models\TalentoPenaltyAppeal;
use App\Modules\Addons\Talento\Models\TalentoPenaltyType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenaltyService
{
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

            // Ledger DEBIT — immutable
            $today = now()->toDateString();
            TalentoLedgerEntry::create([
                'colaborador_id' => $penalty->colaborador_id,
                'type'           => 'debit',
                'concept'        => 'penalty',
                'amount'         => $penalty->amount,
                'reference_type' => TalentoPenalty::class,
                'reference_id'   => $penalty->id,
                'period_start'   => $today,
                'period_end'     => $today,
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
    public function resolveAppeal(TalentoPenaltyAppeal $appeal, int $reviewerColaboradorId, string $decision, ?string $notes): TalentoPenaltyAppeal
    {
        $penalty = $appeal->penalty;

        // Justice validation
        if ($reviewerColaboradorId === $penalty->applied_by) {
            throw new \InvalidArgumentException(
                'El revisor de la apelación debe ser distinto del supervisor que aplicó la penalización.'
            );
        }

        return DB::transaction(function () use ($appeal, $penalty, $reviewerColaboradorId, $decision, $notes) {
            $appeal->update([
                'reviewed_by'    => $reviewerColaboradorId,
                'decision'       => $decision,
                'decision_notes' => $notes,
                'resolved_at'    => now(),
            ]);

            $newStatus = $decision === 'overturned' ? 'overturned' : 'upheld';
            $penalty->update(['status' => $newStatus]);

            if ($decision === 'overturned') {
                // Ledger CREDIT reversal — the original debit stays untouched
                $today = now()->toDateString();
                TalentoLedgerEntry::create([
                    'colaborador_id' => $penalty->colaborador_id,
                    'type'           => 'credit',
                    'concept'        => 'penalty_reversal',
                    'amount'         => $penalty->amount,
                    'reference_type' => TalentoPenalty::class,
                    'reference_id'   => $penalty->id,
                    'period_start'   => $today,
                    'period_end'     => $today,
                    'notes'          => "Reversión de penalización #{$penalty->id} por apelación aprobada",
                    'created_by'     => $reviewerColaboradorId,
                ]);

                Log::info('Talento: penalty reversed via appeal', [
                    'penalty_id' => $penalty->id,
                    'appeal_id'  => $appeal->id,
                    'reviewer'   => $reviewerColaboradorId,
                ]);
            }

            return $appeal->fresh(['penalty', 'reviewedByColaborador.user']);
        });
    }
}
