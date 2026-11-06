<?php

namespace App\Modules\Addons\Roadmap\Services\ConsolaAcciones;

/**
 * Item #876 — declaración de las acciones de la Torre migradas al catálogo.
 *
 * Primera migrada: `torre.seguimiento.crear` (botón "＋ Seguimiento" → "Crear seguimiento" en
 * `TorreControl.vue`, antes servido por `RoadmapController::seguimiento()` con solo
 * `$this->authorize('circuito.decidir')`). Sin cambio de comportamiento ni de permiso — el mismo
 * `circuito.decidir` de siempre — solo ahora pasa por el ejecutor central y queda registrada en
 * `consola_acciones_ejecuciones`.
 *
 * El resto de los botones de la Torre (~30, inventariados en el reporte de cierre del item #876)
 * quedan pendientes de migrar en sub-items separados, agrupados por permiso y por riesgo — el
 * kill switch (`circuito.pause`) y el disparo de vueltas (`circuito.disparar`) deliberadamente
 * NO se tocan en esta primera pasada: son el corazón operativo del circuito y merecen su propio
 * sub-item, revisado con más cuidado que una migración mecánica.
 *
 * Cómo declarar una acción nueva: ver `docs/circuito/acciones-de-consola.md`.
 */
class TorreAccionesCatalogo
{
    public static function declarar(ConsolaAccionCatalogo $catalogo): void
    {
        $catalogo->declarar([
            'clave'       => 'torre.seguimiento.crear',
            'nombre'      => 'Crear seguimiento',
            'descripcion' => 'Crea un item nuevo de seguimiento a partir de otro, opcionalmente cerrando el origen.',
            'permiso'     => 'circuito.decidir',
            'confirma'    => ConsolaAccion::CONFIRMA_NO,
            'reversible'  => true,
            'registra'    => 'origen, nuevo item creado y si se cerró el origen',
            'respuesta'   => 'Seguimiento creado.',
        ]);
    }
}
