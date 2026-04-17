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
        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->string('box_nomenclator_old')->after('box_nomenclator')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_additional_information', function (Blueprint $table) {
            $table->dropColumn('box_nomenclator_old');
        });
    }
};
