<!-- Generada por `php artisan asistente:aprender gestionred` desde docs/modulos/gestionred.md, el menú y las pantallas de alta · huella 19e488ee7242512d16793459ebc3fd817745f8d5 · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Gestión de Red
**En pocas palabras:** Es el panel donde se controlan routers, OLTs y se organizan direcciones IP.

## Para qué sirve
- Permite al equipo técnico administrar routers MikroTik y su conexión a clientes.
- Facilita la gestión de OLTs y ONUs de fibra para monitorear y realizar acciones sobre ellos.
- Organiza las subredes IPv4 e IPs asignadas a los clientes.

## Pantallas
- [Routers](/red/router/listar) — Controla y lista los routers MikroTik.
- [Red IPv4](/red/ipv4/listar) — Administra las direcciones IP asignadas a los clientes.
- [OLTs](/olts) — Muestra un listado y controla las OLTs y ONUs de fibra óptica.
- [Mapa de red](/mapa-red) — Visualiza la infraestructura de red de fibra.

## Cómo se hace
### Alta de un router
1. Ve a la pantalla [Routers](/red/router/crear) y completa los campos requeridos.

### Alta de una dirección IP
1. Accede a la pantalla [Red IPv4](/red/ipv4/crear) y completa los campos obligatorios.

### Sincronización de Mikrotik
1. Dirígete a la pantalla [Sync Mikrotik](/red/mikrotik-sync) para realizar la sincronización.

### Monitorear OLTs y ONUs
1. Entra a la pantalla [OLTs](/olts) y revisa el listado para monitorear el estado de los dispositivos.

## Qué datos pide
### Alta de dirección IP ([Red IPv4](/red/ipv4/crear))
- **Obligatorios:** BM, Título, Comentario, Router, Tipo de uso (Estatico / Pool)
- **Opcionales:** Red, Permitir el Uso del network y el Broadcast, Ubicación, Categoría de Red, Tipo de Red

### Alta de router ([Routers](/red/router/crear))
- **Obligatorios:** Título, IP/Host
- **Opcionales:** Tipo de Nas, Vendedor/Modelo, Socios, Ubicación, Dirección física, Autorización / Contabilidad, Radius Secreto, NAS IP, Pool

## Quién lo usa
Este módulo es utilizado por el equipo técnico para administrar la infraestructura de red, incluyendo routers, OLTs y la asignación de direcciones IP.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
