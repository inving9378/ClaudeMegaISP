<template>
    <div :class="`${property.class_col === 'full' ? 'col-12' : 'col-6 partial-class-field'}`">
        <form>
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="customFile" />
                <label class="custom-file-label" for="customFile"
                    >Seleccione el archivo</label
                >
            </div>
        </form>
    </div>
</template>

<script>
export default {
    name: "InputUploadFile",
    }
</script>

<style scoped></style>
