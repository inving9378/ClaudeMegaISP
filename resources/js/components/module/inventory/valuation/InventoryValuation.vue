<template>
    <div>
        <div v-if="loading" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

        <template v-else-if="data">
            <!-- Total general -->
            <div class="row g-3 mb-4">
                <div class="col-md-6 offset-md-3">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <h6 class="text-muted mb-1">Total Invertido</h6>
                            <h3 class="fw-bold text-primary">${{ formatCurrency(data.total) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Por Almacén -->
            <div class="card mb-3" v-if="Object.keys(data.by_store).length">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-warehouse me-2"></i>Por Almacén</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Almacén</th>
                                <th class="text-end">Inversión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(val, key) in data.by_store" :key="key">
                                <td>{{ key }}</td>
                                <td class="text-end fw-bold">${{ formatCurrency(val) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Asignado a Usuarios -->
            <div class="card mb-3" v-if="Object.keys(data.by_user).length">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-users me-2"></i>Asignado a Usuarios</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Usuario</th>
                                <th class="text-end">Inversión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(val, key) in data.by_user" :key="key">
                                <td>{{ key }}</td>
                                <td class="text-end fw-bold">${{ formatCurrency(val) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Entregado a Clientes -->
            <div class="card mb-3" v-if="Object.keys(data.by_client).length">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-handshake me-2"></i>Entregado a Clientes</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Cliente</th>
                                <th class="text-end">Inversión</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(val, key) in data.by_client" :key="key">
                                <td>{{ key }}</td>
                                <td class="text-end fw-bold">${{ formatCurrency(val) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="!Object.keys(data.by_store).length && !Object.keys(data.by_user).length && !Object.keys(data.by_client).length"
                class="alert alert-info">
                No hay artículos con costo registrado aún. Agrega stock desde facturas de proveedores para ver la valuación.
            </div>
        </template>

        <div class="d-flex justify-content-end mt-2">
            <button class="btn btn-outline-secondary btn-sm" @click="load">
                <i class="fas fa-sync-alt me-1"></i> Actualizar
            </button>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import axios from "axios";

export default {
    name: "InventoryValuation",
    setup() {
        const data    = ref(null);
        const loading = ref(true);

        const load = async () => {
            loading.value = true;
            try {
                const res = await axios.get("/inventory/inventory-valuation/data");
                data.value = res.data.data;
            } catch (e) { console.error(e); }
            finally { loading.value = false; }
        };

        onMounted(load);

        const formatCurrency = (val) => Number(val ?? 0).toLocaleString("es-MX", { minimumFractionDigits: 2 });

        return { data, loading, load, formatCurrency };
    },
};
</script>
