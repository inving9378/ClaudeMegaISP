export default {
    beforeMount(el, binding, vnode) {
        const permission = binding.value;
        const store = binding.instance.$store;
    
        if (store.state.permissions.length === 0) {
            console.log("Permisos aún no cargados");
            return; // Salir temprano si los permisos no se han cargado
        }
    
        if (!store.getters.hasPermission(permission)) {
            console.log("Permiso no encontrado, ocultando elemento.");
            el.style.display = 'none';
        } else {
            console.log("Permiso encontrado, mostrando elemento.");
        }
    }
};

