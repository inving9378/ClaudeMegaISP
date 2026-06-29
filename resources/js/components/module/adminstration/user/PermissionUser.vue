<template>
    <!--
        Wrapper delgado (#114): la lógica vive en el modal compartido
        PermissionAssignmentModal. Conserva la misma interfaz (userId/userName/
        showModal + update:showModal) para no tocar el call site (UserListar.vue).
    -->
    <PermissionAssignmentModal
        entity-type="user"
        :entity-id="userId"
        :entity-name="userName"
        :show-modal="showModal"
        @update:show-modal="(v) => emit('update:showModal', v)"
    />
</template>

<script setup>
import PermissionAssignmentModal from "../PermissionAssignmentModal.vue";

const props = defineProps({
    userId: {
        type: Number,
        default: 0,
    },
    userName: {
        type: String,
        required: true,
    },
    showModal: {
        type: Boolean,
        required: true,
    },
});

const emit = defineEmits(["update:showModal"]);
</script>
