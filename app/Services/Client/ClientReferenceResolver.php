<?php

namespace App\Services\Client;

use Illuminate\Support\Facades\DB;

/**
 * Descubre, leyendo el esquema (information_schema) en tiempo real, TODAS las
 * tablas/columnas que referencian a un cliente. Así el cambio de id de cliente
 * nunca se queda corto aunque se agreguen módulos nuevos: si una tabla apunta a
 * `clients`, este resolver la encuentra sola.
 *
 * Dos tipos de referencia:
 *  - directa: FK declarada hacia `clients` + cualquier columna llamada `client_id`.
 *  - polimórfica: pares `<base>_id` + `<base>_type` (morphTo). Se actualiza SOLO
 *    cuando el `_type` corresponde a un cliente (CLIENT_MORPH_TYPES), evitando
 *    tocar filas de otros modelos con el mismo id.
 */
class ClientReferenceResolver
{
    /**
     * Strings de tipo que un morphTo guarda cuando apunta a un cliente.
     * Hay dos clases Client (App\Models\Client extiende la modular); ambas
     * pueden aparecer almacenadas.
     */
    public const CLIENT_MORPH_TYPES = [
        'App\\Models\\Client',
        'App\\Modules\\Core\\Clientes\\Models\\Client',
    ];

    /**
     * Prefijos de tablas cuyo `client_id` NO es el cliente final del ISP, sino
     * un tenant del producto SaaS (Flotas). NO deben migrarse con el cliente.
     */
    private const DENYLIST_PREFIXES = ['fleet_'];

    /** Tablas puntuales a excluir (además de los prefijos). */
    private const DENYLIST_TABLES = [];

    /**
     * Referencias directas: [['table' => ..., 'column' => ...], ...]
     */
    public function directReferences(): array
    {
        $schema = DB::getDatabaseName();

        $rows = DB::select(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = 'clients'
             UNION
             SELECT TABLE_NAME AS t, COLUMN_NAME AS c
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = 'client_id'",
            [$schema, $schema]
        );

        $refs = [];
        foreach ($rows as $r) {
            if ($r->t === 'clients' || $this->isDenied($r->t)) {
                continue;
            }
            $refs[$r->t . '.' . $r->c] = ['table' => $r->t, 'column' => $r->c];
        }
        ksort($refs);

        return array_values($refs);
    }

    /**
     * Referencias polimórficas: [['table' => ..., 'id_column' => ..., 'type_column' => ...], ...]
     */
    public function polymorphicReferences(): array
    {
        $schema = DB::getDatabaseName();

        // Empareja cada columna `<base>_id` con su hermana `<base>_type`.
        $rows = DB::select(
            "SELECT c1.TABLE_NAME AS t, c1.COLUMN_NAME AS idc, c2.COLUMN_NAME AS typec
               FROM information_schema.COLUMNS c1
               JOIN information_schema.COLUMNS c2
                 ON c1.TABLE_SCHEMA = c2.TABLE_SCHEMA AND c1.TABLE_NAME = c2.TABLE_NAME
              WHERE c1.TABLE_SCHEMA = ?
                AND c1.COLUMN_NAME LIKE '%\\_id'
                AND c2.COLUMN_NAME = CONCAT(SUBSTRING(c1.COLUMN_NAME, 1, CHAR_LENGTH(c1.COLUMN_NAME) - 3), '_type')",
            [$schema]
        );

        $refs = [];
        foreach ($rows as $r) {
            if ($this->isDenied($r->t)) {
                continue;
            }
            $refs[$r->t . '.' . $r->idc] = [
                'table'       => $r->t,
                'id_column'   => $r->idc,
                'type_column' => $r->typec,
            ];
        }
        ksort($refs);

        return array_values($refs);
    }

    private function isDenied(string $table): bool
    {
        foreach (self::DENYLIST_PREFIXES as $prefix) {
            if (str_starts_with($table, $prefix)) {
                return true;
            }
        }

        return in_array($table, self::DENYLIST_TABLES, true);
    }
}
