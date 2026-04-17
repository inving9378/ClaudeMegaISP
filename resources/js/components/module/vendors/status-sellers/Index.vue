<template>
    <h3 class="text-center mb-4">Estados de los vendedores</h3>
    <div class="row">
        <div class="col-md-6">
            <div class="card vh-50">
                <div class="card-header">
                    <h5>Agregar status del vendedor</h5>
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
            <div class="card vh-100">
                <div class="card-body">
                    <div v-for="state in data" :key="state.id" class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="">{{ state.name }}</h5>
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
