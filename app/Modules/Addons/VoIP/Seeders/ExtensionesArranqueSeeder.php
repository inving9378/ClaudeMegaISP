<?php

namespace App\Modules\Addons\VoIP\Seeders;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\RangoNumeracion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Extensiones de arranque, por departamento (#9990718 §8).
 *
 * **Sin nombres de personas.** Cada extensión se siembra identificada por el
 * departamento que la usará —`Administración 01`, `Técnicos de campo 03`—, no por
 * quién la usa. Los nombres se asignan después desde la interfaz, cuando cada
 * quien tome la suya. Sembrar nombres de personas obliga a mantener una lista de
 * empleados dentro de un seeder, que envejece mal y no le sirve a ninguna otra
 * instalación.
 *
 * 30 en total: suficiente para arrancar sin llenar la pantalla de registros vacíos.
 * Las cantidades son parametrizables.
 *
 * IDEMPOTENTE: no pisa ninguna extensión que ya exista con ese número. Si la
 * encuentra, la deja como está y continúa.
 */
class ExtensionesArranqueSeeder extends Seeder
{
    /** rango => [departamento, cuántas]. Editable sin tocar el código de abajo. */
    public const PLAN = [
        'oficinas'     => ['Administración',       5],
        'atencion'     => ['Atención a clientes',  5],
        'tecnicos'     => ['Técnicos de campo',   10],
        'ventas'       => ['Ventas y cobranza',    5],
        'direccion'    => ['Dirección',            2],
        'dispositivos' => ['Recepción y dispositivos', 3],
    ];

    public function run(): void
    {
        $creadas = $omitidas = 0;
        $sinRango = [];

        foreach (self::PLAN as $codigoRango => [$departamento, $cuantas]) {
            $rango = RangoNumeracion::porCodigo($codigoRango)->first();

            if (! $rango) {
                // Sin el plan de numeración sembrado, no hay de dónde heredar el
                // perfil. Se avisa y se sigue: mejor sembrar parcialmente y
                // reportarlo que fallar entero.
                $sinRango[] = $codigoRango;
                continue;
            }

            // Empieza en desde+1: el primer número del rango se reserva por
            // convención para el grupo o la operadora del departamento.
            $base = (int) $rango->desde;

            for ($i = 1; $i <= $cuantas; $i++) {
                $numero = (string) ($base + $i);

                if (Extension::where('numero', $numero)->exists()) {
                    $omitidas++;
                    continue;
                }

                Extension::create([
                    'numero'                   => $numero,
                    'nombre'                   => sprintf('%s %02d', $departamento, $i),
                    'departamento'             => $departamento,
                    // Larga y aleatoria, distinta en cada una. Nunca `extensión + 1234`:
                    // una contraseña predecible en una extensión SIP es la puerta por
                    // la que entra el fraude telefónico, y con 30 sembradas basta con
                    // que una sea adivinable.
                    'secret'                   => Str::random(32),
                    'user_id'                  => null,   // se asigna cuando alguien la tome
                    'tipo_dispositivo'         => 'softphone',
                    'contexto'                 => 'from-internal',
                    'codecs'                   => 'alaw,ulaw',
                    // Del manifiesto, no literal: es el NOMBRE del objeto transport
                    // que declara pjsip.conf, no el protocolo. Escribir 'udp' aquí
                    // dejaba a Asterisk sin poder resolverlo y toda llamada moría
                    // con un 500, aunque el teléfono registrara sin problema.
                    'transporte'               => config('requisitos-voip.asterisk.transporte', 'transport-udp'),
                    'callerid'                 => $numero,
                    'activo'                   => true,
                    'provisionado_at'          => now(),
                    'voip_rango_numeracion_id' => $rango->id,
                    // Sin perfil propio: hereda el de su rango, que es el caso corriente.
                    'voip_perfil_extension_id' => null,
                    'sembrada_por_sistema'     => true,
                ]);

                $creadas++;
            }
        }

        $this->command?->info("  extensiones: {$creadas} creadas, {$omitidas} omitidas por existir");

        if ($sinRango) {
            $this->command?->warn('  ⚠ sin rango sembrado (corre antes PlanNumeracionSeeder): ' . implode(', ', $sinRango));
        }
    }
}
