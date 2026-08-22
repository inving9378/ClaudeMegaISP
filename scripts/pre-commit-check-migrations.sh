#!/bin/sh
# Item #1018 — valida las migraciones en staging antes de commitear: sin ruta a main
# (item #534) y sin operaciones destructivas sin excepción de contracción madura.
#
# Instalación OPCIONAL (no se auto-instala, cada quien decide si lo quiere):
#   ln -sf ../../scripts/pre-commit-check-migrations.sh .git/hooks/pre-commit
#
# Sin migraciones en staging, no hace nada (exit 0 inmediato).

staged_migrations=$(git diff --cached --name-only --diff-filter=ACM -- '*/migrations/*.php' 'database/migrations/*.php')

if [ -z "$staged_migrations" ]; then
    exit 0
fi

php artisan megaisp:check-migrations
exit $?
