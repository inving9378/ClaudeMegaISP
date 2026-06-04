<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_fiscal_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->unique();
            $table->boolean('facturacion_fiscal')->default(false);

            // Datos fiscales (SAT / CFDI 4.0)
            $table->string('razon_social', 250)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('codigo_postal_fiscal', 5)->nullable();
            // Catálogo SAT c_RegimenFiscal — almacenamos la clave (ej: "601")
            $table->string('regimen_fiscal', 4)->nullable();
            // Catálogo SAT c_UsoCFDI — almacenamos la clave (ej: "G03")
            $table->string('uso_cfdi', 4)->nullable();
            $table->string('correo_fiscal', 200)->nullable();
            // Ruta al PDF de constancia de situación fiscal (Storage local)
            $table->string('constancia_path', 500)->nullable();

            $table->unsignedBigInteger('created_by')->default(0);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->index('rfc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_fiscal_data');
    }
};
