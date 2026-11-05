<template>
    <q-dialog :model-value="modelValue" persistent @update:model-value="(v) => $emit('update:modelValue', v)" @show="cargar">
        <q-card class="drd-card" :dark="darkMode">
            <q-card-section class="drd-encabezado">
                <div>
                    <div class="text-h6">Datos de red del dedicado</div>
                    <div class="text-caption drd-tenue">{{ subtitulo }}</div>
                </div>
                <q-btn icon="close" flat round dense aria-label="Cerrar" @click="cerrar" />
            </q-card-section>
            <q-separator :dark="darkMode" />

            <q-card-section v-if="cargando" class="drd-centro">
                <q-spinner size="32px" color="primary" />
            </q-card-section>

            <q-card-section v-else-if="errorCarga">
                <q-banner class="bg-negative text-white" rounded>{{ errorCarga }}</q-banner>
            </q-card-section>

            <q-card-section v-else class="drd-cuerpo">
                <q-banner v-for="(aviso, i) in avisos" :key="i" dense rounded class="drd-aviso q-mb-sm">
                    <template #avatar><q-icon name="info" color="primary" /></template>
                    {{ aviso }}
                </q-banner>
                <q-banner v-if="!puedeEditar" dense rounded class="drd-aviso q-mb-sm">
                    <template #avatar><q-icon name="lock" color="grey" /></template>
                    Solo lectura: para guardar se necesita el permiso «Editar los datos de red de un servicio dedicado».
                </q-banner>
                <q-banner v-if="errores.servicio" dense rounded class="bg-negative text-white q-mb-sm">{{ errores.servicio }}</q-banner>

                <div class="drd-campos">
                    <q-select
                        v-model="form.router_id"
                        :options="routers"
                        option-value="id"
                        option-label="title"
                        emit-value
                        map-options
                        label="Router"
                        outlined
                        dense
                        :dark="darkMode"
                        :readonly="!puedeEditar"
                        :error="!!errores.router_id"
                        :error-message="errores.router_id"
                    />
                    <q-input
                        v-model.number="form.vlan_id"
                        type="number"
                        label="VLAN ID"
                        outlined
                        dense
                        :dark="darkMode"
                        :readonly="!puedeEditar"
                        :error="!!errores.vlan_id"
                        :error-message="errores.vlan_id"
                    />
                    <q-input
                        v-model="form.interfaz_vlan"
                        class="drd-ancho"
                        label="Interfaz VLAN en el router"
                        hint="El nombre exacto, con sus espacios. Si no lo sabes, basta el VLAN ID."
                        outlined
                        dense
                        :dark="darkMode"
                        :readonly="!puedeEditar"
                        :error="!!errores.interfaz_vlan"
                        :error-message="errores.interfaz_vlan"
                    />
                    <q-input
                        v-model="form.bloque_cidr"
                        label="Bloque entregado (CIDR)"
                        hint="Por ejemplo 203.0.113.120/29. Corrígelo si el inventario no coincide."
                        outlined
                        dense
                        :dark="darkMode"
                        :readonly="!puedeEditar"
                        :error="!!errores.bloque_cidr || !!errorBloque"
                        :error-message="errores.bloque_cidr || errorBloque"
                    />
                    <q-select
                        v-model="form.ip_router"
                        :options="hosts"
                        label="IP del router en el bloque"
                        hint="Esta no se monitorea."
                        clearable
                        outlined
                        dense
                        :dark="darkMode"
                        :readonly="!puedeEditar"
                        :error="!!errores.ip_router"
                        :error-message="errores.ip_router"
                    />
                </div>
                <div class="drd-estado">
                    <span class="drd-punto" :class="monitoreo.activo === false ? 'drd-pausado' : 'drd-activo'" aria-hidden="true"></span>
                    <span>{{ monitoreo.activo === false ? `Monitoreo pausado: ${monitoreo.motivo}` : "Monitoreo activo" }}</span>
                    <q-badge v-if="monitoreo.automatica" color="amber-9" label="Automática, sin revisar" />
                </div>

                <div class="drd-subtitulo">IPs del bloque a monitorear</div>
                <div v-if="!filas.length" class="drd-tenue">Captura el bloque para ver sus IPs.</div>
                <q-markup-table v-else dense flat bordered :dark="darkMode" class="drd-tabla">
                    <thead>
                        <tr>
                            <th class="text-left">IP</th>
                            <th class="text-center">Monitorear</th>
                            <th class="text-left">Etiqueta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(f, i) in filas" :key="f.ip">
                            <td class="text-left">
                                {{ f.ip }}
                                <q-badge v-if="f.ip === form.ip_router" color="grey-7" label="router" class="q-ml-xs" />
                                <div v-if="errorDeIp(f.ip)" class="text-negative text-caption">{{ errorDeIp(f.ip) }}</div>
                            </td>
                            <td class="text-center">
                                <q-checkbox
                                    v-model="f.monitorear"
                                    dense
                                    :dark="darkMode"
                                    :disable="!puedeEditar || f.ip === form.ip_router"
                                />
                            </td>
                            <td class="text-left">
                                <q-input
                                    v-model="f.etiqueta"
                                    dense
                                    borderless
                                    placeholder="Ej. Equipo principal"
                                    :dark="darkMode"
                                    :readonly="!puedeEditar"
                                />
                            </td>
                        </tr>
                    </tbody>
                </q-markup-table>

                <ProbarLecturaResultado v-if="prueba" :r="prueba" :confirmando="confirmando" @confirmar="confirmarIdentidad" />
            </q-card-section>

            <q-card-actions align="right" class="drd-acciones">
                <q-btn
                    v-if="puedeEditar && !cargando && !errorCarga"
                    outline
                    color="primary"
                    no-caps
                    label="Probar lectura"
                    :loading="probando"
                    :disable="!sePuedeProbar"
                    @click="probar"
                >
                    <q-tooltip v-if="!sePuedeProbar">Guarda primero: se prueba lo que está guardado.</q-tooltip>
                </q-btn>
                <q-space />
                <q-btn flat label="Cerrar" @click="cerrar" />
                <q-btn v-if="puedeEditar && !cargando && !errorCarga" color="primary" label="Guardar" :loading="guardando" @click="guardar" />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script>
