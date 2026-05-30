<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warroom_kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('period');
            $table->json('kpis');
            $table->timestamp('snapshot_at');
            $table->timestamps();
            $table->unique('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warroom_kpi_snapshots');
    }
};
