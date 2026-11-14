/**
 * Marketing IA — kit de componentes (Fase 0). Se registra globalmente desde ../registrar.js.
 */
import MktRaiz from "./MktRaiz.vue";
import MktEncabezadoPantalla from "./MktEncabezadoPantalla.vue";
import MktSelectorEmpresa from "./MktSelectorEmpresa.vue";
import MktTarjetaKpi from "./MktTarjetaKpi.vue";
import MktTarjetaPanel from "./MktTarjetaPanel.vue";
import MktTablaEjecutiva from "./MktTablaEjecutiva.vue";
import MktEstadoVacio from "./MktEstadoVacio.vue";
import MktInsigniaEstado from "./MktInsigniaEstado.vue";
import MktNavegacionModulo from "./MktNavegacionModulo.vue";
import MktDialogo from "./MktDialogo.vue";
import MktPestanas from "./MktPestanas.vue";
import MktFichaEncabezado from "./MktFichaEncabezado.vue";
import MktBoton from "./MktBoton.vue";
import MktMenuTabla from "./MktMenuTabla.vue";

export * from "./formato";
export * from "./tokens";

/** tag kebab-case → componente (lo consume registrar.js). */
export const COMPONENTES_KIT = Object.freeze({
    "mkt-raiz": MktRaiz,
    "mkt-encabezado-pantalla": MktEncabezadoPantalla,
    "mkt-selector-empresa": MktSelectorEmpresa,
    "mkt-tarjeta-kpi": MktTarjetaKpi,
    "mkt-tarjeta-panel": MktTarjetaPanel,
    "mkt-tabla-ejecutiva": MktTablaEjecutiva,
    "mkt-estado-vacio": MktEstadoVacio,
    "mkt-insignia-estado": MktInsigniaEstado,
    // Fase 1
    "mkt-navegacion-modulo": MktNavegacionModulo,
    "mkt-dialogo": MktDialogo,
    // Fase 2 · diseño unificado con Clientes (§2.13)
    "mkt-pestanas": MktPestanas,
    "mkt-ficha-encabezado": MktFichaEncabezado,
    "mkt-boton": MktBoton,
    "mkt-menu-tabla": MktMenuTabla,
});

export {
    MktRaiz,
    MktEncabezadoPantalla,
    MktSelectorEmpresa,
    MktTarjetaKpi,
    MktTarjetaPanel,
    MktTablaEjecutiva,
    MktEstadoVacio,
    MktInsigniaEstado,
    MktNavegacionModulo,
    MktDialogo,
    MktPestanas,
    MktFichaEncabezado,
    MktBoton,
    MktMenuTabla,
};