import { computed, nextTick, reactive, ref, watch } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { darkMode } from "../../../../../hook/appConfig";
import ProbarLecturaResultado from "./ProbarLecturaResultado.vue";

// #988: «Datos de red» de un servicio de internet dedicado. Guarda en sus propias tablas por
// /cliente/servicio-internet/{id}/datos-red; nunca guarda el servicio (eso manda trabajos al router).

const ipAEntero = (ip) => {
    const o = String(ip).trim().split(".");
    if (o.length !== 4 || o.some((x) => !/^\d{1,3}$/.test(x) || Number(x) > 255)) return null;
    return o.reduce((acc, x) => acc * 256 + Number(x), 0);
};
const enteroAIp = (n) => [24, 16, 8, 0].map((s) => Math.floor(n / 2 ** s) % 256).join(".");

/** IPs utilizables del bloque (sin red ni broadcast, salvo /31 y /32), o un error legible. */
const hostsDe = (cidr, prefijoMinimo) => {
    const m = String(cidr || "").match(/^\s*(\d{1,3}(?:\.\d{1,3}){3})\s*\/\s*(\d{1,2})\s*$/);
    if (!m) return { error: cidr ? "Escríbelo en CIDR, por ejemplo 203.0.113.120/29." : null, hosts: [] };
    const ip = ipAEntero(m[1]);
    const prefijo = Number(m[2]);
    if (ip === null || prefijo > 32) return { error: "El bloque no es válido.", hosts: [] };
    if (prefijo < prefijoMinimo) return { error: `El bloque es demasiado grande: como máximo /${prefijoMinimo}.`, hosts: [] };
    const tamano = 2 ** (32 - prefijo);
    const red = Math.floor(ip / tamano) * tamano;
    const [desde, hasta] = prefijo >= 31 ? [0, tamano - 1] : [1, tamano - 2];
    const hosts = [];
    for (let i = desde; i <= hasta; i++) hosts.push(enteroAIp(red + i));
    return { error: null, hosts };
};

