<?php

namespace Tests\Feature\Portal;

use App\Modules\Addons\PortalCliente\Models\PortalClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesApplication;

/**
 * Clase base para tests del Portal Cliente.
 *
 * USA DatabaseTransactions en lugar de RefreshDatabase para NO destruir la BD productiva.
 * Cada test inserta datos y al terminar la transacción se hace rollback automático.
 *
 * ADVERTENCIA: NO correr con APP_ENV=production ni apuntando a la BD de producción
 * sin verificar que DATABASE_TRANSACTIONS está activo. El setUp no llama migrate:fresh.
 */
abstract class PortalTestCase extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin migrate:fresh — el esquema ya existe en BD. DatabaseTransactions rollback al finalizar.
    }

    // ── Helpers para crear clientes de prueba ────────────────────────────

    /**
     * Crea una fila en `clients` + `client_main_information` con portal_password.
     * Retorna el modelo PortalClient listo para actingAs(..., 'cliente').
     * Todo se revierte al final del test (DatabaseTransactions).
     */
    protected function crearClientePortal(array $overrides = []): PortalClient
    {
        // Insertamos el cliente base (campos mínimos NOT NULL)
        $clientId = DB::table('clients')->insertGetId([
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $defaults = [
            'client_id'          => $clientId,
            'name'               => 'TestCliente' . $clientId,
            'father_last_name'   => 'Prueba',
            'phone'              => '5500000000',
            'portal_password'    => Hash::make('TestPassword123!'),
            'portal_registered_at' => now(),
            'created_at'         => now(),
            'updated_at'         => now(),
        ];

        $cmiId = DB::table('client_main_information')->insertGetId(
            array_merge($defaults, $overrides)
        );

        return PortalClient::find($cmiId);
    }

    /**
     * Crea una factura para un client_id dado.
     */
    protected function crearFactura(int $clientId, array $extra = []): int
    {
        return DB::table('client_invoices')->insertGetId(array_merge([
            'client_id'     => $clientId,
            'number'        => rand(100000, 999999),
            'total'         => 399.00,
            'estado'        => 'Pendiente',
            'added_by'      => 1,
            'document_date' => '01/06/2026',
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $extra));
    }

    /**
     * Crea un pago para un client_id dado (polymorphic).
     * payments no tiene created_by/updated_by — solo los campos del schema real.
     */
    protected function crearPago(int $clientId, array $extra = []): int
    {
        return DB::table('payments')->insertGetId(array_merge([
            'paymentable_id'    => $clientId,
            'paymentable_type'  => 'App\\Models\\Client',
            'amount'            => 399.00,
            'date'              => '01/06/2026',
            'comment'           => 'Pago test',
            'payment_method_id' => 1,
            'add_by'            => 1,
            'receipt'           => 'TEST-' . uniqid(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $extra));
    }

    /**
     * Crea un ticket para un client_id dado.
     * priority es int (1=low,2=normal,3=high); estado ENUM con valores fijos.
     */
    protected function crearTicket(int $clientId, array $extra = []): int
    {
        return DB::table('tickets')->insertGetId(array_merge([
            'customer_lead' => $clientId,
            'topic'         => 'Test ticket ' . $clientId,
            'priority'      => 2,
            'estado'        => 'Nuevo',
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $extra));
    }

    /**
     * Crea registros de consumo para un client_id dado (patrón Meganet{id}).
     * session_id es NOT NULL sin default — usar UUID para no colisionar.
     */
    protected function crearConsumo(int $clientId, string $patron = 'Meganet'): void
    {
        DB::table('internet_consumptions')->insert([
            'client_name'   => $patron . $clientId,
            'session_id'    => 'test-' . uniqid(),
            'bytes_in'      => 1073741824,
            'bytes_out'     => 536870912,
            'date_recorded' => now()->toDateTimeString(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Crea CLABE para un client_id dado.
     */
    protected function crearClabe(int $clientId, string $clabe = '646180111800000001'): int
    {
        return DB::table('payment_clabes')->insertGetId([
            'client_id'  => $clientId,
            'clabe'      => $clabe,
            'is_active'  => 1,
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
