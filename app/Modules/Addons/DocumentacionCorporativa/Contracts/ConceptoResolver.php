<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Contracts;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;

/**
 * Contrato único de resolución de conceptos.
 *
 * Seis implementaciones: Sistema, Documento, Plantilla, Grafica, Inventario y
 * Pendiente. `ResolverFactory` elige una por `dc_conceptos.tipo_resolvedor` y
 * cae a `PendienteResolver` cuando la elegida dice que no está disponible — así
 * un concepto sin fuente muestra una tarjeta que lo explica, nunca un error.
 */
interface ConceptoResolver
{
    /** ¿Este resolvedor puede resolver ESTE concepto en ESTA empresa hoy? */
    public function disponible(DcConcepto $concepto, int $empresaId): bool;

    public function resolver(DcConcepto $concepto, int $empresaId): ResultadoConcepto;

    /**
     * Genera un archivo con el contenido del concepto y devuelve su ruta absoluta.
     *
     * @param  string  $formato  'csv' | 'pdf'
     * @throws FormatoNoSoportadoException
     */
    public function exportar(DcConcepto $concepto, int $empresaId, string $formato): string;
}
