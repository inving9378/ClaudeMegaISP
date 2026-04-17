<?php

use App\Models\DocumentTypeTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_type_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $nameTemplateTypes = [
            'Clientes',
            'Documentos',
            'Correos',
            'Facturas'
        ];

        foreach ($nameTemplateTypes as $value) {
            DocumentTypeTemplate::create([
                'name' => $value
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_type_templates');
    }
};
