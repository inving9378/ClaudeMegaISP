<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\PerfilExtension;

/**
 * Resuelve el perfil que DE VERDAD aplica a una extensión.
 *
 * Herencia: **extensión → rango → sistema**.
 *
 *   · La extensión puede sobrescribir campos sueltos (`voip_perfil_extension_id`).
 *   · Si no lo hace, hereda el perfil de su rango — el caso corriente.
 *   · Si el rango no tiene perfil, caen los defaults del sistema.
 *
 * La sobrescritura es PARCIAL a propósito: un perfil de sobrescritura solo pisa
 * los campos que declara distintos de null, no el perfil entero. Si pisara todo,
 * cambiar un solo permiso obligaría a redefinir códecs, límites y grabación — y
 * en el momento en que el perfil del rango cambiara, la extensión sobrescrita se
 * quedaría atrás sin que nadie lo note.
 *
 * ⚠️ No usar `Extension::perfilSobrescrito()` para saber qué aplica: esa relación
 * devuelve la sobrescritura, que casi siempre es null. El perfil efectivo es este.
 */
class ResolverPerfilEfectivo
{
    /**
     * Defaults del sistema — el último eslabón de la herencia.
     *
     * `permite_internacional` y `permite_premium` en false no son una preferencia:
     * son los destinos donde cobra el fraude telefónico. Una extensión comprometida
     * marcando a un premium genera decenas de miles de pesos en una madrugada, así
     * que habilitarlos tiene que ser deliberado en algún nivel, nunca el resultado
     * de que nadie configuró nada.
     */
    public const DEFAULTS_SISTEMA = [
        'permite_nacional_fijo'      => true,
        'permite_nacional_movil'     => true,
        'permite_internacional'      => false,
        'permite_premium'            => false,
        'permite_entrantes_exterior' => true,
        'graba_llamadas'             => false,
        'codecs'                     => ['alaw', 'ulaw'],
        'limite_diario_centavos'     => null,
        'limite_mensual_centavos'    => null,
        'canales_simultaneos_max'    => null,
        'politicas'                  => [],
    ];

    /**
     * @return array{valores: array<string,mixed>, origen: array<string,string>}
     *         `origen` dice de dónde salió cada campo: 'extension', 'rango' o
     *         'sistema'. Sin eso, depurar "¿por qué esta extensión puede marcar
     *         al extranjero?" es adivinar.
     */
    public function resolver(Extension $extension): array
    {
        $valores = self::DEFAULTS_SISTEMA;
        $origen  = array_fill_keys(array_keys($valores), 'sistema');

        // Nivel 2 — el perfil del rango.
        $perfilRango = $extension->rango?->perfil;
        if ($perfilRango) {
            $this->aplicar($perfilRango, $valores, $origen, 'rango');
        }

        // Nivel 3 — la sobrescritura de la extensión, encima de todo.
        $sobrescritura = $extension->perfilSobrescrito;
        if ($sobrescritura) {
            $this->aplicar($sobrescritura, $valores, $origen, 'extension');
        }

        return ['valores' => $valores, 'origen' => $origen];
    }

    /** Solo los valores. Para cuando no interesa de dónde vino cada uno. */
    public function valores(Extension $extension): array
    {
        return $this->resolver($extension)['valores'];
    }

    /** ¿Esta extensión puede marcar a este tipo de destino? */
    public function permite(Extension $extension, string $destino): bool
    {
        $campo = 'permite_' . $destino;
        $v     = $this->valores($extension);

        // Un destino que no existe se niega. Preguntar por algo desconocido no
        // puede abrir la puerta: sería un typo convertido en permiso.
        return (bool) ($v[$campo] ?? false);
    }

    /**
     * Aplica un perfil encima de lo acumulado. Solo pisa lo que declara: un campo
     * null significa "no opino, deja lo de abajo", que es lo que hace parcial a
     * la sobrescritura.
     */
    private function aplicar(PerfilExtension $perfil, array &$valores, array &$origen, string $nivel): void
    {
        foreach (PerfilExtension::CAMPOS_HEREDABLES as $campo) {
            $v = $perfil->{$campo};

            if ($v === null) {
                continue;
            }

            // Los booleanos del modelo nunca son null (tienen default en BD), así
            // que un perfil de rango siempre define sus permisos. Los nullables
            // —límites, códecs, políticas— son los que de verdad heredan.
            $valores[$campo] = $v;
            $origen[$campo]  = $nivel;
        }
    }
}
