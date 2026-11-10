<?php

namespace Database\Seeders;

use App\Modules\Addons\Talento\Models\TalentoWorkOrderTemplate;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Support\EsquemaCamposOrden;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Item #1200, Fase A6 — tipo de orden y plantilla de planta externa "Habilitar caja".
 *
 *   php artisan db:seed --class=TalentoPlantaExternaSeeder
 *
 * Idempotente y sin ids: todo se busca por nombre. Solo CREA lo que falta; si el tipo o la
 * plantilla ya existen no los toca, para no pisar lo que se ajuste después en la pantalla de
 * plantillas (Fase C): puntos, proyecto, campos… La única excepción es el proyecto del tipo, que
 * se llena si sigue vacío y ya existe en el entorno.
 *
 * Las demás plantillas de planta externa (Montaje de fibra óptica, Administración de zonas…) NO
 * se siembran: se capturan en la Fase C (#1201).
 */
class TalentoPlantaExternaSeeder extends Seeder
{
    private const TIPO = 'Habilitar caja';

    /** Proyecto que usaba la plantilla de Scheduling; se busca por título sin importar espacios. */
    private const PROYECTO = 'Habilitación de caja Zona: Caja';

    /** Evidencias obligatorias, por nombre. La lectura dBm queda fuera hasta tener umbrales de caja (#1203). */
    private const EVIDENCIAS = ['Foto antes', 'Foto después'];

    private const CAMPOS = [
        ['key' => 'nomenclatura', 'label' => 'Nomenclatura',   'type' => 'texto',       'required' => true,  'when' => 'creacion', 'help' => 'Ejemplo: D1'],
        ['key' => 'zona',         'label' => 'Zona',           'type' => 'texto',       'required' => true,  'when' => 'creacion', 'identificador' => true],
        ['key' => 'caja',         'label' => 'Caja',           'type' => 'texto',       'required' => true,  'when' => 'creacion', 'identificador' => true],
        ['key' => 'coordenadas',  'label' => 'Coordenadas',    'type' => 'coordenadas', 'required' => true,  'when' => 'creacion', 'help' => 'latitud,longitud'],
        ['key' => 'modelo_caja',  'label' => 'Modelo de caja', 'type' => 'texto',       'required' => false, 'when' => 'creacion'],
        // La potencia se queda en los datos de la orden: no pasa a talento_work_order_media.potencia_dbm,
        // así no la toman ni el bono de salud de red ni el bloqueo de cierre por dBm (#1203).
        ['key' => 'potencia',     'label' => 'Potencia',       'type' => 'numero',      'required' => true,  'when' => 'cierre', 'unit' => 'dBm'],
        ['key' => 'splitter',     'label' => 'Splitter',       'type' => 'texto',       'required' => false, 'when' => 'cierre'],
        ['key' => 'serie_fibra',  'label' => 'Serie fibra',    'type' => 'texto',       'required' => false, 'when' => 'cierre'],
        ['key' => 'tipo_fibra',   'label' => 'Tipo de fibra',  'type' => 'texto',       'required' => false, 'when' => 'cierre'],
        ['key' => 'buffer',       'label' => 'Buffer',         'type' => 'texto',       'required' => false, 'when' => 'cierre'],
        ['key' => 'hilo',         'label' => 'Hilo',           'type' => 'texto',       'required' => false, 'when' => 'cierre'],
        ['key' => 'caset',        'label' => 'Caset',          'type' => 'texto',       'required' => false, 'when' => 'cierre'],
        ['key' => 'etiquetado',   'label' => 'Etiquetado',     'type' => 'si_no',       'required' => false, 'when' => 'cierre'],
    ];

    private const CHECKLIST = [
        '8 cinchos blancos',
        'Cinchos plásticos chicos',
        'Cinchos plásticos grandes',
        'Cinchos metálicos',
        'Toallitas',
        'Cinta doble cara',
        'Alcohol isopropílico',
    ];

    public function run(): void
    {
        $esquema = EsquemaCamposOrden::normalizarEsquema(self::CAMPOS);
        $checklist = EsquemaCamposOrden::normalizarChecklist(self::CHECKLIST);
        if ($esquema['errors'] || $checklist['errors']) {
            throw new \RuntimeException('Esquema de Habilitar caja inválido: ' . json_encode($esquema['errors'] + $checklist['errors'], JSON_UNESCAPED_UNICODE));
        }

        DB::transaction(function () use ($esquema, $checklist) {
            $projectId = $this->proyectoPorTitulo(self::PROYECTO);

            $tipo = TalentoWorkOrderType::where('name', self::TIPO)->first();
            if (! $tipo) {
                $tipo = TalentoWorkOrderType::create([
                    'name'                => self::TIPO,
                    'category'            => 'campo', // Task::saving exige tipo == category; los listados filtran 'campo'
                    'area'                => TalentoWorkOrderType::AREA_PLANTA_EXTERNA,
                    'project_id'          => $projectId,
                    'color'               => '#43A047',
                    'points'              => 0,     // los define Irving (#1204)
                    'is_billable'         => false, // sin bono de salud de red ni garantía (#1203)
                    'requires_validation' => true,
                    'inicia_garantia'     => false,
                    'active'              => true,
                ]);
                $this->info("Tipo «" . self::TIPO . "» creado (id {$tipo->id}).");
            } elseif (! $tipo->project_id && $projectId) {
                $tipo->update(['project_id' => $projectId]);
                $this->info("Tipo «" . self::TIPO . "»: proyecto asignado.");
            } else {
                $this->info("Tipo «" . self::TIPO . "» ya existía; no se modifica.");
            }

            if (! $tipo->project_id) {
                $this->aviso('No existe el proyecto «' . self::PROYECTO . '» en este entorno: el tipo «' . self::TIPO
                    . '» queda sin proyecto y no admite órdenes (422) hasta asignarlo en la Fase C.');
            }

            $this->evidencias($tipo->id);

            $plantilla = TalentoWorkOrderTemplate::withTrashed()
                ->where('type_id', $tipo->id)->where('name', self::TIPO)->first();
            if (! $plantilla) {
                TalentoWorkOrderTemplate::create([
                    'type_id'    => $tipo->id,
                    'name'       => self::TIPO,
                    'title'      => self::TIPO,
                    'priority'   => 'Media',
                    'fields'     => $esquema['fields'],
                    'checklist'  => $checklist['items'],
                    'active'     => true,
                    'sort_order' => 10,
                ]);
                $this->info('Plantilla «' . self::TIPO . '» creada.');
            } else {
                $this->info('Plantilla «' . self::TIPO . '» ya existía; no se modifica.');
            }
        });
    }

    private function proyectoPorTitulo(string $titulo): ?int
    {
        $buscado = $this->normalizar($titulo);

        foreach (DB::table('projects')->whereNull('deleted_at')->orderBy('id')->get(['id', 'title']) as $p) {
            if ($this->normalizar((string) $p->title) === $buscado) {
                return (int) $p->id;
            }
        }

        return null;
    }

    private function evidencias(int $tipoId): void
    {
        foreach (self::EVIDENCIAS as $nombre) {
            $evId = DB::table('talento_evidence_types')->where('name', $nombre)->value('id');
            if (! $evId) {
                $this->aviso("No existe el tipo de evidencia «{$nombre}»; no se agrega como obligatoria.");
                continue;
            }
            $existe = DB::table('talento_ot_type_evidence_requirements')
                ->where('ot_type_id', $tipoId)->where('evidence_type_id', $evId)->exists();
            if (! $existe) {
                DB::table('talento_ot_type_evidence_requirements')->insert([
                    'ot_type_id'       => $tipoId,
                    'evidence_type_id' => $evId,
                    'condition'        => null,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $this->info("Evidencia obligatoria «{$nombre}» agregada.");
            }
        }
    }

    private function normalizar(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s)));
    }

    private function info(string $m): void
    {
        $this->command?->info($m);
    }

    private function aviso(string $m): void
    {
        Log::warning('TalentoPlantaExternaSeeder: ' . $m);
        $this->command?->warn($m);
    }
}
