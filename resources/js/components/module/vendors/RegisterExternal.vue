<template>
    <div class="container-register">
        <div class="form-container">
            <h5 class="modal-title mb-3">Registrarme como vendedor externo</h5>
            <hr />
            <form @submit.prevent="addUser" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="mb-3">
                                <label for="name" class="form-label"
                                    >Nombre(s)</label
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa el nombre(s)"
                                    v-model="data.name"
                                />
                                <span
                                    v-for="error in v$.name.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="father_last_name" class="form-label"
                                    >Apellido Paterno</label
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa el apellido paterno"
                                    v-model="data.father_last_name"
                                />
                                <span
                                    v-for="error in v$.father_last_name.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="mother_last_name" class="form-label"
                                    >Apellido Materno</label
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa el apellido materno"
                                    v-model="data.mother_last_name"
                                />
                                <span
                                    v-for="error in v$.mother_last_name.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label"
                                    >Correo Electrónico</label
                                >
                                <input
                                    type="email"
                                    class="form-control"
                                    placeholder="example@email.com"
                                    v-model="data.email"
                                />
                                <span
                                    v-for="error in v$.email.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label"
                                    >Teléfono</label
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="(455) 000 0000"
                                    v-model="data.phone"
                                />
                                <span
                                    v-for="error in v$.phone.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label"
                                    >Dirección</label
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa la dirección"
                                    v-model="data.address"
                                />
                                <span
                                    v-for="error in v$.address.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="photography" class="form-label"
                                    >Fotografía</label
                                >
                                <input
                                    type="file"
                                    class="form-control"
                                    accept=".png, .jpg, .jpeg"
                                    @change="onImageChange"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="mb-3">
                                <label for="state_country" class="form-label"
                                    >Estado</label
                                >
                                <select
                                    class="form-select"
                                    v-model="state"
                                    @change="getState"
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
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3 d-flex gap-2">
                                <div class="col-md-6">
                                    <label
                                        for="city_municipality"
                                        class="form-label"
                                        >Municipio</label
                                    >
                                    <select
                                        class="form-select"
                                        v-model="municipality"
                                        @change="getColony"
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
                                        >{{ error.$message }}</span
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label
                                        for="city_municipality"
                                        class="form-label"
                                        >Colonia</label
                                    >
                                    <select
                                        class="form-select"
                                        v-model="data.colony"
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
                                        >{{ error.$message }}</span
                                    >
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="code_postal" class="form-label"
                                    >Código Postal</label
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa el código postal"
                                    v-model="data.code_postal"
                                />
                                <span
                                    v-for="error in v$.code_postal.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="rfc" class="form-label">RFC</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa el RFC"
                                    v-model="data.rfc"
                                />
                                <span
                                    v-for="error in v$.rfc.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="login_user" class="form-label"
                                    >Nombre usuario</label
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa el nombre de usuario"
                                    v-model="data.login_user"
                                />
                                <span
                                    v-for="error in v$.login_user.$errors"
                                    :key="error.$uid"
                                    class="error-message"
                                    >{{ error.$message }}</span
                                >
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label"
                                    >Contraseña</label
                                >
                                <div class="input-group auth-pass-inputgroup">
                                    <input
                                        :type="
                                            showPassword ? 'text' : 'password'
                                        "
                                        class="form-control"
                                        placeholder="Ingresa la contraseña"
                                        v-model="data.password"
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
                                    >{{ error.$message }}</span
                                >
                            </div>
                        </div>
                    </div>
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

        <div class="position-relative">
            <div class="col-xxl-9 col-lg-8 col-md-7 w-100">
                <div class="auth-bg pt-md-5 p-4 d-flex">
                    <div class="bg-overlay bg-primary"></div>
                    <ul class="bg-bubbles">
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                        <li></li>
                    </ul>
                    <!-- end bubble effect -->
                    <div class="row justify-content-center align-items-center">
                        <div class="col-xl-7">
                            <div class="p-0 p-sm-4 px-xl-0">
                                <div
                                    id="reviewcarouselIndicators"
                                    class="carousel slide"
                                    data-bs-ride="carousel"
                                >
                                    <div
                                        class="carousel-indicators carousel-indicators-rounded justify-content-start ms-0 mb-0"
                                    >
                                        <button
                                            type="button"
                                            data-bs-target="#reviewcarouselIndicators"
                                            data-bs-slide-to="0"
                                            class="active"
                                            aria-current="true"
                                            aria-label="Slide 1"
                                        ></button>
                                        <button
                                            type="button"
                                            data-bs-target="#reviewcarouselIndicators"
                                            data-bs-slide-to="1"
                                            aria-label="Slide 2"
                                        ></button>
                                        <button
                                            type="button"
                                            data-bs-target="#reviewcarouselIndicators"
                                            data-bs-slide-to="2"
                                            aria-label="Slide 3"
                                        ></button>
                                    </div>
                                    <!-- end carouselIndicators -->
                                    <div class="carousel-inner">
                                        <div class="carousel-item active">
                                            <div
                                                class="testi-contain text-white"
                                            >
                                                <i
                                                    class="bx bxs-quote-alt-left text-success display-6"
                                                ></i>

                                                <h4
                                                    class="mt-4 fw-medium lh-base text-white"
                                                >
                                                    “Me siento seguro de
                                                    imponerme el cambio. Es
                                                    mucho más divertido
                                                    progresar que mirar hacia
                                                    atrás.”
                                                </h4>
                                                <div class="mt-4 pt-3 pb-5">
                                                    <div
                                                        class="d-flex align-items-start"
                                                    >
                                                        <div
                                                            class="flex-shrink-0"
                                                        >
                                                            <img
                                                                src="assets/images/users/avatar-1.jpg"
                                                                class="avatar-md img-fluid rounded-circle"
                                                                alt="..."
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex-grow-1 ms-3 mb-4"
                                                        >
                                                            <h5
                                                                class="font-size-18 text-white"
                                                            >
                                                                Richard Drews
                                                            </h5>
                                                            <p
                                                                class="mb-0 text-white-50"
                                                            >
                                                                Web Designer
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="carousel-item">
                                            <div
                                                class="testi-contain text-white"
                                            >
                                                <i
                                                    class="bx bxs-quote-alt-left text-success display-6"
                                                ></i>

                                                <h4
                                                    class="mt-4 fw-medium lh-base text-white"
                                                >
                                                    “Me siento seguro de
                                                    imponerme el cambio. Es
                                                    mucho más divertido
                                                    progresar que mirar hacia
                                                    atrás.”
                                                </h4>
                                                <div class="mt-4 pt-3 pb-5">
                                                    <div
                                                        class="d-flex align-items-start"
                                                    >
                                                        <div
                                                            class="flex-shrink-0"
                                                        >
                                                            <img
                                                                src="assets/images/users/avatar-2.jpg"
                                                                class="avatar-md img-fluid rounded-circle"
                                                                alt="..."
                                                            />
                                                        </div>
                                                        <div
                                                            class="flex-grow-1 ms-3 mb-4"
                                                        >
                                                            <h5
                                                                class="font-size-18 text-white"
                                                            >
                                                                Rosanna French
                                                            </h5>
                                                            <p
                                                                class="mb-0 text-white-50"
                                                            >
                                                                Web Developer
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="carousel-item">
                                            <div
                                                class="testi-contain text-white"
                                            >
                                                <i
                                                    class="bx bxs-quote-alt-left text-success display-6"
                                                ></i>

                                                <h4
                                                    class="mt-4 fw-medium lh-base text-white"
                                                >
                                                    “Me siento seguro de
                                                    imponerme el cambio. Es
                                                    mucho más divertido
                                                    progresar que mirar hacia
                                                    atrás.”
                                                </h4>
                                                <div class="mt-4 pt-3 pb-5">
                                                    <div
                                                        class="d-flex align-items-start"
                                                    >
                                                        <img
                                                            src="assets/images/users/avatar-3.jpg"
                                                            class="avatar-md img-fluid rounded-circle"
                                                            alt="..."
                                                        />
                                                        <div
                                                            class="flex-1 ms-3 mb-4"
                                                        >
                                                            <h5
                                                                class="font-size-18 text-white"
                                                            >
                                                                Ilse R. Eaton
                                                            </h5>
                                                            <p
                                                                class="mb-0 text-white-50"
                                                            >
                                                                Manager
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end carousel-inner -->
                                </div>
                                <!-- end review carousel -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from "vue";
