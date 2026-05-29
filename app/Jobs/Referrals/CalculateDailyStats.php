<?php

namespace App\Jobs\Referrals;

use App\Jobs\Referrals\Concerns\MeasuresPerformance;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateDailyStats implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MeasuresPerformance;

    public int $tries = 3;

    public function __construct(
        private readonly ?string $date = null
    ) {
        $this->onQueue('referrals');
    }

    public function handle(): void
    {
        $this->startMeasure();
        $date = $this->date ? Carbon::parse($this->date)->toDateString() : now()->subDay()->toDateString();

        $start = Carbon::parse($date)->startOfDay();
        $end   = Carbon::parse($date)->endOfDay();

        $totalEmbajadores  = DB::table('client_referral_profiles')->count();
        $activeEmbajadores = DB::table('client_referral_profiles')->where('is_eligible', true)->count();

        $newReferrals = DB::table('referrals')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $convertedReferrals = DB::table('referrals')
            ->whereBetween('updated_at', [$start, $end])
            ->whereIn('status', ['active', 'completed'])
            ->count();

        $commissionsGenerated = DB::table('referral_commissions')
            ->whereBetween('created_at', [$start, $end])
            ->sum('commission_amount');

        $commissionsApplied = DB::table('referral_commissions')
            ->where('status', 'applied')
            ->whereBetween('updated_at', [$start, $end])
            ->sum('commission_amount');

        $rewardsGenerated = DB::table('referral_rewards')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $rewardsApplied = DB::table('referral_rewards')
            ->where('status', 'applied')
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        DB::table('referral_stats_daily')->upsert(
            [[
                'stat_date'              => $date,
                'total_embajadores'      => $totalEmbajadores,
                'active_embajadores'     => $activeEmbajadores,
                'new_referrals'          => $newReferrals,
                'converted_referrals'    => $convertedReferrals,
                'commissions_generated'  => $commissionsGenerated,
                'commissions_applied'    => $commissionsApplied,
                'rewards_generated'      => $rewardsGenerated,
                'rewards_applied'        => $rewardsApplied,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]],
            ['stat_date'],
            [
                'total_embajadores', 'active_embajadores', 'new_referrals',
                'converted_referrals', 'commissions_generated', 'commissions_applied',
                'rewards_generated', 'rewards_applied', 'updated_at',
            ]
        );

        Log::info('CalculateDailyStats: stats calculadas', [
            'date'                   => $date,
            'total_embajadores'      => $totalEmbajadores,
            'active_embajadores'     => $activeEmbajadores,
            'new_referrals'          => $newReferrals,
            'commissions_generated'  => $commissionsGenerated,
        ]);

        $this->countProcessed(1);
        $this->recordSuccess(['date' => $date, 'total_embajadores' => $totalEmbajadores]);
    }
}
