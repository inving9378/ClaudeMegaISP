<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\RangoNumeracion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Teléfono web automático por colaborador.
 *
 * Desde el 17-nov-2026 (segmentos, ver SegmentosTelefono) lo que se le da a una persona
 * es su TELÉFONO WEB, con número propio en 2000–2999 y en el área de su rol (mostrador →
 * 2100–2199). Ya no se toma un número de aparato físico (1000–1999): esos son de los
 * teléfonos de escritorio y se asignan a mano en Extensiones cuando hay un aparato.
 *
 * Alcance decidido por David (23-sep-2026): solo los roles de atención directa reciben
 * teléfono solo. El resto (oficina, RH, contabilidad…) se da de alta a mano en
 * Extensiones → «Nueva extensión» → tipo «Teléfono web».
 */
class ReclamadorExtensionAutomatico
{
    /** rol de Spatie => código del área (el rango web es «<área>_web») */
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

        // Uno por persona.
        if (Extension::where('user_id', $user->id)->where('es_webrtc', true)->exists()) {
            return null;
        }

        $area = self::areaDe($user);
        if (! $area) {
            // Rol fuera del alcance decidido (oficina, RH, etc.) — no es un error.
            return null;
        }

        $codigo = $area . SegmentosTelefono::SUFIJO_WEB;
        $rango  = RangoNumeracion::porCodigo($codigo)->first();
        if (! $rango) {
            Log::warning("VoIP: teléfono web automático — el rango '{$codigo}' no está sembrado (lo crea «Instalar dependencias» de VoIP).", [
                'colaborador_id' => $colaborador->id,
            ]);
            return null;
        }

        $numero = SegmentosTelefono::primerLibre($rango);
        if (! $numero) {
            Log::warning("VoIP: teléfono web automático — sin números libres en '{$codigo}' para el colaborador #{$colaborador->id}. Darlo de alta a mano en Extensiones.", [
                'colaborador_id' => $colaborador->id,
            ]);
            return null;
        }

        return $this->crearTelefonoWeb($user, $numero, $rango);
    }

    /**
     * Da de alta el teléfono web de una persona y lo publica en Asterisk. Best-effort en
     * la publicación: si falla, la extensión queda en MegaISP («Sin provisionar») y
     * «Sincronizar con Asterisk» la sube.
     */
    public function crearTelefonoWeb(User $user, string $numero, ?RangoNumeracion $rango, string $contexto = 'from-internal'): Extension
    {
        $ext = Extension::create(SegmentosTelefono::DATOS_WEB + [
            'numero'                   => $numero,
            'nombre'                   => trim("{$user->name} {$user->father_last_name}") . ' (navegador)',
            'user_id'                  => $user->id,
            'secret'                   => Str::random(32),
            'contexto'                 => $contexto,
            'callerid'                 => $numero,
            'activo'                   => true,
            'voip_rango_numeracion_id' => $rango?->id,
            'sembrada_por_sistema'     => false,
        ]);

        try {
            $this->provisioner->provisionarExtension($ext);
        } catch (\Throwable $e) {
            Log::warning("VoIP: teléfono web {$numero} creado para {$user->name} pero falló la publicación en Asterisk: {$e->getMessage()}");
        }

        return $ext->fresh();
    }

    /** Área de la persona según su rol («atencion», «tecnicos»…); null si su rol no está en la lista. */
    public static function areaDe(User $user): ?string
    {
        foreach (self::ROL_A_RANGO as $rol => $codigoRango) {
            if ($user->hasRole($rol)) {
                return $codigoRango;
            }
        }
        return null;
    }
}
