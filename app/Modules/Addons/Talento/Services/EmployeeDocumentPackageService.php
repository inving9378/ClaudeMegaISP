<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\CompanyInformation;
use App\Modules\Addons\Flotas\Models\FleetAssignment;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoDocumentTemplate;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocument;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocumentSignature;
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
 *
 * Auditoria item #9990645 (placeholders de las plantillas vs. este $data): las listas de #each
 * `activos_tecnicos` (plantilla "responsiva equipo tecnico") y `epp` (equipo de proteccion
 * personal) tampoco tienen key aqui, IGUAL que combustible_entrega arriba -- no existe fuente:
 * inventory_items/inventory_item_stocks no tienen columnas marca/modelo/serie_imei/accesorios/
 * valor_ref (activos_tecnicos) ni talla_modelo/fecha_entrega (epp). Mapear con datos de
 * `herramientas` (custodia generica) inventaria informacion que no es real. Se dejan sin key a
 * proposito (el #each ya degrada a "0 filas" sin ruido cuando la key no existe -- mismo efecto
 * visual que un arreglo vacio). Requiere columnas nuevas de inventario + captura, fuera de
 * alcance de este item.
 */
class EmployeeDocumentPackageService
{
    /**
     * Item #9990661 — mapa inverso whitelisteado ruta->columna real. NUNCA se escribe una
     * columna fuera de estas 3 listas; cualquier otra ruta (incluida cualquier empleado. o
     * empresa. no listada aquí) cae al catch-all de datos_extra en completar()/destinoForRuta().
     */
    private const WHITELIST_COLABORADOR = [
        'empleado.curp' => 'curp',
        'empleado.nss' => 'nss',
        'empleado.area' => 'department',
        'empleado.salario' => 'base_salary',
        'empleado.periodicidad_pago' => 'pay_frequency',
        'empleado.lugar_trabajo' => 'work_location',
        'empleado.contacto_emergencia_nombre' => 'emergency_contact_name',
        'empleado.contacto_emergencia_telefono' => 'emergency_contact_phone',
        'empleado.fecha_ingreso' => 'hire_date',
        'empleado.fecha_nacimiento' => 'birth_date',
    ];

    private const WHITELIST_USER = [
        'empleado.rfc' => 'rfc',
        'empleado.domicilio' => 'address',
        'empleado.correo' => 'email',
        'empleado.telefono' => 'phone',
    ];

    private const WHITELIST_EMPRESA = [
        'empresa.razon_social' => 'company_name',
        'empresa.correo_contacto' => 'email',
        'empresa.domicilio_datos_personales' => 'data_privacy_address',
    ];

    /** Caso especial: 1 ruta -> 2 columnas (shift_start/shift_end), valor {inicio,fin} HH:MM. */
    private const RUTA_HORARIO = 'empleado.horario';

    /**
     * Derivados/no-settables (decisión explícita del item #9990661): existen en el render pero
     * NUNCA se aceptan como destino de escritura (ni siquiera al catch-all de datos_extra) —
     * divergirían silenciosamente de su fuente real (nombre del user, puesto del colaborador,
     * relación de supervisor) si alguien los sobreescribiera por documento.
     */
    private const NO_EDITABLE = ['empleado.nombre_completo', 'empleado.puesto', 'empleado.jefe_inmediato'];

    private const LABELS = [
        'empleado.curp' => 'CURP',
        'empleado.nss' => 'NSS',
        'empleado.rfc' => 'RFC',
        'empleado.domicilio' => 'Domicilio',
        'empleado.correo' => 'Correo electrónico',
        'empleado.telefono' => 'Teléfono',
        'empleado.area' => 'Área / Departamento',
        'empleado.salario' => 'Salario',
        'empleado.periodicidad_pago' => 'Periodicidad de pago',
        'empleado.lugar_trabajo' => 'Lugar de trabajo',
        'empleado.horario' => 'Horario (entrada y salida)',
        'empleado.dias_trabajo' => 'Días de trabajo',
        'empleado.contacto_emergencia_nombre' => 'Contacto de emergencia — nombre',
        'empleado.contacto_emergencia_telefono' => 'Contacto de emergencia — teléfono',
        'empleado.fecha_ingreso' => 'Fecha de ingreso',
        'empleado.fecha_nacimiento' => 'Fecha de nacimiento',
        'empleado.nombre_completo' => 'Nombre completo (no editable aquí)',
        'empleado.puesto' => 'Puesto (no editable aquí)',
        'empleado.jefe_inmediato' => 'Jefe inmediato (no editable aquí)',
        'empresa.razon_social' => 'Razón social',
        'empresa.correo_contacto' => 'Correo de contacto',
        'empresa.domicilio_datos_personales' => 'Domicilio (aviso de privacidad)',
        'fecha.ciudad_firma' => 'Ciudad de firma',
        'fecha.firma' => 'Fecha de firma',
        'fecha.emision' => 'Fecha de emisión',
    ];

