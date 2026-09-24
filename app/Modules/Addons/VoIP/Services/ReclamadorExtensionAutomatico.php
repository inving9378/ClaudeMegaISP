<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\RangoNumeracion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MegaVoz Fase 1 — extensión SIP automática por colaborador.
 *
 * NO crea extensiones nuevas: reclama una de las ya sembradas por
 * `ExtensionesArranqueSeeder` (#9990718 §8) en el rango que le corresponde a su
 * rol, le pone el nombre real y le rota el secret (una extensión reclamada no
 * debe seguir con el secret genérico con el que se sembró). Duplicar la
 * creación de extensiones sería pisar el plan de numeración ya construido
 * (§7/§8) — este servicio se apoya en él, no lo reemplaza.
 *
 * Alcance decidido por David (23-sep-2026): solo los roles de atención directa
 * reclaman automáticamente. El resto de los colaboradores (oficina, RH,
 * contabilidad, etc.) no recibe extensión sola — se asigna a mano si hace
 * falta, vía la pantalla de Extensiones.
 */
class ReclamadorExtensionAutomatico
{
    /** rol de Spatie => código del rango en voip_rangos_numeracion */
    private const ROL_A_RANGO = [
        'TECNICO'            => 'tecnicos',
        'TECNICO_INSTALADOR' => 'tecnicos',
        'TECNICO_PLANTA'     => 'tecnicos',
        'Vendedor'           => 'ventas',
        'Mostrador'          => 'atencion',
        'SUPERVISOR_MOSTRADOR' => 'atencion',
    ];

    public function __construct(private AsteriskProvisioningService $provisioner) {}

    public function reclamarParaColaborador(TalentoColaborador $colaborador): ?Extension
    {
        $user = User::find($colaborador->user_id);
        if (! $user) {
            return null;
        }

        // Ya tiene una — no se reclama una segunda.
        if (Extension::where('user_id', $user->id)->exists()) {
            return null;
        }

        $codigoRango = $this->rangoParaUsuario($user);
        if (! $codigoRango) {
            // Rol fuera del alcance decidido (oficina, RH, etc.) — no es un error.
            return null;
        }

        $rango = RangoNumeracion::porCodigo($codigoRango)->first();
        if (! $rango) {
            Log::warning("VoIP: reclamo automático de extensión — rango '{$codigoRango}' no está sembrado (corre PlanNumeracionSeeder).", [
                'colaborador_id' => $colaborador->id,
            ]);
            return null;
        }

        $extension = Extension::where('voip_rango_numeracion_id', $rango->id)
            ->whereNull('user_id')
            ->where('sembrada_por_sistema', true)
            ->orderBy('numero')
            ->first();

        if (! $extension) {
            Log::warning("VoIP: reclamo automático de extensión — sin extensiones libres en el rango '{$codigoRango}' para el colaborador #{$colaborador->id}. Asignar a mano desde Extensiones.", [
                'colaborador_id' => $colaborador->id,
            ]);
            return null;
        }

        $extension->update([
            'nombre'   => $user->name,
            'user_id'  => $user->id,
            // Una extensión reclamada deja de tener el secret genérico con el
            // que se sembró — se rota por una propia.
            'secret'   => Str::random(32),
        ]);

        try {
            $this->provisioner->provisionarExtension($extension);
        } catch (\Throwable $e) {
            // Best-effort: el reclamo en BD ya quedó (queda visible en Extensiones
            // como "sin provisionar"); un admin puede reintentar desde la UI.
            Log::warning("VoIP: extensión {$extension->numero} reclamada para colaborador #{$colaborador->id} pero falló el provisionamiento: {$e->getMessage()}");
        }

        $this->crearGemelaWebrtc($extension, $user);

        return $extension->fresh();
    }

    /**
     * MegaVoz Fase 2 — "endpoint doble" (plan 22-sep-2026): la extensión de
     * escritorio recién reclamada recibe una gemela para el mini-teléfono del
     * navegador (`web{numero}`). A diferencia de la de escritorio, esta SÍ se
     * crea nueva — no hay un pool de gemelas pre-sembradas que reclamar,
     * porque es 100% derivada del número de la extensión dueña.
     *
     * PÚBLICO desde 24-sep-2026: además del alta automática de arriba,
     * `ExtensionController::store()/update()` reusa este mismo método cuando
     * un admin asigna `user_id` a una extensión A MANO (el camino que la
     * propia clase ya documentaba como soportado — "se asigna a mano si hace
     * falta, vía la pantalla de Extensiones" — pero nunca creaba la gemela
     * web, así que el mini-teléfono no aparecía para nadie asignado por ese
     * camino). Idempotente si el dueño no cambió (early-return si
     * `web{numero}` ya existe CON el mismo `user_id`).
     *
     * REASIGNACIÓN (24-sep-2026): si `web{numero}` ya existe pero con OTRO
     * `user_id` (la extensión de escritorio se le quitó a alguien y se le
     * dio a otra persona), se actualiza la gemela en vez de dejarla como
     * estaba — si no, quedaba a nombre de la persona anterior para
     * siempre. Se rota también el `secret` (la persona anterior pudo
     * haberlo visto/guardado) y se reprovisiona contra PJSIP realtime para
     * que el cambio de credencial surta efecto de inmediato.
     */
    public function crearGemelaWebrtc(Extension $extension, User $user): void
    {
        $numeroWeb = 'web' . $extension->numero;
        $gemela    = Extension::where('numero', $numeroWeb)->first();

        if ($gemela) {
            if ((int) $gemela->user_id === (int) $user->id) {
                return; // ya es de este mismo usuario — nada que hacer
            }

            // Reasignación: la gemela existe pero era de otra persona.
            $gemela->update([
                'nombre'  => $user->name . ' (navegador)',
                'user_id' => $user->id,
                'secret'  => Str::random(32),
            ]);

            try {
                $this->provisioner->provisionarExtension($gemela);
            } catch (\Throwable $e) {
                Log::warning("VoIP: reasignación de gemela WebRTC {$numeroWeb} guardada pero falló el reprovisionamiento: {$e->getMessage()}");
            }

            return;
        }

        $gemela = Extension::create([
            'numero'                   => $numeroWeb,
            'nombre'                   => $user->name . ' (navegador)',
            'user_id'                  => $user->id,
            'secret'                   => Str::random(32),
            'tipo_dispositivo'         => 'softphone',
            'es_webrtc'                => true,
            'contexto'                 => $extension->contexto,
            // ulaw PRIMERO (no opus) — este Asterisk NO tiene transcodificador
            // de Opus instalado (solo res_format_attr_opus.so, que negocia el
            // formato pero no convierte audio; confirmado: "module show like
            // opus" no lista ningún codec_opus). Con opus primero, una
            // llamada navegador→teléfono de escritorio (que solo tiene
            // ulaw/alaw) terminaba con cada lado negociando un códec
            // distinto → "No path to translate" → la llamada se caía al
            // conectar. ulaw es soportado por cualquier navegador WebRTC
            // (parte obligatoria del estándar), así que no rompe nada — solo
            // dejamos de depender de una conversión que este servidor no
            // puede hacer. Encontrado en vivo 24-sep-2026 (David llamando de
            // MegaVoz a la extensión de escritorio de Irving).
            'codecs'                   => 'ulaw,alaw,opus',
            'transporte'               => 'transport-wss',
            'callerid'                 => $extension->numero,
            'activo'                   => true,
            'voip_rango_numeracion_id' => $extension->voip_rango_numeracion_id,
            'sembrada_por_sistema'     => false,
        ]);

        try {
            $this->provisioner->provisionarExtension($gemela);
        } catch (\Throwable $e) {
            Log::warning("VoIP: gemela WebRTC {$numeroWeb} creada para colaborador pero falló el provisionamiento: {$e->getMessage()}");
        }
    }

    private function rangoParaUsuario(User $user): ?string
    {
        foreach (self::ROL_A_RANGO as $rol => $codigoRango) {
            if ($user->hasRole($rol)) {
                return $codigoRango;
            }
        }
        return null;
    }
}
