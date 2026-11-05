<?php

namespace App\Modules\Addons\CrmEmpresarial\Services;

use App\Models\CompanyInformation;
use App\Modules\Addons\CrmEmpresarial\Models\CrmConfiguracionServicio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Configurador de servicios dedicados (item #559). Configuración comercial:
 * no toca client_*_services ni facturación. Precio de lista y % de IVA salen
 * de la BD (catálogo y company_information), nunca del cliente HTTP.
 */
class ConfiguradorServicioService
{
    public const PERMISO_APROBAR = 'crm-empresarial.precios.aprobar';

    /** Campos que el usuario puede enviar (los de precio/aprobación se calculan aquí). */
    public const EDITABLES = [
        'sitio_id', 'servicio_tipo', 'servicio_id', 'velocidad_bajada', 'velocidad_subida', 'simetrico',
        'duration_contract_id', 'plazo_meses', 'precio_aprobado', 'justificacion_precio',
        'costo_instalacion', 'cargo_activacion', 'descuento', 'equipo_entrega', 'ipv4_solicitadas',
        'bloque_adicional', 'ipv6', 'sla_objetivo', 'monitoreo', 'horario_soporte', 'tiempo_atencion',
        'observaciones_ingenieria', 'infraestructura_existente', 'fecha_estimada_entrega',
        'vigencia_propuesta', 'red_rutas_fisicas', 'red_proteccion_logica', 'red_diversidad_equipos',
        'red_diversidad_proveedores', 'red_disponibilidad_contractual', 'red_conmutacion', 'red_verificada',
    ];

    /** % de IVA vigente (company_information.iva guarda p. ej. "16%"). */
    public function ivaPct(): float
    {
        $raw = CompanyInformation::query()->value('iva');
        if ($raw === null || ! is_numeric($n = str_replace(['%', ' '], '', (string) $raw))) {
            throw ValidationException::withMessages(['iva' => 'No hay IVA configurado en la empresa.']);
        }

        return (float) $n;
    }

    /** IVA en un solo servicio, redondeado a 2 decimales. */
    public function calcular(float $base, float $ivaPct): array
    {
        $iva = round($base * $ivaPct / 100, 2);

        return ['iva_importe' => $iva, 'total' => round($base + $iva, 2)];
    }

    /** Precio de lista tomado del catálogo (customs / internets). */
    public function precioLista(?string $tipo, ?int $id): ?float
    {
        if (! $tipo || ! $id) {
            return null;
        }
        $tabla = $tipo === 'internet' ? 'internets' : 'customs';
        $p = DB::table($tabla)->where('id', $id)->value('price');

        return $p === null ? null : (float) $p;
    }

    public function guardar(int $expedienteId, array $datos, $user, ?CrmConfiguracionServicio $actual = null): CrmConfiguracionServicio
    {
        $c = $actual ?? new CrmConfiguracionServicio(['expediente_id' => $expedienteId]);
        $antesAprobado = $c->precio_aprobado;
        $antesLista = $c->precio_lista;
        $c->fill(array_intersect_key($datos, array_flip(self::EDITABLES)));

        $c->precio_lista = $this->precioLista($c->servicio_tipo, $c->servicio_id) ?? $c->precio_lista;
        $c->iva_pct = $this->ivaPct();
        $base = $c->precio_aprobado ?? $c->precio_lista;

        $distinto = $c->precio_aprobado !== null && $c->precio_lista !== null
            && round((float) $c->precio_aprobado, 2) !== round((float) $c->precio_lista, 2);
        if ($distinto) {
            if (! $user || ! $user->can(self::PERMISO_APROBAR)) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'Un precio distinto al de lista requiere el permiso ' . self::PERMISO_APROBAR . '.'
                );
            }
            if (blank($c->justificacion_precio)) {
                throw ValidationException::withMessages(['justificacion_precio' => 'La justificación es obligatoria.']);
            }
            $c->aprobado_por = $user->id;
            $c->aprobado_at = now();
        } else {
            $c->aprobado_por = null;
            $c->aprobado_at = null;
        }

        if ($base !== null) {
            $c->fill($this->calcular((float) $base, (float) $c->iva_pct));
        }
        if ($c->exists && (
            round((float) $antesAprobado, 2) !== round((float) $c->precio_aprobado, 2)
            || round((float) $antesLista, 2) !== round((float) $c->precio_lista, 2)
        )) {
            $c->version_oferta = ($c->version_oferta ?? 1) + 1;
        }

        $c->save();

        return $c;
    }

    /**
     * Redundancia que puede aparecer en documentos: sólo garantías verificadas
     * y aprobadas; una doble ruta física NO se presenta como redundancia total.
     */
    public function redundanciaVerificada(CrmConfiguracionServicio $c): array
    {
        if (! $c->red_verificada) {
            return [];
        }

        return array_filter([
            'rutas_fisicas' => $c->red_rutas_fisicas,
            'proteccion_logica' => $c->red_proteccion_logica ?: null,
            'diversidad_equipos' => $c->red_diversidad_equipos ?: null,
            'diversidad_proveedores' => $c->red_diversidad_proveedores ?: null,
            'disponibilidad_contractual' => $c->red_disponibilidad_contractual,
            'conmutacion' => $c->red_conmutacion,
        ], fn ($v) => $v !== null);
    }
}
