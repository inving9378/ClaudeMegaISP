<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->date('date');
            $table->enum('status', ['draft', 'active', 'completed'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->unique(['colaborador_id', 'date'], 'tr_col_date_unique');
        });

        Schema::create('talento_route_stops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedSmallInteger('sequence');
            $table->timestamp('eta')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('route_id')->references('id')->on('talento_routes')->onDelete('cascade');
            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
            $table->unique(['route_id', 'sequence'], 'trs_route_seq_unique');
        });

        // Deviation alerts — append-only
        Schema::create('talento_route_deviations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('colaborador_id');
            $table->decimal('detected_lat', 10, 7);
            $table->decimal('detected_lng', 10, 7);
            $table->decimal('deviation_m', 8, 1);   // distance from route corridor
            $table->unsignedSmallInteger('sustained_minutes'); // consecutive minutes off-corridor
            $table->boolean('supervisor_notified')->default(false);
            $table->timestamp('detected_at')->useCurrent();

            $table->foreign('route_id')->references('id')->on('talento_routes')->onDelete('cascade');
            $table->index(['route_id', 'detected_at'], 'trd_route_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_route_deviations');
        Schema::dropIfExists('talento_route_stops');
        Schema::dropIfExists('talento_routes');
    }
};
