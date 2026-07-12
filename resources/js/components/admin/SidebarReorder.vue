<template>
    <q-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
        <q-card style="min-width: 520px; max-width: 640px;">
            <q-card-section class="row items-center q-pb-none">
                <div class="text-h6">Reordenar módulos del sidebar</div>
                <q-space />
                <q-btn icon="close" flat round dense v-close-popup />
            </q-card-section>

            <q-card-section class="text-caption text-grey-7 q-pt-sm">
                Arrastra para reordenar dentro de cada sección. Los sub-menús (con
                sangría) se reordenan dentro de su módulo padre. El orden se guarda
                al soltar. (Para una posición exacta también puedes usar el número
                en "Visibilidad".)
            </q-card-section>

            <q-card-section v-if="loading" class="text-center q-py-lg">
                <q-spinner size="2em" /> <span class="q-ml-sm">Cargando…</span>
            </q-card-section>

            <q-card-section v-else class="q-gutter-md" style="max-height: 60vh; overflow-y: auto;">
                <div v-for="(items, section) in sections" :key="section">
                    <div class="text-subtitle2 text-uppercase text-grey-7 q-mb-xs">
                        {{ section === 'menu' ? 'Menú' : 'Módulos' }}
                    </div>
                    <ul class="sb-reorder-list">
                        <template v-for="(item, index) in items" :key="item.module_key">
                            <li
                                class="sb-reorder-item"
                                :class="{ 'sb-dragging': drag.section === section && drag.index === index }"
                                draggable="true"
                                @dragstart="onDragStart(section, index)"
                                @dragend="onDragEnd"
                                @dragover.prevent
                                @drop.prevent="onDrop(section, index)"
                            >
                                <i class="fa fa-grip-vertical text-muted me-2"></i>
                                <span class="sb-pos">{{ index + 1 }}</span>
                                <i v-if="item.sidebar_icon" :class="item.sidebar_icon" class="me-1"></i>
                                <span class="sb-label">{{ item.sidebar_label || item.module_key }}</span>
                                <small class="text-muted ms-2">{{ item.module_key }}</small>
                            </li>
                            <ul
                                v-if="(childrenByParent[item.module_key] || []).length"
                                class="sb-reorder-list sb-reorder-children"
                            >
                                <li
                                    v-for="(child, cIndex) in childrenByParent[item.module_key]"
                                    :key="child.module_key"
                                    class="sb-reorder-item sb-reorder-child"
                                    :class="{ 'sb-dragging': dragChild.parent === item.module_key && dragChild.index === cIndex }"
                                    draggable="true"
                                    @dragstart="onChildDragStart(item.module_key, cIndex)"
                                    @dragend="onChildDragEnd"
                                    @dragover.prevent
                                    @drop.prevent="onChildDrop(item.module_key, cIndex)"
                                >
                                    <i class="fa fa-grip-vertical text-muted me-2"></i>
                                    <span class="sb-pos">{{ cIndex + 1 }}</span>
                                    <i v-if="child.sidebar_icon" :class="child.sidebar_icon" class="me-1"></i>
                                    <span class="sb-label">{{ child.sidebar_label || child.module_key }}</span>
                                    <small class="text-muted ms-2">{{ child.module_key }}</small>
                                </li>
                            </ul>
                        </template>
                    </ul>
                </div>
                <div v-if="!Object.keys(sections).length" class="text-grey-6 text-center q-py-md">
                    No hay módulos top-level visibles para reordenar.
                </div>
            </q-card-section>

            <q-card-actions align="right">
                <q-btn flat label="Cerrar" v-close-popup />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script>
