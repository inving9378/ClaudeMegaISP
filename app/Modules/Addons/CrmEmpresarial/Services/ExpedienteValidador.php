<?php

namespace App\Modules\Addons\CrmEmpresarial\Services;

use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;

/**
 * Item #964 (CRM-E 04, fase 3): qué le falta a un expediente según la acción que se quiere hacer.
 * Guardar un borrador incompleto SIEMPRE se permite (devuelve los faltantes solo como aviso);
 * las demás acciones acumulan requisitos: emitir_propuesta < enviar_firma < activar.
 */
class ExpedienteValidador
{
    public const ACCIONES = ['guardar', 'emitir_propuesta', 'enviar_firma', 'activar'];

    private const ETIQUETAS = [
        'tipo_persona' => 'Tipo de persona', 'razon_social' => 'Razón social', 'rfc' => 'RFC',
        'regimen_fiscal' => 'Régimen fiscal', 'domicilio_fiscal_cp' => 'Código postal fiscal',
        'domicilio_fiscal_calle' => 'Calle del domicilio fiscal', 'uso_cfdi' => 'Uso de CFDI',
        'email' => 'Correo', 'telefono' => 'Teléfono',
        'representante' => 'Al menos un representante legal', 'representante_validado' => 'Representante legal validado',
        'contacto' => 'Al menos un contacto', 'sitio' => 'Al menos un sitio de servicio',
    ];

    /** Campos del expediente requeridos por acción (acumulativo). */
    private const CAMPOS = [
        'emitir_propuesta' => ['tipo_persona', 'razon_social'],
        'enviar_firma' => ['rfc', 'regimen_fiscal', 'domicilio_fiscal_cp', 'domicilio_fiscal_calle', 'email'],
        'activar' => ['uso_cfdi', 'telefono'],
    ];

    /**
     * @return array{accion:string, permitido:bool, faltantes:array<string,string>, errores:array<string,string>}
     *  faltantes = clave => etiqueta legible; errores = formato inválido (RFC/CP/régimen).
     */
    public function validar(CrmExpediente $e, string $accion): array
    {
        if (! in_array($accion, self::ACCIONES, true)) {
            throw new \InvalidArgumentException("Acción desconocida: {$accion}");
        }

        $faltantes = [];
        $nivel = array_search($accion, self::ACCIONES, true);

        foreach (['emitir_propuesta', 'enviar_firma', 'activar'] as $i => $a) {
            // 'guardar' = nivel 0: solo informa lo que pediría emitir_propuesta, sin bloquear.
            if ($accion !== 'guardar' && $nivel < $i + 1) {
                break;
            }
            foreach (self::CAMPOS[$a] as $campo) {
                if (blank($e->{$campo})) {
                    $faltantes[$campo] = self::ETIQUETAS[$campo];
                }
            }
            if ($accion === 'guardar') {
                break;
            }
        }

        if ($nivel >= 2) {
            $this->requisitosRelacionados($e, $accion, $faltantes);
        }

        $errores = (new ExpedienteFiscalService())->validar($e);

        $bloquea = $accion !== 'guardar';
        return [
            'accion' => $accion,
            'permitido' => $bloquea ? ($faltantes === [] && $errores === []) : true,
            'faltantes' => $faltantes,
            'errores' => $errores,
        ];
    }

    /** Informe por todas las acciones (para mostrar "qué falta para cada paso"). */
    public function resumen(CrmExpediente $e): array
    {
        $out = [];
        foreach (self::ACCIONES as $a) {
            $out[$a] = $this->validar($e, $a);
        }
        return $out;
    }

    private function requisitosRelacionados(CrmExpediente $e, string $accion, array &$faltantes): void
    {
        $reps = $e->exists ? $e->representantes()->get() : collect();
        if ($reps->isEmpty()) {
            $faltantes['representante'] = self::ETIQUETAS['representante'];
        } elseif ($accion === 'activar' && $reps->where('estado_validacion', 'validado')->isEmpty()) {
            $faltantes['representante_validado'] = self::ETIQUETAS['representante_validado'];
        }

        if ($accion === 'activar') {
            if (! $e->exists || $e->contactos()->count() === 0) {
                $faltantes['contacto'] = self::ETIQUETAS['contacto'];
            }
            if (! $e->exists || $e->sitios()->count() === 0) {
                $faltantes['sitio'] = self::ETIQUETAS['sitio'];
            }
        }
    }
}
