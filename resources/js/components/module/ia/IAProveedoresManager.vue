<template>
    <div class="ia-modal" @click.self="$emit('close')">
        <div class="ia-modal-dialog">
            <header class="ia-modal-head">
                <h5 class="mb-0">Proveedores de IA</h5>
                <button class="btn-close" @click="$emit('close')"></button>
            </header>

            <div class="ia-modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <small class="text-muted">
                        Configura proveedores de IA. Puedes activar/desactivar y probar la conexión.
                    </small>
                    <button class="btn btn-primary btn-sm" @click="abrirFormulario(null)">
                        <i class="bi bi-plus-lg"></i> Agregar proveedor
                    </button>
                </div>

                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Nombre</th>
                            <th>Driver</th>
                            <th>Modelo</th>
                            <th>Imágenes</th>
                            <th>Activo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in proveedores" :key="p.id">
                            <td>
                                <span class="ia-status-dot" :class="`s-${p.estado}`"
                                    :title="p.ultimo_error || p.estado"></span>
                                {{ etiquetaEstado(p.estado) }}
                            </td>
                            <td>{{ p.nombre }}</td>
                            <td><code>{{ p.driver }}</code></td>
                            <td><small>{{ p.modelo_default }}</small></td>
                            <td>
                                <i v-if="p.soporta_imagenes" class="bi bi-check2 text-success"></i>
                                <i v-else class="bi bi-x text-muted"></i>
                            </td>
                            <td>
                                <input
                                    type="checkbox"
                                    :checked="p.activo"
                                    @change="toggleActivo(p)"
                                />
                            </td>
                            <td class="text-end">
                                <button
                                    class="btn btn-sm btn-outline-secondary me-1"
                                    @click="probar(p)"
                                    :disabled="probando === p.id"
                                >
                                    <i class="bi bi-lightning"></i>
                                    {{ probando === p.id ? "..." : "Probar" }}
                                </button>
                                <button class="btn btn-sm btn-outline-primary me-1"
                                    @click="abrirFormulario(p)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger"
                                    @click="eliminar(p)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Formulario -->
                <div v-if="formAbierto" class="ia-form mt-4">
                    <h6>{{ form.id ? "Editar proveedor" : "Agregar proveedor" }}</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input class="form-control" v-model="form.nombre" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Driver</label>
                            <select class="form-select" v-model="form.driver">
                                <option v-for="d in drivers" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">API Key</label>
                            <div class="input-group">
                                <input
                                    :type="mostrarKey ? 'text' : 'password'"
                                    class="form-control"
                                    v-model="form.api_key"
                                    :placeholder="form.id && form.tiene_api_key ? '(sin cambios)' : 'API key del proveedor'"
                                />
                                <button class="btn btn-outline-secondary" type="button"
                                    @click="mostrarKey = !mostrarKey">
                                    <i :class="mostrarKey ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Endpoint URL</label>
                            <input class="form-control" v-model="form.endpoint_url"
                                :placeholder="endpointPlaceholder" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modelo por defecto</label>
                            <input class="form-control" v-model="form.modelo_default" />
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="soporta_img"
                                    v-model="form.soporta_imagenes"
                                />
                                <label class="form-check-label" for="soporta_img">
                                    Soporta imágenes
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    id="activo_chk"
                                    v-model="form.activo"
                                />
                                <label class="form-check-label" for="activo_chk">Activo</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">
                                Headers personalizados (JSON)
                                <small class="text-muted">opcional</small>
                            </label>
                            <textarea
                                class="form-control font-monospace"
                                rows="3"
                                v-model="form.headers_personalizados_raw"
                                placeholder='{"X-Custom-Header": "valor"}'
                            ></textarea>
                            <small class="text-danger" v-if="errorHeaders">JSON inválido</small>
                        </div>
                    </div>

                    <div v-if="errorForm" class="alert alert-danger mt-2">{{ errorForm }}</div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button class="btn btn-secondary btn-sm" @click="formAbierto = false">
                            Cancelar
                        </button>
                        <button class="btn btn-primary btn-sm" @click="guardar" :disabled="guardando">
                            {{ guardando ? "Guardando..." : "Guardar" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed } from "vue";
import axios from "axios";
import Swal from "sweetalert2";

const DEFAULTS = {
    claude: {
        endpoint: "https://api.anthropic.com/v1/messages",
        modelo: "claude-3-5-sonnet-20241022",
    },
    openai: {
        endpoint: "https://api.openai.com/v1/chat/completions",
        modelo: "gpt-4o",
    },
    openai_compatible: {
        endpoint: "https://api.ejemplo.com/v1/chat/completions",
        modelo: "model-name",
    },
    gemini: {
        endpoint: "https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent",
        modelo: "gemini-2.0-flash",
    },
    custom: { endpoint: "", modelo: "" },
};

export default {
    name: "IAProveedoresManager",
    props: {
        proveedores: { type: Array, default: () => [] },
    },
    emits: ["close", "refresh"],
    setup(props, { emit }) {
        const drivers = ref(["claude", "openai", "openai_compatible", "gemini", "custom"]);
        const formAbierto = ref(false);
        const form = ref({});
        const mostrarKey = ref(false);
        const errorHeaders = ref(false);
        const errorForm = ref("");
        const guardando = ref(false);
        const probando = ref(null);

        const endpointPlaceholder = computed(
            () => DEFAULTS[form.value?.driver]?.endpoint || ""
        );

        const etiquetaEstado = (e) =>
            ({ conectado: "Conectado", error: "Error", sin_configurar: "Sin configurar" }[e] || e);

        const abrirFormulario = (p) => {
            errorForm.value = "";
            errorHeaders.value = false;
            mostrarKey.value = false;
            if (p) {
                form.value = {
                    ...p,
                    api_key: "",
                    headers_personalizados_raw: p.headers_personalizados
                        ? JSON.stringify(p.headers_personalizados, null, 2)
                        : "",
                };
            } else {
                const def = DEFAULTS.openai;
                form.value = {
                    id: null,
                    nombre: "",
                    driver: "openai",
                    api_key: "",
                    endpoint_url: def.endpoint,
                    modelo_default: def.modelo,
                    soporta_imagenes: true,
                    activo: true,
                    headers_personalizados_raw: "",
                    tiene_api_key: false,
                };
            }
            formAbierto.value = true;
        };

        const guardar = async () => {
            errorForm.value = "";
            errorHeaders.value = false;

            let headers = null;
            if (form.value.headers_personalizados_raw) {
                try {
                    headers = JSON.parse(form.value.headers_personalizados_raw);
                } catch {
                    errorHeaders.value = true;
                    return;
                }
            }

            const payload = {
                nombre: form.value.nombre,
                driver: form.value.driver,
                api_key: form.value.api_key || "",
                endpoint_url: form.value.endpoint_url,
                modelo_default: form.value.modelo_default,
                soporta_imagenes: form.value.soporta_imagenes ? 1 : 0,
                activo: form.value.activo ? 1 : 0,
                headers_personalizados: headers ? JSON.stringify(headers) : "",
            };

            guardando.value = true;
            try {
                const url = form.value.id
                    ? `/ia/proveedores/update/${form.value.id}`
                    : "/ia/proveedores/store";
                const { data } = await axios.post(url, payload);
                if (!data.success) {
                    errorForm.value = data.message || Object.values(data.errors || {}).flat().join(", ");
                    return;
                }
                formAbierto.value = false;
                emit("refresh");
            } catch (e) {
                errorForm.value = e.response?.data?.message || e.message || "Error guardando";
            } finally {
                guardando.value = false;
            }
        };

        const toggleActivo = async (p) => {
            await axios.post(`/ia/proveedores/toggle-activo/${p.id}`);
            emit("refresh");
        };

        const eliminar = async (p) => {
            const res = await Swal.fire({
                title: "¿Eliminar proveedor?",
                text: p.nombre,
                icon: "warning",
                showCancelButton: true,
            });
            if (!res.isConfirmed) return;
            await axios.delete(`/ia/proveedores/destroy/${p.id}`);
            emit("refresh");
        };

        const probar = async (p) => {
            probando.value = p.id;
            try {
                const { data } = await axios.post(`/ia/proveedores/probar/${p.id}`);
                Swal.fire({
                    icon: data.success ? "success" : "error",
                    title: data.success ? "Conectado" : "Error de conexión",
                    text: data.message,
                });
                emit("refresh");
            } finally {
                probando.value = null;
            }
        };

        return {
            drivers,
            formAbierto,
            form,
            mostrarKey,
            errorHeaders,
            errorForm,
            guardando,
            probando,
            endpointPlaceholder,
            etiquetaEstado,
            abrirFormulario,
            guardar,
            toggleActivo,
            eliminar,
            probar,
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
    max-width: 960px;
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
.ia-modal-body {
    padding: 16px;
    overflow-y: auto;
}
.ia-status-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ccc;
    margin-right: 6px;
}
.ia-status-dot.s-conectado { background: #22c55e; }
.ia-status-dot.s-error { background: #ef4444; }
.ia-status-dot.s-sin_configurar { background: #d1d5db; }
.ia-form {
    border-top: 1px solid #e5e7eb;
    padding-top: 16px;
}
</style>
