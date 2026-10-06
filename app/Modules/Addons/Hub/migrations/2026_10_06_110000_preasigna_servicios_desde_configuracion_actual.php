<?php

use App\Models\Core\ApiIntegration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Segunda pasada de "deja asignado lo que funciona hoy" (Módulos de servicios).
 * La primera solo asignaba lo que ya vivía en el Hub; casi todo lo que funciona
 * hoy vive en otro lado, así que la pestaña salía casi vacía:
 *
 * - Correo: la cuenta del servidor (.env) y la de Configuración → Correo se dan
 *   de alta en el Hub con las MISMAS credenciales y se asignan a sus módulos.
 * - Mapas: la llave de Configuración → Mapas (si el Hub ya la tiene, esa; si no,
 *   se da de alta) se asigna a "mapas.google".
 * - WhatsApp: los avisos que mandan directo y hoy salen por la instancia de
 *   Marketing se asignan a esa línea si existe como línea.
 *
 * El correo, los mapas y los WhatsApp salen exactamente igual que antes; solo
 * quedan visibles y editables en la pestaña. No pisa asignaciones existentes.
 */
return new class extends Migration
{
    private const CORREO_ENV = [
        'facturacion.correo_recibos', 'crm.correo', 'tickets.correo', 'domiciliacion.correo',
        'portal.correo_recibo', 'portalpago.resumen_correo', 'evaluador.correo', 'marketing.correo_masivo',
        'flotas.correo', 'talento.correo_vencimientos',
    ];

    private const CORREO_CONFIGURACION = ['facturacion.correos_sistema'];

    private const WHATSAPP_DIRECTO = [
        'referidos.avisos', 'portalpago.resumen_diario', 'warroom.minutas', 'warroom.acciones',
        'flotas.geocercas', 'flotas.documentos', 'flotas.suscripciones',
    ];

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('servicio_asignaciones')) {
            return;
        }

        // ── Correo del servidor (.env) ──────────────────────────────────────
        $env = config('mail.mailers.' . config('mail.default'));
        $cuentaEnv = null;
        if (($env['transport'] ?? null) === 'smtp' && !empty($env['host'])) {
            $cuentaEnv = $this->cuentaSmtp(
                'Correo del sistema (' . ($env['username'] ?: $env['host']) . ')',
                $env['host'], $env['port'] ?? 587, $env['encryption'] ?? null, $env['username'] ?? null,
                $env['password'] ?? null, config('mail.from.address'), config('mail.from.name'),
            );
            $this->asignarVarias(self::CORREO_ENV, ['api_integration_id' => $cuentaEnv]);
        }

        // ── Correo de Configuración → Correo (email_settings) ──────────────
        $es = DB::getSchemaBuilder()->hasTable('email_settings') ? DB::table('email_settings')->first() : null;
        if ($es && !empty($es->mail_host) && (($es->mail_mailer ?? 'smtp') ?: 'smtp') === 'smtp') {
            $cuentaConf = $this->cuentaSmtp(
                'Correo de Configuración (' . ($es->mail_username ?: $es->mail_host) . ')',
                $es->mail_host, $es->mail_port ?? 587, $es->mail_encryption ?? null, $es->mail_username ?? null,
                $es->mail_password ?? null, $es->mail_from_address ?? null, $es->mail_from_name ?? null,
            );
            $this->asignarVarias(self::CORREO_CONFIGURACION, ['api_integration_id' => $cuentaConf]);
        }

        // ── Mapas ───────────────────────────────────────────────────────────
        $llaveMapas = DB::getSchemaBuilder()->hasTable('system_map_credentials')
            ? DB::table('system_map_credentials')->value('api_key') : null;
        if ($llaveMapas) {
            $mapas = ApiIntegration::where('company_id', 1)->where('provider', 'google_maps')->where('active', true)
                ->get()->first(fn ($i) => $i->value === $llaveMapas);
            if (!$mapas) {
                $mapas = $this->integracion('google_maps', 'Google Maps (Configuración → Mapas)', $llaveMapas, []);
            }
            $this->asignarVarias(['mapas.google'], ['api_integration_id' => $mapas->id]);
        }

        // ── WhatsApp que hoy sale por la instancia de Marketing ─────────────
        try {
            $instancia = \App\Models\Marketing\Setting::get('evolution_instance_name', 1);
        } catch (\Throwable) {
            $instancia = null;
        }
        $linea = $instancia
            ? DB::table('whatsapp_instances')->where('slug', $instancia)->where('active', true)->value('id')
            : null;
        $principal = DB::table('whatsapp_instances')->where('active', true)->where('default_instance', true)->value('id');
        if ($linea && !$principal) {
            $this->asignarVarias(self::WHATSAPP_DIRECTO, ['whatsapp_instance_id' => $linea]);
        }
    }

    /** Reusa la cuenta si ya está en el Hub (mismo servidor y usuario); si no, la da de alta. */
    private function cuentaSmtp(string $nombre, string $host, $puerto, ?string $cifrado, ?string $usuario, ?string $clave, ?string $de, ?string $deNombre): int
    {
        $existente = ApiIntegration::where('company_id', 1)->where('provider', 'smtp')->get()
            ->first(fn ($i) => ($i->config['host'] ?? null) === $host && ($i->config['username'] ?? null) === $usuario);
        if ($existente) {
            return $existente->id;
        }

        return $this->integracion('smtp', $nombre, $clave, [
            'host'         => $host,
            'port'         => (int) $puerto,
            'encryption'   => in_array($cifrado, ['tls', 'ssl'], true) ? $cifrado : 'ninguno',
            'username'     => $usuario,
            'from_address' => $de,
            'from_name'    => $deNombre,
        ])->id;
    }

    private function integracion(string $proveedor, string $nombre, ?string $llave, array $config): ApiIntegration
    {
        $hayPrincipal = ApiIntegration::where('company_id', 1)->where('provider', $proveedor)->where('is_default_for_provider', true)->exists();

        $i = ApiIntegration::create([
            'company_id'              => 1,
            'provider'                => $proveedor,
            'slug'                    => $proveedor . '-' . substr(md5($nombre), 0, 8),
            'name'                    => $nombre,
            'config'                  => $config,
            'active'                  => true,
            'is_default_for_provider' => !$hayPrincipal,
        ]);
        if ($llave) {
            $i->value = $llave;
            $i->save();
        }
        return $i;
    }

    private function asignarVarias(array $claves, array $destino): void
    {
        foreach ($claves as $clave) {
            if (DB::table('servicio_asignaciones')->where('clave', $clave)->exists()) {
                continue;
            }
            DB::table('servicio_asignaciones')->insert(['clave' => $clave, 'created_at' => now(), 'updated_at' => now()] + $destino);
        }
    }

    public function down(): void
    {
        // Sin reversa automática: quitar asignaciones devuelve cada módulo a "como siempre", que es lo mismo.
    }
};
