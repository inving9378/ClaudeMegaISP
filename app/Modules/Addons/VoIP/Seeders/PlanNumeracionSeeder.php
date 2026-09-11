<?php

namespace App\Modules\Addons\VoIP\Seeders;

use App\Modules\Addons\VoIP\Models\PerfilExtension;
use App\Modules\Addons\VoIP\Models\RangoNumeracion;
use Illuminate\Database\Seeder;

/**
 * Plan de numeración estándar: ocho perfiles y nueve rangos (#9990718 §7).
 *
 * Que la instalación no quede con una pantalla vacía de extensiones, sino con la
 * estructura de la empresa ya definida.
 *
 * **Crea el PLAN, no las extensiones.** Dar de alta setecientos endpoints vacíos
 * ensucia la base, vuelve inútil la pantalla de extensiones y consume recursos
 * por nada. Las extensiones se crean cuando alguien dice cuántas necesita.
 *
 * IDEMPOTENTE: se resuelve por `codigo`, nunca por id, y no pisa lo que ya existe.
 * Correrlo dos veces no duplica ni modifica nada.
 */
class PlanNumeracionSeeder extends Seeder
{
    /**
     * Los ocho perfiles, con sus diferencias explícitas.
     *
     * `internacional` y `premium` van en false en TODOS salvo donde el plan lo
     * pide configurable: son los destinos donde cobra el fraude telefónico.
     */
    public const PERFILES = [
        ['codigo' => 'oficina',       'nombre' => 'Oficina',
         'descripcion' => 'Personal fijo de oficina y administración. El perfil base.',
         'permite_entrantes_exterior' => true,  'graba_llamadas' => false],

        ['codigo' => 'atencion',      'nombre' => 'Atención a clientes',
         'descripcion' => 'Mostrador y atención. Con grabación activa por omisión.',
         'permite_entrantes_exterior' => true,  'graba_llamadas' => true],

        ['codigo' => 'tecnico_campo', 'nombre' => 'Técnico de campo',
         'descripcion' => 'Softphone móvil. Entrantes del exterior configurable por extensión.',
         'permite_entrantes_exterior' => false, 'graba_llamadas' => false],

        ['codigo' => 'ventas',        'nombre' => 'Ventas y cobranza',
         'descripcion' => 'Origen del dialer saliente. Con grabación.',
         'permite_entrantes_exterior' => true,  'graba_llamadas' => true],

        ['codigo' => 'direccion',     'nombre' => 'Gerencia y dirección',
         'descripcion' => 'Sin grabación por omisión. Internacional se habilita por excepción.',
         'permite_entrantes_exterior' => true,  'graba_llamadas' => false],

        ['codigo' => 'dispositivo',   'nombre' => 'Dispositivo o sala',
         'descripcion' => 'Recepción, salas, porteros y faxes. No recibe del exterior.',
         'permite_entrantes_exterior' => false, 'graba_llamadas' => false],

        ['codigo' => 'grupo',         'nombre' => 'Grupo o cola',
         'descripcion' => 'Colas y grupos de timbrado. La grabación la hereda de sus miembros.',
         'permite_entrantes_exterior' => true,  'graba_llamadas' => false],

        ['codigo' => 'sistema',       'nombre' => 'Sistema (reservado)',
         'descripcion' => 'Enlaces a centrales externas, troncales internas y servicios. No es para personas.',
         'permite_entrantes_exterior' => false, 'graba_llamadas' => false],
    ];

