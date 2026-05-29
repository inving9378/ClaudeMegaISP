<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Modules\Addons\Marketing\Services\Publishing\TokenRefresher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshMetaTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $companyId = 1) {}

    public function handle(TokenRefresher $refresher): void
    {
        $result = $refresher->refreshMetaTokens($this->companyId);
        Log::info('[RefreshMetaTokensJob] Completado', $result);
    }
}
