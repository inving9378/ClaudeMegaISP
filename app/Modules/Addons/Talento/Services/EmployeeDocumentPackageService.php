<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\CompanyInformation;
use App\Modules\Addons\Flotas\Models\FleetAssignment;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocument;
use App\Modules\Addons\Talento\Models\TalentoPuestoDocumentTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Item roadmap #871 (Expediente RH — Hijo D2). Arma el paquete de documentos que le toca a un
 * colaborador segun su puesto (catalogo del Hijo D1, `talento_puesto_document_templates`) y los
 * renderiza con TemplateRenderService (Hijo B). Regla dura del item: nunca se capturan a mano
 * placas/vin/herramientas en un formulario propio de este servicio — siempre se leen en vivo de
 * Flotas/Inventario en el momento de renderizar.
 *
 * Los campos sin fuente de datos real en el sistema (p.ej. combustible_entrega/golpes_existentes
 * de una responsiva vehicular, que no se registran en ningun lado hoy) se dejan fuera de $data a
 * proposito: TemplateRenderService los marca .campo-faltante y el documento queda status=pendiente,
 * que es el comportamiento correcto (no inventar un valor).
 */
class EmployeeDocumentPackageService
{
    public function __construct(private TemplateRenderService $renderer)
    {
    }

    /**
     * @return TalentoEmployeeDocument[]
     */
    public function generateForColaborador(TalentoColaborador $colaborador): array
    {
        $puesto = $colaborador->job_title;
        if (!$puesto) {
            return [];
        }

        $templateIds = TalentoPuestoDocumentTemplate::where('puesto', $puesto)->pluck('template_id');
        if ($templateIds->isEmpty()) {
            return [];
        }

        $data = $this->buildData($colaborador);
        $documentos = [];

        foreach (TalentoDocumentTemplate::active()->with('currentVersion')->whereIn('id', $templateIds)->get() as $template) {
            $version = $template->currentVersion;
            if (!$version) {
                continue;
            }

            $html = $this->renderer->renderDocument($version->content, $data, $template->name);
            $status = str_contains($html, 'campo-faltante') ? 'pendiente' : 'completo';

            $documentos[] = TalentoEmployeeDocument::updateOrCreate(
                ['colaborador_id' => $colaborador->id, 'template_id' => $template->id],
                [
                    'template_version_id' => $version->id,
                    'rendered_html'       => $html,
                    'status'              => $status,
                    'generated_at'        => now(),
                ]
            );
        }

        return $documentos;
    }

    private function buildData(TalentoColaborador $colaborador): array
    {
        return [
            'empleado' => $this->empleadoData($colaborador),
            'empresa'  => $this->empresaData(),
            'fecha'    => ['emision' => now()->translatedFormat('d \d\e F \d\e Y')],
            'vehiculo' => $this->vehiculoData($colaborador),
            'herramientas' => $this->herramientasData($colaborador),
        ];
    }

    private function empleadoData(TalentoColaborador $colaborador): array
    {
        $user = $colaborador->user;
        $diasLabel = ['1' => 'Lun', '2' => 'Mar', '3' => 'Mié', '4' => 'Jue', '5' => 'Vie', '6' => 'Sáb', '7' => 'Dom'];
        $diasTrabajo = collect(explode(',', (string) $colaborador->work_days))
            ->filter()
            ->map(fn ($d) => $diasLabel[$d] ?? $d)
            ->implode(', ');

        return [
            'nombre_completo' => trim(($user->name ?? '') . ' ' . ($user->father_last_name ?? '') . ' ' . ($user->mother_last_name ?? '')),
            'puesto' => $colaborador->job_title,
            'curp' => $colaborador->curp,
            'nss' => $colaborador->nss,
            'rfc' => $user->rfc ?? null,
            'domicilio' => $user->address ?? null,
            'correo' => $user->email ?? null,
            'telefono' => $user->phone ?? null,
            'area' => $colaborador->department,
            'fecha_ingreso' => optional($colaborador->hire_date)->translatedFormat('d \d\e F \d\e Y'),
            'fecha_nacimiento' => optional($colaborador->birth_date)->translatedFormat('d \d\e F \d\e Y'),
            'jefe_inmediato' => optional(optional($colaborador->supervisor)->user)->name,
            'periodicidad_pago' => $colaborador->pay_frequency,
            'lugar_trabajo' => $colaborador->work_location,
            'horario' => ($colaborador->shift_start && $colaborador->shift_end)
                ? "{$colaborador->shift_start} a {$colaborador->shift_end}"
                : null,
            'dias_trabajo' => $diasTrabajo ?: null,
            'salario' => $colaborador->base_salary !== null ? number_format((float) $colaborador->base_salary, 2) : null,
            'contacto_emergencia_nombre' => $colaborador->emergency_contact_name,
            'contacto_emergencia_telefono' => $colaborador->emergency_contact_phone,
        ];
    }

    private function empresaData(): array
    {
        $company = CompanyInformation::first();

        return [
            'razon_social' => $company->company_name ?? null,
            'correo_contacto' => $company->email ?? null,
            'domicilio_datos_personales' => $company->data_privacy_address ?? null,
        ];
    }

    /**
     * SOLO si hay FleetAssignment activa (until IS NULL) para el user_id del colaborador.
     * combustible_entrega/golpes_existentes no tienen fuente de datos hoy (ninguna tabla los
     * registra) -> se omiten a proposito, quedan .campo-faltante.
     */
    private function vehiculoData(TalentoColaborador $colaborador): array
    {
        $asignacion = FleetAssignment::with('vehicle')
            ->where('user_id', $colaborador->user_id)
            ->whereNull('until')
            ->latest('since')
            ->first();

        $vehiculo = $asignacion?->vehicle;
        if (!$vehiculo) {
            return [];
        }

        return [
            'placas' => $vehiculo->plates,
            'marca' => $vehiculo->brand,
            'modelo' => $vehiculo->model,
            'anio' => $vehiculo->year,
            'color' => $vehiculo->color,
            'vin' => $vehiculo->vin,
            'kilometraje_entrega' => $vehiculo->current_km,
        ];
    }

    /**
     * Mismo scope de TalentoCustodiaController::show() (modelable_type=App\Models\User,
     * current_stock>0) pero con las columnas REALES de inventory_items/inventory_item_stocks
     * (name/serial_number/current_stock/condition) — el controller referenciaba i.sku/i.unit/
     * i.category_id, columnas que no existen en el esquema actual.
     */
    private function herramientasData(TalentoColaborador $colaborador): array
    {
        return DB::table('inventory_item_stocks as s')
            ->join('inventory_items as i', 'i.id', '=', 's.inventory_item_id')
            ->where('s.modelable_type', 'App\\Models\\User')
            ->where('s.modelable_id', $colaborador->user_id)
            ->whereNull('s.deleted_at')
            ->where('s.current_stock', '>', 0)
            ->select('i.name as descripcion', 'i.serial_number as no_serie', 's.current_stock as cantidad', 's.condition as estado')
            ->orderBy('i.name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
