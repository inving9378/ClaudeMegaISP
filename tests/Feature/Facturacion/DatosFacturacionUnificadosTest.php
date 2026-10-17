<?php

namespace Tests\Feature\Facturacion;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\CreatesApplication;

/**
 * FR-01 (#535): «Dirección de facturación» se copia a «Datos de facturación» solo en campos vacíos,
 * sin pisar lo distinto, y el diagnóstico lista los choques. BD de pruebas en transacción revertida;
 * billing_addresses y client_fiscal_data son TEMPORARY (tapan a las reales solo en esta conexión).
 */
class DatosFacturacionUnificadosTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private string $migracion = __DIR__ . '/../../../database/migrations/2026_10_17_100000_unifica_datos_de_facturacion.php';

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE TEMPORARY TABLE billing_addresses (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT NOT NULL, billing_name VARCHAR(255) NULL, billing_street VARCHAR(255) NULL,
            billing_zip_code VARCHAR(255) NULL, billing_city VARCHAR(255) NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL)');
        DB::statement('CREATE TEMPORARY TABLE client_fiscal_data (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL UNIQUE, facturacion_fiscal TINYINT(1) NOT NULL DEFAULT 0,
            razon_social VARCHAR(250) NULL, rfc VARCHAR(13) NULL, codigo_postal_fiscal VARCHAR(5) NULL,
            regimen_fiscal VARCHAR(4) NULL, uso_cfdi VARCHAR(4) NULL, correo_fiscal VARCHAR(200) NULL,
            calle VARCHAR(255) NULL, ciudad VARCHAR(150) NULL, constancia_path VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0, updated_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL)');
    }

    private function cliente(): int
    {
        $u = (int) DB::table('users')->value('id');

        return DB::table('clients')->insertGetId(['created_by' => $u, 'updated_by' => $u, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function direccion(int $cliente, array $d): void
    {
        DB::table('billing_addresses')->insert($d + ['client_id' => $cliente, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function copiar(): void
    {
        $m = require $this->migracion;
        (fn () => $this->copiarDireccionesDeFacturacion())->call($m);
    }

    public function test_sin_datos_de_facturacion_se_crean_desde_la_direccion_como_publico_en_general(): void
    {
        $c = $this->cliente();
        $this->direccion($c, ['billing_name' => 'Juan Pérez', 'billing_street' => 'Av. Uno 10', 'billing_zip_code' => ' 55790 ', 'billing_city' => 'Tultitlán']);

        $this->copiar();

        $f = DB::table('client_fiscal_data')->where('client_id', $c)->first();
        $this->assertSame(0, (int) $f->facturacion_fiscal);
        $this->assertSame('Juan Pérez', $f->razon_social);
        $this->assertSame('Av. Uno 10', $f->calle);
        $this->assertSame('55790', $f->codigo_postal_fiscal);
        $this->assertSame('Tultitlán', $f->ciudad);
        $this->assertNull($f->rfc);
    }

    public function test_solo_llena_vacios_y_no_pisa_lo_distinto(): void
    {
        $c = $this->cliente();
        DB::table('client_fiscal_data')->insert(['client_id' => $c, 'facturacion_fiscal' => 1, 'razon_social' => 'ACME SA DE CV',
            'rfc' => 'AAA010101AAA', 'codigo_postal_fiscal' => '01000', 'created_at' => now(), 'updated_at' => now()]);
        $this->direccion($c, ['billing_name' => 'Otro nombre', 'billing_street' => 'Calle 2', 'billing_zip_code' => '55790', 'billing_city' => 'Cuautitlán']);

        $this->copiar();

        $f = DB::table('client_fiscal_data')->where('client_id', $c)->first();
        $this->assertSame('ACME SA DE CV', $f->razon_social, 'no se pisa la razón social');
        $this->assertSame('01000', $f->codigo_postal_fiscal, 'no se pisa el C.P.');
        $this->assertSame('Calle 2', $f->calle, 'calle vacía se llena');
        $this->assertSame('Cuautitlán', $f->ciudad);
        $this->assertSame(1, (int) $f->facturacion_fiscal);
    }

    public function test_cp_invalido_no_se_copia_y_gana_la_direccion_mas_reciente(): void
    {
        $c = $this->cliente();
        $this->direccion($c, ['billing_name' => 'Viejo', 'billing_zip_code' => '11111']);
        $this->direccion($c, ['billing_name' => 'Nuevo', 'billing_zip_code' => '5579']);

        $this->copiar();

        $f = DB::table('client_fiscal_data')->where('client_id', $c)->first();
        $this->assertSame('Nuevo', $f->razon_social);
        $this->assertNull($f->codigo_postal_fiscal);
    }

    public function test_es_idempotente_y_omite_direcciones_vacias(): void
    {
        $a = $this->cliente();
        $b = $this->cliente();
        $this->direccion($a, ['billing_name' => 'Ana']);
        $this->direccion($b, ['billing_name' => '  ', 'billing_street' => '']);

        $this->copiar();
        $this->copiar();

        $this->assertSame(1, DB::table('client_fiscal_data')->where('client_id', $a)->count());
        $this->assertSame(0, DB::table('client_fiscal_data')->where('client_id', $b)->count());
    }

    public function test_el_diagnostico_lista_choques_y_cp_invalidos_sin_escribir(): void
    {
        $c = $this->cliente();
        DB::table('client_fiscal_data')->insert(['client_id' => $c, 'razon_social' => 'ACME SA', 'created_at' => now(), 'updated_at' => now()]);
        $this->direccion($c, ['billing_name' => 'Otro nombre', 'billing_zip_code' => '12']);
        $antes = DB::table('client_fiscal_data')->where('client_id', $c)->first();

        Artisan::call('facturacion:datos-fiscales-diagnostico', ['--todos' => true]);
        $salida = Artisan::output();

        $this->assertStringContainsString('2 casos para revisar', $salida);
        $this->assertStringContainsString('Otro nombre', $salida);
        $this->assertStringContainsString('ACME SA', $salida);
        $this->assertStringContainsString('C.P. que no es de 5 dígitos', $salida);
        $this->assertEquals($antes, DB::table('client_fiscal_data')->where('client_id', $c)->first(), 'solo lectura');
    }
}
