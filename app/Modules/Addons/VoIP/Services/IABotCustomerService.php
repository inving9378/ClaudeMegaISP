<?php

namespace App\Modules\Addons\VoIP\Services;

use Illuminate\Support\Facades\DB;

/**
 * ¿Quién llama? Busca al cliente por los últimos 10 dígitos de sus teléfonos. Lo usan el
 * mini-teléfono (MiTelefonoController) y la asistente de voz, que además lee su contexto
 * (estado, servicios y saldo) para contestar sin pedir datos que el sistema ya tiene.
 */
class IABotCustomerService
{
    public function identifyCustomer(string $phoneRaw): array
    {
        $phone = preg_replace('/\D/', '', $phoneRaw);
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }

        // Una extensión interna (1001) o un número incompleto no es de nadie.
        if (strlen($phone) < 10) {
            return ['found' => false, 'type' => 'lead', 'phone' => $phone];
        }

        $row = DB::table('client_main_information as cmi')
            ->join('clients as c', 'c.id', '=', 'cmi.client_id')
            ->select('c.id', 'cmi.name', 'cmi.father_last_name', 'cmi.mother_last_name',
                     'cmi.email', 'cmi.phone', 'cmi.phone2', 'cmi.phone3', 'cmi.estado')
            ->where(function ($q) use ($phone) {
                $q->where(DB::raw("RIGHT(REGEXP_REPLACE(cmi.phone,  '[^0-9]',''), 10)"), $phone)
                  ->orWhere(DB::raw("RIGHT(REGEXP_REPLACE(cmi.phone2, '[^0-9]',''), 10)"), $phone)
                  ->orWhere(DB::raw("RIGHT(REGEXP_REPLACE(cmi.phone3, '[^0-9]',''), 10)"), $phone);
            })
            ->whereNull('c.deleted_at')
            ->whereNull('cmi.deleted_at')
            // Si dos fichas comparten teléfono: primero la Activa, luego Bloqueada, Inactiva y el resto.
            ->orderByRaw("FIELD(cmi.estado, 'Activo', 'Bloqueado', 'Inactivo') = 0, FIELD(cmi.estado, 'Activo', 'Bloqueado', 'Inactivo')")
            ->orderByDesc('c.id')
            ->first();

        if (! $row) {
            return ['found' => false, 'type' => 'lead', 'phone' => $phone];
        }

        $fullName = trim(preg_replace('/\s+/', ' ', "{$row->name} {$row->father_last_name} {$row->mother_last_name}"));

        return [
            'found'           => true,
            'type'            => 'customer',
            'id'              => $row->id,
            'name'            => $fullName ?: 'Cliente',
            'nombre_pila'     => self::nombreDePila((string) $row->name),
            'estado'          => $row->estado,
            'email'           => $row->email,
            'phone'           => $row->phone,
            'active_services' => $this->activeServices($row->id),
        ];
    }

    /**
     * Lo que la asistente sabe del cliente que llama, para no pedirle lo que el sistema ya
     * tiene. Solo lectura. El saldo es el de la ficha (`balances.amount`, el mismo que muestra
     * el mini-teléfono): negativo = debe, positivo = saldo a favor. La referencia MEG se calcula
     * (no se crea): es la misma que la ficha.
     *
     * @return array{estado: ?string, servicios: array<int, array{plan: string, precio: float}>, saldo: float, fecha_pago: ?string, fecha_corte: ?string, referencia: ?string}
     */
    public function contextoParaBot(int $clientId): array
    {
        $estado = DB::table('client_main_information')->where('client_id', $clientId)->whereNull('deleted_at')->value('estado');

        $servicios = DB::table('client_internet_services')
            ->where('client_id', $clientId)
            ->whereIn('estado', ['Activo', 'Activado'])
            ->orderBy('id')
            ->limit(5)
            ->get(['description', 'price'])
            ->map(fn ($s) => ['plan' => trim((string) $s->description), 'precio' => (float) $s->price])
            ->filter(fn ($s) => $s['plan'] !== '')
            ->values()
            ->all();

        $saldo = DB::table('balances')
            ->where('balanceable_id', $clientId)
            ->where('balanceable_type', \App\Models\Client::class)
            ->value('amount');

        $fechas = DB::table('clients')->where('id', $clientId)->first(['fecha_pago', 'fecha_corte']);

        $referencia = class_exists(\App\Modules\Addons\Payments\Services\PaymentReferenceService::class)
            ? \App\Modules\Addons\Payments\Services\PaymentReferenceService::build($clientId)
            : null;

        return [
            'estado'      => $estado,
            'servicios'   => $servicios,
            'saldo'       => round((float) $saldo, 2),
            'fecha_pago'  => self::soloFecha($fechas->fecha_pago ?? null),
            'fecha_corte' => self::soloFecha($fechas->fecha_corte ?? null),
            'referencia'  => $referencia,
        ];
    }

    /** «2026-11-30 23:59:59» → «2026-11-30». Las fechas de `clients` son texto (legado). */
    public static function soloFecha(?string $valor): ?string
    {
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', trim((string) $valor), $m) ? $m[1] : null;
    }

    /** «MARÍA  JOSÉ» → «María». Para el saludo: el primer nombre, sin gritarlo. */
    public static function nombreDePila(string $nombre): string
    {
        $primero = preg_split('/\s+/', trim($nombre))[0] ?? '';

        return $primero === '' ? '' : mb_convert_case(mb_strtolower($primero), MB_CASE_TITLE);
    }

    private function activeServices(int $clientId): array
    {
        return DB::table('client_internet_services')
            ->where('client_id', $clientId)
            ->select('id', 'description', 'amount', 'unity', 'price', 'start_date')
            ->limit(5)
            ->get()
            ->map(fn ($s) => (array) $s)
            ->toArray();
    }
}
