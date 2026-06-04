<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-paso 1: Evidencia fotográfica
        Schema::create('talento_work_order_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->enum('type', [
                'presentation', 'completion',
                'ine_front', 'ine_back', 'proof_address',
                'modem_sn', 'other',
            ]);
            $table->string('file_path', 500);           // encrypted for ine_front/ine_back/proof_address
            $table->decimal('captured_lat', 10, 7)->nullable();
            $table->decimal('captured_lng', 10, 7)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->boolean('captured_in_app')->default(true);
            $table->boolean('watermark_applied')->default(false);
            $table->boolean('location_flagged')->default(false);  // true if captured far from order
            $table->decimal('location_distance_m', 8, 1)->nullable(); // distance from order coords
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
            $table->index(['work_order_id', 'type'], 'twom_wo_type_idx');
        });

        // Sub-paso 2: Resultados de validación IA
        Schema::create('talento_work_order_ia_validations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->string('validation_type', 60);  // ine_ocr, address_ocr, modem_sn_match, general
            $table->json('flags');                   // [{field, issue, severity: warning|error}]
            $table->json('raw_response')->nullable();
            $table->boolean('overridden')->default(false);
            $table->string('override_reason', 255)->nullable();
            $table->unsignedBigInteger('overridden_by')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
            $table->index(['work_order_id'], 'twoiv_wo_idx');
        });

        // Sub-paso 3: Firmas
        Schema::create('talento_work_order_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->enum('signer_type', ['technician', 'client']);
            $table->string('signature_path', 500);   // SVG/PNG base64 stored on disk
            $table->decimal('signed_lat', 10, 7)->nullable();
            $table->decimal('signed_lng', 10, 7)->nullable();
            $table->timestamp('signed_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
            $table->unique(['work_order_id', 'signer_type'], 'twos_wo_signer_unique');
        });

        // Sub-paso 5: Activaciones
        Schema::create('talento_work_order_activations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('activated_by')->nullable();  // user_id
            $table->timestamp('requested_at');
            $table->timestamp('activated_at')->nullable();
            $table->boolean('olt_dispatched')->default(false);
            $table->json('olt_response')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
        });

        // Sub-paso 6: Encuesta de onboarding
        Schema::create('talento_installation_surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_order_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedTinyInteger('rating_overall')->nullable();     // 1-5
            $table->unsignedTinyInteger('rating_technician')->nullable();  // 1-5
            $table->text('comments')->nullable();
            $table->boolean('google_review_offered')->default(false);
            $table->boolean('google_review_opened')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->boolean('auto_closed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('work_order_id')->references('id')->on('talento_work_orders')->onDelete('cascade');
            $table->index(['client_id'], 'tis_client_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_installation_surveys');
        Schema::dropIfExists('talento_work_order_activations');
        Schema::dropIfExists('talento_work_order_signatures');
        Schema::dropIfExists('talento_work_order_ia_validations');
        Schema::dropIfExists('talento_work_order_media');
    }
};
