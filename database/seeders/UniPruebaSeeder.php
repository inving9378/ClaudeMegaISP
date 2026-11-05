<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Referrals\Referral;
use App\Models\Seller;
use App\Models\Ticket;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Modules\Addons\Talento\Models\TalentoActivityReportParticipant;
use App\Modules\Addons\Talento\Models\TalentoActivityType;
use App\Modules\Addons\Talento\Models\TalentoProject;
use App\Modules\Addons\Talento\Models\TalentoProjectActivity;
use App\Modules\Addons\Talento\Models\TalentoProjectActivityReport;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderMedia;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderSignature;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Perfiles ficticios para las pruebas de la épica UNI (#865). SOLO megaisp_test.
 * Todo lleva el prefijo UNI-PRUEBA en name/login_user. Idempotente (updateOrCreate).
 * Roles por assignRole puntual, jamás sync.
 */
class UniPruebaSeeder extends Seeder
{
    public const PREFIJO = 'UNI-PRUEBA';

    /** Lo enciende uni:casos-prueba para sembrar en DEV; por defecto solo megaisp_test. */
    public static bool $permitirDev = false;

    // clave => [rol Spatie, departamento, supervisor (clave)]
    private const PERFILES = [
        'direccion'    => ['DESARROLLADOR', 'Direccion', null],
        'supervisor'   => ['SUPERVISOR_MOSTRADOR', 'Operaciones', 'direccion'],
        'subordinado1' => ['TECNICO', 'Operaciones', 'supervisor'],
        'subordinado2' => ['TECNICO', 'Operaciones', 'supervisor'],
        'tecnico1'     => ['TECNICO', 'Campo', 'direccion'],
        'tecnico2'     => ['TECNICO', 'Campo', 'direccion'],
        'mostrador'    => ['Mostrador', 'Mostrador', 'direccion'],
        'vendedor'     => ['Vendedor', 'Ventas', 'direccion'],
        'almacen'      => ['Almacen', 'Almacen', 'direccion'],
    ];

    public function run(): void
    {
        $colabs = $this->perfiles();
        $this->ordenes($colabs);
        $this->ventas($colabs);
    }

    /** @return array<string, TalentoColaborador> */
    public function perfiles(): array
    {
        $db = DB::connection()->getDatabaseName();
        if ($db !== 'megaisp_test' && ! (self::$permitirDev && app()->environment('local') && $db === config('database.connections.mysql.database'))) {
            throw new \RuntimeException("UniPruebaSeeder solo corre en megaisp_test (DB actual: {$db}).");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $colabs = [];

        foreach (self::PERFILES as $clave => [$rol, $depto, $sup]) {
            $login = self::PREFIJO . '-' . $clave;
            $user = User::updateOrCreate(['login_user' => $login], [
                'name'     => self::PREFIJO . ' ' . ucfirst($clave),
                'email'    => strtolower($login) . '@uni-prueba.invalid',
                'password' => Hash::make(bin2hex(random_bytes(8))),
                'estado'   => 'activo',
                'active'   => 1,
            ]);

            if ($role = Role::where('name', $rol)->first()) {
                if (! $user->hasRole($rol)) {
                    $user->assignRole($role);
                }
            }

            $colabs[$clave] = TalentoColaborador::updateOrCreate(['user_id' => $user->id], [
                'type'          => 'interno',
                'department'    => $depto,
                'status'        => 'active',
                'supervisor_id' => $sup ? $colabs[$sup]->id : null,
            ]);
        }

        return $colabs;
    }

    /**
     * Órdenes de trabajo ficticias con evidencias, firmas y participantes de actividad.
     * Idempotente: cada fila se identifica por su nota con el prefijo UNI-PRUEBA.
     * Sin ledger ni montos reales; las rutas de archivo son marcadores, no existen en disco.
     *
     * @param  array<string, TalentoColaborador>  $colabs
     * @return array<string, mixed>
     */
    public function ordenes(array $colabs): array
    {
        $tipo = TalentoWorkOrderType::firstOrCreate(
            ['name' => self::PREFIJO . ' Instalacion'],
            ['category' => 'instalacion', 'points' => 9, 'is_billable' => true, 'requires_validation' => true, 'active' => true]
        );
        $admin = $colabs['direccion']->user_id;
        $now = now();

        $orden = fn (string $marca, string $clave, string $estado, array $extra = []) => TalentoWorkOrder::updateOrCreate(
            ['notes' => self::PREFIJO . ' ' . $marca],
            array_merge([
                'colaborador_id' => $colabs[$clave]->id,
                'type_id'        => $tipo->id,
                'points'         => $tipo->points,
                'is_billable'    => true,
                'assigned_by'    => $admin,
                'status'         => $estado,
                'scheduled_at'   => $now,
            ], $extra)
        );

        $pendiente = $orden('orden pendiente', 'tecnico1', 'pending');
        $curso = $orden('orden en curso', 'tecnico1', 'in_progress', ['started_at' => $now]);
        $completada = $orden('orden completada', 'tecnico2', 'completed', ['started_at' => $now, 'completed_at' => $now]);
        $validada = $orden('orden validada', 'tecnico2', 'validated', [
            'started_at' => $now, 'completed_at' => $now, 'validated_at' => $now, 'validated_by' => $admin,
        ]);

        // Evidencias (marcadores de ruta) y firmas en la orden validada
        foreach (['presentation', 'completion'] as $t) {
            TalentoWorkOrderMedia::updateOrCreate(
                ['work_order_id' => $validada->id, 'type' => $t],
                ['file_path' => "uni-prueba/{$validada->id}/{$t}.jpg", 'captured_at' => $now, 'captured_in_app' => true]
            );
        }
        foreach (['technician', 'client'] as $quien) {
            TalentoWorkOrderSignature::updateOrCreate(
                ['work_order_id' => $validada->id, 'signer_type' => $quien],
                ['signature_path' => "uni-prueba/{$validada->id}/firma-{$quien}.png", 'signed_at' => $now]
            );
        }

        // Participantes: un reporte de actividad compartido por los dos técnicos
        $actTipo = TalentoActivityType::firstOrCreate(
            ['name' => self::PREFIJO . ' Actividad'],
            ['unit' => 'm', 'points_per_unit' => 0, 'money_per_unit' => 0, 'active' => true]
        );
        $proyecto = TalentoProject::firstOrCreate(
            ['name' => self::PREFIJO . ' Proyecto'],
            ['status' => 'active', 'lead_colaborador_id' => $colabs['supervisor']->id]
        );
        $actividad = TalentoProjectActivity::firstOrCreate(
            ['project_id' => $proyecto->id, 'activity_type_id' => $actTipo->id],
            ['planned_quantity' => 100]
        );
        $reporte = TalentoProjectActivityReport::updateOrCreate(
            ['project_activity_id' => $actividad->id, 'notes' => self::PREFIJO . ' reporte'],
            ['reported_by' => $colabs['tecnico1']->user_id, 'quantity' => 40, 'report_date' => $now->toDateString(), 'status' => 'pending']
        );
        foreach (['tecnico1', 'tecnico2'] as $c) {
            TalentoActivityReportParticipant::updateOrCreate(
                ['report_id' => $reporte->id, 'colaborador_id' => $colabs[$c]->id],
                ['quantity_share' => 20, 'points_earned' => 0]
            );
        }

        return compact('pendiente', 'curso', 'completada', 'validada', 'reporte');
    }

    /**
     * Ticket, venta (vendedor), referido y liquidaciones ficticios. Idempotente.
     * Sin facturas, comisiones ni ledger: las liquidaciones llevan montos en cero.
     *
     * @param  array<string, TalentoColaborador>  $colabs
     * @return array<string, mixed>
     */
    public function ventas(array $colabs): array
    {
        $admin = $colabs['direccion']->user_id;

        $ticket = Ticket::updateOrCreate(
            ['topic' => self::PREFIJO . ' ticket'],
            ['estado' => 'Nuevo', 'type' => 'Incidente', 'priority' => 1, 'assigned_to' => $colabs['mostrador']->user_id, 'hidden' => 0]
        );

        $vendedor = Seller::firstOrCreate(['user_id' => $colabs['vendedor']->user_id], ['balance' => 0]);

        // Clientes ficticios (marcados is_test_data): embajador y referido
        $cliente = function (string $marca) use ($admin) {
            $id = DB::table('clients')->where('referred_by_code', self::PREFIJO . '-' . $marca)->value('id');
            if (! $id) {
                $id = DB::table('clients')->insertGetId([
                    'referred_by_code' => self::PREFIJO . '-' . $marca,
                    'is_test_data'     => 1,
                    'created_by'       => $admin,
                    'updated_by'       => $admin,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            return $id;
        };
        $embajador = $cliente('embajador');
        $referido = $cliente('referido');

        $referral = Referral::updateOrCreate(
            ['embajador_id' => $embajador, 'referred_client_id' => $referido],
            ['chain_path' => (string) $embajador, 'chain_depth' => 1, 'status' => 'pending_threshold']
        );

        // Liquidaciones: una abierta y una cerrada, semanas distintas, montos en cero
        $liq = fn (string $ini, string $fin, string $estado) => TalentoLiquidation::updateOrCreate(
            ['colaborador_id' => $colabs['tecnico2']->id, 'period_start' => $ini, 'period_end' => $fin],
            ['total_units' => 0, 'base_paid' => 0, 'overproduction_paid' => 0, 'other_credits' => 0,
             'other_debits' => 0, 'gross_pay' => 0, 'status' => $estado, 'closed_at' => $estado === 'closed' ? now() : null]
        );
        $abierta = $liq('2026-01-03', '2026-01-10', 'draft');
        $cerrada = $liq('2025-12-27', '2026-01-03', 'closed');

        return compact('ticket', 'vendedor', 'referral', 'abierta', 'cerrada');
    }
}
