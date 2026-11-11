<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Retención de los códigos OTP (#1184): portal_password_otps (Portal Cliente) y
 * parental_otp_codes (MegaFamilia) no se purgaban nunca. Vencen en minutos; aquí se borran los
 * creados hace más de N días (default 7), de sobra pasada su caducidad y la del token de reset.
 * Sin --force solo simula.
 */
class PurgeOtpCodes extends Command
{
    protected $signature = 'otp:purge
                            {--days=7 : Días de antigüedad a conservar}
                            {--force : Ejecutar el borrado real (sin esto, solo simula)}';

    protected $description = 'Borra OTPs del portal y de MegaFamilia con más de N días';

    private const TABLAS = ['portal_password_otps' => 'created_at', 'parental_otp_codes' => 'created_at'];

    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));

        foreach (self::TABLAS as $tabla => $col) {
            $q = DB::table($tabla)->where($col, '<', $cutoff);
            if (! $this->option('force')) {
                $this->info("[dry-run] {$tabla}: " . (clone $q)->count() . " filas se borrarían. Usa --force.");
                continue;
            }
            $total = 0;
            do {
                $n = DB::table($tabla)->where($col, '<', $cutoff)->limit(5000)->delete();
                $total += $n;
            } while ($n > 0);
            Log::info("otp:purge: {$total} filas borradas de {$tabla}.");
            $this->info("{$tabla}: {$total} filas borradas.");
        }

        return self::SUCCESS;
    }
}
