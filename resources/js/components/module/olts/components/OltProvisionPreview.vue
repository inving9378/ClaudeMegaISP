<template>
    <!-- Trigger button -->
    <q-btn
        v-if="hasProvisionPreview"
        flat
        dense
        color="info"
        icon="preview"
        :label="btnLabel"
        @click="open"
        :title="'Ver preview VRP (dry-run) para ' + sn"
    />

    <!-- Modal -->
    <q-dialog v-model="dialog" persistent maximized>
        <q-card :class="darkMode ? 'bg-dark text-white' : ''">
            <q-card-section class="row items-center q-pb-none">
                <div class="text-h6">
                    Preview de provisión — dry-run
                    <q-badge color="orange" label="DRY-RUN — sin envío a OLT" class="q-ml-sm" />
                </div>
                <q-space />
                <q-btn icon="close" flat round dense v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section class="scroll" style="max-height: calc(100vh - 120px)">
                <div class="row q-gutter-md">
                    <!-- Columna izquierda: formulario -->
                    <div class="col-12 col-md-5">
                        <div class="text-subtitle1 q-mb-sm">Parámetros de provisión</div>

                        <div class="row q-gutter-sm">
                            <div class="col-12">
                                <q-input v-model="form.sn" label="SN" dense outlined
                                    :dark="darkMode" hint="Número de serie del ONT (auto-relleno si viene de autofind)" />
                            </div>
                            <div class="col-12 col-sm-5">
                                <q-input v-model.number="form.olt_id" label="OLT ID" dense outlined type="number" :dark="darkMode" />
                            </div>
                            <div class="col col-sm-3">
                                <q-input v-model.number="form.slot" label="Slot (board)" dense outlined type="number" :dark="darkMode" />
                            </div>
                            <div class="col col-sm-3">
                                <q-input v-model.number="form.port" label="Puerto" dense outlined type="number" :dark="darkMode" />
                            </div>
                            <div class="col-12 col-sm-5">
                                <q-input v-model.number="form.ont_id" label="ONT-ID" dense outlined type="number" :dark="darkMode" hint="ID dentro del puerto GPON" />
                            </div>
                            <div class="col col-sm-3">
                                <q-input v-model.number="form.line_profile_id" label="Line-profile" dense outlined type="number" :dark="darkMode" />
                            </div>
                            <div class="col col-sm-3">
                                <q-input v-model.number="form.srv_profile_id" label="Srv-profile" dense outlined type="number" :dark="darkMode" />
                            </div>
                            <div class="col-12 col-sm-4">
                                <q-input v-model.number="form.svlan" label="SVLAN" dense outlined type="number" :dark="darkMode" />
                            </div>
                            <div class="col col-sm-4">
                                <q-input v-model.number="form.user_vlan" label="User-VLAN" dense outlined type="number" :dark="darkMode" />
                            </div>
                            <div class="col col-sm-3">
                                <q-input v-model="form.cvlan" label="CVLAN" dense outlined type="number" :dark="darkMode"
                                    hint="Vacío = single-tag" clearable />
                            </div>
                            <div class="col-12 col-sm-4">
                                <q-input v-model.number="form.gemport" label="GEM-port" dense outlined type="number" :dark="darkMode" />
                            </div>
                            <div class="col-12">
                                <q-input v-model="form.desc" label="Descripción" dense outlined :dark="darkMode" />
                            </div>
                        </div>

                        <div class="q-mt-md">
                            <q-btn label="Ejecutar preview" color="primary" @click="runPreview" :loading="loading" no-caps />
                        </div>
                    </div>

                    <!-- Columna derecha: resultados -->
                    <div class="col-12 col-md-6">
                        <!-- Pre-checks -->
                        <div v-if="result">
                            <div class="text-subtitle1 q-mb-xs">Pre-checks</div>
                            <q-list dense bordered separator class="rounded-borders q-mb-md">
                                <q-item v-for="c in result.checks" :key="c.check">
                                    <q-item-section avatar>
                                        <q-icon
                                            :name="c.ok === true ? 'check_circle' : c.ok === false ? 'cancel' : 'help'"
                                            :color="c.ok === true ? 'positive' : c.ok === false ? 'negative' : 'grey'"
                                        />
                                    </q-item-section>
                                    <q-item-section>
                                        <q-item-label>{{ c.label }}</q-item-label>
                                        <q-item-label caption>{{ c.message }}</q-item-label>
                                    </q-item-section>
                                    <q-item-section side>
                                        <q-badge
                                            :color="c.ok === true ? 'positive' : c.ok === false ? 'negative' : 'grey'"
                                            :label="c.ok === true ? 'OK' : c.ok === false ? 'FALLO' : 'OMITIDO'"
                                        />
                                    </q-item-section>
                                </q-item>
                            </q-list>

                            <q-banner
                                v-if="!result.all_passed"
                                dense
                                class="bg-warning text-dark q-mb-md rounded-borders"
                            >
                                <template v-slot:avatar><q-icon name="warning" /></template>
                                Uno o más checks fallaron. Revisar antes de continuar.
                            </q-banner>

                            <!-- Comandos VRP -->
                            <div class="text-subtitle1 q-mb-xs">
                                Secuencia VRP (dry-run)
                                <q-badge color="orange" label="No se enviarán a la OLT" class="q-ml-sm" />
                            </div>
                            <q-card flat bordered class="q-mb-md">
                                <q-card-section class="q-pa-sm">
                                    <pre class="text-caption q-mb-none" style="white-space:pre-wrap; font-family:monospace;">{{ result.commands.join('\n') }}</pre>
                                </q-card-section>
                            </q-card>

                            <!-- Comparación W0 SmartOLT -->
                            <div v-if="result.reference">
                                <div class="text-subtitle1 q-mb-xs">
                                    Comparación SmartOLT W0
                                    <q-badge color="teal" :label="result.reference.source" class="q-ml-sm" />
                                </div>
                                <q-markup-table dense flat bordered class="q-mb-md">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Campo</th>
                                            <th class="text-left">W0 (SmartOLT)</th>
                                            <th class="text-left">Preview</th>
                                            <th class="text-left">Match</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>SVLAN</td>
                                            <td>{{ result.reference.svlan }}</td>
                                            <td>{{ form.svlan }}</td>
                                            <td><q-icon :name="result.reference.svlan == form.svlan ? 'check' : 'close'" :color="result.reference.svlan == form.svlan ? 'positive' : 'negative'" /></td>
                                        </tr>
                                        <tr>
                                            <td>CVLAN</td>
                                            <td>{{ result.reference.cvlan }}</td>
                                            <td>{{ form.cvlan }}</td>
                                            <td><q-icon :name="result.reference.cvlan == form.cvlan ? 'check' : 'close'" :color="result.reference.cvlan == form.cvlan ? 'positive' : 'negative'" /></td>
                                        </tr>
                                        <tr>
                                            <td>User-VLAN</td>
                                            <td>{{ result.reference.user_vlan }}</td>
                                            <td>{{ form.user_vlan }}</td>
                                            <td><q-icon :name="result.reference.user_vlan == form.user_vlan ? 'check' : 'close'" :color="result.reference.user_vlan == form.user_vlan ? 'positive' : 'negative'" /></td>
                                        </tr>
                                        <tr>
                                            <td>GEM-port</td>
                                            <td>{{ result.reference.gemport }}</td>
                                            <td>{{ form.gemport }}</td>
                                            <td><q-icon :name="result.reference.gemport == form.gemport ? 'check' : 'close'" :color="result.reference.gemport == form.gemport ? 'positive' : 'negative'" /></td>
                                        </tr>
                                        <tr>
                                            <td>Service-port state</td>
                                            <td>{{ result.reference.state }}</td>
                                            <td>—</td>
                                            <td>—</td>
                                        </tr>
                                    </tbody>
                                </q-markup-table>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else-if="!loading" class="text-center text-grey q-mt-xl">
                            <q-icon name="preview" size="3rem" />
                            <div class="q-mt-sm">Completa el formulario y presiona "Ejecutar preview".</div>
                        </div>

                        <!-- Loading -->
                        <div v-if="loading" class="text-center q-mt-xl">
                            <q-spinner-dots color="primary" size="3rem" />
                            <div class="q-mt-sm text-caption">Ejecutando pre-checks...</div>
                        </div>
                    </div>
                </div>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, computed } from "vue";
