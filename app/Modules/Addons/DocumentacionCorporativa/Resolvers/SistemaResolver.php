<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Resolvers;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\FuenteRegistry;
use App\Modules\Addons\DocumentacionCorporativa\Contracts\ResultadoConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use Illuminate\Support\Facades\Log;

/**
 * Conceptos que el sistema YA sabe: cartera, saldos, proveedores, plantilla
 * laboral, equipos de red. No consulta ningún módulo por su cuenta: pide la
 * fuente que el concepto declara en `config.fuente` al `FuenteRegistry` y la
 * ejecuta.
 *
 * Regla dura: una fuente es SIEMPRE de solo lectura. Llama al servicio público
 * del módulo destino si existe; si no, hace query al modelo. Nunca duplica
 * lógica de negocio y nunca escribe en tablas de otros módulos.
 */
class SistemaResolver extends BaseResolver
{
    public function __construct(protected FuenteRegistry $registry)
    {
    }

    protected function vista(): string
    {
        return 'dc-concepto-tabla';
    }

    public function disponible(DcConcepto $concepto, int $empresaId): bool
    {
        return $this->registry->tiene($concepto->config['fuente'] ?? null);
    }

    public function resolver(DcConcepto $concepto, int $empresaId): ResultadoConcepto
    {
        $clave = $concepto->config['fuente'] ?? null;

        if (! $this->registry->tiene($clave)) {
            return ResultadoConcepto::sinFuente(
                'Este concepto declara la fuente de sistema "' . ($clave ?? 'sin declarar')
                . '", que todavía no está registrada. Se conectará en la fase correspondiente.',
                ['obligatorio' => (bool) $concepto->obligatorio]
            );
        }

        try {
            $salida = $this->registry->ejecutar($clave, $concepto->config ?? [], $empresaId);
        } catch (\Throwable $e) {
            // Una fuente rota NUNCA tumba el apartado completo: el concepto cae a
            // "sin fuente" con el motivo, y el resto del expediente sigue vivo.
            Log::warning('dc: fuente de sistema falló', [
                'concepto' => $concepto->slug, 'fuente' => $clave, 'error' => $e->getMessage(),
            ]);

            return ResultadoConcepto::sinFuente(
                'La fuente "' . $clave . '" respondió con un error y no se pudo leer. '
                . 'El resto del apartado no se ve afectado.',
                ['obligatorio' => (bool) $concepto->obligatorio, 'error' => true]
            );
        }

        $metricas = $salida['metricas'] + ['obligatorio' => (bool) $concepto->obligatorio];

        if ($salida['datos'] === []) {
            return ResultadoConcepto::vacio(
                $this->vista(),
                $salida['mensaje'] ?? 'Sin registros en este entorno.',
                $metricas
            );
        }

        return ResultadoConcepto::resuelto($this->vista(), $salida['datos'], $metricas);
    }
}
