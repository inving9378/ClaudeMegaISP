<template>
    <div class="mpc-wrap">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex align-items-center">
                <i class="mdi mdi-cash-register fs-4 text-primary me-2"></i>
                <h5 class="mb-0 fw-semibold">Registrar pago reportado</h5>
            </div>
            <div class="card-body">
                <!-- Buscar cliente -->
                <div class="mb-3 position-relative">
                    <label class="form-label fw-semibold">Cliente</label>
                    <div v-if="selectedClient" class="d-flex align-items-center justify-content-between border rounded p-2 bg-light">
                        <div>
                            <strong>{{ selectedClient.nombre }}</strong>
                            <span class="text-muted ms-2">#{{ selectedClient.client_id }}</span>
                            <div class="small">
                                <span class="badge bg-primary-subtle text-primary me-2" style="font-family:monospace">{{ selectedClient.referencia || 'sin referencia' }}</span>
                                <span :class="Number(selectedClient.balance) < 0 ? 'text-danger' : 'text-success'">Saldo: $ {{ selectedClient.balance }}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearClient">Cambiar</button>
                    </div>
                    <template v-else>
                        <input
                            v-model="search"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por nombre o referencia MEG…"
                            @input="onSearch"
                            autocomplete="off"
                        />
                        <div v-if="searching" class="small text-muted mt-1"><span class="spinner-border spinner-border-sm me-1"></span> Buscando…</div>
                        <ul v-if="results.length" class="list-group position-absolute w-100 shadow" style="z-index:20;max-height:260px;overflow:auto">
                            <li
                                v-for="r in results"
                                :key="r.client_id"
                                class="list-group-item list-group-item-action"
                                style="cursor:pointer"
                                @click="selectClient(r)"
                            >
                                <strong>{{ r.nombre }}</strong> <span class="text-muted">#{{ r.client_id }}</span>
                                <span class="badge bg-light text-dark ms-2" style="font-family:monospace">{{ r.referencia || '—' }}</span>
                            </li>
                        </ul>
                        <div v-if="!searching && searched && !results.length" class="small text-muted mt-1">Sin resultados.</div>
                    </template>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input v-model="form.amount" type="number" step="0.01" min="0.01" class="form-control" placeholder="0.00" />
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Fecha del pago</label>
                        <input v-model="form.fecha_pago" type="date" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Método</label>
                        <select v-model="form.method_of_payment_id" class="form-select">
                            <option v-for="m in methods" :key="m.id" :value="m.id">{{ m.type }}</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Clave de rastreo (SPEI)</label>
                        <input v-model="form.clave_rastreo" type="text" maxlength="40" class="form-control" placeholder="Ej. 2606300106270836900H" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Titular (ordenante)</label>
                        <input v-model="form.titular" type="text" maxlength="255" class="form-control" placeholder="Nombre de quien transfirió" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Banco origen</label>
                        <input v-model="form.banco_origen" type="text" maxlength="255" class="form-control" placeholder="Ej. BanCoppel" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Cuenta receptora</label>
                        <select v-model="form.receiver_account_id" class="form-select">
                            <option :value="null">— seleccionar —</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.banco }} · {{ a.nombre }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Comprobante (foto)</label>
                        <input ref="fileInput" type="file" accept="image/*,application/pdf" class="form-control" @change="onFile" />
                        <div class="form-text">JPG/PNG/PDF, máx 8 MB.</div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="btn btn-primary px-4" :disabled="!canSubmit || saving" @click="submit">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="mdi mdi-check me-1"></i>
                        {{ saving ? 'Guardando…' : 'Aplicar y registrar pago' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    name: "ManualPaymentCapture",
    props: {
        methods: { type: Array, default: () => [] },
        accounts: { type: Array, default: () => [] },
        defaultMethodId: { type: [Number, String], default: 2 },
    },
    setup(props) {
        const todayIso = () => new Date().toISOString().slice(0, 10);

        const search = ref("");
        const results = ref([]);
        const searching = ref(false);
        const searched = ref(false);
        const selectedClient = ref(null);
        const saving = ref(false);
        const fileInput = ref(null);
        let debounce = null;

        const form = reactive({
            amount: "",
            fecha_pago: todayIso(),
            method_of_payment_id: Number(props.defaultMethodId) || 2,
            clave_rastreo: "",
            titular: "",
            banco_origen: "",
            receiver_account_id: props.accounts.length === 1 ? props.accounts[0].id : null,
            comprobante: null,
        });

        const onSearch = () => {
            clearTimeout(debounce);
            const q = search.value.trim();
            if (q.length < 2) { results.value = []; searched.value = false; return; }
            debounce = setTimeout(async () => {
                searching.value = true;
                try {
                    const { data } = await axios.get("/finanzas/captura-pago/buscar-cliente", { params: { q } });
                    results.value = Array.isArray(data) ? data : [];
                } catch { results.value = []; }
                searching.value = false;
                searched.value = true;
            }, 300);
        };

        const selectClient = (r) => { selectedClient.value = r; results.value = []; search.value = ""; };
        const clearClient = () => { selectedClient.value = null; };
        const onFile = (e) => { form.comprobante = e.target.files?.[0] || null; };

        const canSubmit = computed(() =>
            selectedClient.value && Number(form.amount) > 0 && form.fecha_pago && form.method_of_payment_id
        );

        const submit = async () => {
            if (!canSubmit.value || saving.value) return;
            saving.value = true;
            try {
                const fd = new FormData();
                fd.append("client_id", selectedClient.value.client_id);
                fd.append("amount", form.amount);
                fd.append("fecha_pago", form.fecha_pago);
                fd.append("method_of_payment_id", form.method_of_payment_id);
                if (form.clave_rastreo) fd.append("clave_rastreo", form.clave_rastreo);
                if (form.titular) fd.append("titular", form.titular);
                if (form.banco_origen) fd.append("banco_origen", form.banco_origen);
                if (form.receiver_account_id) fd.append("receiver_account_id", form.receiver_account_id);
                if (form.comprobante) fd.append("comprobante", form.comprobante);

                const { data } = await axios.post("/finanzas/captura-pago", fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });
                if (data.ok) {
                    Swal.fire({ icon: "success", title: "Pago registrado", text: data.message, timer: 2200, showConfirmButton: false });
                    resetForm();
                } else {
                    Swal.fire("Error", data.message || "No se pudo registrar el pago.", "error");
                }
            } catch (e) {
                const msg = e.response?.data?.message
                    || (e.response?.data?.errors ? Object.values(e.response.data.errors).flat().join("\n") : null)
                    || "No se pudo registrar el pago.";
                Swal.fire("Error", msg, "error");
            } finally {
                saving.value = false;
            }
        };

        const resetForm = () => {
            selectedClient.value = null;
            form.amount = "";
            form.fecha_pago = todayIso();
            form.method_of_payment_id = Number(props.defaultMethodId) || 2;
            form.clave_rastreo = "";
            form.titular = "";
            form.banco_origen = "";
            form.receiver_account_id = props.accounts.length === 1 ? props.accounts[0].id : null;
            form.comprobante = null;
            if (fileInput.value) fileInput.value.value = "";
        };

        return { search, results, searching, searched, selectedClient, saving, fileInput, form, onSearch, selectClient, clearClient, onFile, canSubmit, submit };
    },
};
</script>

<style scoped>
.mpc-wrap { max-width: 900px; margin: 0 auto; }
</style>
