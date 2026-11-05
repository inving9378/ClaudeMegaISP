// Paquete Vue LIGERO del Portal Cliente → "Mis vehículos" (#652, Flotas Portal=Panel F1).
// Monta las MISMAS pantallas de Flotas del panel de Meganet, sin cargar app.js (~15 MB).
// Se carga solo en las vistas de Mis Vehículos del portal; el servidor las alimenta por
// /portal/flotas/api/... (contexto 'flotas.portal'). Exclusivos de Meganet (proveedores,
// comparativo, suscripciones, flotas de clientes) NO se registran aquí.
// Iconos bi-* y Bootstrap 5 se sirven desde public (plugins/quasar/extras/bootstrap-icons, assets/css/bootstrap.min.css): los enlaza la vista (F2), no se empaquetan.
import axios from "axios";
import { createApp } from "vue";

import FleetDashboard from "./components/module/flotas/FleetDashboard.vue";
import FleetVehicleForm from "./components/module/flotas/FleetVehicleForm.vue";
import FleetVehicleShow from "./components/module/flotas/FleetVehicleShow.vue";
import FleetTabInfo from "./components/module/flotas/FleetTabInfo.vue";
import FleetTabAsignacion from "./components/module/flotas/FleetTabAsignacion.vue";
import FleetTabMantenimientos from "./components/module/flotas/FleetTabMantenimientos.vue";
import FleetTabDocumentos from "./components/module/flotas/FleetTabDocumentos.vue";
import FleetTabCombustible from "./components/module/flotas/FleetTabCombustible.vue";
import FleetTabGps from "./components/module/flotas/FleetTabGps.vue";
import FleetTabHistorial from "./components/module/flotas/FleetTabHistorial.vue";
import FleetTabFotos from "./components/module/flotas/FleetTabFotos.vue";
import FleetMap from "./components/module/flotas/FleetMap.vue";
import FleetGeofenceList from "./components/module/flotas/FleetGeofenceList.vue";
import FleetGeofenceForm from "./components/module/flotas/FleetGeofenceForm.vue";
import FleetGeofenceShow from "./components/module/flotas/FleetGeofenceShow.vue";
import FleetNotificationLog from "./components/module/flotas/FleetNotificationLog.vue";
import FleetRuleList from "./components/module/flotas/FleetRuleList.vue";
import FleetClientAlertContacts from "./components/module/flotas/FleetClientAlertContacts.vue";
import FleetDocumentsDashboard from "./components/module/flotas/FleetDocumentsDashboard.vue";

axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
const csrf = document.querySelector('meta[name="csrf-token"]');
if (csrf) {
    axios.defaults.headers.common["X-CSRF-TOKEN"] = csrf.content;
}

const COMPONENTES = {
    "fleet-dashboard": FleetDashboard,
    "fleet-vehicle-form": FleetVehicleForm,
    "fleet-vehicle-show": FleetVehicleShow,
    "fleet-map": FleetMap,
    "fleet-geofence-list": FleetGeofenceList,
    "fleet-geofence-form": FleetGeofenceForm,
    "fleet-geofence-show": FleetGeofenceShow,
    "fleet-notification-log": FleetNotificationLog,
    "fleet-rule-list": FleetRuleList,
    "fleet-client-alert-contacts": FleetClientAlertContacts,
    "fleet-documents-dashboard": FleetDocumentsDashboard,
    // Sub-componentes de FleetVehicleShow (deben ser globales, igual que en app.js)
    "fleet-tab-info": FleetTabInfo,
    "fleet-tab-asignacion": FleetTabAsignacion,
    "fleet-tab-mantenimientos": FleetTabMantenimientos,
    "fleet-tab-documentos": FleetTabDocumentos,
    "fleet-tab-combustible": FleetTabCombustible,
    "fleet-tab-gps": FleetTabGps,
    "fleet-tab-historial": FleetTabHistorial,
    "fleet-tab-fotos": FleetTabFotos,
};

const el = document.getElementById("portal-flotas-app");
if (el) {
    const app = createApp({});
    Object.entries(COMPONENTES).forEach(([nombre, comp]) => app.component(nombre, comp));
    app.mount(el);
}
