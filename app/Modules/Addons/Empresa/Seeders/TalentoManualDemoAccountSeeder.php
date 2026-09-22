<?php

namespace App\Modules\Addons\Empresa\Seeders;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Services\Security\PasswordService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cuenta DE PRUEBA dedicada, exclusiva para tomar capturas de pantalla del manual
 * de usuario (Portal de Colaborador) — NUNCA la cuenta de un colaborador real.
 * Se creó a propósito para no volver a repetir el incidente del item de
 * seguimiento "no cambiar password real para probar" (ver memoria de sesión).
 *
 * Idempotente: buscar por login_user antes de crear cualquier fila.
 */
class TalentoManualDemoAccountSeeder extends Seeder
{
    public const LOGIN = 'manual_demo_tecnico';
    public const PASSWORD = 'ManualDemo2026!';

    public function run(): void
    {
        $user = User::firstOrCreate(
            ['login_user' => self::LOGIN],
            [
                'name' => 'Demo',
                'father_last_name' => 'Manual',
                'mother_last_name' => 'Colaborador',
                'email' => 'manual-demo-tecnico@meganet.local',
                'password' => PasswordService::make(self::PASSWORD),
                'active' => 1,
                'estado' => 'activo',
                'color' => '#0d9488',
            ]
        );

        // assignRole() es aditivo/idempotente en Spatie (no duplica ni toca otros roles) —
        // JAMÁS syncRoles aquí, sería destructivo si el usuario ya existiera.
        if (!$user->hasRole('TECNICO')) {
            $user->assignRole('TECNICO');
        }
        if (!$user->hasRole('Vendedor')) {
            $user->assignRole('Vendedor');
        }
        // Blindaje: id reciclado de un usuario borrado puede traer asignaciones huérfanas en
        // model_has_roles (deuda ya documentada aparte, "~14 asignaciones huérfanas del rol
        // client") — nunca debe colarse en una cuenta nueva de campo.
        if ($user->hasRole('client')) {
            $user->removeRole('client');
        }

        // Eloquent::create(), NO DB::table()->insert() — el alta real de un colaborador dispara
        // TalentoColaboradorObserver (portal.colaborador + permisos de "Mis documentos" +
        // generación del paquete de documentos), que un insert crudo se saltaría.
        $colaboradorModel = TalentoColaborador::where('user_id', $user->id)->first();
        if (!$colaboradorModel) {
            $colaboradorModel = TalentoColaborador::create([
                'user_id' => $user->id,
                'type' => 'interno',
                'department' => 'Campo',
                'job_title' => 'Técnico de campo (cuenta demo)',
                'hire_date' => now()->subMonths(6)->toDateString(),
                'status' => 'active',
            ]);
        }
        $colaboradorId = $colaboradorModel->id;

        $seller = DB::table('sellers')->where('user_id', $user->id)->first();
        if (!$seller) {
            DB::table('sellers')->insert([
                'user_id' => $user->id,
                'status_id' => 1,
                'balance' => 0,
                'range' => 'Cobre',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $client = DB::table('clients')->orderBy('id')->first();

        if ($client && DB::table('talento_work_orders')->where('colaborador_id', $colaboradorId)->count() === 0) {
            DB::table('talento_work_orders')->insert([
                [
                    'colaborador_id' => $colaboradorId,
                    'type_id' => 1, // Instalación nueva
                    'points' => 9,
                    'is_billable' => 1,
                    'client_id' => $client->id,
                    'status' => 'pending',
                    'scheduled_at' => now()->setTime(10, 0),
                    'notes' => 'Orden de ejemplo (cuenta demo del manual de usuario).',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'colaborador_id' => $colaboradorId,
                    'type_id' => 2, // Soporte/reparación
                    'points' => 3,
                    'is_billable' => 1,
                    'client_id' => $client->id,
                    'status' => 'in_progress',
                    'scheduled_at' => now()->setTime(13, 30),
                    'notes' => 'Orden de ejemplo (cuenta demo del manual de usuario).',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        $itemHerramienta = DB::table('inventory_items')->where('name', 'like', '%TALADRO%')->first()
            ?? DB::table('inventory_items')->first();
        $itemMaterial = DB::table('inventory_items')
            ->join('inventory_item_types', 'inventory_items.inventory_item_type_id', '=', 'inventory_item_types.id')
            ->where('inventory_item_types.categoria', 'material')
            ->select('inventory_items.*')
            ->first();

        $existingStock = DB::table('inventory_item_stocks')
            ->where('modelable_type', User::class)
            ->where('modelable_id', $user->id)
            ->count();

        if ($existingStock === 0) {
            $rows = [];
            foreach ([$itemHerramienta, $itemMaterial] as $item) {
                if (!$item) {
                    continue;
                }
                $rows[] = [
                    'inventory_item_id' => $item->id,
                    'modelable_type' => User::class,
                    'modelable_id' => $user->id,
                    'current_stock' => 1,
                    'condition' => 'used',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows) {
                DB::table('inventory_item_stocks')->insert($rows);
            }
        }
    }
}
