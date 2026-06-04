<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-paso 1: Credenciales del colaborador
        Schema::create('talento_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->enum('type', ['driver_license', 'other'])->default('driver_license');
            $table->string('document_number', 60)->nullable();
            $table->text('file_path')->nullable();       // foto/escaneo CIFRADO con Crypt::encryptString
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->enum('status', ['valid', 'expiring', 'expired', 'missing'])->default('missing');
            $table->unsignedTinyInteger('alert_weeks_before')->default(8);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('cascade');
            $table->unique(['colaborador_id', 'type'], 'tc_col_type_unique');
            $table->index(['status', 'expires_at'], 'tc_status_expires_idx');
        });

        // Sub-paso 3: Fondo de ahorro forzoso
        Schema::create('talento_funds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('colaborador_id');
            $table->enum('purpose', ['license', 'other'])->default('license');
            $table->decimal('target_amount', 10, 2);       // costo de renovación/licencia nueva
            $table->decimal('accumulated', 10, 2)->default(0.00);
            $table->decimal('weekly_deduction', 10, 2);    // monto semanal a apartar
            $table->enum('status', ['accumulating', 'ready', 'spent'])->default('accumulating');
            // Sub-paso 4: LFT — NO se descuenta sin autorización por escrito
            $table->boolean('authorized')->default(false);
            $table->timestamp('authorized_at')->nullable();
            $table->unsignedBigInteger('authorized_by')->nullable();
            $table->unsignedBigInteger('credential_id')->nullable(); // FK opcional al registro de credencial
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('colaborador_id')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->foreign('credential_id')
                  ->references('id')->on('talento_credentials')->onDelete('set null');
            $table->index(['colaborador_id', 'status'], 'tf_col_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_funds');
        Schema::dropIfExists('talento_credentials');
    }
};
