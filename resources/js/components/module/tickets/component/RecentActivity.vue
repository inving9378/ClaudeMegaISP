<template>
        <div class="card">
            <div class="card-body">
                <div class="float-right">
                    <div class="dropdown">
                        <a class="dropdown-toggle" href="#" id="dropdownMenuButton3" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="text-muted">Recent<i class="mdi mdi-chevron-down ml-1"></i></span>
                        </a>

                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton3">
                            <a class="dropdown-item" href="#">Recent</a>
                            <a class="dropdown-item" href="#">By Users</a>
                        </div>
                    </div>
                </div>

                <h4 class="card-title mb-4">Actividad Reciente</h4>

                <ol class="activity-feed mb-0 pl-2" data-simplebar="init" style="max-height: 336px;">
                    <div class="simplebar-wrapper" style="margin: 0px 0px 0px -8px;">
                        <div class="simplebar-height-auto-observer-wrapper"><div class="simplebar-height-auto-observer"></div></div>
                        <div class="simplebar-mask">
                            <div class="simplebar-offset" style="right: -15px; bottom: 0px;">
                                <div class="simplebar-content-wrapper" style="height: auto; overflow: hidden scroll;">
                                    <div class="simplebar-content" style="padding: 0px 0px 0px 8px;">
                                        <li v-for="activity in activities" class="feed-item">
                                            <span class="text-muted mb-1 font-size-13">{{ activity.day }} <small class="d-inline-block ml-1">{{ activity.time }}</small></span>
                                            <span class="ml-3">{{ activity.title }}</span>
                                            <p class="mt-0 mb-0 ml-1">{{ activity.description }} <span class="text-primary">link</span></p>
                                        </li>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="simplebar-placeholder" style="width: auto; height: 466px;"></div>
                    </div>
                    <div class="simplebar-track simplebar-horizontal" style="visibility: hidden;"><div class="simplebar-scrollbar" style="transform: translate3d(0px, 0px, 0px); display: none;"></div></div>
                    <div class="simplebar-track simplebar-vertical" style="visibility: visible;"><div class="simplebar-scrollbar" style="height: 242px; transform: translate3d(0px, 0px, 0px); display: block;"></div></div>
                </ol>
            </div>
        </div>


</template>

<script>

import {reactive} from 'vue';

export default {
    name: "RecentActivity",
    props: {
        list: String
    },
    setup(props){
        const activities = reactive([
            {
                'day': '3 May, 2021',
                'time': '12:21pm',
                'title': 'Este es Titulo 1',
                'description': 'Descripcion de la dfjd asdasd asdasd asdasd asdf 1fdf 1'
            },{
                'day': '4 May, 2021',
                'time': '12:22pm',
                'title': 'Titulo 2',
                'description': 'Descripcion 2'
            },{
                'day': '5 May, 2021',
                'time': '12:23pm',
                'title': 'Titulo 3',
                'description': 'Descripcion 3'
            },{
                'day': '6 May, 2021',
                'time': '12:24pm',
                'title': 'Titulo 4',
                'description': 'Descripcion 4'
            }
        ]);
        return {
            activities
        }
    }
}
</script>

<style scoped>

</style>