export default {
    name: 'SidebarReorder',
    props: {
        modelValue: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'saved'],
    data() {
        return {
            loading: false,
            saving: false,
            sections: {},                 // { menu: [...items], modulos: [...items] }
            childrenByParent: {},         // { parentModuleKey: [...sub_item items] }
            drag: { section: null, index: null },
            dragChild: { parent: null, index: null },
        };
    },
    watch: {
        modelValue(open) {
            if (open) this.load();
        },
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/admin/modules/visibility');
                // Solo top-level del sidebar (direct + visible). El reorden es por sección
                // porque el sidebar ordena por (sidebar_section, sidebar_position).
                const top = (data.data || [])
                    .filter(m => m.show_in_sidebar && m.sidebar_location === 'direct')
                    .sort((a, b) => (a.sidebar_position ?? 99) - (b.sidebar_position ?? 99));

                const grouped = {};
                for (const m of top) {
                    const sec = m.sidebar_section || 'menu';
                    (grouped[sec] = grouped[sec] || []).push(m);
                }
                this.sections = grouped;

                // Hijos anidados (sub_item), agrupados por su módulo padre y
                // ordenados por posición — mismo scope con el que el sidebar
                // real los renderiza (SidebarComposer::compose).
                const childrenByParent = {};
                for (const m of (data.data || [])) {
                    if (m.show_in_sidebar && m.sidebar_location === 'sub_item' && m.sidebar_parent) {
                        (childrenByParent[m.sidebar_parent] = childrenByParent[m.sidebar_parent] || []).push(m);
                    }
                }
                for (const key in childrenByParent) {
                    childrenByParent[key].sort((a, b) => (a.sidebar_position ?? 99) - (b.sidebar_position ?? 99));
                }
                this.childrenByParent = childrenByParent;
            } catch (e) {
                this.notify('negative', 'Error al cargar módulos: ' + (e.response?.data?.message || e.message));
                this.$emit('update:modelValue', false);
            } finally {
                this.loading = false;
            }
        },

        onDragStart(section, index) {
            this.drag = { section, index };
        },
        onDragEnd() {
            this.drag = { section: null, index: null };
        },

        async onDrop(section, dropIndex) {
            const from = this.drag;
            // Solo reorden DENTRO de la misma sección (no se cambia de sección por DnD).
            if (from.section !== section || from.index === null || from.index === dropIndex) {
                this.onDragEnd();
                return;
            }

            const list = this.sections[section];
            const [moved] = list.splice(from.index, 1);
            list.splice(dropIndex, 0, moved);
            this.onDragEnd();

            await this.persist(section);
        },

        async persist(section) {
            const list = this.sections[section];
            // Posiciones contiguas 1..N para esta sección.
            const order = list.map((m, i) => ({ module_key: m.module_key, sidebar_position: i + 1 }));
            await this.persistOrder(order);
        },

        onChildDragStart(parent, index) {
            this.dragChild = { parent, index };
        },
        onChildDragEnd() {
            this.dragChild = { parent: null, index: null };
        },

        async onChildDrop(parent, dropIndex) {
            const from = this.dragChild;
            // Solo reorden DENTRO del mismo padre (no se cambia de padre por DnD).
            if (from.parent !== parent || from.index === null || from.index === dropIndex) {
                this.onChildDragEnd();
                return;
            }

            const list = this.childrenByParent[parent];
            const [moved] = list.splice(from.index, 1);
            list.splice(dropIndex, 0, moved);
            this.onChildDragEnd();

            await this.persistChildren(parent);
        },

        async persistChildren(parent) {
            const list = this.childrenByParent[parent];
            // Posiciones contiguas 1..N dentro de este padre.
            const order = list.map((m, i) => ({ module_key: m.module_key, sidebar_position: i + 1 }));
            await this.persistOrder(order);
        },

        async persistOrder(order) {
            this.saving = true;
            try {
                await axios.post('/admin/modules/visibility/reorder', { order });
                this.notify('positive', 'Orden guardado.');
                this.$emit('saved');
            } catch (e) {
                this.notify('negative', 'No se pudo guardar el orden: ' + (e.response?.data?.message || e.message));
                // El batch es atómico → si falló, la BD no cambió. Recargamos para
                // dejar la UI consistente con el servidor.
                await this.load();
            } finally {
                this.saving = false;
            }
        },

        notify(type, message) {
            this.$q.notify({
                message,
                color: type === 'positive' ? 'green-7' : 'red-7',
                position: 'top-right',
                timeout: 4000,
            });
        },
    },
};
</script>

<style scoped>
.sb-reorder-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.sb-reorder-item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    margin-bottom: 4px;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 6px;
    background: var(--bs-body-bg, #fff);
    cursor: grab;
    user-select: none;
}
.sb-reorder-item:active {
    cursor: grabbing;
}
.sb-dragging {
    opacity: 0.4;
}
.sb-pos {
    display: inline-block;
    min-width: 22px;
    text-align: center;
    font-weight: 600;
    color: #6c757d;
    margin-right: 8px;
}
.sb-label {
    font-weight: 500;
}
.sb-reorder-children {
    margin-left: 28px;
    margin-top: 2px;
    margin-bottom: 6px;
}
.sb-reorder-child {
    padding: 6px 10px;
    font-size: 0.92em;
    background: var(--bs-tertiary-bg, #f8f9fa);
}
</style>
