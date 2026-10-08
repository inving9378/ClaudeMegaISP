<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Busca colonias del catálogo (nombre, código postal, municipio, estado). Se necesita para dar de
 * alta clientes y prospectos: las pantallas piden la colonia exacta del catálogo.
 */
class BuscarColonia extends Herramienta
{
    public function nombre(): string
    {
        return 'buscar_colonia';
    }

    public function descripcion(): string
    {
        return 'Busca colonias del catálogo por nombre, código postal, municipio o estado. Devuelve el número de '
            . 'colonia que piden crear_cliente y crear_prospecto. Si hay varias parecidas, pregunta cuál es.';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'colonia'   => ['type' => 'string', 'description' => 'Nombre (o parte) de la colonia.'],
                'cp'        => ['type' => 'string', 'description' => 'Código postal.'],
                'municipio' => ['type' => 'string', 'description' => 'Municipio o alcaldía, por ejemplo Tultitlán o Guadalajara.'],
                'estado'    => ['type' => 'string', 'description' => 'Estado, por ejemplo Jalisco.'],
            ],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $colonia = trim((string) ($args['colonia'] ?? ''));
        $cp = preg_replace('/\D/', '', (string) ($args['cp'] ?? ''));
        $municipio = trim((string) ($args['municipio'] ?? ''));
        $estado = trim((string) ($args['estado'] ?? ''));
        if ($colonia === '' && $cp === '') {
            return ['error' => 'Necesito el nombre de la colonia o el código postal.'];
        }

        $q = DB::table('colonies as c')
            ->leftJoin('municipalities as m', 'm.id', '=', 'c.municipality_id')
            ->leftJoin('states as s', 's.id', '=', 'm.state_id');
        if ($colonia !== '') {
            $q->where('c.name', 'like', '%' . $colonia . '%')
                ->orderByRaw('c.name = ? DESC', [$colonia]);
        }
        if ($cp !== '') {
            $q->where('c.data', $cp);
        }
        if ($municipio !== '') {
            $q->where('m.name', 'like', '%' . $municipio . '%');
        }
        if ($estado !== '') {
            $q->where('s.name', 'like', '%' . $estado . '%');
        }

        $total = (clone $q)->count();
        $filas = $q->orderBy('c.name')->limit(10)->get(['c.id', 'c.name', 'c.data as cp', 'm.name as municipio', 's.name as estado']);

        if ($filas->isEmpty()) {
            return ['encontradas' => 0, 'nota' => 'No hay colonias así en el catálogo. Pide otro dato (código postal o municipio).'];
        }

        return [
            'encontradas' => $total,
            'colonias'    => $filas->map(fn ($c) => [
                'colonia_id' => $c->id,
                'colonia'    => $c->name,
                'cp'         => $c->cp,
                'municipio'  => $c->municipio,
                'estado'     => $c->estado,
            ])->all(),
            'nota' => $total > 10 ? "Hay {$total}; muestro 10. Pide el código postal o el municipio para afinar." : null,
        ];
    }
}
