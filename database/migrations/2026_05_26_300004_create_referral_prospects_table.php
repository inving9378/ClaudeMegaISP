<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('embajador_id');
            $table->string('name', 100);
            $table->string('phone', 20);
            $table->string('email', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->enum('source', ['shared_link', 'manual', 'contact_import', 'bulk_send'])->default('manual');
            $table->enum('status', ['new', 'contacted', 'interested', 'quoted', 'converted', 'lost'])->default('new');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('converted_client_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();

            $table->foreign('embajador_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('converted_client_id')->references('id')->on('clients')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_prospects');
    }
};
