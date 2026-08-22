<template>
    <div class="ipv6-config-container q-pa-md">
        <div class="row items-center q-mb-md">
            <div class="text-h5">Configuración IPv6</div>
        </div>
        <div class="text-caption text-grey-7 q-mb-md">
            Solo lectura hacia el router: detección de versión y mapeo de zonas marcadas
            (<code>MgNet-IPv6</code>). La vista previa del plan de direccionamiento es cálculo puro,
            nunca escribe en el router.
        </div>

        <q-card flat bordered class="q-pa-md q-mb-md">
            <div class="text-subtitle1 q-mb-sm">1. Router</div>
            <q-select
                v-model="routerId"
                :options="routerOptions"
                option-value="id"
                option-label="label"
                emit-value
                map-options
                label="Router registrado"
                outlined
                dense
                :loading="loadingRouters"
                style="max-width: 480px"
            />

            <div class="row q-gutter-sm q-mt-md">
                <q-btn
                    color="primary"
                    label="Detectar versión"
                    :disable="!routerId"
                    :loading="loadingVersion"
                    @click="detectarVersion"
                />
                <q-btn
                    color="secondary"
                    label="Mapear zonas"
                    :disable="!routerId"
                    :loading="loadingZonas"
                    @click="mapearZonas"
                />
            </div>

            <q-banner v-if="versionError" class="bg-negative text-white q-mt-md" dense>
                {{ versionError }}
            </q-banner>

            <div v-if="version" class="q-mt-md">
                <div class="text-body2">
                    <b>Versión:</b> {{ version.version || '—' }} ·
                    <b>Board:</b> {{ version.board_name || '—' }} ·
                    <b>Arquitectura:</b> {{ version.architecture_name || '—' }} ·
                    <b>Familia:</b> {{ version.family }} ({{ version.family_source }})
                </div>
            </div>

            <q-select
                v-if="versionError"
                v-model="familyOverride"
                :options="familyOptions"
                emit-value
                map-options
                label="Familia (selector manual de respaldo)"
                outlined
                dense
                clearable
                style="max-width: 320px"
                class="q-mt-sm"
            />
        </q-card>

        <q-card v-if="topology" flat bordered class="q-pa-md q-mb-md">
            <div class="text-subtitle1 q-mb-sm">2. Zonas mapeadas</div>

            <div class="text-caption text-grey-7 q-mb-xs">
                VLANs candidatas ({{ topology.vlans_candidatas.length }})
            </div>
            <q-list bordered dense class="q-mb-md" v-if="topology.vlans_candidatas.length">
                <q-item v-for="(v, i) in topology.vlans_candidatas" :key="'vlan-' + i">
                    <q-item-section>
                        <q-item-label>{{ v.interface }} (VLAN {{ v.vlan_id }})</q-item-label>
                        <q-item-label caption>
                            {{ v.comment }} — {{ v.ya_asignado ? 'ya tiene IPv6 asignado' : 'sin IPv6 asignado' }}
                        </q-item-label>
                    </q-item-section>
                </q-item>
            </q-list>
            <div v-else class="text-grey-6 q-mb-md">Sin VLANs marcadas.</div>

            <div class="text-caption text-grey-7 q-mb-xs">
                Perfiles PPP candidatos ({{ topology.ppp_profiles_candidatos.length }})
            </div>
            <q-list bordered dense class="q-mb-md" v-if="topology.ppp_profiles_candidatos.length">
                <q-item v-for="(p, i) in topology.ppp_profiles_candidatos" :key="'ppp-' + i">
                    <q-item-section>
                        <q-item-label>{{ p.profile }}</q-item-label>
                        <q-item-label caption>{{ p.comment }}</q-item-label>
                    </q-item-section>
                </q-item>
            </q-list>
            <div v-else class="text-grey-6 q-mb-md">Sin perfiles PPP marcados.</div>

            <div class="text-caption text-grey-7 q-mb-xs">
                Servidores PPPoE por distrito ({{ Object.keys(topology.pppoe_servers_por_distrito).length }})
            </div>
            <div v-for="(servers, distrito) in topology.pppoe_servers_por_distrito" :key="distrito" class="q-mb-sm">
                <b>{{ distrito }}</b>: {{ servers.length }} servidor(es)
            </div>

            <div class="text-caption text-grey-7 q-mb-xs q-mt-md">
                IPv6 ya asignados ({{ topology.ipv6_ya_asignados.length }})
            </div>
            <div class="text-caption text-grey-7 q-mb-xs q-mt-md">
                Queues dedicadas candidatas ({{ topology.queues_dedicadas_candidatas.length }})
            </div>
        </q-card>
        <q-banner v-if="zonasError" class="bg-negative text-white q-mb-md" dense>
            {{ zonasError }}
        </q-banner>

        <q-card flat bordered class="q-pa-md">
            <div class="text-subtitle1 q-mb-sm">3. Vista previa del plan de direccionamiento</div>
            <div class="text-caption text-grey-7 q-mb-md">
                Cálculo puro — nunca escribe en el router.
            </div>

            <div class="row q-gutter-md">
                <q-input v-model="planForm.prefijo" label="Prefijo delegado *" outlined dense
                         hint="ej. 2001:db8::/48" style="width: 260px" />
                <q-input v-model.number="planForm.clientes_actuales" type="number" label="Clientes actuales *"
                         outlined dense style="width: 180px" />
                <q-input v-model.number="planForm.margen_crecimiento" type="number" step="0.1"
                         label="Margen de crecimiento *" outlined dense style="width: 200px" />
                <q-input v-model="planForm.version" label="Versión RouterOS *" outlined dense
                         hint="ej. 7.14.2" style="width: 180px" />
            </div>

            <div class="q-mt-md">
                <div class="text-caption text-grey-7 q-mb-xs">Zonas (distritos PPPoE)</div>
                <div class="row q-gutter-sm items-center q-mb-xs" v-for="(z, i) in planForm.zonas" :key="i">
                    <q-input v-model="planForm.zonas[i]" outlined dense style="width: 260px" :label="'Zona ' + (i + 1)" />
                    <q-btn flat dense round icon="close" color="negative" @click="planForm.zonas.splice(i, 1)"
                           v-if="planForm.zonas.length > 1" />
                </div>
                <q-btn flat dense icon="add" label="Agregar zona" @click="planForm.zonas.push('')" />
            </div>

            <div class="q-mt-md">
                <q-btn color="primary" label="Calcular vista previa" :loading="loadingPlan" @click="vistaPrevia" />
            </div>

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
    </div>
