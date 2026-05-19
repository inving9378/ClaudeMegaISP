<template>
    <div class="ia-app">
        <IASidebar
            :proyectos="proyectos"
            :conversaciones="conversaciones"
            :activa="conversacionActivaId"
            :proveedores="proveedoresActivos"
            @nuevo-chat="nuevoChat"
            @abrir-conversacion="abrirConversacion"
            @renombrar="renombrarConversacion"
            @eliminar="eliminarConversacion"
            @abrir-tareas="tareasOpen = true"
            @abrir-notas="notasOpen = true"
            @crear-proyecto="crearProyecto"
            @renombrar-proyecto="renombrarProyecto"
            @eliminar-proyecto="eliminarProyecto"
            @mover-conversacion="moverConversacion"
        />

        <div class="ia-main">
            <header class="ia-main-header">
                <div>
                    <h5 class="mb-0">{{ conversacionActiva?.titulo || "Asistente IA" }}</h5>
                    <small class="text-muted" v-if="conversacionActiva">
                        Proveedor:
                        <select
                            class="form-select form-select-sm d-inline-block w-auto"
                            :value="conversacionActiva.ia_proveedor_id"
                            @change="cambiarProveedor($event.target.value)"
                        >
                            <option
                                v-for="p in proveedoresActivos"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.nombre }} ({{ p.modelo_default }})
                            </option>
                        </select>
                    </small>
                </div>
            </header>

            <IAChatArea
                :mensajes="mensajesActivos"
                :loading="enviando"
                :empty-state-text="emptyStateText"
            />

            <IAMessageInput
                :soporta-imagenes="proveedorActivo?.soporta_imagenes ?? false"
                :disabled="!conversacionActiva || enviando"
                @enviar="enviarMensaje"
            />
        </div>

        <IATareasPanel
            v-if="tareasOpen"
            @close="tareasOpen = false"
        />

        <IANotasPanel
            v-if="notasOpen"
            @close="notasOpen = false"
        />
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import IASidebar from "./IASidebar.vue";
import IAChatArea from "./IAChatArea.vue";
import IAMessageInput from "./IAMessageInput.vue";
import IATareasPanel from "./IATareasPanel.vue";
import IANotasPanel from "./IANotasPanel.vue";

