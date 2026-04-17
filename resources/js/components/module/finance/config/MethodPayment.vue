<template>
    <h3 class="text-center mb-4">Metodos de pago</h3>
    <div class="row">
        <div class="col-md-6">
            <div class="card vh-50">
                <div class="card-header">
                    <h5>Agregar método de pago</h5>
                </div>
                <div class="card-body">
                    <form @submit.prevent="isEditing ? update() : create()">
                        <div class="form-group">
                            <div>
                                <label for="name">Nombre</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    v-model="method.type"
                                />
                                <span
                                    v-for="error in v$.type.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="form-check form-switch mt-3">
                                <input
                                    class="form-check-input switch"
                                    type="checkbox"
                                    id="flexSwitchCheckChecked"
                                    v-model="method.active"
                                />
                                <label
                                    class="form-check-label"
                                    for="flexSwitchCheckChecked"
                                    >Estado del método de pago (Activo -
                                    Inactivo)</label
                                >
                            </div>
                        </div>
                        <div class="mt-4">
                            <button class="btn btn-primary" type="submit">
                                {{
                                    isEditing
                                        ? "Actualizar metodo de pago"
                                        : "Guardar metodo de pago"
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
                    <div v-for="method in data" :key="method.id" class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="">{{ method.type }}</h5>
                                <div
                                    class="d-flex justify-content-center gap-2"
                                >
                                    <q-btn
                                        flat
                                        round
                                        color="primary"
                                        icon="edit"
                                        size="sm"
                                        @click="edit(method)"
                                    />
                                    <q-btn
                                        flat
                                        round
                                        color="danger"
                                        icon="delete"
                                        size="sm"
                                        @click="remove(method)"
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
    editMethodPayment,
    createMethodPayment,
    updateMethodPayment,
    deleteMethodPayment,
} from "./helper/helper.js";

const data = ref([]);
const method = ref({});
const isEditing = ref(false);

onMounted(async () => {
    data.value = await getAll();
});

const rules = computed(() => {
    return {
        type: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
    };
});

const v$ = useVuelidate(rules, method);

const edit = async (methodToEdit) => {
    method.value = await editMethodPayment(methodToEdit.id);
    method.value.active = Boolean(method.value.active);
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

        const response = await createMethodPayment(method.value);
        Swal.fire("¡Creado!", response.message, "success");
        isEditing.value = false;
        data.value = await getAll();
        method.value = {};
    } catch (error) {
        Swal.fire(
            "¡Error!",
            "Hubo un error, verifica que el metodo de pago no esta siendo duplicado",
            "error"
        );
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

        method.value.active = method.value.active ? 1 : 0;

        const response = await updateMethodPayment(
            method.value.id,
            method.value
        );
        Swal.fire("¡Actualizado!", response.message, "success");
        isEditing.value = false;
        data.value = await getAll();
        method.value = {};
    } catch (error) {
        console.log(error.response.data.message);
        Swal.fire("¡Error!", "Ocurrio un error", "error");
    }
};

const remove = async (methodToRemove) => {
    Swal.fire({
        title: "¿Estás seguro de eliminar este método de pago?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "¡Sí, eliminar!",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await deleteMethodPayment(methodToRemove.id);
                Swal.fire("¡Eliminado!", response.message, "success");
                data.value = await getAll();
            } catch (error) {
                Swal.fire(
                    "¡Error!",
                    "Hubo un error al eliminar el medio de venta",
                    "error"
                );
            }
        }
    });
};
</script>

<style scoped>
.switch {
    width: 45px;
    height: 20px;
    margin-right: 8px;
}
</style>
