<template>
    <div class="ipv6-config-container q-pa-md">
        <div class="row items-center q-mb-md">
            <div class="text-h5">Configuración IPv6 — Alta de bloque</div>
        </div>
        <div class="text-caption text-grey-7 q-mb-md">
            Solo lectura hacia el router: detección de versión y mapeo de zonas marcadas
            (<code>MgNet-IPv6</code>). El cálculo del plan de direccionamiento es puro,
            nunca escribe en el router. Nada de esto se guarda todavía (persistencia pendiente).
        </div>

        <q-stepper v-model="step" flat bordered animated color="primary">
            <q-step :name="1" title="1. Alta del bloque" icon="dns" :done="step > 1">
                <q-card flat bordered class="q-pa-md">
                    <q-input
                        v-model="prefijoProveedor"
                        label="Prefijo del proveedor *"
                        hint="Único dato que MegaISP no puede saber, ej. 2001:db8::/48"
                        outlined
                        dense
                        style="max-width: 420px"
                    />

                    <q-select
                        v-model="routerId"
                        :options="routerOptions"
                        option-value="id"
                        option-label="label"
                        emit-value
                        map-options
                        label="Router destino *"
                        outlined
                        dense
                        :loading="loadingRouters"
                        class="q-mt-md"
                        style="max-width: 480px"
                        @update:model-value="onRouterChange"
                    />

                    <div class="q-mt-md">
                        <div class="text-caption text-grey-7">Versión RouterOS</div>
                        <div v-if="loadingVersion" class="row items-center q-gutter-sm q-mt-xs">
                            <q-spinner color="primary" size="20px" />
                            <span class="text-caption">Detectando versión…</span>
                        </div>
                        <div v-else-if="version && !forzarVersionManual" class="text-body2 q-mt-xs">
                            <b>{{ version.version || '—' }}</b>
                            <span class="text-grey-7">
                                · {{ version.board_name || '—' }} ({{ version.architecture_name || '—' }})
                            </span>
                            <q-btn flat dense size="sm" color="primary" label="Forzar manual"
                                   class="q-ml-sm" @click="forzarVersionManual = true" />
                        </div>

                        <q-banner v-if="versionError" class="bg-negative text-white q-mt-sm" dense>
                            {{ versionError }}
                        </q-banner>

                        <q-select
                            v-if="forzarVersionManual || (versionError && !loadingVersion)"
                            v-model="versionManual"
                            :options="versionManualOptions"
                            emit-value
                            map-options
                            label="Versión RouterOS (selector manual de respaldo)"
                            outlined
                            dense
                            style="max-width: 320px"
                            class="q-mt-sm"
                        />
                    </div>

                    <q-expansion-item
                        class="q-mt-lg ipv6-advanced"
                        icon="tune"
                        label="Opciones avanzadas"
                        header-class="text-primary"
                        dense-toggle
                    >
                        <div class="q-pa-md">
                            <div class="row q-gutter-md items-start">
                                <q-select
                                    v-model.number="opciones.longitudDelegacion"
                                    :options="longitudDelegacionOptions"
                                    emit-value
                                    map-options
                                    label="Longitud de delegación"
                                    hint="Recomendada por el calculador — rango /32 a /56"
                                    outlined
                                    dense
                                    style="width: 220px"
                                />
                                <q-input
                                    v-model.number="opciones.reservaInfraestructura"
                                    type="number"
                                    min="0"
                                    label="Reserva de infraestructura"
                                    hint="Bloques adicionales reservados, además del bloque base"
                                    outlined
                                    dense
                                    style="width: 220px"
                                />
                            </div>

                            <div class="row q-gutter-md items-start q-mt-sm">
                                <q-input
                                    v-model.number="opciones.clientesActuales"
                                    type="number"
                                    min="0"
                                    label="Clientes actuales"
                                    outlined
                                    dense
                                    style="width: 220px"
                                />
                                <q-input
                                    v-model.number="opciones.margenCrecimiento"
                                    type="number"
                                    step="0.1"
                                    min="0.1"
                                    label="Margen de crecimiento"
                                    outlined
                                    dense
                                    style="width: 220px"
                                />
                            </div>
                            <div class="text-caption text-grey-7 q-mt-xs">
                                {{ clientesActualesNota }}
                            </div>

                            <div class="text-subtitle2 q-mt-md">Datos de contrato</div>
                            <div class="row q-gutter-md items-start q-mt-xs">
                                <q-input
                                    v-model="opciones.contratoReferencia"
                                    label="Referencia / número de contrato"
                                    outlined
                                    dense
                                    style="width: 300px"
                                />
                            </div>
                            <q-input
                                v-model="opciones.contratoNotas"
                                type="textarea"
                                label="Notas de contrato"
                                outlined
                                dense
                                class="q-mt-sm"
                                style="max-width: 620px"
                            />
                        </div>
                    </q-expansion-item>
                </q-card>

                <q-stepper-navigation>
                    <q-btn
                        color="primary"
                        label="Continuar a mapeo de zonas"
                        :disable="!puedeContinuar"
                        :loading="loadingZonas"
                        @click="continuarAZonas"
                    />
                </q-stepper-navigation>
            </q-step>

            <q-step :name="2" title="2. Mapeo de zonas" icon="hub">
                <q-banner class="bg-blue-1 text-blue-10 q-mb-md" dense>
                    Filas descubiertas automáticamente en el router (marcador <code>MgNet-IPv6</code>).
                    Solo confirma cuáles deben incluirse en el plan — no se editan ni se capturan
                    zonas nuevas a mano aquí.
                </q-banner>

                <q-banner v-if="zonasError" class="bg-negative text-white q-mb-md" dense>
                    {{ zonasError }}
                </q-banner>

                <q-card flat bordered class="q-pa-md q-mb-md" v-if="topology">
                    <div class="text-subtitle1 q-mb-sm">
                        Candidatas a confirmar ({{ filasConfirmables.length }})
                    </div>

                    <q-table
                        v-if="filasConfirmables.length"
                        :rows="filasConfirmables"
                        :columns="columnasFilas"
                        row-key="key"
                        dense
                        flat
                        hide-pagination
                        :rows-per-page-options="[0]"
                    >
                        <template #body-cell-confirmar="props">
                            <q-td :props="props">
                                <q-toggle v-model="confirmaciones[props.row.key]" color="primary" />
                            </q-td>
                        </template>
                    </q-table>
                    <div v-else class="text-grey-6">
                        Sin candidatas marcadas con <code>MgNet-IPv6</code> en este router.
                    </div>

                    <div class="text-caption text-grey-7 q-mt-sm">
                        {{ totalConfirmadas }} de {{ filasConfirmables.length }} confirmadas.
                    </div>

                    <div class="text-subtitle2 q-mt-lg">
                        Zonas / distritos PPPoE detectados ({{ distritosDetectados.length }})
                    </div>
                    <div v-if="distritosDetectados.length" class="q-mt-xs">
                        <div v-for="d in distritosDetectados" :key="d.nombre" class="text-body2">
                            <b>{{ d.nombre }}</b> — {{ d.count }} servidor(es)
                        </div>
                    </div>
                    <div v-else class="text-grey-6 q-mt-xs">Sin distritos detectados.</div>

                    <div class="text-caption text-grey-7 q-mt-md">
                        IPv6 ya asignados en el router: {{ topology.ipv6_ya_asignados.length }}
                    </div>
                </q-card>

                <q-card flat bordered class="q-pa-md">
                    <div class="text-subtitle1 q-mb-sm">Vista previa del plan de direccionamiento</div>
                    <div class="text-caption text-grey-7 q-mb-md">
                        Cálculo puro con las zonas detectadas y las opciones avanzadas de la pantalla
                        anterior — nunca escribe en el router. Nada se persiste (pendiente #949).
                    </div>

                    <q-btn
                        color="primary"
                        label="Calcular vista previa"
                        :disable="!distritosDetectados.length || !prefijoProveedor"
                        :loading="loadingPlan"
                        @click="vistaPrevia"
                    />

                    <q-banner v-if="planError" class="bg-negative text-white q-mt-md" dense>
                        {{ planError }}
                    </q-banner>

                    <div v-if="plan" class="q-mt-md">
                        <div class="text-subtitle2">Plan calculado</div>
                        <pre class="ipv6-plan-pre">{{ JSON.stringify(plan, null, 2) }}</pre>

                        <div class="text-subtitle2 q-mt-md">Comandos ({{ driver }})</div>
                        <pre class="ipv6-plan-pre">{{ comandos.join('\n') }}</pre>

                        <div v-if="advertencias.length" class="text-subtitle2 q-mt-md text-warning">Advertencias</div>
                        <ul v-if="advertencias.length">
                            <li v-for="(a, i) in advertencias" :key="i">{{ a }}</li>
                        </ul>
                    </div>
                </q-card>

                <q-stepper-navigation>
                    <q-btn flat color="primary" label="Volver a alta del bloque" @click="step = 1" />
                </q-stepper-navigation>
            </q-step>
        </q-stepper>
    </div>
</template>

<script>
import { ref, reactive, computed, watch } from 'vue';
import axios from 'axios';

export default {
    name: 'Ipv6Config',
    setup() {
        const step = ref(1);

        const routers = ref([]);
        const routerId = ref(null);
        const loadingRouters = ref(false);

        const prefijoProveedor = ref('');

        const version = ref(null);
        const versionError = ref('');
        const loadingVersion = ref(false);
        const forzarVersionManual = ref(false);
        const versionManual = ref('7.13');
        const versionManualOptions = [
            { label: '6.x', value: '6.0' },
            { label: '7.0 – 7.12', value: '7.0' },
            { label: '7.13+', value: '7.13' },
        ];

        const longitudDelegacionOptions = [32, 36, 40, 44, 48, 52, 56].map((n) => ({
            label: '/' + n,
            value: n,
        }));

        const opciones = reactive({
            longitudDelegacion: 48,
            reservaInfraestructura: 1,
            clientesActuales: 0,
            margenCrecimiento: 1.5,
            contratoReferencia: '',
            contratoNotas: '',
        });
        const fuenteClientesReal = ref(false);

        const topology = ref(null);
        const zonasError = ref('');
        const loadingZonas = ref(false);
        const confirmaciones = reactive({});

        const plan = ref(null);
        const driver = ref('');
        const comandos = ref([]);
        const advertencias = ref([]);
        const planError = ref('');
        const loadingPlan = ref(false);

        const routerOptions = computed(() =>
            routers.value.map((r) => ({
                id: r.id,
                label: r.title + ' (' + r.ip_host + ')' + (r.conectable ? '' : ' — sin credenciales'),
                disable: !r.conectable,
            }))
        );

        const clientesActualesNota = computed(() => {
            if (fuenteClientesReal.value) {
                return 'Precargado del conteo real de clientes de internet activos en este router.';
            }
            return 'Sin fuente de conteo real para este router — se dejó en 0, ajusta manualmente.';
        });

        const puedeContinuar = computed(() => {
            const versionResuelta = (version.value && !forzarVersionManual.value) || !!versionManual.value;
            return !!prefijoProveedor.value && !!routerId.value && versionResuelta;
        });

        const versionEfectiva = computed(() => {
            if (forzarVersionManual.value || versionError.value) {
                return versionManual.value;
            }
            return (version.value && version.value.version) || versionManual.value;
        });

        const columnasFilas = [
            { name: 'tipo', label: 'Tipo', field: 'tipo', align: 'left' },
            { name: 'nombre', label: 'Nombre', field: 'nombre', align: 'left' },
            { name: 'detalle', label: 'Detalle', field: 'detalle', align: 'left' },
            { name: 'confirmar', label: 'Confirmar', field: 'confirmar', align: 'center' },
        ];

        const filasConfirmables = computed(() => {
            if (!topology.value) return [];
            const filas = [];

            (topology.value.vlans_candidatas || []).forEach((v, i) => {
                filas.push({
                    key: 'vlan-' + i,
                    tipo: 'VLAN',
                    nombre: v.interface + (v.vlan_id ? ' (VLAN ' + v.vlan_id + ')' : ''),
                    detalle: (v.comment || '') + (v.ya_asignado ? ' — ya tiene IPv6 asignado' : ''),
                });
            });
            (topology.value.ppp_profiles_candidatos || []).forEach((p, i) => {
                filas.push({
                    key: 'ppp-' + i,
                    tipo: 'Perfil PPP',
                    nombre: p.profile,
                    detalle: p.comment || '',
                });
            });
            (topology.value.queues_dedicadas_candidatas || []).forEach((q, i) => {
                filas.push({
                    key: 'queue-' + i,
                    tipo: 'Queue',
                    nombre: q.name,
                    detalle: (q.target || '') + (q.comment ? ' — ' + q.comment : ''),
                });
            });

            return filas;
        });

        watch(filasConfirmables, (filas) => {
            filas.forEach((f) => {
                if (!(f.key in confirmaciones)) {
                    confirmaciones[f.key] = false;
                }
            });
        });

        const totalConfirmadas = computed(
            () => Object.values(confirmaciones).filter(Boolean).length
        );

        const distritosDetectados = computed(() => {
            if (!topology.value) return [];
            const grupos = topology.value.pppoe_servers_por_distrito || {};
            return Object.keys(grupos).map((nombre) => ({
                nombre,
                count: (grupos[nombre] || []).length,
            }));
        });

        async function loadRouters() {
            loadingRouters.value = true;
            try {
                const { data } = await axios.get('/red/ipv6-config/routers');
                routers.value = data.routers || [];
            } catch (e) {
                console.error(e);
            } finally {
                loadingRouters.value = false;
            }
        }

        function onRouterChange() {
            version.value = null;
            versionError.value = '';
            forzarVersionManual.value = false;
            topology.value = null;

            const router = routers.value.find((r) => r.id === routerId.value);
            if (router && typeof router.clientes_activos === 'number') {
                opciones.clientesActuales = router.clientes_activos;
                fuenteClientesReal.value = true;
            } else {
                opciones.clientesActuales = 0;
                fuenteClientesReal.value = false;
            }

            const parsed = parsePrefixLen(prefijoProveedor.value);
            if (parsed) {
                opciones.longitudDelegacion = parsed;
            }

            if (routerId.value) {
                detectarVersion();
            }
        }

        function parsePrefixLen(prefijo) {
            const m = /\/(\d{1,3})\s*$/.exec((prefijo || '').trim());
            if (!m) return null;
            const n = parseInt(m[1], 10);
            if (n < 32 || n > 56) return null;
            return Math.ceil(n / 4) * 4;
        }

        async function detectarVersion() {
            version.value = null;
            versionError.value = '';
            loadingVersion.value = true;
            try {
                const { data } = await axios.post('/red/ipv6-config/detectar-version', {
                    router_id: routerId.value,
                });
                if (data.ok) {
                    version.value = data.version;
                } else {
                    versionError.value = data.error || 'No se pudo detectar la versión.';
                }
            } catch (e) {
                versionError.value = e.response?.data?.error || e.message;
            } finally {
                loadingVersion.value = false;
            }
        }

        async function mapearZonas() {
            zonasError.value = '';
            loadingZonas.value = true;
            try {
                const { data } = await axios.post('/red/ipv6-config/mapear-zonas', {
                    router_id: routerId.value,
                });
                if (data.ok) {
                    topology.value = data.topology;
                } else {
                    zonasError.value = data.error || 'No se pudieron mapear las zonas.';
                }
            } catch (e) {
                zonasError.value = e.response?.data?.error || e.message;
            } finally {
                loadingZonas.value = false;
            }
        }

        async function continuarAZonas() {
            if (!topology.value) {
                await mapearZonas();
            }
            step.value = 2;
        }

        async function vistaPrevia() {
            plan.value = null;
            planError.value = '';
            loadingPlan.value = true;
            try {
                const { data } = await axios.post('/red/ipv6-config/vista-previa', {
                    prefijo: prefijoProveedor.value,
                    clientes_actuales: opciones.clientesActuales,
                    margen_crecimiento: opciones.margenCrecimiento,
                    zonas: distritosDetectados.value.map((d) => d.nombre),
                    version: versionEfectiva.value,
                });
                if (data.ok) {
                    plan.value = data.plan;
                    driver.value = data.driver;
                    comandos.value = data.comandos;
                    advertencias.value = data.advertencias;
                } else {
                    planError.value = data.error || 'No se pudo calcular el plan.';
                }
            } catch (e) {
                planError.value = e.response?.data?.error || e.message;
            } finally {
                loadingPlan.value = false;
            }
        }

        loadRouters();

        return {
            step,
            routerId, routerOptions, loadingRouters, onRouterChange,
            prefijoProveedor,
            version, versionError, loadingVersion, forzarVersionManual, versionManual, versionManualOptions,
            longitudDelegacionOptions, opciones, clientesActualesNota,
            puedeContinuar, continuarAZonas,
            topology, zonasError, loadingZonas,
            filasConfirmables, columnasFilas, confirmaciones, totalConfirmadas, distritosDetectados,
            plan, driver, comandos, advertencias, planError, loadingPlan, vistaPrevia,
        };
    },
};
</script>

<style scoped>
.ipv6-plan-pre {
    background: #0f172a;
    color: #e2e8f0;
    padding: 12px;
    border-radius: 8px;
    overflow: auto;
    font-size: 12px;
    max-height: 360px;
}
</style>
