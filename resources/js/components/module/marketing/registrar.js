/**
 * Marketing IA — registro global del kit y de las pantallas nuevas (Fase 0+).
 *
 * app.js lo llama UNA vez:  require("./components/module/marketing/registrar").default(app);
 * Las fases siguientes agregan aquí sus pantallas, sin volver a tocar app.js.
 */
import { COMPONENTES_KIT } from "./kit";
import MarketingEmpresasEquipo from "./empresas/MarketingEmpresasEquipo.vue";
import MarketingCampanas360Lista from "./campanas360/MarketingCampanas360Lista.vue";
import MarketingCampana360Asistente from "./campanas360/MarketingCampana360Asistente.vue";
import MarketingCampana360Ficha from "./campanas360/MarketingCampana360Ficha.vue";

const PANTALLAS = {
    // Fase 0 — Empresas y equipo (/marketing/empresas)
    "marketing-empresas-equipo": MarketingEmpresasEquipo,
    // Fase 2 — Campañas 360 (/marketing/campanas-360, /nueva, /{id}, /{id}/editar)
    "marketing-campanas-360-lista": MarketingCampanas360Lista,
    "marketing-campana-360-asistente": MarketingCampana360Asistente,
    "marketing-campana-360-ficha": MarketingCampana360Ficha,
};

export default function registrarMarketing(app) {
    Object.entries({ ...COMPONENTES_KIT, ...PANTALLAS }).forEach(([tag, componente]) => {
        app.component(tag, componente);
    });
}
