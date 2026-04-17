<?php

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplates;
use App\Services\DocumentTemplateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templatePath = resource_path('views/meganet/plantillas/Contrato-Fijo-Revalidacion-12-Meses-recurrente.blade.php');

        // Verificar si el archivo existe
        if (File::exists($templatePath)) {
            // Leer el contenido del archivo
            $templateContent = file_get_contents($templatePath);
            // Crear el nuevo registro con el contenido de la plantilla
            DocumentTemplate::create([
                'name' => 'Contrato-Fijo-Revalidacion-12-Meses-recurrente',
                'html' => $templateContent,
                'type' => 'contract',
                'created_by' => 1
            ]);

            $filePath = 'document_template/document/Contrato-Fijo-Revalidacion-12-Meses-recurrente.pdf';
            $documentTemplateService = new DocumentTemplateService();
            $documentTemplateService->saveDocumentTemplate($filePath, $templateContent);

        } else {
            // Manejar el error si el archivo no existe
            throw new Exception("La plantilla no existe en la ruta especificada: " . $templatePath);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DocumentTemplate::where('name', 'Contrato-Fijo-Revalidacion-12-Meses-recurrente')->delete();
    }
};
