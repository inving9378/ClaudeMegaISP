<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Modules\Core\Clientes\Services\ClienteSearchService;
use Illuminate\Support\Facades\DB;

/** Encuentra clientes por nombre, número de cliente, teléfono, correo, referencia MEG o serie del equipo. */
class BuscarCliente extends Herramienta
{
    private const MAX = 8;

    public function nombre(): string
    {
        return 'buscar_cliente';
    }

    public function descripcion(): string
    {
        return 'Busca clientes por nombre (completo o parcial), número de cliente, teléfono, correo, referencia de pago '
            . 'MEG o número de serie del equipo. Devuelve hasta 8 coincidencias con su número de cliente.';
    }

    public function permiso(): string|array|null
    {
        return 'client_view_client';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['texto' => ['type' => 'string', 'description' => 'Lo que dijo el usuario: nombre, número, teléfono, correo, MEG-... o serie']],
            'required'   => ['texto'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $texto = trim((string) ($args['texto'] ?? ''));
        if ($texto === '') {
            return ['error' => 'Necesito el nombre, el teléfono o el número del cliente.'];
        }

        $q = DB::table('client_main_information as c')
            ->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->leftJoin('colonies as col', 'col.id', '=', 'c.colony_id')
            ->whereNull('cl.deleted_at')
            ->whereNull('c.deleted_at');

        $soloDigitos = preg_replace('/[\s\-().+]/', '', $texto);
        $criterio = 'nombre';

        if (preg_match('/^MEG-?/i', $texto)) {
            $criterio = 'referencia';
            $ref = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $texto));
            $ids = DB::table('client_payment_references')->whereRaw("REPLACE(UPPER(reference), '-', '') = ?", [$ref])
                ->limit(self::MAX)->pluck('client_id');
            $q->whereIn('cl.id', $ids);
        } elseif ($soloDigitos !== '' && ctype_digit($soloDigitos)) {
            if (strlen($soloDigitos) <= 6) {
                $criterio = 'número de cliente';
                $q->where('cl.id', (int) ltrim($soloDigitos, '0'));
            } else {
                $criterio = 'teléfono';
                $tel = substr($soloDigitos, -10);
                $q->where(fn ($w) => $w->where('c.phone', 'like', "%{$tel}%")->orWhere('c.phone2', 'like', "%{$tel}%")->orWhere('c.phone3', 'like', "%{$tel}%"));
            }
        } elseif (str_contains($texto, '@')) {
            $criterio = 'correo';
            $q->where('c.email', 'like', '%' . $texto . '%');
        } elseif (preg_match('/^[A-Za-z0-9\-:]{8,}$/', $texto) && preg_match('/\d/', $texto) && preg_match('/[A-Za-z]/', $texto)) {
            $criterio = 'serie del equipo';
            $sn = ClienteSearchService::normalizarSn($texto);
            $ids = DB::table('client_additional_information')
                ->where('serie_equipo_norm', $sn)
                ->orWhere('modem_sn', 'like', "%{$texto}%")
                ->orWhere('gpon_ont', 'like', "%{$texto}%")
                ->limit(self::MAX)->pluck('client_id');
            $q->whereIn('cl.id', $ids);
        } else {
            $palabras = array_filter(preg_split('/\s+/', $texto), fn ($p) => mb_strlen($p) >= 2);
            if (!$palabras) {
                return ['error' => 'Necesito al menos dos letras del nombre.'];
            }
            foreach ($palabras as $p) {
                $q->whereRaw("CONCAT_WS(' ', c.name, c.father_last_name, c.mother_last_name) LIKE ?", ['%' . $p . '%']);
            }
        }

        $total = (clone $q)->count();
        $filas = $q->orderByRaw("c.estado = 'Activo' DESC")->orderBy('cl.id')->limit(self::MAX)
            ->get(['cl.id as numero', 'c.name', 'c.father_last_name', 'c.mother_last_name', 'c.estado', 'col.name as colonia', 'c.phone']);

        if ($filas->isEmpty()) {
            return ['encontrados' => 0, 'busque_por' => $criterio, 'nota' => 'No hay clientes que coincidan.'];
        }

        return [
            'encontrados' => $total,
            'busque_por'  => $criterio,
            'clientes'    => $filas->map(fn ($c) => [
                'numero_cliente' => $c->numero,
                'nombre'         => DatosCliente::nombre($c),
                'estado'         => $c->estado,
                'colonia'        => $c->colonia,
                'telefono'       => $c->phone,
                'enlace'         => $this->enlace('ficha_cliente', $c->numero),
            ])->all(),
            'nota' => $total > self::MAX ? "Hay {$total} coincidencias; muestro las primeras " . self::MAX . '. Pide más datos para afinar.' : null,
        ];
    }
}
