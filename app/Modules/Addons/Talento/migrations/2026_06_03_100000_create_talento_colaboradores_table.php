<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_colaboradores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->enum('type', ['interno', 'externo'])->default('interno');
            $table->string('department', 100)->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->date('hire_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('base_salary', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('supervisor_id')->references('id')->on('talento_colaboradores')->onDelete('set null');
            $table->index('status');
            $table->index('type');
            $table->index('department');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_colaboradores');
    }
};
