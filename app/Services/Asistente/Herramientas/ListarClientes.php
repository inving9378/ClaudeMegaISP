<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lista o cuenta clientes con filtros (los más recientes, por estado, colonia, vendedor o fecha de
 * registro). Lo mismo que se ve en la Lista de clientes, sin tener que armar la búsqueda a mano.
 */
class ListarClientes extends Herramienta
{
    use FiltrosListado;

    private const ESTADOS = ['Nuevo', 'Activo', 'Bloqueado', 'Inactivo', 'Cancelado'];

    public function nombre(): string
    {
        return 'listar_clientes';
    }

    public function descripcion(): string
    {
        return 'Lista o cuenta clientes: los últimos registrados, por estado, colonia, vendedor o fecha de registro. '
            . 'Úsala para "los últimos 3 clientes", "cuántos clientes activos hay", "clientes de la colonia X", '
            . '"clientes registrados este mes". Para UN cliente concreto usa buscar_cliente.';
    }

    public function permiso(): string|array|null
    {
        return 'client_view_client';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->propiedadesListado() + [
                'orden'    => ['type' => 'string', 'enum' => ['recientes', 'antiguos', 'nombre'], 'description' => 'Por defecto recientes (los últimos registrados primero).'],
                'estado'   => ['type' => 'string', 'enum' => self::ESTADOS],
                'colonia'  => ['type' => 'string', 'description' => 'Nombre (o parte) de la colonia.'],
                'vendedor' => ['type' => 'string', 'description' => 'Nombre (o parte) de quien lo vendió.'],
            ],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $rango = $this->rango($args);
        if (isset($rango['error'])) {
            return $rango;
        }

        $q = DB::table('clients as cl')
            ->join('client_main_information as c', 'c.client_id', '=', 'cl.id')
            ->leftJoin('colonies as col', 'col.id', '=', 'c.colony_id')
            ->leftJoin('users as v', 'v.id', '=', 'c.seller_id')
            ->whereNull('cl.deleted_at')
            ->whereNull('c.deleted_at');

        $filtros = [];
        if (in_array($args['estado'] ?? null, self::ESTADOS, true)) {
            $q->where('c.estado', $args['estado']);
            $filtros[] = 'estado ' . $args['estado'];
        }
        if (($colonia = trim((string) ($args['colonia'] ?? ''))) !== '') {
            $q->where('col.name', 'like', '%' . $colonia . '%');
            $filtros[] = "colonia «{$colonia}»";
        }
        if (($vendedor = trim((string) ($args['vendedor'] ?? ''))) !== '') {
            $q->whereRaw("CONCAT_WS(' ', v.name, v.father_last_name, v.mother_last_name) LIKE ?", ['%' . $vendedor . '%']);
            $filtros[] = "vendedor «{$vendedor}»";
        }
        if ($rango[0]) {
            $q->where('cl.created_at', '>=', $rango[0]);
        }
        if ($rango[1]) {
            $q->where('cl.created_at', '<=', $rango[1]);
        }
        if ($p = $this->periodoTexto($rango)) {
            $filtros[] = "registrados {$p}";
        }

        $total = (clone $q)->count();
        $respuesta = [
            'total_que_coinciden' => $total,
            'filtros'             => $filtros ?: ['todos los clientes'],
            'enlace_lista'        => $this->enlace('lista_clientes'),
        ];
        if (!empty($args['solo_contar'])) {
            return $respuesta;
        }

        match ($args['orden'] ?? 'recientes') {
            'antiguos' => $q->orderBy('cl.created_at')->orderBy('cl.id'),
            'nombre'   => $q->orderBy('c.name')->orderBy('c.father_last_name'),
            default    => $q->orderByDesc('cl.created_at')->orderByDesc('cl.id'),
        };

        $filas = $q->limit($this->cuantos($args))->get([
            'cl.id', 'cl.created_at', 'c.name', 'c.father_last_name', 'c.mother_last_name', 'c.estado', 'c.phone',
            'c.email', 'col.name as colonia', 'v.name as v_name', 'v.father_last_name as v_father_last_name',
        ]);

        return $respuesta + [
            'mostrando' => $filas->count(),
            'clientes'  => $filas->map(fn ($c) => [
                'numero'      => $c->id,
                'nombre'      => self::nombreDe($c),
                'estado'      => $c->estado,
                'telefono'    => $c->phone,
                'correo'      => $c->email,
                'colonia'     => $c->colonia,
                'registrado'  => DatosCliente::fecha($c->created_at, true),
                'vendedor'    => self::nombreDe($c, 'v_') ?: null,
                'enlace'      => $this->enlace('ficha_cliente', $c->id),
            ])->all(),
        ];
    }
}
