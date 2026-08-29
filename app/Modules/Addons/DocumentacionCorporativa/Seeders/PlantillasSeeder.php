<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Seeders;

use App\Models\DocumentTemplate;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEmpresa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2d (item roadmap #737) — siembra las 4 plantillas de `document_templates`
 * que el catálogo (Fase 0, `CatalogoSeeder`) previó para los conceptos tipo
 * `plantilla`: organigrama corporativo, estructura accionaria, relación de
 * activos y pasivos, y carátula de expediente.
 *
 * IDEMPOTENTE: `updateOrCreate` por `name` — correrlo N veces no duplica nada,
 * sólo refresca el HTML si se editó aquí.
 *
 * `type` = id de `document_type_templates` (columna mal nombrada históricamente:
 * NO es un string libre, es una FK — ver `DocumentTemplate::type()`). Se usa el
 * tipo "Documentos" (id=2, sembrado desde 2024) porque ninguno de los 4 tipos
 * existentes (Clientes/Correos/Facturas/Cliente Potencial/Pagos/Pruebas) aplica
 * y este análisis ya concluyó no inventar uno nuevo sin necesidad real.
 *
 * SÓLO 2 de las 4 se enlazan a un concepto vía `dc_conceptos.plantilla_id`
 * (organigrama y relación de activos y pasivos, ambos `tipo_resolvedor` =
 * `plantilla` en el catálogo). "Estructura accionaria" es tipo `grafica` en el
 * catálogo (se resuelve por `FuenteRegistry`, no por plantilla — ver
 * `Fuentes\PropiaFuentes`) y "Carátula de expediente" no tiene concepto propio
 * hoy (probablemente para el paquete de entrega de Fase 5, item #667). Ambas
 * quedan en `document_templates` sin enlazar, listas para cuando se necesiten.
 */
class PlantillasSeeder extends Seeder
{
    private const TYPE_DOCUMENTOS = 2;

    public function run(): void
    {
        $creadoPor = (string) (auth()->id() ?? 1);

        $organigrama = DocumentTemplate::updateOrCreate(
            ['name' => 'Organigrama corporativo'],
            ['html' => $this->htmlOrganigrama(), 'type' => self::TYPE_DOCUMENTOS, 'created_by' => $creadoPor]
        );

        DocumentTemplate::updateOrCreate(
            ['name' => 'Estructura accionaria'],
            ['html' => $this->htmlEstructuraAccionaria(), 'type' => self::TYPE_DOCUMENTOS, 'created_by' => $creadoPor]
        );

        $activosPasivos = DocumentTemplate::updateOrCreate(
            ['name' => 'Relación de activos y pasivos'],
            ['html' => $this->htmlActivosPasivos(), 'type' => self::TYPE_DOCUMENTOS, 'created_by' => $creadoPor]
        );

        DocumentTemplate::updateOrCreate(
            ['name' => 'Carátula de expediente'],
            ['html' => $this->htmlCaratula(), 'type' => self::TYPE_DOCUMENTOS, 'created_by' => $creadoPor]
        );

        $this->enlazar($organigrama->id, 'organigrama-corporativo');
        $this->enlazar($activosPasivos->id, 'relacion-de-activos-y-pasivos');
    }

    /**
     * Enlaza la plantilla al concepto homónimo de TODAS las empresas activas —
     * el catálogo es multi-empresa, un solo UPDATE no basta (cada empresa tiene
     * su propia fila `dc_conceptos`, sembrada por `CatalogoSeeder`).
     */
    private function enlazar(int $plantillaId, string $slug): void
    {
        $empresaIds = DcEmpresa::activas()->pluck('id');

        DcConcepto::whereIn('empresa_id', $empresaIds)
            ->where('slug', $slug)
            ->update(['plantilla_id' => $plantillaId]);
    }

    private function htmlOrganigrama(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
.membrete { border-bottom: 2px solid #0057A8; padding-bottom: 8px; margin-bottom: 16px; }
.membrete img { max-height: 46px; }
h1 { font-size: 16px; color: #0057A8; margin: 4px 0 2px; }
.sub { color: #666; font-size: 10px; }
table { width: 100%; border-collapse: collapse; margin-top: 14px; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
th { background: #f2f2f2; font-weight: bold; width: 30%; }
.pie { margin-top: 22px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
</style>
</head>
<body>
<div class="membrete">
<img src="${data.url_logo}">
<h1>Organigrama corporativo</h1>
<div class="sub">${data.company_name} · RFC ${data.rfc}</div>
</div>

<table>
<tr><th>Nivel</th><td>Órgano / puesto</td></tr>
<tr><th>1</th><td>Asamblea de accionistas</td></tr>
<tr><th>2</th><td>Consejo de administración</td></tr>
<tr><th>3</th><td>Dirección general</td></tr>
<tr><th>4</th><td>Direcciones de área (operación, comercial, administración y finanzas, técnica)</td></tr>
<tr><th>5</th><td>Jefaturas y coordinaciones</td></tr>
<tr><th>6</th><td>Personal operativo</td></tr>
</table>

<div class="pie">
Este documento se generó desde la plantilla del expediente corporativo. Su contenido se mantiene
editando la plantilla "Organigrama corporativo" en Administración → Plantillas de documentos.
</div>
</body>
</html>
HTML;
    }

    private function htmlEstructuraAccionaria(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
.membrete { border-bottom: 2px solid #0057A8; padding-bottom: 8px; margin-bottom: 16px; }
.membrete img { max-height: 46px; }
h1 { font-size: 16px; color: #0057A8; margin: 4px 0 2px; }
.sub { color: #666; font-size: 10px; }
table { width: 100%; border-collapse: collapse; margin-top: 14px; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
th { background: #f2f2f2; font-weight: bold; }
.pie { margin-top: 22px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
</style>
</head>
<body>
<div class="membrete">
<img src="${data.url_logo}">
<h1>Estructura accionaria</h1>
<div class="sub">${data.company_name} · RFC ${data.rfc}</div>
</div>

<table>
<thead>
<tr><th>Accionista / razón social</th><th>Serie</th><th>Número de acciones</th><th>Porcentaje</th></tr>
</thead>
<tbody>
<tr><td colspan="4">La estructura accionaria vigente se consulta en vivo en el apartado I del
expediente corporativo (fuente <em>dc.estructura_accionaria</em>, libro de registro de acciones).
Esta plantilla queda disponible para un corte impreso puntual.</td></tr>
</tbody>
</table>

<div class="pie">
Este documento se generó desde la plantilla del expediente corporativo.
</div>
</body>
</html>
HTML;
    }

    private function htmlActivosPasivos(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
.membrete { border-bottom: 2px solid #0057A8; padding-bottom: 8px; margin-bottom: 16px; }
.membrete img { max-height: 46px; }
h1 { font-size: 16px; color: #0057A8; margin: 4px 0 2px; }
.sub { color: #666; font-size: 10px; }
h2 { font-size: 12px; margin: 16px 0 4px; color: #0057A8; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
th { background: #f2f2f2; font-weight: bold; }
.total td { font-weight: bold; background: #fafafa; }
.pie { margin-top: 22px; color: #888; font-size: 9px; border-top: 1px solid #ddd; padding-top: 6px; }
</style>
</head>
<body>
<div class="membrete">
<img src="${data.url_logo}">
<h1>Relación de activos y pasivos</h1>
<div class="sub">${data.company_name} · RFC ${data.rfc}</div>
</div>

<h2>Activos</h2>
<table>
<thead><tr><th>Concepto</th><th>Importe</th></tr></thead>
<tbody>
<tr><td>Activo circulante</td><td></td></tr>
<tr><td>Activo fijo</td><td></td></tr>
<tr><td>Otros activos</td><td></td></tr>
<tr class="total"><td>Total de activos</td><td></td></tr>
</tbody>
</table>

<h2>Pasivos</h2>
<table>
<thead><tr><th>Concepto</th><th>Importe</th></tr></thead>
<tbody>
<tr><td>Pasivo a corto plazo</td><td></td></tr>
<tr><td>Pasivo a largo plazo</td><td></td></tr>
<tr class="total"><td>Total de pasivos</td><td></td></tr>
</tbody>
</table>

<h2>Capital contable</h2>
<table>
<tbody>
<tr class="total"><td>Total de capital contable (activo − pasivo)</td><td></td></tr>
</tbody>
</table>

<div class="pie">
Documento base para el corte de activos y pasivos del apartado II del expediente corporativo.
Los importes se capturan a mano y se actualizan editando esta plantilla en Administración →
Plantillas de documentos antes de generar un nuevo corte.
</div>
</body>
</html>
HTML;
    }

    private function htmlCaratula(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; text-align: center; padding-top: 120px; }
img { max-height: 90px; margin-bottom: 24px; }
h1 { font-size: 22px; color: #0057A8; margin: 0 0 6px; }
.sub { font-size: 13px; color: #444; margin-bottom: 4px; }
.marco { border: 1px solid #ccc; padding: 30px; margin: 40px auto; width: 70%; }
.pie { position: fixed; bottom: 30px; left: 0; right: 0; color: #888; font-size: 9px; text-align: center; }
</style>
</head>
<body>
<img src="${data.url_logo}">
<h1>${data.company_name}</h1>
<div class="sub">RFC ${data.rfc}</div>

<div class="marco">
<div class="sub">EXPEDIENTE CORPORATIVO Y SOCIETARIO</div>
<div class="sub">Confidencial — uso interno</div>
</div>

<div class="pie">
Carátula de expediente generada desde la plantilla del módulo de Documentación Corporativa.
</div>
</body>
</html>
HTML;
    }
}
