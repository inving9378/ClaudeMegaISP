<?php

namespace App\Console\Commands\Active;

use App\Models\User;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use Illuminate\Console\Command;

/**
 * Item roadmap #155 — normaliza client_main_information.user quitando el
 * padding de ceros a la izquierda (ej. "004981" -> "4981") en los registros
 * históricos. SOLO DEV (decisión de Irving en el brief: script en dev
 * primero, validar reportes; prod queda para un plan aparte, fuera de este
 * item). Usa Eloquent save() para que el observer existente
 * (ClientMainInformationObserver::updateUserIfIsDifferent) sincronice el
 * espejo users.login_user en el mismo movimiento — así no se rompe el
 * vínculo que usan MegaFamilia y los accesos internos.
 */
class NormalizarUsuarioWebCommand extends Command
{
    protected $signature = 'client:normalizar-usuario-web
        {--apply : Aplica los cambios; sin esta bandera solo reporta (dry-run)}
        {--limit=0 : Límite de registros candidatos a procesar (0 = sin límite)}';

    protected $description = 'Normaliza client_main_information.user quitando ceros a la izquierda en valores numéricos (item #155). Dry-run por defecto.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = (int) $this->option('limit');

        $this->info($apply ? 'Modo APLICAR — se escribirán cambios.' : 'Modo DRY-RUN (sin --apply) — solo reporta.');

        $query = ClientMainInformation::query()
            ->whereNotNull('user')
            ->where('user', '!=', '')
            ->orderBy('id');

        $candidatos = 0;
        $normalizados = 0;
        $omitidos = 0;
        $sinCambio = 0;
        $procesados = 0;

        $query->chunkById(200, function ($rows) use ($apply, $limit, &$candidatos, &$normalizados, &$omitidos, &$sinCambio, &$procesados) {
            foreach ($rows as $row) {
                if ($limit > 0 && $candidatos >= $limit) {
                    return false; // corta el chunk, ya se alcanzó el límite
                }

                $procesados++;
                $original = $row->getRawOriginal('user');
                $normalizado = ClientMainInformation::normalizeUserNumber($original);

                if ($normalizado === $original) {
                    $sinCambio++;
                    continue;
                }

                $candidatos++;

                if (!$apply) {
                    $this->line("  [dry-run] id={$row->id} client_id={$row->client_id} '{$original}' -> '{$normalizado}'");
                    continue;
                }

                try {
                    $row->user = $normalizado; // dispara el observer -> sincroniza users.login_user
                    $row->save();
                    $normalizados++;
                } catch (\Throwable $e) {
                    $omitidos++;
                    $this->warn("  [omitido] id={$row->id} client_id={$row->client_id} '{$original}' -> '{$normalizado}': " . $e->getMessage());
                }
            }
        });

        $this->newLine();
        $this->info("Procesados: {$procesados} | Sin cambio (ya normalizados o no numéricos): {$sinCambio} | Candidatos con padding: {$candidatos}");
        if ($apply) {
            $this->info("Normalizados: {$normalizados} | Omitidos por colisión/error: {$omitidos}");
        } else {
            $this->info('Nada escrito (dry-run). Corre con --apply para aplicar.');
        }

        return 0;
    }
}
