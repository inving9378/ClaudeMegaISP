<template>
    <div class="wvc-bar">
        <!-- Rango fechas -->
        <div class="wvc-group">
            <label class="wvc-label"><i class="ti ti-calendar me-1"></i>Desde</label>
            <input
                type="date"
                class="wvc-input"
                :value="from"
                @change="$emit('update:from', $event.target.value)"
            />
        </div>
        <div class="wvc-group">
            <label class="wvc-label">Hasta</label>
            <input
                type="date"
                class="wvc-input"
                :value="to"
                @change="$emit('update:to', $event.target.value)"
            />
        </div>

        <!-- Filtros propios de cada pestaña (equipo, factor, etc.) -->
        <slot />

        <!-- Período (granularidad) -->
        <div class="wvc-group">
            <label class="wvc-label"><i class="ti ti-calendar-stats me-1"></i>Período</label>
            <div class="wvc-pills">
                <button
                    v-for="g in GRANS"
                    :key="g.value"
                    class="wvc-pill"
                    :class="{ active: granularidad === g.value }"
                    @click="$emit('update:granularidad', g.value)"
                >{{ g.label }}</button>
            </div>
        </div>

        <!-- Refresh -->
        <button
            class="wvc-refresh"
            @click="$emit('refresh')"
            :disabled="loading"
            title="Actualizar"
        >
            <i :class="['ti', loading ? 'ti-loader-2 wvc-spin' : 'ti-refresh']"></i>
        </button>
    </div>
</template>

<script setup>
const GRANS = [
    { value: 'dia',    label: 'Día' },
    { value: 'semana', label: 'Semana' },
    { value: 'mes',    label: 'Mes' },
    { value: 'ano',    label: 'Año' },
];

defineProps({
    from:         { type: String, default: '' },
    to:           { type: String, default: '' },
    granularidad: { type: String, default: 'semana' },
    loading:      { type: Boolean, default: false },
});

defineEmits(['update:from', 'update:to', 'update:granularidad', 'refresh']);
</script>

<style scoped>
.wvc-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 10px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 8px;
    padding: 10px 14px;
}

.wvc-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 110px;
}

.wvc-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #9999b0;
}

.wvc-input, :slotted(.wvc-select) {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 6px;
    color: #e8e8f0;
    font-size: 12px;
    padding: 5px 8px;
    outline: none;
    transition: border-color 0.15s;
}
.wvc-input:focus { border-color: rgba(83,74,183,0.7); }

.wvc-pills {
    display: flex;
    gap: 3px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    padding: 3px;
}

.wvc-pill {
    background: transparent;
    border: none;
    color: #9896b8;
    font-size: 11px;
    padding: 3px 9px;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    white-space: nowrap;
}
.wvc-pill.active { background: #534AB7; color: #fff; }
.wvc-pill:hover:not(.active) { background: rgba(83,74,183,0.25); color: #c0b8ff; }

.wvc-refresh {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: #9999b0;
    padding: 5px 10px;
    cursor: pointer;
    align-self: flex-end;
    transition: all 0.15s;
    font-size: 15px;
}
.wvc-refresh:hover { background: rgba(255,255,255,0.09); color: #e8e8f0; }
.wvc-refresh:disabled { opacity: 0.4; cursor: not-allowed; }

.wvc-spin {
    display: inline-block;
    animation: wvc-spin 0.8s linear infinite;
}
@keyframes wvc-spin { to { transform: rotate(360deg); } }
</style>
