<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_work_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->unsignedBigInteger('type_id');
            $table->unsignedSmallInteger('points');       // snapshot del tipo al crear
            $table->boolean('is_billable');               // snapshot del tipo al crear
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('olt_onu_id')->nullable();
            $table->enum('status', ['pending','in_progress','completed','validated','cancelled'])
                  ->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('colaborador_id')->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('type_id')->references('id')->on('talento_work_order_types')->onDelete('restrict');
            $table->index(['colaborador_id', 'status']);
            $table->index(['scheduled_at']);
            $table->index(['status']);
        });

        Schema::create('talento_work_order_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->text('description');
            $table->unsignedSmallInteger('duration_minutes')->default(0);
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_work_order_activities');
        Schema::dropIfExists('talento_work_orders');
    }
};
