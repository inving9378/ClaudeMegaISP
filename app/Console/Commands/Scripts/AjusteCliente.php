<?php

namespace App\Console\Commands\Scripts;

use App\Services\Client\ClientIdMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AjusteCliente extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ajuste-cliente {id_actual : ID actual del cliente} {id_nuevo : Nuevo ID que tendrá el cliente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cambia el ID de un cliente y todas sus relaciones';

    /**
     * Execute the console command.
     *
     * Delega en ClientIdMigrator, que descubre TODAS las referencias del cliente
     * desde el esquema (directas + polimórficas). Antes este comando tenía una
     * lista de tablas a mano que se quedaba corta (mismos huérfanos que editId).
     */
    public function handle(ClientIdMigrator $migrator): int
    {
        $idActual = (int) $this->argument('id_actual');
        $idNew = (int) $this->argument('id_nuevo');

        if (! DB::table('clients')->where('id', $idActual)->exists()) {
            $this->error("El cliente #$idActual no existe.");
            return 1;
        }

        if (DB::table('clients')->where('id', $idNew)->exists()) {
            $this->error("El ID destino #$idNew ya está ocupado.");
            return 1;
        }

        try {
            $summary = $migrator->migrate($idActual, $idNew);
        } catch (\Throwable $e) {
            $this->error('Error durante el proceso: ' . $e->getMessage());
            return 1;
        }

        $total = array_sum($summary);
        $this->info("Cliente #$idActual reasignado a #$idNew. Filas reasignadas: $total (en " . count($summary) . ' tablas).');

        return 0;
    }
}
