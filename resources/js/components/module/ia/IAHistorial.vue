<template>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">
                    <i class="bi bi-clock-history me-1"></i> Historial de Conversaciones IA
                </h4>
                <small class="text-muted">Todas tus conversaciones con la IA, agrupadas por proyecto.</small>
            </div>
            <a :href="`/ia`" class="btn btn-primary btn-sm">
                <i class="bi bi-chat-dots"></i> Ir al asistente
            </a>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <input
                    v-model="busqueda"
                    class="form-control form-control-sm"
                    placeholder="Buscar por título..."
                />
            </div>
            <div class="col-md-3">
                <select v-model="filtroProyecto" class="form-select form-select-sm">
                    <option :value="null">Todos los proyectos</option>
                    <option v-for="p in proyectos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <span class="badge bg-secondary">{{ conversacionesFiltradas.length }} resultados</span>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm me-1"></div> Cargando historial...
        </div>

        <div v-else-if="conversacionesFiltradas.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size: 2em"></i>
            <p class="mt-2 mb-0">No hay conversaciones que mostrar.</p>
        </div>

        <div v-else class="table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Proyecto</th>
                        <th>Proveedor</th>
                        <th class="text-end">Mensajes</th>
                        <th>Último mensaje</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in conversacionesFiltradas" :key="c.id">
                        <td>
                            <a :href="`/ia?conversacion=${c.id}`" class="text-decoration-none">
                                <strong>{{ c.titulo || `Chat #${c.id}` }}</strong>
                            </a>
                        </td>
                        <td>
                            <span v-if="c.proyecto" class="badge" :style="`background:${c.proyecto.color || '#6c757d'}`">
                                {{ c.proyecto.nombre }}
                            </span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td>
                            <small>{{ c.proveedor?.nombre || '—' }}</small>
                        </td>
                        <td class="text-end">{{ c.mensajes_count }}</td>
                        <td>
                            <small class="text-muted">{{ formatearFecha(c.ultimo_mensaje_at) }}</small>
                        </td>
                        <td class="text-end">
                            <a :href="`/ia?conversacion=${c.id}`" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-up-right"></i> Abrir
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import axios from "axios";

export default {
    name: "IAHistorial",
    props: {
        proyectosIniciales: { type: Array, default: () => [] },
    },
    setup(props) {
        const conversaciones = ref([]);
        const proyectos = ref(props.proyectosIniciales);
        const busqueda = ref("");
        const filtroProyecto = ref(null);
        const loading = ref(true);

        const cargar = async () => {
            loading.value = true;
            try {
                const { data } = await axios.get("/ia/historial/tabla");
                conversaciones.value = data.data || [];
            } catch (e) {
                console.error("Error cargando historial", e);
            } finally {
                loading.value = false;
            }
        };

        const conversacionesFiltradas = computed(() => {
            const q = busqueda.value.trim().toLowerCase();
            return conversaciones.value.filter((c) => {
                if (filtroProyecto.value && c.ia_proyecto_id !== filtroProyecto.value) return false;
                if (q && !(c.titulo || "").toLowerCase().includes(q)) return false;
                return true;
            });
        });

        const formatearFecha = (iso) => {
            if (!iso) return "—";
            const d = new Date(iso);
            return d.toLocaleString("es-MX", {
                year: "numeric",
                month: "2-digit",
                day: "2-digit",
                hour: "2-digit",
                minute: "2-digit",
            });
        };

        onMounted(cargar);

        return {
            conversaciones,
            proyectos,
            busqueda,
            filtroProyecto,
            loading,
            conversacionesFiltradas,
            formatearFecha,
        };
    },
};
</script>
