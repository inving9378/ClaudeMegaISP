<template>
    <div class="ia-modal" @click.self="$emit('close')">
        <div class="ia-modal-dialog">
            <header class="ia-modal-head">
                <h5 class="mb-0">
                    <i class="bi bi-check2-square"></i> Tareas del proyecto
                </h5>
                <button class="btn-close" @click="$emit('close')"></button>
            </header>

            <div class="ia-modal-body">
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <input
                        v-model="form.titulo"
                        class="form-control"
                        placeholder="Nueva tarea..."
                        @keydown.enter="agregar"
                        style="max-width: 300px"
                    />
                    <select v-model="form.prioridad" class="form-select" style="max-width:120px">
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>
                    <input
                        v-model="form.modulo_relacionado"
                        class="form-control"
                        placeholder="Módulo"
                        style="max-width:160px"
                    />
                    <button class="btn btn-primary" @click="agregar">Agregar</button>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <select v-model="filtroEstado" class="form-select form-select-sm" style="max-width: 180px">
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="en_progreso">En progreso</option>
                        <option value="completada">Completadas</option>
                        <option value="cancelada">Canceladas</option>
                    </select>
                </div>

                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Tarea</th>
                            <th>Módulo</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in tareasFiltradas" :key="t.id" :class="`prio-${t.prioridad}`">
                            <td style="width: 30px">
                                <input
                                    type="checkbox"
                                    :checked="t.estado === 'completada'"
                                    @change="cambiarEstado(t, $event.target.checked ? 'completada' : 'pendiente')"
                                />
                            </td>
                            <td>
                                <span :class="{ 'text-muted text-decoration-line-through': t.estado === 'completada' }">
                                    {{ t.titulo }}
                                </span>
                                <div v-if="t.descripcion"><small class="text-muted">{{ t.descripcion }}</small></div>
                            </td>
                            <td><small>{{ t.modulo_relacionado || "—" }}</small></td>
                            <td>
                                <span class="badge" :class="`bg-${prioColor(t.prioridad)}`">
                                    {{ t.prioridad }}
                                </span>
                            </td>
                            <td>
                                <select
                                    class="form-select form-select-sm"
                                    :value="t.estado"
                                    @change="cambiarEstado(t, $event.target.value)"
                                >
                                    <option value="pendiente">Pendiente</option>
                                    <option value="en_progreso">En progreso</option>
                                    <option value="completada">Completada</option>
                                    <option value="cancelada">Cancelada</option>
                                </select>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger" @click="eliminar(t)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!tareasFiltradas.length">
                            <td colspan="6" class="text-center text-muted py-4">
                                Sin tareas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    name: "IATareasPanel",
    emits: ["close"],
    setup() {
        const tareas = ref([]);
        const filtroEstado = ref("");
        const form = ref({
            titulo: "",
            prioridad: "media",
            modulo_relacionado: "",
        });

        const tareasFiltradas = computed(() =>
            filtroEstado.value
                ? tareas.value.filter((t) => t.estado === filtroEstado.value)
                : tareas.value
        );

        const prioColor = (p) =>
            ({ alta: "danger", media: "warning", baja: "secondary" }[p] || "secondary");

        const cargar = async () => {
            const { data } = await axios.get("/ia/tareas");
            tareas.value = data.data || [];
        };

        const agregar = async () => {
            if (!form.value.titulo.trim()) return;
            const { data } = await axios.post("/ia/tareas/store", form.value);
            if (data.success) {
                tareas.value.unshift(data.data);
                form.value.titulo = "";
                form.value.modulo_relacionado = "";
            }
        };

        const cambiarEstado = async (t, estado) => {
            const { data } = await axios.post(`/ia/tareas/update/${t.id}`, { estado });
            if (data.success) {
                const i = tareas.value.findIndex((x) => x.id === t.id);
                if (i !== -1) tareas.value[i] = data.data;
            }
        };

        const eliminar = async (t) => {
            const res = await Swal.fire({
                title: "¿Eliminar tarea?",
                text: t.titulo,
                icon: "warning",
                showCancelButton: true,
            });
            if (!res.isConfirmed) return;
            await axios.delete(`/ia/tareas/destroy/${t.id}`);
            tareas.value = tareas.value.filter((x) => x.id !== t.id);
        };

        onMounted(cargar);

        return {
            tareas,
            tareasFiltradas,
            filtroEstado,
            form,
            prioColor,
            agregar,
            cambiarEstado,
            eliminar,
        };
    },
};
</script>

<style scoped>
.ia-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}
.ia-modal-dialog {
    background: #fff;
    width: 92%;
    max-width: 880px;
    max-height: 90vh;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ia-modal-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
}
.ia-modal-body { padding: 16px; overflow-y: auto; }
tr.prio-alta td:nth-child(2) { border-left: 3px solid #ef4444; }
tr.prio-media td:nth-child(2) { border-left: 3px solid #f59e0b; }
tr.prio-baja td:nth-child(2) { border-left: 3px solid #9ca3af; }
</style>
