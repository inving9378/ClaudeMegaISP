<template>
    <div class="vnd-wrap q-pa-md">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-toggle2-on fs-4"></i>
            <h1 class="h4 fw-bold mb-0">Estados de los vendedores</h1>
        </div>
    <div class="row">
        <div class="col-md-6">
            <div class="card vnd-card vh-50">
                <div class="card-header">
                    <h5 class="vnd-title mb-0">Agregar status del vendedor</h5>
                </div>
                <div class="card-body">
                    <form @submit.prevent="isEditing ? update() : create()">
                        <div class="form-group">
                            <label for="name">Nombre del status</label>
                            <input
                                type="text"
                                class="form-control"
                                v-model="state.name"
                            />
                            <span
                                v-for="error in v$.name.$errors"
                                :key="error.$uid"
                                class="error-message"
                                >{{ error.$message }}</span
                            >
                        </div>
                        <div class="mt-4">
                            <button class="btn btn-primary" type="submit">
                                {{
                                    isEditing
                                        ? "Actualizar status"
                                        : "Guardar status"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card vnd-card vh-100">
                <div class="card-body">
                    <div
                        v-for="state in data"
                        :key="state.id"
                        class="card vnd-card vnd-list-item"
                    >
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="mb-0">{{ state.name }}</h5>
                                <div
                                    class="d-flex justify-content-center gap-2"
                                >
                                    <q-btn
                                        flat
                                        round
                                        color="primary"
                                        icon="edit"
                                        size="sm"
                                        @click="edit(state)"
                                    />
                                    <q-btn
                                        flat
                                        round
                                        color="danger"
                                        icon="delete"
                                        size="sm"
                                        @click="remove(state)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import Swal from "sweetalert2";
import useVuelidate from "@vuelidate/core";
import { required, helpers } from "@vuelidate/validators";
import {
    getAll,
    getById,
    createStatusSeller,
    updateStatusSeller,
    deleteStatusSeller,
} from "./helper/helper.js";

const data = ref([]);
const state = ref({});
const isEditing = ref(false);

onMounted(async () => {
    data.value = await getAll();
});

const rules = computed(() => {
    return {
        name: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
    };
});

const v$ = useVuelidate(rules, state);

const edit = async (stateToEdit) => {
    state.value = await getById(stateToEdit.id);
    isEditing.value = true;
};

const create = async () => {
    try {
        const result = await v$.value.$validate();

        if (!result) {
            Swal.fire(
                "¡Error!",
                "Por favor, completa el formulario",
                "warning"
            );
            return;
        }

        const response = await createStatusSeller(state.value);
        Swal.fire("¡Creado!", response.message, "success");
        isEditing.value = false;
        data.value = await getAll();
        state.value = {};
    } catch (error) {
        console.log(error.response.data.message);
        Swal.fire("¡Error!", "Ocurrió un error", "error");
    }
};

const update = async () => {
    try {
        const result = await v$.value.$validate();

        if (!result) {
            Swal.fire(
                "¡Error!",
                "Por favor, completa el formulario",
                "warning"
            );
            return;
        }

        const response = await updateStatusSeller(state.value.id, state.value);
        Swal.fire("¡Actualizado!", response.message, "success");
        isEditing.value = false;
        data.value = await getAll();
        state.value = {};
    } catch (error) {
        console.log(error.response.data.message);
        Swal.fire("¡Error!", error.response.data.message, "error");
    }
};

const remove = async (stateToRemove) => {
    Swal.fire({
        title: "¿Estás seguro de eliminar el estado del vendedor?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "¡Sí, eliminar!",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await deleteStatusSeller(stateToRemove.id);
                Swal.fire("¡Eliminado!", response.message, "success");
                data.value = await getAll();
            } catch (error) {
                console.log(error.response.data.message);
                Swal.fire(
                    "¡Error!",
                    "Hubo un error al actualizar los datos",
                    "error"
                );
            }
        }
    });
};
</script>

<style scoped>
/* Restyle con el sistema visual de la Torre de Control, tokens locales por componente (mismo
   patrón que VendedorListar.vue). Item #9991079. */
.vnd-wrap {
    --vnd-ink: #111827;
}

.vnd-title {
    color: var(--vnd-ink);
}

.vnd-card {
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.vnd-list-item {
    margin-bottom: 0.75rem;
}

.vnd-list-item:last-child {
    margin-bottom: 0;
}
</style>
