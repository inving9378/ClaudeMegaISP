<?php

use App\Models\Core\ApiIntegration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Los proveedores del chat /ia (ia_proveedores) guardaban su PROPIA copia de la
 * llave: al cambiarla en Integraciones, el chat seguía usando la vieja. Aquí se
 * vinculan a la integración principal del Hub de su protocolo (config_extra.
 * hub_integracion, el mismo mecanismo del lote 6) y se borra la copia.
 *
 * Solo se toca la fila cuando su copia es IDÉNTICA a la llave del Hub: así el
 * comportamiento no cambia hoy y desde ahora la llave vive en un solo lugar.
 * Si difieren (o no se puede leer) la fila se deja tal cual. Idempotente.
 */
return new class extends Migration
{
    private const HUB_POR_DRIVER = ['claude' => 'anthropic', 'openai' => 'openai'];

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('ia_proveedores')) {
            return;
        }

        foreach (DB::table('ia_proveedores')->get() as $p) {
            $proveedorHub = self::HUB_POR_DRIVER[$p->driver] ?? null;
            $extra = json_decode($p->config_extra ?? 'null', true) ?: [];
            if (!$proveedorHub || !$p->api_key || !empty($extra['hub_integracion'])) {
                continue;
            }

            try {
                $copia = Crypt::decryptString($p->api_key);
            } catch (DecryptException) {
                continue;
            }

            $hub = ApiIntegration::where('company_id', 1)
                ->where('provider', $proveedorHub)
                ->where('is_default_for_provider', true)
                ->first();
            if (!$hub || $hub->value !== $copia) {
                continue;
            }

            $extra['hub_integracion'] = $hub->slug;
            DB::table('ia_proveedores')->where('id', $p->id)->update([
                'config_extra' => json_encode($extra, JSON_UNESCAPED_UNICODE),
                'api_key'      => null,
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Sin reversa: la llave sigue disponible en el Hub y la fila apunta a ella.
    }
};
