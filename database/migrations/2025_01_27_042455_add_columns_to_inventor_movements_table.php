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
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('from')->after('description')->nullable();
            $table->string('to')->after('from')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_movements', 'from')) {
                $table->dropColumn('from');
            }
            if (Schema::hasColumn('inventory_movements', 'to')) {
                $table->dropColumn('to');
            }
        });
    }
};
