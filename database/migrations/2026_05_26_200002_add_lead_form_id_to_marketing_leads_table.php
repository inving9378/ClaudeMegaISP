<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table) {
            $table->foreignId('lead_form_id')
                ->nullable()
                ->after('source_id')
                ->constrained('marketing_lead_forms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_leads', function (Blueprint $table) {
            $table->dropForeign(['lead_form_id']);
            $table->dropColumn('lead_form_id');
        });
    }
};