export default {
    name: "DatosRedDedicado",
    components: { ProbarLecturaResultado },
    props: {
        modelValue: { type: Boolean, default: false },
        servicioId: { type: [Number, String], default: null },
    },
    emits: ["update:modelValue", "guardado"],
    setup(props, { emit }) {
        const cargando = ref(false);
        const guardando = ref(false);
        const errorCarga = ref(null);
        const servicio = ref(null);
        const routers = ref([]);
        const avisos = ref([]);
        const puedeEditar = ref(false);
        const prefijoMinimo = ref(24);
        const errores = ref({});
        const form = reactive({ router_id: null, interfaz_vlan: null, vlan_id: null, bloque_cidr: null, ip_router: null });
        const filas = ref([]);
        const monitoreo = ref({ activo: null, motivo: null, automatica: false });
        const propuesto = ref(false);
        const prueba = ref(null);
        const probando = ref(false);
        const confirmando = ref(false);
        // Lo que está guardado, para saber si «Probar lectura» probaría otra cosa que lo que se ve.
        const fotoGuardada = ref("");
        const foto = () => JSON.stringify([form, filas.value]);
        const sePuedeProbar = computed(() => !propuesto.value && foto() === fotoGuardada.value);

        const bloque = computed(() => hostsDe(form.bloque_cidr, prefijoMinimo.value));
        const hosts = computed(() => bloque.value.hosts);
        const errorBloque = computed(() => bloque.value.error);

        // Con lo guardado, no con lo que se va escribiendo.
        const vlanGuardada = ref(null);
        const subtitulo = computed(() => {
            if (!servicio.value) return "";
            const partes = [servicio.value.plan || servicio.value.descripcion || "Servicio de internet"];
            if (vlanGuardada.value) partes.unshift(`VLAN ${vlanGuardada.value}`);
            return `${partes.join(" · ")} (servicio #${servicio.value.id})`;
        });

        // Al cambiar el bloque se rehace la lista, conservando lo marcado de las IPs que siguen dentro.
        const rehacerFilas = (guardadas) => {
            const previas = Object.fromEntries((guardadas ?? filas.value).map((f) => [f.ip, f]));
            filas.value = hosts.value.map((ip) => ({
                ip,
                monitorear: previas[ip]?.monitorear ?? false,
                etiqueta: previas[ip]?.etiqueta ?? "",
            }));
            if (form.ip_router && !hosts.value.includes(form.ip_router)) form.ip_router = null;
        };
        watch(hosts, () => rehacerFilas());
        watch(() => form.ip_router, (ip) => {
            const f = filas.value.find((x) => x.ip === ip);
            if (f) f.monitorear = false;
        });

        const aplicar = (r) => {
            servicio.value = r.servicio;
            routers.value = r.routers;
            avisos.value = r.avisos || [];
            puedeEditar.value = !!r.puede_editar;
            prefijoMinimo.value = r.prefijo_minimo || 24;
            monitoreo.value = r.monitoreo || monitoreo.value;
            propuesto.value = !!r.propuesto;
            vlanGuardada.value = r.propuesto ? null : r.datos.vlan_id;
            Object.assign(form, {
                router_id: r.datos.router_id,
                interfaz_vlan: r.datos.interfaz_vlan,
                vlan_id: r.datos.vlan_id,
                bloque_cidr: r.datos.bloque_cidr,
                ip_router: r.datos.ip_router,
            });
            // hosts se recalcula en el siguiente tick; se arma con lo guardado.
            rehacerFilas(r.datos.ips || []);
            nextTick(() => (fotoGuardada.value = foto()));
        };

        const cargar = async () => {
            if (!props.servicioId) return;
            cargando.value = true;
            errorCarga.value = null;
            errores.value = {};
            prueba.value = null;
            try {
                const { data } = await axios.get(`/cliente/servicio-internet/${props.servicioId}/datos-red`);
                aplicar(data);
            } catch (e) {
                errorCarga.value = e.response?.data?.message || "No se pudieron cargar los datos de red.";
            } finally {
                cargando.value = false;
            }
        };

        const cerrar = () => emit("update:modelValue", false);

        const probar = async () => {
            probando.value = true;
            prueba.value = null;
            try {
                const { data } = await axios.post(`/cliente/servicio-internet/${props.servicioId}/datos-red/probar`, {}, { timeout: 25000 });
                prueba.value = data;
            } catch (e) {
                Swal.fire("Error", e.response?.status === 429 ? "Demasiadas pruebas seguidas: espera un minuto." : e.response?.data?.message || "No se pudo probar la lectura.", "error");
            } finally {
                probando.value = false;
            }
        };

        const confirmarIdentidad = async () => {
            confirmando.value = true;
            try {
                await axios.post(`/cliente/servicio-internet/${props.servicioId}/datos-red/identidad`);
                await probar();
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || "No se pudo confirmar el router.", "error");
            } finally {
                confirmando.value = false;
            }
        };

        const guardar = async () => {
            guardando.value = true;
            errores.value = {};
            try {
                const { data } = await axios.post(`/cliente/servicio-internet/${props.servicioId}/datos-red`, {
                    ...form,
                    vlan_id: form.vlan_id === "" ? null : form.vlan_id,
                    ips: filas.value
                        .filter((f) => f.monitorear || (f.etiqueta || "").trim() !== "")
                        .map((f) => ({ ip: f.ip, etiqueta: f.etiqueta, monitorear: !!f.monitorear })),
                });
                aplicar(data);
                emit("guardado", props.servicioId);
                cerrar();
                Swal.fire("Guardado", "Los datos de red del dedicado quedaron guardados.", "success");
            } catch (e) {
                if (e.response?.status === 422) {
                    errores.value = Object.fromEntries(Object.entries(e.response.data.errors || {}).map(([k, v]) => [k, v[0]]));
                } else {
                    Swal.fire("Error", e.response?.data?.message || "No se pudieron guardar los datos de red.", "error");
                }
            } finally {
                guardando.value = false;
            }
        };

        const errorDeIp = (ip) => {
            const i = filas.value.filter((f) => f.monitorear || (f.etiqueta || "").trim() !== "").findIndex((f) => f.ip === ip);
            return i >= 0 ? errores.value[`ips.${i}.ip`] : null;
        };

        return { darkMode, cerrar, monitoreo, prueba, probando, confirmando, sePuedeProbar, probar, confirmarIdentidad, cargando, guardando, errorCarga, servicio, routers, avisos, puedeEditar, errores, form, filas, hosts, errorBloque, subtitulo, cargar, guardar, errorDeIp };
    },
};
</script>

