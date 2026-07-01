<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nota de resolución de un ticket de conciliación: el operador debe escribir
     * cómo se resolvió/por qué se descartó antes de cerrarlo. Deja rastro visible
     * junto con resolved_by/resolved_at. Aditiva, nullable (los tickets ya
     * cerrados antes de esta mejora no tienen nota).
     */
    public function up(): void
    {
        Schema::table('reconciliation_tickets', function (Blueprint $table) {
            $table->text('resolution_note')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_tickets', function (Blueprint $table) {
            $table->dropColumn('resolution_note');
        });
    }
};
