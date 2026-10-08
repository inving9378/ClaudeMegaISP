<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lista o cuenta prospectos del CRM (los más recientes, por estado, colonia, responsable o fecha).
 */
class ListarProspectos extends Herramienta
{
    use FiltrosListado;

    public const ESTADOS = ['Nuevo', 'Contactado', 'Interesado', 'Instalacion', 'Ganado', 'Perdido'];

    public function nombre(): string
    {
        return 'listar_prospectos';
    }

    public function descripcion(): string
    {
        return 'Lista o cuenta prospectos (posibles clientes del CRM): los últimos registrados, por estado, colonia, '
            . 'responsable o fecha. También sirve para buscar un prospecto por nombre, teléfono o correo.';
    }

    public function permiso(): string|array|null
    {
        return 'crm_view_crm';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->propiedadesListado() + [
                'orden'       => ['type' => 'string', 'enum' => ['recientes', 'antiguos', 'nombre']],
                'estado'      => ['type' => 'string', 'enum' => self::ESTADOS],
                'texto'       => ['type' => 'string', 'description' => 'Nombre, teléfono o correo (o parte).'],
                'colonia'     => ['type' => 'string'],
                'responsable' => ['type' => 'string', 'description' => 'Nombre (o parte) de quien lo atiende.'],
            ],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $rango = $this->rango($args);
        if (isset($rango['error'])) {
            return $rango;
        }

        $q = DB::table('crms as cr')
            ->join('crm_main_information as m', 'm.crm_id', '=', 'cr.id')
            ->leftJoin('crm_lead_information as l', 'l.crm_id', '=', 'cr.id')
            ->leftJoin('colonies as col', 'col.id', '=', 'm.colony_id')
            ->leftJoin('users as o', 'o.id', '=', 'l.owner_id');

        $filtros = [];
        if (in_array($args['estado'] ?? null, self::ESTADOS, true)) {
            $q->where('l.crm_status', $args['estado']);
            $filtros[] = 'estado ' . $args['estado'];
        }
        if (($texto = trim((string) ($args['texto'] ?? ''))) !== '') {
            $digitos = preg_replace('/\D/', '', $texto);
            $q->where(function ($w) use ($texto, $digitos) {
                $w->whereRaw("CONCAT_WS(' ', m.name, m.father_last_name, m.mother_last_name) LIKE ?", ['%' . $texto . '%'])
                    ->orWhere('m.email', 'like', '%' . $texto . '%');
                if (strlen($digitos) >= 7) {
                    $w->orWhere('m.phone', 'like', '%' . substr($digitos, -10) . '%')
                        ->orWhere('m.phone2', 'like', '%' . substr($digitos, -10) . '%');
                }
            });
            $filtros[] = "que coincidan con «{$texto}»";
        }
        if (($colonia = trim((string) ($args['colonia'] ?? ''))) !== '') {
            $q->where('col.name', 'like', '%' . $colonia . '%');
            $filtros[] = "colonia «{$colonia}»";
        }
        if (($resp = trim((string) ($args['responsable'] ?? ''))) !== '') {
            $q->whereRaw("CONCAT_WS(' ', o.name, o.father_last_name, o.mother_last_name) LIKE ?", ['%' . $resp . '%']);
            $filtros[] = "responsable «{$resp}»";
        }
        if ($rango[0]) {
            $q->where('cr.created_at', '>=', $rango[0]);
        }
        if ($rango[1]) {
            $q->where('cr.created_at', '<=', $rango[1]);
        }
        if ($p = $this->periodoTexto($rango)) {
            $filtros[] = "registrados {$p}";
        }

        $total = (clone $q)->count();
        $respuesta = [
            'total_que_coinciden' => $total,
            'filtros'             => $filtros ?: ['todos los prospectos'],
            'enlace_lista'        => $this->enlace('lista_prospectos'),
        ];
        if (!empty($args['solo_contar'])) {
            return $respuesta;
        }

        match ($args['orden'] ?? 'recientes') {
            'antiguos' => $q->orderBy('cr.created_at')->orderBy('cr.id'),
            'nombre'   => $q->orderBy('m.name')->orderBy('m.father_last_name'),
            default    => $q->orderByDesc('cr.created_at')->orderByDesc('cr.id'),
        };

        $filas = $q->limit($this->cuantos($args))->get([
            'cr.id', 'cr.created_at', 'm.name', 'm.father_last_name', 'm.mother_last_name', 'm.phone', 'm.email',
            'l.crm_status', 'col.name as colonia', 'o.name as o_name', 'o.father_last_name as o_father_last_name',
        ]);

        return $respuesta + [
            'mostrando'  => $filas->count(),
            'prospectos' => $filas->map(fn ($p) => [
                'prospecto'   => $p->id,
                'nombre'      => self::nombreDe($p),
                'estado'      => $p->crm_status,
                'telefono'    => $p->phone,
                'correo'      => $p->email,
                'colonia'     => $p->colonia,
                'registrado'  => DatosCliente::fecha($p->created_at, true),
                'responsable' => self::nombreDe($p, 'o_') ?: null,
                'enlace'      => $this->enlace('prospecto', $p->id),
            ])->all(),
        ];
    }
}
