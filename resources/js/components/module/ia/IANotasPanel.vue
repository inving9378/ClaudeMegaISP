<template>
    <div class="ia-modal" @click.self="$emit('close')">
        <div class="ia-modal-dialog">
            <header class="ia-modal-head">
                <h5 class="mb-0">
                    <i class="bi bi-journal-text"></i> Notas del proyecto
                </h5>
                <button class="btn-close" @click="$emit('close')"></button>
            </header>

            <div class="ia-modal-body">
                <div class="alert alert-info py-2">
                    <small>
                        <i class="bi bi-info-circle"></i>
                        Las notas marcadas como <strong>importantes</strong> se inyectan automáticamente
                        en el contexto del asistente cada vez que abres un chat.
                    </small>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input v-model="form.titulo" class="form-control" placeholder="Título" />
                    </div>
                    <div class="col-md-2">
                        <input v-model="form.categoria" class="form-control" placeholder="Categoría" />
                    </div>
                    <div class="col-md-5">
                        <input v-model="form.contenido" class="form-control" placeholder="Contenido..." />
                    </div>
                    <div class="col-md-1">
                        <div class="form-check mt-2" title="Importante (siempre inyectada)">
                            <input type="checkbox" class="form-check-input" v-model="form.importante" />
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-primary w-100" @click="agregar">+</button>
                    </div>
                </div>

                <div class="ia-notas-list">
                    <div
                        v-for="n in notas"
                        :key="n.id"
                        class="ia-nota"
                        :class="{ importante: n.importante }"
                    >
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="ia-nota-head">
                                    <strong>{{ n.titulo }}</strong>
                                    <span v-if="n.categoria" class="badge bg-light text-dark ms-2">
                                        {{ n.categoria }}
                                    </span>
                                    <i
                                        v-if="n.importante"
                                        class="bi bi-star-fill text-warning ms-2"
                                        title="Importante"
                                    ></i>
                                </div>
                                <p class="mb-0">{{ n.contenido }}</p>
                            </div>
                            <div class="d-flex gap-1">
                                <button
                                    class="btn btn-sm btn-outline-warning"
                                    @click="toggleImportante(n)"
                                    :title="n.importante ? 'Quitar importancia' : 'Marcar importante'"
                                >
                                    <i :class="n.importante ? 'bi bi-star-fill' : 'bi bi-star'"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" @click="eliminar(n)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!notas.length" class="text-center text-muted py-4">
                        Sin notas. Agrega notas como "Usamos soft deletes" o
                        "No tocar módulo de facturación".
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    name: "IANotasPanel",
    emits: ["close"],
    setup() {
        const notas = ref([]);
        const form = ref({
            titulo: "",
            contenido: "",
            categoria: "",
            importante: false,
        });

        const cargar = async () => {
            const { data } = await axios.get("/ia/notas");
            notas.value = data.data || [];
        };

        const agregar = async () => {
            if (!form.value.titulo.trim() || !form.value.contenido.trim()) return;
            const { data } = await axios.post("/ia/notas/store", {
                ...form.value,
                importante: form.value.importante ? 1 : 0,
            });
            if (data.success) {
                notas.value.unshift(data.data);
                form.value = { titulo: "", contenido: "", categoria: "", importante: false };
            }
        };

        const toggleImportante = async (n) => {
            const { data } = await axios.post(`/ia/notas/update/${n.id}`, {
                importante: !n.importante ? 1 : 0,
            });
            if (data.success) {
                const i = notas.value.findIndex((x) => x.id === n.id);
                if (i !== -1) notas.value[i] = data.data;
            }
        };

        const eliminar = async (n) => {
            const res = await Swal.fire({
                title: "¿Eliminar nota?",
                icon: "warning",
                showCancelButton: true,
            });
            if (!res.isConfirmed) return;
            await axios.delete(`/ia/notas/destroy/${n.id}`);
            notas.value = notas.value.filter((x) => x.id !== n.id);
        };

        onMounted(cargar);

        return {
            notas,
            form,
            agregar,
            toggleImportante,
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
    max-width: 820px;
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

.ia-nota {
    padding: 10px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 8px;
    background: #fff;
}
.ia-nota.importante {
    border-left: 4px solid #f59e0b;
    background: #fffbeb;
}
.ia-nota-head {
    margin-bottom: 4px;
    font-size: 0.95em;
}
</style>
