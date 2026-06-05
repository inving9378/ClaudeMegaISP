<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_work_orders', function (Blueprint $table) {
            $table->text('nota_tecnico')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('talento_work_orders', function (Blueprint $table) {
            $table->dropColumn('nota_tecnico');
        });
    }
};
