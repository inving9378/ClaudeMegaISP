<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Publication;
use App\Modules\Addons\Marketing\Services\Publishing\PostPublisherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    public function __construct(public Publication $pub) {}

    public function handle(PostPublisherService $publisher): void
    {
        if ($this->pub->status === 'cancelled') {
            Log::info('[PublishPostJob] Publicación cancelada, omitiendo', ['pub' => $this->pub->id]);
            return;
        }

        // Si está programada para el futuro, no publicar todavía
        if ($this->pub->scheduled_for && $this->pub->scheduled_for->isFuture()) {
            Log::info('[PublishPostJob] Publicación programada en el futuro, omitiendo', [
                'pub'          => $this->pub->id,
                'scheduled_for'=> $this->pub->scheduled_for,
            ]);
            return;
        }

        $this->pub->update(['status' => 'publishing']);
        $this->pub->addLog('publishing', 'Worker inició publicación');

        $result = $publisher->publishNow($this->pub);

        if (!$result['success']) {
            $error = $result['error'] ?? 'Error desconocido';
            Log::error('[PublishPostJob] Falló publicación', ['pub' => $this->pub->id, 'error' => $error]);
            throw new \RuntimeException($error);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[PublishPostJob] Job definitivamente fallido', [
            'pub'   => $this->pub->id,
            'error' => $exception->getMessage(),
        ]);

        $this->pub->update([
            'status'         => 'failed',
            'failure_reason' => 'Job fallido después de ' . $this->tries . ' intentos: ' . $exception->getMessage(),
        ]);
    }
}
