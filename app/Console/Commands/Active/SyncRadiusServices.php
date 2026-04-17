<?php

namespace App\Console\Commands\Active;

use App\Http\Repository\ClientInternetServiceRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ClientInternetService;

class SyncRadiusServices extends Command
{
    protected $signature = 'radius:sync {--force : Sincronizar todo sin preguntar}';

    protected $description = 'Sincroniza usuarios y contraseñas de Meganet con la BD de FreeRADIUS';

    public function handle()
    {
        $this->info('Iniciando sincronización con Radius...');
        $clientInternetServiceRepository = new ClientInternetServiceRepository();
        $servicios = $clientInternetServiceRepository->getServicesWhereHasIp();

        $bar = $this->output->createProgressBar(count($servicios));
        $bar->start();

        foreach ($servicios as $servicio) {
            $username = $servicio->client_name;
            $password = $servicio->password;

            if (empty($username) || empty($password)) {
                $bar->advance();
                continue;
            }

            // 2. Sincronizar Contraseña en radcheck
            // El operador ':=' es estándar para Cleartext-Password en FreeRADIUS
            DB::connection('radius')->table('radcheck')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Cleartext-Password'],
                [
                    'op'    => ':=',
                    'value' => $password
                ]
            );

            // 3. Sincronizar IP fija en radreply (si el servicio tiene una asignada)
            if (!empty($servicio->network_ip_used_by->ip)) {
                DB::connection('radius')->table('radreply')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Framed-IP-Address'],
                    [
                        'op'    => '==',
                        'value' => $servicio->network_ip_used_by->ip
                    ]
                );
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // 4. LIMPIEZA: Eliminar usuarios en Radius que ya no existen en Meganet
        $this->info('Limpiando usuarios obsoletos en Radius...');

        $usernamesEnMeganet = $servicios->pluck('client_name')->toArray();

        $usuariosBorrados = DB::connection('radius')->table('radcheck')
            ->whereNotIn('username', $usernamesEnMeganet)
            ->delete();

        DB::connection('radius')->table('radreply')
            ->whereNotIn('username', $usernamesEnMeganet)
            ->delete();

        $this->info("Sincronización finalizada. Usuarios actualizados: " . count($servicios) . ". Usuarios eliminados: $usuariosBorrados.");
    }
}
