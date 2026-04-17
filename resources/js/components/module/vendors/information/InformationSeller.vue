<template>
    <div class="row" v-if="activeTab === '#navs-pills-justified-information'">
        <div class="col-md-6">
            <div class="card mb-4 h-100">
                <h5 class="card-header">Información Principal</h5>
                <div class="card-body demo-vertical-spacing demo-only-element">
                    <div class="px-4">
                        <div class="mb-3">
                            <label class="form-label"
                                >Nombre(s): {{ seller.name }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Apellido Paterno:
                                {{ seller.father_last_name }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Apellido Materno:
                                {{ seller.mother_last_name }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Correo electrónico: {{ seller.email }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Teléfono: {{ seller.phone }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Dirección: {{ seller.address }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Municipio:
                                {{ seller.city_municipality }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Estado: {{ seller.state_country }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >Código Postal: {{ seller.code_postal }}</label
                            >
                        </div>
                        <div class="mb-3">
                            <label class="form-label"
                                >RFC: {{ seller.rfc }}</label
                            >
                        </div>
                        <hr />
                        <form @submit.prevent="update">
                            <div class="mb-3">
                                <label class="form-label">Tipo</label>
                                <select
                                    v-model="data.type_id"
                                    class="form-select"
                                    aria-label="Default select example"
                                >
                                    <option
                                        v-for="typeS in typesSeller"
                                        :key="typeS.id"
                                        :value="typeS.id"
                                    >
                                        {{ typeS.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select
                                    v-model="data.status_id"
                                    class="form-select"
                                    aria-label="Default select example"
                                >
                                    <option
                                        v-for="status in statuses"
                                        :key="status.id"
                                        :value="status.id"
                                    >
                                        {{ status.name }}
                                    </option>
                                </select>
                            </div>
                            <hr />
                            <div class="d-md-flex justify-content-center mt-2">
                                <button type="submit" class="btn btn-primary">
                                    Actualizar Información
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4 h-100">
                <h5 class="card-header">Formato de credencial</h5>
                <div
                    class="card-body demo-vertical-spacing demo-only-element d-flex flex-column align-items-center justify-content-center"
                >
                    <div
                        class="card background-credential-front m-2 p-2 credential"
                        :style="{
                            backgroundImage:
                                imgFront && imgFront.name
                                    ? `url(/credencial/${imgFront.name})`
                                    : 'url(/images/gafete-front.png)',
                        }"
                    >
                        <div class="card-body">
                            <img
                                class="image-logo"
                                :src="
                                    imgLogo && imgLogo.name
                                        ? `/credencial/${imgLogo.name}`
                                        : '/images/logo_meganet.jpg'
                                "
                                alt="logo de credencial"
                            />
                            <div class="d-flex justify-content-center mt-4">
                                <img
                                    class="image-perfil"
                                    :src="
                                        seller.photography
                                            ? `/perfiles/${seller.photography}`
                                            : '/images/perfil.png'
                                    "
                                />
                            </div>
                            <h3 class="name-credential text-uppercase">
                                {{ seller.name }}
                                {{ seller.father_last_name }}
                                {{ seller.mother_last_name }}
                            </h3>
                            <h3 class="title-user">Vendedor</h3>
                            <p class="credential-text">
                                Teléfono: {{ seller.phone }}
                            </p>
                            <p class="credential-text">
                                Correo electrónico: {{ seller.email }}
                            </p>
                            <p class="credential-text">RFC: {{ seller.rfc }}</p>
                        </div>
                    </div>

                    <div
                        class="card background-credential-back m-2 p-2 example credential"
                        :style="{
                            backgroundImage:
                                imgBack && imgBack.name
                                    ? `url(/credencial/${imgBack.name})`
                                    : 'url(/images/gafete-back.png)',
                        }"
                    >
                        <div class="card-body example">
                            <img
                                class="image-logo"
                                :src="
                                    imgLogo && imgLogo.name
                                        ? `/credencial/${imgLogo.name}`
                                        : '/images/logo_meganet.jpg'
                                "
                                alt="logo de credencial"
                            />
                            <div class="text-back">
                                <h5 class="text-center">
                                    Dirección de la empresa:
                                </h5>
                                <p class="credential-text-black">
                                    Av. Hda La Purisima Mz3 Lt 54 Casa A Fracc.
                                    Ex Hacienda Santa Ines Nextlalpan Edo de
                                    Mexico, CP 55796.
                                </p>

                                <p class="text-center signature"></p>
                                <p class="text-center firm">Firma</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a
                            class="btn btn-primary"
                            :href="'/vendedores/' + props.id + '/pdf'"
                        >
                            <i class="fas fa-file-download m-1"></i>
                            Descargar credencial
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps, ref, reactive, onMounted, watch } from "vue";
import {
    getById,
    updateSeller,
    getImageFront,
    getImageBack,
    getImageLogo,
    getStatusSeller,
    getTypeSeller,
} from "../helper/request.js";
import Swal from "sweetalert2";
import { activeTab } from "../../sellers/comun_variables.js";

const props = defineProps({
    id: Number,
});

const seller = ref([]);
const data = reactive({
    type_id: null,
    status_id: null,
});

const credential = ref({
    image: "",
    type: "",
});

const imgFront = ref([]);
const imgBack = ref([]);
const imgLogo = ref([]);

const typesSeller = ref([]);
const statuses = ref([]);

const isLoaded = ref(false);

onMounted(async () => {
    if (activeTab.value === "#navs-pills-justified-information") {
        isLoaded.value = true;
        seller.value = await getById(props.id);
        data.type_id = seller.value.type_id;
        data.status_id = seller.value.status_id;
        imgFront.value = await getImageFront();
        imgBack.value = await getImageBack();
        imgLogo.value = await getImageLogo();

        typesSeller.value = await getTypeSeller();
        statuses.value = await getStatusSeller();
    }
});

watch(activeTab, async () => {
    if (activeTab.value === "#navs-pills-justified-information" && !isLoaded.value) {
        seller.value = await getById(props.id);
        data.type_id = seller.value.type_id;
        data.status_id = seller.value.status_id;
        imgFront.value = await getImageFront();
        imgBack.value = await getImageBack();
        imgLogo.value = await getImageLogo();

        typesSeller.value = await getTypeSeller();
        statuses.value = await getStatusSeller();
    }
});

const update = async () => {
    try {
        const response = await updateSeller(props.id, data);
        Swal.fire("¡Actualizado!", response.message, "success");
    } catch (error) {
        console.log(error);
        Swal.fire("Error", "Hubo un error al actualizar el vendedor", "error");
    }
};
</script>

<style scoped>
.credential {
    width: 380px;
    height: 600px;
}

.background-credential-front {
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

.background-credential-back {
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

.image-logo {
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
    object-fit: contain;
    height: 18%;
    width: 100%;
}

.image-perfil {
    border-radius: 50%;
    width: 150px;
    height: 150px;
    border: 5px solid #004790;
    margin-bottom: 10px;
}

.title-credential {
    font-size: 1rem;
    font-weight: bold;
    color: #004790;
    text-align: center;
}

.title-user {
    font-size: 0.8rem;
    color: #fff;
    text-align: center;
    text-transform: uppercase;
}

.name-credential {
    margin-top: 10px;
    font-size: 1.3rem;
    font-weight: bold;
    color: #fff;
    text-align: center;
}

.credential-text {
    font-size: 1.1rem;
    color: #fff;
    text-align: center;
}

.credential-text-black {
    font-size: 1rem;
    color: #504f4f;
    text-align: justify;
}

.text-back {
    margin-top: 30px;
    overflow: auto;
}

.signature {
    margin-top: 150px;
    border-bottom: 1px solid black;
    width: 250px;
    margin-left: auto;
    margin-right: auto;
}

.firm {
    font-size: 1.5rem;
    font-weight: bold;
}

@media (max-width: 1600px) {
    .image-perfil {
        width: 120px;
        height: 120px;
    }

    .title-credential {
        font-size: 1.1rem;
    }

    .name-credential {
        font-size: 1.2rem;
        padding-top: 50px;
    }

    .credential-text,
    .credential-text-black {
        font-size: 0.9rem;
    }

    .signature {
        margin-top: 100px;
        border-bottom: 1px solid black;
        width: 150px;
        margin-left: auto;
        margin-right: auto;
    }

    .firm {
        font-size: 1.2rem;
        font-weight: bold;
    }
}

@media (max-width: 900px) {
    .image-perfil {
        width: 100px;
        height: 100px;
    }

    .title-credential {
        font-size: 1rem;
    }

    .name-credential {
        font-size: 1.1rem;
        padding-top: 0;
        margin-top: 0;
    }

    .credential-text,
    .credential-text-black {
        font-size: 1rem;
    }

    .text-back {
        margin-top: 5px;
        overflow: auto;
    }

    .signature {
        margin-top: 20px;
        border-bottom: 1px solid black;
        width: 100px;
        margin-left: auto;
        margin-right: auto;
    }

    .firm {
        font-size: 1rem;
        font-weight: bold;
    }

    .signature {
        margin-top: 0;
    }
}

@media (max-width: 480px) {
    .image-perfil {
        width: 80px;
        height: 80px;
    }

    .title-credential {
        font-size: 0.8rem;
    }

    .name-credential {
        font-size: 1rem;
    }

    .credential-text,
    .credential-text-black {
        font-size: 0.7rem;
    }

    .signature {
        margin-top: 50px;
    }
}
</style>