    /** Los nueve rangos. El 1700–1899 no lleva perfil: es reserva sin uso asignado. */
    public const RANGOS = [
        ['codigo' => 'oficinas',    'desde' => '1000', 'hasta' => '1099', 'perfil' => 'oficina',
         'nombre' => 'Oficinas y administración', 'proposito' => 'El grueso del personal fijo', 'protegido' => false],

        ['codigo' => 'atencion',    'desde' => '1100', 'hasta' => '1199', 'perfil' => 'atencion',
         'nombre' => 'Atención a clientes y mostrador', 'proposito' => 'Con grabación activa por omisión', 'protegido' => false],

        ['codigo' => 'tecnicos',    'desde' => '1200', 'hasta' => '1299', 'perfil' => 'tecnico_campo',
         'nombre' => 'Técnicos de campo', 'proposito' => 'Perfil de softphone móvil', 'protegido' => false],

        ['codigo' => 'ventas',      'desde' => '1300', 'hasta' => '1399', 'perfil' => 'ventas',
         'nombre' => 'Ventas y cobranza', 'proposito' => 'Origen del dialer saliente', 'protegido' => false],

        ['codigo' => 'direccion',   'desde' => '1400', 'hasta' => '1499', 'perfil' => 'direccion',
         'nombre' => 'Gerencia y dirección', 'proposito' => 'Sin grabación por omisión', 'protegido' => false],

        ['codigo' => 'dispositivos','desde' => '1500', 'hasta' => '1599', 'perfil' => 'dispositivo',
         'nombre' => 'Recepción, salas, dispositivos y servicios internos', 'proposito' => 'Porteros, faxes y salas', 'protegido' => false],

        ['codigo' => 'grupos',      'desde' => '1600', 'hasta' => '1699', 'perfil' => 'grupo',
         'nombre' => 'Grupos y colas', 'proposito' => 'Colas de atención y grupos de timbrado', 'protegido' => false],

        ['codigo' => 'reserva',     'desde' => '1700', 'hasta' => '1899', 'perfil' => null,
         'nombre' => 'Reserva para crecimiento', 'proposito' => 'Sin uso asignado todavía', 'protegido' => false],

        // El único protegido. Ahí viven los enlaces del sistema: una extensión de
        // persona aquí rompe la telefonía de la propia instalación.
        ['codigo' => 'sistema',     'desde' => '1900', 'hasta' => '1999', 'perfil' => 'sistema',
         'nombre' => 'Sistema, reservado y protegido', 'proposito' => 'Enlaces a PBX externo, troncales internas y servicios', 'protegido' => true],
    ];

    public function run(): void
    {
        $perfiles = $this->sembrarPerfiles();
        $this->sembrarRangos($perfiles);
    }

    /** @return array<string,int> codigo => id */
    private function sembrarPerfiles(): array
    {
        $ids = [];
        $nuevos = 0;

        foreach (self::PERFILES as $p) {
            $existente = PerfilExtension::porCodigo($p['codigo'])->first();

            if ($existente) {
                // No se pisa: si alguien ajustó el perfil desde la UI, su cambio manda.
                $ids[$p['codigo']] = $existente->id;
                continue;
            }

            $perfil = PerfilExtension::create([
                'codigo'                     => $p['codigo'],
                'nombre'                     => $p['nombre'],
                'descripcion'                => $p['descripcion'],
                'permite_nacional_fijo'      => true,
                'permite_nacional_movil'     => true,
                'permite_internacional'      => false,   // fraude: nunca por omisión
                'permite_premium'            => false,   // ídem
                'permite_entrantes_exterior' => $p['permite_entrantes_exterior'],
                'graba_llamadas'             => $p['graba_llamadas'],
                'codecs'                     => ['alaw', 'ulaw'],
                'es_plantilla_sistema'       => true,
            ]);

            $ids[$p['codigo']] = $perfil->id;
            $nuevos++;
        }

        $this->command?->info("  perfiles: {$nuevos} creados, " . (count(self::PERFILES) - $nuevos) . ' ya existían');

        return $ids;
    }

    private function sembrarRangos(array $perfiles): void
    {
        $nuevos = 0;

        foreach (self::RANGOS as $i => $r) {
            if (RangoNumeracion::porCodigo($r['codigo'])->exists()) {
                continue;
            }

            RangoNumeracion::create([
                'codigo'                   => $r['codigo'],
                'nombre'                   => $r['nombre'],
                'proposito'                => $r['proposito'],
                'desde'                    => $r['desde'],
                'hasta'                    => $r['hasta'],
                'voip_perfil_extension_id' => $r['perfil'] ? ($perfiles[$r['perfil']] ?? null) : null,
                'protegido'                => $r['protegido'],
                'orden'                    => ($i + 1) * 10,
                'activo'                   => true,
                'es_plantilla_sistema'     => true,
            ]);

            $nuevos++;
        }

        $this->command?->info("  rangos:   {$nuevos} creados, " . (count(self::RANGOS) - $nuevos) . ' ya existían');
    }
}
