<?php

namespace App\Services\Client;

use Illuminate\Support\Facades\DB;

/**
 * Cambia el id de un cliente reasignando TODAS sus referencias (directas y
 * polimórficas) descubiertas por ClientReferenceResolver, en una sola
 * transacción, y reposiciona el AUTO_INCREMENT de `clients`.
 *
 * Usa UPDATEs por conjunto (DB::table(...)->update) en vez de cargar modelos:
 * es más rápido, no depende de que exista un modelo Eloquent por tabla y no
 * dispara eventos (la migración de id no debe disparar side-effects de negocio).
 */
class ClientIdMigrator
{
    public function __construct(private ClientReferenceResolver $resolver)
    {
    }

    /**
     * Ejecuta la migración. Devuelve el resumen [referencia => filas afectadas].
     */
    public function migrate(int $oldId, int $newId): array
    {
        $summary = [];

        DB::transaction(function () use ($oldId, $newId, &$summary) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                // Hijos directos (client_id / FKs a clients).
                foreach ($this->resolver->directReferences() as $ref) {
                    $affected = DB::table($ref['table'])
                        ->where($ref['column'], $oldId)
                        ->update([$ref['column'] => $newId]);
                    if ($affected > 0) {
                        $summary[$ref['table'] . '.' . $ref['column']] = $affected;
                    }
                }

                // Hijos polimórficos: solo filas cuyo *_type apunta a un cliente.
                foreach ($this->resolver->polymorphicReferences() as $ref) {
                    $affected = DB::table($ref['table'])
                        ->where($ref['id_column'], $oldId)
                        ->whereIn($ref['type_column'], ClientReferenceResolver::CLIENT_MORPH_TYPES)
                        ->update([$ref['id_column'] => $newId]);
                    if ($affected > 0) {
                        $summary[$ref['table'] . '.' . $ref['id_column'] . ' (morph)'] = $affected;
                    }
                }

                // El cliente mismo, al final.
                $summary['clients.id'] = DB::table('clients')
                    ->where('id', $oldId)
                    ->update(['id' => $newId]);
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        // Reposicionar el contador interno. Es DDL (commit implícito) => va
        // fuera de la transacción. MySQL nunca lo baja por debajo de MAX(id).
        $nextId = (int) DB::table('clients')->max('id') + 1;
        DB::statement('ALTER TABLE clients AUTO_INCREMENT = ' . $nextId);

        return $summary;
    }

    /**
     * Cuenta, SIN modificar nada, cuántas filas se reasignarían para un cliente.
     * Devuelve ['direct' => [['ref','rows'],...], 'polymorphic' => [...]].
     */
    public function preview(int $clientId): array
    {
        $direct = [];
        foreach ($this->resolver->directReferences() as $ref) {
            $direct[] = [
                'ref'  => $ref['table'] . '.' . $ref['column'],
                'rows' => DB::table($ref['table'])->where($ref['column'], $clientId)->count(),
            ];
        }

        $polymorphic = [];
        foreach ($this->resolver->polymorphicReferences() as $ref) {
            $polymorphic[] = [
                'ref'  => $ref['table'] . '.' . $ref['id_column'],
                'rows' => DB::table($ref['table'])
                    ->where($ref['id_column'], $clientId)
                    ->whereIn($ref['type_column'], ClientReferenceResolver::CLIENT_MORPH_TYPES)
                    ->count(),
            ];
        }

        return ['direct' => $direct, 'polymorphic' => $polymorphic];
    }
}
