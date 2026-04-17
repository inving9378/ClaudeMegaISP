<template>
    <div>
        <h5 class="modal-title mb-3">Agregar Administrador</h5>
        <hr />
        <form @submit.prevent="addUser" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <!-- Nombre -->
                        <div class="mb-3">
                            <label class="form-label">Nombre(s)</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Ingresa el nombre(s)"
                                v-model="data.name"
                                @input="clearServerErrors('name')"
                            />
                            <span
                                v-for="error in v$.name.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.name"
                                class="error-message"
                            >
                                {{ serverErrors.name[0] }}
                            </span>
                        </div>

                        <!-- Apellido Paterno -->
                        <div class="mb-3">
                            <label class="form-label">Apellido Paterno</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Ingresa el apellido paterno"
                                v-model="data.father_last_name"
                                @input="clearServerErrors('father_last_name')"
                            />
                            <span
                                v-for="error in v$.father_last_name.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.father_last_name"
                                class="error-message"
                            >
                                {{ serverErrors.father_last_name[0] }}
                            </span>
                        </div>

                        <!-- Apellido Materno -->
                        <div class="mb-3">
                            <label class="form-label">Apellido Materno</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Ingresa el apellido materno"
                                v-model="data.mother_last_name"
                                @input="clearServerErrors('mother_last_name')"
                            />
                            <span
                                v-for="error in v$.mother_last_name.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.mother_last_name"
                                class="error-message"
                            >
                                {{ serverErrors.mother_last_name[0] }}
                            </span>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label class="form-label">Correo Electrónico</label>
                            <input
                                type="email"
                                class="form-control"
                                placeholder="example@email.com"
                                v-model="data.email"
                                @input="clearServerErrors('email')"
                            />
                            <span
                                v-for="error in v$.email.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.email"
                                class="error-message"
                            >
                                {{ serverErrors.email[0] }}
                            </span>
                        </div>

                        <!-- Teléfono -->
                        <div class="mb-3">
                            <label class="form-label">Teléfono</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="(455) 000 0000"
                                v-model="data.phone"
                                @input="clearServerErrors('phone')"
                            />
                            <span
                                v-for="error in v$.phone.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.phone"
                                class="error-message"
                            >
                                {{ serverErrors.phone[0] }}
                            </span>
                        </div>

                        <!-- Dirección -->
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Ingresa la dirección"
                                v-model="data.address"
                                @input="clearServerErrors('address')"
                            />
                            <span
                                v-for="error in v$.address.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.address"
                                class="error-message"
                            >
                                {{ serverErrors.address[0] }}
                            </span>
                        </div>

                        <!-- Estado del vendedor -->
                        <div
                            class="mb-3"
                            v-if="
                                getRoleName(data.role) === 'Vendedor' ||
                                data.is_seller
                            "
                        >
                            <label class="form-label"
                                >Estado del vendedor</label
                            >
                            <select
                                class="form-select"
                                v-model="data.status_id"
                                @change="getStatus"
                                @input="clearServerErrors('status_id')"
                            >
                                <option
                                    v-for="status in statusSellers"
                                    :key="status.id"
                                    :value="status.id"
                                >
                                    {{ status.name }}
                                </option>
                            </select>
                            <span
                                v-for="error in v$.status_id.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.status_id"
                                class="error-message"
                            >
                                {{ serverErrors.status_id[0] }}
                            </span>
                        </div>

                        <!-- Fotografía -->
                        <div class="mb-3">
                            <label class="form-label">Fotografía</label>
                            <input
                                type="file"
                                class="form-control"
                                accept=".png, .jpg, .jpeg"
                                @change="onImageChange"
                            />
                        </div>

                        <!-- Color -->
                        <QColorPicker
                            :errors="{}"
                            :property="{ field: 'color' }"
                            :modelValue="'#38DE6B'"
                            @update-field="updateThisField"
                            v-model="data.color"
                        />
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <!-- Estado -->
                        <div class="mb-3">
                            <label class="form-label">Estado</label>
                            <select
                                class="form-select"
                                v-model="state"
                                @change="getState"
                                @input="clearServerErrors('state_country')"
                            >
                                <option disabled value="">
                                    Selecciona el estado
                                </option>
                                <option
                                    v-for="state in states"
                                    :key="state.id"
                                    :value="state.id"
                                >
                                    {{ state.name }}
                                </option>
                            </select>
                            <span
                                v-for="error in v$.state_country.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.state_country"
                                class="error-message"
                            >
                                {{ serverErrors.state_country[0] }}
                            </span>
                        </div>

                        <!-- Municipio y Colonia -->
                        <div class="mb-3 d-flex gap-2">
                            <div class="col-md-6">
                                <label class="form-label">Municipio</label>
                                <select
                                    class="form-select"
                                    v-model="municipality"
                                    @change="getColony"
                                    @input="
                                        clearServerErrors('city_municipality')
                                    "
                                >
                                    <option disabled value="">
                                        Selecciona el municipio
                                    </option>
                                    <option
                                        v-for="municipality in municipalities"
                                        :key="municipality.id"
                                        :value="municipality.id"
                                    >
                                        {{ municipality.name }}
                                    </option>
                                </select>
                                <span
                                    v-for="error in v$.city_municipality
                                        .$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                >
                                    {{ error.$message }}
                                </span>
                                <span
                                    v-if="serverErrors.city_municipality"
                                    class="error-message"
                                >
                                    {{ serverErrors.city_municipality[0] }}
                                </span>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Colonia</label>
                                <select
                                    class="form-select"
                                    v-model="data.colony"
                                    @input="clearServerErrors('colony')"
                                >
                                    <option disabled value="">
                                        Selecciona la colonia
                                    </option>
                                    <option
                                        v-for="colony in colonies"
                                        :key="colony.id"
                                    >
                                        {{ colony.name }}
                                    </option>
                                </select>
                                <span
                                    v-for="error in v$.colony.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                >
                                    {{ error.$message }}
                                </span>
                                <span
                                    v-if="serverErrors.colony"
                                    class="error-message"
                                >
                                    {{ serverErrors.colony[0] }}
                                </span>
                            </div>
                        </div>

                        <!-- Código Postal -->
                        <div class="mb-3">
                            <label class="form-label">Código Postal</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Ingresa el código postal"
                                v-model="data.code_postal"
                                @input="clearServerErrors('code_postal')"
                            />
                            <span
                                v-for="error in v$.code_postal.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.code_postal"
                                class="error-message"
                            >
                                {{ serverErrors.code_postal[0] }}
                            </span>
                        </div>

                        <!-- RFC -->
                        <div class="mb-3">
                            <label class="form-label">RFC</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Ingresa el RFC"
                                v-model="data.rfc"
                                @input="clearServerErrors('rfc')"
                            />
                            <span
                                v-for="error in v$.rfc.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span v-if="serverErrors.rfc" class="error-message">
                                {{ serverErrors.rfc[0] }}
                            </span>
                        </div>

                        <!-- Nombre de usuario -->
                        <div class="mb-3">
                            <label class="form-label">Nombre usuario</label>
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Ingresa el nombre de usuario"
                                v-model="data.login_user"
                                @input="clearServerErrors('login_user')"
                            />
                            <span
                                v-for="error in v$.login_user.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.login_user"
                                class="error-message"
                            >
                                {{ serverErrors.login_user[0] }}
                            </span>
                        </div>

                        <!-- Rol -->
                        <div class="d-flex align-items-center gap-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rol</label>
                                <select
                                    class="form-select"
                                    v-model="data.role"
                                    @input="clearServerErrors('role')"
                                >
                                    <option
                                        v-for="role in roles"
                                        :key="role.id"
                                        :value="role.id"
                                    >
                                        {{ role.name }}
                                    </option>
                                </select>
                                <span
                                    v-for="error in v$.role.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                >
                                    {{ error.$message }}
                                </span>
                                <span
                                    v-if="serverErrors.role"
                                    class="error-message"
                                >
                                    {{ serverErrors.role[0] }}
                                </span>
                            </div>
                            <div class="col-md-6 form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    v-model="data.is_seller"
                                    v-bind:disabled="data.role === 4"
                                />
                                <label class="form-check-label">
                                    Añadir rol de vendedor
                                </label>
                            </div>
                        </div>

                        <!-- Tipo de vendedor -->
                        <div
                            class="mb-3"
                            v-if="
                                getRoleName(data.role) === 'Vendedor' ||
                                data.is_seller
                            "
                        >
                            <label class="form-label">Tipo de vendedor</label>
                            <select
                                class="form-select"
                                v-model="data.type_id"
                                @change="getTypes"
                                @input="clearServerErrors('type_id')"
                            >
                                <option
                                    v-for="typeSeller in typesSellers"
                                    :key="typeSeller.id"
                                    :value="typeSeller.id"
                                >
                                    {{ typeSeller.name }}
                                </option>
                            </select>
                            <span
                                v-if="serverErrors.type_id"
                                class="error-message"
                            >
                                {{ serverErrors.type_id[0] }}
                            </span>
                        </div>

                        <!-- Regla asociada -->
                        <div
                            class="mb-3"
                            v-if="
                                getRoleName(data.role) === 'Vendedor' ||
                                data.is_seller
                            "
                        >
                            <label class="form-label">Regla asociada</label>
                            <select
                                class="form-select"
                                v-model="data.rule_id"
                                @input="clearServerErrors('rule_id')"
                            >
                                <option value="">Selecciona la regla</option>
                                <option
                                    v-for="r in commissionsRules"
                                    :key="r.id"
                                    :value="r.id"
                                >
                                    {{ r.name }}
                                </option>
                            </select>
                            <span
                                v-if="serverErrors.rule_id"
                                class="error-message"
                            >
                                {{ serverErrors.rule_id[0] }}
                            </span>
                        </div>

                        <!-- Contraseña -->
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <div class="input-group auth-pass-inputgroup">
                                <input
                                    :type="showPassword ? 'text' : 'password'"
                                    class="form-control"
                                    placeholder="Ingresa la contraseña"
                                    v-model="data.password"
                                    @input="clearServerErrors('password')"
                                />
                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    @click="showPassword = !showPassword"
                                >
                                    <i
                                        class="fas"
                                        :class="
                                            showPassword
                                                ? 'fa-eye-slash'
                                                : 'fa-eye'
                                        "
                                    ></i>
                                </button>
                            </div>
                            <span
                                v-for="error in v$.password.$errors"
                                :key="error.$uid"
                                class="error-message"
                            >
                                {{ error.$message }}
                            </span>
                            <span
                                v-if="serverErrors.password"
                                class="error-message"
                            >
                                {{ serverErrors.password[0] }}
                            </span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sucursal</label>
                            <select
                                class="form-select"
                                v-model="data.sucursal_id"
                                @input="clearServerErrors('sucursal')"
                            >
                                <option
                                    v-for="s in sucursals"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer justify-content-center gap-3">
                    <a
                        type="button"
                        class="btn btn-secondary"
                        :href="'/administracion/user'"
                    >
                        Regresar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from "vue";
