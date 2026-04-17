<template>
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center">
                <div></div>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li 
                            v-for="(item, index) in data"
                            :key="index"
                            :class="['breadcrumb-item', { 'active': item.active }]"
                        >
                            <a v-if="!item.active && item.link" :href="item.link">
                                {{ item.title }}
                            </a>
                            <span v-else>
                                {{ item.title }}
                            </span>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref } from "vue";

export default {
    name: "Breadcrumb",
    props: {
        list: {
            type: [String, Array],
            required: true
        }
    },
    setup(props) {
        const data = ref(
            typeof props.list === 'string' 
                ? JSON.parse(props.list) 
                : props.list
        );
        
        return {
            data
        };
    }
}
</script>

<style scoped>
/* Opcional: estilo para el breadcrumb activo */
.breadcrumb-item.active {
    color: #6c757d;
    pointer-events: none;
}
</style>