<!-- Generada por `php artisan asistente:aprender scheduling` desde docs/modulos/scheduling.md, el menú y las pantallas de alta · huella c0349fb740f7a6b147e741769292895091453e53 · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Planificación y seguimiento de proyectos y tareas
**En pocas palabras:** Es el módulo para organizar proyectos y tareas del ISP en un solo lugar.

## Para qué sirve
- Centraliza la gestión de proyectos y tareas operativas.
- Permite asignar tareas a usuarios y equipos, además de hacer seguimiento en un calendario.
- Agrega notas y archivos adjuntos para cada tarea.
- Facilita la creación de plantillas reutilizables para procesos recurrentes.

## Pantallas
- [Scheduling › Tareas](/scheduling/task) — Aquí puedes crear y gestionar tareas.
- [Scheduling › Proyectos](/scheduling/project) — Permite crear y gestionar proyectos asociados a tareas.
- [Scheduling › Calendario](/scheduling/task/calendar) — Muestra las tareas y eventos programados en un calendario.
- [Scheduling › Plantillas](/scheduling/task-templates) — Permite crear y gestionar plantillas de tareas.

## Cómo se hace
### Crear una nueva tarea
1. Ve a la pantalla [Scheduling › Tareas](/scheduling/task) y selecciona la opción para crear una nueva tarea.

### Crear un nuevo proyecto
1. Accede a la pantalla [Scheduling › Proyectos](/scheduling/project) y elige la opción para crear un nuevo proyecto.

## Qué datos pide
### Alta de tarea ([Scheduling › Tareas](/scheduling/task/crear))
- **Obligatorios:** Título, Descripción, Proyecto, Asignado a, Prioridad (Alta / Media / Baja), Estado (Por Hacer / En Progreso / Terminado / Pospuesto por el Cliente)
- **Opcionales:** Selecciona Plantilla, Cliente, Socio, Dirección, Datos geográficos, Plantilla de la lista de Verificación, Fecha de Inicio, Fecha Fin, Nombre
- **Se llenan solos:** Tiempo estimado (ya viene puesto: 3), Tiempo dedicado (ya viene puesto: 1)

### Alta de proyecto (dentro de la pantalla de proyectos)
- **Obligatorios:** Título, Project Lead
- **Opcionales:** Descripción, Tipo, Socios, Categoría, Flujo de Trabajo

## Quién lo usa
Este módulo es utilizado por técnicos, administrativos y personal de soporte para coordinar actividades y proyectos dentro del ISP.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
