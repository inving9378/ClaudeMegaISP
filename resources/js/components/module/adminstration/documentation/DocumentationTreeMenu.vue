<template>
    <div class="documentation-tree">
        <!-- Loading -->
        <div v-if="loading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <span class="ms-2 text-muted small">Cargando documentación...</span>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="alert alert-warning alert-sm py-2">
            <i class="fas fa-exclamation-triangle me-2"></i>
            {{ error }}
        </div>

        <!-- Árbol de menús -->
        <div v-else-if="menus.length === 0" class="text-muted text-center py-4">
            <i class="far fa-folder-open fa-2x mb-2 d-block opacity-50"></i>
            No hay documentación disponible
        </div>

        <div v-else class="metismenu list-unstyled" id="side-menu-docs">
            <li 
                v-for="menu in menus" 
                :key="menu.id" 
                class="menu-item"
                :class="{ 'mm-active': expandedMenus.includes(menu.id) }"
            >
                <!-- Header del Menú (colapsable) -->
                <a 
                    href="javascript: void(0);"
                    class="has-arrow"
                    :class="{ 'mm-active': expandedMenus.includes(menu.id) }"
                    @click.prevent="toggleMenu(menu.id)"
                >
                    <i class="fas fa-folder icon-nav"></i>
                    <span>{{ menu.title }}</span>
                </a>

                <!-- Submenús (desplegable) -->
                <ul 
                    class="sub-menu mm-collapse"
                    :class="{ 'mm-show': expandedMenus.includes(menu.id) }"
                    aria-expanded="false"
                >
                    <li v-if="!menu.submenus || menu.submenus.length === 0">
                        <a href="javascript: void(0);" class="text-muted">
                            <small><i class="fas fa-info-circle me-1"></i> Sin submenús</small>
                        </a>
                    </li>
                    
                    <li 
                        v-for="submenu in menu.submenus" 
                        :key="submenu.id"
                    >
                        <a 
                            href="javascript: void(0);"
                            @click.prevent="navigateToSubmenu(submenu.id)"
                            :title="submenu.description"
                        >
                            <span>
                                <small><i class="far fa-file-alt me-1"></i></small>
                                {{ submenu.title }}
                            </span>
                        </a>
                    </li>
                </ul>
            </li>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';

export default {
    name: 'DocumentationTreeMenu',
    setup() {
        const menus = ref([]);
        const loading = ref(true);
        const error = ref(null);
        const expandedMenus = ref([]);

        
        const loadTree = async () => {
            try {
                loading.value = true;
                error.value = null;
                
                const response = await axios.get('/administracion/documentation/documentation_menu/tree');
                
                if (response.data.success) {
                    menus.value = response.data.data;                    
                } else {
                    error.value = response.data.message || 'Error al cargar datos';
                }
            } catch (err) {
                console.error('Error cargando documentación:', err);
                error.value = 'No se pudo conectar con el servidor';
            } finally {
                loading.value = false;
            }
        };

        
        const toggleMenu = (menuId) => {
            const index = expandedMenus.value.indexOf(menuId);
            if (index === -1) {
                expandedMenus.value.push(menuId);
            } else {
                expandedMenus.value.splice(index, 1);
            }
        };

        
        const navigateToSubmenu = (submenuId) => {
            
            const offcanvasEl = document.getElementById('offcanvasRight');
            if (offcanvasEl) {
                const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                if (bsOffcanvas) {
                    bsOffcanvas.hide();
                }
            }
            
            
            window.location.href = `/administracion/documentation/documentation_submenu/${submenuId}`;
        };

        onMounted(() => {
            loadTree();
        });

        return {
            menus,
            loading,
            error,
            expandedMenus,
            toggleMenu,
            navigateToSubmenu
        };
    }
};
</script>

<style scoped>


.documentation-tree {
    padding: 0;
}


#side-menu-docs {
    list-style: none;
    padding: 0;
    margin: 0;
}

#side-menu-docs .menu-item {
    margin-bottom: 8px;
    position: relative;
}


#side-menu-docs > li > a {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.25rem;
    color: #545a6d;
    font-size: 15px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.4s;
    border-radius: 0;
    position: relative;
}


#side-menu-docs > li > a .icon-nav {
    min-width: 1.5rem;
    font-size: 1rem;
    margin-right: 0.75rem;
    color: #6c757d;
    transition: all 0.4s;
    text-align: center;
}


#side-menu-docs > li > a:hover {
    color: #556ee6;
    background-color: transparent;
    text-decoration: none;
}

#side-menu-docs > li > a:hover .icon-nav {
    color: #556ee6;
}


#side-menu-docs > li.mm-active > a {
    color: #556ee6;
    background-color: #f5f6f8;
}

#side-menu-docs > li.mm-active > a .icon-nav {
    color: #556ee6;
}


#side-menu-docs > li > a.has-arrow::after {
    content: "\f105"; /* fa-angle-right */
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    display: block;
    position: absolute;
    right: 1.25rem;
    transition: transform 0.2s;
    font-size: 1rem;
    color: #6c757d;
}


#side-menu-docs > li.mm-active > a.has-arrow::after {
    transform: rotate(90deg);
    color: #556ee6;
}


#side-menu-docs ul.sub-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    background-color: #f8f9fa;
}

#side-menu-docs ul.sub-menu li a {
    display: flex;
    align-items: center;
    padding: 0.6rem 1.5rem 0.6rem 3.5rem;
    color: #545a6d;
    font-size: 14px;
    font-weight: 400;
    text-decoration: none;
    transition: all 0.4s;
    position: relative;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

#side-menu-docs ul.sub-menu li a:hover {
    color: #556ee6;
    background-color: transparent;
}

#side-menu-docs ul.sub-menu li a:hover small i {
    color: #556ee6;
}

#side-menu-docs ul.sub-menu li a small {
    color: #6c757d;
    margin-right: 0.25rem;
    transition: all 0.4s;
}

.mm-collapse {
    display: none;
}

.mm-collapse.mm-show {
    display: block;
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 500px;
    }
}

@media (max-width: 576px) {
    #side-menu-docs > li > a {
        padding: 0.625rem 1rem;
        font-size: 14px;
    }
    
    #side-menu-docs ul.sub-menu li a {
        padding: 0.5rem 1rem 0.5rem 2.5rem;
    }
}


.documentation-tree::-webkit-scrollbar {
    width: 6px;
}

.documentation-tree::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.documentation-tree::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.documentation-tree::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>