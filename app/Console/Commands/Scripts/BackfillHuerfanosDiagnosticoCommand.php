<?php

namespace App\Console\Commands\Scripts;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnóstico READ-ONLY (roadmap #654, prerequisito explícito de #105 —
 * el backfill masivo real, que SÍ escribe y está parqueado para producción).
 * Este comando NUNCA escribe en la base de datos, solo mide y reporta.
 *
 * Vínculo canónico entre las dos tablas (mismo criterio que
 * ClientMainInformationObserver::updateUserIfIsDifferent y el hermano
 * escritor BackfillOrphanClientUsersCommand):
 *   client_main_information.client_id = users.client_id
 * El candidato a login_user es client_main_information.user (columna
 * "Usuario WEB"), que es lo que el Observer copia a users.login_user
 * (UNIQUE) al crear la fila espejo.
 *
 * Uso:
 *   php artisan users:backfill-huerfanos --dry-run
 *   php artisan users:backfill-huerfanos --dry-run --sample=15
 */
class BackfillHuerfanosDiagnosticoCommand extends Command
{
    protected $signature = 'users:backfill-huerfanos
        {--dry-run : No-op explícito — este comando NUNCA escribe, solo reporta}
        {--sample=10 : Cuántos clientes de muestra incluir con detalle completo}';

    protected $description = 'Diagnóstico read-only de client_main_information huérfanos (sin fila users espejo) — prerequisito de #105';

