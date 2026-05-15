<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sembrado para entorno local — crea ~5 clientes con ClientMainInformation
 * + ~5 prospectos CRM con CrmMainInformation + CrmLeadInformation. Idempotente
 * vía marcas en el email ("@dev.megafamilia.test"); re-ejecutar no duplica.
 *
 * Por qué INSERT raw en lugar de Eloquent:
 *   - ClientMainInformationObserver dispara setAddressClient (lee Colony /
 *     Municipality / State — todas vacías en dev), getGenerateUser (genera
 *     usernames), createNewUserRoleClient (crea User asociado).
 *   - LogsActivity escribiría a activity_log por cada INSERT.
 *   Bypassar todo eso para datos de demo es lo correcto; quien quiera
 *   ejercitar el flujo completo lo hace por la UI.
 *
 * Uso:
 *   php artisan db:seed --class='Database\Seeders\DevDataSeeder'
 */
class DevDataSeeder extends Seeder
{
    private const EMAIL_TAG = '@dev.megafamilia.test';

    private const FIRST_NAMES = ['Diego', 'Carla', 'Mario', 'Lucía', 'Javier', 'Andrea', 'Pablo', 'Sofía', 'Iván', 'Camila'];
    private const LAST_NAMES = ['Hernández', 'García', 'Ramírez', 'Sánchez', 'Torres', 'Castillo', 'Flores', 'Rivera', 'Morales', 'Vega'];
    private const STATUSES = ['Nuevo', 'En contacto', 'Cotizando', 'Negociando', 'Esperando decisión'];

    public function run(): void
    {
        $now = now();

        // BaseModel no auto-stampea (CLAUDE.md está desactualizado en ese
        // detalle); para tablas con `created_by`/`updated_by` NOT NULL hay
        // que aportar el id del usuario actor. En dev usamos el primer
        // usuario disponible — los simulators ya garantizan que existe.
        $userId = DB::table('users')->value('id');
        if (! $userId) {
            $this->command?->error('No hay usuarios en `users`. Corre antes los simulators o crea un admin.');
            return;
        }

        $this->command?->info('Sembrando clientes…');
        $clients = $this->seedClients($now, $userId, 5);
        $this->command?->info(sprintf('  %d clientes (nuevos o ya existentes).', count($clients)));

        $this->command?->info('Sembrando prospectos CRM…');
        $crms = $this->seedCrms($now, $userId, 5);
        $this->command?->info(sprintf('  %d prospectos (nuevos o ya existentes).', count($crms)));
    }

    /**
     * @return array<int>  ids de client_main_information procesados
     */
    private function seedClients(\Carbon\Carbon $now, int $userId, int $count): array
    {
        $processed = [];
        for ($i = 0; $i < $count; $i++) {
            $first = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[$i % count(self::LAST_NAMES)];
            $email = strtolower($first . '.' . $last . self::EMAIL_TAG);

            $existing = DB::table('client_main_information')->where('email', $email)->first();
            if ($existing) {
                $processed[] = $existing->id;
                continue;
            }

            $clientId = DB::table('clients')->insertGetId([
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $cmiId = DB::table('client_main_information')->insertGetId(array_filter([
                'client_id' => $clientId,
                'name' => $first,
                'father_last_name' => $last,
                'mother_last_name' => self::LAST_NAMES[($i + 3) % count(self::LAST_NAMES)],
                'email' => $email,
                'phone' => '555-' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
                'estado' => $i === 0 ? 'Nuevo' : ($i % 2 ? 'Activo' : 'Online'),
                'activation_date' => $now->copy()->subDays(30 - $i * 5)->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ], fn ($v) => $v !== null));

            $processed[] = $cmiId;
        }
        return $processed;
    }

    /**
     * @return array<int>  ids de crm_main_information procesados
     */
    private function seedCrms(\Carbon\Carbon $now, int $userId, int $count): array
    {
        $processed = [];
        for ($i = 0; $i < $count; $i++) {
            $first = self::FIRST_NAMES[($i + 5) % count(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[($i + 5) % count(self::LAST_NAMES)];
            $email = strtolower('crm.' . $first . '.' . $last . self::EMAIL_TAG);

            $existing = DB::table('crm_main_information')->where('email', $email)->first();
            if ($existing) {
                $processed[] = $existing->id;
                continue;
            }

            $crmId = DB::table('crms')->insertGetId([
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('crm_main_information')->insert(array_filter([
                'crm_id' => $crmId,
                'name' => $first,
                'father_last_name' => $last,
                'mother_last_name' => self::LAST_NAMES[($i + 2) % count(self::LAST_NAMES)],
                'email' => $email,
                'phone' => '555-' . str_pad((string) (2000 + $i), 4, '0', STR_PAD_LEFT),
                'created_at' => $now,
                'updated_at' => $now,
            ], fn ($v) => $v !== null));

            DB::table('crm_lead_information')->insert(array_filter([
                'crm_id' => $crmId,
                'crm_status' => self::STATUSES[$i % count(self::STATUSES)],
                'last_contacted' => $now->copy()->subDays($i * 2)->toDateString(),
                'score' => 1 + $i,
                'created_at' => $now,
                'updated_at' => $now,
            ], fn ($v) => $v !== null));

            $processed[] = $crmId;
        }
        return $processed;
    }
}
