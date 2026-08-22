<?php

namespace App\Services\Deploy;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Punto único para calcular el vínculo técnico de una versión (item roadmap #1017):
 * commit exacto y rango de migraciones que introdujo. Lo usan tanto el pipeline local
 * (DeploymentService, dev/publicador) como el pipeline remoto (RemoteDeployCommand,
 * la caja que de verdad corre `migrate`) — misma lógica, sin duplicar.
 */
class ReleaseTechnicalLinkService
{
    /**
     * Migración que agregó el vínculo técnico (item #1017). Las releases previas a esta
     * fecha no tienen ancla real (backfill las deja sin migracion_hasta — no se inventa
     * hacia atrás), así que se usa como punto de arranque: la primera release nueva que se
     * calcule después de este cambio reporta "de aquí en adelante", no reclama nada de las
     * migraciones históricas de releases viejas.
     */
    private const EPOCH_MIGRATION = '2026_08_22_001200_add_vinculo_tecnico_to_releases_table';

    /** SHA completo del commit actual (HEAD). Null si no se pudo determinar (best-effort). */
    public function currentCommitSha(): ?string
    {
        try {
            $process = Process::fromShellCommandline('git rev-parse HEAD', base_path());
            $process->run();
            $sha = trim($process->getOutput());
            return $sha !== '' ? $sha : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Rango [migracion_desde, migracion_hasta] aplicado DESPUÉS de $ultimaMigracionAnterior
     * (el `migracion_hasta` de la release previa). Si esa release no tiene ancla (release
     * previa a este vínculo técnico, o no hay release previa), se usa EPOCH_MIGRATION como
     * punto de arranque en vez de adivinar — ver constante arriba.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function rangoDesdeMigracion(?string $ultimaMigracionAnterior): array
    {
        $ancla = $ultimaMigracionAnterior ?? self::EPOCH_MIGRATION;

        try {
            $anclaId = DB::table('migrations')->where('migration', $ancla)->value('id');
            if ($anclaId === null) {
                return [null, null];
            }

            $migraciones = DB::table('migrations')
                ->where('id', '>', $anclaId)
                ->orderBy('id')
                ->pluck('migration');
        } catch (\Throwable) {
            return [null, null];
        }

        if ($migraciones->isEmpty()) {
            return [null, null];
        }

        return [$migraciones->first(), $migraciones->last()];
    }
}