    public function handle(): int
    {
        $sampleSize = max(1, (int) $this->option('sample'));

        $this->info('=== Diagnóstico de huérfanos client_main_information -> users (READ-ONLY, no escribe nada) ===');
        $this->line('Vínculo canónico: client_main_information.client_id = users.client_id');
        $this->newLine();

        $existingClientIds = User::query()->whereNotNull('client_id')->pluck('client_id')->all();

        $baseQuery = fn () => DB::table('client_main_information')->whereNotIn('client_id', $existingClientIds);

        // (a) Conteo total de huérfanos
        $totalOrphans = $baseQuery()->count();

        // Universo completo de huérfanos. Acotado (~900 filas reales al 2026-08),
        // se trae completo en una sola query: no amerita paginar para un reporte.
        $orphans = $baseQuery()->orderBy('id')->get([
            'id', 'client_id', 'user', 'name', 'father_last_name', 'mother_last_name',
            'email', 'phone', 'password', 'estado', 'created_at',
        ]);

        $withoutLoginCandidate = $orphans->filter(fn ($r) => trim((string) $r->user) === '')->count();

        // (b) Colisiones potenciales de login_user si se deriva del campo canónico
        $candidateLogins = $orphans->pluck('user')->filter(fn ($v) => trim((string) $v) !== '')->unique()->values();

        $collidesWithExistingUsers = $candidateLogins->isEmpty()
            ? collect()
            : DB::table('users')->whereIn('login_user', $candidateLogins)->pluck('login_user');

        $duplicatesWithinBatch = $baseQuery()
            ->whereNotNull('user')->where('user', '!=', '')
            ->select('user', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user')
            ->having('cnt', '>', 1)
            ->pluck('user');

        $collisionLogins = $collidesWithExistingUsers->merge($duplicatesWithinBatch)->unique()->values();
        $collidingOrphans = $orphans->filter(fn ($r) => $r->user !== null && $collisionLogins->contains($r->user));

        // (c) Sin email disponible
        $withoutEmail = $orphans->filter(fn ($r) => trim((string) $r->email) === '')->count();

        // Extra de contexto (no pedido explícitamente, pero acota la factibilidad
        // real del backfill #105, que solo crea la fila si hay password):
        $withoutPassword = $orphans->filter(fn ($r) => trim((string) $r->password) === '')->count();

        // (d) Muestra para validación manual
        $sample = $orphans->take($sampleSize);

        $report = $this->buildReport(
            $totalOrphans,
            $withoutLoginCandidate,
            $collidingOrphans,
            $collisionLogins,
            $existingClientIds ? count($existingClientIds) : 0,
            $withoutEmail,
            $withoutPassword,
            $sample
        );

        $this->line($report);

        $logPath = storage_path('logs/backfill-huerfanos-diagnostico-' . date('Ymd_His') . '.log');
        file_put_contents($logPath, $report);
        $this->newLine();
        $this->info("Reporte completo guardado en: {$logPath}");

        return self::SUCCESS;
    }

    protected function buildReport(
        int $totalOrphans,
        int $withoutLoginCandidate,
        \Illuminate\Support\Collection $collidingOrphans,
        \Illuminate\Support\Collection $collisionLogins,
        int $totalUsersWithClientId,
        int $withoutEmail,
        int $withoutPassword,
        \Illuminate\Support\Collection $sample
    ): string {
        $lines = [];
        $lines[] = '=== Diagnóstico de huérfanos client_main_information -> users ===';
        $lines[] = 'Generado: ' . now()->toDateTimeString();
        $lines[] = 'Modo: READ-ONLY — este comando no escribió nada en la base de datos.';
        $lines[] = 'Vínculo canónico usado: client_main_information.client_id = users.client_id';
        $lines[] = 'Candidato a login_user: client_main_information.user (columna "Usuario WEB")';
        $lines[] = '';

        $lines[] = '--- (a) Conteo total ---';
        $lines[] = "Filas users con client_id no nulo (ya tienen espejo): {$totalUsersWithClientId}";
        $lines[] = "Huérfanos (client_main_information sin fila users por client_id): {$totalOrphans}";
        $lines[] = 'Referencia del item original: ~879. ' .
            ($totalOrphans >= 850 && $totalOrphans <= 910
                ? 'Cifra consistente con la referencia (drift normal por altas/bajas desde que se escribió el item).'
                : 'DIVERGE de forma notable de la referencia — revisar antes de asumir el backfill de #105 tal cual.');
        $lines[] = "De esos, sin ningún valor en 'user' (no se les puede derivar login_user): {$withoutLoginCandidate}";
        $lines[] = '';

        $lines[] = '--- (b) Colisiones potenciales de login_user ---';
        $lines[] = "Huérfanos cuyo 'user' candidato colisionaría (ya existe en users.login_user, o se repite entre dos o más huérfanos): {$collidingOrphans->count()}";
        $lines[] = "Valores de login_user involucrados en alguna colisión: {$collisionLogins->count()}";
        if ($collidingOrphans->isNotEmpty()) {
            $lines[] = 'Sugerencia de resolución (no aplicada, solo propuesta): sufijar con el client_id, que es único';
            $lines[] = "  garantizado -> 'login_user-{client_id}'. Ejemplos:";
            foreach ($collidingOrphans->take(10) as $row) {
                $lines[] = "  · client_id={$row->client_id} user actual='{$row->user}' -> propuesto='{$row->user}-{$row->client_id}'";
            }
            if ($collidingOrphans->count() > 10) {
                $lines[] = '  · (' . ($collidingOrphans->count() - 10) . ' colisiones adicionales omitidas del detalle, incluidas en el conteo)';
            }
        } else {
            $lines[] = 'Sin colisiones detectadas.';
        }
        $lines[] = '';

        $lines[] = '--- (c) Sin email disponible ---';
        $lines[] = "Huérfanos sin email (columna vacía o nula): {$withoutEmail} de {$totalOrphans}";
        $lines[] = '';

        $lines[] = '--- Extra de contexto (no pedido explícitamente, acota la factibilidad de #105) ---';
        $lines[] = "Huérfanos sin password en client_main_information (el backfill escritor -#105- solo crea la fila si hay password; sin ella hoy tampoco la crearía el alta normal): {$withoutPassword} de {$totalOrphans}";
        $lines[] = '';

        $lines[] = "--- (d) Muestra de {$sample->count()} para validación manual ---";
        $lines[] = 'NOTA DE SEGURIDAD: nunca se vuelca el valor de la contraseña (client_main_information.password es texto plano) — solo si está presente o no.';
        $lines[] = '';
        foreach ($sample as $row) {
            $userVal = trim((string) $row->user) !== '' ? $row->user : '(vacío — sin candidato a login_user)';
            $emailVal = trim((string) $row->email) !== '' ? $row->email : '(sin email)';
            $hasPassword = trim((string) $row->password) !== '' ? 'sí' : 'no';
            $collide = ($row->user !== null && $collisionLogins->contains($row->user)) ? 'SÍ' : 'no';
            $nombreCompleto = trim($row->name . ' ' . $row->father_last_name . ' ' . $row->mother_last_name);

            $lines[] = "client_id={$row->client_id} (cmi.id={$row->id})";
            $lines[] = "  Nombre: {$nombreCompleto}";
            $lines[] = "  login_user candidato: {$userVal}";
            $lines[] = "  Email: {$emailVal}";
            $lines[] = "  Teléfono: " . (trim((string) $row->phone) !== '' ? $row->phone : '(sin teléfono)');
            $lines[] = "  Tiene password en CMI: {$hasPassword}";
            $lines[] = "  Estado: " . ($row->estado ?? '(sin estado)');
            $lines[] = "  Colisiona login_user: {$collide}";
            $lines[] = "  Creado: {$row->created_at}";
            $lines[] = '';
        }

        $lines[] = '=== Fin del diagnóstico. Ningún dato fue modificado. ===';

        return implode(PHP_EOL, $lines);
    }
}
