<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Item #869 (origen #829/#808): el rebuild limpio de megaisp_dryrun (#817/#818) reveló
// drift real entre lo que declaran las migraciones 2026_05_26_300004/300006 y la BD viva
// megaisp de dev — el índice/FK quedó registrado como "Ran" en la tabla `migrations` pero
// nunca se materializó en el esquema (mismo patrón que las "migraciones fantasma" del
// item #534). Verificado en vivo (SHOW INDEX / information_schema): 0 filas huérfanas en
// converted_client_id/prospect_id, 0 duplicados en referred_client_id → seguro reponer.
// Idempotente: cada bloque checa antes de crear, para poder correr sin importar qué
// subconjunto ya exista.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referral_prospects') && Schema::hasColumn('referral_prospects', 'converted_client_id')) {
            if (! $this->hasForeignKey('referral_prospects', 'referral_prospects_converted_client_id_foreign')) {
                Schema::table('referral_prospects', function (Blueprint $table) {
                    $table->foreign('converted_client_id', 'referral_prospects_converted_client_id_foreign')
                        ->references('id')->on('clients')->onDelete('set null');
                });
            }
        }

        if (Schema::hasTable('referrals')) {
            if (! $this->hasIndex('referrals', 'idx_referral_chain')) {
                Schema::table('referrals', function (Blueprint $table) {
                    $table->index('chain_path', 'idx_referral_chain');
                });
            }

            if (! $this->hasIndex('referrals', 'idx_referral_status')) {
                Schema::table('referrals', function (Blueprint $table) {
                    $table->index('status', 'idx_referral_status');
                });
            }

            if (! $this->hasIndex('referrals', 'referrals_referred_client_id_unique')) {
                Schema::table('referrals', function (Blueprint $table) {
                    $table->unique('referred_client_id', 'referrals_referred_client_id_unique');
                });
            }

            if (Schema::hasColumn('referrals', 'prospect_id') && ! $this->hasForeignKey('referrals', 'referrals_prospect_id_foreign')) {
                Schema::table('referrals', function (Blueprint $table) {
                    $table->foreign('prospect_id', 'referrals_prospect_id_foreign')
                        ->references('id')->on('referral_prospects')->onDelete('set null');
                });
            }
        }
    }

    public function down(): void
    {
        // Restauración de drift, no de un feature nuevo: down() vacío a propósito
        // (dropear estas constraints reintroduciría el drift original).
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$table, $indexName]
        );

        return (bool) $row;
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL LIMIT 1',
            [$table, $constraintName]
        );

        return (bool) $row;
    }
};
