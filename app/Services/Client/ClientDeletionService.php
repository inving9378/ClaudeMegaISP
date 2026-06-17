<?php

namespace App\Services\Client;

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Jobs\DeletedClientWithServiceJob;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Support\Facades\DB;

/**
 * Borra físicamente un cliente y TODAS sus referencias (descubiertas desde el
 * esquema por ClientReferenceResolver), en una sola transacción.
 *
 * Política:
 *  - LIBERAR: infraestructura/equipo que sigue existiendo sin el cliente
 *    (IPs, nomenclaturas, puertos de mapa, ONUs, módems) → se pone client_id = null.
 *  - BORRAR: todo lo demás (datos propios + contables/auditoría, por decisión).
 *
 * Antes el borrado limpiaba ~14 relaciones a mano y dejaba huérfanos (invoices,
 * receipts, tickets, mikrotik_ppoes, files, sales, talento, referidos…). Ahora
 * el resolver garantiza cobertura completa y se auto-mantiene.
 */
class ClientDeletionService
{
    public function __construct(private ClientReferenceResolver $resolver)
    {
    }

    /**
     * Tablas que NO se borran: se libera su client_id (la fila sobrevive).
     * [tabla => valores extra a setear además de client_id => null]
     */
    private function freeReferences(): array
    {
        return [
            'network_ips'       => ['used' => ComunConstantsController::IS_NUMERICAL_FALSE, 'used_by' => '--'],
            'nomenclatures'     => [],
            'map_ports'         => [],
            'map_devices_ports' => [],
            'olt_onus'          => [],
            'modems'            => [],
        ];
    }

    /**
     * Borra físicamente al cliente. Devuelve el resumen [referencia => filas].
     */
    public function forceDeleteClient(Client $client): array
    {
        $clientId = (int) $client->id;
        $free = $this->freeReferences();

        // Efecto externo ANTES de borrar: quitar el servicio del address-list de Mikrotik.
        foreach ($client->internet_service as $service) {
            if ($service) {
                DeletedClientWithServiceJob::dispatchAfterResponse($service);
            }
        }

        $summary = [];

        DB::transaction(function () use ($clientId, $free, &$summary) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                // 1. Liberar infraestructura (client_id = null, sin borrar la fila).
                foreach ($free as $table => $extra) {
                    $affected = DB::table($table)
                        ->where('client_id', $clientId)
                        ->update(array_merge($extra, ['client_id' => null]));
                    if ($affected > 0) {
                        $summary["LIBERADA $table"] = $affected;
                    }
                }

                // 2. Borrar el resto de referencias directas.
                foreach ($this->resolver->directReferences() as $ref) {
                    if (array_key_exists($ref['table'], $free)) {
                        continue; // ya se liberó arriba
                    }
                    $affected = DB::table($ref['table'])
                        ->where($ref['column'], $clientId)
                        ->delete();
                    if ($affected > 0) {
                        $summary[$ref['table'] . '.' . $ref['column']] = $affected;
                    }
                }

                // 3. Borrar referencias polimórficas (solo filas cuyo *_type es Client).
                foreach ($this->resolver->polymorphicReferences() as $ref) {
                    $affected = DB::table($ref['table'])
                        ->where($ref['id_column'], $clientId)
                        ->whereIn($ref['type_column'], ClientReferenceResolver::CLIENT_MORPH_TYPES)
                        ->delete();
                    if ($affected > 0) {
                        $summary[$ref['table'] . '.' . $ref['id_column'] . ' (morph)'] = $affected;
                    }
                }

                // 4. Borrar físicamente al cliente.
                $summary['clients.id'] = DB::table('clients')->where('id', $clientId)->delete();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        return $summary;
    }
}
