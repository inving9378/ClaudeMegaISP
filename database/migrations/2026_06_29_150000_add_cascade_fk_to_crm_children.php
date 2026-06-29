<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #169 — Red de seguridad: FK ON DELETE CASCADE en los hijos del CRM que hoy
 * no la tienen (document_crms, deal_crms, quote_crms), alineado con el patrón
 * ya usado en crm_main_information / crm_lead_information.
 *
 * El fix real es el CrmObserver (limpia también files + archivo físico, que
 * una FK no puede tocar). Esta FK es defensa-en-profundidad a nivel BD.
 *
 * Additive-only. Prod-safe e idempotente:
 *  1) Purga las filas huérfanas preexistentes (crm_id sin padre en crms) — si
 *     no, ADD FOREIGN KEY fallaría. En document_crms también borra sus files.
 *     (El archivo físico NO se toca aquí; eso es la pasada separada de #81.)
 *  2) Normaliza crm_id a BIGINT UNSIGNED para que case con crms.id (hoy es
 *     signed -> la FK no se podría crear). Data-safe: los ids son positivos.
 *  3) Crea la FK ON DELETE CASCADE solo si no existe.
 */
return new class extends Migration
{
    private array $tables = ['document_crms', 'deal_crms', 'quote_crms'];

    public function up(): void
    {
        // 1) Purga de huérfanos preexistentes (para no violar la FK al crearla).
        $orphanDocIds = DB::table('document_crms')
            ->whereNotIn('crm_id', fn ($q) => $q->select('id')->from('crms'))
            ->pluck('id');
        if ($orphanDocIds->isNotEmpty()) {
            DB::table('files')
                ->where('fileable_type', 'App\\Models\\DocumentCrm')
                ->whereIn('fileable_id', $orphanDocIds)
                ->delete();
            DB::table('document_crms')->whereIn('id', $orphanDocIds)->delete();
        }
        DB::table('deal_crms')->whereNotIn('crm_id', fn ($q) => $q->select('id')->from('crms'))->delete();
        DB::table('quote_crms')->whereNotIn('crm_id', fn ($q) => $q->select('id')->from('crms'))->delete();

        // 2) Normalizar el tipo para que case con crms.id (bigint unsigned).
        foreach ($this->tables as $t) {
            DB::statement("ALTER TABLE `{$t}` MODIFY `crm_id` BIGINT UNSIGNED NOT NULL");
        }

        // 3) FK ON DELETE CASCADE (idempotente).
        foreach ($this->tables as $t) {
            $fk = "{$t}_crm_id_foreign";
            if (!$this->foreignKeyExists($fk)) {
                Schema::table($t, function (Blueprint $table) use ($fk) {
                    $table->foreign('crm_id', $fk)->references('id')->on('crms')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            $fk = "{$t}_crm_id_foreign";
            if ($this->foreignKeyExists($fk)) {
                Schema::table($t, fn (Blueprint $table) => $table->dropForeign($fk));
            }
        }
        // El tipo de columna se deja en BIGINT UNSIGNED (revertirlo no aporta).
    }

    private function foreignKeyExists(string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
