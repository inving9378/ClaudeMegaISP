<?php

namespace App\Modules\Core\Notifications\Console;

use App\Modules\Core\Notifications\Models\PushToken;
use Illuminate\Console\Command;

/**
 * Purga diaria de push tokens sin actividad (item #627, decisión aprobada
 * por Irving: last_seen_at > 60 días). Registrado en el schedule de
 * Kernel.php, junto a los demás jobs críticos.
 */
class PurgePushTokensCommand extends Command
{
    protected $signature = 'push-tokens:purge';

    protected $description = 'Elimina push tokens FCM sin actividad hace más de 60 días';

    public function handle(): int
    {
        $count = PushToken::where('last_seen_at', '<', now()->subDays(60))->delete();

        $this->info("Push tokens purgados: {$count}");

        return self::SUCCESS;
    }
}
