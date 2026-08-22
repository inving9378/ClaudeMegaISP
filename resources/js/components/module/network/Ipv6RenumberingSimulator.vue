<template>
    <div class="ipv6-sim-container q-pa-md">
        <q-banner class="bg-negative text-white q-mb-md ipv6-sim-banner" dense rounded>
            <template #avatar><q-icon name="warning" /></template>
            <b>SIMULACIÓN — NO EJECUTA CONTRA EL ROUTER.</b>
            Solo genera comandos de referencia y datos de prueba, exclusivamente para revisión de Irving.
        </q-banner>

        <div class="text-h5 q-mb-xs">Simulador de renumeración IPv6 (RFC4192)</div>
        <div class="text-caption text-grey-7 q-mb-md">
            Genera, para un plan de direccionamiento, los comandos de referencia de las 4 fases de
            renumeración (A convivencia · B deprecación · C vigilancia · D retiro). Todos los comandos
            se muestran en solo lectura con botón copiar — no hay ningún botón ejecutar/aplicar en
            esta pantalla.
        </div>

        <q-card flat bordered class="q-pa-md q-mb-md">
            <div class="text-subtitle1 q-mb-sm">Datos del plan</div>
            <div class="row q-gutter-md items-start">
                <q-input
                    v-model="prefijo"
                    label="Prefijo del proveedor *"
                    hint="Ej. 2001:db8::/48"
                    outlined
                    dense
                    style="width: 260px"
                />
                <q-input
                    v-model.number="clientesActuales"
                    type="number"
                    min="0"
                    label="Clientes actuales"
                    outlined
                    dense
                    style="width: 180px"
                />
                <q-input
                    v-model.number="margenCrecimiento"
                    type="number"
                    step="0.1"
                    min="0.1"
                    label="Margen de crecimiento"
                    outlined
                    dense
                    style="width: 180px"
                />
                <q-select
                    v-model="version"
                    :options="versionOptions"
                    emit-value
                    map-options
                    label="Versión RouterOS"
                    outlined
                    dense
                    style="width: 180px"
                />
            </div>
            <q-input
                v-model="zonasTexto"
                label="Zonas / distritos (separados por coma)"
                hint="Ej. norte, sur, centro"
                outlined
                dense
                class="q-mt-md"
                style="max-width: 480px"
            />

            <q-banner v-if="planError" class="bg-negative text-white q-mt-md" dense>
                {{ planError }}
            </q-banner>

            <q-btn
                color="primary"
                class="q-mt-md"
                icon="play_circle"
                :label="plan ? 'Regenerar simulación' : 'Generar simulación'"
                :disable="!puedeGenerar"
                :loading="loadingPlan"
                @click="generarSimulacion"
            />
        </q-card>

        <template v-if="plan">
            <q-tabs v-model="fase" dense active-color="primary" indicator-color="primary" align="left" class="q-mb-md">
                <q-tab name="A" label="A · Convivencia" />
                <q-tab name="B" label="B · Deprecación" />
                <q-tab name="C" label="C · Vigilancia" />
                <q-tab name="D" label="D · Retiro" />
            </q-tabs>

            <q-tab-panels v-model="fase" animated>
                <!-- Fase A — Convivencia -->
                <q-tab-panel name="A">
                    <q-card flat bordered class="q-pa-md">
                        <div class="text-subtitle1 q-mb-sm">A · Convivencia (dual-stack)</div>
                        <div class="text-body2 text-grey-8 q-mb-md">
                            Se activa el nuevo direccionamiento en paralelo al viejo. Ambos prefijos conviven
                            mientras los clientes migran gradualmente. Comandos idénticos a los de la
                            Vista previa del plan (fases 1.4-1.7, ya en producción).
                        </div>
                        <div class="row items-center justify-between q-mb-sm">
                            <div class="text-caption text-grey-7">Comandos ({{ driver }})</div>
                            <q-btn flat dense color="primary" icon="content_copy" label="Copiar"
                                   @click="copiar(comandosA)" />
                        </div>
                        <pre class="ipv6-sim-pre">{{ comandosA.join('\n') }}</pre>
                    </q-card>
                </q-tab-panel>

                <!-- Fase B — Deprecación -->
                <q-tab-panel name="B">
                    <q-card flat bordered class="q-pa-md">
                        <div class="text-subtitle1 q-mb-sm">B · Deprecación</div>
                        <div class="text-body2 text-grey-8 q-mb-md">
                            El prefijo viejo se marca como deprecado (se deshabilita y se etiqueta) para
                            que los clientes dejen de preferirlo, sin retirarlo todavía. Da tiempo a que
                            el tráfico migre solo al nuevo prefijo antes del retiro (fase D).
                        </div>
                        <div class="row items-center justify-between q-mb-sm">
                            <div class="text-caption text-grey-7">Comandos ({{ driver }})</div>
                            <q-btn flat dense color="primary" icon="content_copy" label="Copiar"
                                   @click="copiar(comandosB)" />
                        </div>
                        <pre class="ipv6-sim-pre">{{ comandosB.join('\n') }}</pre>
                    </q-card>
                </q-tab-panel>

                <!-- Fase C — Vigilancia -->
                <q-tab-panel name="C">
                    <q-card flat bordered class="q-pa-md">
                        <div class="text-subtitle1 q-mb-sm">C · Vigilancia</div>
                        <div class="text-body2 text-grey-8 q-mb-md">
                            Se vigila el porcentaje de clientes que ya migraron al nuevo prefijo antes de
                            decidir el retiro. Esta fase no genera comandos.
                        </div>
                        <q-banner class="bg-orange-2 text-orange-10 q-mb-md" dense icon="science">
                            Datos de prueba, sin conexión a clientes reales.
                        </q-banner>

                        <div class="row items-center q-gutter-lg q-mb-md">
                            <q-circular-progress
                                :value="progresoGlobal"
                                size="90px"
                                :thickness="0.2"
                                color="primary"
                                track-color="grey-3"
                                show-value
                                class="text-subtitle1"
                            >
                                {{ progresoGlobal }}%
                            </q-circular-progress>
                            <div class="text-body2 text-grey-8">
                                Promedio simulado de clientes migrados al nuevo prefijo, entre todas las
                                zonas del plan.
                            </div>
                        </div>

                        <q-table
                            :rows="progresoPorZona"
                            :columns="columnasProgreso"
                            row-key="nombre"
                            dense
                            flat
                            hide-pagination
                            :rows-per-page-options="[0]"
                        >
                            <template #body-cell-progreso="props">
                                <q-td :props="props">
                                    <q-linear-progress
                                        :value="props.row.pct / 100"
                                        size="14px"
                                        color="primary"
                                        track-color="grey-3"
                                        rounded
                                        style="width: 160px"
                                    />
                                    <span class="q-ml-sm text-caption">{{ props.row.pct }}%</span>
                                </q-td>
                            </template>
                        </q-table>
                    </q-card>
                </q-tab-panel>

                <!-- Fase D — Retiro -->
                <q-tab-panel name="D">
                    <q-card flat bordered class="q-pa-md">
                        <div class="text-subtitle1 q-mb-sm">D · Retiro</div>
                        <div class="text-body2 text-grey-8 q-mb-md">
                            Una vez confirmada la migración completa (fase C), se retira del router el
                            direccionamiento viejo marcado por el sistema.
                        </div>
                        <q-banner class="bg-negative text-white q-mb-md" dense icon="lock">
                            Marcador de seguridad: <code>requiere_confirmacion = true</code> — esta fase
                            JAMÁS se ejecuta automáticamente, ni desde este simulador ni desde ningún
                            botón de esta pantalla.
                        </q-banner>
                        <div class="row items-center justify-between q-mb-sm">
                            <div class="text-caption text-grey-7">Comandos ({{ driver }})</div>
                            <q-btn flat dense color="primary" icon="content_copy" label="Copiar"
                                   @click="copiar(comandosD)" />
                        </div>
                        <pre class="ipv6-sim-pre">{{ comandosD.join('\n') }}</pre>
                    </q-card>
                </q-tab-panel>
            </q-tab-panels>
        </template>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import axios from 'axios';
