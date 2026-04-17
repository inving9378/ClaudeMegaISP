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
        Schema::table('cut_boxs', function (Blueprint $table) {
            $table->double('total_received')->after('user_id')->default(0);
            $table->double('total_extras')->after('total_received')->default(0);
            $table->double('total_technicals')->after('total_extras')->default(0);
            $table->double('total_proveedores')->after('total_technicals')->default(0);
            $table->double('total_net')->after('total_proveedores')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cut_boxs', function (Blueprint $table) {
            $table->dropColumn(['total_received', 'total_extras', 'total_technicals', 'total_proveedores', 'total_net']);
        });
    }
};
