/**
 * Marketing IA — registro global del kit y de las pantallas nuevas (Fase 0+).
 *
 * app.js lo llama UNA vez:  require("./components/module/marketing/registrar").default(app);
 * Las fases siguientes agregan aquí sus pantallas, sin volver a tocar app.js.
 */
import { COMPONENTES_KIT } from "./kit";
import MarketingEmpresasEquipo from "./empresas/MarketingEmpresasEquipo.vue";

const PANTALLAS = {
    // Fase 0 — Empresas y equipo (/marketing/empresas)
    "marketing-empresas-equipo": MarketingEmpresasEquipo,
};

export default function registrarMarketing(app) {
    Object.entries({ ...COMPONENTES_KIT, ...PANTALLAS }).forEach(([tag, componente]) => {
        app.component(tag, componente);
    });
}
