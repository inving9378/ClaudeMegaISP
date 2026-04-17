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
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('status_seller');
            $table->dropColumn('type');
    
            $table->unsignedBigInteger('status_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
    
            $table->foreign('status_id')->references('id')->on('seller_status');
            $table->foreign('type_id')->references('id')->on('seller_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropForeign(['type_id']);
    
            $table->dropColumn('status_id');
            $table->dropColumn('type_id');
    
            $table->string('status_seller')->default('Activo');
            $table->string('type')->default('Interno');
        });
    }
};
