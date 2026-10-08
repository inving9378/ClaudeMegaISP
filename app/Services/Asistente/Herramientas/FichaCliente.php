<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Resumen de la ficha: contacto, estado, saldo, servicios contratados y equipo. */
class FichaCliente extends Herramienta
{
    public function nombre(): string
    {
        return 'ficha_cliente';
    }

    public function descripcion(): string
    {
        return 'Datos de un cliente por su número: estado, contacto, dirección, saldo (cuánto debe o tiene a favor), '
            . 'fechas de pago y de corte, referencia MEG, servicios contratados con precio, su equipo y tickets abiertos.';
    }

    public function permiso(): string|array|null
    {
        return 'client_view_client';
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => ['numero_cliente' => ['type' => 'integer']],
            'required'   => ['numero_cliente'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        $numero = $this->entero($args, 'numero_cliente');
        $c = $numero ? DatosCliente::cliente($numero) : null;
        if (!$c) {
            return DatosCliente::noEncontrado((int) $numero);
        }

        $servicios = [];
        foreach ([
            'Internet'      => 'client_internet_services',
            'Voz'           => 'client_voz_services',
            'Personalizado' => 'client_custom_services',
            'Paquete'       => 'client_bundle_services',
        ] as $tipo => $tabla) {
            foreach (DB::table($tabla)->where('client_id', $numero)->get(['description', 'price', 'estado']) as $s) {
                $servicios[] = ['tipo' => $tipo, 'servicio' => $s->description, 'precio' => DatosCliente::dinero($s->price), 'estado' => $s->estado];
            }
        }

        $equipo = DB::table('client_additional_information')->where('client_id', $numero)->whereNull('deleted_at')
            ->first(['connection_type', 'serie_equipo', 'modem_sn', 'power_dbm', 'last_time_online']);

        $saldo = DatosCliente::saldo($numero);
        $direccion = trim(implode(' ', array_filter([$c->street, $c->external_number, $c->internal_number ? "int. {$c->internal_number}" : null])));

        return [
            'numero_cliente'  => $c->numero,
            'nombre'          => DatosCliente::nombre($c),
            'estado'          => $c->estado,
            'telefonos'       => array_values(array_filter([$c->phone, $c->phone2, $c->phone3])),
            'correo'          => $c->email,
            'direccion'       => trim($direccion . ($c->colonia ? ", col. {$c->colonia}" : '') . ($c->zip ? ", C.P. {$c->zip}" : '')),
            'saldo'           => DatosCliente::saldoTexto($saldo),
            'fecha_de_pago'   => DatosCliente::fecha($c->fecha_pago),
            'fecha_de_corte'  => DatosCliente::fecha($c->fecha_corte),
            'cliente_desde'   => DatosCliente::fecha($c->activation_date ?: $c->discharge_date),
            'referencia_pago' => DB::table('client_payment_references')->where('client_id', $numero)->value('reference'),
            'servicios'       => $servicios,
            'equipo'          => $equipo ? array_filter([
                'conexion'          => $equipo->connection_type,
                'serie'             => $equipo->serie_equipo ?: $equipo->modem_sn,
                'senal_dbm'         => $equipo->power_dbm,
                'ultima_vez_en_linea' => DatosCliente::fecha($equipo->last_time_online, true),
            ]) : null,
            'tickets_abiertos' => DB::table('tickets')->where('customer_lead', $numero)->whereNotIn('estado', ['Cerrado', 'Reciclado', 'Resuelto'])->count(),
            'enlaces'         => [
                'ficha'      => $this->enlace('ficha_cliente', $numero),
                'pagos'      => $this->enlace('ficha_cliente_pagos', $numero),
                'servicios'  => $this->enlace('ficha_cliente_servicios', $numero),
            ],
        ];
    }
}
