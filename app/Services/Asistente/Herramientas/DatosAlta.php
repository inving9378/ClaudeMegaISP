<?php

namespace App\Services\Asistente\Herramientas;

use Illuminate\Support\Facades\DB;

/**
 * Datos de contacto y domicilio que comparten el alta de prospectos y la de clientes: los
 * argumentos (en español, como los dice el usuario), su limpieza y la colonia del catálogo.
 */
trait DatosAlta
{
    protected function propiedadesAlta(): array
    {
        return [
            'nombre'           => ['type' => 'string', 'description' => 'Nombre(s), sin apellidos.'],
            'apellido_paterno' => ['type' => 'string'],
            'apellido_materno' => ['type' => 'string'],
            'telefono'         => ['type' => 'string'],
            'telefono2'        => ['type' => 'string'],
            'correo'           => ['type' => 'string'],
            'colonia_id'       => ['type' => 'integer', 'description' => 'Número de colonia que da buscar_colonia.'],
            'calle'            => ['type' => 'string'],
            'numero_exterior'  => ['type' => 'string'],
            'numero_interior'  => ['type' => 'string'],
            'cp'               => ['type' => 'string', 'description' => 'Código postal. Si no lo dan, se usa el de la colonia.'],
            'aunque_exista'    => ['type' => 'boolean', 'description' => 'true solo si el usuario ya dijo que lo registre aunque exista alguien con el mismo nombre.'],
        ];
    }

