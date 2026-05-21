<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora persistente de import/export. Sustituye al tracking efímero en
     * Cache: aquí queda historial consultable, archivos generados con su path,
     * conteos finales y errores, sin importar reinicio del worker/redis.
     */
    public function up(): void
    {
        Schema::create('import_export_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['import', 'export']);
            $table->string('filename')->nullable();
            $table->string('format', 16)->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('modules_selected')->nullable();
            $table->json('fields_selected')->nullable();
            $table->json('ai_analysis')->nullable();
            $table->string('output_path')->nullable();
            $table->string('job_id', 64)->nullable()->index();
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->string('admin_user')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_export_logs');
    }
};
