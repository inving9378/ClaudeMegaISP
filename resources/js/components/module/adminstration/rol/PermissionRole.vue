<template>
    <!--
        Wrapper delgado (#114): la lógica vive en el modal compartido
        PermissionAssignmentModal. Conserva la misma interfaz (roleId/roleName/
        showModal + update:showModal) para no tocar el call site (ListarRol.vue).
    -->
    <PermissionAssignmentModal
        entity-type="role"
        :entity-id="roleId"
        :entity-name="roleName"
        :show-modal="showModal"
        @update:show-modal="(v) => emit('update:showModal', v)"
    />
</template>

<script setup>
import PermissionAssignmentModal from "../PermissionAssignmentModal.vue";

const props = defineProps({
    roleId: {
        type: Number,
        default: 0,
    },
    roleName: {
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
