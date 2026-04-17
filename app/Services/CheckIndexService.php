<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckIndexService
{

    /**
     * Check for missing indexes in specified columns of tables.
     *
     * @param array $tablesAndColumns
     * @return array
     */
    public function checkMissingIndexes(array $tablesAndColumns): array
    {
        $missingIndexes = [];

        foreach ($tablesAndColumns as $table => $columns) {
            $indexedColumns = $this->getIndexedColumns($table);

            foreach ($columns as $column) {
                if (!in_array($column, $indexedColumns)) {
                    $missingIndexes[$table][] = $column;
                }
            }
        }

        return $missingIndexes;
    }

    public static function getTablesWithNonIndexedColumns(): array
    {
        $tables = DB::connection()->getDoctrineSchemaManager()->listTableNames();
        $result = [];

        foreach ($tables as $table) {
            // Obtener todas las columnas de la tabla
            $columns = Schema::getColumnListing($table);

            // Obtener los índices de la tabla
            $indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes($table);
            $indexedColumns = [];

            // Extraer las columnas que son parte de índices
            foreach ($indexes as $index) {
                $indexedColumns = array_merge($indexedColumns, $index->getColumns());
            }

            // Filtrar columnas no indexadas (eliminar duplicados y quedarse con las no indexadas)
            $nonIndexedColumns = array_diff($columns, array_unique($indexedColumns));

            if (!empty($nonIndexedColumns)) {
                $result[$table] = array_values($nonIndexedColumns);
            }
        }

        return $result;
    }

    /**
     * Get indexed columns for a table.
     *
     * @param string $table
     * @return array
     */
    private function getIndexedColumns(string $table): array
    {
        $indexes = DB::select("SHOW INDEX FROM $table");

        $indexedColumns = [];
        foreach ($indexes as $index) {
            $indexedColumns[] = $index->Column_name;
        }

        return array_unique($indexedColumns);
    }

    public function addMissingIndexes(array $missingIndexes): array
    {
        $indexedColumns = [];

        foreach ($missingIndexes as $table => $columns) {
            foreach ($columns as $column) {
                try {
                    // Crear índice con un nombre único, basado en la tabla y la columna
                    $indexName = "idx_{$table}_{$column}";
                    DB::statement("CREATE INDEX $indexName ON $table($column)");

                    $indexedColumns[$table][] = $column;
                } catch (\Exception $e) {
                    // Captura cualquier error al crear el índice
                    $indexedColumns[$table]['failed'][] = [
                        'column' => $column,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return $indexedColumns;
    }
}
