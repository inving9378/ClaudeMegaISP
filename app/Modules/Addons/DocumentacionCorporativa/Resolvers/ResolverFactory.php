<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Resolvers;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\ConceptoResolver;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;

/**
 * Elige el resolvedor de un concepto y garantiza que SIEMPRE haya uno.
 *
 * Si el declarado no está disponible —fuente no registrada, tabla que aún no
 * existe, plantilla sin asignar— cae a `PendienteResolver`. Por eso ningún
 * apartado puede quedar en blanco ni reventar: el peor caso es una tarjeta que
 * explica qué falta.
 */
class ResolverFactory
{
    public function __construct(
        private SistemaResolver $sistema,
        private DocumentoResolver $documento,
        private PlantillaResolver $plantilla,
        private GraficaResolver $grafica,
        private InventarioResolver $inventario,
        private PendienteResolver $pendiente,
    ) {
    }

    public function para(DcConcepto $concepto, int $empresaId): ConceptoResolver
    {
        $candidato = $this->porTipo($concepto->tipo_resolvedor);

        return $candidato->disponible($concepto, $empresaId) ? $candidato : $this->pendiente;
    }

    private function porTipo(?string $tipo): ConceptoResolver
    {
        return match ($tipo) {
            'sistema'    => $this->sistema,
            'documento'  => $this->documento,
            'plantilla'  => $this->plantilla,
            'grafica'    => $this->grafica,
            'inventario' => $this->inventario,
            default      => $this->pendiente,
        };
    }
}
