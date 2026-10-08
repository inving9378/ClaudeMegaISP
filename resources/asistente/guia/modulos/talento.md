<!-- Generada por `php artisan asistente:aprender talento` desde docs/modulos/talento.md, el menú y las pantallas de alta · huella 96e920c409c326ece0d03e6d904aa30b6c71e8de · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Talento
**En pocas palabras:** Gestión del personal de campo y sus labores.

## Para qué sirve
- Administra el ciclo de vida de colaboradores como técnicos y vendedores.
- Facilita la visualización y gestión de órdenes de trabajo, asistencias y pagos.
- Permite el acceso a información relevante a través de la app móvil y el Portal Técnico Web.
- Controla la formación y las penalizaciones de los colaboradores.

## Pantallas
- [Talento › Colaboradores](/talento) — Gestión de colaboradores, altas y asignaciones.
- [Talento › Catálogo de puestos](/talento/puestos) — Registro y administración de puestos de trabajo.
- [Talento › Niveles](/talento/niveles) — Control de los niveles dentro de la organización.
- [Talento › Escalafón](/talento/escalafon) — Visualización de escalafones y puntuaciones de colaboradores.
- [Talento › Mapa en vivo](/talento/mapa-en-vivo) — Monitoreo en tiempo real del personal en campo.
- [Talento › Sitios de checada](/talento/sitios) — Administración de sitios para checada de colaboradores.
- [Talento › Roadmap](/talento/roadmap) — Planificación y visualización de proyectos en curso.

## Cómo se hace
### Alta de colaborador
1. Se hace en [Talento › Colaboradores](/talento).

### Alta de puesto
1. Se hace en [Talento › Catálogo de puestos](/talento/puestos).

### Alta de nivel
1. Se hace en [Talento › Niveles](/talento/niveles).

### Alta en escalafón
1. Se hace en [Talento › Escalafón](/talento/escalafon).

### Alta de sitio de checada
1. Se hace en [Talento › Sitios de checada](/talento/sitios).

### Alta de orden de trabajo
1. Se hace en [Talento › Colaboradores](/talento).

### Alta de inspección de calidad
1. Se hace en [Talento › Mapa en vivo](/talento/mapa-en-vivo).

### Alta de curso de formación
1. Se hace en [Talento › Roadmap](/talento/roadmap).

## Qué datos pide
### Alta de colaborador 
- **Obligatorios:** User, Type, Status
- **Opcionales:** Ninguno

### Alta de puesto 
- **Obligatorios:** Colaborador, Type
- **Opcionales:** Ninguno

### Alta de sitio de checada 
- **Obligatorios:** Nombre, Type, Latitude, Longitude, Radius m
- **Opcionales:** Ninguno

### Alta de orden de trabajo 
- **Obligatorios:** Caja ref, Baseline power dbm
- **Opcionales:** Ninguno

### Alta de inspección de calidad 
- **Obligatorios:** Colaborador, Fecha, Work order ids
- **Opcionales:** Ninguno

### Alta en escalafón 
- **Obligatorios:** Nombre, Status
- **Opcionales:** Ninguno

### Alta de curso de formación 
- **Obligatorios:** Colaborador, Type
- **Opcionales:** Ninguno

### Alta de evaluación de curso 
- **Obligatorios:** Nombre
- **Opcionales:** Ninguno

## Quién lo usa
Este módulo es utilizado por el personal de Recursos Humanos y supervisores para gestionar a los colaboradores de campo. También es accesible para los colaboradores a través de la app móvil y el portal.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
