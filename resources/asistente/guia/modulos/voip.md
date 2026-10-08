<!-- Generada por `php artisan asistente:aprender voip` desde docs/modulos/voip.md, el menú y las pantallas de alta · huella ff86d3528875eb495a3d6e95fa7bcd264a7702ba · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# VoIP
**En pocas palabras:** Módulo para gestionar troncales SIP de manera centralizada.

## Para qué sirve
- Administra la telefonía IP y troncales SIP de MegaISP.
- Permite crear, editar y eliminar troncales sin intervención manual en el servidor de Asterisk.
- Sincroniza automáticamente la configuración con Asterisk en tiempo real.

## Pantallas
- [VoIP › Troncales](/voip/troncales) — Lista, crea, edita y elimina troncales SIP.
- [VoIP › Extensiones](/voip/extensiones) — Gestiona las extensiones SIP de los empleados.
- [VoIP › Grupos de timbrado](/voip/grupos-timbrado) — Administra grupos para el enrutamiento de llamadas entrantes.
- [VoIP › Asistente IA](/voip/ia-bot) — Configuración y gestión de un asistente de IA telefónico.

## Cómo se hace
### Crear una troncal
1. Acceder a la pantalla [VoIP › Troncales](/voip/troncales) y seleccionar la opción para crear una nueva troncal.

### Editar una troncal
1. Acceder a la pantalla [VoIP › Troncales](/voip/troncales), seleccionar la troncal que desea editar y realizar los cambios necesarios.

### Eliminar una troncal
1. Acceder a la pantalla [VoIP › Troncales](/voip/troncales) y seleccionar la troncal que desea eliminar.

## Qué datos pide
### Alta de troncal ([VoIP › Troncales](/voip/troncales))
- **Obligatorios:** Nombre, Tipo, Dirección, Host
- **Opcionales:** Ninguno

### Alta de extensión ([VoIP › Extensiones](/voip/extensiones))
- **Obligatorios:** Número, Nombre, Secret
- **Opcionales:** Ninguno

### Alta de grupo de timbrado ([VoIP › Grupos de timbrado](/voip/grupos-timbrado))
- **Obligatorios:** Nombre
- **Opcionales:** Ninguno

## Quién lo usa
Es utilizado por operadores y técnicos de red que gestionan servicios de telefonía IP para los clientes y para la infraestructura interna de MegaISP.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
