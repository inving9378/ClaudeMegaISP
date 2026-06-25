<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dropea la columna muerta `threshold_covered_at` de client_referral_profiles: no tiene
 * ningún escritor (el timestamp real de activación es `activated_at`). Verificado:
 * 100% NULL antes de dropear. Reversible (la re-crea nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('client_referral_profiles', 'threshold_covered_at')) {
            Schema::table('client_referral_profiles', function (Blueprint $t) {
                $t->dropColumn('threshold_covered_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('client_referral_profiles', 'threshold_covered_at')) {
            Schema::table('client_referral_profiles', function (Blueprint $t) {
                $t->timestamp('threshold_covered_at')->nullable()->after('threshold_amount_paid');
            });
        }
    }
};
