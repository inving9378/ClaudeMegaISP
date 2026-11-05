<template>
    <q-card flat bordered class="mdc" :dark="darkMode">
        <q-card-section>
            <div class="text-h6">Monitoreo de dedicados</div>
            <div class="mdc-tenue">
                Cada minuto lee el tráfico de la VLAN y hace ping a las IPs de los servicios dedicados que tienen
                «Datos de red» guardados. En el router solo lee; no cambia nada.
            </div>
        </q-card-section>
        <q-separator :dark="darkMode" />

        <q-card-section v-if="cargando" class="mdc-centro"><q-spinner size="32px" color="primary" /></q-card-section>
        <q-card-section v-else-if="errorCarga"><q-banner class="bg-negative text-white" rounded>{{ errorCarga }}</q-banner></q-card-section>

        <q-card-section v-else>
            <q-toggle
                :model-value="estado.ajuste"
                :disable="guardando"
                :label="estado.ajuste ? 'Encendido' : 'Apagado'"
                color="positive"
                size="lg"
                :dark="darkMode"
                @update:model-value="cambiar"
            />
            <div class="mdc-tenue">{{ ultimoCambio }}</div>

            <q-banner v-if="estado.override !== null" dense rounded class="mdc-aviso q-mt-sm">
                <template #avatar><q-icon name="warning" color="warning" /></template>
                METRICAS_ACTIVO en el .env lo tiene forzado a «{{ estado.override ? "encendido" : "apagado" }}»: manda
                sobre este interruptor hasta que se quite de ahí.
            </q-banner>

            <dl class="mdc-datos">
                <div><dt>Servicios que se miden</dt><dd>{{ estado.servicios_medidos }}</dd></div>
                <div><dt>Servicios dedicados sin datos de red completos</dt><dd>{{ estado.incompletos }}</dd></div>
                <div><dt>Última corrida del colector</dt><dd>{{ ultimaCorrida }}</dd></div>
            </dl>

            <div v-if="routers.length" class="mdc-tabla">
                <q-markup-table dense flat bordered :dark="darkMode">
                    <thead>
                        <tr>
                            <th class="text-left">Router</th>
                            <th class="text-left">Última lectura</th>
                            <th class="text-left">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in routers" :key="r.router_id">
                            <td class="text-left">{{ r.titulo || `Router #${r.router_id}` }}</td>
                            <td class="text-left">{{ textoEstado(r.estado) }}</td>
                            <td class="text-left mdc-detalle">{{ r.error || "—" }}</td>
                        </tr>
                    </tbody>
                </q-markup-table>
            </div>
        </q-card-section>
    </q-card>
</template>

<script>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { darkMode } from "../../../hook/appConfig";

const ESTADOS_ROUTER = {
    ok: "Bien",
    omitido: "Sin tiempo en el ciclo",
    sin_conexion: "Sin conexión",
    sin_identidad_esperada: "Falta confirmar el router",
    identidad_distinta: "Contestó otra identidad",
};

const fecha = new Intl.DateTimeFormat("es-MX", { dateStyle: "short", timeStyle: "short" });

// #988: interruptor maestro del monitoreo de dedicados y cómo va el colector.
export default {
    name: "MonitoreoDedicadosConfig",
    setup() {
        const cargando = ref(true);
        const guardando = ref(false);
        const errorCarga = ref(null);
        const estado = ref(null);

        const cargar = async () => {
            try {
                estado.value = (await axios.get("/configuracion/monitoreo-dedicados/estado")).data;
            } catch (e) {
                errorCarga.value = e.response?.data?.message || "No se pudo cargar el monitoreo de dedicados.";
            } finally {
                cargando.value = false;
            }
        };

        const cambiar = async (activo) => {
            guardando.value = true;
            try {
                estado.value = (await axios.post("/configuracion/monitoreo-dedicados", { activo })).data;
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || "No se pudo cambiar el monitoreo.", "error");
            } finally {
                guardando.value = false;
            }
        };

        const ultimoCambio = computed(() => {
            const { ajuste, cambiado_por: por, cambiado_en: en } = estado.value;
            if (!por) return `${ajuste ? "Encendido" : "Apagado"} desde la instalación; nadie lo ha cambiado.`;
            const cuando = en ? fecha.format(new Date(en.replace(" ", "T"))) : "";
            return `Lo ${ajuste ? "encendió" : "apagó"} ${por} · ${cuando}`;
        });

        const ultimaCorrida = computed(() => {
            const c = estado.value.colector;
            if (!c) return "Todavía no ha corrido";
            if (c.segundos < 120) return `Hace ${c.segundos} s`;
            if (c.segundos < 7200) return `Hace ${Math.round(c.segundos / 60)} min`;
            return fecha.format(new Date(c.ultimo));
        });

        const routers = computed(() => estado.value?.colector?.routers ?? []);
        const textoEstado = (e) => ESTADOS_ROUTER[e] ?? e ?? "—";

        onMounted(cargar);

        return { darkMode, cargando, guardando, errorCarga, estado, cambiar, ultimoCambio, ultimaCorrida, routers, textoEstado };
    },
};
</script>

<style scoped>
.mdc {
    border-radius: 10px;
    max-width: 860px;
}
.mdc-tenue {
    opacity: 0.75;
}
.mdc-centro {
    display: flex;
    justify-content: center;
    padding: 32px;
}
.mdc-aviso {
    background: rgba(245, 158, 11, 0.12);
}
.mdc-datos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin: 16px 0;
}
.mdc-datos dt {
    font-weight: 500;
    opacity: 0.75;
}
.mdc-datos dd {
    margin: 2px 0 0;
    font-size: 1.1rem;
    font-weight: 600;
}
.mdc-tabla {
    overflow-x: auto;
}
.mdc-detalle {
    white-space: normal;
    min-width: 220px;
}
/* El Bootstrap global estira los .row internos de Quasar: se neutraliza solo aquí (como en DatosRedDedicado.vue). */
.mdc :deep(.row) {
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
}
.mdc :deep(.row > *) {
    width: auto;
    max-width: none;
}
</style>
