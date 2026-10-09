<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('monitoring_case_documents', 'ai_analyzed_at')) {
            return; // idempotente
        }
        Schema::table('monitoring_case_documents', function (Blueprint $table) {
            $table->text('ai_summary')->nullable()->after('notes');
            $table->text('ai_change_summary')->nullable()->after('ai_summary');
            $table->boolean('ai_has_relevant_change')->nullable()->after('ai_change_summary');
            $table->timestamp('ai_analyzed_at')->nullable()->after('ai_has_relevant_change');
            $table->text('ai_error')->nullable()->after('ai_analyzed_at');
        });
    }

    public function down(): void
    {
        // Columnas PROPIAS del módulo sobre su propia tabla: seguro retirarlas.
        Schema::table('monitoring_case_documents', function (Blueprint $table) {
            $table->dropColumn(['ai_summary', 'ai_change_summary', 'ai_has_relevant_change', 'ai_analyzed_at', 'ai_error']);
        });
    }
};