    public function __construct(private TemplateRenderService $renderer)
    {
    }

    /**
     * $mostrarFaltantes=true es el modo admin (item #9990645): muestra "[FALTA: ruta]" visible
     * en vez de la línea en blanco del documento entregable. Nadie lo usa todavía (no hay
     * pantalla de vista previa admin) — queda disponible para cuando exista ese consumidor.
     *
     * @return TalentoEmployeeDocument[]
     */
    public function generateForColaborador(TalentoColaborador $colaborador, bool $mostrarFaltantes = false): array
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

        foreach (TalentoDocumentTemplate::active()->with(['currentVersion', 'signatureSlots'])->whereIn('id', $templateIds)->get() as $template) {
            $version = $template->currentVersion;
            if (!$version) {
                continue;
            }

            // Item #9990647: preserva los datos_extra ya capturados por "Completar documento"
            // (comisión mixta, lugar y fecha...) al regenerar en lote — updateOrCreate de abajo
            // no toca esa columna, pero el render sí necesita leerla para no perder lo llenado.
            $existente = TalentoEmployeeDocument::where('colaborador_id', $colaborador->id)
                ->where('template_id', $template->id)
                ->first();

            $dataDelDocumento = $this->overlayDatosExtra($data, $existente->datos_extra ?? []);

            if ($template->signatureSlots->isNotEmpty()) {
                // La firma es por documento (employee_document_id + slot_key), no por
                // colaborador: un documento nuevo (sin id todavía) no puede tener firmas —
                // data_get() en el renderer las deja en blanco, comportamiento correcto
                // (item #9990654).
                $dataDelDocumento['firma'] = $this->firmaData($template, $existente?->id);
            }

            $html = $this->renderer->renderDocument($version->content, $dataDelDocumento, $template->name, $mostrarFaltantes);
            // La clase 'campo-faltante' se sigue agregando en AMBOS modos (item #9990645) —
            // el estado pendiente/completo no depende de si el marcador se ve o no.
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

    /**
     * 'firma' => [slot_key => ['signature_path' => ...|null]] SOLO para templates con slots
     * declarados (retrocompat: un template sin filas en signatureSlots() no recibe key 'firma'
     * en $data en absoluto — ver generateForColaborador()).
     */
    private function firmaData(TalentoDocumentTemplate $template, ?int $documentoId): array
    {
        $firma = [];

        foreach ($template->signatureSlots as $slot) {
            $signature = $documentoId
                ? TalentoEmployeeDocumentSignature::where('employee_document_id', $documentoId)
                    ->where('slot_key', $slot->key)
                    ->first()
                : null;

            $firma[$slot->key] = [
                'signature_path' => $signature?->signature_path,
            ];
        }

        return $firma;
    }

    /**
     * Item #9990661 — re-scope gap-driven de #9990647/#9990651: "completar" ya no está limitado
     * al catálogo doc.* fijo, ruteamos por el MAPA INVERSO whitelisteado (arriba) a su lugar
     * real: colaborador/user (dato compartido del empleado), CompanyInformation (dato compartido
     * de TODA la empresa) o datos_extra del propio documento (todo lo demás). Ignora en silencio
     * cualquier ruta no reconocida o marcada NO_EDITABLE — nunca escribe una columna fuera de la
     * whitelist. TRANSACCIÓN: si se tocó un dato compartido (empleado/empresa) regenera TODO el
     * paquete del colaborador (para que el hueco desaparezca también en los demás documentos);
     * si solo se tocó datos_extra, regenera únicamente este documento.
     *
     * @return array{documento: TalentoEmployeeDocument, afecta_a_todos: bool, huecos: array}
     */
    public function completar(TalentoEmployeeDocument $documento, array $campos): array
    {
        $documento->loadMissing(['colaborador.user', 'template']);
        $colaborador = $documento->colaborador;
        abort_if(!$colaborador, 422, 'El documento no tiene colaborador asociado.');

        $colaboradorCambios = [];
        $userCambios = [];
        $empresaCambios = [];
        $datosExtra = $documento->datos_extra ?? [];

        foreach ($campos as $ruta => $valor) {
            if (!is_string($ruta) || in_array($ruta, self::NO_EDITABLE, true)) {
                continue;
            }

            if ($ruta === self::RUTA_HORARIO) {
                $inicio = is_array($valor) ? ($valor['inicio'] ?? null) : null;
                $fin = is_array($valor) ? ($valor['fin'] ?? null) : null;
                if ($this->esHoraValida($inicio) && $this->esHoraValida($fin)) {
                    $colaboradorCambios['shift_start'] = $inicio;
                    $colaboradorCambios['shift_end'] = $fin;
                }
                continue;
            }

            if (isset(self::WHITELIST_COLABORADOR[$ruta])) {
                $columna = self::WHITELIST_COLABORADOR[$ruta];
                $normalizado = $this->normalizarValorColaborador($columna, $valor);
                if ($normalizado !== null) {
                    $colaboradorCambios[$columna] = $normalizado;
                }
                continue;
            }

            if (isset(self::WHITELIST_USER[$ruta])) {
                if ($colaborador->user) {
                    $userCambios[self::WHITELIST_USER[$ruta]] = is_string($valor) ? trim($valor) : $valor;
                }
                continue;
            }

            if (isset(self::WHITELIST_EMPRESA[$ruta])) {
                $empresaCambios[self::WHITELIST_EMPRESA[$ruta]] = is_string($valor) ? trim($valor) : $valor;
                continue;
            }

            // Catch-all (fecha.*, doc.*, vehiculo.*, herramientas.*, cualquier ruta sin
            // columna): doc.* conserva su clave corta (retrocompat con el catálogo group=doc
            // ya en uso); el resto guarda la ruta completa para que overlayDatosExtra() la
            // pueda deep-merge de vuelta al nivel superior de $data en el próximo render.
            $clave = str_starts_with($ruta, 'doc.') ? substr($ruta, 4) : $ruta;
            $datosExtra[$clave] = is_string($valor) ? trim($valor) : $valor;
        }

        $tocoEmpleado = (bool) ($colaboradorCambios || $userCambios);
        $tocoEmpresa = (bool) $empresaCambios;

        DB::transaction(function () use ($colaborador, $colaboradorCambios, $userCambios, $empresaCambios, $documento, $datosExtra, $tocoEmpleado, $tocoEmpresa) {
            if ($colaboradorCambios) {
                $colaborador->update($colaboradorCambios);
            }
            if ($userCambios && $colaborador->user) {
                $colaborador->user->update($userCambios);
            }
            if ($empresaCambios) {
                CompanyInformation::first()?->update($empresaCambios);
            }

            $documento->datos_extra = $datosExtra;
            $documento->save();

            if ($tocoEmpleado || $tocoEmpresa) {
                $this->generateForColaborador($colaborador);
            } else {
                $this->regenerateOne($documento);
            }
        });

        $documentoFresco = $documento->fresh(['template']);

        return [
            'documento' => $documentoFresco,
            'afecta_a_todos' => $tocoEmpleado || $tocoEmpresa,
            'huecos' => $this->missingFields($documentoFresco),
        ];
    }

    /**
     * Item #9990661 — huecos reales de un documento YA renderizado: parsea data-campo="ruta" de
     * cada <span class="campo-faltante"> del rendered_html guardado (TemplateRenderService lo
     * escribe en AMBOS modos mostrarFaltantes, así que no hace falta re-renderizar — ver
     * TemplateRenderService::missingMarker). Los slots de firma pendiente (clase
     * "firma-pendiente", sin data-campo) NO cuentan como hueco a propósito (item #9990654).
     *
     * @return array<int, array{ruta:string, label:string, destino:?string, tipo:string, editable:bool}>
     */
    public function missingFields(TalentoEmployeeDocument $documento): array
    {
        $documento->loadMissing('template');

        preg_match_all('/data-campo="([^"]+)"/', $documento->rendered_html ?? '', $matches);
        $rutas = array_values(array_unique($matches[1] ?? []));

        return collect($rutas)
            ->map(fn (string $ruta) => [
                'ruta' => $ruta,
                'label' => $this->labelForRuta($documento, $ruta),
                'destino' => $this->destinoForRuta($ruta),
                'tipo' => $this->tipoForRuta($ruta),
                'editable' => !in_array($ruta, self::NO_EDITABLE, true),
            ])
            ->values()
            ->all();
    }

    private function labelForRuta(TalentoEmployeeDocument $documento, string $ruta): string
    {
        if (isset(self::LABELS[$ruta])) {
            return self::LABELS[$ruta];
        }

        if (str_starts_with($ruta, 'doc.')) {
            $campo = collect($documento->template->fillable_fields ?? [])
                ->firstWhere('key', substr($ruta, 4));
            if ($campo) {
                return $campo['label'] ?? $ruta;
            }
        }

        $ultimo = str_contains($ruta, '.') ? substr($ruta, strrpos($ruta, '.') + 1) : $ruta;

        return ucfirst(str_replace('_', ' ', $ultimo));
    }

    private function destinoForRuta(string $ruta): ?string
    {
        if (in_array($ruta, self::NO_EDITABLE, true)) {
            return null;
        }
        if ($ruta === self::RUTA_HORARIO || isset(self::WHITELIST_COLABORADOR[$ruta]) || isset(self::WHITELIST_USER[$ruta])) {
            return 'empleado';
        }
        if (isset(self::WHITELIST_EMPRESA[$ruta])) {
            return 'empresa';
        }

        return 'doc';
    }

    private function tipoForRuta(string $ruta): string
    {
        if ($ruta === self::RUTA_HORARIO) {
            return 'horario';
        }
        if (in_array($ruta, ['empleado.fecha_ingreso', 'empleado.fecha_nacimiento'], true)) {
            return 'fecha';
        }
        if ($ruta === 'empleado.salario') {
            return 'numero';
        }

        return 'texto';
    }

    private function esHoraValida($valor): bool
    {
        return is_string($valor) && preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $valor) === 1;
    }

