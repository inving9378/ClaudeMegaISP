<?php

namespace App\Modules\Addons\Marketing\Requests;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\MarketingNiche;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Services\Campanas360\Campana360Presenter;
use App\Modules\Addons\Marketing\Services\Campanas360\CatalogosCampana360;
use App\Modules\Addons\Marketing\Services\Campanas360\RepartoPresupuesto;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * POST /api/marketing/campanas-360 y PUT /api/marketing/campanas-360/{id} (§2.4.2 y §2.4.4; borrador parcial por F-1).
 *
 * Saneado previo de todo texto: `strip_tags` y sin caracteres de control (se conservan salto de línea y tabulador).
 * `{{` / `}}` → 422. Arreglos donde va texto (o al revés) → 422. En PUT todos los campos son `sometimes`; las reglas
 * cruzadas (fechas, edades) se revisan contra lo que ya tiene la campaña.
 */
class GuardarCampana360Request extends FormRequest
{
    private const SIN_LLAVES = 'not_regex:/\{\{|\}\}/';

    private const TEXTOS = [
        'nombre', 'publico.descripcion', 'oferta.descripcion', 'oferta.plan', 'oferta.condiciones',
    ];

    private const LISTAS_TEXTO = ['publico.zonas', 'publico.intereses', 'publico.nichos'];

    public function authorize(): bool
    {
        return true;
    }

    public function esEdicion(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }

    protected function prepareForValidation(): void
    {
        $datos = $this->all();

        foreach (self::TEXTOS as $ruta) {
            $valor = data_get($datos, $ruta);
            if (is_string($valor)) {
                data_set($datos, $ruta, self::sanear($valor));
            }
        }

        foreach (self::LISTAS_TEXTO as $ruta) {
            $lista = data_get($datos, $ruta);
            if (is_array($lista)) {
                data_set($datos, $ruta, array_map(fn ($v) => is_string($v) ? self::sanear($v) : $v, $lista));
            }
        }

        $this->replace($datos);
    }

    public function rules(): array
    {
        // F-1 (QA, decisión del supervisor): se guarda como borrador en cualquier paso. En POST solo son obligatorios
        // nombre y objetivo (paso 1); en PUT nada es obligatorio pero lo que venga no puede ir vacío si identifica la
        // campaña. Todo lo que SÍ venga se valida igual de estricto. La campaña completa se exige al generar y al
        // activar (Campana360Presenter::faltantes).
        $id  = $this->esEdicion() ? ['sometimes'] : [];
        $opc = ['sometimes', 'nullable'];
        $hoy = Carbon::now('America/Mexico_City')->toDateString();

        $fechaInicio = array_merge($opc, ['date_format:Y-m-d']);
        if (! $this->esEdicion() || $this->fechaInicioCambia()) {
            $fechaInicio[] = 'after_or_equal:' . $hoy;
        }

        return [
            'nombre'               => array_merge($id, ['required', 'string', 'min:3', 'max:150', self::SIN_LLAVES]),
            'objetivo'             => array_merge($id, ['required', 'string', Rule::in(array_keys(CatalogosCampana360::OBJETIVOS))]),
            'canales'              => array_merge($opc, ['array', 'max:6']),
            'canales.*'            => ['required', 'string', 'distinct', Rule::in(array_keys(CatalogosCampana360::CANALES))],

            'publico'              => array_merge($opc, ['array']),
            'publico.descripcion'  => ['nullable', 'string', 'min:3', 'max:500', self::SIN_LLAVES],
            'publico.zonas'        => ['nullable', 'array', 'max:20'],
            'publico.zonas.*'      => ['required', 'string', 'max:80', self::SIN_LLAVES],
            'publico.edad_min'     => ['nullable', 'integer', 'between:13,99'],
            'publico.edad_max'     => ['nullable', 'integer', 'between:13,99'],
            'publico.nichos'       => ['nullable', 'array'],
            'publico.nichos.*'     => ['required', 'string', 'distinct', Rule::in($this->nichosValidos())],
            'publico.intereses'    => ['nullable', 'array', 'max:10'],
            'publico.intereses.*'  => ['required', 'string', 'max:40', self::SIN_LLAVES],

            'oferta'                => array_merge($opc, ['array']),
            'oferta.descripcion'    => ['nullable', 'string', 'min:3', 'max:500', self::SIN_LLAVES],
            'oferta.plan'           => ['nullable', 'string', 'max:100', self::SIN_LLAVES],
            'oferta.precio_mxn'     => ['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'oferta.condiciones'    => ['nullable', 'string', 'max:500', self::SIN_LLAVES],
            'oferta.vigencia_hasta' => ['nullable', 'date_format:Y-m-d'],

            'presupuesto_mxn'      => array_merge($opc, ['regex:/^\d{1,10}(\.\d{1,2})?$/']),
            'fecha_inicio'         => $fechaInicio,
            'fecha_fin'            => array_merge($opc, ['date_format:Y-m-d']),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            foreach (['canales', 'publico.zonas', 'publico.nichos', 'publico.intereses'] as $campo) {
                $lista = $this->input($campo);
                if (is_array($lista) && ! array_is_list($lista)) {
                    $v->errors()->add($campo, 'Debe ser una lista.');
                }
            }

            // Fecha de fin vs. inicio: se revisa aunque haya otros errores (Henry 13-nov: 422 claro en `fecha_fin`).
            $actual = $this->campanaActual();
            $this->revisarFechas($v, $actual);

            if ($v->errors()->isNotEmpty()) {
                return;
            }

            foreach (['oferta.precio_mxn', 'presupuesto_mxn'] as $campo) {
                $valor = $this->input($campo);
                if ($valor !== null && ! is_string($valor) && ! is_int($valor)) {
                    $v->errors()->add($campo, 'Escribe la cantidad como número con hasta 2 decimales.');
                }
            }

            $min = $this->has('publico') ? $this->input('publico.edad_min') : ($actual?->publico['edad_min'] ?? null);
            $max = $this->has('publico') ? $this->input('publico.edad_max') : ($actual?->publico['edad_max'] ?? null);
            if ($min !== null && $max !== null && (int) $min > (int) $max) {
                $v->errors()->add('publico.edad_max', 'La edad máxima debe ser mayor o igual a la mínima.');
            }

        });
    }

