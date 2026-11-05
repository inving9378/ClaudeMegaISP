<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item #485: plantillas de documentos con rutas de disco «horneadas» (/var/www/MEGANET/public,
 * /var/www/ClaudeMegaISP/public) en vez de ${data.image_src}, que DocumentTemplateService resuelve
 * a public_path() del árbol activo. Aditiva e idempotente: respalda el HTML previo (una vez por
 * plantilla) en document_templates_html_backup_485 y solo toca filas que aún contengan esas rutas.
 * Las plantillas se localizan por contenido, nunca por id (dev ≠ prod).
 */
return new class extends Migration
{
    private const REEMPLAZOS = [
        // Rutas que ni siquiera existen en el árbol actual → el archivo real vive en public/images/
        '/var/www/MEGANET/public/logo_meganet_oficial.png' => '${data.image_src}/images/logo_meganet_oficial.png',
        '/var/www/ClaudeMegaISP/public/storage/logo_meganet/logo-meganet-oficial.png' => '${data.image_src}/images/logo_meganet_oficial.png',
        '/var/www/MEGANET/public' => '${data.image_src}',
        '/var/www/ClaudeMegaISP/public' => '${data.image_src}',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) {
            return;
        }

        if (! Schema::hasTable('document_templates_html_backup_485')) {
            Schema::create('document_templates_html_backup_485', function (Blueprint $table) {
                $table->unsignedBigInteger('template_id')->primary();
                $table->string('name')->nullable();
                $table->longText('html_previo');
                $table->timestamp('respaldado_at')->useCurrent();
            });
        }

        $filas = DB::table('document_templates')
            ->where(function ($q) {
                $q->where('html', 'like', '%/var/www/MEGANET/%')
                  ->orWhere('html', 'like', '%/var/www/ClaudeMegaISP/%');
            })
            ->get(['id', 'name', 'html']);

        foreach ($filas as $fila) {
            $nuevo = strtr($fila->html, self::REEMPLAZOS);
            if ($nuevo === $fila->html) {
                continue;
            }
            DB::table('document_templates_html_backup_485')->insertOrIgnore([
                'template_id' => $fila->id,
                'name' => $fila->name,
                'html_previo' => $fila->html,
            ]);
            DB::table('document_templates')->where('id', $fila->id)->update(['html' => $nuevo]);
        }
    }

    public function down(): void
    {
        // Restaura el HTML previo desde el respaldo; deja el respaldo en su lugar.
        if (! Schema::hasTable('document_templates_html_backup_485')) {
            return;
        }
        foreach (DB::table('document_templates_html_backup_485')->get() as $r) {
            DB::table('document_templates')->where('id', $r->template_id)->update(['html' => $r->html_previo]);
        }
    }
};
