<template>
    <div
        :aria-labelledby="'loadingComponentModal'"
        aria-hidden="true"
        class="modal fade"
        id="loadingComponentModal"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        role="dialog"
    >
        <div :class="`modal-dialog modal-dialog-centered`">
            <div class="modal-content" style="background-color: transparent">
                <div
                    :class="`loading loading-${showWithColor} text-center m-auto`"

                >
                    <div
                        class="lds-roller"
                        style="background-color: transparent"
                    >
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "LoadingComponentModal",
    props: {
        loading: Boolean,
        showWithColor: {
            type: String,
            default: "",
        },
    },
};
</script>

<style scoped></style>
