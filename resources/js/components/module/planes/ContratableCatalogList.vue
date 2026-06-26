<template>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fa fa-cubes me-2"></i>Servicios contratables</h5>
            <a href="/planes/contratables/crear" class="btn btn-primary btn-sm">
                <i class="fa fa-plus me-1"></i> Nuevo servicio
            </a>
        </div>
        <div class="card-body">
            <div v-if="loading" class="text-center text-muted py-4">Cargando…</div>

            <div v-else-if="!services.length" class="text-center text-muted py-5">
                <i class="fa fa-cubes fa-2x mb-2 d-block"></i>
                Aún no hay servicios contratables. Crea el primero con “Nuevo servicio”.
            </div>

            <div v-else class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Módulo</th>
                            <th>Nombre</th>
                            <th>Métrica</th>
                            <th class="text-center">Paquetes</th>
                            <th class="text-end">Rango de precios (IVA incl.)</th>
                            <th class="text-center">Prueba</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in services" :key="s.id">
                            <td><span class="badge bg-secondary">{{ moduleLabel(s.module_key) }}</span></td>
                            <td>{{ s.nombre }}</td>
                            <td>{{ s.metrica }}</td>
                            <td class="text-center">{{ s.packages.length }}</td>
                            <td class="text-end">{{ priceRange(s.packages) }}</td>
                            <td class="text-center">{{ s.meses_prueba }} factura(s)</td>
                            <td class="text-center">
                                <span :class="['badge', s.activo ? 'bg-success' : 'bg-secondary']">
                                    {{ s.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a :href="`/planes/contratables/editar/${s.id}`" class="btn btn-outline-primary btn-sm me-1">
                                    <i class="fa fa-pen"></i>
                                </a>
                                <button class="btn btn-outline-danger btn-sm" @click="remove(s)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "ContratableCatalogList",
    data() {
        return {
            services: [],
            loading: true,
            modulos: [],
        };
    },
    async mounted() {
        try {
            const [{ data: cat }, { data: mods }] = await Promise.all([
                axios.get("/planes/contratables/data"),
                axios.get("/planes/contratables/modulos"),
            ]);
            this.services = cat.services || [];
            this.modulos = mods.modulos || [];
        } catch (e) {
            this.services = [];
        } finally {
            this.loading = false;
        }
    },
    methods: {
        moduleLabel(key) {
            const m = this.modulos.find((x) => x.key === key);
            return m ? m.label : key;
        },
        priceRange(packages) {
            if (!packages.length) return "—";
            const precios = packages.map((p) => p.precio);
            const min = Math.min(...precios);
            const max = Math.max(...precios);
            const f = (n) => "$" + Number(n).toLocaleString("es-MX", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            return min === max ? f(min) : `${f(min)} – ${f(max)}`;
        },
        async remove(s) {
            if (!confirm(`¿Eliminar el servicio “${s.nombre}” y sus paquetes?`)) return;
            try {
                await axios.post(`/planes/contratables/destroy/${s.id}`);
                this.services = this.services.filter((x) => x.id !== s.id);
            } catch (e) {
                alert("No se pudo eliminar: " + (e.response?.data?.message || e.message));
            }
        },
    },
};
</script>
