<template>
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center">
                <div></div>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li v-for="item in data"
                            :class="`breadcrumb-item ${item.active ?? ''}`"
                        >
                            <a :href="item.a" v-if="item.a">
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
import {ref} from "vue";

export default {
    name: "Breadcrumb",
    props: {
        list: String|Array
    },
    setup(props){
        const data = ref(typeof props.list == 'string' ? JSON.parse(props.list) : props.list);
        return {
            data
        }
    }
}
</script>

<style scoped>

</style>
