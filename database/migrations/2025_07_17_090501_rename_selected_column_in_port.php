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
        Schema::table('map_splitters_ports', function (Blueprint $table) {
            $table->renameColumn('selected', 'connected')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('map_splitters_ports', function (Blueprint $table) {
            $table->renameColumn('connected', 'selected')->change();
        });
    }
};
