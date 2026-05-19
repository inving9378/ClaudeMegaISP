<template>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">
                    <i class="bi bi-bookmark me-1"></i> Mis Prompts Guardados
                </h4>
                <small class="text-muted">Plantillas de prompt reutilizables para el asistente IA.</small>
            </div>
            <button class="btn btn-primary btn-sm" @click="abrirNuevo">
                <i class="bi bi-plus-lg"></i> Nuevo prompt
            </button>
        </div>

        <div v-if="loading" class="text-center py-5 text-muted">
            <div class="spinner-border spinner-border-sm me-1"></div> Cargando prompts...
        </div>

        <div v-else-if="prompts.length === 0" class="text-center py-5 text-muted">
            <i class="bi bi-bookmark-plus" style="font-size: 2em"></i>
            <p class="mt-2 mb-0">Aún no tienes prompts guardados.</p>
            <button class="btn btn-link" @click="abrirNuevo">Crear el primero</button>
        </div>

        <div v-else class="row g-3">
            <div v-for="p in prompts" :key="p.id" class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title d-flex justify-content-between">
                            {{ p.titulo }}
                            <span v-if="p.es_publico" class="badge bg-info">Público</span>
                        </h6>
                        <small class="text-muted">{{ p.categoria || "Sin categoría" }} · {{ p.usos }} usos</small>
                        <pre class="card-text mt-2 ia-prompt-preview">{{ p.contenido }}</pre>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button class="btn btn-sm btn-outline-primary" @click="usarPrompt(p)">
                            <i class="bi bi-clipboard-check"></i> Usar
                        </button>
                        <div>
                            <button class="btn btn-sm btn-link" @click="editar(p)">Editar</button>
                            <button class="btn btn-sm btn-link text-danger" @click="eliminar(p)">Eliminar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="modalAbierto" class="ia-modal" @click.self="cerrarModal">
            <div class="ia-modal-dialog" style="max-width: 600px">
                <header class="ia-modal-head">
                    <h5 class="mb-0">{{ formulario.id ? "Editar prompt" : "Nuevo prompt" }}</h5>
                    <button class="btn-close" @click="cerrarModal"></button>
                </header>
                <div class="ia-modal-body">
                    <div class="mb-2">
                        <label class="form-label">Título</label>
                        <input v-model="formulario.titulo" class="form-control" maxlength="250" />
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Categoría (opcional)</label>
                        <input v-model="formulario.categoria" class="form-control" maxlength="100" />
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Contenido</label>
                        <textarea v-model="formulario.contenido" class="form-control" rows="8" placeholder="Escribe tu prompt..."></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="es-publico" v-model="formulario.es_publico" />
                        <label class="form-check-label" for="es-publico">Visible para otros usuarios</label>
                    </div>
                </div>
                <footer class="ia-modal-foot d-flex justify-content-end gap-2 p-3 border-top">
                    <button class="btn btn-secondary" @click="cerrarModal">Cancelar</button>
                    <button class="btn btn-primary" :disabled="guardando || !formulario.titulo || !formulario.contenido" @click="guardar">
                        <span v-if="guardando" class="spinner-border spinner-border-sm me-1"></span>
                        Guardar
                    </button>
                </footer>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    name: "IAPrompts",
    setup() {
        const prompts = ref([]);
        const loading = ref(true);
        const modalAbierto = ref(false);
        const guardando = ref(false);
        const formulario = ref({ id: null, titulo: "", categoria: "", contenido: "", es_publico: false });

        const cargar = async () => {
            loading.value = true;
            try {
                const { data } = await axios.get("/ia/prompts/listar");
                prompts.value = data.data || [];
            } catch (e) {
                console.error("Error cargando prompts", e);
            } finally {
                loading.value = false;
            }
        };

        const abrirNuevo = () => {
            formulario.value = { id: null, titulo: "", categoria: "", contenido: "", es_publico: false };
            modalAbierto.value = true;
        };

        const editar = (p) => {
            formulario.value = {
                id: p.id,
                titulo: p.titulo,
                categoria: p.categoria || "",
                contenido: p.contenido,
                es_publico: !!p.es_publico,
            };
            modalAbierto.value = true;
        };

        const cerrarModal = () => {
            modalAbierto.value = false;
        };

        const guardar = async () => {
            guardando.value = true;
            try {
                const url = formulario.value.id
                    ? `/ia/prompts/update/${formulario.value.id}`
                    : `/ia/prompts/store`;
                const { data } = await axios.post(url, formulario.value);
                if (data.success) {
                    cerrarModal();
                    cargar();
                } else {
                    Swal.fire("Error", data.message || "No se pudo guardar", "error");
                }
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || e.message, "error");
            } finally {
                guardando.value = false;
            }
        };

        const eliminar = async (p) => {
            const res = await Swal.fire({
                title: "¿Eliminar prompt?",
                text: p.titulo,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Eliminar",
                cancelButtonText: "Cancelar",
            });
            if (!res.isConfirmed) return;
            try {
                await axios.delete(`/ia/prompts/destroy/${p.id}`);
                cargar();
            } catch (e) {
                Swal.fire("Error", e.response?.data?.message || e.message, "error");
            }
        };

        const usarPrompt = async (p) => {
            try {
                await axios.post(`/ia/prompts/usar/${p.id}`);
            } catch (e) {
                /* incremento de usos best-effort */
            }
            try {
                await navigator.clipboard.writeText(p.contenido);
                Swal.fire({ icon: "success", title: "Copiado al portapapeles", timer: 1200, showConfirmButton: false });
            } catch (e) {
                window.prompt("Copia el prompt:", p.contenido);
            }
        };

        onMounted(cargar);

        return {
            prompts,
            loading,
            modalAbierto,
            guardando,
            formulario,
            abrirNuevo,
            editar,
            cerrarModal,
            guardar,
            eliminar,
            usarPrompt,
        };
    },
};
</script>

<style scoped>
.ia-prompt-preview {
    background: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    font-size: 0.8em;
    max-height: 120px;
    overflow: hidden;
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
