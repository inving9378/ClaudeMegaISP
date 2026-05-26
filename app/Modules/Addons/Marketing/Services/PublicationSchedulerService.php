<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Modules\Addons\Marketing\Models\Campaign;
use App\Modules\Addons\Marketing\Models\CampaignSchedule;
use Carbon\Carbon;

class PublicationSchedulerService
{
    // Horario permitido: 8 AM – 8 PM
    private const SEND_HOUR_START = 8;
    private const SEND_HOUR_END   = 20;

    // Intervalo mínimo entre envíos (anti-bloqueo WhatsApp)
    private const MIN_INTERVAL_MINUTES = 4;
    private const MAX_JITTER_MINUTES   = 12;

    /**
     * Genera los slots de envío para todos los contenidos aprobados de una campaña.
     * Respeta daily_limit y distribuye con jitter para evitar patrones detectables.
     * Devuelve el número de schedules creados.
     */
    public function scheduleForCampaign(Campaign $campaign): int
    {
        $contents = $campaign->contents()->approved()->get();

        if ($contents->isEmpty()) {
            return 0;
        }

        $slots     = $this->buildTimeSlots($campaign);
        $scheduled = 0;
        $slotIndex = 0;

        foreach ($contents as $content) {
            foreach ($campaign->channel ?? ['whatsapp'] as $channel) {
                if ($slotIndex >= count($slots)) {
                    break 2;
                }

                $alreadyScheduled = CampaignSchedule::where([
                    'campaign_content_id' => $content->id,
                    'channel'             => $channel,
                ])->whereIn('status', ['pending', 'published'])->exists();

                if (!$alreadyScheduled) {
                    CampaignSchedule::create([
                        'campaign_id'         => $campaign->id,
                        'campaign_content_id' => $content->id,
                        'channel'             => $channel,
                        'scheduled_at'        => $slots[$slotIndex],
                        'status'              => 'pending',
                    ]);
                    $slotIndex++;
                    $scheduled++;
                }
            }
        }

        return $scheduled;
    }

    /**
     * Reagenda los slots fallidos con nuevo tiempo aleatorio (máx 3 reintentos).
     */
    public function rescheduleFailedSlots(Campaign $campaign): int
    {
        $failed = CampaignSchedule::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->get();

        $rescheduled = 0;

        foreach ($failed as $schedule) {
            $newTime = Carbon::now()->addMinutes(rand(30, 120));

            if ($newTime->hour >= self::SEND_HOUR_START && $newTime->hour < self::SEND_HOUR_END) {
                $schedule->update([
                    'scheduled_at' => $newTime,
                    'status'       => 'pending',
                    'retry_count'  => $schedule->retry_count + 1,
                ]);
                $rescheduled++;
            }
        }

        return $rescheduled;
    }

    /**
     * Construye el array de Carbon con los slots de tiempo distribuidos
     * a lo largo del rango de fechas de la campaña.
     */
    private function buildTimeSlots(Campaign $campaign): array
    {
        $startDate = Carbon::parse($campaign->start_date);
        $endDate   = Carbon::parse($campaign->end_date ?? $campaign->start_date);
        $daily     = (int) $campaign->daily_limit;
        $slots     = [];
        $current   = $startDate->copy();

        while ($current->lte($endDate)) {
            $daySlots    = 0;
            $slotCurrent = $current->copy()->setTime(self::SEND_HOUR_START, 0, 0);
            $dayEnd      = $current->copy()->setTime(self::SEND_HOUR_END, 0, 0);

            while ($slotCurrent->lt($dayEnd) && $daySlots < $daily) {
                $jitter   = rand(0, self::MAX_JITTER_MINUTES);
                $slotTime = $slotCurrent->copy()->addMinutes($jitter);

                if ($slotTime->lt($dayEnd)) {
                    $slots[] = $slotTime->copy();
                    $daySlots++;
                }

                $interval = self::MIN_INTERVAL_MINUTES + rand(1, 8);
                $slotCurrent->addMinutes($interval + $jitter);
            }

            $current->addDay();
        }

        return $slots;
    }
}
