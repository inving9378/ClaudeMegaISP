<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\DocumentTemplate;

/**
 * Corrige la plantilla ID 6 "CONTRATO 18 MESES CRT PRO":
 *
 * 1. Rutas de imágenes: /var/www/MEGANET/ → variables dinámicas del motor de plantillas.
 *    - Logo     → ${data.url_logo}  (resuelto a public_path por DocumentTemplateService)
 *    - QR       → ${data.image_src}/images/qr_meganet.jpg
 *    - Firma    → ${data.image_src}/images/firma_meganet.jpg
 *
 * 2. Variable de tarifa: "$ customer . mrr_total" (texto literal, nunca se interpolaba)
 *    → ${data.cost_all_services}
 *
 * 3. Tamaño de fuente conservador para llenar mejor la hoja sin agregar páginas:
 *    - Cuerpo/tablas: 9 px → 10 px
 *    - .tj (cláusulas 2 columnas, cuello de botella): 8 px → 9 px
 *
 * Idempotente: se aplica solo si la ruta incorrecta todavía existe en el HTML.
 */
return new class extends Migration
{
    public function up(): void
    {
        $template = DocumentTemplate::find(6);
        if (!$template) {
            return;
        }

        // Guardar sólo si hay algo que corregir (idempotencia)
        if (!str_contains($template->html, '/var/www/MEGANET/') && !str_contains($template->html, '$ customer . mrr_total')) {
            return;
        }

        $html = $template->html;

        // ── 1. Logo ──────────────────────────────────────────────────────────
        $html = str_replace(
            '/var/www/MEGANET/public/images/logo_meganet_oficial.png',
            '${data.url_logo}',
            $html
        );

        // ── 2. QR ─────────────────────────────────────────────────────────────
        $html = str_replace(
            '/var/www/MEGANET/public/images/qr_meganet.jpg',
            '${data.image_src}/images/qr_meganet.jpg',
            $html
        );

        // ── 3. Firma ─────────────────────────────────────────────────────────
        $html = str_replace(
            '/var/www/MEGANET/public/images/firma_meganet.jpg',
            '${data.image_src}/images/firma_meganet.jpg',
            $html
        );

        // ── 4. Variable de tarifa ─────────────────────────────────────────────
        $html = str_replace(
            '$ customer . mrr_total',
            '${data.cost_all_services}',
            $html
        );

        // ── 5. Fuentes ────────────────────────────────────────────────────────
        // Cuerpo / tablas: 9 px → 10 px
        $html = str_replace('font-size: 9px;', 'font-size: 10px;', $html);
        $html = str_replace('font-size: 9px !important;', 'font-size: 10px !important;', $html);
        // .tj (cláusulas 2 columnas): 8 px → 9 px
        $html = str_replace('font-size: 8px !important;', 'font-size: 9px !important;', $html);

        $template->html = $html;
        $template->save();
    }

    public function down(): void
    {
        // Reversión: restaurar los valores originales
        $template = DocumentTemplate::find(6);
        if (!$template) {
            return;
        }

        $html = $template->html;

        $html = str_replace('${data.url_logo}',                          '/var/www/MEGANET/public/images/logo_meganet_oficial.png', $html);
        $html = str_replace('${data.image_src}/images/qr_meganet.jpg',   '/var/www/MEGANET/public/images/qr_meganet.jpg',           $html);
        $html = str_replace('${data.image_src}/images/firma_meganet.jpg', '/var/www/MEGANET/public/images/firma_meganet.jpg',        $html);
        $html = str_replace('${data.cost_all_services}',                  '$ customer . mrr_total',                                 $html);
        $html = str_replace('font-size: 10px;',        'font-size: 9px;',             $html);
        $html = str_replace('font-size: 10px !important;', 'font-size: 9px !important;', $html);
        // .tj: aquí 9px !important ahora cubre tanto el body como el .tj;
        // down() es best-effort (el .tj quedará en 9px en vez de 8px)

        $template->html = $html;
        $template->save();
    }
};
