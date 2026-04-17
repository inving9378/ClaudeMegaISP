import { createStore } from 'vuex';
import axios from 'axios';

const store = createStore({
    state: {
        permissions: [],
    },

    mutations: {
        setPermissions(state, permissions) {
            state.permissions = permissions;
        }
    },

    actions: {
        fetchPermissions({ commit }) {
            axios.get('/permissions-auth')
                .then(response => {
                    commit('setPermissions', response.data);
                })
                .catch(error => {
                    console.error('Error al obtener permisos:', error);
                });
        }
    },

    getters: {
        hasPermission: (state) => (permission) => {
            return state.permissions.includes(permission);
        },
    }
});

export default store;