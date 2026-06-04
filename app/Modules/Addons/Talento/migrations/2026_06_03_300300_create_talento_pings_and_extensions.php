<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_location_pings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('attendance_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_m', 7, 2)->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->timestamp('recorded_at');
            // NO updated_at — high-volume, append-only

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('attendance_id')->references('id')->on('talento_attendances')->onDelete('cascade');
            $table->index(['attendance_id', 'recorded_at'], 'tlp_att_time_idx');
            $table->index(['colaborador_id', 'recorded_at'], 'tlp_col_time_idx');
        });

        Schema::create('talento_shift_extensions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_id');
            $table->unsignedSmallInteger('minutes');
            $table->string('reason_category', 60); // overtime, emergency, client_request, other
            $table->text('reason_text')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('attendance_id')->references('id')->on('talento_attendances')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_shift_extensions');
        Schema::dropIfExists('talento_location_pings');
    }
};
