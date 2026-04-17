<template>
    <div class="row">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5>Vista previa</h5>
                    <hr />

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
                                        :src="'/images/perfil.png'"
                                    />
                                </div>
                                <h3 class="name-credential text-uppercase">
                                    Jhon Doe
                                </h3>
                                <h3 class="title-user">Vendedor</h3>
                                <p class="credential-text">
                                    Teléfono: 234 567 4567
                                </p>
                                <p class="credential-text">
                                    Correo electrónico: jhon@correo.com
                                </p>
                                <p class="credential-text">
                                    RFC: JHON881203GM1
                                </p>
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
                                        Av. Hda La Purisima Mz3 Lt 54 Casa A
                                        Fracc. Ex Hacienda Santa Ines Nextlalpan
                                        Edo de Mexico, CP 55796.
                                    </p>

                                    <p class="text-center signature"></p>
                                    <p class="text-center firm">Firma</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form enctype="multipart/form-data" @submit="updateFront">
                        <div>
                            <h5>Actualizar frente de la credencial</h5>
                            <hr />
                            <div class="mt-3">
                                <label for="front" class="form-label"
                                    >Frontal de credencial (.png, .jpg, .jpeg),
                                    dimensiones recomendadas (alto: 900px,
                                    ancho: 600px).
                                </label>
                                <input
                                    type="file"
                                    class="form-control"
                                    accept=".png, .jpg, .jpeg"
                                    @change="onChangeFront"
                                />
                            </div>
                            <div class="mt-3 d-flex justify-content-end">
                                <button class="btn btn-primary" type="submit">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <form enctype="multipart/form-data" @submit="updateBack">
                        <div>
                            <h5>Actualizar reverso de credencial</h5>
                            <hr />
                            <div class="mt-3">
                                <label for="front" class="form-label"
                                    >Reverso de credencial (.png, .jpg, .jpeg),
                                    dimensiones recomendadas (alto: 900px,
                                    ancho: 600px).</label
                                >
                                <input
                                    type="file"
                                    class="form-control"
                                    accept=".png, .jpg, .jpeg"
                                    @change="onChangeBack"
                                />
                            </div>
                            <div class="mt-3 d-flex justify-content-end">
                                <button class="btn btn-primary" type="submit">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <form enctype="multipart/form-data" @submit="updateLogo">
                        <div>
                            <h5>Actualizar logo de credencial</h5>
                            <hr />
                            <div class="mt-3">
                                <label for="front" class="form-label"
                                    >Logo de credencial (.png, .jpg, .jpeg),
                                    dimensiones recomendadas (alto: 600px,
                                    ancho: 2000px).</label
                                >
                                <input
                                    type="file"
                                    class="form-control"
                                    accept=".png, .jpg, .jpeg"
                                    @change="onChangeLogo"
                                />
                            </div>
                            <div class="mt-3 d-flex justify-content-end">
                                <button class="btn btn-primary" type="submit">
                                    Actualizar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import Swal from "sweetalert2";
import {
    getImageFront,
    getImageBack,
    getImageLogo,
    updateCredential,
} from "../helper/request.js";

const dataFront = reactive({
    image: "",
    type: "",
});
const dataBack = reactive({
    image: "",
    type: "",
});
const dataLogo = reactive({
    image: "",
    type: "",
});

const data = ref({
    image: "",
    type: "",
});

const imgFront = ref([]);
const imgBack = ref([]);
const imgLogo = ref([]);

onMounted(async () => {
    imgFront.value = await getImageFront();
    imgBack.value = await getImageBack();
    imgLogo.value = await getImageLogo();
});

const updateFront = async (e) => {
    e.preventDefault();

    try {
        let formData = new FormData();
        formData.append("image", dataFront.image);
        formData.append("type", dataFront.type);

        const response = await updateCredential(formData);
        Swal.fire("¡Actualizado!", response.message, "success");
        imgFront.value = await getImageFront();
        e.target.reset();
    } catch (error) {
        console.log(error);
        Swal.fire("Error", "Hubo un error al actualizar la imagen", "error");
    }
};

const updateBack = async (e) => {
    e.preventDefault();

    try {
        let formData = new FormData();
        formData.append("image", dataBack.image);
        formData.append("type", dataBack.type);

        const response = await updateCredential(formData);
        Swal.fire("¡Actualizado!", response.message, "success");
        imgBack.value = await getImageBack();
        e.target.reset();
    } catch (error) {
        console.log(error);
        Swal.fire("Error", "Hubo un error al actualizar la imagen", "error");
    }
};

const updateLogo = async (e) => {
    e.preventDefault();

    try {
        let formData = new FormData();
        formData.append("image", dataLogo.image);
        formData.append("type", dataLogo.type);

        const response = await updateCredential(formData);
        Swal.fire("¡Actualizado!", response.message, "success");
        imgLogo.value = await getImageLogo();
        e.target.reset();
    } catch (error) {
        console.log(error);
        Swal.fire("Error", "Hubo un error al actualizar la imagen", "error");
    }
};

const onChangeFront = (e) => {
    dataFront.image = e.target.files[0];
    dataFront.type = "frontal";
};

const onChangeBack = (e) => {
    dataBack.image = e.target.files[0];
    dataBack.type = "reverso";
};

const onChangeLogo = (e) => {
    dataLogo.image = e.target.files[0];
    dataLogo.type = "logo";
};
</script>

<style scoped>
.credential {
    width: 280px;
    height: 500px;
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
    width: 110px;
    height: 110px;
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
    margin-top: 70px;
    border-bottom: 1px solid black;
    width: 180px;
    margin-left: auto;
    margin-right: auto;
}

.firm {
    font-size: 1.1rem;
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
        padding-top: 0px;
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
