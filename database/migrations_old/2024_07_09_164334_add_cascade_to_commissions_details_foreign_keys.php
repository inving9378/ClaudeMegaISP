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
            $table->dropForeign(['bundle_id']);
            $table->dropForeign(['prospect_id']);
            $table->dropForeign(['client_id']);

            $table->foreign('bundle_id')
                  ->references('id')->on('client_bundle_services')
                  ->onDelete('cascade');

            $table->foreign('prospect_id')
                  ->references('id')->on('crm_lead_information')
                  ->onDelete('cascade');

            $table->foreign('client_id')
                  ->references('id')->on('client_main_information')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions_details', function (Blueprint $table) {
            $table->dropForeign(['bundle_id']);
            $table->dropForeign(['prospect_id']);
            $table->dropForeign(['client_id']);

            $table->foreign('bundle_id')
                  ->references('id')->on('client_bundle_services');

            $table->foreign('prospect_id')
                  ->references('id')->on('crm_lead_information');

            $table->foreign('client_id')
                  ->references('id')->on('client_main_information');
        });
    }
};