import { darkMode } from "../../../../hook/appConfig";
import axios from "axios";

defineOptions({ name: "OltProvisionPreview" });

const props = defineProps({
    sn: { type: String, default: "" },
    oltId: { type: Number, default: 0 },
    slot: { type: Number, default: 0 },
    port: { type: Number, default: 0 },
    hasPermission: Object,
    btnLabel: { type: String, default: "" },
});

const dialog  = ref(false);
const loading = ref(false);
const result  = ref(null);

const hasProvisionPreview = computed(() =>
    props.hasPermission?.data.canView("gestion-red.provision.preview") ?? false
);

const form = ref({
    sn:              props.sn || "",
    olt_id:          props.oltId || 3,   // default lab OLT id=3 (MEGANETWORKX7)
    slot:            props.slot || 3,
    port:            props.port || 2,
    ont_id:          0,
    line_profile_id: 1,
    srv_profile_id:  2,
    svlan:           105,
    user_vlan:       200,
    cvlan:           200,
    gemport:         1,
    desc:            "",
});

const open = () => {
    // Refresh SN from prop each time the modal opens
    form.value.sn = props.sn || form.value.sn;
    result.value  = null;
    dialog.value  = true;
};

const runPreview = async () => {
    loading.value = true;
    result.value  = null;

    try {
        const resp = await axios.post("/olts/provision/preview", {
            ...form.value,
            cvlan: form.value.cvlan !== "" && form.value.cvlan !== null
                ? Number(form.value.cvlan) : null,
        });
        result.value = resp.data;
    } catch (e) {
        const msg = e.response?.data?.message || e.response?.data?.error || e.message;
        result.value = {
            dry_run: true,
            sn: form.value.sn,
            checks: [],
            all_passed: false,
            commands: [],
            reference: null,
            _error: msg,
        };
    } finally {
        loading.value = false;
    }
};
</script>