    private function revisarFechas(Validator $v, ?Campaign $actual): void
    {
        $inicio = $this->has('fecha_inicio') ? $this->input('fecha_inicio') : $actual?->start_date?->format('Y-m-d');
        $fin    = $this->has('fecha_fin') ? $this->input('fecha_fin') : $actual?->end_date?->format('Y-m-d');
        if (self::esFecha($inicio) && self::esFecha($fin) && ! $v->errors()->has('fecha_fin')) {
            $a = Carbon::createFromFormat('Y-m-d', $inicio)->startOfDay();
            $b = Carbon::createFromFormat('Y-m-d', $fin)->startOfDay();
            if ($b->lt($a)) {
                $v->errors()->add('fecha_fin', 'La fecha de fin debe ser igual o posterior a la de inicio.');
            } elseif ($a->diffInDays($b) > 366) {
                $v->errors()->add('fecha_fin', 'La campaña puede durar como máximo 366 días.');
            }
        }
    }

    private static function esFecha(mixed $f): bool
    {
        return is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) === 1 && Carbon::hasFormat($f, 'Y-m-d')
            && Carbon::createFromFormat('Y-m-d', $f)->format('Y-m-d') === $f;
    }

    public function messages(): array
    {
        return [
            'required'                 => 'Este campo es obligatorio.',
            'required_with'            => 'Este campo es obligatorio.',
            'string'                   => 'Debe ser texto.',
            'array'                    => 'Formato no válido.',
            'integer'                  => 'Debe ser un número entero.',
            'in'                       => 'Elige una opción de la lista.',
            'distinct'                 => 'No repitas opciones.',
            'not_regex'                => 'No puede llevar {{ }}.',
            'date_format'              => 'Usa una fecha válida (AAAA-MM-DD).',
            'nombre.min'               => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max'               => 'El nombre puede tener hasta 150 caracteres.',
            'canales.min'              => 'Elige al menos un canal.',
            'canales.max'              => 'Son 6 canales como máximo.',
            'publico.descripcion.min'  => 'Describe al público con al menos 3 caracteres.',
            'publico.descripcion.max'  => 'La descripción del público puede tener hasta 500 caracteres.',
            'publico.zonas.max'        => 'Hasta 20 zonas.',
            'publico.zonas.*.max'      => 'Cada zona puede tener hasta 80 caracteres.',
            'publico.edad_min.between' => 'La edad debe estar entre 13 y 99.',
            'publico.edad_max.between' => 'La edad debe estar entre 13 y 99.',
            'publico.nichos.*.in'      => 'Ese nicho no existe.',
            'publico.intereses.max'    => 'Hasta 10 intereses.',
            'publico.intereses.*.max'  => 'Cada interés puede tener hasta 40 caracteres.',
            'oferta.descripcion.min'   => 'Describe la oferta con al menos 3 caracteres.',
            'oferta.descripcion.max'   => 'La descripción de la oferta puede tener hasta 500 caracteres.',
            'oferta.plan.max'          => 'El plan puede tener hasta 100 caracteres.',
            'oferta.precio_mxn.regex'  => 'Escribe el precio en pesos con hasta 2 decimales (ej. 599.00).',
            'oferta.condiciones.max'   => 'Las condiciones pueden tener hasta 500 caracteres.',
            'presupuesto_mxn.regex'    => 'Escribe el presupuesto en pesos con hasta 2 decimales (ej. 15000.00).',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy.',
        ];
    }

    /**
     * Atributos del modelo con los datos validados (solo lo que vino en la petición).
     *
     * @return array<string,mixed>
     */
    public function datos(): array
    {
        $v = $this->validated();
        $d = [];

        if (array_key_exists('nombre', $v)) {
            $d['name'] = $v['nombre'];
        }
        if (array_key_exists('objetivo', $v)) {
            $d['objetivo'] = $v['objetivo'];
        }
        if (array_key_exists('canales', $v)) {
            $d['canales'] = array_values($v['canales'] ?? []);
        }
        if (array_key_exists('publico', $v)) {
            $p = $v['publico'];
            $d['publico'] = $p === null ? null : [
                'descripcion' => $p['descripcion'] ?? null,
                'zonas'       => array_values($p['zonas'] ?? []),
                'edad_min'    => isset($p['edad_min']) ? (int) $p['edad_min'] : null,
                'edad_max'    => isset($p['edad_max']) ? (int) $p['edad_max'] : null,
                'nichos'      => array_values($p['nichos'] ?? []),
                'intereses'   => array_values($p['intereses'] ?? []),
            ];
        }
        if (array_key_exists('oferta', $v)) {
            $o = $v['oferta'];
            $d['oferta'] = $o === null ? null : [
                'descripcion'    => $o['descripcion'] ?? null,
                'plan'           => $o['plan'] ?? null,
                'precio_mxn'     => isset($o['precio_mxn']) ? RepartoPresupuesto::dinero((string) $o['precio_mxn']) : null,
                'condiciones'    => $o['condiciones'] ?? null,
                'vigencia_hasta' => $o['vigencia_hasta'] ?? null,
            ];
        }
        if (array_key_exists('presupuesto_mxn', $v)) {
            // `budget` es NOT NULL (default 0): «sin capturar» se distingue de «orgánica en $0» con la marca en `config`.
            $definido = $v['presupuesto_mxn'] !== null;
            $d['budget'] = $definido ? RepartoPresupuesto::dinero((string) $v['presupuesto_mxn']) : '0.00';
            $d['config'] = array_merge($this->campanaActual()?->config ?? [], [Campana360Presenter::MARCA_PRESUPUESTO => $definido]);
        }
        if (array_key_exists('fecha_inicio', $v)) {
            $d['start_date'] = $v['fecha_inicio'];
        }
        if (array_key_exists('fecha_fin', $v)) {
            $d['end_date'] = $v['fecha_fin'];
        }

        return $d;
    }

    public static function sanear(string $texto): string
    {
        $texto = strip_tags($texto);
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto) ?? '';

        return trim($texto);
    }

    private function campanaActual(): ?Campaign
    {
        $id = $this->route('id');

        return $this->esEdicion() && $id ? Campaign::query()->find((int) $id) : null;
    }

    private function fechaInicioCambia(): bool
    {
        if (! $this->has('fecha_inicio')) {
            return false;
        }

        return $this->input('fecha_inicio') !== $this->campanaActual()?->start_date?->format('Y-m-d');
    }

    /** @return array<int,string> */
    private function nichosValidos(): array
    {
        return MarketingNiche::query()
            ->where('company_id', Empresa::PRINCIPAL_ID)
            ->where('active', true)
            ->pluck('slug')
            ->all();
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422));
    }
}
