<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990792 — Expediente digital del colaborador. Clasifica cada plantilla de
 * `talento_document_templates` en uno de tres tipos con tratamiento distinto (ver prompt del
 * item): `firma` (bloquea operar hasta firmarse), `acuse` (solo notifica) y `estudio` (manuales/
 * cursos, item de Academia #9B, sin filas hoy). Decisión q3 de Irving (opción recomendada):
 * mapeo automático por nombre + fallback a `acuse` (el tratamiento menos disruptivo) para lo que
 * no matchea ningún patrón — así "ninguna plantilla queda sin tipo" sin bloquear a nadie por una
 * clasificación ambigua. Aditiva: columna nueva con default, ningún lector existente la usa aún.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('talento_document_templates', 'tipo')) {
            Schema::table('talento_document_templates', function (Blueprint $table) {
                $table->enum('tipo', ['firma', 'acuse', 'estudio'])->default('acuse')->after('category');
            });
        }

        // Backfill determinista de las plantillas ya sembradas (idempotente: solo firma
        // necesita escribirse, `acuse` ya es el default de la columna).
        $firmaKeywords = ['contrato', 'convenio', 'confidencialidad', 'responsiva', 'resguardo'];

        $rows = DB::table('talento_document_templates')->select('id', 'name')->get();
        foreach ($rows as $row) {
            $nombre = mb_strtolower((string) $row->name);
            $esFirma = false;
            foreach ($firmaKeywords as $kw) {
                if (str_contains($nombre, $kw)) {
                    $esFirma = true;
                    break;
                }
            }
            // Caso "Entrega y Recepción de Herramientas y Equipo": sin las palabras de arriba,
            // pero es resguardo de equipo por naturaleza (item lo cita textualmente en su tabla).
            if (!$esFirma && str_contains($nombre, 'entrega y recepci') && (str_contains($nombre, 'equipo') || str_contains($nombre, 'herramienta'))) {
                $esFirma = true;
            }

            if ($esFirma) {
                DB::table('talento_document_templates')->where('id', $row->id)->update(['tipo' => 'firma']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('talento_document_templates', function (Blueprint $table) {
            if (Schema::hasColumn('talento_document_templates', 'tipo')) {
                $table->dropColumn('tipo');
            }
        });
    }
};