    protected static function texto(array $args, string $clave, int $max = 120): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_substr((string) ($args[$clave] ?? ''), 0, $max)));
    }

    protected static function telefono(array $args, string $clave): string
    {
        return preg_replace('/\D/', '', (string) ($args[$clave] ?? ''));
    }

    /** La colonia del catálogo, con municipio y estado, o null. */
    protected static function colonia(?int $id): ?object
    {
        if (!$id) {
            return null;
        }

        return DB::table('colonies as c')
            ->leftJoin('municipalities as m', 'm.id', '=', 'c.municipality_id')
            ->leftJoin('states as s', 's.id', '=', 'm.state_id')
            ->where('c.id', $id)
            ->first(['c.id', 'c.name', 'c.data as cp', 'c.municipality_id', 'm.state_id', 'm.name as municipio', 's.name as estado']);
    }

    /**
     * Lo común ya limpio + lo que falta. Los nombres de campo son los de las pantallas.
     *
     * @return array{datos:array, faltan:string[], colonia:?object}
     */
    protected function datosComunes(array $args): array
    {
        $colonia = self::colonia($this->entero($args, 'colonia_id'));
        $datos = [
            'name'             => self::texto($args, 'nombre'),
            'father_last_name' => self::texto($args, 'apellido_paterno'),
            'mother_last_name' => self::texto($args, 'apellido_materno'),
            'phone'            => self::telefono($args, 'telefono'),
            'phone2'           => self::telefono($args, 'telefono2'),
            'email'            => mb_strtolower(self::texto($args, 'correo')),
            'colony_id'        => $colonia?->id,
            'municipality_id'  => $colonia?->municipality_id,
            'state_id'         => $colonia?->state_id,
            'street'           => self::texto($args, 'calle'),
            'external_number'  => self::texto($args, 'numero_exterior', 20),
            'internal_number'  => self::texto($args, 'numero_interior', 20),
            'zip'              => preg_replace('/\D/', '', (string) ($args['cp'] ?? '')) ?: ($colonia?->cp ?? ''),
            'enable_same_name_or_rfc' => !empty($args['aunque_exista']),
        ];

        $faltan = [];
        foreach (['name' => 'nombre', 'father_last_name' => 'apellido paterno', 'mother_last_name' => 'apellido materno'] as $campo => $etiqueta) {
            if ($datos[$campo] === '') {
                $faltan[] = $etiqueta;
            }
        }
        if (!$colonia) {
            $faltan[] = 'colonia (el nombre o el código postal)';
        }

        return ['datos' => $datos, 'faltan' => $faltan, 'colonia' => $colonia];
    }

    /**
     * Clientes y prospectos que ya existen con el mismo nombre completo o el mismo correo, con sus
     * datos reales: así JARVIS le dice al usuario quién es (y no se lo inventa).
     */
    protected static function existentes(array $d): array
    {
        $out = [];
        $porNombre = fn ($q, $a) => $q->where("{$a}.name", $d['name'])->where("{$a}.father_last_name", $d['father_last_name'])
            ->where("{$a}.mother_last_name", $d['mother_last_name']);
        $clientes = DB::table('client_main_information as c')->join('clients as cl', 'cl.id', '=', 'c.client_id')
            ->leftJoin('colonies as col', 'col.id', '=', 'c.colony_id')->whereNull('cl.deleted_at')
            ->where(fn ($w) => $w->where(fn ($q) => $porNombre($q, 'c'))->when($d['email'] !== '', fn ($q) => $q->orWhere('c.email', $d['email'])))
            ->limit(3)->get(['cl.id', 'c.name', 'c.father_last_name', 'c.mother_last_name', 'c.estado', 'c.email', 'col.name as colonia']);
        foreach ($clientes as $c) {
            $out[] = ['es' => 'cliente', 'numero' => $c->id, 'nombre' => trim("{$c->name} {$c->father_last_name} {$c->mother_last_name}"),
                'estado' => $c->estado, 'correo' => $c->email, 'colonia' => $c->colonia, 'enlace' => "/cliente/editar/{$c->id}"];
        }
        $prospectos = DB::table('crm_main_information as m')->leftJoin('crm_lead_information as l', 'l.crm_id', '=', 'm.crm_id')
            ->leftJoin('colonies as col', 'col.id', '=', 'm.colony_id')
            ->where(fn ($w) => $w->where(fn ($q) => $porNombre($q, 'm'))->when($d['email'] !== '', fn ($q) => $q->orWhere('m.email', $d['email'])))
            ->limit(3)->get(['m.crm_id', 'm.name', 'm.father_last_name', 'm.mother_last_name', 'l.crm_status', 'm.email', 'col.name as colonia']);
        foreach ($prospectos as $p) {
            $out[] = ['es' => 'prospecto', 'numero' => $p->crm_id, 'nombre' => trim("{$p->name} {$p->father_last_name} {$p->mother_last_name}"),
                'estado' => $p->crm_status, 'correo' => $p->email, 'colonia' => $p->colonia, 'enlace' => "/crm/editar/{$p->crm_id}"];
        }

        return $out;
    }

    /** Respuesta de "la pantalla no lo acepta", con los registros que ya existen si es por repetido. */
    protected static function rechazo(string $pantalla, string $error, array $datos): array
    {
        $ya = self::existentes($datos);

        return ['error' => "La pantalla de {$pantalla} no lo acepta: {$error}"]
            + ($ya ? [
                'ya_existe'  => $ya,
                'como_seguir' => 'Dile quién es con su enlace (usa solo estos datos) y pregúntale si de todos modos lo registras; si dice que sí, vuelve a prepararlo con aunque_exista.',
            ] : []);
    }

    /** Las líneas del resumen que ve el usuario antes de decir "sí". */
    protected function lineasResumen(array $d, ?object $colonia): array
    {
        $domicilio = trim(implode(' ', array_filter([$d['street'], $d['external_number'], $d['internal_number'] !== '' ? 'int. ' . $d['internal_number'] : ''])));

        return array_values(array_filter([
            '- Nombre: ' . trim("{$d['name']} {$d['father_last_name']} {$d['mother_last_name']}"),
            $d['phone'] !== '' ? "- Teléfono: {$d['phone']}" : null,
            $d['phone2'] !== '' ? "- Teléfono 2: {$d['phone2']}" : null,
            $d['email'] !== '' ? "- Correo: {$d['email']}" : null,
            $colonia ? "- Colonia: {$colonia->name}, {$colonia->municipio}, {$colonia->estado}" . ($d['zip'] !== '' ? " (CP {$d['zip']})" : '') : null,
            $domicilio !== '' ? "- Domicilio: {$domicilio}" : null,
        ]));
    }
}
