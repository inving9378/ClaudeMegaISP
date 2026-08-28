<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcAccesoLog;
use Illuminate\Http\Request;

/**
 * Escribe la bitácora de accesos. Punto ÚNICO: nadie escribe `dc_accesos_log`
 * por su cuenta.
 *
 * Se llama ANTES de servir el contenido, no después: si el registro falla, el
 * contenido no sale. No hay flag de configuración para desactivarlo — un
 * interruptor para apagar la bitácora convierte la bitácora en una sugerencia.
 */
class BitacoraService
{
    public function __construct(private ?Request $request = null)
    {
        $this->request = $request ?? request();
    }

    public function ver(int $empresaId, ?int $apartadoId = null, ?int $conceptoId = null, array $contexto = []): DcAccesoLog
    {
        return $this->registrar(DcAccesoLog::ACCION_VER, $empresaId, $apartadoId, $conceptoId, null, $contexto);
    }

    public function descargar(int $empresaId, int $documentoId, ?int $conceptoId = null, array $contexto = []): DcAccesoLog
    {
        return $this->registrar(DcAccesoLog::ACCION_DESCARGAR, $empresaId, null, $conceptoId, $documentoId, $contexto);
    }

    public function exportar(int $empresaId, ?int $apartadoId = null, ?int $conceptoId = null, array $contexto = []): DcAccesoLog
    {
        return $this->registrar(DcAccesoLog::ACCION_EXPORTAR, $empresaId, $apartadoId, $conceptoId, null, $contexto);
    }

    public function imprimir(int $empresaId, ?int $apartadoId = null, ?int $conceptoId = null, array $contexto = []): DcAccesoLog
    {
        return $this->registrar(DcAccesoLog::ACCION_IMPRIMIR, $empresaId, $apartadoId, $conceptoId, null, $contexto);
    }

    private function registrar(
        string $accion,
        int $empresaId,
        ?int $apartadoId,
        ?int $conceptoId,
        ?int $documentoId,
        array $contexto
    ): DcAccesoLog {
        return DcAccesoLog::create([
            'empresa_id'   => $empresaId,
            'user_id'      => auth()->id(),
            'apartado_id'  => $apartadoId,
            'concepto_id'  => $conceptoId,
            'documento_id' => $documentoId,
            'accion'       => $accion,
            'ip'           => $this->request?->ip(),
            'user_agent'   => mb_substr((string) $this->request?->userAgent(), 0, 512) ?: null,
            'contexto'     => $contexto === [] ? null : $contexto,
        ]);
    }
}
