<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\DocumentTemplate;

/**
 * Reduce secuencias de doble <br> a un solo <br> en la plantilla ID 6.
 *
 * Con la fuente subida a 10px los <br><br> generaban demasiado espacio vertical
 * en: sección AUTORIZACIÓN (firmas), bloque SUSCRIPTOR, PAGARÉ y Página 5.
 *
 * Solo toca pares/grupos de <br> consecutivos (con whitespace intermedio).
 * Los <br> simples entre </table> y <table> o dentro de celdas no se alteran
 * porque el regex requiere que haya al menos dos <br> seguidos.
 *
 * Idempotente: omite la actualización si ya no hay dobles <br>.
 */
return new class extends Migration
{
    public function up(): void
    {
        $template = DocumentTemplate::find(6);
        if (!$template) {
            return;
        }

        // Idempotencia: si no hay ningún par de <br> consecutivos, ya está corregido
        if (!preg_match('/<br\s*\/?>\s*<br\s*\/?>/', $template->html)) {
            return;
        }

        // Colapsar cualquier secuencia de 2+ <br> (con whitespace intermedio) a uno solo
        $fixed = preg_replace('/<br\s*\/?>(\s*<br\s*\/?>)+/', '<br>', $template->html);

        if ($fixed === $template->html) {
            return;
        }

        DocumentTemplate::where('id', 6)->update(['html' => $fixed]);
    }

    public function down(): void
    {
        // No reversible sin backup del HTML original.
    }
};