    private function normalizarValorColaborador(string $columna, $valor)
    {
        if ($columna === 'base_salary') {
            $limpio = is_string($valor) ? preg_replace('/[^0-9.\-]/', '', $valor) : $valor;

            return is_numeric($limpio) ? (float) $limpio : null;
        }

        if (in_array($columna, ['hire_date', 'birth_date'], true)) {
            if (!$valor) {
                return null;
            }
            try {
                return \Illuminate\Support\Carbon::parse($valor)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return is_string($valor) ? trim($valor) : $valor;
    }

    /**
     * Item #9990661 — deep-merge de datos_extra sobre el nivel superior de $data: las claves
     * cortas (group=doc, sin punto) siguen entrando SOLO como data['doc'][clave] (retrocompat);
     * las claves con ruta completa (fecha.*, vehiculo.*, empleado.* o empresa.* sin columna) se
     * overlayean con data_set() para que {{esa.ruta}} resuelva con el valor capturado.
     */
    private function overlayDatosExtra(array $data, array $datosExtra): array
    {
        $data['doc'] = $datosExtra;

        foreach ($datosExtra as $clave => $valor) {
            if (is_string($clave) && str_contains($clave, '.')) {
                data_set($data, $clave, $valor);
            }
        }

        return $data;
    }

    /**
     * Re-renderiza UN documento puntual (no todo el paquete del colaborador, a diferencia de
     * `generateForColaborador`) con sus datos_extra vigentes. Mismo upsert seguro: no toca
     * signature_path/signed_at (fuera del array de atributos que actualiza).
     */
    public function regenerateOne(TalentoEmployeeDocument $documento, bool $mostrarFaltantes = false): TalentoEmployeeDocument
    {
        $documento->loadMissing(['colaborador', 'template.currentVersion', 'template.signatureSlots']);

        $colaborador = $documento->colaborador;
        $version = $documento->template?->currentVersion;
        if (!$colaborador || !$version) {
            return $documento;
        }

        $data = $this->overlayDatosExtra($this->buildData($colaborador), $documento->datos_extra ?? []);
        // Mismo tratamiento de firma que generateForColaborador() (item #9990654): sin esto,
        // completar() pisaría el HTML de un documento ya firmado y borraría la firma visible.
        if ($documento->template->signatureSlots->isNotEmpty()) {
            $data['firma'] = $this->firmaData($documento->template, $documento->id);
        }

        $html = $this->renderer->renderDocument($version->content, $data, $documento->template->name, $mostrarFaltantes);
        $status = str_contains($html, 'campo-faltante') ? 'pendiente' : 'completo';

        $documento->update([
            'template_version_id' => $version->id,
            'rendered_html'       => $html,
            'status'              => $status,
            'generated_at'        => now(),
        ]);

        return $documento->fresh();
    }

    private function buildData(TalentoColaborador $colaborador): array
    {
        $fechaEmision = now()->translatedFormat('d \d\e F \d\e Y');

        return [
            'empleado' => $this->empleadoData($colaborador),
            'empresa'  => $this->empresaData(),
            'fecha'    => [
                'emision' => $fechaEmision,
                // Item #9990645: ciudad de la empresa para el pie de firma. No existe columna
                // city/ciudad en company_information — se usa el municipio (equivalente real
                // más cercano en el domicilio mexicano, ya expuesto via appends). Null si la
                // empresa no tiene municipio capturado (renderer lo deja en blanco, no [FALTA:]).
                'ciudad_firma' => CompanyInformation::first()?->municipality_name,
                // Sin captura propia de "fecha en que se firmó" en ningún lado del sistema:
                // se usa la de emisión como default razonable (mismo día que se generó/imprimió).
                'firma' => $fechaEmision,
            ],
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
