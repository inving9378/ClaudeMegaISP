#!/usr/bin/env bash
#
# install_manual_module.sh — Instala el módulo addon-manual (Manual de Usuario).
#
# Requisitos previos: estos archivos deben estar en el árbol del proyecto
# (ya creados por el commit del módulo):
#
#   app/Modules/Addons/Manual/module.json
#   app/Modules/Addons/Manual/ModuleServiceProvider.php
#   app/Modules/Addons/Manual/routes.php
#   app/Modules/Addons/Manual/Controllers/ManualController.php
#   app/Modules/Addons/Manual/Models/ManualSection.php
#   app/Modules/Addons/Manual/Services/ManualGeneratorService.php
#   app/Modules/Addons/Manual/migrations/2026_05_21_120000_create_manual_sections_table.php
#   app/Modules/Addons/Manual/migrations/2026_05_21_120100_add_permissions_manual.php
#   app/Modules/Addons/Manual/views/index.blade.php
#   resources/js/components/module/manual/ManualIndex.vue
#
# Y los siguientes archivos deben ya tener las entradas correspondientes:
#   config/services.php             → bloque 'anthropic' => [...]
#   config/route_permission.php     → 'manual_view' y 'manual_generate'
#   resources/js/app.js             → import ManualIndex + registro 'manual-index'
#   app/Modules/Core/Layout/views/topbar.blade.php → botón <a href="/manual">
#
# .env debe contener:
#   CLAUDE_API_KEY=sk-ant-...
#   CLAUDE_MODEL=claude-sonnet-4-20250514         # opcional, este es el default
#   CLAUDE_API_ENDPOINT=https://api.anthropic.com/v1/messages   # opcional
#
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/var/www/megaisp}"

if [[ ! -f "$PROJECT_DIR/artisan" ]]; then
    echo "ERROR: no se encontró artisan en $PROJECT_DIR" >&2
    exit 1
fi

cd "$PROJECT_DIR"

echo "==> Verificando archivos del módulo..."
required_files=(
    "app/Modules/Addons/Manual/module.json"
    "app/Modules/Addons/Manual/ModuleServiceProvider.php"
    "app/Modules/Addons/Manual/routes.php"
    "app/Modules/Addons/Manual/Controllers/ManualController.php"
    "app/Modules/Addons/Manual/Models/ManualSection.php"
    "app/Modules/Addons/Manual/Services/ManualGeneratorService.php"
    "app/Modules/Addons/Manual/migrations/2026_05_21_120000_create_manual_sections_table.php"
    "app/Modules/Addons/Manual/migrations/2026_05_21_120100_add_permissions_manual.php"
    "app/Modules/Addons/Manual/views/index.blade.php"
    "resources/js/components/module/manual/ManualIndex.vue"
)
missing=0
for f in "${required_files[@]}"; do
    if [[ ! -f "$f" ]]; then
        echo "  FALTA: $f" >&2
        missing=$((missing+1))
    fi
done
if [[ $missing -gt 0 ]]; then
    echo "ERROR: faltan $missing archivos del módulo. Aborto." >&2
    exit 1
fi
echo "  OK"

echo "==> Verificando CLAUDE_API_KEY en .env..."
if ! grep -q "^CLAUDE_API_KEY=" .env 2>/dev/null; then
    echo "  ADVERTENCIA: CLAUDE_API_KEY no está definido en .env."
    echo "  Agrega 'CLAUDE_API_KEY=sk-ant-...' antes de regenerar el manual."
else
    echo "  OK"
fi

echo "==> Verificando bloque 'anthropic' en config/services.php..."
if ! grep -q "'anthropic'" config/services.php; then
    echo "ERROR: config/services.php no contiene el bloque 'anthropic'." >&2
    exit 1
fi
echo "  OK"

echo "==> Ejecutando migraciones..."
php artisan migrate --force

echo "==> Limpiando caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "==> Compilando assets (npm run dev)..."
npm run dev

echo ""
echo "============================================================"
echo "  Módulo Manual de Usuario instalado correctamente."
echo "  URL:  /manual"
echo "  API:  GET  /api/manual/sections"
echo "        GET  /api/manual/sections/{slug}"
echo "        POST /api/manual/generate    (rol DESARROLLADOR)"
echo ""
echo "  Para generar el manual por primera vez:"
echo "    1) Inicia sesión con un usuario rol DESARROLLADOR."
echo "    2) Entra a /manual y pulsa 'Regenerar Manual'."
echo "============================================================"
