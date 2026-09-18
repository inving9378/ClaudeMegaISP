<?php

use App\Models\DocumentTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * Corrige el contenido de la plantilla "Recibo de Pago Meganet" (item roadmap #9991219,
 * hallazgo al probar las 20 plantillas tras el fix de "Generar Contrato"): su HTML era una
 * copia sin adaptar de un sistema DISTINTO (marcadores `{{ App.formatMoney(...) }}` /
 * `{{ App.t(...) }}`, sintaxis de Splynx) que este sistema nunca supo interpretar — el motor
 * real de sustitución de este sistema usa `${data.xxx}` (DocumentTemplateService::
 * validateAndReplaceTemplate(), regex `/\${(.*?)}/`). Además tenía un `<table>` sin su
 * `</table>` correspondiente (12 aperturas / 13 cierres), lo que hacía tronar a dompdf
 * (`Call to a member function get_cellmap() on null`) en CUALQUIER intento de generar o
 * previsualizar. Confirmado con `DocumentClient::where('title','like','%Recibo de Pago%')`
 * = 0 filas: nunca se generó un documento real con ella — no hay nada que migrar de datos
 * ya emitidos, es contenido que nunca funcionó.
 *
 * Nuevo contenido: reescrito en el formato real del sistema (mismos `${data.xxx}` que usa
 * "CONTRATO 18 MESES CRT PRO", que sí funciona), reusando el bloque compartido
 * `${data.table_client_pending_payments}` (ya usado en otras plantillas, vista
 * resources/views/meganet/module/client/template/html_table_pending_payments.blade.php) para
 * la tabla de pagos pendientes — no se inventa lógica nueva, solo se conecta a la ya existente.
 * Verificado end-to-end (preview y generar, ambos ya idénticos tras el fix de #9991219) contra
 * 3 clientes reales de dev, 1 sola página, sin errores.
 *
 * Resuelve por `name` (no por id — los ids de `document_templates` NO coinciden entre
 * entornos, ver CLAUDE.md). Aditiva/idempotente: si el row no existe (no debería faltar, pero
 * por si acaso en un entorno distinto), no hace nada en vez de fallar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html lang="es">
            <head>
            <meta charset="UTF-8">
            <title>Recibo de Pago</title>
            <style>
              body { font-family: Arial, sans-serif; font-size: 12px; color: #222; margin: 20px; }
              .header { display: table; width: 100%; margin-bottom: 20px; }
              .header .logo { display: table-cell; width: 40%; vertical-align: middle; }
              .header .logo img { max-width: 150px; }
              .header .empresa { display: table-cell; width: 60%; text-align: right; vertical-align: middle; font-size: 11px; line-height: 1.5; }
              h1 { text-align: center; font-size: 18px; margin: 10px 0 20px; text-transform: uppercase; border-bottom: 2px solid #000; padding-bottom: 8px; }
              table.datos-cliente { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
              table.datos-cliente td { padding: 4px 6px; font-size: 11px; }
              table.datos-cliente td.label { font-weight: bold; width: 130px; }
              .saldo { border: 2px solid #000; padding: 10px; text-align: center; margin: 15px 0; font-size: 14px; font-weight: bold; }
              table.table { width: 100%; border-collapse: collapse; margin-top: 10px; }
              table.table, table.table th, table.table td { border: 1px solid #999; }
              table.table th, table.table td { padding: 6px 8px; text-align: left; font-size: 10px; }
              table.table th { background-color: #eee; }
              .footer { margin-top: 30px; font-size: 10px; color: #555; text-align: center; }
            </style>
            </head>
            <body>

            <div class="header">
              <div class="logo"><img src="${data.url_logo}" alt="Logo"></div>
              <div class="empresa">
                <strong>${data.company_name}</strong><br>
                ${data.company_street} ${data.company_external_number}<br>
                RFC: ${data.rfc}<br>
                Tel. atención a clientes: ${data.atention_client_phone}<br>
                ${data.email}
              </div>
            </div>

            <h1>Recibo de Pago</h1>

            <table class="datos-cliente">
              <tr>
                <td class="label">Cliente:</td>
                <td>${data.full_name}</td>
                <td class="label">Fecha:</td>
                <td>${data.now}</td>
              </tr>
              <tr>
                <td class="label">Dirección:</td>
                <td>${data.street} ${data.external_number}, ${data.colony}, ${data.municipality}, ${data.state}, C.P. ${data.zip}</td>
                <td class="label">Teléfono:</td>
                <td>${data.phone}</td>
              </tr>
              <tr>
                <td class="label">Fecha de pago:</td>
                <td>${data.fecha_pago}</td>
                <td class="label">Fecha de corte:</td>
                <td>${data.fecha_corte}</td>
              </tr>
            </table>

            <div class="saldo">Saldo de la cuenta: $${data.amount}</div>

            ${data.table_client_pending_payments}

            <div class="footer">
              ${data.company_name} · Depósitos y transferencias a nombre de ${data.company_name} · Banco ${data.bank_name}, cuenta ${data.bank_account}<br>
              Este documento es un comprobante informativo, no tiene validez fiscal.
            </div>

            </body>
            </html>
            HTML;

        DocumentTemplate::where('name', 'Recibo de Pago Meganet')->update(['html' => $html]);
    }

    /**
     * Sin rollback de contenido a propósito: el HTML anterior nunca funcionó (0 documentos
     * generados jamás con esta plantilla) — no hay nada real que "restaurar". Un down() vacío
     * es más seguro que reintroducir contenido roto.
     */
    public function down(): void
    {
    }
};
