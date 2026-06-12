---
name: megaisp-flujo-trabajo
description: Flujo de trabajo con Irving en MegaISP. Usar al iniciar cualquier sesión o prompt estructurado, al cerrar fases o sub-pasos, y cuando surjan pendientes, bugs o decisiones diferidas.
---

# Flujo de trabajo MegaISP

## Inicio
- Todo prompt estructurado inicia con **Paso 0**: gate de confirmación. NO ejecutar cambios sin confirmación explícita de Irving.
- Si la sesión alcanza el límite de contexto: abrir sesión NUEVA y retomar leyendo el repo. NO activar créditos de 1M, NO cambiar de modelo.

## Durante
- Un commit por sub-paso, `git add` selectivo, mensajes en español.
- Cada pendiente, deuda técnica, bug o decisión diferida que surja se registra INMEDIATAMENTE como item en el módulo Hoja de Ruta (Roadmap), sin esperar a que se pida.

## Cierre de tarea (checklist)
1. `git add` selectivo + commit por sub-paso.
2. Caches limpiados y warm-up ejecutado (`view:cache` + `config:cache`).
3. Permisos sincronizados si se registraron nuevos (`permissions:sync-roles`).
4. Pendientes registrados en Hoja de Ruta.
5. Indicar a Irving qué pantallas validar con screenshot.

## Comunicación
- Respuestas y commits siempre en español.
- Estimaciones honestas y realistas; nunca optimismo sin datos.