import Swal from "sweetalert2";
import useVuelidate from "@vuelidate/core";
import {
    required,
    minLength,
    email,
    helpers,
    requiredIf,
} from "@vuelidate/validators";
import {
    createUser,
    getRoles,
    getStates,
    getMunicipalities,
    getColonies,
    getStatusSellers,
    getTypesSellers,
    getRules,
} from "./helper/request.js";
import QColorPicker from "../../../../shared/QColorPicker.vue";

const props = defineProps({
    sucursals: {
        type: Array,
        default: [],
    },
});

const showPassword = ref(false);
const roles = ref([]);
const states = ref([]);
const municipalities = ref([]);
const colonies = ref([]);

const state = ref(null);
const municipality = ref(null);
const statusSellers = ref([]);
const typesSellers = ref([]);
const commissionsRules = ref([]);

const data = reactive({
    name: "",
    father_last_name: "",
    mother_last_name: "",
    email: "",
    phone: "",
    address: "",
    photography: null,
    city_municipality: "",
    colony: "",
    state_country: "",
    code_postal: "",
    rfc: "",
    login_user: "",
    role: null,
    password: "",
    is_seller: false,
    status_id: null,
    type_id: null,
    rule_id: null,
    sucursal_id: null,
    color: "#38DE6B",
});