import { message } from '../../../helpers/toastMsg';

/** Marcador que ya usan en producción los comandos de alta (Ipv6CommandGenerator/AbstractRouterOsDriver). */
const MARCADOR = 'MgNet-IPv6';

/** Hash corto y determinista (mismo nombre de zona → mismo % simulado siempre). */
function pctSimulado(semilla) {
    let h = 0;
    for (let i = 0; i < semilla.length; i++) {
        h = (h * 31 + semilla.charCodeAt(i)) >>> 0;
    }
    return 15 + (h % 81); // rango 15-95
}

export default {
    name: 'Ipv6RenumberingSimulator',
    setup() {
        const prefijo = ref('');
        const clientesActuales = ref(0);
        const margenCrecimiento = ref(1.5);
        const zonasTexto = ref('norte, sur, centro');
        const version = ref('7.13');
        const versionOptions = [
            { label: '6.x', value: '6.0' },
            { label: '7.0 – 7.12', value: '7.0' },
            { label: '7.13+', value: '7.13' },
        ];

        const plan = ref(null);
        const driver = ref('');
        const comandosA = ref([]);
        const planError = ref('');
        const loadingPlan = ref(false);
        const fase = ref('A');

        const zonas = computed(() =>
            zonasTexto.value.split(',').map((z) => z.trim()).filter(Boolean)
        );

        const puedeGenerar = computed(() => !!prefijo.value && zonas.value.length > 0);

        async function generarSimulacion() {
            plan.value = null;
            planError.value = '';
            loadingPlan.value = true;
            try {
                // Reusa el endpoint YA EXISTENTE y read-only de vista previa (fases 1.4-1.7,
                // Ipv6AddressPlanCalculator + Ipv6CommandGenerator) para la fase A. Nunca
                // instancia MikrotikIpv6Client ni escribe en ningún router.
                const { data } = await axios.post('/red/ipv6-config/vista-previa', {
                    prefijo: prefijo.value,
                    clientes_actuales: clientesActuales.value,
                    margen_crecimiento: margenCrecimiento.value,
                    zonas: zonas.value,
                    version: version.value,
                });
                if (data.ok) {
                    plan.value = data.plan;
                    driver.value = data.driver;
                    comandosA.value = data.comandos;
                    fase.value = 'A';
                } else {
                    planError.value = data.error || 'No se pudo calcular el plan.';
                }
            } catch (e) {
                planError.value = e.response?.data?.error || e.message;
            } finally {
                loadingPlan.value = false;
            }
        }

        // Fase B — mismo patrón que AbstractRouterOsDriver::deprecarPrefijo() (ya en
        // producción, comandos reales de deshabilitar + comentar), aplicado a cada
        // bloque del plan. Generado en el cliente: no llama a ningún endpoint nuevo.
        const comandosB = computed(() => {
            if (!plan.value) return [];
            const segs = plan.value.segmentos;
            const bloques = [
                segs.infraestructura.prefijo,
                ...segs.zonas.map((z) => z.prefijo),
                segs.empresarial.prefijo,
            ];
            const lineas = [];
            bloques.forEach((prefijoBloque) => {
                lineas.push(`/ipv6 address disable [find address="${prefijoBloque}"]`);
                lineas.push(`/ipv6 address set [find address="${prefijoBloque}"] comment="${MARCADOR} DEPRECADO"`);
            });
            return lineas;
        });

        // Fase C — porcentaje simulado por zona (determinista por nombre) + promedio global.
        const progresoPorZona = computed(() => {
            if (!plan.value) return [];
            return plan.value.segmentos.zonas.map((z) => ({
                nombre: z.nombre,
                pct: pctSimulado(z.nombre),
            }));
        });
        const columnasProgreso = [
            { name: 'nombre', label: 'Zona', field: 'nombre', align: 'left' },
            { name: 'progreso', label: '% migrados (simulado)', field: 'pct', align: 'left' },
        ];
        const progresoGlobal = computed(() => {
            const filas = progresoPorZona.value;
            if (!filas.length) return 0;
            return Math.round(filas.reduce((acc, f) => acc + f.pct, 0) / filas.length);
        });

        // Fase D — mismo patrón que AbstractRouterOsDriver::eliminarPorMarcador() (ya en
        // producción, comandos reales de baja por marcador), uno por cada tipo de recurso
        // que crea la fase A (address/route/pool/firewall).
        const comandosD = computed(() => {
            if (!plan.value) return [];
            return [
                `/ipv6 address remove [find comment~"${MARCADOR}"]`,
                `/ipv6 route remove [find comment~"${MARCADOR}"]`,
                `/ipv6 pool remove [find comment~"${MARCADOR}"]`,
                `/ipv6 firewall address-list remove [find comment~"${MARCADOR}"]`,
            ];
        });

        // Copiar — navigator.clipboard con fallback execCommand (contexto http sin TLS).
        async function copiar(lista) {
            const texto = (lista || []).join('\n');
            if (!texto) return;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(texto);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = texto;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                message('Comandos copiados');
            } catch (e) {
                message('No se pudieron copiar los comandos', 'error');
            }
        }

        return {
            prefijo, clientesActuales, margenCrecimiento, zonasTexto, version, versionOptions,
            plan, driver, comandosA, planError, loadingPlan, puedeGenerar, generarSimulacion,
            fase, comandosB, progresoPorZona, columnasProgreso, progresoGlobal, comandosD, copiar,
        };
    },
};
</script>

<style scoped>
.ipv6-sim-pre {
    background: #0f172a;
    color: #e2e8f0;
    padding: 12px;
    border-radius: 8px;
    overflow: auto;
    font-size: 12px;
    max-height: 360px;
}
.ipv6-sim-banner {
    position: sticky;
    top: 0;
    z-index: 1;
}
</style>
