# 🚀 Laravel 10 + Vue.js 3 (Composition API)

Este proyecto es una aplicación web moderna utilizando **Laravel 10** como backend y **Vue.js 3** con Composition API en el frontend.

## ✅ Requisitos del sistema

Asegúrate de tener los siguientes requisitos antes de comenzar:

### 🧰 Requisitos generales

- PHP = 8.2.17
- Node.js = v20.10.0
- npm = 10.2.3
- Git = 2.34.1

## 🛠 Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/tu_usuario/tu_repositorio.git
cd tu_repositorio
```

### 2. Instalar dependencias de Laravel

```bash
composer install
```

### 3. Copiar archivo de entorno

```bash
cp .env.example .env
php artisan key:generate
php artisan storage:link
```

### 4. Configurar base de datos

Edita el archivo `.env` y agrega tus credenciales:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tu_basededatos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña


MIX_VUE_APP_GOOGLEMAPS_KEY=AIzaSyCE7DdEderJ4A7bw6e29NKIlMcmjsVi7u4
MIX_VUE_APP_CENTER_MAP_LATITUDE=19.700586990172585
MIX_VUE_APP_CENTER_MAP_LONGITUDE=-99.07096803188318

```
# 🔧 Configuración de Entorno de Pruebas (TEST) OJOOOOO

```env
APP_ENV=test
APP_DEBUG=true

# Configuración de Base de Datos
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=meganet_test
DB_USERNAME=root
DB_PASSWORD=

# Configuración de Correo
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="${APP_NAME}"
ROUTER_LOCAL="true"
CONECTION_MIKROTIK=true

### 5. Migrar la base de datos

```bash
php artisan migrate
```

### 6. Instalar dependencias de Vue

```bash
npm install
```


## ⚙️ Estructura del frontend

- Vue 3 con Composition API
- Quasar

## 🧪 Servidor de desarrollo

```bash
php artisan serve
```


## 🧼 Testing

```bash
php artisan test
```