import Swal from "sweetalert2";
import useVuelidate from "@vuelidate/core";
import { required, minLength, email, helpers } from "@vuelidate/validators";
import {
    createUser,
    getStates,
    getMunicipalities,
    getColonies,
} from "../adminstration/user/helper/request.js";

const showPassword = ref(false);
const states = ref([]);
const municipalities = ref([]);
const colonies = ref([]);

const state = ref(null);
const municipality = ref(null);

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
    role: "external",
    password: "",
    is_seller: true,
});

onMounted(async () => {
    states.value = await getStates();
});

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
                "El número de teléfono debe tener al menos 10 digitos",
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
                "El código postal debe contener 5 digitos",
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
            minLength: helpers.withMessage(
                "La contraseña debe contener al menos 8 caracteres",
                minLength(8)
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
        console.log(error.response.data.message);
        Swal.fire(
            "¡Error!",
            "Verifica que el correo electrónico y nombre de usuario no hayan sido registrados antes, ¡Deben ser unicos!",
            "error"
        );
    }
};
</script>

<style>
.form-container {
    margin-right: auto;
    margin-left: 20px;
    padding: 45px;
}

.container-register {
    min-height: 100vh;
    width: 100%;
    display: grid;
    grid-template-columns: 50% 1fr;
}

@media screen and (max-width: 769px) {
    .container-register {
        grid-template-columns: 50% 1fr;
    }
}

.error-message {
    color: red;
}
</style>
