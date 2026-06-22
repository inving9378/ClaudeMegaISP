<?php

namespace Tests\Feature\MegaFamilia;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;

/**
 * Anti-IDOR de la API móvil de MegaFamilia (/api/megafamilia/*).
 *
 * Verifica que el cliente A NO puede leer ni modificar device/tarea/perfil/solicitud
 * de otra cuenta (cliente B) conociendo su id: un id ajeno debe devolver 404, sin
 * revelar existencia. El dueño legítimo conserva su acceso (200/201).
 *
 * USA DatabaseTransactions (rollback automático al terminar cada test), NO migrate:fresh,
 * para NO destruir la BD productiva (ver feedback de proyecto). Ejecutar selectivo:
 *   php artisan test --filter=ApiIdorTest
 *
 * Cubre el hotfix de seguridad en ApiController: deviceRules, updateDeviceRules,
 * completeTask, storeRequest, respondRequest, reportLocation.
 */
class ApiIdorTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    /**
     * Crea un tenant completo (user + account + profile + device + task + request).
     * Todo se revierte al final del test.
     */
    private function crearTenant(string $sufijo): object
    {
        $userId = DB::table('users')->insertGetId([
            'name'       => 'IDOR Test ' . $sufijo,
            'login_user' => 'idor_' . $sufijo . '_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = DB::table('parental_accounts')->insertGetId([
            'user_id'    => $userId,
            'plan_id'    => 1,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileId = DB::table('parental_profiles')->insertGetId([
            'account_id' => $accountId,
            'name'       => 'Hijo ' . $sufijo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deviceId = DB::table('parental_devices')->insertGetId([
            'profile_id' => $profileId,
            'account_id' => $accountId,
            'name'       => 'Telefono ' . $sufijo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $taskId = DB::table('parental_tasks')->insertGetId([
            'profile_id' => $profileId,
            'title'      => 'Tarea ' . $sufijo,
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestId = DB::table('parental_requests')->insertGetId([
            'profile_id' => $profileId,
            'type'       => 'time_extra',
            'status'     => 'pending',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) [
            'user'    => User::find($userId),
            'profile' => $profileId,
            'device'  => $deviceId,
            'task'    => $taskId,
            'request' => $requestId,
        ];
    }

    private function actuarComo(object $tenant): void
    {
        Sanctum::actingAs($tenant->user, ['*']);
    }

    // ── deviceRules (GET /devices/{id}/rules) ────────────────────────────

    public function test_a_no_puede_leer_reglas_del_device_de_b(): void
    {
        $a = $this->crearTenant('A');
        $b = $this->crearTenant('B');
        $this->actuarComo($a);

        $this->getJson("/api/megafamilia/devices/{$b->device}/rules")->assertStatus(404);
        $this->getJson("/api/megafamilia/devices/{$a->device}/rules")->assertStatus(200);
    }

    // ── updateDeviceRules (PUT /devices/{id}/rules) ──────────────────────

    public function test_a_no_puede_modificar_reglas_del_device_de_b(): void
    {
        $a = $this->crearTenant('A');
        $b = $this->crearTenant('B');
        $this->actuarComo($a);

        $payload = ['daily_limit_minutes' => 30, 'internet_paused' => true];
        $this->putJson("/api/megafamilia/devices/{$b->device}/rules", $payload)->assertStatus(404);
        $this->putJson("/api/megafamilia/devices/{$a->device}/rules", $payload)->assertStatus(200);
    }

    // ── completeTask (POST /tasks/{id}/complete) ─────────────────────────

    public function test_a_no_puede_completar_la_tarea_de_b(): void
    {
        $a = $this->crearTenant('A');
        $b = $this->crearTenant('B');
        $this->actuarComo($a);

        $this->postJson("/api/megafamilia/tasks/{$b->task}/complete")->assertStatus(404);
        $this->postJson("/api/megafamilia/tasks/{$a->task}/complete")->assertStatus(200);
    }

    // ── respondRequest (POST /requests/{id}/respond) ─────────────────────

    public function test_a_no_puede_responder_la_solicitud_de_b(): void
    {
        $a = $this->crearTenant('A');
        $b = $this->crearTenant('B');
        $this->actuarComo($a);

        $this->postJson("/api/megafamilia/requests/{$b->request}/respond", ['status' => 'approved'])->assertStatus(404);
        $this->postJson("/api/megafamilia/requests/{$a->request}/respond", ['status' => 'approved'])->assertStatus(200);
    }

    // ── storeRequest (POST /requests) — perfil ajeno ─────────────────────

    public function test_a_no_puede_crear_solicitud_contra_el_perfil_de_b(): void
    {
        $a = $this->crearTenant('A');
        $b = $this->crearTenant('B');
        $this->actuarComo($a);

        $this->postJson('/api/megafamilia/requests', ['profile_id' => $b->profile, 'type' => 'time_extra'])->assertStatus(404);
        $this->postJson('/api/megafamilia/requests', ['profile_id' => $a->profile, 'type' => 'time_extra'])->assertStatus(201);
    }

    // ── reportLocation (POST /locations) — device ajeno ──────────────────

    public function test_a_no_puede_reportar_ubicacion_en_el_device_de_b(): void
    {
        $a = $this->crearTenant('A');
        $b = $this->crearTenant('B');
        $this->actuarComo($a);

        $coords = ['lat' => 19.4326, 'lng' => -99.1332];
        $this->postJson('/api/megafamilia/locations', ['device_id' => $b->device] + $coords)->assertStatus(404);
        $this->postJson('/api/megafamilia/locations', ['device_id' => $a->device] + $coords)->assertStatus(200);
    }
}
