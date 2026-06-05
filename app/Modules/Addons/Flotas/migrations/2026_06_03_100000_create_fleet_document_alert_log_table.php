<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_document_alert_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                  ->constrained('fleet_documents')
                  ->cascadeOnDelete();
            $table->smallInteger('threshold');   // 30, 7, 1, 0
            $table->smallInteger('days_until');  // días reales al momento del envío
            $table->enum('channel', ['email', 'whatsapp']);
            $table->string('recipient')->nullable();
            $table->enum('status', ['sent', 'failed', 'skipped']);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Cada umbral + canal dispara a lo más una vez por documento.
            $table->unique(['document_id', 'threshold', 'channel'], 'uniq_doc_threshold_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_document_alert_log');
    }
};
