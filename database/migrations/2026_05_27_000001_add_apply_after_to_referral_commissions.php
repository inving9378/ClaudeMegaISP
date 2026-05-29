<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->timestamp('apply_after_at')->nullable()->after('status')
                ->comment('Comisión solo se aplica después de esta fecha (garantía 15 días anti-reversas)');
            $table->timestamp('applied_at')->nullable()->after('apply_after_at')
                ->comment('Timestamp exacto cuando pasó a applied — auditoría');
            $table->index('apply_after_at', 'idx_apply_after_at');

            // Idempotencia: evita duplicados si InvoicePaid se dispara más de una vez
            $table->unique(
                ['invoice_id', 'beneficiary_id', 'level'],
                'uniq_invoice_beneficiary_level'
            );
        });
    }

    public function down(): void
    {
        Schema::table('referral_commissions', function (Blueprint $table) {
            $table->dropUnique('uniq_invoice_beneficiary_level');
            $table->dropIndex('idx_apply_after_at');
            $table->dropColumn(['apply_after_at', 'applied_at']);
        });
    }
};
