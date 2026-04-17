<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commissions_details', function (Blueprint $table) {
            $table->dropForeign(['prospect_id']);
            $table->foreign('prospect_id')
                  ->references('id')
                  ->on('crms')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_details', function (Blueprint $table) {
            $table->dropForeign(['prospect_id']);
            $table->foreign('prospect_id')
                  ->references('id')
                  ->on('crm_lead_information')
                  ->onDelete('cascade');
        });
    }
};