</template>

<script>
import { ref, reactive, computed } from 'vue';
import axios from 'axios';

export default {
    name: 'Ipv6Config',
    setup() {
        const routers = ref([]);
        const routerId = ref(null);
        const loadingRouters = ref(false);

        const version = ref(null);
        const versionError = ref('');
        const loadingVersion = ref(false);
        const familyOverride = ref(null);
        const familyOptions = [
            { label: 'x86 / CHR', value: 'x86' },
            { label: 'ARM', value: 'arm' },
            { label: 'ARM64', value: 'arm64' },
            { label: 'MIPSBE', value: 'mipsbe' },
            { label: 'TILE', value: 'tile' },
            { label: 'PPC', value: 'ppc' },
        ];

        const topology = ref(null);
        const zonasError = ref('');
        const loadingZonas = ref(false);

        const planForm = reactive({
            prefijo: '',
            clientes_actuales: 0,
            margen_crecimiento: 1.5,
            zonas: [''],
            version: '',
        });
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

        async function detectarVersion() {
            version.value = null;
            versionError.value = '';
            loadingVersion.value = true;
            try {
                const { data } = await axios.post('/red/ipv6-config/detectar-version', {
                    router_id: routerId.value,
                    family_override: familyOverride.value,
                });
                if (data.ok) {
                    version.value = data.version;
                    if (data.version.version) {
                        planForm.version = data.version.version;
                    }
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
            topology.value = null;
            zonasError.value = '';
            loadingZonas.value = true;
            try {
                const { data } = await axios.post('/red/ipv6-config/mapear-zonas', {
                    router_id: routerId.value,
                    family_override: familyOverride.value,
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

        async function vistaPrevia() {
            plan.value = null;
            planError.value = '';
            loadingPlan.value = true;
            try {
                const { data } = await axios.post('/red/ipv6-config/vista-previa', {
                    prefijo: planForm.prefijo,
                    clientes_actuales: planForm.clientes_actuales,
                    margen_crecimiento: planForm.margen_crecimiento,
                    zonas: planForm.zonas.filter((z) => z.trim() !== ''),
                    version: planForm.version,
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
            routerId, routerOptions, loadingRouters,
            version, versionError, loadingVersion, familyOverride, familyOptions,
            topology, zonasError, loadingZonas,
            planForm, plan, driver, comandos, advertencias, planError, loadingPlan,
            detectarVersion, mapearZonas, vistaPrevia,
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
