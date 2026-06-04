<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) return;

        $configs = [
            ['talento_survey_autoclose_days', '7',  'Días sin respuesta para auto-cierre de encuesta de instalación'],
            ['talento_survey_reminder_days',  '3',  'Días sin respuesta para enviar recordatorio de encuesta'],
            ['talento_media_flagged_radius_m','500', 'Radio (m) fuera del cual la evidencia fotográfica queda flagged'],
            ['talento_doc_encryption_key',   '',    'Clave adicional para cifrado de documentos sensibles (vacío = usa APP_KEY)'],
        ];

        $now = now();
        foreach ($configs as [$key, $value, $description]) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'description' => $description, 'updated_at' => $now]
            );
        }
    }

    public function down(): void {}
};