export default {
    name: "IAChatIndex",
    components: {
        IASidebar,
        IAChatArea,
        IAMessageInput,
        IATareasPanel,
        IANotasPanel,
    },
    props: {
        proveedoresIniciales: { type: Array, default: () => [] },
        proyectosIniciales: { type: Array, default: () => [] },
    },
    setup(props) {
        const proveedores = ref(props.proveedoresIniciales);
        const proyectos = ref(props.proyectosIniciales);
        const conversaciones = ref([]);
        const mensajesActivos = ref([]);
        const conversacionActivaId = ref(null);
        const enviando = ref(false);
        const tareasOpen = ref(false);
        const notasOpen = ref(false);

        const proveedoresActivos = computed(() =>
            proveedores.value.filter((p) => p.activo)
        );

        const conversacionActiva = computed(() =>
            conversaciones.value.find((c) => c.id === conversacionActivaId.value)
        );

        const proveedorActivo = computed(() => {
            const c = conversacionActiva.value;
            if (!c) return null;
            return proveedores.value.find((p) => p.id === c.ia_proveedor_id);
        });

        const emptyStateText = computed(() => {
            if (!conversacionActiva.value) {
                return "Selecciona o crea una conversación para empezar";
            }
            return "Escribe un mensaje para iniciar la conversación";
        });

        const cargarConversaciones = async () => {
            const { data } = await axios.get("/ia/conversaciones");
            conversaciones.value = data.data || [];
        };

        const cargarProyectos = async () => {
            const { data } = await axios.get("/ia/proyectos");
            proyectos.value = data.data || [];
        };

        const abrirConversacion = async (id) => {
            conversacionActivaId.value = id;
            const { data } = await axios.get(`/ia/conversaciones/${id}`);
            mensajesActivos.value = data.data.mensajes || [];
        };

        const nuevoChat = async (proyectoId = null) => {
            const proveedor = proveedoresActivos.value[0];
            if (!proveedor) {
                Swal.fire("Sin proveedores", "Configura un proveedor de IA primero.", "warning");
                return;
            }
            const { data } = await axios.post("/ia/conversaciones/store", {
                ia_proveedor_id: proveedor.id,
                ia_proyecto_id: proyectoId,
            });
            if (data.success) {
                conversaciones.value.unshift(data.data);
                conversacionActivaId.value = data.data.id;
                mensajesActivos.value = [];
            }
        };

        const enviarMensaje = async ({ texto, imagenes }) => {
            if (!conversacionActiva.value) return;
            enviando.value = true;
            try {
                const { data } = await axios.post(
                    `/ia/conversaciones/${conversacionActiva.value.id}/enviar`,
                    { mensaje: texto, imagenes }
                );
                if (data.success) {
                    mensajesActivos.value.push(data.user, data.assistant);
                    // Actualiza el título y posición de la conversación
                    const i = conversaciones.value.findIndex(
                        (c) => c.id === data.conversacion.id
                    );
                    if (i !== -1) {
                        conversaciones.value[i] = {
                            ...conversaciones.value[i],
                            ...data.conversacion,
                        };
                    }
                } else {
                    Swal.fire("Error", data.message || "No se pudo enviar el mensaje", "error");
                }
            } catch (e) {
                Swal.fire(
                    "Error",
                    e.response?.data?.message || e.message || "Error enviando mensaje",
                    "error"
                );
            } finally {
                enviando.value = false;
            }
        };

        const renombrarConversacion = async (conv, nuevoTitulo) => {
            const { data } = await axios.post(`/ia/conversaciones/update/${conv.id}`, {
                titulo: nuevoTitulo,
            });
            if (data.success) {
                const i = conversaciones.value.findIndex((c) => c.id === conv.id);
                if (i !== -1) conversaciones.value[i].titulo = nuevoTitulo;
            }
        };

        const eliminarConversacion = async (conv) => {
            const res = await Swal.fire({
                title: "¿Eliminar conversación?",
                text: conv.titulo,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Eliminar",
                cancelButtonText: "Cancelar",
            });
            if (!res.isConfirmed) return;
            await axios.delete(`/ia/conversaciones/destroy/${conv.id}`);
            conversaciones.value = conversaciones.value.filter((c) => c.id !== conv.id);
            if (conversacionActivaId.value === conv.id) {
                conversacionActivaId.value = null;
                mensajesActivos.value = [];
            }
        };

        const cambiarProveedor = async (proveedorId) => {
            if (!conversacionActiva.value) return;
            const { data } = await axios.post(
                `/ia/conversaciones/update/${conversacionActiva.value.id}`,
                { ia_proveedor_id: Number(proveedorId) }
            );
            if (data.success) {
                const i = conversaciones.value.findIndex(
                    (c) => c.id === conversacionActiva.value.id
                );
                if (i !== -1) {
                    conversaciones.value[i].ia_proveedor_id = Number(proveedorId);
                }
            }
        };

        const crearProyecto = async (nombre) => {
            const { data } = await axios.post("/ia/proyectos/store", { nombre });
            if (data.success) proyectos.value.push(data.data);
        };

        const renombrarProyecto = async (proy, nombre) => {
            const { data } = await axios.post(`/ia/proyectos/update/${proy.id}`, { nombre });
            if (data.success) {
                const i = proyectos.value.findIndex((p) => p.id === proy.id);
                if (i !== -1) proyectos.value[i].nombre = nombre;
            }
        };

        const eliminarProyecto = async (proy) => {
            const res = await Swal.fire({
                title: "¿Eliminar proyecto?",
                icon: "warning",
                showCancelButton: true,
            });
            if (!res.isConfirmed) return;
            const { data } = await axios.delete(`/ia/proyectos/destroy/${proy.id}`);
            if (data.success) {
                proyectos.value = proyectos.value.filter((p) => p.id !== proy.id);
            } else {
                Swal.fire("Error", data.message || "No se pudo eliminar", "error");
            }
        };

        const moverConversacion = async ({ conversacionId, proyectoId }) => {
            const { data } = await axios.post(
                `/ia/conversaciones/update/${conversacionId}`,
                { ia_proyecto_id: proyectoId }
            );
            if (data.success) {
                const i = conversaciones.value.findIndex((c) => c.id === conversacionId);
                if (i !== -1) conversaciones.value[i].ia_proyecto_id = proyectoId;
            }
        };

        onMounted(async () => {
            await Promise.all([cargarConversaciones(), cargarProyectos()]);
        });

        return {
            proveedores,
            proyectos,
            conversaciones,
            mensajesActivos,
            conversacionActivaId,
            enviando,
            tareasOpen,
            notasOpen,
            proveedoresActivos,
            conversacionActiva,
            proveedorActivo,
            emptyStateText,
            abrirConversacion,
            nuevoChat,
            enviarMensaje,
            renombrarConversacion,
            eliminarConversacion,
            cambiarProveedor,
            crearProyecto,
            renombrarProyecto,
            eliminarProyecto,
            moverConversacion,
        };
    },
};
</script>

