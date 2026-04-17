<template>
    <q-btn-dropdown
        color="primary"
        label="Actualización"
        no-caps
        split
        :loading="loading"
        @click="emits('reload', false)"
        v-if="hasPermission?.data.canView('sync_from_api')"
    >
        <q-list dense>
            <q-item
                clickable
                :disable="loading"
                @click="() => emits('reload', false)"
            >
                <q-item-section>
                    <q-item-label>Local</q-item-label>
                </q-item-section>
            </q-item>
            <q-item
                clickable
                :disable="loading"
                @click="() => emits('reload', true)"
            >
                <q-item-section>
                    <q-item-label>Desde API SmartOLT</q-item-label>
                </q-item-section>
            </q-item>
        </q-list>
    </q-btn-dropdown>
    <q-btn
        icon="mdi-sync"
        color="primary"
        :loading="loading"
        @click="() => emits('reload')"
        v-else
    >
        <q-tooltip> Actualizar </q-tooltip>
    </q-btn>
</template>

<script setup>
defineOptions({
    name: "SyncFromApi",
});

const props = defineProps({
    hasPermission: Object,
    loading: Boolean,
});

const emits = defineEmits(["reload"]);
</script>
