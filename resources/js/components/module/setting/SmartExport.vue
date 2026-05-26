<template>
    <div class="smart-export-wrapper p-3">
        <div class="row q-col-gutter-md">
            <!-- Columna izquierda: selección -->
            <div class="col-12 col-md-7">
                <q-card flat bordered>
                    <q-card-section>
                        <div class="text-h6">
                            <i class="fas fa-cloud-download-alt text-primary"></i>
                            Selecciona los módulos a exportar
                        </div>
                        <div class="text-caption text-grey-7">
                            Cada módulo contiene varias tablas relacionadas.
                            Las columnas sensibles se eliminan automáticamente.
                        </div>
                    </q-card-section>
                    <q-separator />
                    <q-card-section v-if="loading">
                        <q-linear-progress indeterminate color="primary" />
                        <div class="text-caption q-mt-sm">
                            Cargando módulos y conteo de registros...
                        </div>
                    </q-card-section>
                    <q-list separator v-else>
                        <q-item
                            v-for="m in modules"
                            :key="m.key"
                            clickable
                            tag="label"
                        >
                            <q-item-section avatar>
                                <q-checkbox
                                    v-model="selected"
                                    :val="m.key"
                                    color="primary"
                                />
                            </q-item-section>
                            <q-item-section avatar>
                                <q-avatar
                                    rounded
                                    color="primary"
                                    text-color="white"
                                >
                                    <i :class="m.icon"></i>
                                </q-avatar>
                            </q-item-section>
                            <q-item-section>
                                <q-item-label>{{ m.label }}</q-item-label>
                                <q-item-label caption>
                                    <q-chip
                                        v-for="t in m.tables"
                                        :key="t.name"
                                        dense
                                        :color="t.exists ? 'grey-3' : 'red-2'"
                                        :text-color="
                                            t.exists ? 'grey-9' : 'red-10'
                                        "
                                    >
                                        {{ t.name }} · {{ t.count }}
                                    </q-chip>
                                </q-item-label>
                                <q-item-label
                                    v-if="m.sensitive.length"
                                    caption
                                    class="text-orange-9 q-mt-xs"
                                >
                                    <i class="fas fa-shield-alt"></i>
                                    Columnas sensibles omitidas:
                                    {{ m.sensitive.join(", ") }}
                                </q-item-label>
                            </q-item-section>
                            <q-item-section side top>
                                <q-badge color="primary">
                                    {{ m.total_rows }} filas
                                </q-badge>
                            </q-item-section>
                        </q-item>
                    </q-list>
                </q-card>
            </div>

            <!-- Columna derecha: opciones + acción -->
            <div class="col-12 col-md-5">
                <q-card flat bordered class="sticky-card">
                    <q-card-section>
                        <div class="text-h6">
                            <i class="fas fa-cog text-primary"></i>
                            Opciones de exportación
                        </div>
                    </q-card-section>
                    <q-separator />
                    <q-card-section>
                        <div class="text-subtitle2 q-mb-sm">Formato</div>
                        <q-option-group
                            v-model="format"
                            :options="formatOptions"
                            color="primary"
                            type="radio"
                        />
                        <q-separator class="q-my-md" />
                        <q-toggle
                            v-model="stripSensitive"
                            label="Quitar columnas sensibles (recomendado)"
                            color="primary"
                        />
                        <q-separator class="q-my-md" />
                        <div class="text-subtitle2 q-mb-sm">Resumen</div>
                        <div class="text-caption">
                            Módulos seleccionados:
                            <strong>{{ selected.length }}</strong>
                        </div>
                        <div class="text-caption">
                            Filas totales:
                            <strong>{{ selectedRowsCount }}</strong>
                        </div>
                    </q-card-section>
                    <q-separator />
                    <q-card-actions align="right">
                        <q-btn
                            :disable="!selected.length || generating"
                            :loading="generating"
                            color="primary"
                            icon="download"
                            label="Generar y descargar"
                            @click="generate"
                        />
                    </q-card-actions>

                    <q-card-section v-if="lastDownload">
                        <q-banner class="bg-green-1 text-green-10" rounded>
                            <template v-slot:avatar>
                                <i class="fas fa-check-circle text-positive"></i>
                            </template>
                            Exportación lista:
                            <strong>{{ lastDownload.filename }}</strong>
                            ({{ humanSize(lastDownload.size) }})
                            <template v-slot:action>
                                <q-btn
                                    flat
                                    color="primary"
                                    label="Descargar de nuevo"
                                    :href="lastDownload.download_url"
                                    target="_blank"
                                />
                            </template>
                        </q-banner>
                    </q-card-section>
                </q-card>
            </div>
        </div>
    </div>
</template>

<script>
import { computed, onMounted, ref } from "vue";

export default {
    name: "SmartExport",
    setup() {
        const modules = ref([]);
        const selected = ref([]);
        const format = ref("sql");
        const stripSensitive = ref(true);
        const loading = ref(false);
        const generating = ref(false);
        const lastDownload = ref(null);

        const formatOptions = [
            { label: "SQL (.sql) — para restaurar en MySQL", value: "sql" },
            { label: "JSON (.json) — estructurado", value: "json" },
            { label: "Excel (.xlsx) — una hoja por tabla", value: "xlsx" },
        ];

        const selectedRowsCount = computed(() => {
            return modules.value
                .filter((m) => selected.value.includes(m.key))
                .reduce((acc, m) => acc + (m.total_rows || 0), 0);
        });

        function humanSize(bytes) {
            if (!bytes) return "0 B";
            const u = ["B", "KB", "MB", "GB"];
            let i = 0;
            while (bytes >= 1024 && i < u.length - 1) {
                bytes /= 1024;
                i++;
            }
            return `${bytes.toFixed(1)} ${u[i]}`;
        }

        async function loadModules() {
            loading.value = true;
            try {
                const { data } = await axios.get(
                    "/configuracion/smart-export/modules"
                );
                if (data.success) modules.value = data.modules;
            } catch (e) {
                console.error(e);
                alert("No se pudieron cargar los módulos.");
            } finally {
                loading.value = false;
            }
        }

        async function generate() {
            generating.value = true;
            try {
                const { data } = await axios.post(
                    "/configuracion/smart-export/generate",
                    {
                        modules: selected.value,
                        format: format.value,
                        strip_sensitive: stripSensitive.value,
                    }
                );
                if (!data.success) throw new Error(data.message);
                lastDownload.value = data;
                // Trigger automático del download.
                window.location.href = data.download_url;
            } catch (e) {
                console.error(e);
                alert(
                    "Error generando la exportación: " +
                        (e.response?.data?.message || e.message)
                );
            } finally {
                generating.value = false;
            }
        }

        onMounted(loadModules);

        return {
            modules,
            selected,
            format,
            stripSensitive,
            loading,
            generating,
            lastDownload,
            formatOptions,
            selectedRowsCount,
            humanSize,
            loadModules,
            generate,
        };
    },
};
</script>

<style scoped>
.sticky-card {
    position: sticky;
    top: 80px;
}
</style>
