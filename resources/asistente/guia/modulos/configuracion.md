<!-- Generada por `php artisan asistente:aprender configuracion` desde docs/modulos/configuracion.md, el menú y las pantallas de alta · huella 8ea2939d9510e4733970e7424e5541ec3c80c5ed · 2026-10-08. No editar a mano: corregir la documentación del módulo o agregar una nota en resources/asistente/entrenamiento.json y regenerar. -->
# Configuración
**En pocas palabras:** Ajustes globales del sistema MegaISP.

## Para qué sirve
- Permite al personal administrativo parametrizar el sistema.
- Centraliza catálogos operativos utilizados en otros módulos.
- Administra datos fiscales, métodos de pago y notificaciones.

## Pantallas
- [Configuración](/configuracion) — Ajustes globales del sistema.

## Cómo se hace
### Alta en la pantalla de importación
1. Ingresar a la pantalla [Configuración](/configuracion) y acceder a la sección de importación.

### Alta de nuevas reglas
1. Ingresar a la pantalla [Configuración](/configuracion) y acceder a la sección de reglas.

### Alta de empresa
1. Ingresar a la pantalla [Configuración](/configuracion) y acceder a la sección correspondiente para ingresar los datos de la empresa.

### Alta de campo adicional
1. Ingresar a la pantalla [Configuración](/configuracion) y acceder a la sección de campos adicionales.

### Alta en catálogo de métodos de pago
1. Ingresar a la pantalla [Configuración](/configuracion) y acceder a la sección de métodos de pago.

## Qué datos pide
### Alta en la pantalla /configuracion/tools-import/crear
- **Obligatorios:** Módulo, Archivo a importar
- **Opcionales:** (ninguno)

### Alta en la pantalla /configuracion/rules/create
- **Obligatorios:** (ninguno)

### Alta de empresa (dentro de otra pantalla)
- **Obligatorios:** Nombre de la Empresa, Código Postal, País, Colonia, Correo Electrónico, Teléfono Atención a Clientes, RFC de la Empresa, IVA, Nombre del Banco, Cuenta Bancaria, Comisión a Socios, Enlace a Portal, Calle, Numero Exterior, Numero Interior, Logo, Representante que firma, Domicilio para datos personales
- **Opcionales:** (ninguno)

### Alta de campo adicional (dentro de otra pantalla)
- **Obligatorios:** Nombre del Campo
- **Opcionales:** Titulo del campo, Tipo de Campo, Seleccione clase, Mostrar, Requerido

### Alta de método de pago (dentro de otra pantalla)
- **Obligatorios:** Nombre
- **Opcionales:** (ninguno)

## Quién lo usa
El personal administrativo utiliza este módulo para configurar ajustes del sistema y administrar la información esencial de la empresa.

## Con JARVIS
Por ahora JARVIS te explica cómo hacerlo y te da el enlace a la pantalla.
