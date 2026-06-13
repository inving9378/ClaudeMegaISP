<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\DocumentTemplate;

/**
 * Repara la corrupción de saltos de línea en TODAS las plantillas de documento.
 *
 * Causa raíz: SmartImportService::parseSqlTuple() tomaba el carácter literal tras
 * el backslash, convirtiendo las secuencias de escape MySQL \r → r y \n → n.
 * Resultado: cada salto de línea (CRLF) quedó almacenado como las letras 'rn'.
 *
 * Fix: reemplaza 'rn' → "\n" SALVO que esté flanqueado por letras en ambos lados
 * (eso preserva 'rn' genuino dentro de palabras como Paterno, external, internet).
 *
 * Idempotente: se omite si la plantilla ya tiene saltos de línea reales.
 */
return new class extends Migration
{
    private const LETTERS = 'a-zA-ZáéíóúüñÁÉÍÓÚÜÑ';

    public function up(): void
    {
        $templates = DocumentTemplate::withTrashed()->get(['id', 'html']);

        foreach ($templates as $t) {
            // Si ya tiene saltos reales, fue creada/editada manualmente → omitir
            if (str_contains($t->html, "\n") || str_contains($t->html, "\r")) {
                continue;
            }
            // Sin 'rn' en posición no-letra → no fue importada con la corrupción → omitir
            if (!preg_match('/(?<![a-zA-ZáéíóúüñÁÉÍÓÚÜÑ])rn/', $t->html)) {
                continue;
            }

            $html = $this->fixRn($t->html);

            // Actualizar solo si hubo cambio
            if ($html !== $t->html) {
                DocumentTemplate::withTrashed()
                    ->where('id', $t->id)
                    ->update(['html' => $html]);
            }
        }
    }

    public function down(): void
    {
        // No reversible: restaurar los backups o reimportar desde producción.
    }

    private function fixRn(string $html): string
    {
        $L = self::LETTERS;

        // Reemplaza 'rn' → "\n" a menos que esté entre letras en AMBOS lados.
        // Dos pasadas cubren cadenas consecutivas tipo 'rnrn' donde la segunda
        // tiene 'n' (letra) antes de ella tras la primera pasada en el string original.
        for ($pass = 0; $pass < 3; $pass++) {
            $prev = $html;
            // Alt 1: rn no precedido por letra
            // Alt 2: rn no seguido por letra
            $html = preg_replace("/(?<![$L])rn|rn(?![$L])/", "\n", $html);
            if ($html === $prev) {
                break;
            }
        }

        return $html;
    }
};