onMounted(async () => {
    roles.value = await getRoles();
    states.value = await getStates();
    getStatus();
    getTypes();
    commissionsRules.value = await getRules();

    const urlParams = new URLSearchParams(window.location.search);
    const roleParam = urlParams.get("role");
    if (roleParam == "Vendedor") {
        const sellerRole = roles.value.find((role) => role.name === "Vendedor");
        if (sellerRole) {
            data.role = sellerRole.id;
        }
    }
});

watch(
    () => data.role,
    (n) => {
        data.rule_id = null;
    }
);

const updateThisField = ({ field, value }) => {
    if (field == "color") {
        data.color = value.value;
    }
};

const serverErrors = ref({});

const rules = computed(() => {
    return {
        name: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        father_last_name: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        mother_last_name: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        email: {
            required: helpers.withMessage("Este campo es requerido", required),
            email,
        },
        phone: {
            required: helpers.withMessage("Este campo es requerido", required),
            minLength: helpers.withMessage(
                "El número de teléfono debe tener al menos 10 dígitos",
                minLength(10)
            ),
        },
        address: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        city_municipality: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        colony: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        state_country: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        code_postal: {
            required: helpers.withMessage("Este campo es requerido", required),
            minLength: helpers.withMessage(
                "El código postal debe contener 5 dígitos",
                minLength(5)
            ),
        },
        rfc: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        login_user: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        role: {
            required: helpers.withMessage("Este campo es requerido", required),
        },
        password: {
            required: helpers.withMessage("Este campo es requerido", required),
            isSecure: helpers.withMessage(
                "La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número",
                (value) => {
                    if (!value) return false;
                    const passwordRegex =
                        /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
                    return passwordRegex.test(value);
                }
            ),
        },
        status_id: {
            requiredIfSellerOrRole6: helpers.withMessage(
                "Este campo es requerido",
                requiredIf(() => data.is_seller || data.role === 6)
            ),
        },
    };
});

