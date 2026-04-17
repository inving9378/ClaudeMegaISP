<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cut_installations', function (Blueprint $table) {
            $table->dropForeign(['technical_id']);
            $table->dropForeign(['branch_id']);
        });

        DB::statement('ALTER TABLE cut_installations MODIFY technical_id bigint UNSIGNED NULL,  MODIFY branch_id bigint UNSIGNED NULL, MODIFY service_amount double null, MODIFY installation_cost double null, MODIFY warranty_cost varchar(255) null, MODIFY constance varchar(255) null');

        Schema::table('cut_installations', function (Blueprint $table) {
            $table->foreign('technical_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('sucursals')->nullOnDelete();

            if (Schema::hasColumn('cut_installations', 'service_id')) {
                $table->dropColumn('service_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