<style scoped>
.drd-card {
    width: 760px;
    max-width: 95vw;
}
.drd-encabezado {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.drd-centro {
    display: flex;
    justify-content: center;
    padding: 32px;
}
.drd-cuerpo {
    max-height: 70vh;
    overflow-y: auto;
}
.drd-campos {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 12px;
}
.drd-ancho {
    grid-column: 1 / -1;
}
.drd-subtitulo {
    font-weight: 600;
    margin: 12px 0 6px;
}
.drd-tenue {
    opacity: 0.7;
}
.drd-aviso {
    background: rgba(25, 118, 210, 0.08);
}
.drd-tabla td {
    vertical-align: middle;
}
.drd-acciones {
    margin: 0 !important;
}
.drd-estado {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 18px 0 10px;
    font-weight: 500;
}
.drd-punto {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}
.drd-activo {
    background: #16a34a;
}
.drd-pausado {
    background: #6b7280;
}
/* Quasar usa .row por dentro (el interruptor, los campos) y el Bootstrap global les pone ancho
   completo y relleno: se neutraliza solo dentro de esta ventana (mismo arreglo que FechaHoraOrden.vue). */
.drd-card :deep(.row) {
    --bs-gutter-x: 0;
    --bs-gutter-y: 0;
}
.drd-card :deep(.row > *) {
    width: auto;
    max-width: none;
}
@media (max-width: 600px) {
    .drd-campos {
        grid-template-columns: 1fr;
    }
}
</style>