const v$ = useVuelidate(rules, data);

const onImageChange = (e) => {
    data.photography = e.target.files[0];
};

const getState = async () => {
    const selectedState = states.value.find((s) => s.id === state.value);
    municipalities.value = await getMunicipalities(selectedState.id);
    data.state_country = selectedState.name;
};

const getColony = async () => {
    const selectedMunicipality = municipalities.value.find(
        (m) => m.id === municipality.value
    );
    colonies.value = await getColonies(selectedMunicipality.id);
    data.city_municipality = selectedMunicipality.name;
};

const getStatus = async () => {
    statusSellers.value = await getStatusSellers();
};

const getTypes = async () => {
    typesSellers.value = await getTypesSellers();
};

const getRoleName = (roleId) => {
    const role = roles.value.find((rol) => rol.id === roleId);
    return role ? role.name : "";
};

const clearServerErrors = (field) => {
    if (serverErrors.value[field]) {
        delete serverErrors.value[field];
    }
};

const addUser = async () => {
    try {
        const result = await v$.value.$validate();

        if (result) {
            let formData = new FormData();
            Object.keys(data).forEach((key) => {
                let value = data[key];
                if (key === "is_seller") {
                    value = value ? 1 : 0;
                }
                formData.append(key, value);
            });
            const response = await createUser(formData);
            Swal.fire("¡Añadido!", response.message, "success").then(
                (result) => {
                    if (result.isConfirmed) {
                        window.location.href = "/administracion/user";
                    }
                }
            );
        } else {
            Swal.fire(
                "¡Error!",
                "Por favor, llena los campos correctamente",
                "warning"
            );
        }
    } catch (error) {
        const errorsResponse = error.response.data.errors;
        serverErrors.value = errorsResponse;
        Swal.fire(
            "¡Error!",
            "Verifica que el correo electrónico y nombre de usuario no hayan sido registrados antes, ¡Deben ser unicos!",
            "error"
        );
    }
};
</script>

<style>
.error-message {
    color: red;
}
</style>
