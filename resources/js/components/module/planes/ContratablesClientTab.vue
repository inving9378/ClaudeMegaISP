<template>
    <div class="contratables-client-tab mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="fa fa-cubes me-2"></i>Servicios contratados</h5>
            <button class="btn btn-light btn-sm" @click="load" :disabled="loading">
                <i class="fa fa-rotate me-1"></i>Actualizar
            </button>
        </div>

        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary"></div>
        </div>

        <div v-else-if="!rows.length" class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fa fa-cubes fa-2x mb-2 d-block"></i>
                No hay servicios contratables activos en el catálogo.
            </div>
        </div>

        <div v-else class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th class="text-center">Unidades</th>
                        <th>Paquete actual</th>
                        <th class="text-end">Precio mensual (IVA incl.)</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.service_id">
                        <td>
                            <div class="fw-semibold">{{ r.nombre }}</div>
                            <small class="text-muted">{{ r.metrica }}</small>
                        </td>
                        <td class="text-center">{{ r.conteo }}</td>
                        <td>
                            <span v-if="r.paquete">{{ r.paquete }}</span>
                            <span v-else class="text-muted small">sin paquete para {{ r.conteo }} u.</span>
                        </td>
                        <td class="text-end">
                            <span v-if="r.precio !== null">{{ money(r.precio) }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="text-center">
                            <span :class="['badge', estadoBadge(r)]">{{ estadoLabel(r) }}</span>
                            <span v-if="r.estado === 'active' && r.en_prueba" class="badge bg-primary ms-1">en prueba</span>
                        </td>
                        <td class="text-end">
                            <button v-if="r.estado === 'inactivo'" class="btn btn-success btn-sm"
                                    :disabled="busy === r.service_id" @click="act('activar', r)">
                                <i class="fa fa-play me-1"></i>Activar
                            </button>
                            <button v-else-if="r.estado === 'active'" class="btn btn-outline-warning btn-sm"
                                    :disabled="busy === r.service_id" @click="act('suspender', r)">
                                <i class="fa fa-pause me-1"></i>Suspender
                            </button>
                            <button v-else-if="r.estado === 'suspended'" class="btn btn-outline-success btn-sm"
                                    :disabled="busy === r.service_id" @click="act('reactivar', r)">
                                <i class="fa fa-play me-1"></i>Reactivar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="text-muted small mt-2">
                <i class="fa fa-info-circle me-1"></i>
                El precio se determina por el conteo real de unidades al facturar. Durante la prueba
                ({{ rows[0] ? rows[0].meses_prueba : 0 }} primeras facturas) el cargo es $0.
            </p>
        </div>
    </div>
</template>

<script>
export default {
    name: "ContratablesClientTab",
    props: {
        id: { type: [String, Number], default: null },
        clientId: { type: Number, default: null },
    },
    data() {
        return { loading: false, busy: null, rows: [] };
    },
    computed: {
        cid() {
            return this.clientId || Number(this.id);
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        money(n) {
            return "$" + Number(n).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        estadoLabel(r) {
            return { active: "Activo", suspended: "Suspendido", inactivo: "Inactivo" }[r.estado] || r.estado;
        },
        estadoBadge(r) {
            return { active: "bg-success", suspended: "bg-warning text-dark", inactivo: "bg-secondary" }[r.estado] || "bg-secondary";
        },
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get(`/cliente/contratables/${this.cid}/data`);
                this.rows = data.rows || [];
            } catch (e) {
                this.rows = [];
            } finally {
                this.loading = false;
            }
        },
        async act(accion, r) {
            const verbos = { activar: "activar", suspender: "suspender", reactivar: "reactivar" };
            if (!confirm(`¿Seguro que deseas ${verbos[accion]} «${r.nombre}» para este cliente?`)) return;
            this.busy = r.service_id;
            try {
                await axios.post(`/cliente/contratables/${this.cid}/${accion}`, { service_id: r.service_id });
                await this.load();
            } catch (e) {
                alert("No se pudo " + verbos[accion] + ": " + (e.response?.data?.message || e.message));
            } finally {
                this.busy = null;
            }
        },
    },
};
</script>
