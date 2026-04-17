<template>
    <div class="modal-body m-0" style="min-height: 500px">
        <!-- Input de archivo oculto -->
        <input
            type="file"
            ref="fileInput"
            @change="handleFileUpload"
            style="display: none"
            multiple
            accept="image/*"
        >

        <div v-if="mediaItems.length === 0" class="text-center py-5">
            No hay imágenes disponibles
        </div>
        <div v-else class="row">
            <div
                v-for="(media, index) in mediaItems"
                :key="index"
                class="col-md-4 mb-3"
            >
                <div class="card">
                    <img
                        :src="`${url_base}/${media.url}`"
                        class="card-img-top img-fluid"
                        style="max-height: 200px; object-fit: contain"
                        alt="Imagen del ítem"
                    />
                    <div class="card-body">
                        <button
                            class="btn btn-sm btn-danger"
                            @click="deleteMedia(media.id)"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button
            type="button"
            class="btn btn-secondary mr-3"
            @click="closeModal"
        >
            Cerrar
        </button>
        <button class="btn btn-primary" type="button" @click="uploadMedia">
            Subir nueva imagen
        </button>
    </div>
</template>

<script>
import { onMounted, ref, watch, reactive } from "vue";
import Swal from "sweetalert2";
import { idItem } from "../comun_variables";

export default {
    name: "MediaItem",
    props: {
        url_base: String,
    },
    emits: ["close-modal"],
    components: {},
    setup(props, { emit }) {
        const mediaItems = reactive([]);
        const fileInput = ref(null);
        const isLoading = ref(false);

        // Cargar imágenes cuando el componente se monta
        onMounted(() => {
            getMediaByItem();
        });

        watch(
            () => idItem.value,
            () => {
                getMediaByItem();
            }
        );

        const getMediaByItem = async () => {
            if (idItem.value == null) return;
            try {
                isLoading.value = true;
                const response = await axios.get(
                    `/inventory/inventory_item_stock/get_media_by_item/${idItem.value}`
                );
                mediaItems.length = 0; // Limpiar el array reactivo
                mediaItems.push(...response.data.media); // Agregar los nuevos elementos
            } catch (error) {
                console.error("Error al obtener medios:", error);
                Swal.fire(
                    "Error",
                    "No se pudieron cargar las imágenes",
                    "error"
                );
            } finally {
                isLoading.value = false;
            }
        };

        const deleteMedia = async (mediaId) => {
            try {
                const result = await Swal.fire({
                    title: "¿Estás seguro?",
                    text: "Esta imagen se eliminará permanentemente",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Sí, eliminar",
                });

                if (result.isConfirmed) {
                    await axios.delete(
                        `/inventory/inventory_item_stock/delete_media/${mediaId}`
                    );
                    await getMediaByItem(); // Recargar las imágenes
                    Swal.fire(
                        "Eliminado",
                        "La imagen ha sido eliminada",
                        "success"
                    );
                }
            } catch (error) {
                console.error("Error al eliminar medio:", error);
                Swal.fire("Error", "No se pudo eliminar la imagen", "error");
            }
        };

        const uploadMedia = () => {
            // Disparar el click en el input file oculto
            fileInput.value.click();
        };

        const handleFileUpload = async (event) => {
            const files = event.target.files;
            if (!files.length || !idItem.value) return;

            // Validar tamaño y tipo de archivos antes de subir
            const MAX_SIZE = 5 * 1024 * 1024; // 5MB
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];

            for (let i = 0; i < files.length; i++) {
                if (!validTypes.includes(files[i].type)) {
                    Swal.fire(
                        "Error",
                        "Solo se permiten imágenes (JPEG, PNG, GIF)",
                        "error"
                    );
                    return;
                }

                if (files[i].size > MAX_SIZE) {
                    Swal.fire(
                        "Error",
                        `La imagen ${files[i].name} es demasiado grande (máx. 5MB)`,
                        "error"
                    );
                    return;
                }
            }

            const formData = new FormData();
            for (let i = 0; i < files.length; i++) {
                formData.append("files[]", files[i]);
            }
            formData.append("item_id", idItem.value);

            try {
                isLoading.value = true;
                const response = await axios.post(
                    "/inventory/inventory_item_stock/upload_media",
                    formData,
                    {
                        headers: {
                            "Content-Type": "multipart/form-data",
                        },
                    }
                );

                await getMediaByItem(); // Recargar las imágenes
                Swal.fire("Éxito", "Imágenes subidas correctamente", "success");

                // Resetear el input file para permitir subir el mismo archivo otra vez
                event.target.value = '';
            } catch (error) {
                console.error("Error al subir medios:", error);
                let errorMessage = "No se pudieron subir las imágenes";

                if (error.response && error.response.data.message) {
                    errorMessage = error.response.data.message;
                }

                Swal.fire("Error", errorMessage, "error");
            } finally {
                isLoading.value = false;
            }
        };

        const closeModal = () => {
            emit("close-modal");
        };

        return {
            closeModal,
            idItem,
            mediaItems,
            deleteMedia,
            uploadMedia,
            fileInput,
            handleFileUpload,
            isLoading
        };
    },
};
</script>

<style scoped>
.card {
    height: 100%;
}
</style>
