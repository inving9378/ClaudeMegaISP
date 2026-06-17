<?php

namespace App\Console\Commands\Scripts;

use App\Services\Client\ClientIdMigrator;
use App\Services\Client\ClientReferenceResolver;
use Illuminate\Console\Command;

/**
 * Lista TODAS las tablas/columnas que referencian a un cliente. Sin id solo
 * muestra el mapa de referencias; con id cuenta cuántas filas se reasignarían
 * (solo lectura, NO modifica datos). Sirve para auditar la cobertura del
 * cambio de id de cliente antes de ejecutarlo.
 */
class ListClientReferences extends Command
{
    protected $signature = 'client:references {id? : ID del cliente para contar filas afectadas}';

    protected $description = 'Audita (solo lectura) las referencias de clientes que se reasignarían al cambiar un id.';

    public function handle(ClientReferenceResolver $resolver, ClientIdMigrator $migrator): int
    {
        $id = $this->argument('id');

        if ($id === null) {
            $direct = $resolver->directReferences();
            $poly   = $resolver->polymorphicReferences();

            $this->info('Referencias DIRECTAS (client_id / FK a clients): ' . count($direct));
            $this->table(['Tabla', 'Columna'], array_map(
                fn ($r) => [$r['table'], $r['column']],
                $direct
            ));

            $this->info('Referencias POLIMÓRFICAS (morphTo, filtradas por tipo Client): ' . count($poly));
            $this->table(['Tabla', 'Columna id', 'Columna tipo'], array_map(
                fn ($r) => [$r['table'], $r['id_column'], $r['type_column']],
                $poly
            ));

            $this->line('Tip: pasa un id de cliente para contar filas reales: php artisan client:references 7510');

            return self::SUCCESS;
        }

        $preview = $migrator->preview((int) $id);

        $rows = [];
        $total = 0;
        foreach (['direct' => 'directa', 'polymorphic' => 'morph'] as $key => $tipo) {
            foreach ($preview[$key] as $item) {
                if ($item['rows'] > 0) {
                    $rows[] = [$item['ref'], $tipo, $item['rows']];
                    $total += $item['rows'];
                }
            }
        }

        usort($rows, fn ($a, $b) => $b[2] <=> $a[2]);

        $this->info("Cliente #$id — filas que se reasignarían al cambiar su id:");
        $this->table(['Referencia', 'Tipo', 'Filas'], $rows ?: [['(ninguna)', '-', 0]]);
        $this->line("Total de filas a reasignar: $total  (en " . count($rows) . ' tablas)');

        return self::SUCCESS;
    }
}
