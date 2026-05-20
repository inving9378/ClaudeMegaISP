<template>
    <aside class="ia-sidebar">
        <div class="ia-sidebar-head">
            <button class="btn btn-primary btn-sm w-100" @click="$emit('nuevo-chat', null)">
                <i class="bi bi-plus-lg"></i> Nuevo chat
            </button>
        </div>

        <div class="ia-sidebar-section">
            <div class="ia-section-title">
                <span>Proyectos</span>
                <button class="btn btn-link btn-sm p-0" @click="abrirCrearProyecto" title="Nuevo proyecto">
                    <i class="bi bi-folder-plus"></i>
                </button>
            </div>

            <div
                v-for="p in proyectos"
                :key="p.id"
                class="ia-proyecto"
                @dragover.prevent
                @drop="onDropConversacion($event, p.id)"
            >
                <div class="ia-proyecto-head">
                    <span
                        class="ia-proyecto-color"
                        :style="{ background: p.color || '#888' }"
                    ></span>
                    <span
                        class="ia-proyecto-nombre flex-grow-1"
                        @dblclick="renombrarProyecto(p)"
                        :title="p.descripcion"
                    >{{ p.nombre }}</span>
                    <button
                        v-if="!p.es_default"
                        class="btn btn-link btn-sm p-0 text-danger"
                        @click="$emit('eliminar-proyecto', p)"
                        title="Eliminar proyecto"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
                <div class="ia-conv-list">
                    <div
                        v-for="c in conversacionesDe(p.id)"
                        :key="c.id"
                        class="ia-conv-item"
                        :class="{ active: c.id === activa }"
                        draggable="true"
                        @dragstart="onDragStart($event, c)"
                        @click="$emit('abrir-conversacion', c.id)"
                    >
                        <span class="ia-conv-title" @dblclick.stop="renombrar(c)">
                            {{ c.titulo }}
                        </span>
                        <span class="ia-conv-actions">
                            <i class="bi bi-pencil" @click.stop="renombrar(c)"></i>
                            <i class="bi bi-trash" @click.stop="$emit('eliminar', c)"></i>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Conversaciones huérfanas (sin proyecto) -->
            <div
                v-if="huerfanas.length"
                class="ia-proyecto"
                @dragover.prevent
                @drop="onDropConversacion($event, null)"
            >
                <div class="ia-proyecto-head">
                    <span class="ia-proyecto-color" style="background:#bbb"></span>
                    <span class="ia-proyecto-nombre">Sin proyecto</span>
                </div>
                <div class="ia-conv-list">
                    <div
                        v-for="c in huerfanas"
                        :key="c.id"
                        class="ia-conv-item"
                        :class="{ active: c.id === activa }"
                        draggable="true"
                        @dragstart="onDragStart($event, c)"
                        @click="$emit('abrir-conversacion', c.id)"
                    >
                        <span class="ia-conv-title">{{ c.titulo }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="ia-sidebar-foot">
            <button class="btn btn-link btn-sm" @click="$emit('abrir-tareas')">
                <i class="bi bi-check2-square"></i> Tareas
            </button>
            <button class="btn btn-link btn-sm" @click="$emit('abrir-notas')">
                <i class="bi bi-journal-text"></i> Notas
            </button>
            <div class="ia-prov-status">
                <small class="text-muted">
                    {{ proveedores.length }} proveedores activos
                </small>
            </div>
        </div>
    </aside>
</template>

<script>
import { computed } from "vue";
import Swal from "sweetalert2";

export default {
    name: "IASidebar",
    props: {
        proyectos: { type: Array, default: () => [] },
        conversaciones: { type: Array, default: () => [] },
        activa: { type: Number, default: null },
        proveedores: { type: Array, default: () => [] },
    },
    emits: [
        "nuevo-chat",
        "abrir-conversacion",
        "renombrar",
        "eliminar",
        "abrir-tareas",
        "abrir-notas",
        "crear-proyecto",
        "renombrar-proyecto",
        "eliminar-proyecto",
        "mover-conversacion",
    ],
    setup(props, { emit }) {
        const conversacionesDe = (proyectoId) =>
            props.conversaciones.filter((c) => c.ia_proyecto_id === proyectoId);

        const huerfanas = computed(() =>
            props.conversaciones.filter((c) => c.ia_proyecto_id == null)
        );

        const renombrar = async (c) => {
            const { value } = await Swal.fire({
                title: "Renombrar conversación",
                input: "text",
                inputValue: c.titulo,
                showCancelButton: true,
            });
            if (value) emit("renombrar", c, value);
        };

        const renombrarProyecto = async (p) => {
            if (p.es_default) return;
            const { value } = await Swal.fire({
                title: "Renombrar proyecto",
                input: "text",
                inputValue: p.nombre,
                showCancelButton: true,
            });
            if (value) emit("renombrar-proyecto", p, value);
        };

        const abrirCrearProyecto = async () => {
            const { value } = await Swal.fire({
                title: "Nuevo proyecto",
                input: "text",
                inputPlaceholder: "Nombre del proyecto",
                showCancelButton: true,
            });
            if (value) emit("crear-proyecto", value);
        };

        const onDragStart = (ev, c) => {
            ev.dataTransfer.setData("text/plain", String(c.id));
            ev.dataTransfer.effectAllowed = "move";
        };
        const onDropConversacion = (ev, proyectoId) => {
            const id = Number(ev.dataTransfer.getData("text/plain"));
            if (!id) return;
            emit("mover-conversacion", { conversacionId: id, proyectoId });
        };

        return {
            conversacionesDe,
            huerfanas,
            renombrar,
            renombrarProyecto,
            abrirCrearProyecto,
            onDragStart,
            onDropConversacion,
        };
    },
};
</script>

<style scoped>
.ia-sidebar {
    width: 280px;
    background: #f3f4f6;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ia-sidebar-head {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
}
.ia-sidebar-section {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}
.ia-section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 8px;
    font-weight: 600;
    font-size: 0.85em;
    color: #6b7280;
    text-transform: uppercase;
}
.ia-proyecto {
    margin-bottom: 12px;
}
.ia-proyecto-head {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 4px;
    background: #e5e7eb;
}
.ia-proyecto-color {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
.ia-proyecto-nombre {
    font-weight: 500;
    cursor: pointer;
    font-size: 0.92em;
}
.ia-conv-list {
    margin-top: 4px;
    padding-left: 4px;
}
.ia-conv-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.88em;
}
.ia-conv-item:hover {
    background: #e5e7eb;
}
.ia-conv-item.active {
    background: #dbeafe;
    color: #1d4ed8;
    font-weight: 500;
}
.ia-conv-actions { display: none; gap: 6px; }
.ia-conv-item:hover .ia-conv-actions { display: inline-flex; }
.ia-conv-actions i { cursor: pointer; opacity: 0.7; }
.ia-conv-actions i:hover { opacity: 1; }
.ia-conv-title {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ia-sidebar-foot {
    border-top: 1px solid #e5e7eb;
    padding: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}
.ia-prov-status {
    width: 100%;
    padding: 4px 0 0;
    text-align: center;
}

/* Dark mode */
body[data-layout-mode="dark"] .ia-sidebar {
    background: #1d2025;
    color: #e5e7eb;
}
body[data-layout-mode="dark"] .ia-sidebar-head,
body[data-layout-mode="dark"] .ia-sidebar-foot {
    border-color: #3a3d44;
}
body[data-layout-mode="dark"] .ia-section-title {
    color: #9ca3af;
}
body[data-layout-mode="dark"] .ia-proyecto-head {
    background: #2d3036;
}
body[data-layout-mode="dark"] .ia-conv-item:hover {
    background: #2d3036;
}
body[data-layout-mode="dark"] .ia-conv-item.active {
    background: #1e3a8a;
    color: #93c5fd;
}
</style>
