<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voip_troncales', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_entrante_id')->nullable()->after('did');
            $table->foreign('grupo_entrante_id')
                  ->references('id')->on('voip_grupos_timbrado')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('voip_troncales', function (Blueprint $table) {
            $table->dropForeign(['grupo_entrante_id']);
            $table->dropColumn('grupo_entrante_id');
        });
    }
};