<style>
.ia-app {
    display: flex;
    height: calc(100vh - 70px);
    background: #f8f9fa;
}
.ia-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-left: 1px solid #e5e7eb;
    min-width: 0;
}
.ia-main-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
}

/* Markdown rendering */
.ia-md p { margin: 0 0 0.5em; }
.ia-md h1, .ia-md h2, .ia-md h3, .ia-md h4 { margin: 0.6em 0 0.3em; font-weight: 600; }
.ia-md ul { margin: 0.4em 0; padding-left: 1.4em; }
.ia-md li { margin: 0.2em 0; }
.ia-md code {
    background: rgba(135, 131, 120, 0.15);
    color: #c7254e;
    padding: 2px 5px;
    border-radius: 3px;
    font-size: 0.9em;
}
.ia-md pre.ia-code {
    background: #1e1e1e;
    color: #f8f8f2;
    padding: 12px 14px;
    padding-top: 30px;
    border-radius: 6px;
    overflow-x: auto;
    position: relative;
    margin: 0.5em 0;
}
.ia-md pre.ia-code code {
    background: transparent;
    color: inherit;
    padding: 0;
}
.ia-md pre.ia-code .ia-code-copy {
    position: absolute;
    top: 6px;
    right: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 0.75em;
    cursor: pointer;
}
.ia-md pre.ia-code .ia-code-copy:hover {
    background: rgba(255, 255, 255, 0.2);
}
.ia-md table.ia-table {
    border-collapse: collapse;
    margin: 0.5em 0;
}
.ia-md table.ia-table th, .ia-md table.ia-table td {
    border: 1px solid #d0d7de;
    padding: 4px 8px;
}
.ia-md blockquote {
    border-left: 3px solid #ccc;
    margin: 0.4em 0;
    padding: 0.2em 0.8em;
    color: #555;
}
.ia-md a { color: #2563eb; text-decoration: underline; }

/* Dark mode overrides — patrón megaisp body[data-layout-mode="dark"] */
body[data-layout-mode="dark"] .ia-app {
    background: #1a1d21;
}
body[data-layout-mode="dark"] .ia-main {
    background: #25282d;
    border-left-color: #3a3d44;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-main-header {
    background: #25282d;
    border-bottom-color: #3a3d44;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-main-header .form-select {
    background-color: #2d3036;
    border-color: #3a3d44;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-md code {
    background: rgba(255, 255, 255, 0.08);
    color: #f9b6c5;
}
body[data-layout-mode="dark"] .ia-md blockquote {
    border-left-color: #4a4d54;
    color: #9ca3af;
}
body[data-layout-mode="dark"] .ia-md table.ia-table th,
body[data-layout-mode="dark"] .ia-md table.ia-table td {
    border-color: #3a3d44;
}
</style>
